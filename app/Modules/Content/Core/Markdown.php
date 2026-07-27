<?php

declare(strict_types=1);

namespace App\Modules\Content\Core;

use W3a\Core\Config;

/**
 * Markdown parser using Parsedown library
 * 
 * Features:
 * - Full Markdown support via Parsedown
 * - XSS protection via SafeMode
 * - Custom @mentions support
 * - File-based caching for performance
 * - Two modes: full (posts) and restricted (comments)
 * - Configurable via module config
 */
class Markdown
{
    private Config $config;
    private string $storagePath;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->storagePath = dirname(__DIR__, 4) . '/storage/';
    }

    /**
     * Parse Markdown text to HTML (full mode - for stories/descriptions)
     */
    public function parse(?string $text, bool $allowImages = true): string
    {
        if (empty($text)) {
            return '';
        }

        // Проверяем, включено ли кэширование
        $cacheEnabled = $this->config->getBool('content.config.cache.enabled', true);
        $cacheTtl = $this->config->getInt('content.config.cache.ttl', 3600);

        // Check cache
        $cacheKey = 'md_' . md5($text . ($allowImages ? '_img' : ''));
        
        if ($cacheEnabled) {
            $cached = $this->getCached($cacheKey, $cacheTtl);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Используем SafeParsedown вместо стандартного Parsedown
        $parsedown = new SafeParsedown();
        
        $safeMode = $this->config->getBool('content.config.markdown.safe_mode', true);
        $parsedown->setSafeMode($safeMode);
        
        $markupEscaped = $this->config->getBool('content.config.markdown.markup_escaped', true);
        $parsedown->setMarkupEscaped($markupEscaped);
        
        $urlsLinked = $this->config->getBool('content.config.markdown.urls_linked', true);
        $parsedown->setUrlsLinked($urlsLinked);

        // Отключаем изображения через наш кастомный метод
        $imagesInComments = $this->config->getBool('content.config.images.allowed_in_comments', false);
        if (!$allowImages || !$imagesInComments) {
            $parsedown->setImagesEnabled(false);
        }

        // Parse Markdown
        $html = $parsedown->text($text);

        // Add custom @mentions support (если включено)
        if ($this->config->getBool('content.config.mentions.enabled', true)) {
            $html = $this->parseMentions($html);
        }

        // Add target="_blank" and rel="noopener" to all external links
        $html = $this->addLinkAttributes($html);

        // Фильтрация по чёрному списку
        $html = $this->filterBlacklist($html);

        // Cache result
        if ($cacheEnabled) {
            $this->setCache($cacheKey, $html, $cacheTtl);
        }

        return $html;
    }

    /**
     * Parse Markdown for comments (restricted mode - no images)
     */
    public function parseComment(?string $text): string
    {
        return $this->parse($text, false);
    }

    /**
     * Parse plain text (no Markdown, just escape and line breaks)
     */
    public function parsePlain(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        return '<p>' . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    /**
     * Parse @username mentions and convert to links
     */
    private function parseMentions(string $html): string
    {
        $minLen = $this->config->getInt('content.config.mentions.min_length', 3);
        $maxLen = $this->config->getInt('content.config.mentions.max_length', 20);
        $profileUrl = $this->config->getString('content.config.mentions.profile_url', '/user/{username}');
        
        $pattern = '/(?<![\w@])@([a-zA-Z0-9_]{' . $minLen . ',' . $maxLen . '})(?![\w@])/';
        
        return preg_replace_callback(
            $pattern,
            function ($matches) use ($profileUrl) {
                $username = $matches[1];
                $url = str_replace('{username}', htmlspecialchars($username), $profileUrl);
                return '<a href="' . $url . '" class="mention">@' . htmlspecialchars($username) . '</a>';
            },
            $html
        );
    }

    /**
     * Add target="_blank" and rel="noopener noreferrer" to all links
     */
    private function addLinkAttributes(string $html): string
    {
        $externalBlank = $this->config->getBool('content.config.links.external_blank', true);
        $externalSecure = $this->config->getBool('content.config.links.external_secure', true);
        $internalDomains = $this->config->getArray('content.config.links.internal_domains', []);
        
        if (!$externalBlank && !$externalSecure) {
            return $html;
        }

        return preg_replace_callback(
            '/<a\s+href="([^"]+)"/',
            function ($matches) use ($externalBlank, $externalSecure, $internalDomains) {
                $url = $matches[1];
                
                $isInternal = false;
                
                if (strpos($url, '/') === 0 || strpos($url, '#') === 0) {
                    $isInternal = true;
                }
                
                foreach ($internalDomains as $domain) {
                    if (strpos($url, $domain) === 0) {
                        $isInternal = true;
                        break;
                    }
                }
                
                if ($isInternal) {
                    return $matches[0];
                }
                
                $attrs = [];
                if ($externalBlank) {
                    $attrs[] = 'target="_blank"';
                }
                if ($externalSecure) {
                    $attrs[] = 'rel="noopener noreferrer"';
                }
                
                return '<a href="' . $url . '" ' . implode(' ', $attrs);
            },
            $html
        );
    }

    /**
     * Фильтрация по чёрному списку доменов
     */
    private function filterBlacklist(string $html): string
    {
        $blacklist = $this->config->getArray('content.config.blacklist.domains', []);
        
        if (empty($blacklist)) {
            return $html;
        }
        
        return preg_replace_callback(
            '/<a\s+href="([^"]+)"[^>]*>.*?<\/a>/',
            function ($matches) use ($blacklist) {
                $url = $matches[0];
                foreach ($blacklist as $domain) {
                    if (strpos($url, $domain) !== false) {
                        return '<span class="blocked-link">[ссылка заблокирована]</span>';
                    }
                }
                return $matches[0];
            },
            $html
        );
    }

    // ==================== CACHE ====================

    private function getCached(string $key, int $ttl = 3600): ?string
    {
        $cachePath = $this->config->getString('content.config.cache.path', 'cache');
        $file = $this->storagePath . $cachePath . '/' . $key . '.html';
        
        if (file_exists($file) && (time() - filemtime($file) < $ttl)) {
            return file_get_contents($file);
        }
        return null;
    }

    private function setCache(string $key, string $value, int $ttl): void
    {
        $cachePath = $this->config->getString('content.config.cache.path', 'cache');
        $cacheDir = $this->storagePath . $cachePath . '/';
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cacheDir . $key . '.html', $value);
    }

    /**
     * Clear Markdown cache
     */
    public function clearCache(): void
    {
        $cachePath = $this->config->getString('content.config.cache.path', 'cache');
        $cacheDir = $this->storagePath . $cachePath . '/';
        
        $files = glob($cacheDir . 'md_*.html');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}