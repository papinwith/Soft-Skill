<?php
require_once '../config/db.php';
check_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $faculty_id = (int)($_POST['faculty_id'] ?? 0);
    $error = null;
    try {
        if ($action === 'add_faculty' && $name !== '') {
            $pdo->prepare("INSERT INTO faculties (name) VALUES (?)")->execute([$name]);
        } elseif ($action === 'edit_faculty' && $name !== '') {
            $pdo->prepare("UPDATE faculties SET name = ? WHERE id = ?")->execute([$name, $id]);
        } elseif ($action === 'delete_faculty') {
            $pdo->prepare("DELETE FROM faculties WHERE id = ?")->execute([$id]);
        } elseif ($action === 'add_department' && $name !== '' && $faculty_id) {
            $pdo->prepare("INSERT INTO departments (faculty_id, name) VALUES (?, ?)")->execute([$faculty_id, $name]);
        } elseif ($action === 'edit_department' && $name !== '' && $faculty_id) {
            $pdo->prepare("UPDATE departments SET faculty_id = ?, name = ? WHERE id = ?")->execute([$faculty_id, $name, $id]);
            $pdo->prepare("UPDATE students SET department = ?, faculty_id = ? WHERE department_id = ?")->execute([$name, $faculty_id, $id]);
        } elseif ($action === 'delete_department') {
            $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
        } else {
            $error = 'กรุณากรอกข้อมูลให้ครบ';
        }
    } catch (PDOException $e) {
        $error = ($e->errorInfo[1] ?? 0) == 1062 ? 'ข้อมูลนี้มีอยู่แล้ว' : 'ไม่สามารถบันทึกข้อมูลได้';
    }
    header('Location: manage_departments.php?' . ($error ? 'error=' . urlencode($error) : 'success=Saved')); exit;
}

$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT d.*, f.name AS faculty_name FROM departments d JOIN faculties f ON d.faculty_id = f.id ORDER BY f.name, d.name")->fetchAll();

include '../includes/header.php';

function facultySelect($faculties) { ?>
    <select name="faculty_id" class="form-select" required>
        <option value="">-- เลือกคณะ --</option>
        <?php foreach ($faculties as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option><?php endforeach; ?>
    </select>
<?php } ?>
<div class="mb-3"><a href="dashboard.php" class="btn btn-sm btn-secondary">&larr; ย้อนกลับ</a></div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div><?php endif; ?>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="d-flex justify-content-between mb-2">
            <h4>จัดการคณะ (Faculties)</h4>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFaculty">เพิ่มคณะ</button>
        </div>
        <div class="card shadow-sm"><div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead class="table-dark"><tr><th>ชื่อคณะ</th><th>จัดการ</th></tr></thead>
                <tbody>
                <?php foreach ($faculties as $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['name']) ?></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editFaculty" data-fill="<?= htmlspecialchars(json_encode(['id' => $f['id'], 'name' => $f['name']]), ENT_QUOTES) ?>">แก้ไข</button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('ลบคณะนี้? สาขาในคณะจะถูกลบด้วย');">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete_faculty"><input type="hidden" name="id" value="<?= $f['id'] ?>">
                                <button class="btn btn-sm btn-danger">ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($faculties)): ?><tr><td colspan="2" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-lg-7 mb-4">
        <div class="d-flex justify-content-between mb-2">
            <h4>จัดการสาขา (Majors)</h4>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addDepartment">เพิ่มสาขา</button>
        </div>
        <div class="card shadow-sm"><div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead class="table-dark"><tr><th>คณะ</th><th>ชื่อสาขา</th><th>จัดการ</th></tr></thead>
                <tbody>
                <?php foreach ($departments as $d): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($d['faculty_name']) ?></span></td>
                        <td><?= htmlspecialchars($d['name']) ?></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editDepartment" data-fill="<?= htmlspecialchars(json_encode(['id' => $d['id'], 'name' => $d['name'], 'faculty_id' => $d['faculty_id']]), ENT_QUOTES) ?>">แก้ไข</button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบสาขานี้?');">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete_department"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button class="btn btn-sm btn-danger">ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($departments)): ?><tr><td colspan="3" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<?php foreach ([['addFaculty', 'add_faculty', 'เพิ่มคณะ', false, false], ['editFaculty', 'edit_faculty', 'แก้ไขคณะ', true, false],
                ['addDepartment', 'add_department', 'เพิ่มสาขา', false, true], ['editDepartment', 'edit_department', 'แก้ไขสาขา', true, true]] as [$mid, $act, $title, $isEdit, $hasFaculty]): ?>
<div class="modal fade" id="<?= $mid ?>">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $act ?>">
      <?php if ($isEdit): ?><input type="hidden" name="id" value=""><?php endif; ?>
      <div class="modal-header"><h5 class="modal-title"><?= $title ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php if ($hasFaculty): ?><div class="mb-3"><label class="form-label">คณะ</label><?php facultySelect($faculties); ?></div><?php endif; ?>
        <div class="mb-3"><label class="form-label"><?= $hasFaculty ? 'ชื่อสาขา' : 'ชื่อคณะ' ?></label><input type="text" name="name" class="form-control" required></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php include '../includes/footer.php'; ?>
