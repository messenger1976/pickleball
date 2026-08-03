-- Business users, roles, and CMS landing pages
ALTER TABLE `user`
  ADD COLUMN IF NOT EXISTS `user_role` varchar(30) NOT NULL DEFAULT 'business_admin' AFTER `user_fullname`,
  ADD COLUMN IF NOT EXISTS `user_email` varchar(200) DEFAULT NULL AFTER `user_role`,
  ADD COLUMN IF NOT EXISTS `user_status` tinyint(1) NOT NULL DEFAULT 1 AFTER `user_email`;

UPDATE `user` SET `user_role` = 'super_admin' WHERE `user_business_account` = 0;

CREATE TABLE IF NOT EXISTS `business_page` (
  `page_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `page_tagline` varchar(255) DEFAULT NULL,
  `page_about` text,
  `page_hours` varchar(255) DEFAULT NULL,
  `page_specialties` text,
  `page_hero_image` text,
  `page_banner_image` text,
  `page_theme_color` varchar(20) DEFAULT '#2d6a4f',
  `page_published` tinyint(1) NOT NULL DEFAULT 1,
  `page_updated` datetime DEFAULT NULL,
  PRIMARY KEY (`page_id`),
  UNIQUE KEY `business_id` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
