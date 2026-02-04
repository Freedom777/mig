-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Фев 04 2026 г., 14:26
-- Версия сервера: 8.0.44-0ubuntu0.24.04.1
-- Версия PHP: 8.5.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- База данных: `mig`
--

-- --------------------------------------------------------

--
-- Структура таблицы `cache`
--

CREATE TABLE `cache` (
                         `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
                         `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `cache_locks`
--

CREATE TABLE `cache_locks` (
                               `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                               `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                               `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `faces`
--

CREATE TABLE `faces` (
                         `id` bigint UNSIGNED NOT NULL,
                         `parent_id` bigint UNSIGNED DEFAULT NULL,
                         `image_id` bigint UNSIGNED DEFAULT NULL,
                         `face_index` tinyint UNSIGNED NOT NULL,
                         `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                         `encoding` json DEFAULT NULL,
                         `status` enum('process','unknown','not_face','ok') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'process',
                         `created_at` timestamp NULL DEFAULT NULL,
                         `updated_at` timestamp NULL DEFAULT NULL,
                         `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `failed_jobs`
--

CREATE TABLE `failed_jobs` (
                               `id` bigint UNSIGNED NOT NULL,
                               `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                               `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
                               `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
                               `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                               `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                               `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `images`
--

CREATE TABLE `images` (
                          `id` bigint UNSIGNED NOT NULL,
                          `parent_id` bigint UNSIGNED DEFAULT NULL,
                          `image_geolocation_point_id` bigint UNSIGNED DEFAULT NULL,
                          `disk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                          `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                          `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                          `debug_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                          `width` int DEFAULT NULL,
                          `height` int DEFAULT NULL,
                          `size` int DEFAULT NULL,
                          `hash` binary(16) DEFAULT NULL,
                          `phash` binary(8) DEFAULT NULL,
                          `created_at_file` datetime DEFAULT NULL,
                          `updated_at_file` datetime DEFAULT NULL,
                          `metadata` json DEFAULT NULL,
                          `faces_checked` tinyint(1) NOT NULL DEFAULT '0',
                          `thumbnail_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                          `thumbnail_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                          `thumbnail_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                          `thumbnail_width` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                          `thumbnail_height` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                          `created_at` timestamp NULL DEFAULT NULL,
                          `updated_at` timestamp NULL DEFAULT NULL,
                          `status` enum('process','not_photo','recheck','ok') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'process',
                          `last_error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `image_geolocation_addresses`
--

CREATE TABLE `image_geolocation_addresses` (
                                               `id` bigint UNSIGNED NOT NULL,
                                               `osm_id` bigint NOT NULL,
                                               `osm_area` polygon
) ;

-- --------------------------------------------------------

--
-- Структура таблицы `image_geolocation_points`
--

CREATE TABLE `image_geolocation_points` (
                                            `id` bigint UNSIGNED NOT NULL,
                                            `image_geolocation_address_id` bigint UNSIGNED DEFAULT NULL,
                                            `coordinates` point NOT NULL
) ;

-- --------------------------------------------------------

--
-- Структура таблицы `jobs`
--

CREATE TABLE `jobs` (
                        `id` bigint UNSIGNED NOT NULL,
                        `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                        `attempts` tinyint UNSIGNED NOT NULL,
                        `reserved_at` int UNSIGNED DEFAULT NULL,
                        `available_at` int UNSIGNED NOT NULL,
                        `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `job_batches`
--

CREATE TABLE `job_batches` (
                               `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                               `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                               `total_jobs` int NOT NULL,
                               `pending_jobs` int NOT NULL,
                               `failed_jobs` int NOT NULL,
                               `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                               `options` mediumtext COLLATE utf8mb4_unicode_ci,
                               `cancelled_at` int DEFAULT NULL,
                               `created_at` int NOT NULL,
                               `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `migrations`
--

CREATE TABLE `migrations` (
                              `id` int UNSIGNED NOT NULL,
                              `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                              `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
                                         `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                                         `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                                         `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
                                          `id` bigint UNSIGNED NOT NULL,
                                          `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                                          `tokenable_id` bigint UNSIGNED NOT NULL,
                                          `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                                          `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
                                          `abilities` text COLLATE utf8mb4_unicode_ci,
                                          `last_used_at` timestamp NULL DEFAULT NULL,
                                          `expires_at` timestamp NULL DEFAULT NULL,
                                          `created_at` timestamp NULL DEFAULT NULL,
                                          `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `queues`
--

CREATE TABLE `queues` (
                          `id` bigint UNSIGNED NOT NULL,
                          `queue_key` binary(16) NOT NULL,
                          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `sessions`
--

CREATE TABLE `sessions` (
                            `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                            `user_id` bigint UNSIGNED DEFAULT NULL,
                            `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `user_agent` text COLLATE utf8mb4_unicode_ci,
                            `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                            `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
                         `id` bigint UNSIGNED NOT NULL,
                         `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `email_verified_at` timestamp NULL DEFAULT NULL,
                         `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
                         `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
                         `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
                         `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                         `created_at` timestamp NULL DEFAULT NULL,
                         `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `cache`
--
ALTER TABLE `cache`
    ADD PRIMARY KEY (`key`);

--
-- Индексы таблицы `cache_locks`
--
ALTER TABLE `cache_locks`
    ADD PRIMARY KEY (`key`);

--
-- Индексы таблицы `faces`
--
ALTER TABLE `faces`
    ADD PRIMARY KEY (`id`),
  ADD KEY `faces_image_id_foreign` (`image_id`);

--
-- Индексы таблицы `failed_jobs`
--
ALTER TABLE `failed_jobs`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Индексы таблицы `images`
--
ALTER TABLE `images`
    ADD PRIMARY KEY (`id`),
  ADD KEY `disk_path_filename_index` (`disk`,`path`,`filename`),
  ADD KEY `faces_checked_index` (`faces_checked`),
  ADD KEY `images_image_geolocation_point_id_foreign` (`image_geolocation_point_id`),
  ADD KEY `hash_index` (`hash`),
  ADD KEY `phash` (`phash`);

--
-- Индексы таблицы `jobs`
--
ALTER TABLE `jobs`
    ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Индексы таблицы `job_batches`
--
ALTER TABLE `job_batches`
    ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `migrations`
--
ALTER TABLE `migrations`
    ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
    ADD PRIMARY KEY (`email`);

--
-- Индексы таблицы `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Индексы таблицы `queues`
--
ALTER TABLE `queues`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `queues_queue_key_unique` (`queue_key`);

--
-- Индексы таблицы `sessions`
--
ALTER TABLE `sessions`
    ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `faces`
--
ALTER TABLE `faces`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `failed_jobs`
--
ALTER TABLE `failed_jobs`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `images`
--
ALTER TABLE `images`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `image_geolocation_addresses`
--
ALTER TABLE `image_geolocation_addresses`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `image_geolocation_points`
--
ALTER TABLE `image_geolocation_points`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `jobs`
--
ALTER TABLE `jobs`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `migrations`
--
ALTER TABLE `migrations`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `queues`
--
ALTER TABLE `queues`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `faces`
--
ALTER TABLE `faces`
    ADD CONSTRAINT `faces_image_id_foreign` FOREIGN KEY (`image_id`) REFERENCES `images` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `images`
--
ALTER TABLE `images`
    ADD CONSTRAINT `images_image_geolocation_point_id_foreign` FOREIGN KEY (`image_geolocation_point_id`) REFERENCES `image_geolocation_points` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT;
COMMIT;
