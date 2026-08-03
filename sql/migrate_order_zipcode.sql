-- Zipcode support for towns and orders
ALTER TABLE `geo_towns`
  ADD COLUMN IF NOT EXISTS `zipcode` varchar(20) DEFAULT NULL AFTER `town_name`;

ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `orders_zipcode` varchar(20) DEFAULT NULL AFTER `orders_town`;
