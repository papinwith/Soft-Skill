<?php
require_once '../config/db.php';
check_login('manager');
$manager_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'add') {
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        
        $stmt = $pdo->prepare("INSERT INTO activities (title, description, manager_id, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $desc, $manager_id, $start, $end]);
        header("Location: my_activities.php?success=Added"); exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM activities WHERE id = ? AND manager_id = ?")->execute([$id, $manager_id]);
        header("Location: my_activities.php?success=Deleted"); exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM activities WHERE manager_id = ? ORDER BY id DESC");
$stmt->execute([$manager_id]);
$activities = $stmt->fetchAll();

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการกิจกรรมของฉัน</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">สร้างกิจกรรมใหม่</button>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr><th>ID</th><th>ชื่อกิจกรรม</th><th>เริ่ม</th><th>สิ้นสุด</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach($activities as $a): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><?= htmlspecialchars($a['title']) ?></td>
                    <td><?= $a['start_date'] ?></td>
                    <td><?= $a['end_date'] ?></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบกิจกรรมนี้? ข้อมูลผู้เข้าร่วมและการประเมินจะถูกลบไปด้วย');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($activities)): ?><tr><td colspan="5" class="text-center">คุณยังไม่ได้สร้างกิจกรรม</td></tr><?php endif; ?>
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
        <h5 class="modal-title">สร้างกิจกรรมใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">ชื่อกิจกรรม</label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">รายละเอียด</label><textarea name="description" class="form-control" rows="3"></textarea></div>
        <div class="mb-3"><label class="form-label">วันที่เริ่ม</label><input type="date" name="start_date" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">วันที่สิ้นสุด</label><input type="date" name="end_date" class="form-control" required></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึกกิจกรรม</button></div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
