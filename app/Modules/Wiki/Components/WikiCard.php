<?php

namespace App\Modules\Wiki\Components;

use W3a\Core\Http\Request;

class WikiCard
{
    /**
     * Рендер карточки wiki страницы
     * 
     * @param array $page Данные страницы
     * @param array $tag Данные тега
     * @param bool $showExcerpt Показывать превью
     * @param bool $showDeletedMark Показывать метку "Удалена"
     * @param Request|null $request Объект запроса (для CSRF в форме восстановления)
     */
    public static function render(
        array $page,
        array $tag,
        bool $showExcerpt = true,
        bool $showDeletedMark = false,
        ?Request $request = null
    ): string {
        $html = '<li class="story">';
        $html .= '<div class="story_liner">';

        // Заголовок
        $html .= '<div class="link">';
        $html .= sprintf(
            '<a href="/t/%s/wiki/%s">%s</a>',
            e($tag['slug']),
            e($page['slug']),
            e($page['title'])
        );

        if (!empty($page['is_primary'])) {
            $html .= ' <span class="tag tag-meta">Основная</span>';
        }

        if ($showDeletedMark && !empty($page['deleted_at'])) {
            $html .= ' <span class="tag red">Удалена</span>';
        }

        $html .= '</div>';

        // Содержимое
        if ($showExcerpt && !empty($page['rendered_content'])) {
            $html .= '<div class="story_content">';
            $html .= truncateDescription($page['rendered_content'], 200);
            $html .= '</div>';
        }

        // Метаданные
        $html .= '<div class="byline">';
        $html .= sprintf('👤 <a href="/user/%s">%s</a>', e($page['author_name']), e($page['author_name']));
        $html .= ' <span class="divider">|</span> ';
        $html .= sprintf('<span title="%s">📅 %s</span>', dt($page['updated_at'], 'd.m.Y H:i:s'), dt($page['updated_at']));
        $html .= ' <span class="divider">|</span> ';
        $html .= sprintf('👁️ %d %s', $page['view_count'], plural($page['view_count'], ['просмотр', 'просмотра', 'просмотров']));

        // Кнопка восстановления для удалённых страниц
        if (!empty($page['deleted_at']) && $showDeletedMark && $request) {
            $html .= ' <span class="divider">|</span> ';
            $html .= sprintf(
                '<form action="/t/%s/wiki/%d/restore" method="POST" class="inline-form js-confirm-delete" data-confirm-message="Восстановить эту страницу?">%s<button type="submit" class="is-link">♻️ Восстановить</button></form>',
                e($tag['slug']),
                (int)$page['id'],
                $request->csrfField()
            );
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</li>';

        return $html;
    }
}
