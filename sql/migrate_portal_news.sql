-- Portal news / announcements for home page hero
CREATE TABLE IF NOT EXISTS `portal_news` (
  `news_id` int(11) NOT NULL AUTO_INCREMENT,
  `news_title` varchar(200) NOT NULL DEFAULT '',
  `news_message` text NOT NULL,
  `news_link_url` varchar(500) DEFAULT '',
  `news_link_label` varchar(100) DEFAULT 'Learn more',
  `news_status` tinyint(1) NOT NULL DEFAULT 1,
  `news_is_default` tinyint(1) NOT NULL DEFAULT 0,
  `news_date` date DEFAULT NULL,
  `news_updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`news_id`),
  KEY `idx_portal_news_date` (`news_date`),
  KEY `idx_portal_news_status` (`news_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `portal_news` (`news_title`, `news_message`, `news_link_url`, `news_link_label`, `news_status`, `news_is_default`, `news_date`)
SELECT
  'Welcome to Pickleball Portal',
  'Find community courts near you, check open slots, and book your game in advance. Browse partner venues across Bohol and reserve indoor or outdoor courts in minutes.',
  'signup.php',
  'Create Free Account',
  1,
  1,
  NULL
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `portal_news` WHERE `news_is_default` = 1 LIMIT 1);
