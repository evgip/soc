<?php

declare(strict_types=1);

namespace App\Modules\Stories\Repositories;

use W3a\Core\Database\Database;

/**
 * Репозиторий для выборки данных историй (stories).
 * 
 * Реализует паттерн Fluent Interface (Query Builder) для гибкого и безопасного 
 * построения сложных SQL-запросов с JOIN-ами, агрегацией и пагинацией.
 * 
 * Основные преимущества:
 * 1. Устраняет дублирование кода при сборке одинаковых JOIN-ов в разных методах.
 * 2. Автоматически предотвращает дублирование таблиц в SQL-запросе при цепочечных вызовах.
 * 3. Инкапсулирует логику парсинга тегов (GROUP_CONCAT), избавляя от глобальных функций.
 */
class StoryRepository
{
    private Database $db;

    /** @var array Список выбираемых полей (SELECT) */
    private array $selects = ['s.*'];

    /** @var array Список условий присоединения таблиц (JOIN) */
    private array $joins = [];

    /** 
     * @var array Реестр уже добавленных таблиц. 
     * Используется как HashSet (O(1) поиск) для предотвращения дублирования JOIN-ов 
     * при цепочечных вызовах (например, withAuthor()->withAvatar()).
     */
    private array $joinedTables = [];

    /** @var array Условия фильтрации (WHERE) */
    private array $where = [];

    /** @var array Параметры для подготовленных выражений (PDO bindings) */
    private array $bindings = [];

    /** @var string Условие группировки (GROUP BY) */
    private string $groupBy = '';

    /** @var string Условие сортировки (ORDER BY) */
    private string $orderBy = '';

    /** @var int|null Лимит записей (LIMIT) */
    private ?int $limit = null;

    /** @var int|null Смещение записей (OFFSET) */
    private ?int $offset = null;

    /** @var string Основная таблица или подзапрос в FROM */
    private string $from = '`stories` s';

    /** 
     * @var bool Флаг, указывающий, что в запросе использовался GROUP_CONCAT для тегов,
     * и результат требует пост-обработки (гидратации) в PHP.
     */
    private bool $needsTagParsing = false;

    /**
     * Конструктор репозитория.
     *
     * @param Database $db Экземпляр обертки над PDO для выполнения запросов
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Добавляет данные автора (username) к выборке.
     * Безопасен для повторного вызова: JOIN добавится только один раз.
     *
     * @return self Для поддержки цепочечных вызовов (Fluent Interface)
     */
    public function withAuthor(): self
    {
        if (!isset($this->joinedTables['users'])) {
            $this->selects[] = 'u.username as author_name';
            $this->joins[] = 'JOIN `users` u ON s.user_id = u.id';
            $this->joinedTables['users'] = true;
        }
        return $this;
    }

    /**
     * Добавляет аватар автора к выборке.
     * Автоматически гарантирует наличие JOIN с таблицей `users`, 
     * так как `user_profiles` логически зависит от неё.
     *
     * @return self
     */
    public function withAvatar(): self
    {
        if (!isset($this->joinedTables['user_profiles'])) {
            $this->selects[] = 'up.avatar as author_avatar';

            // Гарантируем, что таблица users уже присоединена
            if (!isset($this->joinedTables['users'])) {
                $this->joins[] = 'JOIN `users` u ON s.user_id = u.id';
                $this->joinedTables['users'] = true;
            }

            $this->joins[] = 'LEFT JOIN `user_profiles` up ON u.id = up.user_id';
            $this->joinedTables['user_profiles'] = true;
        }
        return $this;
    }

    /**
     * Добавляет агрегированные данные тегов через GROUP_CONCAT.
     * Устанавливает флаг $needsTagParsing для последующей гидратации массива тегов в PHP.
     *
     * @return self
     */
    public function withTags(): self
    {
        if (!$this->needsTagParsing) {
            $this->selects[] = "GROUP_CONCAT(t.slug ORDER BY t.slug ASC) as tag_list";
            $this->selects[] = "GROUP_CONCAT(CONCAT(t.slug, '||', t.name) ORDER BY t.slug ASC) as tags_combined";
            $this->joins[] = 'LEFT JOIN `taggings` tg ON s.id = tg.story_id';
            $this->joins[] = 'LEFT JOIN `tags` t ON tg.tag_id = t.id';

            $this->joinedTables['taggings'] = true;
            $this->joinedTables['tags'] = true;

            // GROUP BY обязателен при использовании агрегатных функций (GROUP_CONCAT)
            $this->groupBy = 's.id';
            $this->needsTagParsing = true;
        }
        return $this;
    }

    /**
     * Переопределяет источник данных для выборки сохраненных историй (закладок).
     * Меняет основную таблицу на `saved_stories` с INNER JOIN к `stories`.
     *
     * @param int $userId ID пользователя, чьи закладки нужно получить
     * @return self
     */
    public function fromSaved(int $userId): self
    {
        $this->from = '`saved_stories` ss JOIN `stories` s ON ss.story_id = s.id';
        $this->selects[] = 'ss.created_at as saved_at';
        $this->where[] = 'ss.user_id = :saved_user_id';
        $this->bindings[':saved_user_id'] = $userId;
        return $this;
    }

    /**
     * Добавляет дополнительное поле в SELECT.
     *
     * @param string $select SQL-выражение для выбора (например, "MATCH(...) as relevance")
     * @return self
     */
    public function addSelect(string $select): self
    {
        $this->selects[] = $select;
        return $this;
    }

    /**
     * Добавляет одно условие WHERE и соответствующие биндинги.
     *
     * @param string $condition SQL-условие (например, "s.deleted_at IS NULL")
     * @param array $bindings Ассоциативный массив параметров для PDO
     * @return self
     */
    public function addWhere(string $condition, array $bindings = []): self
    {
        if (!empty($condition)) {
            $this->where[] = $condition;
            $this->bindings = array_merge($this->bindings, $bindings);
        }
        return $this;
    }

    /**
     * Добавляет массив условий WHERE за один вызов.
     *
     * @param array $conditions Массив SQL-условий
     * @param array $bindings Ассоциативный массив параметров для PDO
     * @return self
     */
    public function addWheres(array $conditions, array $bindings = []): self
    {
        foreach ($conditions as $condition) {
            $this->where[] = $condition;
        }
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    /**
     * Устанавливает порядок сортировки результатов.
     *
     * @param string $orderBy SQL-выражение сортировки (например, "s.created_at DESC")
     * @return self
     */
    public function setOrderBy(string $orderBy): self
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * Устанавливает ограничения пагинации.
     *
     * @param int $limit Максимальное количество записей
     * @param int $offset Смещение начала выборки (по умолчанию 0)
     * @return self
     */
    public function paginate(int $limit, int $offset = 0): self
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Собирает финальную SQL-строку из накопленных компонентов.
     *
     * @param bool $isCount Если true, формирует запрос для подсчета количества (COUNT), 
     *                      игнорируя GROUP BY, ORDER BY, LIMIT и OFFSET для оптимизации.
     * @return string Готовый SQL-запрос
     */
    private function buildSql(bool $isCount = false): string
    {
        // Для COUNT используем COUNT(DISTINCT s.id), чтобы LEFT JOIN с тегами 
        // не приводил к завышенному подсчету из-за умножения строк.
        $selects = $isCount ? ['COUNT(DISTINCT s.id) as count'] : $this->selects;

        $sql = "SELECT " . implode(', ', $selects) . " FROM " . $this->from;

        if (!empty($this->joins)) {
            $sql .= " " . implode(" ", $this->joins);
        }

        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(" AND ", $this->where);
        }

        // GROUP BY нужен только для выборки данных с агрегатными функциями. 
        // Для COUNT он не нужен и даже вреден (замедляет запрос).
        if (!$isCount && !empty($this->groupBy)) {
            $sql .= " GROUP BY " . $this->groupBy;
        }

        if (!$isCount && !empty($this->orderBy)) {
            $sql .= " ORDER BY " . $this->orderBy;
        }

        if (!$isCount && $this->limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        return $sql;
    }

    /**
     * Преобразует строковые результаты GROUP_CONCAT в структурированные PHP-массивы.
     *
     * @param array $results Массив строк, полученных из БД (передается по ссылке внутри цикла)
     * @return array Обработанный массив результатов
     */
    private function hydrateTags(array $results): array
    {
        if ($this->needsTagParsing) {
            foreach ($results as &$row) {
                $this->parseTagsCombined($row);
            }
        }
        return $results;
    }

    /**
     * Инкапсулирует логику парсинга тегов, ранее находившуюся в глобальной функции.
     * Разбивает строки "slug1,slug2" и "slug1||Name1,slug2||Name2" в удобные массивы.
     *
     * @param array $story Строка результата запроса (передается по ссылке)
     */
    private function parseTagsCombined(array &$story): void
    {
        $story['tags'] = !empty($story['tag_list']) ? explode(',', $story['tag_list']) : [];

        $tagsWithNames = [];
        if (!empty($story['tags_combined'])) {
            foreach (explode(',', $story['tags_combined']) as $pair) {
                $parts = explode('||', $pair);
                // Проверка на корректность формата "slug||name"
                if (count($parts) === 2) {
                    $tagsWithNames[] = ['slug' => $parts[0], 'name' => $parts[1]];
                }
            }
        }

        $story['tags_with_names'] = $tagsWithNames;

        // Очищаем сырые строковые поля, чтобы не засорять итоговый массив
        unset($story['tags_combined'], $story['tag_list']);
    }

    /**
     * Выполняет запрос и возвращает массив результатов.
     * Автоматически подставляет :limit и :offset в биндинги, если пагинация была задана.
     *
     * @return array Массив ассоциативных массивов с данными историй
     */
    public function get(): array
    {
        $sql = $this->buildSql();
        $bindings = $this->bindings;

        if ($this->limit !== null) {
            $bindings[':limit'] = $this->limit;
            $bindings[':offset'] = $this->offset;
        }

        return $this->hydrateTags($this->db->fetchAll($sql, $bindings));
    }

    /**
     * Выполняет запрос и возвращает первую найденную запись или null.
     * Принудительно устанавливает LIMIT 1, игнорируя ранее заданную пагинацию.
     *
     * @return array|null Ассоциативный массив с данными или null, если ничего не найдено
     */
    public function first(): ?array
    {
        // Для first() всегда принудительно ставим LIMIT 1
        $this->limit = 1;
        $this->offset = 0;

        $sql = $this->buildSql();
        $bindings = $this->bindings;
        $bindings[':limit'] = 1;
        $bindings[':offset'] = 0;

        $result = $this->db->fetchOne($sql, $bindings);

        if ($result && $this->needsTagParsing) {
            $this->parseTagsCombined($result);
        }

        return $result ?: null;
    }

    /**
     * Выполняет запрос подсчета количества записей.
     * Оптимизирован: не использует GROUP BY, ORDER BY, LIMIT и OFFSET.
     *
     * @return int Общее количество записей, удовлетворяющих условиям
     */
    public function count(): int
    {
        $sql = $this->buildSql(true);
        return (int)$this->db->fetchColumn($sql, $this->bindings);
    }
	
    /**
     * Переключает статус подписки автора на свою историю.
     * Безопасно: обновляет запись только если user_id совпадает с автором истории.
     */
    public function toggleFollow(int $storyId, int $userId): bool
    {
        return $this->db->execute(
            "UPDATE `stories` 
             SET `user_is_following` = NOT `user_is_following` 
             WHERE `id` = :id AND `user_id` = :user_id",
            [
                'id' => $storyId,
                'user_id' => $userId
            ]
        ) > 0;
    }

    /**
     * Проверяет, подписан ли автор на свою историю.
     */
    public function isFollowing(int $storyId, int $userId): bool
    {
        return (bool)$this->db->fetchColumn(
            "SELECT `user_is_following` FROM `stories` 
             WHERE `id` = :id AND `user_id` = :user_id",
            [
                'id' => $storyId,
                'user_id' => $userId
            ]
        );
    }
}
