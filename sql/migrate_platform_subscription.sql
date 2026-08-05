-- Platform partner Free Trial + Subscription billing

CREATE TABLE IF NOT EXISTS `portal_subscription_settings` (
  `settings_id` int(11) NOT NULL AUTO_INCREMENT,
  `trial_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `trial_days` int(11) NOT NULL DEFAULT 7,
  `monthly_fee` decimal(10,2) NOT NULL DEFAULT 1500.00,
  `yearly_discount_pct` decimal(5,2) NOT NULL DEFAULT 10.00,
  `paymongo_fee_percent` decimal(5,2) NOT NULL DEFAULT 2.90,
  `paymongo_fee_fixed` decimal(10,2) NOT NULL DEFAULT 15.00,
  `signup_blurb` varchar(500) NOT NULL DEFAULT 'Choose a free trial or subscribe to list your courts on Pickleball Portal.',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`settings_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `portal_subscription_settings`
  (`trial_enabled`, `trial_days`, `monthly_fee`, `yearly_discount_pct`, `paymongo_fee_percent`, `paymongo_fee_fixed`, `signup_blurb`)
SELECT 1, 7, 1500.00, 10.00, 2.90, 15.00,
  'Choose a free trial or subscribe to list your courts on Pickleball Portal.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `portal_subscription_settings` LIMIT 1);

CREATE TABLE IF NOT EXISTS `business_subscriptions` (
  `subscription_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `owner_user_id` int(11) NOT NULL DEFAULT 0,
  `signup_intent` varchar(20) NOT NULL DEFAULT 'trial',
  `status` varchar(30) NOT NULL DEFAULT 'pending_activation',
  `billing_cycle` varchar(20) DEFAULT NULL,
  `months_purchased` int(11) NOT NULL DEFAULT 0,
  `trial_ends_at` datetime DEFAULT NULL,
  `current_period_start` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `activation_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `amount_subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `last_payment_id` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`subscription_id`),
  KEY `idx_biz_sub_business` (`business_id`),
  KEY `idx_biz_sub_status` (`status`),
  KEY `idx_biz_sub_token` (`activation_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `payments`
  ADD COLUMN IF NOT EXISTS `platform_subscription_id` int(11) NOT NULL DEFAULT 0 AFTER `membership_id`;

ALTER TABLE `payments`
  ADD KEY IF NOT EXISTS `idx_pay_platform_sub` (`platform_subscription_id`);
