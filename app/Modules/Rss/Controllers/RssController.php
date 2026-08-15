<?php

declare(strict_types=1);

namespace App\Modules\Rss\Controllers;

use App\BaseController;
use W3a\Core\Exceptions\NotFoundException;
use App\Modules\Rss\Services\RssService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Comments\Models\Comment;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Users\Models\User;

class RssController extends BaseController
{
    private const ITEM_LIMIT = 50;

    /**
     * Главная RSS-лента: все новые истории
     */
    public function index(): void
    {
        $filterService = $this->service(StoryFilterService::class);
        $stories = $filterService->getFilteredStories(self::ITEM_LIMIT, 0, '', '', 'new');

        $items = [];
        foreach ($stories as $story) {
            $items[] = $this->storyToRssItem($story);
        }

        $rssService = $this->service(RssService::class);
        $xml = $rssService->generate([
            'title' => config('app.name', 'w3a') . ' — Новые истории',
            'link' => config('app.url', '/'),
            'description' => 'Свежие публикации',
        ], $items);

        $this->renderRss($xml);
    }

    /**
     * RSS по тегу
     */
    public function byTag(string $tagslug): void
    {
        $tagFilterService = $this->service(TagFilterService::class);
        $tagInfo = $tagFilterService->getByInfoSlug($tagslug);

        if (empty($tagInfo['id'])) {
            throw new NotFoundException("Тег не найден");
        }

        $filterService = $this->service(StoryFilterService::class);
        $stories = $filterService->getFilteredStories(self::ITEM_LIMIT, 0, $tagslug, '', 'new');

        $items = [];
        foreach ($stories as $story) {
            $items[] = $this->storyToRssItem($story);
        }

        $rssService = $this->service(RssService::class);
        $xml = $rssService->generate([
            'title' => config('app.name', 'w3a') . ' — Тег #' . e($tagInfo['name']),
            'link' => route('tags.filter', ['tagslug' => $tagslug]),
            'description' => 'Публикации с тегом ' . e($tagInfo['name']),
        ], $items);

        $this->renderRss($xml);
    }

    /**
     * RSS пользователя
     */
    public function byUser(string $username): void
    {
        $userModel = $this->container->get(User::class);
        $user = $userModel->findByName($username);

        if (!$user) {
            throw new NotFoundException("Пользователь не найден");
        }

        $filterService = $this->service(StoryFilterService::class);
        $stories = $filterService->getFilteredStories(self::ITEM_LIMIT, 0, '', '', 'new', $username);

        $items = [];
        foreach ($stories as $story) {
            $items[] = $this->storyToRssItem($story);
        }

        $rssService = $this->service(RssService::class);
        $xml = $rssService->generate([
            'title' => config('app.name', 'w3a') . ' — Публикации ' . e($username),
            'link' => route('user.stories', ['username' => $username]),
            'description' => 'Публикации пользователя ' . e($username),
        ], $items);

        $this->renderRss($xml);
    }

    /**
     * RSS всех новых комментариев
     */
    public function comments(): void
    {
        $commentModel = $this->container->get(Comment::class);
        $comments = $commentModel->getLatestComments(self::ITEM_LIMIT);

        $items = [];
        foreach ($comments as $comment) {
            $items[] = $this->commentToRssItem($comment);
        }

        $rssService = $this->service(RssService::class);
        $xml = $rssService->generate([
            'title' => config('app.name', 'w3a') . ' — Новые комментарии',
            'link' => route('comments.index'),
            'description' => 'Свежие комментарии со всего сайта',
        ], $items);

        $this->renderRss($xml);
    }

    // =========================================================================
    // Вспомогательные методы
    // =========================================================================

    /**
     * Преобразует историю в RSS-item
     */
    private function storyToRssItem(array $story): array
    {
        $storyUrl = !empty($story['url']) ? $story['url'] : route('story.show', ['id' => $story['id']]);
        $storyPageUrl = route('story.show', ['id' => $story['id']]);

        // Короткое описание для <description> (plain text)
        $description = '';
        if (!empty($story['description'])) {
            $description = mb_substr(strip_tags($story['description']), 0, 300);
            if (mb_strlen($story['description']) > 300) {
                $description .= '...';
            }
        } else {
            $description = (int)($story['comments_count'] ?? 0) . ' комментариев';
        }

        // Полный HTML для <content:encoded>
        $contentEncoded = '';
        if (!empty($story['description'])) {
            $contentEncoded = markdown($story['description']);
        }

        return [
            'title' => $story['title'],
            'link' => $storyUrl,
            'description' => $description,
            'contentEncoded' => $contentEncoded,
            'guid' => $storyPageUrl,
            'pubDate' => $story['created_at'],
            'author' => $story['author_name'] ?? '',
            'comments' => $storyPageUrl . '#comments',
        ];
    }

    /**
     * Преобразует комментарий в RSS-item
     */
    private function commentToRssItem(array $comment): array
    {
        $storyUrl = route('story.show', ['id' => $comment['story_id']]);
        $commentUrl = $storyUrl . '#comment-block-' . (int)$comment['id'];

        // Описание: ссылка на историю + текст комментария
        $description = 'Комментарий к: ' . ($comment['story_title'] ?? '') . "\n\n" . strip_tags($comment['comment']);
        $description = mb_substr($description, 0, 500);

        return [
            'title' => 'Комментарий от ' . ($comment['author_name'] ?? 'аноним') . ' к: ' . ($comment['story_title'] ?? 'истории'),
            'link' => $commentUrl,
            'description' => $description,
            'contentEncoded' => markdown_comment($comment['comment'] ?? ''),
            'guid' => $commentUrl,
            'pubDate' => $comment['created_at'],
            'author' => $comment['author_name'] ?? '',
        ];
    }

    /**
     * Отдаёт XML с правильным Content-Type
     */
    private function renderRss(string $xml): void
    {
        header('Content-Type: application/rss+xml; charset=UTF-8');
        header('Cache-Control: public, max-age=300'); // Кэш на 5 минут
        echo $xml;
        exit;
    }
}
