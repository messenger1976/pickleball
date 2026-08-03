-- Food category setup (global menu categories shared across restaurants)
CREATE TABLE IF NOT EXISTS `category` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_name` varchar(150) DEFAULT NULL,
  `cat_status` tinyint(1) NOT NULL DEFAULT 1,
  `cat_sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cat_id`),
  KEY `idx_category_status_sort` (`cat_status`, `cat_sort`, `cat_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add sort order if upgrading an older category table
ALTER TABLE `category`
  ADD COLUMN IF NOT EXISTS `cat_sort` int(11) NOT NULL DEFAULT 0 AFTER `cat_status`;

-- Seed defaults when empty
INSERT INTO `category` (`cat_name`, `cat_status`, `cat_sort`)
SELECT 'Main Dish', 1, 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `category` LIMIT 1);

INSERT INTO `category` (`cat_name`, `cat_status`, `cat_sort`)
SELECT 'Drinks', 1, 20 FROM DUAL
WHERE (SELECT COUNT(*) FROM `category`) = 1
  AND NOT EXISTS (SELECT 1 FROM `category` WHERE `cat_name` = 'Drinks' LIMIT 1);

-- Backfill sort for existing rows that still sit at 0
UPDATE `category` SET `cat_sort` = `cat_id` * 10 WHERE `cat_sort` = 0;
