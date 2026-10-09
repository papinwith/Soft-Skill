<?php
require_once '../config/db.php';
check_login('manager');
$manager_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $activity_id = (int)($_POST['activity_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        $q = trim($_POST['q'] ?? '');
        $result = 'success=StatusUpdated';
        $pdo->beginTransaction();
        $check = $pdo->prepare("SELECT capacity FROM activities WHERE id = ? AND manager_id = ? FOR UPDATE");
        $check->execute([$activity_id, $manager_id]);
        $activity = $check->fetch();
        if ($activity && in_array($status, ['applied', 'approved', 'attended', 'rejected'], true)) {
            $cur = $pdo->prepare("SELECT status FROM activity_participants WHERE activity_id = ? AND student_id = ?");
            $cur->execute([$activity_id, $student_id]);
            $current = $cur->fetchColumn();
            $reopening = $current === 'rejected' && $status !== 'rejected';
            if ($reopening && $activity['capacity'] > 0) {
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM activity_participants WHERE activity_id = ? AND status <> 'rejected'");
                $cnt->execute([$activity_id]);
                if ((int)$cnt->fetchColumn() >= $activity['capacity']) {
                    $result = 'error=' . urlencode('ไม่สามารถเปลี่ยนสถานะได้ เนื่องจากกิจกรรมมีผู้สมัครเต็มจำนวนแล้ว');
                }
            }
            if ($current !== false && strpos($result, 'error=') === false) {
                $pdo->prepare("UPDATE activity_participants SET status = ? WHERE activity_id = ? AND student_id = ?")->execute([$status, $activity_id, $student_id]);
            }
        }
        $pdo->commit();
        header("Location: participants.php?activity_id=$activity_id" . ($q !== '' ? '&q=' . urlencode($q) : '') . "&$result"); exit;
    }
}

$activities = $pdo->prepare("SELECT id, title FROM activities WHERE manager_id = ? ORDER BY id DESC");
$activities->execute([$manager_id]);
$activities = $activities->fetchAll();

$selected_activity = (int)($_GET['activity_id'] ?? ($activities[0]['id'] ?? 0));
if (!in_array($selected_activity, array_map('intval', array_column($activities, 'id')), true)) {
    $selected_activity = 0;
}
$q = trim($_GET['q'] ?? '');

$participants = [];
if ($selected_activity) {
    $sql = "SELECT ap.*, u.name, u.student_id as student_code, u.department
        FROM activity_participants ap JOIN students u ON ap.student_id = u.id
        WHERE ap.activity_id = ?";
    $params = [$selected_activity];
    if ($q !== '') {
        $sql .= " AND (u.name LIKE ? OR u.student_id LIKE ?)";
        $like = '%' . addcslashes($q, '%_\\') . '%';
        array_push($params, $like, $like);
    }
    $stmt = $pdo->prepare($sql . " ORDER BY ap.applied_at DESC");
    $stmt->execute($params);
    $participants = $stmt->fetchAll();
}

include '../includes/header.php';
$badges = ['applied' => ['secondary', 'รอยืนยัน'], 'approved' => ['primary', 'ยืนยันแล้ว'], 'attended' => ['success', 'เข้าร่วมแล้ว'], 'rejected' => ['danger', 'ปฏิเสธ']];
?>
<div class="d-flex justify-content-between mb-3">
    <h3>ตรวจสอบรายชื่อนักศึกษาและยืนยันการเข้าร่วม</h3>
</div>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">อัปเดตสถานะเรียบร้อย</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row gx-3 gy-2 align-items-end">
            <div class="col-md-5">
                <label>เลือกกิจกรรมที่ต้องการดูรายชื่อ:</label>
                <select name="activity_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- เลือกกิจกรรม --</option>
                    <?php foreach($activities as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $selected_activity == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label>ค้นหา (ชื่อ / รหัสนักศึกษา):</label>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="พิมพ์เพื่อค้นหา">
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">ค้นหา</button></div>
        </form>
    </div>
</div>

<?php if($selected_activity): ?>
<div class="card shadow-sm">
    <div class="card-header bg-success text-white">รายชื่อผู้สมัครเข้าร่วม</div>
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr><th>รหัสนักศึกษา</th><th>ชื่อ-นามสกุล</th><th>คณะ/สาขา</th><th>สถานะปัจจุบัน</th><th>เปลี่ยนสถานะ</th></tr>
            </thead>
            <tbody>
                <?php foreach($participants as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['student_code'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['department'] ?? '') ?></td>
                    <td><span class="badge bg-<?= $badges[$p['status']][0] ?>"><?= $badges[$p['status']][1] ?></span></td>
                    <td class="text-nowrap">
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="activity_id" value="<?= (int)$selected_activity ?>">
                            <input type="hidden" name="student_id" value="<?= (int)$p['student_id'] ?>">
                            <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                            <select name="status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                <option value="applied" <?= $p['status']=='applied'?'selected':'' ?>>รอยืนยัน</option>
                                <option value="approved" <?= $p['status']=='approved'?'selected':'' ?>>ยืนยันให้เข้าร่วม (อนุมัติ)</option>
                                <option value="rejected" <?= $p['status']=='rejected'?'selected':'' ?>>ปฏิเสธ</option>
                                <option value="attended" <?= $p['status']=='attended'?'selected':'' ?>>เช็คชื่อเข้าร่วมแล้ว (เสร็จสิ้น)</option>
                            </select>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($participants)): ?><tr><td colspan="5" class="text-center">ไม่พบผู้สมัคร</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
