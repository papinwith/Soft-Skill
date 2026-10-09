<?php
require_once '../config/db.php';
check_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $year_level = ($_POST['year_level'] ?? '') === '' ? null : max(1, min(4, (int)$_POST['year_level']));
    $education_level = trim($_POST['education_level'] ?? '') ?: null;
    $error = null;

    if ($name === '' || !$department_id) {
        $error = 'กรุณากรอกข้อมูลให้ครบ';
    } else {
        $dep = $pdo->prepare("SELECT id FROM departments WHERE id = ?");
        $dep->execute([$department_id]);
        if (!$dep->fetch()) $error = 'ไม่พบสาขาที่เลือก';
    }

    if (!$error) {
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO curricula (department_id, name, year_level, education_level) VALUES (?, ?, ?, ?)")
                ->execute([$department_id, $name, $year_level, $education_level]);
        } elseif ($action === 'edit') {
            $pdo->prepare("UPDATE curricula SET department_id = ?, name = ?, year_level = ?, education_level = ? WHERE id = ?")
                ->execute([$department_id, $name, $year_level, $education_level, $id]);
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM curricula WHERE id = ?")->execute([$id]);
        }
    }
    header('Location: manage_curricula.php?' . ($error ? 'error=' . urlencode($error) : 'success=Saved')); exit;
}

$departments = $pdo->query("SELECT d.*, f.name AS faculty_name FROM departments d JOIN faculties f ON d.faculty_id = f.id ORDER BY f.name, d.name")->fetchAll();
$curricula = $pdo->query("SELECT c.*, d.name AS department_name, f.name AS faculty_name
    FROM curricula c JOIN departments d ON c.department_id = d.id JOIN faculties f ON d.faculty_id = f.id
    ORDER BY f.name, d.name, c.name")->fetchAll();

include '../includes/header.php';

function curriculum_fields($departments) { ?>
    <div class="mb-3"><label class="form-label">สาขา</label>
        <select name="department_id" class="form-select" required>
            <option value="">-- เลือกสาขา --</option>
            <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['faculty_name'] . ' / ' . $d['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="mb-3"><label class="form-label">ชื่อหลักสูตร</label><input type="text" name="name" class="form-control" required></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">ชั้นปี</label>
            <select name="year_level" class="form-select"><option value="">-</option><?php for ($y = 1; $y <= 4; $y++): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?></select></div>
        <div class="col-md-6 mb-3"><label class="form-label">ระดับการศึกษา</label><input type="text" name="education_level" class="form-control" placeholder="เช่น ปริญญาตรี"></div>
    </div>
<?php } ?>
<div class="mb-3"><a href="dashboard.php" class="btn btn-sm btn-secondary">&larr; ย้อนกลับ</a></div>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการหลักสูตร</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่มหลักสูตร</button>
</div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark"><tr><th>คณะ/สาขา</th><th>ชื่อหลักสูตร</th><th>ชั้นปี</th><th>ระดับการศึกษา</th><th>จัดการ</th></tr></thead>
            <tbody>
                <?php foreach ($curricula as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['faculty_name'] . ' / ' . $c['department_name']) ?></td>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= htmlspecialchars($c['year_level'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['education_level'] ?? '-') ?></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" data-fill="<?= htmlspecialchars(json_encode(['id' => $c['id'], 'department_id' => $c['department_id'], 'name' => $c['name'], 'year_level' => $c['year_level'], 'education_level' => $c['education_level']]), ENT_QUOTES) ?>">แก้ไข</button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบหลักสูตรนี้?');">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($curricula)): ?><tr><td colspan="5" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white"><h5 class="modal-title">เพิ่มหลักสูตร</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><?php curriculum_fields($departments); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="editModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="">
      <div class="modal-header bg-warning"><h5 class="modal-title">แก้ไขหลักสูตร</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><?php curriculum_fields($departments); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button></div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
