<?php
require_once '../config/db.php';
check_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['skill_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($action === 'add' && $name !== '') {
        $pdo->prepare("INSERT INTO soft_skills (skill_name, description) VALUES (?, ?)")->execute([$name, $desc]);
        header("Location: manage_skills.php?success=Added"); exit;
    } elseif ($action === 'edit' && $name !== '') {
        $pdo->prepare("UPDATE soft_skills SET skill_name = ?, description = ? WHERE id = ?")->execute([$name, $desc, (int)($_POST['id'] ?? 0)]);
        header("Location: manage_skills.php?success=Updated"); exit;
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM soft_skills WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        header("Location: manage_skills.php?success=Deleted"); exit;
    }
    header("Location: manage_skills.php?error=" . urlencode('กรุณากรอกชื่อทักษะ')); exit;
}

$skills = $pdo->query("SELECT * FROM soft_skills")->fetchAll();

include '../includes/header.php';

function skill_fields() { ?>
    <div class="mb-3"><label class="form-label">ชื่อทักษะ</label><input type="text" name="skill_name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">คำอธิบาย</label><textarea name="description" class="form-control" rows="3"></textarea></div>
<?php } ?>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการ Soft Skills</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่ม Soft Skill</button>
</div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr><th>ID</th><th>ชื่อทักษะ (Skill Name)</th><th>คำอธิบาย</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach ($skills as $s): ?>
                <tr>
                    <td><?= $s['id'] ?></td>
                    <td><?= htmlspecialchars($s['skill_name']) ?></td>
                    <td><?= htmlspecialchars($s['description'] ?? '') ?></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" data-fill="<?= htmlspecialchars(json_encode(['id' => $s['id'], 'skill_name' => $s['skill_name'], 'description' => $s['description']]), ENT_QUOTES) ?>">แก้ไข</button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ? คำถามที่เชื่อมกับทักษะนี้จะถูกลบด้วย');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($skills)): ?><tr><td colspan="4" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">เพิ่ม Soft Skill</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php skill_fields(); ?></div>
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
        <h5 class="modal-title">แก้ไข Soft Skill</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php skill_fields(); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button></div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
