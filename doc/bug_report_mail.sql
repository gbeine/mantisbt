ALTER TABLE `mantis_project_table`
ADD `pop3_host` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_user` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_pass` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_categories` ENUM( '0', '1' ) DEFAULT '0' NOT NULL ;

ALTER TABLE `mantis_project_category_table`
ADD `pop3_host` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_user` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_pass` VARCHAR( 255 ) DEFAULT NULL ;

INSERT INTO mantis_user_table (username, realname, email, password, date_created, last_visit, enabled, protected, access_level, login_count, lost_password_request_count, failed_login_count, cookie_string) VALUES ('Mail', 'Mail Reporter', 'nomail', 'a268462c3c679a9027658c5aa723f97c', '2004-12-25 15:41:49', '2004-12-25 15:41:49', 1, 0, 25, 0, 0, 0, CONCAT(MD5(RAND()),MD5(NOW())));
