/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `admin_conversation_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_conversation_reads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `last_read_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_conversation_reads_admin_id_conversation_id_unique` (`admin_id`,`conversation_id`),
  KEY `admin_conversation_reads_conversation_id_foreign` (`conversation_id`),
  KEY `admin_conversation_reads_admin_id_last_read_at_index` (`admin_id`,`last_read_at`),
  CONSTRAINT `admin_conversation_reads_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_conversation_reads_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','super_admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('group_chat','member_chat') COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` bigint unsigned NOT NULL,
  `participant1_id` bigint unsigned DEFAULT NULL,
  `participant2_id` bigint unsigned DEFAULT NULL,
  `room_token` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_rooms_room_token_unique` (`room_token`),
  UNIQUE KEY `unique_member_chat` (`group_id`,`participant1_id`,`participant2_id`),
  KEY `chat_rooms_participant2_id_foreign` (`participant2_id`),
  KEY `chat_rooms_type_index` (`type`),
  KEY `chat_rooms_group_id_index` (`group_id`),
  KEY `chat_rooms_participant1_id_participant2_id_index` (`participant1_id`,`participant2_id`),
  KEY `chat_rooms_room_token_index` (`room_token`),
  KEY `chat_rooms_created_at_index` (`created_at`),
  CONSTRAINT `chat_rooms_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`),
  CONSTRAINT `chat_rooms_participant1_id_foreign` FOREIGN KEY (`participant1_id`) REFERENCES `users` (`id`),
  CONSTRAINT `chat_rooms_participant2_id_foreign` FOREIGN KEY (`participant2_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('direct','group','group_member') COLLATE utf8mb4_unicode_ci DEFAULT 'direct',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `max_members` int unsigned DEFAULT '50',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT '管理者による削除日時',
  `deleted_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '削除理由',
  `deleted_by` bigint unsigned DEFAULT NULL COMMENT '削除を実行した管理者ID',
  `owner_user_id` bigint unsigned DEFAULT NULL,
  `qr_code_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_conversation_id` bigint unsigned DEFAULT NULL,
  `chat_styles` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversations_room_token_unique` (`room_token`),
  UNIQUE KEY `conversations_qr_code_token_unique` (`qr_code_token`),
  KEY `conversations_deleted_by_foreign` (`deleted_by`),
  KEY `conversations_owner_user_id_index` (`owner_user_id`),
  KEY `conversations_qr_code_token_index` (`qr_code_token`),
  KEY `conversations_group_conversation_id_foreign` (`group_conversation_id`),
  CONSTRAINT `conversations_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_group_conversation_id_foreign` FOREIGN KEY (`group_conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversations_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `external_api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `external_api_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `usage_count` int unsigned NOT NULL DEFAULT '0',
  `last_used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `external_api_tokens_token_unique` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `friendships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `friendships` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `friend_id` bigint unsigned NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT '友達関係の削除日時',
  `deleted_reason` text COLLATE utf8mb4_unicode_ci COMMENT '削除理由',
  `deleted_by` bigint unsigned DEFAULT NULL COMMENT '削除を実行した管理者ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `friendships_user_id_friend_id_unique` (`user_id`,`friend_id`),
  KEY `friendships_friend_id_foreign` (`friend_id`),
  KEY `friendships_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `friendships_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `friendships_friend_id_foreign` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `friendships_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `max_members` int NOT NULL DEFAULT '50',
  `chat_styles` json NOT NULL,
  `owner_user_id` bigint unsigned NOT NULL,
  `qr_code_token` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `groups_qr_code_token_unique` (`qr_code_token`),
  KEY `groups_owner_user_id_index` (`owner_user_id`),
  KEY `groups_qr_code_token_index` (`qr_code_token`),
  KEY `groups_created_at_index` (`created_at`),
  CONSTRAINT `groups_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `chat_room_id` bigint unsigned DEFAULT NULL,
  `sender_id` bigint unsigned DEFAULT NULL,
  `admin_sender_id` bigint unsigned DEFAULT NULL,
  `content_type` enum('text') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `text_content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `admin_deleted_at` timestamp NULL DEFAULT NULL COMMENT '管理者による削除日時',
  `admin_deleted_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '管理者による削除理由',
  `admin_deleted_by` bigint unsigned DEFAULT NULL COMMENT '削除を実行した管理者ID',
  PRIMARY KEY (`id`),
  KEY `messages_conversation_id_foreign` (`conversation_id`),
  KEY `messages_admin_deleted_by_foreign` (`admin_deleted_by`),
  KEY `messages_sender_id_foreign` (`sender_id`),
  KEY `messages_admin_sender_id_foreign` (`admin_sender_id`),
  KEY `messages_chat_room_id_index` (`chat_room_id`),
  CONSTRAINT `messages_admin_deleted_by_foreign` FOREIGN KEY (`admin_deleted_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_admin_sender_id_foreign` FOREIGN KEY (`admin_sender_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_chat_room_id_foreign` FOREIGN KEY (`chat_room_id`) REFERENCES `chat_rooms` (`id`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `operation_logs_category_index` (`category`),
  KEY `operation_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `chat_room_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_read_message_id` bigint unsigned DEFAULT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `participants_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  KEY `participants_user_id_foreign` (`user_id`),
  KEY `participants_last_read_message_id_foreign` (`last_read_message_id`),
  KEY `participants_chat_room_id_index` (`chat_room_id`),
  CONSTRAINT `participants_chat_room_id_foreign` FOREIGN KEY (`chat_room_id`) REFERENCES `chat_rooms` (`id`),
  CONSTRAINT `participants_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participants_last_read_message_id_foreign` FOREIGN KEY (`last_read_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscribable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscribable_id` bigint unsigned NOT NULL,
  `endpoint` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_encoding` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
  KEY `push_subscriptions_subscribable_type_subscribable_id_index` (`subscribable_type`,`subscribable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `stripe_subscription_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_customer_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan` enum('standard','premium') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_period_end` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriptions_stripe_subscription_id_unique` (`stripe_subscription_id`),
  KEY `subscriptions_user_id_index` (`user_id`),
  KEY `subscriptions_stripe_subscription_id_index` (`stripe_subscription_id`),
  KEY `subscriptions_user_id_status_index` (`user_id`,`status`),
  KEY `subscriptions_stripe_customer_id_index` (`stripe_customer_id`),
  CONSTRAINT `subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `friend_id` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan` enum('free','standard','premium') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `subscription_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_change_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verification_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT '管理者による削除日時',
  `deleted_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '削除理由',
  `deleted_by` bigint unsigned DEFAULT NULL COMMENT '削除を実行した管理者ID',
  `is_banned` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'バン状態（同じメールアドレスでの再登録不可）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_friend_id_unique` (`friend_id`),
  KEY `users_friend_id_index` (`friend_id`),
  KEY `users_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `users_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_01_20_000000_create_admins_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_02_03_072718_add_verification_columns_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_02_03_080318_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_02_08_153206_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_05_12_140611_add_unique_friend_id_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_05_12_141946_create_friendships_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_05_16_114847_create_conversations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_05_16_114901_create_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_05_16_114916_create_participants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_05_20_154438_add_email_change_fields_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_05_20_181539_add_room_token_to_conversations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_05_21_181316_create_push_subscriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_05_27_183432_create_access_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_01_22_000000_add_soft_delete_columns_to_users_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_01_22_000001_add_soft_delete_columns_to_conversations_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_01_22_000002_add_admin_delete_columns_to_messages_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_01_23_000000_add_soft_delete_to_friendships_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_05_29_050000_add_support_type_to_conversations_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_05_29_060000_make_sender_id_nullable_in_messages_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_05_29_164532_add_admin_sender_to_messages_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_05_29_173305_create_admin_conversation_reads_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_01_23_100000_add_google_auth_columns_to_users_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_05_30_100000_create_sessions_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_06_03_100001_add_soft_delete_columns_to_conversations_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_06_03_100002_add_admin_delete_columns_to_messages_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_06_03_100003_add_soft_delete_columns_to_users_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_06_03_100004_add_soft_delete_to_friendships_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_06_03_100005_add_google_auth_columns_to_users_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_06_03_100006_add_verification_columns_to_users_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_06_03_100007_add_unique_friend_id_to_users_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_06_03_100008_add_email_change_fields_to_users_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_06_03_100009_add_room_token_to_conversations_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_06_03_100010_add_support_type_to_conversations_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_06_03_100011_add_admin_sender_to_messages_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_06_03_100012_create_operation_logs_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_07_01_000001_create_groups_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_07_01_000002_create_group_members_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_07_01_000003_create_subscriptions_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_07_01_000004_create_group_messages_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_07_01_000005_add_subscription_fields_to_users_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_06_07_154244_create_external_api_tokens_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_06_08_000000_add_usage_columns_to_external_api_tokens_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_06_08_202615_add_group_fields_to_conversations_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_06_08_225924_add_group_conversation_id_to_conversations_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_06_08_233040_add_group_member_to_conversations_type_enum',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_06_09_124652_add_chat_style_to_conversations_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_06_09_160621_add_chat_styles_to_conversations_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_06_09_165406_optimize_messages_table_structure',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_06_09_165724_remove_chat_style_column_from_conversations_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_06_09_171459_create_groups_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_06_09_171515_create_chat_rooms_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_06_09_171600_update_existing_tables_for_phase2',22);
