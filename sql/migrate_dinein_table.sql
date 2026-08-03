-- Dine-in table number for checkout orders
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `orders_dinein_table` varchar(20) DEFAULT NULL AFTER `orders_shipping_time`;
