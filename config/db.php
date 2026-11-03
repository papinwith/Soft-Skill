<?php
// Match MySQL's server clock (Thailand local time) so PHP-side date()/time() comparisons
// against DB timestamps or admin-entered local times (e.g. registration_close_at) line up.
date_default_timezone_set('Asia/Bangkok');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        // A form left open in an old/stale tab carries an outdated token once the session's
        // token has rotated -- send them somewhere useful instead of a bare 403 dump. If
        // they're still logged in, index.php would just bounce them onward and silently
        // drop the error, so land on their own dashboard instead.
        $msg = urlencode('หน้านี้หมดอายุหรือถูกเปิดค้างไว้นานเกินไป กรุณาลองใหม่อีกครั้ง');
        $landing = ['admin' => '/Soft-Skill/admin/dashboard.php', 'manager' => '/Soft-Skill/manager/dashboard.php', 'student' => '/Soft-Skill/student/profile.php'];
        $target = $landing[$_SESSION['role'] ?? ''] ?? '/Soft-Skill/index.php';
        header("Location: $target?error=$msg");
        exit;
    }
}

// DB connection settings. Set these as environment variables, or (more reliable on
// Windows/XAMPP, see config/mail.php) via a gitignored config/db.local.php returning
// an array of overrides, e.g. ['pass' => 'your-real-password'].
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db = getenv('DB_NAME') ?: 'soft_skill_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dbLocalFile = __DIR__ . '/db.local.php';
if (file_exists($dbLocalFile)) {
    $dbLocal = require $dbLocalFile;
    $host = $dbLocal['host'] ?? $host;
    $db = $dbLocal['db'] ?? $db;
    $user = $dbLocal['user'] ?? $user;
    $pass = $dbLocal['pass'] ?? $pass;
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     error_log("DB connection failed: " . $e->getMessage());
     die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ");
}

// Assessment used for the new-member baseline: the standard one (id 1) if it still has active
// questions, otherwise any assessment that does. Null when no active questions exist at all.
function baseline_assessment_id($pdo) {
    $id = $pdo->query("SELECT assessment_id FROM assessment_questions WHERE is_active = 1
        GROUP BY assessment_id ORDER BY (assessment_id = 1) DESC, assessment_id LIMIT 1")->fetchColumn();
    return $id ? (int)$id : null;
}

// Idle timeout: auto-logout a forgotten session on a shared/lab computer even though the
// cookie itself only expires on browser close.
const IDLE_TIMEOUT_SECONDS = 1800;

// Helper to check logged in user
function check_login($role = null) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /Soft-Skill/index.php');
        exit;
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > IDLE_TIMEOUT_SECONDS) {
        $_SESSION = [];
        session_destroy();
        header('Location: /Soft-Skill/index.php?error=' . urlencode('หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบใหม่'));
        exit;
    }
    $_SESSION['last_activity'] = time();

    if ($role !== null && $_SESSION['role'] !== $role) {
        // Session cookies are shared per-browser: if a different tab logged in as another
        // role in the meantime, a stale link/URL copied from this tab's old page would
        // otherwise land on a bare "Access Denied" dump (issu.md #33). Send them to
        // wherever their *current* session's role actually belongs instead.
        $landing = ['admin' => '/Soft-Skill/admin/dashboard.php', 'manager' => '/Soft-Skill/manager/dashboard.php', 'student' => '/Soft-Skill/student/profile.php'];
        header('Location: ' . ($landing[$_SESSION['role']] ?? '/Soft-Skill/index.php'));
        exit;
    }

    // Auto redirect student to baseline if not completed
    if ($_SESSION['role'] === 'student') {
        global $pdo;
        $current_url = $_SERVER['REQUEST_URI'];
        
        // Skip check if currently on baseline assessment, logout, or the per-tab
        // re-verification page (issu.md #49) -- reverify.php must always be reachable or a
        // student without a completed baseline could never get past it to begin with.
        $is_baseline_page = (strpos($current_url, 'assessment.php') !== false && strpos($current_url, 'type=baseline') !== false);
        $is_logout_page = (strpos($current_url, 'logout.php') !== false);
        $is_reverify_page = (strpos($current_url, 'reverify.php') !== false);

        if (!$is_baseline_page && !$is_logout_page && !$is_reverify_page && baseline_assessment_id($pdo) !== null) {
            $stmt = $pdo->prepare("SELECT id FROM student_assessments WHERE student_id = ? AND type = 'baseline' LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                header("Location: /Soft-Skill/student/assessment.php?type=baseline");
                exit;
            }
        }
    }
}
?>
