<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Events\Event;
use App\Core\Events\EventDispatcher;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\JsonResponseException;
use App\Modules\Votes\Services\VoteService;

/**
 * Базовый абстрактный контроллер для всех модулей.
 * 
 * Предоставляет общую функциональность:
 * - Рендеринг шаблонов с автоматической передачей общих данных
 * - Работа с JSON-ответами через исключения
 * - Редиректы через исключения (без exit)
 * - Контекст пользователя с кэшированием
 * - Open Graph мета-теги
 * - Хлебные крошки
 * 
 * Все дочерние контроллеры наследуют эту функциональность.
 */
abstract class Controller
{
    /** @var Request Объект HTTP-запроса */
    protected Request $request;
    
    /** @var EventDispatcher Диспетчер событий для публикации domain events */
    protected EventDispatcher $eventDispatcher;
    
    /** @var Container DI-контейнер для получения сервисов */
    protected Container $container;
	
	/** @var View Рендерер шаблонов */
	protected View $view;

	/** @var ViewFinder */
	protected ViewFinder $viewFinder;

    /** @var array|null Кеш общих данных для view (вычисляется один раз за запрос) */
    private ?array $commonViewDataCache = null;
    
    /** @var array|null Кеш контекста пользователя (вычисляется один раз за запрос) */
    private ?array $userContextCache = null;

    /**
     * Конструктор базового контроллера.
     * 
     * Все зависимости внедряются через DI-контейнер автоматически.
     * 
     * @param Request $request HTTP-запрос
     * @param EventDispatcher $eventDispatcher Диспетчер событий
     * @param Container $container DI-контейнер
     */
	public function __construct(
		Request $request,
		EventDispatcher $eventDispatcher,
		Container $container,
		View $view,
		ViewFinder $viewFinder
	) {
		$this->request = $request;
		$this->eventDispatcher = $eventDispatcher;
		$this->container = $container;
		$this->view = $view;
		$this->viewFinder = $viewFinder;
	}

    /**
     * Опубликовать domain event.
     * 
     * Используется для слабой связанности между модулями.
     * Например, после создания истории можно опубликовать StoryCreated event.
     * 
     * @param Event $event Событие для публикации
     */
    protected function dispatch(Event $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }

    // =========================================================================
    // КОНТЕКСТ ПОЛЬЗОВАТЕЛЯ
    // =========================================================================

    /**
     * Получить контекст текущего пользователя.
     * 
     * Возвращает массив с данными авторизации для использования в контроллерах.
     * Результат кэшируется в рамках одного запроса для избежания повторных вызовов Auth.
     * 
     * Структура возвращаемого массива:
     * - id: int — ID пользователя (0 для гостей)
     * - isLoggedIn: bool — Авторизован ли пользователь
     * - isAdmin: bool — Является ли администратором
     * - isModerator: bool — Является ли модератором (включая админов)
     * - isAuthor: callable — Функция для проверки авторства: fn(int $authorId): bool
     * 
     * Пример использования:
     * ```php
     * $userContext = $this->getUserContext();
     * 
     * if (!$userContext['isLoggedIn']) {
     *     $this->redirect('/login');
     *     return;
     * }
     * 
     * if ($userContext['isAdmin']) {
     *     // Админские действия
     * }
     * 
     * if ($userContext['isAuthor']($story['user_id'])) {
     *     // Действия только для автора
     * }
     * ```
     * 
     * @return array{
     *     id: int,
     *     isLoggedIn: bool,
     *     isAdmin: bool,
     *     isModerator: bool,
     *     isAuthor: callable
     * }
     */
    protected function getUserContext(): array
    {
        // Возвращаем кеш, если уже вычисляли в этом запросе
        if ($this->userContextCache !== null) {
            return $this->userContextCache;
        }

        // Получаем данные авторизации из Auth сервиса
        $isLoggedIn = \App\Modules\Auth\Services\Auth::check();
        $userId = $isLoggedIn ? (int)\App\Modules\Auth\Services\Auth::id() : 0;
        $isAdmin = \App\Modules\Auth\Services\Auth::isAdmin();
        $isModerator = \App\Modules\Auth\Services\Auth::isModerator();

        // Формируем контекст с callback для проверки авторства
        $this->userContextCache = [
            'id' => $userId,
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'isModerator' => $isModerator,
            // Callback для удобной проверки: является ли пользователь автором контента
            'isAuthor' => fn(int $authorId): bool => $isLoggedIn && $userId === $authorId,
        ];

        return $this->userContextCache;
    }

    /**
     * Рендеринг HTTP-ответа с использованием системы шаблонов и поддержкой тем (Themes).
     * 
     * Реализует паттерн "Fallback Chain" (цепочка отката) для поиска файлов:
     * 1. Ищет шаблон в папке активной темы: /themes/{theme}/Modules/{Module}/Views/{view}.php
     * 2. Если не найден, откатывается к оригиналу модуля: /app/Modules/{Module}/Views/{view}.php
     * 3. Если и там не найден, ищет в общем модуле: /app/Modules/Common/Views/{view}.php
     * 
     * Этот метод гарантирует, что контроллеры остаются "тонкими" и не содержат 
     * логики работы с файловой системой, делегируя поиск классу ViewFinder.
     *
     * @param string $viewName Имя файла шаблона без расширения .php (например, 'index', 'show', 'create').
     * @param array  $data     Ассоциативный массив переменных, которые будут извлечены (extract) в шаблоне.
     * 
     * @throws \RuntimeException Если ViewFinder не может найти ни view-файл, ни layout-файл.
     */
    protected function render(string $viewName, array $data = []): void
    {
        // =========================================================================
        // ШАГ 1: Подготовка базовых и общих данных для всех шаблонов
        // =========================================================================
        
        // 1.1. Внедряем CSRF-токен для защиты всех форм от CSRF-атак.
         $data['csrf_token'] = $this->request->getCsrfToken();
        
        // 1.2. Объединяем переданные данные с общими данными приложения.
        // getCommonViewData() возвращает кэшированный массив: currentUser, unreadNotificationsCount и т.д.
        // Это гарантирует, что шапка сайта (header) всегда имеет актуальные данные без дублирования кода в каждом контроллере.
        $data = array_merge($data, $this->getCommonViewData());

        // =========================================================================
        // ШАГ 2: Автоматическое определение текущего модуля
        // =========================================================================
        
        // 2.1. Получаем полное имя класса вызвавшего этот метод контроллера.
        // Пример: "App\Modules\Stories\Controllers\StoriesController"
        $calledClass = get_called_class();
        
        // 2.2. Разбиваем namespace по обратным слешам.
        // $parts[0] = 'App', $parts[1] = 'Modules', $parts[2] = 'Stories', $parts[3] = 'Controllers'...
        $parts = explode('\\', $calledClass);
        
        // 2.3. Извлекаем имя модуля (3-й элемент массива). 
        // Если по какой-то причине структура namespace нарушена, используем 'Common' как безопасный fallback.
        $moduleName = $parts[2] ?? 'Common';

        // =========================================================================
        // ШАГ 3: Локализация (i18n)
        // =========================================================================
        
        // 3.1. Динамически загружаем языковые файлы для текущего модуля.
        // Это позволяет использовать функции перевода (например, __('key')) внутри шаблонов этого модуля.
        // Пропускаем загрузку для 'Common', так как его языковые файлы загружаются глобально при старте приложения.
        if (!empty($moduleName) && $moduleName !== 'Common') {
            \App\Core\Lang::loadModuleLang($moduleName);
        }

        // =========================================================================
        // ШАГ 4: Разрешение путей и рендеринг контента (Fallback Chain)
        // =========================================================================
        
        // 4.1. ViewFinder автоматически находит правильный путь к файлу шаблона.
        // Он проверяет сначала папку активной темы, а затем оригинальную папку модуля.
        // Пример возвращаемого пути: "/var/www/themes/dark/Modules/Stories/Views/show.php"
        $viewFile = $this->viewFinder->find($viewName, $moduleName);
        
        // 4.2. Рендерим внутренний контент страницы (без layout-обёртки).
        // Класс View выполняет include файла в изолированной области видимости (через замыкание), 
        // предотвращая "загрязнение" глобальных переменных переменными из шаблона.
        $content = $this->view->render($viewFile, $data);

        // =========================================================================
        // ШАГ 5: Обёртывание контента в Layout и финальный вывод
        // =========================================================================
        
		// 5.1. Находим layout. ПЕРЕДАЕМ $moduleName!
        // Если это Admin, он найдет app/Modules/Admin/Views/layout.php
        // Если это Stories, он не найдет его у Stories и откатится к app/Modules/Common/Views/lay
         $layoutFile = $this->viewFinder->findLayout($moduleName);
        
        // 5.2. Передаем отрендеренный внутренний контент в переменную $content для layout-шаблона.
        $data['content'] = $content;
        
        // 5.3. Финальный рендеринг layout-шаблона и немедленный вывод (echo) в HTTP-ответ.
        // На этом этапе выполняется сборка полной HTML-страницы и отправка её пользователю.
        echo $this->view->render($layoutFile, $data);
    }

    /**
     * Получение общих данных для всех шаблонов.
     * 
     * Данные кэшируются в рамках одного запроса для оптимизации.
     * Включает информацию о текущем пользователе и счётчики для шапки.
     * 
     * @return array Общие данные для view
     */
	protected function getCommonViewData(): array
	{
		if ($this->commonViewDataCache !== null) {
			return $this->commonViewDataCache;
		}

		$data = [
			'currentUser' => [
				'id' => null,
				'name' => null,
				'role' => null,
				'avatar' => null,
				'isLoggedIn' => false,
				'isAdmin' => false,
				'isModerator' => false,
			],
			'unreadNotificationsCount' => 0,
			'pendingFlagsCount' => 0,
			'activeSuggestionsCount' => 0,
		];

		try {
			$session = $this->container->get(Session::class);
			$userId = $session->get('user_id');

			// Если пользователь не авторизован — сразу возвращаем пустые данные
			if (!$userId) {
				$this->commonViewDataCache = $data;
				return $data;
			}

			$data['currentUser'] = [
				'id' => $userId,
				'name' => $session->get('user_name'),
				'role' => $session->get('user_role'),
				'avatar' => $session->get('user_avatar'),
				'isLoggedIn' => true,
				'isAdmin' => ($session->get('user_role') === 'admin'),
				'isModerator' => in_array($session->get('user_role'), ['admin', 'moderator']),
			];

			// Загружаем счетчики ТОЛЬКО для авторизованных пользователей
			$data['unreadNotificationsCount'] = $this->getUnreadNotificationsCount((int)$userId);

			// Для модераторов — дополнительные счетчики
			if ($data['currentUser']['isModerator']) {
				$data['pendingFlagsCount'] = $this->getPendingFlagsCount();
				$data['activeSuggestionsCount'] = $this->getActiveSuggestionsCount();
			}
		} catch (\Throwable $e) {
			// Fallback: логируем, но не прерываем выполнение
			error_log("getCommonViewData error: " . $e->getMessage());
		}

		$this->commonViewDataCache = $data;
		return $data;
	}

    /**
     * Получить количество непрочитанных уведомлений.
     * Учитывает замьюченных пользователей (их уведомления не считаются).
     * 
     * @param int $userId ID пользователя
     * @return int Количество непрочитанных уведомлений
     */
    private function getUnreadNotificationsCount(int $userId): int
    {
        try {
            $notifModel = $this->container->get(\App\Modules\Notifications\Models\Notification::class);
            $muteService = $this->container->get(\App\Modules\Muted\Services\MuteService::class);
            $mutedUserIds = $muteService->getMutedUserIds($userId);
            return $notifModel->getUnreadCount($userId, $mutedUserIds);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Получить количество ожидающих проверки флагов.
     * Доступно только модераторам и админам.
     * 
     * @return int Количество флагов со статусом "pending"
     */
    private function getPendingFlagsCount(): int
    {
        try {
            $flagModel = $this->container->get(\App\Modules\Flags\Models\Flag::class);
            return $flagModel->getPendingCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Получить количество активных предложений по изменениям.
     * Доступно только модераторам и админам.
     * 
     * @return int Количество активных предложений
     */
    private function getActiveSuggestionsCount(): int
    {
        try {
            $suggestionModel = $this->container->get(\App\Modules\Suggestions\Models\Suggestion::class);
            return $suggestionModel->countAllActive();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // =========================================================================
    // ОТВЕТЫ И РЕДИРЕКТЫ
    // =========================================================================

    /**
     * Отправить JSON ответ.
     * 
     * Использует JsonResponseException вместо прямого echo + exit.
     * Исключение перехватывается в Application::handleJsonResponse().
     * 
     * Пример:
     * ```php
     * $this->json(['status' => 'success', 'data' => $data]);
     * $this->json(['error' => 'Not found'], 404);
     * ```
     * 
     * @param array $data Данные для JSON ответа
     * @param int $statusCode HTTP статус код (по умолчанию 200)
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        throw new JsonResponseException($data, $statusCode);
    }

    /**
     * Выполнить HTTP редирект.
     * 
     * Использует RedirectException вместо header() + exit.
     * Исключение перехватывается в Application::handleRedirect().
     * 
     * @param string $url URL для редиректа
     * @param int $code HTTP код редиректа (302 по умолчанию)
     */
    protected function redirect(string $url, int $code = 302): void
    {
        throw new \App\Core\Exceptions\RedirectException($url, $code);
    }

    /**
     * Редирект на предыдущую страницу (HTTP_REFERER).
     * 
     * Если referer отсутствует или ведёт на внешний сайт — используется fallback.
     * 
     * @param string $fallback URL для использования, если referer недоступен
     */
    protected function redirectBack(string $fallback = '/'): void
    {
        $this->redirect($this->getSafeBackUrl($fallback));
    }

    /**
     * Получить безопасный URL для редиректа назад.
     * 
     * Проверяет, что URL:
     * - Относительный (начинается с /)
     * - Не является протокольно-относительным (не начинается с //)
     * - Или ведёт на тот же домен, что и приложение
     * 
     * Защита от Open Redirect уязвимостей.
     * 
     * @param string $fallback URL по умолчанию
     * @return string Безопасный URL
     */
    private function getSafeBackUrl(string $fallback = '/'): string
    {
        $referer = $this->request->header('HTTP_REFERER', $fallback);
        return $this->isSafeUrl($referer) ? $referer : $fallback;
    }

    /**
     * Проверить безопасность URL для редиректа.
     * 
     * @param string $url URL для проверки
     * @return bool true, если URL безопасен
     */
    private function isSafeUrl(string $url): bool
    {
        // Разрешаем относительные URL (начинаются с /)
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        
        // Проверяем домен для абсолютных URL
        $urlHost = parse_url($url, PHP_URL_HOST);
        if ($urlHost === null) {
            return false;
        }

        $appHost = parse_url(config('config.app.url', ''), PHP_URL_HOST);
        return $urlHost === $appHost;
    }

    /**
     * Редирект с flash-сообщением.
     * 
     * Сообщение сохраняется в сессии и отображается на следующей странице.
     * 
     * @param string $url URL для редиректа
     * @param string $message Текст сообщения
     * @param string $type Тип сообщения (success, error, warning, info)
     */
    protected function redirectWithMessage(string $url, string $message, string $type = 'success'): void
    {
        $session = $this->container->get(Session::class);
        $session->flash($type, $message);
        $this->redirect($url);
    }

    /**
     * Редирект назад с flash-сообщением.
     * 
     * @param string $message Текст сообщения
     * @param string $type Тип сообщения
     * @param string $fallback URL по умолчанию
     */
    protected function backWithMessage(string $message, string $type = 'success', string $fallback = '/'): void
    {
        $this->redirectWithMessage($this->getSafeBackUrl($fallback), $message, $type);
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить сервис из DI-контейнера.
     * 
     * Короткий алиас для $this->container->get($class).
     * 
     * Пример:
     * ```php
     * $storyService = $this->service(StoryService::class);
     * ```
     * 
     * @param string $class Имя класса сервиса
     * @return mixed Экземпляр сервиса
     */
    protected function service(string $class): mixed
    {
        return $this->container->get($class);
    }

    /**
     * Установить Open Graph мета-теги для страницы.
     * 
     * Если URL не указан, генерируется автоматически из текущего запроса.
     * 
     * Пример:
     * ```php
     * $this->setOpenGraph([
     *     'type' => 'article',
     *     'title' => $story['title'],
     *     'description' => $description,
     *     'image' => $imageUrl,
     * ]);
     * ```
     * 
     * @param array $data Данные Open Graph
     */
    protected function setOpenGraph(array $data): void
    {
        // Автоматически добавляем URL, если не указан
        if (!isset($data['url'])) {
            $host = $this->request->header('HTTP_HOST', 'localhost');
            $uri = $this->request->getUri();
            $data['url'] = 'https://' . $host . $uri;
        }
        OpenGraph::set($data);
    }

    /**
     * Отрендерить хлебные крошки (breadcrumbs).
     * 
     * Генерирует HTML-разметку навигации с поддержкой schema.org/BreadcrumbList.
     * 
     * Пример:
     * ```php
     * $html = $this->renderBreadcrumbs([
     *     ['label' => 'Главная', 'url' => '/'],
     *     ['label' => 'Истории', 'url' => '/stories'],
     *     ['label' => $story['title']], // Без url — текущая страница
     * ]);
     * ```
     * 
     * @param array $items Массив элементов крошек
     * @return string HTML-разметка breadcrumbs
     */
    protected function renderBreadcrumbs(array $items): string
    {
        $html = '<nav aria-label="Breadcrumb"><ol>';
        foreach ($items as $item) {
            $label = $item['label'] ?? $item['title'] ?? '';
            if (isset($item['url'])) {
                // Кликабельный элемент
                $html .= '<li><a href="' . e($item['url']) . '">' . e($label) . '</a></li>';
            } else {
                // Текущая страница (некликабельная)
                $html .= '<li aria-current="page">' . e($label) . '</li>';
            }
        }
        $html .= '</ol></nav>';
        return $html;
    }
	
	// =========================================================================
	// ЛОГИРОВАНИЕ
	// =========================================================================

	/**
	 * Логирование ошибки с полным контекстом.
	 * 
	 * Используется во всех контроллерах для единообразного логирования.
	 * Записывает ошибку в storage/logs/app.log через Logger сервис.
	 * 
	 * Формат записи:
	 * - level: error
	 * - message: [Prefix] сообщение ошибки
	 * - context: file, line, trace, user_id, url
	 * 
	 * Если Logger недоступен — fallback на error_log().
	 * 
	 * @param \Throwable $e Исключение для логирования
	 * @param string $prefix Префикс для сообщения (обычно "Модуль.метод")
	 */
	protected function logError(\Throwable $e, string $prefix = ''): void
	{
		// Авто-определение префикса из имени класса, если не указан
		if ($prefix === '') {
			$prefix = (new \ReflectionClass($this))->getShortName();
		}
		
		try {
			$logger = $this->container->get(\App\Core\Logger::class);
			$logger->error("[{$prefix}] " . $e->getMessage(), [
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTraceAsString(),
				'user_id' => $this->getUserContext()['id'] ?? 0,
				'url' => $this->request->getUri(),
			]);
		} catch (\Throwable $logError) {
			// Если логгер недоступен — используем error_log как последний шанс
			error_log("[{$prefix}] " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
		}
	}
	
	protected function canUserDownvote(int $userId): bool
	{
		return $this->service(VoteService::class)->canUserDownvote($userId);
	}
}