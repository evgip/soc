<?php

declare(strict_types=1);

namespace App\Modules\Collections\Services;

use App\Modules\Collections\Exceptions\CollectionValidationException;
use W3a\Core\Storage\StorageManager;
use W3a\Core\Storage\UploadedFile;
use W3a\Core\Storage\FileValidator;
use W3a\Core\Storage\Exceptions\ValidationException;
use W3a\Core\Foundation\Config;

/**
 * Сервис для загрузки обложек коллекций.
 * 
 * Использует ядро StorageManager (современный подход, в отличие от AvatarService).
 * Хранит файлы в распределённой структуре: collections/{Y}/{m}/{hash}.webp
 * 
 * Структура решает проблему "сотни файлов в одной папке":
 * - Год/месяц как подпапки
 * - Hash-имя файла для уникальности
 * - Конвертация в webp для оптимизации размера
 */
class CollectionCoverService
{
    private const DISK_NAME = 'collections';
    private const MAX_SIZE = 5 * 1024 * 1024; // 5 MB
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const JPEG_QUALITY = 85;

    private StorageManager $storage;
    private Config $config;

    public function __construct(StorageManager $storage, Config $config)
    {
        $this->storage = $storage;
        $this->config = $config;
    }

    /**
     * Обработать загрузку обложки: валидация, конвертация в webp, сохранение.
     * 
     * @param array $fileData Массив из $_FILES['cover_file']
     * @param string|null $oldCoverFilename Старая обложка (для удаления)
     * @return string Относительный путь к новому файлу (для сохранения в БД)
     * 
     * @throws CollectionValidationException
     */
    public function handleUpload(array $fileData, ?string $oldCoverFilename = null): string
    {
        try {
            $file = new UploadedFile($fileData);
        } catch (\Throwable $e) {
            throw new CollectionValidationException('Ошибка при получении файла: ' . $e->getMessage());
        }

        // Валидация через FileValidator
        $validator = new FileValidator([
            'mimes'      => self::ALLOWED_MIMES,
            'extensions' => self::ALLOWED_EXTENSIONS,
            'max_size'   => self::MAX_SIZE,
        ]);

        try {
            $validator->validateOrFail($file);
        } catch (ValidationException $e) {
            throw new CollectionValidationException($e->getMessage());
        }

        // Генерируем путь: collections/{Y}/{m}/{hash}.{ext}
        $subPath = $this->generateSubPath($file);
        $fileName = basename($subPath);
        $tempPath = $file->getTempPath();
        $mimeType = $file->getMimeType();

        // Определяем расширение для сохранения
        $saveAsWebp = function_exists('imagewebp') && $mimeType !== 'image/gif';
        $extension = $saveAsWebp ? 'webp' : 'jpg';
        
        // Переименовываем в нужное расширение
        if ($extension !== $file->guessExtension()) {
            $subPath = preg_replace('/\.[^.]+$/', '.' . $extension, $subPath);
        }

        // Получаем абсолютный путь для сохранения
        $disk = $this->storage->disk(self::DISK_NAME);
        $fullPath = $disk->path($subPath);

        // Конвертация + сохранение
        if (!$this->convertAndSave($tempPath, $mimeType, $fullPath, $saveAsWebp)) {
            throw new CollectionValidationException('Не удалось обработать изображение.');
        }

        // Удаляем старую обложку
        if (!empty($oldCoverFilename) && $oldCoverFilename !== $subPath) {
            $this->deleteCover($oldCoverFilename);
        }

        return $subPath;
    }

    /**
     * Удалить обложку с диска.
     */
    public function deleteCover(string $relativePath): bool
    {
        if (empty($relativePath)) {
            return false;
        }

        $disk = $this->storage->disk(self::DISK_NAME);

        if (!$disk->exists($relativePath)) {
            return false;
        }

        $deleted = $disk->delete($relativePath);

        // Попытка удалить пустые родительские директории (год/месяц)
        if ($deleted) {
            $this->cleanupEmptyDirectories($relativePath);
        }

        return $deleted;
    }

    /**
     * Получить публичный URL к обложке.
     */
    public function getCoverUrl(string $relativePath): ?string
    {
        if (empty($relativePath)) {
            return null;
        }

        return $this->storage->disk(self::DISK_NAME)->url($relativePath);
    }

    /**
     * Сгенерировать относительный путь: {Y}/{m}/{hash}.ext
     * 
     * Использует hash от времени + случайных байтов для уникальности.
     * Год/месяц распределяют файлы по папкам.
     */
    private function generateSubPath(UploadedFile $file): string
    {
        $year = date('Y');
        $month = date('m');
        $hash = bin2hex(random_bytes(16)); // 32 hex символа
        $extension = $file->guessExtension();

        return "{$year}/{$month}/{$hash}.{$extension}";
    }

    /**
     * Конвертировать изображение и сохранить в нужном формате.
     * 
     * Для обложек НЕ делаем ресайз (в отличие от аватара),
     * сохраняем оригинальный размер — они будут отображаться
     * через CSS с object-fit: cover.
     */
    private function convertAndSave(string $srcPath, string $mimeType, string $dstPath, bool $saveAsWebp): bool
    {
        $imageInfo = @getimagesize($srcPath);
        if (!$imageInfo) {
            return false;
        }

        // Создаём директорию, если её нет
        $dir = dirname($dstPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
        }

        $srcImage = match ($mimeType) {
            'image/png'     => imagecreatefrompng($srcPath),
            'image/gif'     => imagecreatefromgif($srcPath),
            'image/webp'    => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($srcPath) : false,
            'image/jpeg',
            'image/jpg'     => imagecreatefromjpeg($srcPath),
            default         => false,
        };

        if (!$srcImage) {
            return false;
        }

        // Для PNG с прозрачностью сохраняем альфа-канал
        if ($mimeType === 'image/png') {
            imagesavealpha($srcImage, true);
        }

        // Сохраняем в нужном формате
        if ($saveAsWebp) {
            $result = imagewebp($srcImage, $dstPath, self::JPEG_QUALITY);
        } else {
            // Для GIF или если webp недоступен — сохраняем как JPEG
            // (белый фон для прозрачных PNG/GIF)
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                $width = imagesx($srcImage);
                $height = imagesy($srcImage);
                $flatImage = imagecreatetruecolor($width, $height);
                $bg = imagecolorallocate($flatImage, 255, 255, 255);
                imagefill($flatImage, 0, 0, $bg);
                imagecopy($flatImage, $srcImage, 0, 0, 0, 0, $width, $height);
                $result = imagejpeg($flatImage, $dstPath, self::JPEG_QUALITY);
                imagedestroy($flatImage);
            } else {
                $result = imagejpeg($srcImage, $dstPath, self::JPEG_QUALITY);
            }
        }

        imagedestroy($srcImage);
        return $result;
    }

    /**
     * Удалить пустые родительские директории после удаления файла.
     * Например: если удалили файл в 2026/08/, попробуем удалить 2026/08/,
     * потом 2026/ (если пустые).
     */
    private function cleanupEmptyDirectories(string $relativePath): void
    {
        $disk = $this->storage->disk(self::DISK_NAME);
        $root = $disk->path('');

        $dir = dirname($relativePath);

        // Идём вверх по дереву, удаляя пустые директории
        // Но не выше года (первого уровня) — чтобы не трогать корень
        while ($dir !== '.' && $dir !== '' && substr_count($dir, '/') >= 1) {
            $fullDir = $disk->path($dir);

            if (!is_dir($fullDir)) {
                break;
            }

            $items = array_diff(scandir($fullDir) ?: [], ['.', '..']);
            if (!empty($items)) {
                break; // директория не пустая — останавливаемся
            }

            @rmdir($fullDir);

            // Переходим к родителю
            $dir = dirname($dir);
        }
    }
}