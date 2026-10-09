<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$error = '';
$message = '';
$resetComplete = false;
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = is_string($token) ? $token : '';
$tokenRecord = null;

// Tables that can own a password reset token (admins have no email, so they're excluded upstream)
const RESET_ROLE_TABLE = ['student' => 'students', 'manager' => 'activity_supervisors'];

$findToken = function ($plainToken, $forUpdate = false) use ($pdo) {
    if (!preg_match('/^[a-f0-9]{64}$/', $plainToken)) return null;
    $stmt = $pdo->prepare("SELECT user_id, role FROM password_reset_tokens
        WHERE token_hash = ? AND expires_at > NOW() LIMIT 1" . ($forUpdate ? " FOR UPDATE" : ""));
    $stmt->execute([hash('sha256', $plainToken)]);
    $row = $stmt->fetch();
    if ($row && !isset(RESET_ROLE_TABLE[$row['role']])) return null;
    return $row ?: null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'request') {
        $requestStart = microtime(true);
        $emailValue = $_POST['email'] ?? '';
        $email = is_string($emailValue) ? trim($emailValue) : '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Admins have no email field (matches the ER-Diagram), so only students/activity_supervisors are searched.
            $accounts = [];
            foreach (RESET_ROLE_TABLE as $role => $table) {
                $stmt = $pdo->prepare("SELECT id FROM `$table` WHERE email = ?");
                $stmt->execute([$email]);
                foreach ($stmt->fetchAll() as $row) $accounts[] = ['id' => $row['id'], 'role' => $role];
            }
            $user = count($accounts) === 1 ? $accounts[0] : null;
            if ($user) {
                $plainToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $plainToken);
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND role = ?")->execute([$user['id'], $user['role']]);
                $pdo->prepare("INSERT INTO password_reset_tokens (user_id, role, token_hash, expires_at)
                    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")->execute([$user['id'], $user['role'], $tokenHash]);
                $pdo->commit();

                $appUrl = getenv('APP_URL') ?: 'http://localhost/Soft-Skill';
                if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($appUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    error_log('Password reset email not sent: APP_URL must be an absolute HTTP(S) URL.');
                    $pdo->prepare("DELETE FROM password_reset_tokens WHERE token_hash = ?")->execute([$tokenHash]);
                } else {
                    $resetUrl = rtrim($appUrl, '/') . '/forgot_password.php?token=' . rawurlencode($plainToken);
                    $body = "มีคำขอตั้งรหัสผ่านใหม่สำหรับบัญชีของคุณ\n";
                    $body .= "เปิดลิงก์นี้เพื่อตั้งรหัสผ่านใหม่ (ลิงก์หมดอายุภายใน 1 ชั่วโมง):\n$resetUrl\n\n";
                    $body .= "หากคุณไม่ได้เป็นผู้ขอ สามารถละเว้นอีเมลฉบับนี้ได้";
                    if (!send_mail($email, 'Soft Skill password reset', $body)) {
                        $pdo->prepare("DELETE FROM password_reset_tokens WHERE token_hash = ?")->execute([$tokenHash]);
                    }
                }
            }
        }
        // Pad to a constant floor so response time doesn't leak whether the email matched an account.
        $elapsedUs = (int)((microtime(true) - $requestStart) * 1_000_000);
        $floorUs = 400_000;
        if ($elapsedUs < $floorUs) usleep($floorUs - $elapsedUs);
        $message = 'หากอีเมลนี้ตรงกับบัญชีที่ลงทะเบียนไว้ ระบบจะส่งลิงก์ตั้งรหัสผ่านไปให้ ลิงก์มีอายุ 1 ชั่วโมง';
    } elseif ($action === 'reset') {
        $tokenRecord = $findToken($token);
        $passwordValue = $_POST['password'] ?? '';
        $confirmValue = $_POST['password_confirm'] ?? '';
        $password = is_string($passwordValue) ? $passwordValue : '';
        $confirm = is_string($confirmValue) ? $confirmValue : '';
        if (!$tokenRecord) {
            $error = 'ลิงก์ตั้งรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่';
        } elseif ($error = password_strength_error($password)) {
            // $error already set
        } elseif ($password !== $confirm) {
            $error = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
        } else {
            $pdo->beginTransaction();
            $tokenRecord = $findToken($token, true);
            if (!$tokenRecord) {
                $pdo->rollBack();
                $error = 'ลิงก์ตั้งรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่';
            } else {
                $table = RESET_ROLE_TABLE[$tokenRecord['role']];
                $pdo->prepare("UPDATE `$table` SET password = ? WHERE id = ?")
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $tokenRecord['user_id']]);
                $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND role = ?")->execute([$tokenRecord['user_id'], $tokenRecord['role']]);
                $pdo->commit();
                $resetComplete = true;
            }
        }
    }
}

if (!$resetComplete && $token !== '') {
    $tokenRecord = $findToken($token);
    if (!$tokenRecord && $error === '') {
        $error = 'ลิงก์ตั้งรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่';
    }
}

include 'includes/header.php';
?>
<div class="row justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0"><?= $token !== '' ? 'ตั้งรหัสผ่านใหม่' : 'ลืมรหัสผ่าน (Forgot Password)' ?></h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($message !== ''): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
                <?php if ($resetComplete): ?>
                    <div class="alert alert-success">ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว</div>
                    <a href="index.php" class="btn btn-primary w-100">กลับไปเข้าสู่ระบบ</a>
                <?php elseif ($token !== '' && $tokenRecord): ?>
                    <p class="text-muted">กรอกรหัสผ่านใหม่เพื่อยืนยันการกู้คืนบัญชีผ่านอีเมล</p>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reset">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold">รหัสผ่านใหม่</label>
                            <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" name="password_confirm" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">บันทึกรหัสผ่านใหม่</button>
                    </form>
                <?php elseif ($token !== ''): ?>
                    <a href="forgot_password.php" class="btn btn-outline-primary w-100">ขอลิงก์ยืนยันใหม่</a>
                <?php elseif ($token === ''): ?>
                    <p class="text-muted">กรอกอีเมลที่ผูกกับบัญชีของคุณ เราจะส่งลิงก์ยืนยันสำหรับตั้งรหัสผ่านใหม่</p>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request">
                        <div class="mb-3">
                            <label class="form-label fw-bold">อีเมล</label>
                            <input type="email" name="email" class="form-control" required autocomplete="email">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">ส่งลิงก์ยืนยันทางอีเมล</button>
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
