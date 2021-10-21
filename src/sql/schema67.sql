-- Schema 67
START TRANSACTION;
    INSERT INTO config (conf_name, conf_value) VALUES ('ts_authority', 'dfn');
    ALTER TABLE `teams` ADD `override_tsa` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0';
    ALTER TABLE `teams` ADD `ts_authority` VARCHAR(255) NOT NULL DEFAULT 'dfn';
    UPDATE config SET conf_value = 67 WHERE conf_name = 'schema';
COMMIT;
