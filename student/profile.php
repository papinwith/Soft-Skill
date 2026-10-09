<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('student');
$student_id = $_SESSION['user_id'];

$baselineCheck = $pdo->prepare("SELECT 1 FROM student_assessments WHERE student_id = ? AND type = 'baseline' LIMIT 1");
$baselineCheck->execute([$student_id]);
if (!$baselineCheck->fetchColumn() && baseline_assessment_id($pdo) !== null) {
    header('Location: assessment.php?type=baseline');
    exit;
}
// If there is no active baseline assessment to take, fall through and render the
// profile page as-is (it already handles the "no skill data yet" case below).

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate') {
    $rows = get_skill_scores($pdo, $student_id, null, 'baseline');
    if ($rows) {
        save_ai_report($pdo, $student_id, null, 'baseline', get_ai_analysis($rows, 'baseline', $_SESSION['name'], 'แบบประเมินสมาชิกใหม่'));
    }
    header("Location: profile.php?success=Regenerated"); exit;
}

$userStmt = $pdo->prepare("SELECT u.*, f.name AS faculty_name FROM students u LEFT JOIN faculties f ON u.faculty_id = f.id WHERE u.id = ?");
$userStmt->execute([$student_id]);
$user = $userStmt->fetch();

$rows = get_skill_scores($pdo, $student_id, null, 'baseline');
$labels = array_column($rows, 'skill_name');
$scoresData = array_column($rows, 'pct');

$weak = weak_skills($rows, 2);
$recommended = recommend_activities($pdo, $student_id, $weak);

include '../includes/header.php';
?>
<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="display-6 fw-bold text-primary mb-1">โปรไฟล์ของฉัน</h2>
            <p class="text-muted mb-0">ข้อมูลส่วนตัว ระดับทักษะพื้นฐาน และกิจกรรมที่แนะนำเพื่อพัฒนาทักษะที่ควรเสริม</p>
        </div>
        <div class="d-flex gap-2">
            <a href="activities.php" class="btn btn-outline-primary"><i class="bi bi-calendar-check"></i> ลงทะเบียนกิจกรรม</a>
            <a href="analysis_report.php" class="btn btn-outline-info"><i class="bi bi-bar-chart-fill"></i> รายงานวิเคราะห์จาก AI</a>
        </div>
    </div>
    <?php if (isset($_GET['success'])): ?><div class="alert alert-success">ดำเนินการเรียบร้อยแล้ว</div><?php endif; ?>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white fw-bold py-3">ข้อมูลนักศึกษา</div>
                <div class="card-body bg-light">
                    <div class="text-center mb-3">
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['name']) ?></h5>
                        <span class="badge bg-secondary">นักศึกษา</span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item bg-transparent"><strong>รหัสนักศึกษา:</strong> <?= htmlspecialchars($user['student_id'] ?: '-') ?></li>
                        <li class="list-group-item bg-transparent"><strong>คณะ:</strong> <?= htmlspecialchars($user['faculty_name'] ?: '-') ?></li>
                        <li class="list-group-item bg-transparent"><strong>สาขาวิชา:</strong> <?= htmlspecialchars($user['department'] ?: '-') ?></li>
                        <li class="list-group-item bg-transparent"><strong>ชั้นปี:</strong> <?= htmlspecialchars($user['year_level'] ?: '-') ?></li>
                        <li class="list-group-item bg-transparent"><strong>อีเมล:</strong> <?= htmlspecialchars($user['email'] ?: '-') ?></li>
                        <li class="list-group-item bg-transparent"><strong>ชื่อผู้ใช้งาน:</strong> <?= htmlspecialchars($user['username']) ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-8 mb-4">
            <?php if (!empty($labels)): ?>
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold py-3">ระดับทักษะพื้นฐาน</div>
                <div class="card-body">
                    <div class="mx-auto" style="max-width: 560px; height: 340px;">
                        <canvas id="baselineRadarChart" aria-label="แผนภูมิใยแมงมุมแสดงระดับทักษะพื้นฐาน" role="img"></canvas>
                    </div>
                    <div class="text-center"><?= skill_level_legend_html() ?></div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning h-100 d-flex align-items-center justify-content-center mb-0">ยังไม่มีข้อมูลคะแนนทักษะพื้นฐานที่แสดงผลได้</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm border-primary">
        <div class="card-header bg-primary text-white fw-bold py-3">กิจกรรมที่แนะนำสำหรับทักษะที่ควรพัฒนา</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($recommended as $r): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong><?= htmlspecialchars($r['title']) ?></strong><br>
                        <small class="text-muted">ทักษะที่ช่วยพัฒนา: <?= htmlspecialchars($r['matched_skills']) ?> | <?= htmlspecialchars($r['start_date']) ?> ถึง <?= htmlspecialchars($r['end_date']) ?></small>
                    </div>
                    <a href="activities.php" class="btn btn-sm btn-outline-primary flex-shrink-0">ดูกิจกรรม</a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($recommended)): ?>
                <li class="list-group-item text-muted">
                    <?= empty($weak) ? 'ไม่พบทักษะที่ต้องเร่งพัฒนา (คะแนนตั้งแต่ 80% ขึ้นไปทุกด้าน)' : 'ยังไม่มีกิจกรรมที่ตรงกับทักษะที่ควรพัฒนาในขณะนี้' ?>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<?php if (!empty($rows)): ?>
<form method="POST" class="profile-analysis-float">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="regenerate">
    <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow" title="เริ่มการวิเคราะห์ AI ใหม่"
        onclick="return confirm('เริ่มการวิเคราะห์ AI ใหม่หรือไม่? อาจใช้เวลาสักครู่');">
        <i class="bi bi-arrow-clockwise"></i> วิเคราะห์ใหม่ด้วย AI
    </button>
</form>
<?php endif; ?>

<script>
<?php if(!empty($labels)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('baselineRadarChart').getContext('2d');
    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'ระดับทักษะพื้นฐาน (%)',
                data: <?= json_encode($scoresData) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { r: { angleLines: { display: true }, suggestedMin: 0, suggestedMax: 100, ticks: { display: false } } },
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
