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
  
  
  
  
 CREATE TABLE `followed_collections` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `collection_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_collection` (`user_id`, `collection_id`),
  KEY `idx_collection_id` (`collection_id`),
  CONSTRAINT `fk_followed_collections_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_followed_collections_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 