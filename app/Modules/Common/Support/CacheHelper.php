<?php

declare(strict_types=1);

namespace App\Modules\Common\Support;

use W3a\Core\Cache\FileCache;

/**
 * Тонкая обёртка над ядровым FileCache.
 * 
 * Добавляет два удобных метода:
 * - remember() — атомарное "получить или вычислить" (аналог Laravel Cache::remember)
 * - forgetMany() — удаление нескольких ключей одним вызовом
 * 
 * Почему не расширяем FileCache?
 * - Ядровый класс закрыт для расширения (sealed by convention)
 * - Обёртка позволяет легко заменить реализацию кэша (Redis, Memcached)
 *   без изменения бизнес-логики сервисов.
 */
final class CacheHelper
{
    public function __construct(private FileCache $cache) {}

    /**
     * Получить значение из кэша или вычислить через callback.
     * 
     * Алгоритм:
     * 1. Ищем ключ в кэше через FileCache::get()
     * 2. Если найден и не истёк TTL — возвращаем сразу
     * 3. Если не найден — вызываем $callback(), сохраняем результат и возвращаем
     * 
     * @param string $key Уникальный ключ кэша
     * @param int $ttl Время жизни в секундах
     * @param callable $callback Функция для вычисления значения
     * @return mixed Значение из кэша или от callback
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->cache->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->cache->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Удалить несколько ключей одним вызовом.
     * 
     * Полезно для инвалидации группы связанных ключей,
     * например: trending_stories_v1_3, _5, _10.
     * 
     * @param array $keys Массив ключей для удаления
     * @return int Количество успешно удалённых ключей
     */
    public function forgetMany(array $keys): int
    {
        $count = 0;
        foreach ($keys as $key) {
            if ($this->cache->delete($key)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Прямой доступ к ядровому FileCache.
     * Используется, когда нужны специфичные методы (clear, has).
     */
    public function cache(): FileCache
    {
        return $this->cache;
    }
}