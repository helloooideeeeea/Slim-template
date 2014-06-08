CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mail_address` text NOT NULL,
  `password` text NOT NULL,
  `nick_name` text NOT NULL,
  `user_thumbnail` text,
  `account_hash` text NOT NULL,
  `created_at` timestamp NOT NULL default '0000-00-00 00:00:00',
  `updated_at` timestamp NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `last_login_at` timestamp NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE INDEX `idx_account_hash` ON `users` (`account_hash`(13));

CREATE TABLE IF NOT EXISTS `comics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mail_address` text NOT NULL,
  `title` text NOT NULL,
  `comic_image` text NOT NULL,
  `comic_thumbnail` text NOT NULL,
  `recommend_counts` int(11) default 0,
  `created_at` timestamp NOT NULL default '0000-00-00 00:00:00',
  `updated_at` timestamp NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comics_id` int(11) NOT NULL,
  `users_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `recommend_counts` int(11) default 0,
  `created_at` timestamp NOT NULL default '0000-00-00 00:00:00',
  `updated_at` timestamp NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
