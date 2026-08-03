-- Timestamp when an order reaches Delivered or Cancelled
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `orders_completed_at` datetime DEFAULT NULL AFTER `orders_status`;
