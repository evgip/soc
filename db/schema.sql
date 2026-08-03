--
-- Структура таблицы `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Guest',
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'guest',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `role`, `ip_address`, `action`, `description`, `category`, `payload`, `created_at`) VALUES
(4, 1, 'Admin', 'admin', '127.0.0.1', 'admin.tag_updated', 'Администратор изменил параметры тега #libarea', 'general', NULL, '2026-06-18 14:47:20'),
(5, 1, 'Admin', 'admin', '127.0.0.1', 'admin.tag_updated', 'Администратор изменил параметры тега #php', 'general', NULL, '2026-06-18 14:47:29'),
(6, 1, 'Admin', 'admin', '127.0.0.1', 'admin.tag_updated', 'Администратор изменил параметры тега #security', 'general', NULL, '2026-06-18 14:47:39'),
(7, 1, 'Admin', 'admin', '127.0.0.1', 'admin.tag_updated', 'Администратор изменил параметры тега #show', 'general', NULL, '2026-06-18 14:47:51'),
(8, 1, 'Admin', 'admin', '127.0.0.1', 'admin.tag_updated', 'Администратор изменил параметры тега #video', 'general', NULL, '2026-06-18 14:47:59');

-- --------------------------------------------------------

--
-- Структура таблицы `banned_ips`
--

CREATE TABLE `banned_ips` (
  `id` int UNSIGNED NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Языки программирования', 'languages', 'PHP, Python, JavaScript и другие', 10, '2026-06-15 22:10:39', '2026-06-15 22:10:39', NULL),
(2, 'Практики', 'practices', 'Безопасность, архитектура, методологии', 20, '2026-06-15 22:10:39', '2026-06-15 22:10:39', NULL),
(3, 'Формат', 'format', 'Видео, демонстрации проектов, подкасты', 30, '2026-06-15 22:10:39', '2026-06-15 22:10:39', NULL),
(4, 'Разное', 'other', 'Общие темы и обсуждения', 99, '2026-06-15 22:10:39', '2026-06-15 22:10:39', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `comments`
--

CREATE TABLE `comments` (
  `id` int UNSIGNED NOT NULL,
  `story_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` int NOT NULL DEFAULT '1',
  `flag_count` int UNSIGNED NOT NULL DEFAULT '0',
  `is_hidden_by_flags` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `confidence_score` decimal(10,8) NOT NULL DEFAULT '0.00000000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `comments`
--

INSERT INTO `comments` (`id`, `story_id`, `user_id`, `parent_id`, `comment`, `score`, `flag_count`, `is_hidden_by_flags`, `created_at`, `updated_at`, `deleted_at`, `confidence_score`) VALUES
(1, 1, 1, NULL, 'Отличная архитектура у фреймворка!', 1, 0, 0, '2026-06-07 10:20:16', '2026-06-21 05:48:02', NULL, 0.37844750),
(2, 1, 1, 1, 'Согласен, один SQL-запрос на дерево — это быстро.', 1, 0, 0, '2026-06-07 10:20:16', '2026-06-21 05:48:02', NULL, 0.37844750),
(3, 1, 1, 2, 'И рекурсия на анонимных функциях PHP выглядит лаконично.', 1, 0, 0, '2026-06-07 10:20:16', '2026-06-21 05:48:02', NULL, 0.37844750),
(4, 1, 1, NULL, 'А когда мы добавим форму отправки нового комментария?', 1, 0, 0, '2026-06-07 10:20:16', '2026-06-21 05:48:02', NULL, 0.37844750),
(5, 2, 1, NULL, 'Добавим первые комментарий 333', 1, 0, 0, '2026-06-07 10:51:00', '2026-06-21 05:48:02', NULL, 0.37844750),
(6, 2, 1, 5, 'Добавим ответ Админу', 2, 0, 0, '2026-06-07 10:51:12', '2026-06-21 05:48:02', NULL, 0.54909237);

-- --------------------------------------------------------

--
-- Структура таблицы `content_logs`
--

CREATE TABLE `content_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `target_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` bigint UNSIGNED NOT NULL,
  `actor_id` bigint UNSIGNED DEFAULT NULL COMMENT 'NULL если действие от сообщества',
  `action_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Человекочитаемое описание изменения',
  `is_community_action` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `content_suggestions`
--

CREATE TABLE `content_suggestions` (
  `id` bigint UNSIGNED NOT NULL,
  `target_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Story или Comment',
  `target_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `proposed_data` json NOT NULL COMMENT 'JSON с предлагаемыми изменениями (нормализованный)',
  `proposed_data_hash` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (md5(cast(`proposed_data` as char charset utf8mb4))) STORED,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `conversations`
--

CREATE TABLE `conversations` (
  `id` int UNSIGNED NOT NULL,
  `user_one` int UNSIGNED NOT NULL,
  `user_two` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `domains`
--

CREATE TABLE `domains` (
  `id` int UNSIGNED NOT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('banned','allowed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'banned',
  `ban_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banned_by` int UNSIGNED DEFAULT NULL COMMENT 'ID модератора/админа, забанившего домен',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `email_activations`
--

CREATE TABLE `email_activations` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `flags`
--

CREATE TABLE `flags` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Автор жалобы',
  `flaggable_type` enum('story','comment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Тип контента',
  `flaggable_id` int UNSIGNED NOT NULL COMMENT 'ID story или comment',
  `reason` enum('spam','offensive','duplicate','broken_link','misleading','off_topic','illegal','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `comment` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Пояснение от пользователя',
  `status` enum('pending','resolved','dismissed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `resolved_by` int UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `invitations`
--

CREATE TABLE `invitations` (
  `id` int UNSIGNED NOT NULL,
  `code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `inviter_id` int UNSIGNED NOT NULL COMMENT 'ID пользователя, который пригласил',
  `invitee_email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email получателя (опционально)',
  `invitee_id` int UNSIGNED DEFAULT NULL COMMENT 'ID зарегистрировавшегося пользователя',
  `status` enum('pending','accepted','expired','revoked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `invitation_requests`
--

CREATE TABLE `invitation_requests` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Причина запроса',
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `messages`
--

CREATE TABLE `messages` (
  `id` int UNSIGNED NOT NULL,
  `conversation_id` int UNSIGNED NOT NULL,
  `sender_id` int UNSIGNED NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `mod_activity`
--

CREATE TABLE `mod_activity` (
  `id` int UNSIGNED NOT NULL,
  `moderator_id` int UNSIGNED NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `count` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `mod_notes`
--

CREATE TABLE `mod_notes` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Пользователь, к которому относится заметка',
  `moderator_id` int UNSIGNED NOT NULL COMMENT 'Автор заметки',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=видна только модераторам, 0=публичная',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `muted_users`
--

CREATE TABLE `muted_users` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `muted_user_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `rate_limits`
--

CREATE TABLE `rate_limits` (
  `identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint_action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `window_start` timestamp NOT NULL COMMENT 'Начало временного окна (например, округленное до минуты)',
  `request_count` int UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`identifier`, `endpoint_action`, `window_start`),
  KEY `idx_cleanup` (`window_start`) -- Индекс только для редкой фоновой очистки
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Структура таблицы `read_ribbons`
--

CREATE TABLE `read_ribbons` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `story_id` int UNSIGNED NOT NULL,
  `last_read_comment_id` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID последнего прочитанного комментария (0 = история открыта, но комментариев не было)',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Отметки прочитанных историй для индикации новых комментариев';

--
-- Дамп данных таблицы `read_ribbons`
--

INSERT INTO `read_ribbons` (`id`, `user_id`, `story_id`, `last_read_comment_id`, `updated_at`) VALUES
(1, 1, 2, 6, '2026-06-16 04:32:51'),
(2, 1, 1, 4, '2026-06-18 14:48:40');

-- --------------------------------------------------------

--
-- Структура таблицы `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `selector` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Публичный идентификатор для поиска',
  `hashed_validator` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Хэш валидатора',
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NOT NULL COMMENT 'Время истечения токена',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `saved_stories`
--

CREATE TABLE `saved_stories` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `story_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `stories`
--

CREATE TABLE `stories` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `score` int NOT NULL DEFAULT '1',
  `hotness` float NOT NULL DEFAULT '0',
  `flag_count` int UNSIGNED NOT NULL DEFAULT '0',
  `is_hidden_by_flags` tinyint(1) NOT NULL DEFAULT '0',
  `comments_count` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_is_following` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Подписан ли автор на уведомления о новых комментариях'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `stories`
--

INSERT INTO `stories` (`id`, `user_id`, `title`, `url`, `domain`, `description`, `score`, `hotness`, `flag_count`, `is_hidden_by_flags`, `comments_count`, `created_at`, `updated_at`, `deleted_at`, `user_is_following`) VALUES
(1, 1, 'Первый пост, написал статью про этот движок.', 'https://libarea.ru/articles/2222/reshil-napisat-lobsters-na-php', 'libarea.ru', 'Редирект будет на LibArea, а если нажать на домен, то сортировка публикаций тут по этому домену.', 15, 14376.8, 0, 0, 4, '2026-06-07 09:53:33', '2026-06-21 23:53:51', NULL, 1),
(2, 1, 'Это второй пост для теста', NULL, NULL, 'Заполните, если это чисто текстовый пост, либо как дополнение к ссылке. 333', 2, 14375.9, 0, 0, 2, '2026-06-07 10:14:37', '2026-06-21 23:53:51', NULL, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `taggings`
--

CREATE TABLE `taggings` (
  `id` int UNSIGNED NOT NULL,
  `story_id` int UNSIGNED NOT NULL,
  `tag_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `taggings`
--

INSERT INTO `taggings` (`id`, `story_id`, `tag_id`) VALUES
(5, 1, 3),
(4, 2, 1),
(3, 2, 4);

-- --------------------------------------------------------

--
-- Структура таблицы `tags`
--

CREATE TABLE `tags` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hotness_mod` float NOT NULL DEFAULT '0' COMMENT 'Модификатор горячести для тега',
  `slug` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_media` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `category_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tags`
--

INSERT INTO `tags` (`id`, `name`, `hotness_mod`, `slug`, `description`, `is_media`, `created_at`, `deleted_at`, `category_id`) VALUES
(1, 'php', 0, 'php', 'Язык1 программирования PHP и фреймворки', 1, '2026-06-07 13:08:44', NULL, 1),
(2, 'безопасность', 0, 'security', 'Уязвимости, безопасность и CSP политики', 1, '2026-06-07 13:08:44', NULL, 2),
(3, 'личное', 0, 'show', 'Демонстрация личных проектов разработчиков', 1, '2026-06-07 13:08:44', NULL, 3),
(4, 'видео', 0, 'video', 'Материал содержит видеоролик', 1, '2026-06-07 13:08:44', NULL, 3),
(5, 'libarea', 0, 'libarea', 'Обсуждение сайта...', 1, '2026-06-07 13:46:26', NULL, 4);

-- --------------------------------------------------------

--
-- Структура таблицы `tag_filters`
--

CREATE TABLE `tag_filters` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `tag_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('user','moderator','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `last_read_comments_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `updated_at`, `deleted_at`, `is_active`, `last_read_comments_at`) VALUES
(1, 'Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-06-07 02:28:55', '2026-06-15 22:15:11', NULL, 1, NULL),
(2, 'test', 'test@test.ru', '$2y$10$xKEu8vvztJ/yoA2yaHHque9z4el8tdWKZDI4SY/AvX3HyyojdHFva', 'user', '2026-06-12 12:31:51', '2026-06-15 22:15:14', NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `user_bans`
--

CREATE TABLE `user_bans` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `banned_by` int UNSIGNED DEFAULT NULL COMMENT 'ID модератора/админа',
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата начала бана',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Дата окончания (NULL = перманентный бан)',
  `unbanned_at` timestamp NULL DEFAULT NULL COMMENT 'Дата досрочного снятия бана',
  `unbanned_by` int UNSIGNED DEFAULT NULL COMMENT 'Кто досрочно разбанил'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Тип объекта (Comment, Message, Story)',
  `notifiable_id` int UNSIGNED DEFAULT NULL COMMENT 'ID связанного объекта',
  `actor_id` int UNSIGNED DEFAULT NULL COMMENT 'ID пользователя, вызвавшего событие',
  `message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'Время прочтения',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int UNSIGNED NOT NULL COMMENT 'Ссылка на пользователя',
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'О себе',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Имя файла аватара',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `bio`, `avatar`, `updated_at`) VALUES
(1, 'О себе я расскажуыва ыва ыва 3', 'a6fa1e10b80411629030d4079a5d2253.jpg', '2026-06-15 22:15:11'),
(2, 'sdfsdfsdf', NULL, '2026-06-15 22:15:14');

-- --------------------------------------------------------

--
-- Структура таблицы `user_settings`
--

CREATE TABLE `user_settings` (
  `user_id` int UNSIGNED NOT NULL COMMENT 'Ссылка на пользователя',
  `notify_on_reply` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Уведомлять об ответах на мои комментарии',
  `notify_on_story_comment` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Уведомлять о комментариях в историях, на которые я подписан',
  `notify_on_mention` tinyint(1) NOT NULL DEFAULT '1',
  `notify_on_message` tinyint(1) NOT NULL DEFAULT '1',
  `email_notifications` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Дублировать уведомления на email',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_settings`
--

INSERT INTO `user_settings` (`user_id`, `notify_on_reply`, `notify_on_story_comment`, `notify_on_mention`, `notify_on_message`, `email_notifications`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, '2026-06-15 22:15:11'),
(2, 1, 1, 1, 1, 1, '2026-06-15 22:15:14');

-- --------------------------------------------------------

--
-- Структура таблицы `votes`
--

CREATE TABLE `votes` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `votable_type` enum('story','comment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `votable_id` int UNSIGNED NOT NULL,
  `vote_type` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `votes`
--

INSERT INTO `votes` (`id`, `user_id`, `votable_type`, `votable_id`, `vote_type`, `created_at`) VALUES
(5, 1, 'comment', 6, 1, '2026-06-07 11:28:01'),
(6, 1, 'story', 2, 1, '2026-06-07 11:28:05');

-- --------------------------------------------------------

--
-- Структура таблицы `wiki_pages`
--

CREATE TABLE `wiki_pages` (
  `id` int UNSIGNED NOT NULL,
  `tag_id` int UNSIGNED DEFAULT NULL COMMENT 'Связь с тегом (опционально)',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Markdown контент',
  `rendered_content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Кэшированный HTML',
  `author_id` int UNSIGNED NOT NULL COMMENT 'Автор страницы',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Основная страница тега',
  `status` enum('draft','published','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `view_count` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `wiki_permissions`
--

CREATE TABLE `wiki_permissions` (
  `id` int UNSIGNED NOT NULL,
  `tag_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `can_edit` tinyint(1) NOT NULL DEFAULT '1',
  `can_delete` tinyint(1) NOT NULL DEFAULT '0',
  `granted_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `wiki_revisions`
--

CREATE TABLE `wiki_revisions` (
  `id` int UNSIGNED NOT NULL,
  `wiki_page_id` int UNSIGNED NOT NULL,
  `revision_number` int UNSIGNED NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `edit_summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_audit_category_created` (`category`,`created_at`),
  ADD KEY `idx_audit_action_ip_time` (`action`,`ip_address`,`created_at`);

--
-- Индексы таблицы `banned_ips`
--
ALTER TABLE `banned_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`);

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_categories_slug` (`slug`),
  ADD KEY `idx_categories_sort` (`sort_order`);

--
-- Индексы таблицы `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comments_user_id` (`user_id`),
  ADD KEY `idx_comments_deleted_at` (`deleted_at`),
  ADD KEY `idx_comments_parent` (`parent_id`,`created_at`),
  ADD KEY `idx_comments_story_score` (`story_id`,`score`,`created_at`);
ALTER TABLE `comments` ADD FULLTEXT KEY `idx_comments_search` (`comment`);

--
-- Индексы таблицы `content_logs`
--
ALTER TABLE `content_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_target` (`target_type`,`target_id`),
  ADD KEY `idx_actor` (`actor_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Индексы таблицы `content_suggestions`
--
ALTER TABLE `content_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_suggestion` (`target_type`,`target_id`,`user_id`,`proposed_data_hash`),
  ADD KEY `idx_target` (`target_type`,`target_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Индексы таблицы `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user_pair_unique` (`user_one`,`user_two`),
  ADD KEY `fk_conv_user_two` (`user_two`);

--
-- Индексы таблицы `domains`
--
ALTER TABLE `domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_domains_domain` (`domain`),
  ADD KEY `idx_domains_status` (`status`),
  ADD KEY `idx_domains_deleted_at` (`deleted_at`),
  ADD KEY `fk_domains_banned_by` (`banned_by`);

--
-- Индексы таблицы `email_activations`
--
ALTER TABLE `email_activations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `fk_email_activation_uid` (`user_id`);

--
-- Индексы таблицы `flags`
--
ALTER TABLE `flags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_flags_unique` (`user_id`,`flaggable_type`,`flaggable_id`),
  ADD KEY `idx_flags_status` (`status`),
  ADD KEY `idx_flags_target` (`flaggable_type`,`flaggable_id`),
  ADD KEY `fk_flags_user` (`user_id`),
  ADD KEY `fk_flags_resolved_by` (`resolved_by`),
  ADD KEY `idx_flags_deleted_at` (`deleted_at`);

--
-- Индексы таблицы `invitations`
--
ALTER TABLE `invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`code`),
  ADD KEY `idx_inviter` (`inviter_id`),
  ADD KEY `idx_invitee` (`invitee_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Индексы таблицы `invitation_requests`
--
ALTER TABLE `invitation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Индексы таблицы `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_msg_sender_id` (`sender_id`),
  ADD KEY `idx_msg_lookup` (`conversation_id`,`created_at`);

--
-- Индексы таблицы `mod_activity`
--
ALTER TABLE `mod_activity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_mod_action_date` (`moderator_id`,`action`,`date`),
  ADD KEY `idx_date` (`date`);

--
-- Индексы таблицы `mod_notes`
--
ALTER TABLE `mod_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_moderator_id` (`moderator_id`),
  ADD KEY `idx_is_private` (`is_private`);

--
-- Индексы таблицы `muted_users`
--
ALTER TABLE `muted_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_muted` (`user_id`,`muted_user_id`),
  ADD KEY `idx_muted_user_id` (`muted_user_id`);

--
-- Индексы таблицы `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_reset_email_token` (`email`,`token`);

--
-- Индексы таблицы `read_ribbons`
--
ALTER TABLE `read_ribbons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_story` (`user_id`,`story_id`),
  ADD KEY `idx_user_updated` (`user_id`,`updated_at`),
  ADD KEY `fk_rr_story` (`story_id`);

--
-- Индексы таблицы `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_selector` (`selector`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Индексы таблицы `saved_stories`
--
ALTER TABLE `saved_stories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_story` (`user_id`,`story_id`),
  ADD KEY `idx_story_id` (`story_id`);

--
-- Индексы таблицы `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stories_user_id` (`user_id`),
  ADD KEY `idx_stories_score` (`score` DESC),
  ADD KEY `idx_stories_created_at` (`created_at` DESC),
  ADD KEY `idx_stories_hotness` (`hotness`),
  ADD KEY `idx_stories_deleted_hotness` (`deleted_at`,`hotness`);
ALTER TABLE `stories` ADD FULLTEXT KEY `idx_stories_search` (`title`,`description`);

--
-- Индексы таблицы `taggings`
--
ALTER TABLE `taggings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `story_tag_unique` (`story_id`,`tag_id`),
  ADD KEY `fk_taggings_tag_id` (`tag_id`);

--
-- Индексы таблицы `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`) USING BTREE,
  ADD KEY `idx_tags_category_id` (`category_id`);

--
-- Индексы таблицы `tag_filters`
--
ALTER TABLE `tag_filters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_tag` (`user_id`,`tag_id`),
  ADD KEY `idx_tag_id` (`tag_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Индексы таблицы `user_bans`
--
ALTER TABLE `user_bans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Индексы таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_notif_lookup` (`user_id`,`is_read`,`created_at` DESC),
  ADD KEY `idx_notifiable` (`notifiable_type`,`notifiable_id`),
  ADD KEY `idx_actor` (`actor_id`);

--
-- Индексы таблицы `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Индексы таблицы `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- Индексы таблицы `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid_type_id_unique` (`user_id`,`votable_type`,`votable_id`),
  ADD KEY `idx_votable_lookup` (`votable_type`,`votable_id`);

--
-- Индексы таблицы `wiki_pages`
--
ALTER TABLE `wiki_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_tag_slug` (`tag_id`,`slug`),
  ADD KEY `wiki_pages_author_id` (`author_id`),
  ADD KEY `wiki_pages_status` (`status`);

--
-- Индексы таблицы `wiki_permissions`
--
ALTER TABLE `wiki_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wiki_permissions_unique` (`tag_id`,`user_id`),
  ADD KEY `wiki_permissions_user_id` (`user_id`),
  ADD KEY `fk_wiki_permissions_granted_by` (`granted_by`);

--
-- Индексы таблицы `wiki_revisions`
--
ALTER TABLE `wiki_revisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wiki_revisions_unique` (`wiki_page_id`,`revision_number`),
  ADD KEY `wiki_revisions_user_id` (`user_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `banned_ips`
--
ALTER TABLE `banned_ips`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `content_logs`
--
ALTER TABLE `content_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `content_suggestions`
--
ALTER TABLE `content_suggestions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `domains`
--
ALTER TABLE `domains`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `email_activations`
--
ALTER TABLE `email_activations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `flags`
--
ALTER TABLE `flags`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `invitations`
--
ALTER TABLE `invitations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `invitation_requests`
--
ALTER TABLE `invitation_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `mod_activity`
--
ALTER TABLE `mod_activity`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `mod_notes`
--
ALTER TABLE `mod_notes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `muted_users`
--
ALTER TABLE `muted_users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `read_ribbons`
--
ALTER TABLE `read_ribbons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `saved_stories`
--
ALTER TABLE `saved_stories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `stories`
--
ALTER TABLE `stories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `taggings`
--
ALTER TABLE `taggings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `tag_filters`
--
ALTER TABLE `tag_filters`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `user_bans`
--
ALTER TABLE `user_bans`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `wiki_pages`
--
ALTER TABLE `wiki_pages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `wiki_permissions`
--
ALTER TABLE `wiki_permissions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `wiki_revisions`
--
ALTER TABLE `wiki_revisions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_story_id` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conv_user_one` FOREIGN KEY (`user_one`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conv_user_two` FOREIGN KEY (`user_two`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `domains`
--
ALTER TABLE `domains`
  ADD CONSTRAINT `fk_domains_banned_by` FOREIGN KEY (`banned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `email_activations`
--
ALTER TABLE `email_activations`
  ADD CONSTRAINT `fk_email_activation_uid` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `flags`
--
ALTER TABLE `flags`
  ADD CONSTRAINT `fk_flags_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_flags_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `invitations`
--
ALTER TABLE `invitations`
  ADD CONSTRAINT `fk_invitations_invitee` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invitations_inviter` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_conversation_id` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_sender_id` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `mod_activity`
--
ALTER TABLE `mod_activity`
  ADD CONSTRAINT `fk_mod_activity_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `mod_notes`
--
ALTER TABLE `mod_notes`
  ADD CONSTRAINT `fk_mod_notes_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mod_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `muted_users`
--
ALTER TABLE `muted_users`
  ADD CONSTRAINT `fk_muted_target` FOREIGN KEY (`muted_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_muted_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `read_ribbons`
--
ALTER TABLE `read_ribbons`
  ADD CONSTRAINT `fk_rr_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `saved_stories`
--
ALTER TABLE `saved_stories`
  ADD CONSTRAINT `fk_saved_stories_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_saved_stories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `stories`
--
ALTER TABLE `stories`
  ADD CONSTRAINT `fk_stories_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `taggings`
--
ALTER TABLE `taggings`
  ADD CONSTRAINT `fk_taggings_story_id` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_taggings_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tags`
--
ALTER TABLE `tags`
  ADD CONSTRAINT `fk_tags_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `tag_filters`
--
ALTER TABLE `tag_filters`
  ADD CONSTRAINT `fk_tag_filters_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tag_filters_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `fk_notif_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notification_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `fk_poly_votes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `wiki_pages`
--
ALTER TABLE `wiki_pages`
  ADD CONSTRAINT `fk_wiki_pages_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wiki_pages_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `wiki_permissions`
--
ALTER TABLE `wiki_permissions`
  ADD CONSTRAINT `fk_wiki_permissions_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wiki_permissions_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wiki_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `wiki_revisions`
--
ALTER TABLE `wiki_revisions`
  ADD CONSTRAINT `fk_wiki_revisions_page` FOREIGN KEY (`wiki_page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wiki_revisions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- События
--
CREATE DEFINER=`root`@`%` EVENT `cleanup_audit_logs` ON SCHEDULE EVERY 1 DAY STARTS '2026-07-08 14:00:40' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)$$

CREATE EVENT `cleanup_rate_limits` 
ON SCHEDULE EVERY 1 HOUR 
ON COMPLETION NOT PRESERVE 
ENABLE 
DO 
  -- Удаляем окна старше 24 часов (с запасом, чтобы не задеть текущие)
  DELETE FROM `rate_limits` 
  WHERE `window_start` < NOW() - INTERVAL 24 HOUR$$

DELIMITER ;


ALTER TABLE `rate_limits` MODIFY `identifier` varchar(255) NOT NULL;
ALTER TABLE `rate_limits` DROP PRIMARY KEY, ADD `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;
ALTER TABLE `rate_limits` ADD UNIQUE KEY `unique_rate_limit` (`identifier`, `endpoint_action`, `window_start`);



--
-- Структура таблицы `followed_users`
--

CREATE TABLE `followed_users` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `followed_user_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `followed_tags`
--

CREATE TABLE `followed_tags` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `tag_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Индексы таблицы `followed_users`
--
ALTER TABLE `followed_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_followed` (`user_id`,`followed_user_id`),
  ADD KEY `idx_followed_user_id` (`followed_user_id`);

--
-- Индексы таблицы `followed_tags`
--
ALTER TABLE `followed_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_followed_tag` (`user_id`,`tag_id`),
  ADD KEY `idx_followed_tag_id` (`tag_id`);

-- --------------------------------------------------------

--
-- AUTO_INCREMENT для таблицы `followed_users`
--
ALTER TABLE `followed_users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `followed_tags`
--
ALTER TABLE `followed_tags`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Ограничения внешнего ключа таблицы `followed_users`
--
ALTER TABLE `followed_users`
  ADD CONSTRAINT `fk_followed_target` FOREIGN KEY (`followed_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_followed_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `followed_tags`
--
ALTER TABLE `followed_tags`
  ADD CONSTRAINT `fk_followed_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_followed_tags_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;