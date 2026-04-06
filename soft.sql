CREATE DATABASE IF NOT EXISTS soft_skill_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE soft_skill_db;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','student') NOT NULL DEFAULT 'student',
  `name` varchar(100) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `soft_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skill_name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text,
  `manager_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activity_participants` (
  `activity_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('applied','approved','attended') NOT NULL DEFAULT 'applied',
  `applied_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`activity_id`,`student_id`),
  FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `assessment_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `soft_skills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `assessment_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `type` enum('pre','post') NOT NULL,
  `question_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `submitted_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `assessment_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ai_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `type` enum('pre','post') NOT NULL,
  `analysis_text` text,
  `recommendation_text` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_key` (`student_id`,`activity_id`,`type`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data
INSERT INTO `users` (`username`, `password`, `role`, `name`) VALUES 
('admin', '$2y$10$aSXrqgUU7B6dItV0ABmTSOONOe.fdiuA.PPymB.348FDSEVWxT7Ze', 'admin', 'System Administrator'),
('manager', '$2y$10$aSXrqgUU7B6dItV0ABmTSOONOe.fdiuA.PPymB.348FDSEVWxT7Ze', 'manager', 'Manager'),
('student', '$2y$10$aSXrqgUU7B6dItV0ABmTSOONOe.fdiuA.PPymB.348FDSEVWxT7Ze', 'student', 'Student')
ON DUPLICATE KEY UPDATE `username`=`username`;

INSERT INTO `soft_skills` (`id`, `skill_name`, `description`) VALUES
(1, 'Communication', 'Ability to convey information effectively.')
ON DUPLICATE KEY UPDATE `skill_name`='Communication';
INSERT INTO `soft_skills` (`id`, `skill_name`, `description`) VALUES
(2, 'Teamwork', 'Ability to work well with others.')
ON DUPLICATE KEY UPDATE `skill_name`='Teamwork';
INSERT INTO `soft_skills` (`id`, `skill_name`, `description`) VALUES
(3, 'Problem Solving', 'Ability to find solutions to difficult or complex issues.')
ON DUPLICATE KEY UPDATE `skill_name`='Problem Solving';
INSERT INTO `soft_skills` (`id`, `skill_name`, `description`) VALUES
(4, 'Leadership', 'Ability to lead and guide others.')
ON DUPLICATE KEY UPDATE `skill_name`='Leadership';
INSERT INTO `soft_skills` (`id`, `skill_name`, `description`) VALUES
(5, 'Working under pressure', 'Ability to perform well and stay calm in high stress situations.')
ON DUPLICATE KEY UPDATE `skill_name`='Working under pressure';

INSERT INTO `assessments` (`id`, `title`, `description`) VALUES
(1, 'Standard Soft Skill Assessment', 'แบบประเมินทักษะ Soft Skill มาตรฐานที่ครอบคลุม 5 ทักษะหลัก')
ON DUPLICATE KEY UPDATE `id`=1;

INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(1, 1, 1, 'คุณสามารถสื่อสารความคิดเห็นของตนเองให้ผู้อื่นเข้าใจได้อย่างชัดเจน') ON DUPLICATE KEY UPDATE `id`=1;
INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(2, 1, 1, 'คุณรับฟังความคิดเห็นของผู้อื่นอย่างตั้งใจและไม่ขัดจังหวะ') ON DUPLICATE KEY UPDATE `id`=2;
INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(3, 1, 2, 'คุณสามารถปรับตัวและทำงานร่วมกับผู้ที่มีความคิดเห็นแตกต่างได้') ON DUPLICATE KEY UPDATE `id`=3;
INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(4, 1, 2, 'คุณมีส่วนร่วมในการช่วยเหลือเพื่อนร่วมทีมเมื่อพบอุปสรรค') ON DUPLICATE KEY UPDATE `id`=4;
INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(5, 1, 3, 'เมื่อเจอปัญหา คุณสามารถวิเคราะห์สาเหตุและหาแนวทางแก้ไขได้อย่างเป็นระบบ') ON DUPLICATE KEY UPDATE `id`=5;
INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(6, 1, 3, 'คุณมีความคิดสร้างสรรค์ในการแก้ปัญหา') ON DUPLICATE KEY UPDATE `id`=6;
INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(7, 1, 4, 'คุณสามารถเป็นผู้นำในการอภิปรายหรือดำเนินงานกลุ่มได้') ON DUPLICATE KEY UPDATE `id`=7;
INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(8, 1, 4, 'คุณสามารถกระตุ้นและสร้างแรงจูงใจให้ผู้อื่นทำงานได้') ON DUPLICATE KEY UPDATE `id`=8;
INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(9, 1, 5, 'คุณสามารถจัดการกับความรู้สึกเครียดเมื่อเผชิญกับเวลาที่จำกัดได้') ON DUPLICATE KEY UPDATE `id`=9;
INSERT INTO `assessment_questions` (`id`, `assessment_id`, `skill_id`, `question_text`) VALUES
(10, 1, 5, 'ผลงานของคุณยังคงมีคุณภาพดีแม้ในสถานการณ์ที่กดดัน') ON DUPLICATE KEY UPDATE `id`=10;
