-- Phase 1: Pickleball marketplace core (courts, availability, bookings, reviews)

ALTER TABLE `business_page`
  ADD COLUMN IF NOT EXISTS `page_amenities` text DEFAULT NULL AFTER `page_specialties`,
  ADD COLUMN IF NOT EXISTS `auto_confirm` tinyint(1) NOT NULL DEFAULT 1 AFTER `preorder_max_days`,
  ADD COLUMN IF NOT EXISTS `booking_max_days` int(11) NOT NULL DEFAULT 14 AFTER `auto_confirm`,
  ADD COLUMN IF NOT EXISTS `cancellation_note` varchar(500) DEFAULT NULL AFTER `booking_max_days`;

CREATE TABLE IF NOT EXISTS `courts` (
  `court_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `court_name` varchar(150) NOT NULL,
  `court_type` varchar(30) NOT NULL DEFAULT 'outdoor',
  `court_surface` varchar(80) DEFAULT 'hard',
  `court_capacity` int(11) NOT NULL DEFAULT 4,
  `court_photo` text DEFAULT NULL,
  `price_per_slot` decimal(10,2) NOT NULL DEFAULT 0.00,
  `slot_length_minutes` int(11) NOT NULL DEFAULT 60,
  `buffer_minutes` int(11) NOT NULL DEFAULT 0,
  `court_status` tinyint(1) NOT NULL DEFAULT 1,
  `court_created` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`court_id`),
  KEY `idx_courts_business` (`business_id`),
  KEY `idx_courts_status` (`court_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `court_availability_rules` (
  `rule_id` int(11) NOT NULL AUTO_INCREMENT,
  `court_id` int(11) NOT NULL,
  `weekday` tinyint(1) NOT NULL COMMENT '0=Sun .. 6=Sat',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `price_override` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`rule_id`),
  KEY `idx_rules_court_day` (`court_id`, `weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `availability_exceptions` (
  `exception_id` int(11) NOT NULL AUTO_INCREMENT,
  `court_id` int(11) NOT NULL,
  `exception_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 1,
  `override_price` decimal(10,2) DEFAULT NULL,
  `exception_note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`exception_id`),
  KEY `idx_exc_court_date` (`court_id`, `exception_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `court_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `customers_id` int(11) NOT NULL DEFAULT 0,
  `booking_receipt_no` varchar(30) DEFAULT NULL,
  `customer_fname` varchar(100) DEFAULT NULL,
  `customer_lname` varchar(100) DEFAULT NULL,
  `customer_email` varchar(200) DEFAULT NULL,
  `customer_contact` varchar(50) DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `booking_status` varchar(30) NOT NULL DEFAULT 'pending',
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_type` varchar(40) NOT NULL DEFAULT 'pay_at_venue',
  `booking_remarks` text DEFAULT NULL,
  `booking_created` datetime DEFAULT CURRENT_TIMESTAMP,
  `booking_updated` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`booking_id`),
  KEY `idx_bookings_court_time` (`court_id`, `start_datetime`, `end_datetime`),
  KEY `idx_bookings_business` (`business_id`),
  KEY `idx_bookings_customer` (`customers_id`),
  KEY `idx_bookings_status` (`booking_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `customers_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `rating` tinyint(1) NOT NULL DEFAULT 5,
  `review_text` text DEFAULT NULL,
  `review_created` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `idx_reviews_business` (`business_id`),
  KEY `idx_reviews_customer` (`customers_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
