-- Optional newsletter subscribers for footer subscribe form
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `subscriber_id` int(11) NOT NULL AUTO_INCREMENT,
  `subscriber_email` varchar(190) NOT NULL,
  `subscribed_at` datetime NOT NULL,
  `subscriber_status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`subscriber_id`),
  UNIQUE KEY `uq_newsletter_email` (`subscriber_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
