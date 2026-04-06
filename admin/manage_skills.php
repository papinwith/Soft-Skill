<?php
require_once '../config/db.php';
check_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'add') {
        $name = $_POST['skill_name'];
        $desc = $_POST['description'];
        $stmt = $pdo->prepare("INSERT INTO soft_skills (skill_name, description) VALUES (?, ?)");
        $stmt->execute([$name, $desc]);
        header("Location: manage_skills.php?success=Added"); exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM soft_skills WHERE id = ?")->execute([$id]);
        header("Location: manage_skills.php?success=Deleted"); exit;
    }
}

$skills = $pdo->query("SELECT * FROM soft_skills")->fetchAll();

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการ Soft Skills</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่ม Soft Skill</button>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr><th>ID</th><th>ชื่อทักษะ (Skill Name)</th><th>คำอธิบาย</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach($skills as $s): ?>
                <tr>
                    <td><?= $s['id'] ?></td>
                    <td><?= htmlspecialchars($s['skill_name']) ?></td>
                    <td><?= htmlspecialchars($s['description']) ?></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($skills)): ?><tr><td colspan="4" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">เพิ่ม Soft Skill</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">ชื่อทักษะ</label><input type="text" name="skill_name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">คำอธิบาย</label><textarea name="description" class="form-control" rows="3"></textarea></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
