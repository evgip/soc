<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use W3a\Core\Audit;

/**
 * Статический класс для проверки состояния авторизации.
 * 
 * ⚠️ ВАЖНО: Этот класс НЕ должен создавать сервисы через new!
 * Все сервисы должны получаться через контейнер, но в статическом
 * контексте это невозможно. Поэтому мы убираем всю бизнес-логику
 * отсюда и оставляем только проверку сессии.
 * 
 * Восстановление сессии через remember token должно происходить
 * в middleware или в начале запроса, а не здесь.
 */
class Auth
{
    private static int $sessionTimeout = 3600; // 1 час неактивности
    private static bool $isLoopProtect = false;

    /**
     * Инициализация сессии.
     * 
     * ✅ УПРОЩЕНО: Убрали все вызовы new AuthService(),
     * которые ломали приложение из-за отсутствия DI-контейнера.
     */
    public static function initSession(): void
    {
        // Защита от рекурсии
        if (self::$isLoopProtect) {
            return;
        }
        self::$isLoopProtect = true;

        // Стартуем сессию, если ещё не запущена
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ✅ Обновляем время последней активности
        $_SESSION['last_activity_time'] = time();

        self::$isLoopProtect = false;
    }

    /**
     * Проверка AJAX запроса
     */
    private static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Проверка авторизации
     */
    public static function check(): bool
    {
        self::initSession();
        return isset($_SESSION['user_id']);
    }

    /**
     * Получить ID текущего авторизованного пользователя
     */
    public static function id(): ?int
    {
        self::initSession();
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Получить имя текущего авторизованного пользователя
     */
    public static function name(): ?string
    {
        self::initSession();
        return $_SESSION['user_name'] ?? null;
    }

    /**
     * Получить роль текущего авторизованного пользователя
     */
    public static function role(): ?string
    {
        self::initSession();
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Проверка, забанен ли текущий пользователь.
     * Использует кэш в сессии.
     */
    public static function isBanned(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        return (bool)($_SESSION['is_banned'] ?? false);
    }

    /**
     * Проверка: текущий пользователь — администратор
     */
    public static function isAdmin(): bool
    {
        self::initSession();
        return self::check() && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /**
     * Проверка: текущий пользователь — модератор (или админ)
     */
    public static function isModerator(): bool
    {
        self::initSession();
        if (!self::check()) {
            return false;
        }
        return in_array($_SESSION['user_role'] ?? '', ['moderator', 'admin'], true);
    }

    /**
     * Проверка: текущий пользователь — член команды модерации (staff)
     */
    public static function isStaff(): bool
    {
        return self::isModerator();
    }
}