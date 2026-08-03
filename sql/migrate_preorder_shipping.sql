-- Preferred shipping date/time for pre-orders
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `orders_shipping_date` date DEFAULT NULL AFTER `orders_shipping_type`,
  ADD COLUMN IF NOT EXISTS `orders_shipping_time` varchar(10) DEFAULT NULL AFTER `orders_shipping_date`;
