-- Customer profile location fields (match checkout)
ALTER TABLE `customers`
  ADD COLUMN IF NOT EXISTS `customers_country` varchar(100) DEFAULT NULL AFTER `customers_complete_address`,
  ADD COLUMN IF NOT EXISTS `customers_province` varchar(100) DEFAULT NULL AFTER `customers_country`,
  ADD COLUMN IF NOT EXISTS `customers_town` varchar(150) DEFAULT NULL AFTER `customers_province`,
  ADD COLUMN IF NOT EXISTS `customers_zipcode` varchar(20) DEFAULT NULL AFTER `customers_town`;
