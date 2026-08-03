-- Per-store pre-order: standing menu items + future service date
ALTER TABLE `items`
  ADD COLUMN IF NOT EXISTS `allow_preorder` tinyint(1) NOT NULL DEFAULT 0 AFTER `business_id`;

ALTER TABLE `business_page`
  ADD COLUMN IF NOT EXISTS `preorder_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `page_published`,
  ADD COLUMN IF NOT EXISTS `preorder_max_days` int(11) NOT NULL DEFAULT 14 AFTER `preorder_enabled`;
