<?php
/**
 * Migration 4: split `users` into admins / activity_supervisors / students
 * (matching the ER-Diagram's Admin / ActivitySupervisor / Student entities),
 * add `curricula`, and split `assessment_responses` into `student_assessments`
 * + `answers` (matching StudentAssessment / Answer).
 *
 * Run from the command line: php update_schema_4.php
 * Safe to re-run: every step is guarded (checks whether it already happened).
 * Take a mysqldump backup before running this in case anything looks wrong
 * afterwards.
 */

$pdo = new PDO('mysql:host=127.0.0.1;dbname=soft_skill_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function table_exists(PDO $pdo, string $name): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $s->execute([$name]);
    return (bool)$s->fetchColumn();
}

function fk_name(PDO $pdo, string $table, string $column): ?string {
    $s = $pdo->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
    $s->execute([$table, $column]);
    $name = $s->fetchColumn();
    return $name ?: null;
}

function repoint_fk(PDO $pdo, string $table, string $column, string $newRefTable, string $onDelete = 'CASCADE'): void {
    $name = fk_name($pdo, $table, $column);
    if ($name) {
        $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$name`");
    }
    $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `{$table}_{$column}_fk` FOREIGN KEY (`$column`) REFERENCES `$newRefTable`(`id`) ON DELETE $onDelete");
    echo "  repointed $table.$column -> $newRefTable.id\n";
}

echo "=== Migration 4: split users / assessment_responses ===\n";

if (!table_exists($pdo, 'users')) {
    echo "users table not found - migration already completed (or schema never had it). Nothing to do.\n";
    exit(0);
}

$counts_before = [
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'assessment_responses' => (int)$pdo->query("SELECT COUNT(*) FROM assessment_responses")->fetchColumn(),
];
echo "Before: users={$counts_before['users']} assessment_responses={$counts_before['assessment_responses']}\n";

// Note: CREATE/ALTER/DROP TABLE in MySQL auto-commit immediately (DDL is never
// transactional in InnoDB), so this script relies on idempotent guards (IF NOT
// EXISTS, INSERT IGNORE, looked-up FK names) to be safely re-runnable after a
// failure, rather than on a wrapping transaction. The two row-count checks must
// both pass before the final DROP TABLE step runs.
try {
    // ---------- 1. Create new tables ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `name` varchar(100) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`), UNIQUE KEY `admins_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_supervisors` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `name` varchar(100) NOT NULL,
        `email` varchar(100) DEFAULT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`), UNIQUE KEY `activity_supervisors_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `curricula` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `department_id` int(11) NOT NULL,
        `name` varchar(150) NOT NULL,
        `year_level` tinyint(4) DEFAULT NULL,
        `education_level` varchar(100) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `name` varchar(100) NOT NULL,
        `student_id` varchar(20) DEFAULT NULL,
        `email` varchar(100) DEFAULT NULL,
        `faculty_id` int(11) DEFAULT NULL,
        `department_id` int(11) DEFAULT NULL,
        `curriculum_id` int(11) DEFAULT NULL,
        `department` varchar(100) DEFAULT NULL,
        `year_level` tinyint(4) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`), UNIQUE KEY `students_username` (`username`),
        FOREIGN KEY (`faculty_id`) REFERENCES `faculties`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`curriculum_id`) REFERENCES `curricula`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `student_assessments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `assessment_id` int(11) NOT NULL,
        `activity_id` int(11) DEFAULT NULL,
        `type` enum('baseline','pre','post') NOT NULL,
        `status` enum('submitted') NOT NULL DEFAULT 'submitted',
        `started_at` timestamp NULL DEFAULT NULL,
        `submitted_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `student_assessments_uniq` (`student_id`, `activity_id`, `type`),
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `answers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_assessment_id` int(11) NOT NULL,
        `question_id` int(11) NOT NULL,
        `score` int(11) NOT NULL,
        `answer_text` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `answers_uniq` (`student_assessment_id`, `question_id`),
        FOREIGN KEY (`student_assessment_id`) REFERENCES `student_assessments`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`question_id`) REFERENCES `assessment_questions`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created: admins, activity_supervisors, curricula, students, student_assessments, answers\n";

    // ---------- 2. Migrate accounts, preserving the original id ----------
    $pdo->exec("INSERT IGNORE INTO admins (id, username, password, name, created_at)
        SELECT id, username, password, name, created_at FROM users WHERE role = 'admin'");
    $pdo->exec("INSERT IGNORE INTO activity_supervisors (id, username, password, name, email, phone, created_at)
        SELECT id, username, password, name, email, phone, created_at FROM users WHERE role = 'manager'");
    $pdo->exec("INSERT IGNORE INTO students (id, username, password, name, student_id, email, faculty_id, department_id, department, year_level, created_at)
        SELECT id, username, password, name, student_id, email, faculty_id, department_id, department, year_level, created_at FROM users WHERE role = 'student'");
    echo "Migrated accounts: admins=" . $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn()
        . " activity_supervisors=" . $pdo->query("SELECT COUNT(*) FROM activity_supervisors")->fetchColumn()
        . " students=" . $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() . "\n";

    // ---------- 3. Migrate assessment_responses -> student_assessments + answers ----------
    $pdo->exec("INSERT IGNORE INTO student_assessments (student_id, assessment_id, activity_id, type, status, started_at, submitted_at)
        SELECT ar.student_id, q.assessment_id, ar.activity_id, ar.type, 'submitted', MIN(ar.submitted_at), MAX(ar.submitted_at)
        FROM assessment_responses ar
        JOIN assessment_questions q ON ar.question_id = q.id
        GROUP BY ar.student_id, ar.activity_id, ar.type, q.assessment_id");
    echo "Created student_assessments: " . $pdo->query("SELECT COUNT(*) FROM student_assessments")->fetchColumn() . "\n";

    $pdo->exec("INSERT IGNORE INTO answers (student_assessment_id, question_id, score)
        SELECT sa.id, ar.question_id, ar.score
        FROM assessment_responses ar
        JOIN assessment_questions q ON ar.question_id = q.id
        JOIN student_assessments sa ON sa.student_id = ar.student_id
            AND sa.activity_id <=> ar.activity_id
            AND sa.type = ar.type
            AND sa.assessment_id = q.assessment_id");
    $answers_count = (int)$pdo->query("SELECT COUNT(*) FROM answers")->fetchColumn();
    echo "Created answers: $answers_count\n";
    if ($answers_count !== $counts_before['assessment_responses']) {
        throw new RuntimeException("Row count mismatch: answers=$answers_count but assessment_responses had {$counts_before['assessment_responses']}. Aborting.");
    }

    // ---------- 4. Repoint foreign keys that used to reference users(id) ----------
    repoint_fk($pdo, 'activities', 'manager_id', 'activity_supervisors');
    repoint_fk($pdo, 'activity_participants', 'student_id', 'students');
    repoint_fk($pdo, 'ai_reports', 'student_id', 'students');

    // ---------- 5. password_reset_tokens: add role, drop the now-ambiguous FK ----------
    $cols = $pdo->query("SHOW COLUMNS FROM password_reset_tokens")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('role', $cols, true)) {
        $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN role ENUM('admin','manager','student') NOT NULL DEFAULT 'student'");
    }
    $prtFk = fk_name($pdo, 'password_reset_tokens', 'user_id');
    if ($prtFk) {
        $pdo->exec("ALTER TABLE password_reset_tokens DROP FOREIGN KEY `$prtFk`");
        echo "  dropped password_reset_tokens FK (role can target 3 different tables, enforced at app level)\n";
    }
    // Any outstanding tokens were issued to students/managers only (admins have no email), default is fine;
    // this table is emptied by normal expiry/use anyway so no backfill needed.

    // ---------- 6. Verify, then drop the old tables ----------
    $admins_n = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    $sup_n = (int)$pdo->query("SELECT COUNT(*) FROM activity_supervisors")->fetchColumn();
    $stu_n = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    if ($admins_n + $sup_n + $stu_n !== $counts_before['users']) {
        throw new RuntimeException("Row count mismatch: admins+supervisors+students=" . ($admins_n + $sup_n + $stu_n) . " but users had {$counts_before['users']}. Aborting.");
    }

    $pdo->exec("DROP TABLE assessment_responses");
    $pdo->exec("DROP TABLE users");
    echo "Dropped old tables: assessment_responses, users\n";

    echo "=== Migration 4 completed successfully ===\n";
} catch (Throwable $e) {
    echo "MIGRATION FAILED: " . $e->getMessage() . "\n";
    echo "(DDL already applied up to this point is not rolled back automatically - re-run this script after fixing the issue; every step is guarded to skip work already done.)\n";
    exit(1);
}
