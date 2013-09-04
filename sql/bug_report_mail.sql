ALTER TABLE `mantis_project_table`
ADD `pop3_host` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_user` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_pass` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_categories` ENUM( '0', '1' ) DEFAULT '0' NOT NULL ;

ALTER TABLE `mantis_project_category_table`
ADD `pop3_host` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_user` VARCHAR( 255 ) DEFAULT NULL ,
ADD `pop3_pass` VARCHAR( 255 ) DEFAULT NULL ;

