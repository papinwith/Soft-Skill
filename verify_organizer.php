<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// A 6-digit OTP with no throttling is brute-forceable within its 15-minute validity window,
// so wrong guesses are counted and the OTP is locked out well before all 1,000,000 codes
// could plausibly be tried.
const OTP_MAX_ATTEMPTS = 5;

$username = trim($_GET['username'] ?? ($_POST['username'] ?? ''));
$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $pwError = password_strength_error($password);

    $stmt = $pdo->prepare("SELECT id, otp_code, otp_expires_at, otp_attempts FROM activity_supervisors WHERE username = ? AND status = 'pending' AND otp_code IS NOT NULL LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row) {
        $error = 'ไม่พบบัญชีนี้ หรือยืนยันไปแล้ว';
    } elseif (strtotime($row['otp_expires_at']) < time()) {
        $error = 'รหัส OTP หมดอายุแล้ว กรุณาติดต่อผู้ดูแลระบบให้สร้างบัญชีใหม่';
    } elseif ((int)$row['otp_attempts'] >= OTP_MAX_ATTEMPTS) {
        $pdo->prepare("UPDATE activity_supervisors SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?")->execute([$row['id']]);
        $error = 'กรอกรหัส OTP ผิดเกินจำนวนที่กำหนด กรุณาติดต่อผู้ดูแลระบบให้สร้างบัญชีใหม่';
    } elseif (!hash_equals($row['otp_code'], $otp)) {
        $pdo->prepare("UPDATE activity_supervisors SET otp_attempts = otp_attempts + 1 WHERE id = ?")->execute([$row['id']]);
        $error = 'รหัส OTP ไม่ถูกต้อง';
    } elseif ($pwError !== null) {
        $error = $pwError;
    } elseif ($password !== $confirm) {
        $error = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        $pdo->prepare("UPDATE activity_supervisors SET password = ?, status = 'active', otp_code = NULL, otp_expires_at = NULL WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        $done = true;
    }
}

include 'includes/header.php';
?>
<div class="row justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">ยืนยันบัญชีผู้ดูแลกิจกรรม</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($done): ?>
                    <div class="alert alert-success">ยืนยันบัญชีสำเร็จแล้ว เข้าสู่ระบบได้เลย</div>
                    <a href="index.php" class="btn btn-primary w-100">ไปหน้าเข้าสู่ระบบ</a>
                <?php else: ?>
                    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <p class="text-muted small">กรอกรหัส OTP ที่ได้รับทางอีเมล และตั้งรหัสผ่านของคุณ</p>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">รหัส OTP</label>
                            <input type="text" name="otp" class="form-control" maxlength="6" required autocomplete="one-time-code">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ตั้งรหัสผ่าน</label>
                            <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ยืนยันรหัสผ่าน</label>
                            <input type="password" name="password_confirm" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">ยืนยันบัญชี</button>
                    </form>
                <?php endif; ?>
                <div class="text-center mt-3">
                    <a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
