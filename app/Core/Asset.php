<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Logger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Компилятор и менеджер статических ассетов (CSS/JS).
 * 
 * Отвечает за:
 * 1. Автоматическое обнаружение файлов .css и .js в модулях и активной теме.
 * 2. Проверку актуальности файлов (mtime) и автоматическую перекомпиляцию в dev-режиме.
 * 3. Объединение (bundling) и базовую минификацию файлов.
 * 4. Генерацию путей с кэш-бастингом (?v=timestamp).
 */
class Asset
{
    private static string $distCssFile;
    private static string $distAdminCssFile;
    private static string $distJsFile;
    
    /** @var Container|null Контейнер для получения сервисов (Config, Logger) */
    private static ?Container $container = null;

    /**
     * Установить контейнер зависимостей.
     * Вызывается один раз при инициализации приложения (в Application::bootstrap).
     */
    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    /**
     * Получить логгер из контейнера с fallback на глобальный контейнер.
     */
    private static function getLogger(): Logger
    {
        if (self::$container === null) {
            if (!isset($GLOBALS['app_container'])) {
                throw new \RuntimeException('Container not initialized for Asset Logger');
            }
            self::$container = $GLOBALS['app_container'];
        }
        return self::$container->get(Logger::class);
    }

    /**
     * Получить имя активной темы.
     * Сначала пытается получить из Config сервиса, затем из файла конфигурации.
     */
    private static function getActiveTheme(): string
    {
        try {
            if (self::$container !== null && self::$container->has(Config::class)) {
                return self::$container->get(Config::class)->get('config.app.theme', 'default');
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки контейнера и переходим к fallback
        }

        $configPath = dirname(__DIR__) . '/Config/config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            return $config['app']['theme'] ?? 'default';
        }

        return 'default';
    }

    /**
     * Инициализация путей к целевым (скомпилированным) файлам.
     */
    private static function init(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        self::$distCssFile      = $publicDir . '/css/app.min.css';
        self::$distAdminCssFile = $publicDir . '/css/admin.min.css';
        self::$distJsFile       = $publicDir . '/js/app.min.js';
    }

    // =========================================================================
    // ПУБЛИЧНЫЕ МЕТОДЫ ДЛЯ ШАБЛОНОВ (Генерация URL с кэш-бастингом)
    // =========================================================================

    /**
     * Получить URL публичного CSS с параметром версии для сброса кэша браузера.
     * В режиме development автоматически проверяет необходимость перекомпиляции.
     */
    public static function css(): string
    {
        self::init();
        if (self::isDevelopment()) {
            self::compileCssIfNeeded();
        }
        $version = file_exists(self::$distCssFile) ? filemtime(self::$distCssFile) : time();
        return "/css/app.min.css?v=" . $version;
    }

    /**
     * Получить URL админского CSS с параметром версии.
     */
    public static function adminCss(): string
    {
        self::init();
        if (self::isDevelopment()) {
            self::compileCssIfNeeded();
        }
        $version = file_exists(self::$distAdminCssFile) ? filemtime(self::$distAdminCssFile) : time();
        return "/css/admin.min.css?v=" . $version;
    }

    /**
     * Получить URL публичного JS с параметром версии.
     */
    public static function js(): string
    {
        self::init();
        if (self::isDevelopment()) {
            self::compileJsIfNeeded();
        }
        $version = file_exists(self::$distJsFile) ? filemtime(self::$distJsFile) : time();
        return "/js/app.min.js?v=" . $version;
    }

    // =========================================================================
    // МЕТОДЫ ДЛЯ АДМИНКИ (Ручная пересборка)
    // =========================================================================

    /**
     * Принудительная пересборка всех ассетов.
     * Вызывается из контроллера админки при нажатии кнопки "Перестроить CSS/JS".
     */
    public static function forceRebuild(): void
    {
        self::init();
        self::buildCss();
        self::buildJs();
    }

    // =========================================================================
    // ВНУТРЕННЯЯ ЛОГИКА ОБНАРУЖЕНИЯ И СБОРКИ
    // =========================================================================

    /**
     * Рекурсивно найти все файлы с заданным расширением в модулях и активной теме.
     * 
     * @param string $extension Расширение файла без точки ('css' или 'js')
     * @return array Массив абсолютных путей к найденным файлам, отсортированный по имени
     */
    private static function discoverFiles(string $extension): array
    {
        $discovered = [];
        $modulesPath = dirname(__DIR__) . '/Modules';
        $theme = self::getActiveTheme();
        $themeAssetsPath = dirname(__DIR__, 2) . "/themes/{$theme}/assets";

        if (is_dir($modulesPath)) {
            $discovered = array_merge($discovered, self::scanDirectory($modulesPath, $extension));
        }

        if (is_dir($themeAssetsPath)) {
            $discovered = array_merge($discovered, self::scanDirectory($themeAssetsPath, $extension));
        }

        // УЛУЧШЕНИЕ: Умная сортировка
        // 1. Сначала файлы из Modules/Common (наш css фреймворк должен быть первым)
        // 2. Затем остальные модули
        // 3. В конце файлы темы (чтобы тема могла переопределять всё)
        usort($discovered, function ($a, $b) {
            $isCommonA = strpos($a, 'Modules' . DIRECTORY_SEPARATOR . 'Common') !== false;
            $isCommonB = strpos($b, 'Modules' . DIRECTORY_SEPARATOR . 'Common') !== false;
            
            if ($isCommonA && !$isCommonB) return -1; // A (Common) идет раньше B
            if (!$isCommonA && $isCommonB) return 1;  // B (Common) идет раньше A
            
            // Если оба не Common или оба Common, сортируем по алфавиту (сработают префиксы 00_, 01_)
            return strcmp($a, $b);
        });

        return $discovered;
    }

    /**
     * Вспомогательный метод для рекурсивного сканирования директории.
     */
    private static function scanDirectory(string $directory, string $extension): array
    {
        $dirIterator = new RecursiveDirectoryIterator($directory);
        $iterator = new RecursiveIteratorIterator($dirIterator);
        $regex = new RegexIterator($iterator, '/^.+\.' . $extension . '$/i', RegexIterator::GET_MATCH);

        $files = [];
        foreach ($regex as $file) {
            $files[] = $file[0];
        }
        return $files;
    }

    /**
     * Проверить, нужно ли перекомпилировать CSS (сравнение mtime исходников и бандла).
     */
    private static function compileCssIfNeeded(): void
    {
        $cssFiles = self::discoverFiles('css');
        $needRebuild = false;

        $mtimeApp = file_exists(self::$distCssFile) ? filemtime(self::$distCssFile) : 0;
        $mtimeAdmin = file_exists(self::$distAdminCssFile) ? filemtime(self::$distAdminCssFile) : 0;

        foreach ($cssFiles as $path) {
            if (file_exists($path)) {
                // Определяем, является ли файл админским
                $isAdminFile = (strpos($path, 'Modules' . DIRECTORY_SEPARATOR . 'Admin') !== false) || 
                               (strpos($path, 'themes' . DIRECTORY_SEPARATOR . self::getActiveTheme() . DIRECTORY_SEPARATOR . 'admin') !== false);
                
                $targetMtime = $isAdminFile ? $mtimeAdmin : $mtimeApp;

                // Если исходный файл новее скомпилированного, нужна пересборка
                if (filemtime($path) > $targetMtime) {
                    $needRebuild = true;
                    break;
                }
            }
        }

        // Пересобираем, если найден измененный файл, или если бандлы еще не существуют (mtime === 0)
        if ($needRebuild || $mtimeApp === 0 || $mtimeAdmin === 0) {
            self::buildCss();
        }
    }

    /**
     * Физическая сборка и минификация CSS файлов.
     */
    private static function buildCss(): void
    {
        $files = self::discoverFiles('css');

        $appCss = "/* Public CSS Bundle: " . date('Y-m-d H:i:s') . " */" . PHP_EOL;
        $adminCss = "/* Admin CSS Bundle: " . date('Y-m-d H:i:s') . " */" . PHP_EOL;

        $appCount = 0;
        $adminCount = 0;
        $rootDir = dirname(__DIR__, 2); // Корень проекта для относительных путей в комментариях

        foreach ($files as $path) {
            if (file_exists($path)) {
                $shortPath = str_replace($rootDir, '', $path);
                $content = "/* Source: {$shortPath} */" . PHP_EOL . file_get_contents($path) . PHP_EOL . PHP_EOL;

                // Разделяем на публичный и админский бандлы
                $isAdminFile = (strpos($path, 'Modules' . DIRECTORY_SEPARATOR . 'Admin') !== false) || 
                               (strpos($path, 'themes' . DIRECTORY_SEPARATOR . self::getActiveTheme() . DIRECTORY_SEPARATOR . 'admin') !== false);

                if ($isAdminFile) {
                    $adminCss .= $content;
                    $adminCount++;
                } else {
                    $appCss .= $content;
                    $appCount++;
                }
            }
        }

        // Простая, но эффективная минификация CSS (удаляет комментарии, лишние пробелы и переносы)
        $minify = function (string $css): string {
            $css = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $css); // Удалить комментарии
            $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);       // Удалить переносы строк и табы
            $css = preg_replace('/ {2,}/', ' ', $css);                      // Множественные пробелы в один
            return str_replace([' {', '{ ', '; '], ['{', '{', ';'], $css);  // Убрать пробелы вокруг скобок и точек с запятой
        };

        $dir = dirname(self::$distCssFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Атомарная запись (опционально, но file_put_contents с LOCK_EX достаточно надежен)
        file_put_contents(self::$distCssFile, $minify($appCss), LOCK_EX);
        file_put_contents(self::$distAdminCssFile, $minify($adminCss), LOCK_EX);

        $logger = self::getLogger();
        $logger->info("Asset Compiler: Сборка CSS завершена. app.min.css (файлов: {$appCount}), admin.min.css (файлов: {$adminCount}). Активная тема: " . self::getActiveTheme());
    }

    /**
     * Проверить, нужно ли перекомпилировать JS.
     */
    private static function compileJsIfNeeded(): void
    {
        $distMtime = file_exists(self::$distJsFile) ? filemtime(self::$distJsFile) : 0;
        $needRebuild = false;
        $files = self::discoverFiles('js');

        foreach ($files as $path) {
            if (file_exists($path) && filemtime($path) > $distMtime) {
                $needRebuild = true;
                break;
            }
        }
        
        if ($needRebuild || $distMtime === 0) {
            self::buildJs();
        }
    }

    /**
     * Физическая сборка JS файлов с учетом приоритета core_utils.js.
     */
    private static function buildJs(): void
    {
        $compiled = "/* JavaScript Bundle: " . date('Y-m-d H:i:s') . " */" . PHP_EOL;
        $files = self::discoverFiles('js');

        // Файл с базовыми утилитами должен быть загружен ПЕРВЫМ
        $priorityFile = dirname(__DIR__) . '/Modules/Common/Views/js/core_utils.js';
        
        $orderedFiles = [];
        $otherFiles = [];
        
        foreach ($files as $path) {
            // Используем realpath для надежного сравнения путей
            if (realpath($path) === realpath($priorityFile)) {
                array_unshift($orderedFiles, $path);
            } else {
                $otherFiles[] = $path;
            }
        }
        
        // Объединяем: сначала core_utils, потом все остальные (включая файлы темы)
        $files = array_merge($orderedFiles, $otherFiles);
        $rootDir = dirname(__DIR__, 2);

        foreach ($files as $path) {
            if (file_exists($path)) {
                $shortPath = str_replace($rootDir, '', $path);
                // Добавляем точку с запятой перед каждым файлом для защиты от слитых инструкций (IIFE safety)
                $compiled .= ";" . PHP_EOL . "/* Source: {$shortPath} */" . PHP_EOL . file_get_contents($path) . PHP_EOL;
            }
        }

        // Минификация JS (удаление многострочных комментариев, однострочных комментариев, лишних пробелов)
        $compiled = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $compiled);
        $compiled = preg_replace('/^[ \t]*\/\/.*$/m', '', $compiled);
        $compiled = str_replace("\t", " ", $compiled);
        $compiled = preg_replace('/ +/', ' ', $compiled);

        $dir = dirname(self::$distJsFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(self::$distJsFile, $compiled, LOCK_EX);

        $logger = self::getLogger();
        $logger->info("Asset Compiler: JS сборка обновлена. Всего файлов: " . count($files) . ". Активная тема: " . self::getActiveTheme());
    }

    /**
     * Проверка, находится ли приложение в режиме разработки.
     */
    private static function isDevelopment(): bool
    {
        try {
            if (self::$container !== null && self::$container->has(Config::class)) {
                return self::$container->get(Config::class)->get('config.app.env', 'development') === 'development';
            }
        } catch (\Throwable $e) {
            // Fallback
        }
        
        $configPath = dirname(__DIR__) . '/Config/config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            return ($config['app']['env'] ?? 'development') === 'development';
        }
        
        return true; // По умолчанию считаем development безопаснее
    }
}