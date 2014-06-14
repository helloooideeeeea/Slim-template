INSERT INTO `users` (`mail_address`, `password`, `nick_name`,`user_thumbnail`, `account_hash`, `created_at`, `last_login_at`) VALUES
('hoge@gmail.com', 'hogehoge', 'hoge', 'https://encrypted-tbn1.gstatic.com/images?q=tbn:ANd9GcRX2Nmg6FiNtWbO_TDDIPIq9pLjfa3SHmHIVapaMyPoQiEDQtKC', '5393fc09a16f5', null, null);
INSERT INTO `users` (`mail_address`, `password`, `nick_name`, `user_thumbnail`, `account_hash`, `created_at`, `last_login_at`) VALUES
('been@gmail.com', 'hogehoge', 'been', 'http://trend-comment.com/wp-content/uploads/2013/08/275px-Ichiro_Suzuki_on_August_1_2012.jpg', '5393fc09a171b',null, null);
INSERT INTO `comics` (`mail_address`, `title`, `comic_image`, `comic_thumbnail`, `created_at`) VALUES 
('hoge@gmail.com', 'シドニアの騎士', 'http://livedoor.4.blogimg.jp/chihhylove/imgs/0/6/06749c96.jpg', 'http://img.ponparemall.net/imgmgr/09/00103509/item/0173/bk-4063107167.jpg', null);
INSERT INTO `comics` (`mail_address`, `title`, `comic_image`, `comic_thumbnail`, `created_at`) VALUES 
('been@gmail.com', '進撃の巨人', 'http://livedoor.4.blogimg.jp/chihhylove/imgs/0/6/06749c96.jpg', 'http://e-hayao1.c.blog.so-net.ne.jp/_images/blog/_470/e-hayao1/m_51tqRvTuEdL__SL500_AA300_.jpg?c=a4', null);
INSERT INTO `comments` (`comics_id`, `users_id`, `comment`, `created_at`) VALUES 
(1, 1, 'すごく楽しい！続編希望！', null);
INSERT INTO `comments` (`comics_id`, `users_id`, `comment`, `created_at`) VALUES 
(2, 2, 'すごく楽しい！続編希望！', null);
INSERT INTO `tokens` (`token_name`, `mail_address`, `password`, `nick_name`, `user_thumbnail`, `expire_at`, `created_at`) VALUES 
('23855aaf799f7cbeb93fae01a3778389', 'hogehoge@gmail.com', 'hogehoge', 'nick', null, '2014-06-15 10:00:00', null);

