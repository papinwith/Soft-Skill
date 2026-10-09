-- Migration 2: faculties/departments, activity details, question ordering, rejected status, activity target skills

CREATE TABLE IF NOT EXISTS `faculties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `faculty_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_name` (`faculty_id`,`name`),
  FOREIGN KEY (`faculty_id`) REFERENCES `faculties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `users` ADD COLUMN `faculty_id` int(11) NULL;
ALTER TABLE `users` ADD COLUMN `department_id` int(11) NULL;
ALTER TABLE `users` ADD COLUMN `year_level` tinyint NULL;
ALTER TABLE `users` ADD COLUMN `phone` varchar(20) NULL;
ALTER TABLE `users` ADD CONSTRAINT `users_faculty_fk` FOREIGN KEY (`faculty_id`) REFERENCES `faculties`(`id`) ON DELETE SET NULL;
ALTER TABLE `users` ADD CONSTRAINT `users_department_fk` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL;

ALTER TABLE `activities` ADD COLUMN `location` varchar(200) NULL;
ALTER TABLE `activities` ADD COLUMN `capacity` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `activities` ADD COLUMN `status` enum('open','closed') NOT NULL DEFAULT 'open';
ALTER TABLE `activities` ADD COLUMN `assessment_id` int(11) NULL;
ALTER TABLE `activities` ADD CONSTRAINT `activities_assessment_fk` FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `activity_skills` (
  `activity_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  PRIMARY KEY (`activity_id`,`skill_id`),
  FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `soft_skills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `activity_participants` MODIFY `status` enum('applied','approved','attended','rejected') NOT NULL DEFAULT 'applied';

ALTER TABLE `assessment_questions` ADD COLUMN `sort_order` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `assessment_questions` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1;
UPDATE `assessment_questions` SET `sort_order` = `id` WHERE `sort_order` = 0;

INSERT IGNORE INTO `faculties` (`id`, `name`) VALUES (1, 'คณะวิทยาศาสตร์และเทคโนโลยี'), (2, 'คณะวิทยาการจัดการ');
INSERT IGNORE INTO `departments` (`faculty_id`, `name`) VALUES
(1, 'เทคโนโลยีสารสนเทศ'), (1, 'วิทยาการคอมพิวเตอร์'), (2, 'คอมพิวเตอร์ธุรกิจดิจิทัล'), (2, 'การตลาด');
