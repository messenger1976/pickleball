-- Barangay cascading lists + customer/order columns

CREATE TABLE IF NOT EXISTS `geo_barangays` (
  `barangay_id` int(11) NOT NULL AUTO_INCREMENT,
  `town_id` int(11) NOT NULL,
  `barangay_name` varchar(150) NOT NULL,
  PRIMARY KEY (`barangay_id`),
  UNIQUE KEY `uq_town_barangay` (`town_id`, `barangay_name`),
  KEY `idx_barangay_town` (`town_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `customers`
  ADD COLUMN IF NOT EXISTS `customers_barangay` varchar(150) DEFAULT NULL AFTER `customers_town`;

ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `orders_barangay` varchar(150) DEFAULT NULL AFTER `orders_town`;
