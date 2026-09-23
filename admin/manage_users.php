<?php
require_once '../config/db.php';
check_login('admin');

$role = $_GET['role'] ?? 'student';
$role_title = $role === 'manager' ? 'ผู้ดูแลกิจกรรม' : 'นักศึกษา';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'add') {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $name = trim($_POST['name']);
        $student_id = $_POST['student_id'] ?? null;
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, student_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password, $role, $name, $student_id]);
        header("Location: manage_users.php?role=$role&success=Added"); exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        header("Location: manage_users.php?role=$role&success=Deleted"); exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE role = ?");
$stmt->execute([$role]);
$users = $stmt->fetchAll();

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการ<?= $role_title ?></h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่ม<?= $role_title ?></button>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Name</th>
                    <?php if($role === 'student'): ?><th>รหัสนักศึกษา</th><?php endif; ?>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <?php if($role === 'student'): ?><td><?= htmlspecialchars($u['student_id']??'') ?></td><?php endif; ?>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบขัอมูลนี้?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($users)): ?><tr><td colspan="5" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="addModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">เพิ่ม<?= $role_title ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">ชื่อ-นามสกุล</label><input type="text" name="name" class="form-control" required></div>
        <?php if($role === 'student'): ?>
            <div class="mb-3"><label class="form-label">รหัสนักศึกษา</label><input type="text" name="student_id" class="form-control" required></div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">บันทึก</button>
      </div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
