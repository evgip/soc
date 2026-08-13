<?php

declare(strict_types=1);

namespace App\Modules\Collections\Services;

use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\CollectionItem;
use App\Modules\Stories\Models\Story;
use App\Modules\Collections\Exceptions\CollectionValidationException;
use W3a\Core\Support\Validator;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Security\UserContext;
use W3a\Core\Support\Audit;

/**
 * Сервис для работы с коллекциями (сериями статей).
 * 
 * Бизнес-логика: создание, редактирование, управление статьями,
 * проверка прав доступа.
 */
class CollectionService
{
    public function __construct(
        private Collection $collectionModel,
        private CollectionItem $itemModel,
        private Story $storyModel,
        private Validator $validator,
        private EventDispatcher $eventDispatcher,
        private UserContext $currentUser,
        private Audit $audit
    ) {}

    // =========================================================================
    // CRUD КОЛЛЕКЦИЙ
    // =========================================================================

    /**
     * Создать новую коллекцию.
     * 
     * @throws CollectionValidationException
     * @return int ID созданной коллекции
     */
    public function createCollection(array $data): int
    {
        $this->validateCollectionData($data);

        $slug = $this->generateSlug($data['title'], $this->currentUser->id);

        $collectionId = $this->collectionModel->createCollection([
            'author_id'   => $this->currentUser->id,
            'title'       => $data['title'],
            'slug'        => $slug,
            'description' => $data['description'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'is_public'   => isset($data['is_public']) ? 1 : 0,
        ]);

        // Аудит
        $this->audit->log('collection.created', 'Пользователь создал коллекцию', 'collection', [
            'collection_id' => $collectionId,
            'user_id'       => $this->currentUser->id,
        ]);

        return $collectionId;
    }

    /**
     * Обновить коллекцию.
     * 
     * @throws CollectionValidationException
     */
    public function updateCollection(int $collectionId, array $data): array
    {
        $collection = $this->getCollectionForEdit($collectionId);

        $this->validateCollectionData($data, $collectionId);

        $updateData = [
            'title'       => trim($data['title']),
            'description' => $data['description'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'is_public'   => isset($data['is_public']) ? 1 : 0,
        ];

        // Если изменили title — можно перегенерировать slug (опционально)
        if (!empty($data['regenerate_slug'])) {
            $updateData['slug'] = $this->generateSlug($data['title'], $this->currentUser->id, $collectionId);
        }

        $this->collectionModel->update($collectionId, $updateData);

        $this->audit->log('collection.updated', 'Пользователь обновил коллекцию', 'collection', [
            'collection_id' => $collectionId,
        ]);

        return $this->collectionModel->find($collectionId);
    }

    /**
     * Удалить коллекцию (мягкое удаление).
     */
    public function deleteCollection(int $collectionId): array
    {
        $collection = $this->getCollectionForEdit($collectionId);

        $this->collectionModel->softDelete($collectionId);

        $this->audit->log('collection.deleted', 'Пользователь удалил коллекцию', 'collection', [
            'collection_id' => $collectionId,
        ]);

        return $collection;
    }

    // =========================================================================
    // УПРАВЛЕНИЕ СТАТЬЯМИ В КОЛЛЕКЦИИ
    // =========================================================================

    /**
     * Добавить статью в коллекцию.
     * 
     * @throws CollectionValidationException Если нет прав или статья уже в коллекции
     */
    public function addStory(int $collectionId, int $storyId): void
    {
        $collection = $this->getCollectionForEdit($collectionId);

        // Проверка: статья существует и опубликована
        $story = $this->storyModel->find($storyId);
        if (!$story || $story['status'] !== 'published' || !empty($story['deleted_at'])) {
            throw new CollectionValidationException('Статья не найдена или не опубликована.');
        }

        // Проверка: статья принадлежит автору коллекции
        if ((int) $story['user_id'] !== (int) $collection['author_id']) {
            throw new CollectionValidationException('В коллекцию можно добавлять только свои статьи.');
        }

        // Добавляем
        $itemId = $this->itemModel->addStory($collectionId, $storyId);

        if ($itemId === 0) {
            throw new CollectionValidationException('Эта статья уже в коллекции.');
        }

        // Обновляем счётчик
        $this->collectionModel->updateStoriesCount($collectionId);
    }

    /**
     * Удалить статью из коллекции.
     */
    public function removeStory(int $collectionId, int $storyId): void
    {
        $this->getCollectionForEdit($collectionId);

        $this->itemModel->removeStory($collectionId, $storyId);
        $this->collectionModel->updateStoriesCount($collectionId);
    }

    /**
     * Изменить порядок статей в коллекции.
     * 
     * @param array $orderedStoryIds Массив story_id в новом порядке
     */
    public function reorderStories(int $collectionId, array $orderedStoryIds): void
    {
        $this->getCollectionForEdit($collectionId);

        if (empty($orderedStoryIds)) {
            throw new CollectionValidationException('Пустой список статей.');
        }

        $this->itemModel->reorder($collectionId, $orderedStoryIds);
    }

    // =========================================================================
    // ЧТЕНИЕ ДАННЫХ
    // =========================================================================

    /**
     * Получить коллекцию с оглавлением для публичного просмотра.
     */
    public function getCollectionWithStories(int $authorId, string $slug): ?array
    {
        $collection = $this->collectionModel->findByAuthorAndSlug($authorId, $slug);

        if (!$collection) {
            return null;
        }

        // Приватные коллекции видны только автору
        if (empty($collection['is_public']) && $collection['author_id'] !== $this->currentUser->id) {
            return null;
        }

        $collection['stories'] = $this->itemModel->getCollectionStories((int) $collection['id']);

        return $collection;
    }

    /**
     * Получить данные для навигации на странице статьи.
     * 
     * Возвращает все коллекции, в которых есть статья, + prev/next для каждой.
     */
    public function getNavigationForStory(int $storyId): array
    {
        $collections = $this->itemModel->getCollectionsForStory($storyId);

        foreach ($collections as &$c) {
            $prevNext = $this->itemModel->getPrevNextStories(
                (int) $c['collection_id'],
                $storyId
            );
            $c['prev'] = $prevNext['prev'];
            $c['next'] = $prevNext['next'];
        }
        unset($c);

        return $collections;
    }

    // =========================================================================
    // ПРИВАТНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить коллекцию с проверкой прав на редактирование.
     * 
     * @throws CollectionValidationException
     */
    private function getCollectionForEdit(int $collectionId): array
    {
        $collection = $this->collectionModel->find($collectionId);

        if (!$collection || !empty($collection['deleted_at'])) {
            throw new CollectionValidationException('Коллекция не найдена.');
        }

        // Право редактирования: автор или админ
        if ((int) $collection['author_id'] !== $this->currentUser->id && !$this->currentUser->isAdmin()) {
            throw new CollectionValidationException('У вас нет прав для изменения этой коллекции.');
        }

        return $collection;
    }

    /**
     * Валидация данных коллекции.
     * 
     * @throws CollectionValidationException
     */
    private function validateCollectionData(array $data, ?int $excludeId = null): void
    {
        $title = trim($data['title'] ?? '');

        if ($title === '') {
            throw new CollectionValidationException('Название коллекции обязательно.');
        }

        if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
            throw new CollectionValidationException('Название должно быть от 3 до 200 символов.');
        }

        if (!empty($data['description']) && mb_strlen($data['description']) > 1000) {
            throw new CollectionValidationException('Описание не должно превышать 1000 символов.');
        }
    }

    /**
     * Генерация уникального slug для коллекции.
     */
    private function generateSlug(string $title, int $authorId, ?int $excludeId = null): string
    {
        // Транслитерация
        $translit = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $slug = mb_strtolower($title);
        $slug = strtr($slug, $translit);
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'collection-' . time();
        }

        // Обеспечиваем уникальность
        $baseSlug = $slug;
        $counter = 1;
        while ($this->collectionModel->slugExists($authorId, $slug, $excludeId)) {
            $slug = $baseSlug . '-' . (++$counter);
        }

        return $slug;
    }
}