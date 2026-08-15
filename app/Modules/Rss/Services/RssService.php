<?php

declare(strict_types=1);

namespace App\Modules\Rss\Services;

class RssService
{
    private string $siteName;
    private string $siteUrl;
    private string $siteDescription;

    public function __construct()
    {
        $this->siteName = config('app.name', 'w3a');
        $this->siteUrl = rtrim(config('app.url', 'http://localhost'), '/');
        $this->siteDescription = config('app.description', 'Интересные статьи и обсуждения');
    }

    /**
     * Генерирует RSS 2.0 XML из массива элементов
     *
     * @param array $channel Метаданные канала (title, link, description)
     * @param array $items   Массив элементов (title, link, description, pubDate, guid, author, comments)
     * @return string        Готовый XML
     */
    public function generate(array $channel, array $items): string
    {
        $channelTitle = $this->escape($channel['title'] ?? $this->siteName);
        $channelLink = $this->escape($channel['link'] ?? $this->siteUrl);
        $channelDescription = $this->escape($channel['description'] ?? $this->siteDescription);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        $xml .= "<channel>\n";
        $xml .= "  <title>{$channelTitle}</title>\n";
        $xml .= "  <link>{$channelLink}</link>\n";
        $xml .= "  <description>{$channelDescription}</description>\n";
        $xml .= "  <language>ru-ru</language>\n";
        $xml .= "  <lastBuildDate>" . $this->rfc822Date(time()) . "</lastBuildDate>\n";
        $xml .= "  <atom:link href=\"{$channelLink}\" rel=\"self\" type=\"application/rss+xml\" />\n";
        $xml .= "  <generator>w3a RSS Generator</generator>\n";

        foreach ($items as $item) {
            $xml .= $this->renderItem($item);
        }

        $xml .= "</channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }

    /**
     * Рендерит один <item>
     */
    private function renderItem(array $item): string
    {
        $title = $this->escape($item['title'] ?? '');
        $link = $this->escape($item['link'] ?? '');
        $description = $this->escape($item['description'] ?? '');
        $guid = $this->escape($item['guid'] ?? $link);
        $pubDate = $this->rfc822Date($item['pubDate'] ?? time());

        $xml = "  <item>\n";
        $xml .= "    <title>{$title}</title>\n";
        $xml .= "    <link>{$link}</link>\n";
        $xml .= "    <description>{$description}</description>\n";
        $xml .= "    <guid isPermaLink=\"true\">{$guid}</guid>\n";
        $xml .= "    <pubDate>{$pubDate}</pubDate>\n";

        if (!empty($item['author'])) {
            $xml .= "    <author>" . $this->escape($item['author']) . "</author>\n";
        }

        // content:encoded — для HTML-контента (например, Markdown-описание)
        if (!empty($item['contentEncoded'])) {
            $xml .= "    <content:encoded><![CDATA[" . $item['contentEncoded'] . "]]></content:encoded>\n";
        }

        // Ссылка на комментарии (для историй)
        if (!empty($item['comments'])) {
            $xml .= "    <comments>" . $this->escape($item['comments']) . "</comments>\n";
        }

        $xml .= "  </item>\n";

        return $xml;
    }

    /**
     * Безопасное экранирование для XML
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Формат даты RFC 822 (требование RSS 2.0)
     */
    private function rfc822Date($timestamp): string
    {
        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
        }
        return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    }
}
