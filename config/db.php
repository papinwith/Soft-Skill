<?php
session_start();

$host = 'localhost';
$db = 'soft_skill_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}

// Helper to check logged in user
function check_login($role = null) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /soft skill/index.php');
        exit;
    }
    if ($role !== null && $_SESSION['role'] !== $role) {
        die("Access Denied: You do not have permission to view this page.");
    }

    // Auto redirect student to baseline if not completed
    if ($_SESSION['role'] === 'student') {
        global $pdo;
        $current_url = $_SERVER['REQUEST_URI'];
        
        // Skip check if currently on baseline assessment or logout page
        $is_baseline_page = (strpos($current_url, 'assessment.php') !== false && strpos($current_url, 'type=baseline') !== false);
        $is_logout_page = (strpos($current_url, 'logout.php') !== false);
        
        if (!$is_baseline_page && !$is_logout_page) {
            $stmt = $pdo->prepare("SELECT id FROM assessment_responses WHERE student_id = ? AND type = 'baseline' LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                header("Location: /soft skill/student/assessment.php?type=baseline");
                exit;
            }
        }
    }
}
?>
