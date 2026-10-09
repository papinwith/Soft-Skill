<?php
require_once '../config/db.php';
check_login('student');
$student_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'apply') {
        $activity_id = (int)($_POST['activity_id'] ?? 0);
        // Lock the activity row so concurrent applications cannot exceed the capacity
        $pdo->beginTransaction();
        $act = $pdo->prepare("SELECT status, capacity, end_date FROM activities WHERE id = ? FOR UPDATE");
        $act->execute([$activity_id]);
        $activity = $act->fetch();
        $error = null;
        if (!$activity) $error = 'ไม่พบกิจกรรม';
        elseif ($activity['status'] !== 'open' || $activity['end_date'] < date('Y-m-d')) $error = 'กิจกรรมนี้ปิดรับสมัครแล้ว';
        elseif ($activity['capacity'] > 0) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM activity_participants WHERE activity_id = ? AND status <> 'rejected'");
            $cnt->execute([$activity_id]);
            if ((int)$cnt->fetchColumn() >= $activity['capacity']) $error = 'กิจกรรมนี้มีผู้สมัครเต็มจำนวนแล้ว';
        }
        if ($error) {
            $pdo->rollBack();
            header("Location: activities.php?error=" . urlencode($error)); exit;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO activity_participants (activity_id, student_id, status) VALUES (?, ?, 'applied')");
        $stmt->execute([$activity_id, $student_id]);
        $pdo->commit();
        header("Location: assessment.php?type=pre&activity_id=" . $activity_id); exit;
    }
}

$activities = $pdo->query("SELECT a.*, u.name as manager_name,
    (SELECT COUNT(*) FROM activity_participants ap WHERE ap.activity_id = a.id AND ap.status <> 'rejected') AS joined
    FROM activities a JOIN activity_supervisors u ON a.manager_id = u.id ORDER BY a.id DESC")->fetchAll();
$my_applications = $pdo->prepare("SELECT activity_id, status FROM activity_participants WHERE student_id = ?");
$my_applications->execute([$student_id]);
$my_apps = [];
foreach($my_applications->fetchAll() as $row) {
    $my_apps[$row['activity_id']] = $row['status'];
}

$responses = $pdo->prepare("SELECT activity_id, type FROM student_assessments WHERE student_id = ?");
$responses->execute([$student_id]);
$submitted_assessments = [];
foreach($responses->fetchAll() as $r) {
    $submitted_assessments[$r['activity_id']][$r['type']] = true;
}

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-4">
    <h3>รายการกิจกรรม</h3>
</div>

<?php if(isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="row">
    <?php foreach($activities as $a):
        $aid = $a['id'];
        $status = $my_apps[$aid] ?? null;
        $done_pre = isset($submitted_assessments[$aid]['pre']);
        $done_post = isset($submitted_assessments[$aid]['post']);
        $is_full = $a['capacity'] > 0 && $a['joined'] >= $a['capacity'];
        $is_open = $a['status'] === 'open' && $a['end_date'] >= date('Y-m-d');
    ?>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-primary text-white fw-bold py-3 fs-5"><?= htmlspecialchars($a['title']) ?></div>
            <div class="card-body bg-light">
                <p class="mb-2"><strong>รายละเอียด:</strong> <?= nl2br(htmlspecialchars($a['description'] ?? '')) ?></p>
                <p class="mb-2"><strong>วันที่:</strong> <?= $a['start_date'] ?> ถึง <?= $a['end_date'] ?></p>
                <?php if($a['location']): ?><p class="mb-2"><strong>สถานที่:</strong> <?= htmlspecialchars($a['location']) ?></p><?php endif; ?>
                <p class="mb-2"><strong>ผู้จัดกิจกรรม:</strong> <?= htmlspecialchars($a['manager_name']) ?></p>
                <p class="mb-0"><strong>ผู้สมัคร:</strong> <?= $a['joined'] ?><?= $a['capacity'] > 0 ? ' / ' . $a['capacity'] : ' (ไม่จำกัดจำนวน)' ?></p>
            </div>
            <div class="card-footer bg-white text-center py-3">
                <?php if($status): ?>
                    <div class="mb-3">
                        <?php if($status === 'applied') echo '<span class="badge bg-secondary p-2 fs-6">กำลังรอยืนยันการเข้าร่วม</span>'; ?>
                        <?php if($status === 'approved') echo '<span class="badge bg-primary p-2 fs-6">ยืนยันการเข้าร่วมแล้ว</span>'; ?>
                        <?php if($status === 'attended') echo '<span class="badge bg-success p-2 fs-6">เข้าร่วมกิจกรรมเสร็จสิ้น</span>'; ?>
                        <?php if($status === 'rejected') echo '<span class="badge bg-danger p-2 fs-6">คำขอถูกปฏิเสธ</span>'; ?>
                    </div>

                    <?php if($status !== 'rejected'): ?>
                        <?php if(!$done_pre): ?>
                            <a href="assessment.php?type=pre&activity_id=<?= $aid ?>" class="btn btn-outline-primary w-100 fw-bold shadow-sm mb-2">ทำแบบประเมินก่อนเข้าร่วม (Pre-test)</a>
                        <?php else: ?>
                            <div class="mb-2"><span class="text-success fw-bold">ทำแบบประเมิน Pre-test เรียบร้อยแล้ว</span></div>

                            <?php if(!$done_post): ?>
                                <?php if($status === 'attended'): ?>
                                    <a href="assessment.php?type=post&activity_id=<?= $aid ?>" class="btn btn-outline-success w-100 fw-bold shadow-sm mb-2">ทำแบบประเมินหลังเข้าร่วม (Post-test)</a>
                                <?php else: ?>
                                    <div class="text-muted small mb-2">Post-test จะเปิดให้ทำหลังผู้ดูแลกิจกรรมยืนยันว่าคุณเข้าร่วมกิจกรรมแล้ว</div>
                                <?php endif; ?>
                                <a href="analysis_report.php?activity_id=<?= $aid ?>" class="btn btn-info text-white w-100 mt-2 fw-bold shadow-sm">ดูรายงานวิเคราะห์ (Pre-test)</a>
                            <?php else: ?>
                                <div class="mb-2"><span class="text-success fw-bold">ทำแบบประเมินครบถ้วนแล้ว</span></div>
                                <a href="analysis_report.php?activity_id=<?= $aid ?>" class="btn btn-info text-white w-100 mt-2 fw-bold shadow-sm">ดูรายงานวิเคราะห์จาก AI</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php elseif($is_full): ?>
                    <span class="badge bg-danger p-2 fs-6">ผู้สมัครเต็มจำนวนแล้ว</span>
                <?php elseif(!$is_open): ?>
                    <span class="badge bg-secondary p-2 fs-6">ปิดรับสมัคร</span>
                <?php else: ?>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="apply">
                        <input type="hidden" name="activity_id" value="<?= $aid ?>">
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" onclick="return confirm('ยืนยันสมัครเข้าร่วมกิจกรรม? ระบบจะให้ทำแบบประเมินก่อนเข้าร่วม (Pre-test) ต่อทันที');">สมัครเข้าร่วมกิจกรรมนี้</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($activities)): ?><div class="col-12"><div class="alert alert-info border-info text-center py-5">ยังไม่มีกิจกรรมในขณะนี้</div></div><?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
