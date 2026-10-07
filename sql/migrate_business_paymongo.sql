-- Per-business PayMongo setup
-- Each business account can connect its own PayMongo account so that court
-- bookings and memberships are collected into that business's own PayMongo
-- balance. When a business is not configured (or disabled), the platform
-- account (PB_PAYMONGO_* config) is used as the fallback.

ALTER TABLE `business_account`
  ADD COLUMN IF NOT EXISTS `business_paymongo_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `business_receipt_next`,
  ADD COLUMN IF NOT EXISTS `business_paymongo_public_key` varchar(200) DEFAULT NULL AFTER `business_paymongo_enabled`,
  ADD COLUMN IF NOT EXISTS `business_paymongo_secret_key` varchar(255) DEFAULT NULL AFTER `business_paymongo_public_key`,
  ADD COLUMN IF NOT EXISTS `business_paymongo_webhook_secret` varchar(255) DEFAULT NULL AFTER `business_paymongo_secret_key`,
  ADD COLUMN IF NOT EXISTS `business_paymongo_updated_at` datetime DEFAULT NULL AFTER `business_paymongo_webhook_secret`;
