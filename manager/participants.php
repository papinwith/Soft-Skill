<?php
require_once '../config/db.php';
check_login('manager');
$manager_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'update_status') {
        $activity_id = $_POST['activity_id'];
        $student_id = $_POST['student_id'];
        $status = $_POST['status']; // 'approved', 'attended', 'applied'
        
        // Verify manager owns the activity
        $check = $pdo->prepare("SELECT id FROM activities WHERE id = ? AND manager_id = ?");
        $check->execute([$activity_id, $manager_id]);
        if ($check->fetch()) {
            $stmt = $pdo->prepare("UPDATE activity_participants SET status = ? WHERE activity_id = ? AND student_id = ?");
            $stmt->execute([$status, $activity_id, $student_id]);
        }
        header("Location: participants.php?activity_id=$activity_id&success=StatusUpdated"); exit;
    }
}

// Get all activities of this manager for the dropdown
$activities = $pdo->prepare("SELECT id, title FROM activities WHERE manager_id = ? ORDER BY id DESC");
$activities->execute([$manager_id]);
$activities = $activities->fetchAll();

$selected_activity = $_GET['activity_id'] ?? ($activities[0]['id'] ?? 0);

$participants = [];
if ($selected_activity) {
    $stmt = $pdo->prepare("SELECT ap.*, u.name, u.student_id as student_code, u.department 
        FROM activity_participants ap JOIN users u ON ap.student_id = u.id 
        WHERE ap.activity_id = ? ORDER BY ap.applied_at DESC");
    $stmt->execute([$selected_activity]);
    $participants = $stmt->fetchAll();
}

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h3>ตรวจสอบรายชื่อนักศึกษาและยืนยันการเข้าร่วม</h3>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row gx-3 gy-2 align-items-center">
            <div class="col-md-6">
                <label>เลือกกิจกรรมที่ต้องการดูรายชื่อ:</label>
                <select name="activity_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- เลือกกิจกรรม --</option>
                    <?php foreach($activities as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $selected_activity == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if($selected_activity): ?>
<div class="card shadow-sm">
    <div class="card-header bg-success text-white">รายชื่อผู้สมัครเข้าร่วม</div>
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr><th>รหัสนักศึกษา</th><th>ชื่อ-นามสกุล</th><th>คณะ/สาขา</th><th>สถานะปัจจุบัน</th><th>เปลี่ยนสถานะ</th></tr>
            </thead>
            <tbody>
                <?php foreach($participants as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['student_code']) ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['department']) ?></td>
                    <td>
                        <?php 
                        if($p['status'] === 'applied') echo '<span class="badge bg-secondary">รอยืนยัน</span>';
                        elseif($p['status'] === 'approved') echo '<span class="badge bg-primary">ยืนยันแล้ว</span>';
                        elseif($p['status'] === 'attended') echo '<span class="badge bg-success">เข้าร่วมแล้ว</span>';
                        ?>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="activity_id" value="<?= $selected_activity ?>">
                            <input type="hidden" name="student_id" value="<?= $p['student_id'] ?>">
                            <select name="status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                <option value="applied" <?= $p['status']=='applied'?'selected':'' ?>>รอยืนยัน</option>
                                <option value="approved" <?= $p['status']=='approved'?'selected':'' ?>>ยืนยันให้เข้าร่วม</option>
                                <option value="attended" <?= $p['status']=='attended'?'selected':'' ?>>เช็คชื่อเข้าร่วมแล้ว (เสร็จสิ้น)</option>
                            </select>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($participants)): ?><tr><td colspan="5" class="text-center">ยังไม่มีผู้สมัคร</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
