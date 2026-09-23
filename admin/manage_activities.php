<?php
require_once '../config/db.php';
check_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'add') {
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $manager = $_POST['manager_id'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        
        $stmt = $pdo->prepare("INSERT INTO activities (title, description, manager_id, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $desc, $manager, $start, $end]);
        header("Location: manage_activities.php?success=Added"); exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM activities WHERE id = ?")->execute([$id]);
        header("Location: manage_activities.php?success=Deleted"); exit;
    }
}

$activities = $pdo->query("SELECT a.*, u.name as manager_name FROM activities a JOIN users u ON a.manager_id = u.id ORDER BY a.id DESC")->fetchAll();
$managers = $pdo->query("SELECT id, name FROM users WHERE role = 'manager'")->fetchAll();

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการกิจกรรม</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่มกิจกรรม</button>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr><th>ID</th><th>ชื่อกิจกรรม</th><th>ผู้ดูแล</th><th>เริ่ม</th><th>สิ้นสุด</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach($activities as $a): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><?= htmlspecialchars($a['title']) ?></td>
                    <td><?= htmlspecialchars($a['manager_name']) ?></td>
                    <td><?= $a['start_date'] ?></td>
                    <td><?= $a['end_date'] ?></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($activities)): ?><tr><td colspan="6" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
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
        <h5 class="modal-title">เพิ่มกิจกรรม</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">ชื่อกิจกรรม</label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">รายละเอียด</label><textarea name="description" class="form-control" rows="3"></textarea></div>
        <div class="mb-3">
            <label class="form-label">ผู้ดูแลกิจกรรม</label>
            <select name="manager_id" class="form-control" required>
                <option value="">-- เลือกผู้ดูแล --</option>
                <?php foreach($managers as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">วันที่เริ่ม</label><input type="date" name="start_date" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">วันที่สิ้นสุด</label><input type="date" name="end_date" class="form-control" required></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
