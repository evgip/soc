<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Storage\StorageManager;
use W3a\Core\Support\Logger;

/**
 * Очистка неиспользуемых изображений из Editor.js
 */
class ImageCleaner
{
    private StorageManager $storage;
    private Logger $logger;

    public function __construct(StorageManager $storage, Logger $logger)
    {
        $this->storage = $storage;
        $this->logger = $logger;
    }

    /**
     * Удаляет изображения, которые были в старом JSON, но отсутствуют в новом.
     */
    public function cleanUnusedImages(?string $oldJson, ?string $newJson): void
    {
        $oldImages = $this->extractImageUrls($oldJson);
        $newImages = $this->extractImageUrls($newJson);

        // Разница: изображения которые были, но больше не используются
        $removedImages = array_diff($oldImages, $newImages);

        foreach ($removedImages as $url) {
            $this->deleteImageWithVariants($url);
        }
    }

    /**
     * Удаляет все изображения из JSON (при удалении статьи).
     */
    public function cleanAllImages(?string $json): void
    {
        $images = $this->extractImageUrls($json);
        foreach ($images as $url) {
            $this->deleteImageWithVariants($url);
        }
    }

    /**
     * Извлекает все URL изображений из JSON Editor.js
     */
    private function extractImageUrls(?string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $data = json_decode($json, true);
        if (!$data || !isset($data['blocks'])) {
            return [];
        }

        $urls = [];
        foreach ($data['blocks'] as $block) {
            if (($block['type'] ?? '') === 'image') {
                $url = $block['data']['file']['url'] ?? null;
                if ($url && is_string($url)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * Удаляет файл и все его варианты (_small, _medium, _large)
     * Работает как с webp, так и с avif версиями.
     */
    private function deleteImageWithVariants(string $url): void
    {
        $relativePath = $this->urlToRelativePath($url);
        if (!$relativePath) {
            return;
        }

        $pathInfo = pathinfo($relativePath);
        $dir = $pathInfo['dirname'] ?? '';
        $baseName = $pathInfo['filename'] ?? '';
        $extension = $pathInfo['extension'] ?? 'webp';

        // Убираем возможный суффикс размера из basename
        $baseName = preg_replace('/_(small|medium|large)$/', '', $baseName);

        // Все варианты для удаления: оригинал + 3 размера + avif версии
        $variants = [];
        foreach (['', '_small', '_medium', '_large'] as $suffix) {
            $variants[] = $baseName . $suffix . '.' . $extension;
            // avif версия (если существует)
            if (function_exists('imageavif')) {
                $variants[] = $baseName . $suffix . '.avif';
            }
        }

        $disk = $this->storage->disk('stories');

        foreach ($variants as $fileName) {
            $path = ($dir === '.' || $dir === '') ? $fileName : $dir . '/' . $fileName;
            
            try {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('ImageCleaner: не удалось удалить ' . $path, [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Преобразует URL в относительный путь на диске 'stories'.
     * Пример: /uploads/stories/2026/08/abc.webp → 2026/08/abc.webp
     */
    private function urlToRelativePath(string $url): ?string
    {
        // Извлекаем часть после /stories/
        if (preg_match('#/stories/(.+)$#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}