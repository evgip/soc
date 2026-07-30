<?php

declare(strict_types=1);

namespace App\Modules\Flags\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;
use W3a\Core\Foundation\Config;

class Flag extends Model
{
    protected string $table = 'flags';
    private Config $config;

    protected array $fillable = [
        'user_id',
        'flaggable_type',
        'flaggable_id',
        'reason',
        'comment',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * Конструктор с инъекцией Database, Logger и Config
     */
    public function __construct(Database $db, Logger $logger, Config $config)
    {
        parent::__construct($db, $logger);
        $this->config = $config;
    }

    /**
     * Получить порог авто-скрытия из конфига
     */
    public function getHideThreshold(): int
    {
        return (int) $this->config->getInt('flags.hide_threshold', 3);
    }

    /**
     * Получить список причин жалобы из конфига
     */
    public function getReasons(): array
    {
        return (array) $this->config->getArray('flags.reasons', []);
    }

    /**
     * Получить кулдаун в минутах
     */
    public function getCooldownMinutes(): int
    {
        return (int) $this->config->getInt('flags.cooldown_minutes', 60);
    }

    /**
     * Получить максимальную длину комментария
     */
    public function getCommentMaxLength(): int
    {
        return (int) $this->config->getInt('flags.comment_max_length', 500);
    }

    /**
     * Пользователь уже жаловался на этот контент?
     */
    public function hasUserFlagged(int $userId, string $type, int $targetId): bool
    {
        $count = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE `user_id` = :uid
               AND `flaggable_type` = :type
               AND `flaggable_id` = :tid",
            [
                'uid'  => $userId,
                'type' => $type,
                'tid'  => $targetId,
            ]
        );
        return $count > 0;
    }

    /**
     * Подать жалобу
     */
    public function submit(int $userId, string $type, int $targetId, string $reason, ?string $comment = null): array
    {
        // Валидация типа
        if (!in_array($type, ['story', 'comment'], true)) {
            return ['ok' => false, 'error' => 'Неизвестный тип контента'];
        }

        // Валидация причины через нестатический метод
        $reasons = $this->getReasons();
        if (!array_key_exists($reason, $reasons)) {
            return ['ok' => false, 'error' => 'Неизвестная причина жалобы'];
        }

        // Проверка дубликата
        if ($this->hasUserFlagged($userId, $type, $targetId)) {
            return ['ok' => false, 'error' => 'Вы уже подавали жалобу на этот контент'];
        }

        // Проверка существования контента
        if (!$this->targetExists($type, $targetId)) {
            return ['ok' => false, 'error' => 'Контент не найден'];
        }

        // Ограничение длины комментария через нестатический метод
        $maxLength = $this->getCommentMaxLength();
        $commentClean = $comment ? trim(mb_substr($comment, 0, $maxLength)) : null;

        // Создаём жалобу
        $this->create([
            'user_id'        => $userId,
            'flaggable_type' => $type,
            'flaggable_id'   => $targetId,
            'reason'         => $reason,
            'comment'        => $commentClean,
            'status'         => 'pending',
        ]);

        // Увеличиваем счётчик флагов у цели
        $this->incrementFlagCount($type, $targetId);

        // Проверяем порог авто-скрытия
        $hidden = $this->checkAndHideIfNeeded($type, $targetId);

        return [
            'ok'     => true,
            'hidden' => $hidden,
        ];
    }

    /**
     * Увеличить счётчик флагов у цели
     */
    private function incrementFlagCount(string $type, int $targetId): void
    {
        $table = $type === 'story' ? 'stories' : 'comments';
        $this->db->execute(
            "UPDATE `{$table}` SET `flag_count` = `flag_count` + 1 WHERE `id` = :id",
            ['id' => $targetId]
        );
    }

    /**
     * Проверить порог и скрыть контент при необходимости
     */
    private function checkAndHideIfNeeded(string $type, int $targetId): bool
    {
        $table = $type === 'story' ? 'stories' : 'comments';
        $threshold = $this->getHideThreshold();

        $row = $this->db->fetchOne(
            "SELECT `flag_count`, `is_hidden_by_flags` FROM `{$table}` WHERE `id` = :id LIMIT 1",
            ['id' => $targetId]
        );

        if (!$row) {
            return false;
        }

        if ((int) $row['flag_count'] >= $threshold && !(int) $row['is_hidden_by_flags']) {
            $this->db->execute(
                "UPDATE `{$table}` SET `is_hidden_by_flags` = 1 WHERE `id` = :id",
                ['id' => $targetId]
            );
            return true;
        }

        return false;
    }

    /**
     * Существует ли целевой контент?
     */
    private function targetExists(string $type, int $targetId): bool
    {
        $table = $type === 'story' ? 'stories' : 'comments';
        $count = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$table}` WHERE `id` = :id",
            ['id' => $targetId]
        );
        return $count > 0;
    }

    /**
     * Список всех активных (pending) жалоб — для модераторов
     */
    public function getPendingFlags(): array
    {
        return $this->db->fetchAll(
            "SELECT f.*,
                    u.username AS reporter_name,
                    r.username AS resolver_name
             FROM `flags` f
             LEFT JOIN `users` u ON f.user_id = u.id
             LEFT JOIN `users` r ON f.resolved_by = r.id
             WHERE f.status = 'pending'
             ORDER BY f.created_at DESC"
        );
    }

    /**
     * Все жалобы (для полной истории)
     */
    public function getAllFlags(int $limit = 100): array
    {
        $sql = "SELECT f.*,
                       u.username AS reporter_name,
                       r.username AS resolver_name
                FROM `flags` f
                LEFT JOIN `users` u ON f.user_id = u.id
                LEFT JOIN `users` r ON f.resolved_by = r.id
                ORDER BY f.created_at DESC
                LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Количество pending-жалоб (для бейджа в меню)
     */
    public function getPendingCount(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `flags` WHERE `status` = 'pending'"
        );
    }

    /**
     * Разрешить жалобу (подтвердить — контент скрыт)
     */
    public function resolve(int $flagId, int $moderatorId): bool
    {
        $flag = $this->find($flagId);
        if (!$flag || $flag['status'] !== 'pending') {
            return false;
        }

        $this->db->execute(
            "UPDATE `flags`
             SET `status` = 'resolved', `resolved_by` = :mid, `resolved_at` = NOW()
             WHERE `id` = :id",
            ['mid' => $moderatorId, 'id' => $flagId]
        );

        // Окончательно скрываем контент
        $table = $flag['flaggable_type'] === 'story' ? 'stories' : 'comments';
        $this->db->execute(
            "UPDATE `{$table}` SET `is_hidden_by_flags` = 1 WHERE `id` = :id",
            ['id' => $flag['flaggable_id']]
        );

        return true;
    }

    /**
     * Отклонить жалобу (снять авто-скрытие)
     */
    public function dismiss(int $flagId, int $moderatorId): bool
    {
        $flag = $this->find($flagId);
        if (!$flag || $flag['status'] !== 'pending') {
            return false;
        }

        $this->db->execute(
            "UPDATE `flags`
             SET `status` = 'dismissed', `resolved_by` = :mid, `resolved_at` = NOW()
             WHERE `id` = :id",
            ['mid' => $moderatorId, 'id' => $flagId]
        );

        // Снимаем авто-скрытие
        $table = $flag['flaggable_type'] === 'story' ? 'stories' : 'comments';
        $this->db->execute(
            "UPDATE `{$table}` SET `is_hidden_by_flags` = 0 WHERE `id` = :id",
            ['id' => $flag['flaggable_id']]
        );

        return true;
    }
}