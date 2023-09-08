-- schema 128 adding status to items and categories to experiments
RENAME TABLE `status` TO `experiments_status`;
CREATE TABLE `items_status` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `team` int UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `color` varchar(6) NOT NULL,
  `is_default` tinyint UNSIGNED DEFAULT NULL,
  `ordering` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `items` ADD `status` INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `experiments` CHANGE `category` `status` INT UNSIGNED NOT NULL;
ALTER TABLE `experiments` ADD `category` INT UNSIGNED NULL DEFAULT NULL;
CREATE TABLE `experiments_categories` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `team` int UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `color` varchar(6) NOT NULL,
  `is_default` tinyint UNSIGNED DEFAULT NULL,
  `ordering` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `experiments` CHANGE `status` `status` INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `items` CHANGE `category` `category` INT UNSIGNED NULL DEFAULT NULL;
UPDATE config SET conf_value = 128 WHERE conf_name = 'schema';
