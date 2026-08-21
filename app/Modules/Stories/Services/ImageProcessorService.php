<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Support\Logger;
use W3a\Core\Storage\StorageManager;

class ImageProcessorService
{
    private Logger $logger;
    private StorageManager $storage;

    public function __construct(Logger $logger, StorageManager $storage)
    {
        $this->logger = $logger;
        $this->storage = $storage;
    }

    /**
     * Конвертирует изображение в WebP/AVIF и УДАЛЯЕТ оригинал.
     * 
     * Возвращает массив с фактически созданными версиями:
     * [
     *   'main'     => '/uploads/stories/2026/08/95568.webp',      // основная версия (webp или оригинал)
     *   'variants' => [                                            // реально созданные размеры
     *       'large'  => ['webp' => '/.../95568_large.webp', 'avif' => '/.../95568_large.avif'],
     *       'medium' => [...],
     *       'small'  => [...],
     *   ],
     * ]
     * Ключи variants заполняются ТОЛЬКО для реально существующих файлов.
     * 
     * @param string $fullPath Полный путь к оригиналу на диске
     * @return array Массив с 'main' и 'variants'
     */
	public function process(string $fullPath): array
	{
		$originalRelative = $this->storiesDisk()->relativePath($fullPath);

		if (!extension_loaded('gd') || !function_exists('imagewebp') || !file_exists($fullPath)) {
			return ['main' => $originalRelative, 'variants' => []];
		}

		$orientation = $this->readExifOrientation($fullPath);
		$image = $this->loadImage($fullPath);
		if (!$image) {
			return ['main' => $originalRelative, 'variants' => []];
		}

		if (!imageistruecolor($image)) {
			imagepalettetotruecolor($image);
		}

		$image = $this->applyOrientation($image, $orientation);

		$originalWidth = imagesx($image);
		$originalHeight = imagesy($image);

		$baseName = pathinfo($fullPath, PATHINFO_FILENAME);
		$outputDir = dirname($fullPath);
		$variants = [];

		// Исходник уже в формате WebP?
		// В этом случае основная версия совпадает с исходным файлом по пути,
		// поэтому его нельзя перезаписывать и тем более удалять.
		$isWebpSource = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'webp';

		// 🆕 Если изображение ОЧЕНЬ маленькое (< 100px) — не создаём версии вообще
		if ($originalWidth < 100 && $originalHeight < 100) {
			$this->logger->info("Image too small ({$originalWidth}x{$originalHeight}), skipping variants");

			if (!$isWebpSource) {
				$webpMain = $outputDir . '/' . $baseName . '.webp';
				if (imagewebp($image, $webpMain, 85)) {
					imagedestroy($image);
					@unlink($fullPath);
					return ['main' => $this->storiesDisk()->relativePath($webpMain), 'variants' => []];
				}
			}

			imagedestroy($image);
			return ['main' => $originalRelative, 'variants' => []];
		}

		$sizes = [
			'large'  => 1200,
			'medium' => 800,
			'small'  => 400,
		];

		foreach ($sizes as $name => $targetWidth) {
			// 🆕 Если оригинал меньше целевого размера - создаём только ОДИН раз
			if ($originalWidth <= $targetWidth) {
				// Проверяем, не создали ли мы уже такую версию
				$webpPath = $outputDir . '/' . $baseName . '_' . $name . '.webp';

				if (!file_exists($webpPath) && imagewebp($image, $webpPath, 85)) {
					$variants[$name]['webp'] = $this->storiesDisk()->relativePath($webpPath);

					if (function_exists('imageavif')) {
						$avifPath = $outputDir . '/' . $baseName . '_' . $name . '.avif';
						if (@imageavif($image, $avifPath, 80)) {
							$variants[$name]['avif'] = $this->storiesDisk()->relativePath($avifPath);
						}
					}
				}

				continue;
			}

			// Обычный ресайз
			$ratio = $targetWidth / $originalWidth;
			$targetHeight = (int)($originalHeight * $ratio);

			$resized = imagecreatetruecolor($targetWidth, $targetHeight);
			$this->preserveTransparency($resized);

			imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $originalWidth, $originalHeight);

			$webpPath = $outputDir . '/' . $baseName . '_' . $name . '.webp';
			if (imagewebp($resized, $webpPath, 85)) {
				$variants[$name]['webp'] = $this->storiesDisk()->relativePath($webpPath);

				if (function_exists('imageavif')) {
					$avifPath = $outputDir . '/' . $baseName . '_' . $name . '.avif';
					if (@imageavif($resized, $avifPath, 80)) {
						$variants[$name]['avif'] = $this->storiesDisk()->relativePath($avifPath);
					}
				}
			}

			imagedestroy($resized);
		}

		// Основная WebP версия
		$webpMain = $outputDir . '/' . $baseName . '.webp';
		$main = $originalRelative;

		// Если исходник уже .webp — основная версия и есть исходный файл,
		// перекодировать и удалять его не нужно.
		if (!$isWebpSource) {
			if (imagewebp($image, $webpMain, 85)) {
				$main = $this->storiesDisk()->relativePath($webpMain);
				@unlink($fullPath);
			}
		}

		imagedestroy($image);

		return ['main' => $main, 'variants' => $variants];
	}

	/**
	 * Возвращает диск 'stories'.
	 */
	private function storiesDisk(): \W3a\Core\Storage\LocalStorage
	{
		return $this->storage->disk('stories');
	}

    private function readExifOrientation(string $path): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'tiff', 'tif'])) {
            return 1;
        }

        try {
            $exif = @exif_read_data($path, 'IFD0', false);
            return (int)($exif['Orientation'] ?? 1);
        } catch (\Throwable $e) {
            return 1;
        }
    }

    private function applyOrientation($image, int $orientation)
    {
        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = imagerotate($image, 180, 0);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $image = imagerotate($image, -90, 0);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $image = imagerotate($image, -90, 0);
                break;
            case 7:
                $image = imagerotate($image, 90, 0);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $image = imagerotate($image, 90, 0);
                break;
        }
        
        return $image;
    }

    private function preserveTransparency($image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
    }

    private function loadImage(string $path)
    {
        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        return match($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => null,
        };
    }
}