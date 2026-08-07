<?php

declare(strict_types=1);

namespace App\Modules\Common\Support;

/**
 * Менеджер макетов страниц.
 * 
 * Позволяет контроллерам декларативно указывать, какой макет использовать.
 * Layout.php читает значение через ::get() и применяет соответствующий CSS-класс.
 * 
 * Использование в контроллере:
 *   use App\Modules\Common\Support\Layout;
 *   Layout::set(Layout::WIDE);
 *   return $this->render('index', $data);
 */
final class Layout
{
    // =========================================================================
    // КОНСТАНТЫ МАКЕТОВ
    // =========================================================================
    
    /** Стандартный — 720px (статьи, профиль, комментарии) */
    public const DEFAULT = 'default';
    
    /** Широкий — 1192px (главная, каталоги с сайдбаром) */
    public const WIDE = 'wide';
    
    /** Узкий — 520px (формы входа, регистрации, настройки) */
    public const NARROW = 'narrow';
    
    /** На всю ширину — 100% (admin-панель, дашборды, таблицы) */
    public const FULL = 'full';
    
    /** Средний — 900px (профиль с большим контентом, wiki) */
    public const MEDIUM = 'medium';

    // =========================================================================
    // ВНУТРЕННЕЕ ХРАНИЛИЩЕ
    // =========================================================================
    
    private static string $current = self::DEFAULT;
    
    /** Список всех допустимых макетов (для валидации) */
    private const ALLOWED = [
        self::DEFAULT,
        self::WIDE,
        self::NARROW,
        self::FULL,
        self::MEDIUM,
    ];

    // =========================================================================
    // ПУБЛИЧНЫЕ МЕТОДЫ
    // =========================================================================
    
    /**
     * Установить макет для текущей страницы.
     * Вызывается в контроллере перед $this->render().
     */
    public static function set(string $layout): void
    {
        if (!in_array($layout, self::ALLOWED, true)) {
            throw new \InvalidArgumentException(
                "Недопустимый layout: '{$layout}'. Допустимые: " . implode(', ', self::ALLOWED)
            );
        }
        
        self::$current = $layout;
    }

    /**
     * Получить текущий макет.
     * Вызывается в layout.php.
     */
    public static function get(): string
    {
        return self::$current;
    }

    /**
     * Получить CSS-класс для текущего макета.
     */
    public static function getClass(): string
    {
        return match (self::$current) {
            self::WIDE   => 'content-wide',
            self::NARROW => 'content-narrow',
            self::FULL   => 'content-full',
            self::MEDIUM => 'content-medium',
            default      => 'content-default',
        };
    }

    /**
     * Получить CSS-класс для body (для кастомизации).
     */
    public static function getBodyClass(): string
    {
        return 'layout-' . self::$current;
    }

    /**
     * Проверка: является ли текущий макет указанным.
     */
    public static function is(string $layout): bool
    {
        return self::$current === $layout;
    }

    /**
     * Сброс к дефолтному состоянию.
     * Вызывается в начале каждого запроса (защита от "грязного" состояния).
     */
    public static function reset(): void
    {
        self::$current = self::DEFAULT;
    }
}