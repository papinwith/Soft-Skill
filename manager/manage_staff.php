<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('manager');

$fieldErrors = [];
$reopenData = [];
$reopenModal = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;

    if ($username === '') $fieldErrors['username'] = 'กรุณากรอก Username';
    if ($name === '') $fieldErrors['name'] = 'กรุณากรอกชื่อ-นามสกุล';
    if ($pwError = password_strength_error($password)) $fieldErrors['password'] = $pwError;
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) $fieldErrors['email'] = 'รูปแบบอีเมลไม่ถูกต้อง';

    if (empty($fieldErrors)) {
        $conflict = identifier_conflict($pdo, $username, null, null, $email);
        if ($conflict) {
            $field = strpos($conflict, 'อีเมล') !== false ? 'email' : 'username';
            $fieldErrors[$field] = $conflict;
        }
    }

    if (empty($fieldErrors)) {
        $stmt = $pdo->prepare("INSERT INTO activity_supervisors (username, password, name, email, phone) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $email, $phone]);
        header("Location: manage_staff.php?success=Added"); exit;
    }
    $reopenModal = 'addModal';
    $reopenData = $_POST;
}

$staff = $pdo->query("SELECT id, username, name, email, phone, created_at FROM activity_supervisors ORDER BY id DESC")->fetchAll();

include '../includes/header.php';
?>
<div class="mb-3"><a href="dashboard.php" class="btn btn-sm btn-secondary">&larr; ย้อนกลับ</a></div>
<div class="d-flex justify-content-between mb-3">
    <h3>ผู้ดูแลกิจกรรมในระบบ</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่มผู้ดูแลกิจกรรม</button>
</div>
<p class="text-muted small">หน้านี้ให้ผู้ดูแลกิจกรรมที่ล็อกอินอยู่สร้างบัญชีผู้ดูแลกิจกรรมคนอื่นเพิ่มได้เอง โดยไม่ต้องรอ Admin — เพิ่มได้อย่างเดียว ไม่สามารถแก้ไขหรือลบบัญชีผู้อื่นจากหน้านี้</p>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">เพิ่มผู้ดูแลกิจกรรมเรียบร้อยแล้ว</div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark"><tr><th>ID</th><th>Username</th><th>ชื่อ-นามสกุล</th><th>อีเมล</th><th>เบอร์โทร</th><th>สร้างเมื่อ</th></tr></thead>
            <tbody>
                <?php foreach ($staff as $s): ?>
                <tr>
                    <td><?= $s['id'] ?></td>
                    <td><?= htmlspecialchars($s['username']) ?></td>
                    <td><?= htmlspecialchars($s['name']) ?></td>
                    <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($s['created_at'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($staff)): ?><tr><td colspan="6" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">เพิ่มผู้ดูแลกิจกรรม</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" minlength="8" required></div>
        <div class="mb-3"><label class="form-label">ชื่อ-นามสกุล</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">อีเมล</label><input type="email" name="email" class="form-control"></div>
        <div class="mb-3"><label class="form-label">เบอร์โทร</label><input type="text" name="phone" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>
<?= $reopenModal ? reopen_modal_script($reopenModal, $reopenData, $fieldErrors) : '' ?>
<?php include '../includes/footer.php'; ?>
