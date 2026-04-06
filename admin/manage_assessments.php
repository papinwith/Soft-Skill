<?php
require_once '../config/db.php';
check_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'add') {
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $stmt = $pdo->prepare("INSERT INTO assessments (title, description) VALUES (?, ?)");
        $stmt->execute([$title, $desc]);
        header("Location: manage_assessments.php?success=Added"); exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM assessments WHERE id = ?")->execute([$id]);
        header("Location: manage_assessments.php?success=Deleted"); exit;
    }
}

$assessments = $pdo->query("SELECT * FROM assessments")->fetchAll();

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการแบบประเมิน</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่มแบบประเมิน</button>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr><th>ID</th><th>ชื่อแบบประเมิน</th><th>รายละเอียด</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach($assessments as $a): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><?= htmlspecialchars($a['title']) ?></td>
                    <td><?= htmlspecialchars($a['description']) ?></td>
                    <td>
                        <a href="assessment_questions.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-info">จัดการคำถาม</a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ? คำถามทั้งหมดในแบบประเมินนี้จะถูกลบด้วย');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($assessments)): ?><tr><td colspan="4" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
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
        <h5 class="modal-title">เพิ่มแบบประเมิน</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">ชื่อแบบประเมิน</label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">รายละเอียด</label><textarea name="description" class="form-control" rows="3"></textarea></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
