<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('admin');

$role = $_GET['role'] ?? 'student';
if (!in_array($role, ['student', 'manager'], true)) $role = 'student';
$role_title = $role === 'manager' ? 'ผู้ดูแลกิจกรรม' : 'นักศึกษา';
$table = $role === 'manager' ? 'activity_supervisors' : 'students';

$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

function nullable_id($v) { return ($v === '' || $v === null) ? null : (int)$v; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $error = null;
    if ($action === 'add' || $action === 'edit') {
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '') ?: null;
        $phone = trim($_POST['phone'] ?? '') ?: null;
        $student_id = $role === 'student' ? (trim($_POST['student_id'] ?? '') ?: null) : null;
        $year_level = $role === 'student' ? nullable_id($_POST['year_level'] ?? '') : null;
        $id = (int)($_POST['id'] ?? 0);
        $faculty_id = $department_id = $department_text = null;

        if ($username === '' || $name === '') $error = 'กรุณากรอกข้อมูลให้ครบ';
        elseif ($action === 'add' && ($pwError = password_strength_error($password))) $error = $pwError;
        elseif ($action === 'edit' && $password !== '' && ($pwError = password_strength_error($password))) $error = $pwError;
        elseif ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'รูปแบบอีเมลไม่ถูกต้อง';
        elseif ($student_id !== null && strlen($student_id) > 10) $error = 'รหัสนักศึกษาต้องมีความยาวไม่เกิน 10 ตัวอักษร';
        elseif ($year_level !== null && ($year_level < 1 || $year_level > 4)) $error = 'ชั้นปีต้องอยู่ระหว่าง 1-4';

        if (!$error) $error = identifier_conflict($pdo, $username, $student_id, $action === 'edit' ? [$table, $id] : null);
        if (!$error && $role === 'student') {
            [$faculty_id, $department_id, $department_text, $error] = resolve_faculty_department($pdo, nullable_id($_POST['faculty_id'] ?? ''), nullable_id($_POST['department_id'] ?? ''));
            if (!$error && $action === 'edit' && $department_id === null) {
                // Keep legacy free-text department when the account was never linked to the department table
                $old = $pdo->prepare("SELECT department, department_id FROM students WHERE id = ?");
                $old->execute([$id]);
                $o = $old->fetch();
                if ($o && $o['department_id'] === null) $department_text = $o['department'];
            }
        }
        if (!$error) {
            if ($action === 'add') {
                if ($table === 'students') {
                    $stmt = $pdo->prepare("INSERT INTO students (username, password, name, student_id, department, email, faculty_id, department_id, year_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $student_id, $department_text, $email, $faculty_id, $department_id, $year_level]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO activity_supervisors (username, password, name, email, phone) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $email, $phone]);
                }
            } else {
                if ($table === 'students') {
                    $stmt = $pdo->prepare("UPDATE students SET username = ?, name = ?, student_id = ?, department = ?, email = ?, faculty_id = ?, department_id = ?, year_level = ? WHERE id = ?");
                    $stmt->execute([$username, $name, $student_id, $department_text, $email, $faculty_id, $department_id, $year_level, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE activity_supervisors SET username = ?, name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $name, $email, $phone, $id]);
                }
                if ($password !== '') {
                    $pdo->prepare("UPDATE `$table` SET password = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            }
            header("Location: manage_users.php?role=$role&success=" . ($action === 'add' ? 'Added' : 'Updated')); exit;
        }
        header("Location: manage_users.php?role=$role&error=" . urlencode($error)); exit;
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
        header("Location: manage_users.php?role=$role&success=Deleted"); exit;
    }
}

if ($table === 'students') {
    $users = $pdo->query("SELECT u.*, f.name AS faculty_name FROM students u LEFT JOIN faculties f ON u.faculty_id = f.id ORDER BY u.id")->fetchAll();
} else {
    $users = $pdo->query("SELECT u.* FROM activity_supervisors u ORDER BY u.id")->fetchAll();
}

include '../includes/header.php';

function user_form_fields($role, $faculties, $departments, $isEdit) { ?>
    <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Password <?= $isEdit ? '<small class="text-muted">(เว้นว่างหากไม่เปลี่ยน)</small>' : '' ?></label><input type="password" name="password" class="form-control" minlength="8" <?= $isEdit ? '' : 'required' ?>></div>
    <div class="mb-3"><label class="form-label">ชื่อ-นามสกุล</label><input type="text" name="name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">อีเมล</label><input type="email" name="email" class="form-control"></div>
    <?php if ($role === 'manager'): ?>
        <div class="mb-3"><label class="form-label">เบอร์โทร</label><input type="text" name="phone" class="form-control"></div>
    <?php else: ?>
        <div class="mb-3"><label class="form-label">รหัสนักศึกษา</label><input type="text" name="student_id" class="form-control" required maxlength="10"></div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">คณะ</label>
                <select name="faculty_id" class="form-select"><option value="">-- เลือกคณะ --</option>
                    <?php foreach ($faculties as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-6 mb-3"><label class="form-label">สาขา</label>
                <select name="department_id" class="form-select" disabled><option value="">-- เลือกคณะก่อน --</option>
                    <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" data-faculty="<?= $d['faculty_id'] ?>" hidden disabled><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="mb-3"><label class="form-label">ชั้นปี</label>
            <select name="year_level" class="form-select"><option value="">-</option><?php for ($y = 1; $y <= 4; $y++): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?></select></div>
    <?php endif;
}
?>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการ<?= $role_title ?></h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่ม<?= $role_title ?></button>
</div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Username</th><th>Name</th><th>อีเมล</th>
                    <?php if ($role === 'student'): ?><th>รหัสนักศึกษา</th><th>คณะ/สาขา</th><th>ชั้นปี</th><?php else: ?><th>เบอร์โทร</th><?php endif; ?>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    $fill = ['id' => $u['id'], 'username' => $u['username'], 'name' => $u['name'], 'email' => $u['email'], 'phone' => $u['phone'] ?? null,
                        'student_id' => $u['student_id'] ?? null, 'faculty_id' => $u['faculty_id'] ?? null, 'department_id' => $u['department_id'] ?? null, 'year_level' => $u['year_level'] ?? null]; ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                    <?php if ($role === 'student'): ?>
                        <td><?= htmlspecialchars($u['student_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars(trim(($u['faculty_name'] ?? '') . ' / ' . ($u['department'] ?? ''), ' /')) ?></td>
                        <td><?= htmlspecialchars($u['year_level'] ?? '') ?></td>
                    <?php else: ?>
                        <td><?= htmlspecialchars($u['phone'] ?? '') ?></td>
                    <?php endif; ?>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" data-fill="<?= htmlspecialchars(json_encode($fill), ENT_QUOTES) ?>">แก้ไข</button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบข้อมูลนี้?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?><tr><td colspan="8" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">เพิ่ม<?= $role_title ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php user_form_fields($role, $faculties, $departments, false); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="editModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">แก้ไข<?= $role_title ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php user_form_fields($role, $faculties, $departments, true); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button></div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
