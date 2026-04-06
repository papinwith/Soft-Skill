<?php
require_once '../config/db.php';
check_login('student');
$student_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'apply') {
        $activity_id = $_POST['activity_id'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO activity_participants (activity_id, student_id, status) VALUES (?, ?, 'applied')");
        $stmt->execute([$activity_id, $student_id]);
        header("Location: assessment.php?type=pre&activity_id=" . urlencode($activity_id)); exit;
    }
}

$activities = $pdo->query("SELECT a.*, u.name as manager_name FROM activities a JOIN users u ON a.manager_id = u.id ORDER BY a.id DESC")->fetchAll();
$my_applications = $pdo->prepare("SELECT activity_id, status FROM activity_participants WHERE student_id = ?");
$my_applications->execute([$student_id]);
$my_apps = [];
foreach($my_applications->fetchAll() as $row) {
    $my_apps[$row['activity_id']] = $row['status'];
}

// Check assessments submitted to avoid doing it twice
$responses = $pdo->prepare("SELECT activity_id, type FROM assessment_responses WHERE student_id = ? GROUP BY activity_id, type");
$responses->execute([$student_id]);
$submitted_assessments = [];
foreach($responses->fetchAll() as $r) {
    $submitted_assessments[$r['activity_id']][$r['type']] = true;
}

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-4">
    <h3>รายการกิจกรรมที่เปิดรับสมัคร</h3>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success">สมัครเข้าร่วมกิจกรรมเรียบร้อยแล้ว กรุณารอการยืนยันจากผู้ดูแลกิจกรรม</div>
<?php endif; ?>

<div class="row">
    <?php foreach($activities as $a): 
        $aid = $a['id'];
        $status = $my_apps[$aid] ?? null;
        $done_pre = isset($submitted_assessments[$aid]['pre']);
        $done_post = isset($submitted_assessments[$aid]['post']);
    ?>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-primary text-white fw-bold py-3 fs-5"><?= htmlspecialchars($a['title']) ?></div>
            <div class="card-body bg-light">
                <p class="mb-2"><strong>รายละเอียด:</strong> <?= nl2br(htmlspecialchars($a['description'])) ?></p>
                <p class="mb-2"><strong>วันที่:</strong> <?= $a['start_date'] ?> ถึง <?= $a['end_date'] ?></p>
                <p class="mb-2"><strong>ผู้จัดกิจกรรม:</strong> <?= htmlspecialchars($a['manager_name']) ?></p>
            </div>
            <div class="card-footer bg-white text-center py-3">
                <?php if($status): ?>
                    <div class="mb-3">
                        <?php if($status === 'applied') echo '<span class="badge bg-secondary p-2 fs-6">กำลังรอยืนยันการเข้าร่วม</span>'; ?>
                        <?php if($status === 'approved') echo '<span class="badge bg-primary p-2 fs-6">ยืนยันการเข้าร่วมแล้ว</span>'; ?>
                        <?php if($status === 'attended') echo '<span class="badge bg-success p-2 fs-6">เข้าร่วมกิจกรรมเสร็จสิ้น</span>'; ?>
                    </div>
                    
                    <?php if(!$done_pre): ?>
                        <a href="assessment.php?type=pre&activity_id=<?= $aid ?>" class="btn btn-outline-primary w-100 fw-bold shadow-sm mb-2">ทำแบบประเมินก่อนเข้าร่วม (Pre-test)</a>
                    <?php else: ?>
                        <div class="mb-2"><span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> ทำแบบประเมิน Pre-test เรียบร้อยแล้ว</span></div>
                        
                        <?php if(!$done_post): ?>
                            <a href="assessment.php?type=post&activity_id=<?= $aid ?>" class="btn btn-outline-success w-100 fw-bold shadow-sm mb-2">ทำแบบประเมินหลังเข้าร่วม (Post-test)</a>
                        <?php else: ?>
                            <div class="mb-2"><span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> ทำแบบประเมินครบถ้วนแล้ว</span></div>
                            <a href="analysis_report.php?activity_id=<?= $aid ?>" class="btn btn-info text-white w-100 mt-2 fw-bold shadow-sm"><i class="bi bi-file-earmark-bar-graph"></i> ดูรายงานวิเคราะห์จาก AI</a>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="apply">
                        <input type="hidden" name="activity_id" value="<?= $aid ?>">
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" onclick="return confirm('ยืนยันสมัครเข้าร่วมกิจกรรม?');">สมัครเข้าร่วมกิจกรรมนี้</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($activities)): ?><div class="col-12"><div class="alert alert-info border-info text-center py-5">ยังไม่มีกิจกรรมเปิดรับสมัครในขณะนี้</div></div><?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
