-- Per-store receipt prefix + counter, and formatted receipt number on orders
ALTER TABLE `business_account`
  ADD COLUMN IF NOT EXISTS `business_receipt_prefix` varchar(10) DEFAULT NULL AFTER `business_subdomain`,
  ADD COLUMN IF NOT EXISTS `business_receipt_next` int(10) unsigned NOT NULL DEFAULT 1 AFTER `business_receipt_prefix`;

ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `orders_receipt_no` varchar(30) DEFAULT NULL AFTER `orders_id`;

-- Seed known store prefixes (only when empty)
UPDATE `business_account` SET `business_receipt_prefix` = 'EFH' WHERE `business_id` = 2 AND (`business_receipt_prefix` IS NULL OR `business_receipt_prefix` = '');
UPDATE `business_account` SET `business_receipt_prefix` = 'TGR' WHERE `business_id` = 3 AND (`business_receipt_prefix` IS NULL OR `business_receipt_prefix` = '');
UPDATE `business_account` SET `business_receipt_prefix` = 'TC' WHERE `business_id` = 4 AND (`business_receipt_prefix` IS NULL OR `business_receipt_prefix` = '');
UPDATE `business_account` SET `business_receipt_prefix` = 'TR' WHERE `business_id` = 5 AND (`business_receipt_prefix` IS NULL OR `business_receipt_prefix` = '');
UPDATE `business_account` SET `business_receipt_prefix` = 'MI' WHERE `business_id` = 6 AND (`business_receipt_prefix` IS NULL OR `business_receipt_prefix` = '');
