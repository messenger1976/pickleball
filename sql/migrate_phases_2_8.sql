-- Phases 2-8: payments, maps, lessons, memberships, chat

ALTER TABLE `business_account`
  ADD COLUMN IF NOT EXISTS `business_lat` decimal(10,7) DEFAULT NULL AFTER `business_zipcode`,
  ADD COLUMN IF NOT EXISTS `business_lng` decimal(10,7) DEFAULT NULL AFTER `business_lat`;

ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `booking_type` varchar(20) NOT NULL DEFAULT 'court' AFTER `booking_id`,
  ADD COLUMN IF NOT EXISTS `lesson_id` int(11) DEFAULT NULL AFTER `court_id`,
  ADD COLUMN IF NOT EXISTS `coach_id` int(11) DEFAULT NULL AFTER `lesson_id`,
  ADD COLUMN IF NOT EXISTS `payment_id` int(11) DEFAULT NULL AFTER `payment_type`,
  ADD COLUMN IF NOT EXISTS `payment_status` varchar(30) NOT NULL DEFAULT 'unpaid' AFTER `payment_id`;

CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL DEFAULT 0,
  `membership_id` int(11) NOT NULL DEFAULT 0,
  `customers_id` int(11) NOT NULL DEFAULT 0,
  `business_id` int(11) NOT NULL DEFAULT 0,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'PHP',
  `provider` varchar(40) NOT NULL DEFAULT 'paymongo',
  `provider_txn_id` varchar(120) DEFAULT NULL,
  `checkout_url` text DEFAULT NULL,
  `payment_status` varchar(30) NOT NULL DEFAULT 'pending',
  `raw_payload` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `idx_pay_booking` (`booking_id`),
  KEY `idx_pay_provider` (`provider_txn_id`),
  KEY `idx_pay_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `coaches` (
  `coach_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `coach_name` varchar(150) NOT NULL,
  `coach_bio` text DEFAULT NULL,
  `coach_photo` text DEFAULT NULL,
  `coach_status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`coach_id`),
  KEY `idx_coach_biz` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lessons` (
  `lesson_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `coach_id` int(11) NOT NULL DEFAULT 0,
  `lesson_title` varchar(200) NOT NULL,
  `lesson_description` text DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `capacity` int(11) NOT NULL DEFAULT 4,
  `lesson_status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`lesson_id`),
  KEY `idx_lesson_biz` (`business_id`),
  KEY `idx_lesson_coach` (`coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lesson_availability_rules` (
  `rule_id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `weekday` tinyint(1) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  PRIMARY KEY (`rule_id`),
  KEY `idx_lesson_rules` (`lesson_id`, `weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `membership_plans` (
  `plan_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `plan_name` varchar(150) NOT NULL,
  `plan_description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_period` varchar(20) NOT NULL DEFAULT 'monthly',
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `plan_status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  KEY `idx_plan_biz` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `memberships` (
  `membership_id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `customers_id` int(11) NOT NULL,
  `membership_status` varchar(30) NOT NULL DEFAULT 'active',
  `current_period_start` date DEFAULT NULL,
  `current_period_end` date DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`membership_id`),
  KEY `idx_memb_customer` (`customers_id`),
  KEY `idx_memb_biz` (`business_id`),
  KEY `idx_memb_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_threads` (
  `thread_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `customers_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `thread_status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`thread_id`),
  KEY `idx_thread_biz` (`business_id`),
  KEY `idx_thread_customer` (`customers_id`),
  KEY `idx_thread_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` int(11) NOT NULL,
  `sender_role` varchar(30) NOT NULL,
  `sender_id` int(11) NOT NULL DEFAULT 0,
  `message_body` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_msg_thread` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Demo map pins (Tagbilaran area)
UPDATE business_account SET business_lat=9.6508000, business_lng=123.8534000 WHERE business_id=2 AND business_lat IS NULL;
UPDATE business_account SET business_lat=9.6491000, business_lng=123.8651000 WHERE business_id=3 AND business_lat IS NULL;
