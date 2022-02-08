-- Schema 72
START TRANSACTION;
    ALTER TABLE `uploads` ADD `storage` int(10) NOT NULL DEFAULT 1;
    ALTER TABLE `uploads` ADD `filesize` int(10) UNSIGNED NULL DEFAULT NULL;
    INSERT INTO config (conf_name, conf_value) VALUES ('uploads_storage', '1');
    INSERT INTO config (conf_name, conf_value) VALUES ('s3_bucket_name', '');
    INSERT INTO config (conf_name, conf_value) VALUES ('s3_path_prefix', '');
    INSERT INTO config (conf_name, conf_value) VALUES ('s3_region', '');
    INSERT INTO config (conf_name, conf_value) VALUES ('s3_endpoint', '');
    UPDATE config SET conf_value = 72 WHERE conf_name = 'schema';
COMMIT;
