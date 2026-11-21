-- Personal Calendar - Habits Tracking Migration
USE personal_calendar;

CREATE TABLE IF NOT EXISTS `habits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency` enum('daily','weekly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
  `target_per_week` tinyint unsigned NOT NULL DEFAULT '7',
  `color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#c2410c',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_habits_user` (`user_id`,`is_archived`),
  CONSTRAINT `fk_habits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `habit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `habit_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `log_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_habit_date` (`habit_id`,`log_date`),
  KEY `idx_habit_logs_user_date` (`user_id`,`log_date`),
  CONSTRAINT `fk_habit_logs_habit` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_habit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
