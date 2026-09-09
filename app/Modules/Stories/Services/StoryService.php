<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Support\Validator;
use W3a\Core\Support\Audit;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Security\UserContext;
use W3a\Core\Support\HtmlSanitizer;

use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Events\StoryDeleted;
use App\Modules\Stories\Events\StoryRestored;
use App\Modules\Stories\Exceptions\StoryValidationException;
use App\Modules\Stories\Services\ImageCleaner;

/**
 * Сервис для управления бизнес-логикой статей (Medium-стиль).
 * 
 * Отвечает за валидацию, создание, обновление и удаление публикаций.
 * НЕ содержит SQL — вся работа с БД через модель Story.
 */
class StoryService
{
    private Story $storyModel;
    private StoryValidator $storyValidator;
    private Validator $validator;
    private Audit $audit;
    private EventDispatcher $eventDispatcher;
    private UserContext $currentUser;
    private HtmlSanitizer $sanitizer;
    private ImageCleaner $imageCleaner;

    public function __construct(
        Story $storyModel,
        StoryValidator $storyValidator,
        Validator $validator,
        Audit $audit,
        EventDispatcher $eventDispatcher,
        UserContext $currentUser,
        HtmlSanitizer $sanitizer,
        ImageCleaner $imageCleaner
    ) {
        $this->storyModel = $storyModel;
        $this->storyValidator = $storyValidator;
        $this->validator = $validator;
        $this->audit = $audit;
        $this->eventDispatcher = $eventDispatcher;
        $this->currentUser = $currentUser;
        $this->sanitizer = $sanitizer;
        $this->imageCleaner = $imageCleaner;
    }

	/**
	 * Создаёт новую статью.
	 * Заголовок извлекается из первого H1/H2 блока Editor.js.
	 *
	 * @param string $status 'published' или 'draft'
	 * @throws StoryValidationException Если данные не прошли валидацию
	 */
	public function createStory(array $data, int $userId, string $status = 'published'): int
	{
		$editorJsJson = $data['description'] ?? '';
		$isDraft = ($status === 'draft');

		// 1. Проверяем, что JSON не пустой (только для публикации)
		if (!$isDraft && empty(trim($editorJsJson))) {
			throw new StoryValidationException('Статья не может быть пустой.');
		}

		// 2. Извлекаем заголовок из первого H1/H2 блока (для черновика может быть пустым)
		$title = '';
		if (!empty($editorJsJson)) {
			$title = $this->storyModel->extractTitleFromJson($editorJsJson);
		}
		
		if (!$isDraft && empty($title)) {
			throw new StoryValidationException('Статья должна начинаться с заголовка (H1 или H2).');
		}

		// 3. Обрабатываем JSON: очищаем HTML и извлекаем текст для поиска
		if (!empty($editorJsJson)) {
			$processedContent = $this->storyModel->processEditorJsData($editorJsJson);
		} else {
			$processedContent = [
				'description_json' => '',
				'description_text' => '',
			];
		}

		// 4. Определяем тип paywall (по умолчанию 'members' для закрытых частей)
		$paywallType = $data['paywall_type'] ?? 'members';

		// 5. Рассчитываем время чтения
		preg_match_all('/\p{L}+/u', $processedContent['description_text'] ?? '', $matches);
		$wordCount = count($matches[0] ?? []);
		$readingTime = max(1, (int)ceil($wordCount / 200)); // 200 слов/мин

		// 6. Формируем данные для сохранения
		$storyData = [
			'user_id'            => $userId,
			'title'              => mb_substr($title, 0, 150),
			'description_json'   => $processedContent['description_json'],
			'description_text'   => $processedContent['description_text'],
			'word_count'         => $wordCount,
			'reading_time'       => $readingTime,
			'score'              => 1,
			'comments_count'     => 0,
			'user_is_following'  => isset($data['user_is_following']) ? 1 : 0,
			'paywall_type'       => $paywallType,
			'status'             => $status,
		];

		// 7. Создаём статью через модель
		$storyId = $this->storyModel->create($storyData);

		// 7. Обновляем paywall-флаги (модель сама читает JSON из БД)
		if ($storyId > 0) {
			$this->storyModel->updatePaywallFlags($storyId, $paywallType);
		}

		// 8. Привязываем теги и пересчитываем hotness
		if ($storyId > 0 && !empty($data['tags'])) {
			$this->storyModel->syncTags($storyId, $data['tags']);
			$this->storyModel->recalculateHotness($storyId);
		}

		// 9. Логируем в аудит
		$this->audit->log('story.created', 'Пользователь создал новую статью', 'story', [
			'story_id' => $storyId,
			'user_id'  => $userId,
			'status'   => $status,
		]);

		return $storyId;
	}

	/**
	 * Обновляет существующую статью.
	 * Заголовок извлекается из первого H1/H2 блока Editor.js.
	 *
	 * @param string|null $status Новый статус ('published' или 'draft'). Если null — статус не меняется.
	 * @throws \InvalidArgumentException Если статья не найдена
	 * @throws StoryValidationException Если данные не прошли валидацию
	 */
	public function updateStory(int $storyId, array $data, ?string $status = null): bool
	{
		$story = $this->storyModel->find($storyId);
		if (!$story) {
			throw new \InvalidArgumentException("Статья не найдена.");
		}

		// Если статус не передан — используем текущий
		if ($status === null) {
			$status = $story['status'] ?? 'published';
		}
		
		$isDraft = ($status === 'draft');

		$editorJsJson = $data['description'] ?? '';

		// 1. Проверяем, что JSON не пустой (только для публикации)
		if (!$isDraft && empty(trim($editorJsJson))) {
			throw new StoryValidationException('Статья не может быть пустой.');
		}

		// 2. Извлекаем новый заголовок из JSON (для черновика может быть пустым)
		$newTitle = '';
		if (!empty($editorJsJson)) {
			$newTitle = $this->storyModel->extractTitleFromJson($editorJsJson);
		}
		
		if (!$isDraft && empty($newTitle)) {
			throw new StoryValidationException('Статья должна начинаться с заголовка (H1 или H2).');
		}

		// 3. Обрабатываем JSON: очищаем HTML и извлекаем текст для поиска
		if (!empty($editorJsJson)) {
			$processedContent = $this->storyModel->processEditorJsData($editorJsJson);
		} else {
			$processedContent = [
				'description_json' => '',
				'description_text' => '',
			];
		}

		// 4. Определяем тип paywall
		$paywallType = $data['paywall_type'] ?? 'members';

		// 5. Рассчитываем время чтения
		preg_match_all('/\p{L}+/u', $processedContent['description_text'] ?? '', $matches);
		$wordCount = count($matches[0] ?? []);
		$readingTime = max(1, (int)ceil($wordCount / 200));

		// 6. Формируем данные для обновления
		$updateData = [
			'title'              => mb_substr($newTitle, 0, 150),
			'description_json'   => $processedContent['description_json'],
			'description_text'   => $processedContent['description_text'],
			'word_count'         => $wordCount,
			'reading_time'       => $readingTime,
			'user_is_following'  => isset($data['user_is_following']) ? 1 : 0,
			'paywall_type'       => $paywallType,
			'status'             => $status,
		];

		// 6. Обновляем статью через модель
		$this->storyModel->update($storyId, $updateData);

		// Удаляем изображения, которые были удалены из статьи
		$oldJson = $story['description_json'] ?? null;
		$newJson = $processedContent['description_json'];
		$this->imageCleaner->cleanUnusedImages($oldJson, $newJson);

		// 7. Обновляем paywall-флаги (модель сама читает JSON из БД)
		$this->storyModel->updatePaywallFlags($storyId, $paywallType);

		// 8. Синхронизируем теги, если они переданы
		if (isset($data['tags'])) {
			$this->storyModel->syncTags($storyId, $data['tags']);
			$this->storyModel->recalculateHotness($storyId);
		}

		// 9. Логируем обновление
		$this->audit->log('story.updated', 'Пользователь отредактировал статью', 'story', [
			'story_id' => $storyId,
			'status'   => $status,
		]);

		return true;
	}

    /**
     * Проверяет наличие прав на редактирование статьи.
     */
    public function canEditStory(array $story, int $userId): bool
    {
        $isAuthor = (int)$story['user_id'] === $userId;
        return $isAuthor || $this->currentUser->canModerate();
    }

    /**
     * Скрывает статью (мягкое удаление).
     */
    public function deleteStory(int $storyId, int $adminId, string $reason = 'Статья скрыта модератором'): bool
    {
        $story = $this->storyModel->find($storyId);
        if (!$story) {
            throw new \InvalidArgumentException("Статья не найдена.");
        }

        $this->storyModel->softDelete($storyId);
		
		// Удаляем все изображения статьи с диска?
		// Пока не имеет смысла, т.к. мягкое удаление это и возможно восстановление
		// $this->imageCleaner->cleanAllImages($story['description_json'] ?? null);
		
        $this->eventDispatcher->dispatch(new StoryDeleted($storyId, $adminId, $reason));
        
        return true;
    }

    /**
     * Восстанавливает скрытую статью.
     */
    public function restoreStory(int $storyId, int $adminId): bool
    {
        $story = $this->storyModel->find($storyId, withTrashed: true);
        if (!$story) {
            throw new \InvalidArgumentException("Статья не найдена.");
        }

        $this->storyModel->restore($storyId);
        $this->eventDispatcher->dispatch(new StoryRestored($storyId, $adminId));
        
        return true;
    }

	/**
	 * Получить черновики пользователя с пагинацией
	 */
	public function getUserDrafts(int $userId, int $page = 1, int $perPage = 20): array
	{
		$drafts = $this->storyModel->getUserDrafts($userId, $page, $perPage);
		$total = $this->storyModel->countUserDrafts($userId);

		return [
			'drafts' => $drafts,
			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,
		];
	}

	/**
	 * Получить статьи пользователя по статусу с пагинацией.
	 *
	 * @param string $status draft | published | scheduled
	 */
	public function getUserStoriesByStatus(int $userId, string $status, int $page = 1, int $perPage = 20): array
	{
		$stories = $this->storyModel->getStoriesByStatus($userId, $status, $page, $perPage);
		$total = $this->storyModel->countStoriesByStatus($userId, $status);

		return [
			'stories' => $stories,
			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,
		];
	}
}