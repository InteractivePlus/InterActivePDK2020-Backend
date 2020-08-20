/*
Params:
USERNAME_MAXLEN
DISPLAYNAME_MAXLEN
SIGNATURE_MAXLEN
EMAIL_MAXLEN
*/
CREATE TABLE IF NOT EXISTS `user_infos`(
    `uid` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR({{ USERNAME_MAXLEN }}) NOT NULL,
    `display_name` VARCHAR({{ DISPLAYNAME_MAXLEN }}) NOT NULL,
    `signature` VARCHAR({{ SIGNATURE_MAXLEN }}),
    `password` CHAR(64),
    `locale` CHAR(6),
    `area` CHAR(2),
    `email` VARCHAR({{ EMAIL_MAXLEN }}),
    `phone_number` CHAR(15),
    `settings` TEXT,
    `email_verified` TINYINT(1),
    `phone_verified` TINYINT(1),
    `permission_override` TEXT,
    `group` VARCHAR({{ USERNAME_MAXLEN }}),
    `regtime` INT,
    `reg_client_addr` VARCHAR(40),
    `is_admin` TINYINT(1),
    `avatar` CHAR(32),
    `is_frozen` TINYINT(1),
    UNIQUE INDEX uidIndex (uid),
    UNIQUE INDEX usernameIndex (username),
    UNIQUE INDEX displayNameIndex (display_name),
    INDEX emailIndex (email),
    INDEX phoneNumberIndex (phone_number)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `usergroup_infos`(
    `groupid` VARCHAR({{ USERNAME_MAXLEN }}) NOT NULL,
    `parent_group_id` VARCHAR({{ USERNAME_MAXLEN }}),
    `display_name` VARCHAR({{DISPLAYNAME_MAXLEN}}),
    `description` VARCHAR({{SIGNATURE_MAXLEN}}),
    `regtime` INT,
    `permissions` TEXT,
    `avatar` CHAR(32),
    UNIQUE INDEX groupidIndex (groupid)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `logged_infos`(
    `token` CHAR(32) NOT NULL,
    `refresh_token` CHAR(32) NOT NULL,
    `uid` BIGINT UNSIGNED NOT NULL,
    `issue_time` INT,
    `expire_time` INT,
    `refresh_expire_time` INT,
    `renew_time` INT,
    `client_addr` VARCHAR(40),
    UNIQUE INDEX tokenIndex (token),
    UNIQUE INDEX refreshTokenIndex (refresh_token),
    INDEX uidIndex (uid)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `verification_codes`(
    `veri_code` CHAR(32) NOT NULL,
    `uid` BIGINT UNSIGNED NOT NULL,
    `action_id` INT,
    `action_param` TEXT,
    `sent_method` TINYINT,
    `issue_time` INT,
    `expire_time` INT,
    `used_stage` TINYINT,
    `trigger_client_ip` VARCHAR(40),
    UNIQUE INDEX veriCodeIndex (veri_code),
    INDEX uidIndex (uid)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `logs`(
    `actionID` int,
    `appuid` BIGINT UNSIGNED NOT NULL,
    `time` INT,
    `logLevel` INT,
    `message` TEXT,
    `success` TINYINT,
    `PDKExceptionCode` INT,
    `context` MEDIUMTEXT,
    `clientAddr` VARCHAR(40)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `captchas`(
    `phrase` VARCHAR(5),
    `gen_time` INT,
    `expire_time` INT,
    `clientAddr` VARCHAR(40),
    `actionID` INT,
    `appuid` BIGINT UNSIGNED NOT NULL
)ENGINE=InnoDB DEFAULT CHARSET=utf8;