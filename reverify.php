<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
check_login();

// Only allow redirecting back into our own app (avoid an open-redirect via ?return=).
$return = $_GET['return'] ?? ($_POST['return'] ?? '/Soft-Skill/index.php');
if (!is_string($return) || !preg_match('#^/Soft-Skill/[A-Za-z0-9_./?=&%-]*$#', $return)) {
    $return = '/Soft-Skill/index.php';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $table = ['student' => 'students', 'manager' => 'activity_supervisors', 'admin' => 'admins'][$_SESSION['role']] ?? null;
    $row = null;
    if ($table) {
        $stmt = $pdo->prepare("SELECT password FROM `$table` WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
    }
    if ($row && password_verify($password, $row['password'])) {
        // Same fingerprint formula as includes/header.php, so the flag this tab stores here
        // matches what the next page load will expect for this exact session identity.
        $sessionIdentity = hash('sha256', session_id() . '|' . $_SESSION['user_id'] . '|' . $_SESSION['role']);
        ?>
        <!doctype html><html><body>
        <script>
            try { sessionStorage.setItem('ss_tab_verified', <?= json_encode($sessionIdentity) ?>); } catch (e) {}
            window.location.replace(<?= json_encode($return) ?>);
        </script>
        </body></html>
        <?php
        exit;
    }
    $error = 'รหัสผ่านไม่ถูกต้อง';
}

include 'includes/header.php';
?>
<div class="row justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0"><i class="bi bi-shield-lock"></i> ยืนยันตัวตนอีกครั้ง</h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small">เพื่อความปลอดภัย แท็บใหม่ของเบราว์เซอร์ต้องยืนยันรหัสผ่านอีกครั้งก่อนเข้าใช้งาน (บัญชี: <strong><?= htmlspecialchars($_SESSION['name']) ?></strong>)</p>
                <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัสผ่าน</label>
                        <input type="password" name="password" class="form-control" required autofocus autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">ยืนยันตัวตน</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
