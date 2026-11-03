<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('manager');
$manager_id = $_SESSION['user_id'];

// Only the one central organizer account may create other organizer accounts this way
// (issu.md #37/#38) -- everyone else applies via manager_register.php instead.
if ($manager_id !== central_manager_id($pdo)) {
    render_error_page('หน้านี้ใช้ได้เฉพาะบัญชีกลางของผู้ดูแลกิจกรรมเท่านั้น', 'dashboard.php');
}

$fieldErrors = [];
$reopenData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '') ?: null;

    if ($username === '') $fieldErrors['username'] = 'กรุณากรอก Username';
    if ($name === '') $fieldErrors['name'] = 'กรุณากรอกชื่อ-นามสกุล';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $fieldErrors['email'] = 'กรุณากรอกอีเมลที่ถูกต้อง (ใช้สำหรับส่งรหัส OTP ยืนยัน)';

    if (empty($fieldErrors)) {
        $conflict = identifier_conflict($pdo, $username, null, null, $email);
        if ($conflict) {
            $field = strpos($conflict, 'อีเมล') !== false ? 'email' : 'username';
            $fieldErrors[$field] = $conflict;
        }
    }

    if (empty($fieldErrors)) {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $placeholderPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO activity_supervisors
            (username, password, name, email, phone, status, otp_code, otp_expires_at, created_by_manager_id)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), ?)");
        $stmt->execute([$username, $placeholderPassword, $name, $email, $phone, $otp, $manager_id]);

        $appUrl = getenv('APP_URL') ?: 'http://localhost/Soft-Skill';
        $verifyUrl = rtrim($appUrl, '/') . '/verify_organizer.php?username=' . rawurlencode($username);
        $body = "บัญชีผู้ดูแลกิจกรรมของคุณถูกสร้างขึ้นในระบบ Soft Skill\n\n";
        $body .= "Username: $username\n";
        $body .= "รหัสยืนยัน (OTP): $otp (หมดอายุภายใน 15 นาที)\n\n";
        $body .= "เปิดลิงก์นี้เพื่อยืนยันตัวตนและตั้งรหัสผ่านของคุณ:\n$verifyUrl";
        $mailSent = send_mail($email, 'ยืนยันบัญชีผู้ดูแลกิจกรรม Soft Skill ของคุณ', $body);

        log_action($pdo, 'organizer.create_otp', "$username <$email>" . ($mailSent ? '' : ' (ส่งอีเมลไม่สำเร็จ)'));
        header("Location: create_organizer.php?success=" . ($mailSent ? 'Sent' : 'SentNoMail')); exit;
    }
    $reopenData = $_POST;
}

$pending = $pdo->prepare("SELECT id, username, name, email, otp_expires_at FROM activity_supervisors
    WHERE created_by_manager_id = ? AND status = 'pending' AND otp_code IS NOT NULL ORDER BY id DESC");
$pending->execute([$manager_id]);
$pending = $pending->fetchAll();

include '../includes/header.php';
?>
<h3 class="mb-3">สร้างผู้ดูแลกิจกรรมใหม่ (ยืนยันผ่าน OTP ทางอีเมล)</h3>
<p class="text-muted small">กรอกข้อมูลผู้ดูแลกิจกรรมคนใหม่ ระบบจะส่งรหัส OTP ไปยังอีเมลที่ระบุ ผู้ใช้ใหม่ต้องกรอก OTP และตั้งรหัสผ่านเองที่หน้ายืนยันก่อนจึงจะเข้าใช้งานได้</p>
<?php if (($_GET['success'] ?? '') === 'Sent'): ?><div class="alert alert-success">สร้างบัญชีและส่งอีเมล OTP เรียบร้อยแล้ว</div><?php endif; ?>
<?php if (($_GET['success'] ?? '') === 'SentNoMail'): ?><div class="alert alert-warning">สร้างบัญชีแล้ว แต่ส่งอีเมลไม่สำเร็จ (ระบบอีเมลอาจยังไม่ได้ตั้งค่า) กรุณาแจ้ง OTP ให้ผู้ใช้ด้วยช่องทางอื่น</div><?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control <?= isset($fieldErrors['username']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($reopenData['username'] ?? '') ?>" required>
                <?php if (isset($fieldErrors['username'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['username']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">ชื่อ-นามสกุล</label>
                <input type="text" name="name" class="form-control <?= isset($fieldErrors['name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($reopenData['name'] ?? '') ?>" required>
                <?php if (isset($fieldErrors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['name']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">อีเมล (ใช้ส่ง OTP)</label>
                <input type="email" name="email" class="form-control <?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($reopenData['email'] ?? '') ?>" required>
                <?php if (isset($fieldErrors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['email']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">เบอร์โทร</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($reopenData['phone'] ?? '') ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">สร้างบัญชีและส่ง OTP</button>
            </div>
        </form>
    </div>
</div>

<h5>รอการยืนยัน OTP</h5>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle mb-0">
            <thead class="table-dark"><tr><th>Username</th><th>ชื่อ-นามสกุล</th><th>อีเมล</th><th>OTP หมดอายุ</th></tr></thead>
            <tbody>
                <?php foreach ($pending as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['username']) ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td><?= htmlspecialchars($p['otp_expires_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pending)): ?><tr><td colspan="4" class="text-center">ไม่มีรายการรอยืนยัน</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
