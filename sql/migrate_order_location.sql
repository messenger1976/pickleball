-- Order location fields + geo lookup tables
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `orders_country` varchar(100) DEFAULT NULL AFTER `orders_delivery_address`,
  ADD COLUMN IF NOT EXISTS `orders_province` varchar(100) DEFAULT NULL AFTER `orders_country`,
  ADD COLUMN IF NOT EXISTS `orders_town` varchar(150) DEFAULT NULL AFTER `orders_province`;

CREATE TABLE IF NOT EXISTS `geo_countries` (
  `country_id` int(11) NOT NULL AUTO_INCREMENT,
  `country_name` varchar(100) NOT NULL,
  `country_code` varchar(5) DEFAULT NULL,
  PRIMARY KEY (`country_id`),
  UNIQUE KEY `country_name` (`country_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_provinces` (
  `province_id` int(11) NOT NULL AUTO_INCREMENT,
  `country_id` int(11) NOT NULL,
  `province_name` varchar(100) NOT NULL,
  PRIMARY KEY (`province_id`),
  KEY `country_id` (`country_id`),
  UNIQUE KEY `country_province` (`country_id`, `province_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_towns` (
  `town_id` int(11) NOT NULL AUTO_INCREMENT,
  `province_id` int(11) NOT NULL,
  `town_name` varchar(150) NOT NULL,
  PRIMARY KEY (`town_id`),
  KEY `province_id` (`province_id`),
  UNIQUE KEY `province_town` (`province_id`, `town_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
