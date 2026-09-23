<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('student');
$student_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$user = $stmt->fetch();

$labels = [];
$scoresData = [];

// Get Baseline scores
$baseScores = $pdo->prepare("SELECT s.skill_name, SUM(ar.score) as total FROM assessment_responses ar JOIN assessment_questions q ON ar.question_id = q.id JOIN soft_skills s ON q.skill_id = s.id WHERE ar.student_id = ? AND ar.type = 'baseline' GROUP BY s.id ORDER BY s.id");
$baseScores->execute([$student_id]);
foreach($baseScores->fetchAll() as $row) {
    $labels[] = $row['skill_name'];
    $scoresData[] = $row['total'];
}

// Get AI Report for baseline
$aiSt = $pdo->prepare("SELECT analysis_text, recommendation_text FROM ai_reports WHERE student_id = ? AND type = 'baseline' LIMIT 1");
$aiSt->execute([$student_id]);
$report = $aiSt->fetch();

include '../includes/header.php';
?>
<div class="container py-4">
    <div class="row mb-5 align-items-center">
        <div class="col-md-8">
            <h2 class="display-5 fw-bold text-primary"><i class="bi bi-person-circle"></i> โปรไฟล์ของฉัน</h2>
            <p class="lead text-muted mt-2">ข้อมูลส่วนตัวและระดับ Soft Skill พื้นฐานของคุณ</p>
        </div>
    </div>

    <div class="row">
        <!-- ข้อมูลส่วนตัว -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white fw-bold py-3">
                    <i class="bi bi-info-circle"></i> ข้อมูลนักศึกษา
                </div>
                <div class="card-body bg-light">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-badge text-primary" style="font-size: 5rem;"></i>
                        <h4 class="mt-2 fw-bold"><?= htmlspecialchars($user['name']) ?></h4>
                        <span class="badge bg-secondary">นักศึกษา</span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item bg-transparent"><strong>รหัสนักศึกษา:</strong> <?= htmlspecialchars($user['student_id'] ?: '-') ?></li>
                        <li class="list-group-item bg-transparent"><strong>สาขาวิชา:</strong> <?= htmlspecialchars($user['department'] ?: '-') ?></li>
                        <li class="list-group-item bg-transparent"><strong>อีเมล:</strong> <?= htmlspecialchars($user['email'] ?: '-') ?></li>
                        <li class="list-group-item bg-transparent"><strong>ชื่อผู้ใช้งาน:</strong> <?= htmlspecialchars($user['username']) ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- แผนภูมิ Soft Skill -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold py-3">
                    <i class="bi bi-graph-up"></i> ระดับทักษะพื้นฐาน (Baseline Soft Skills)
                </div>
                <div class="card-body d-flex flex-column">
                    <?php if(!empty($labels)): ?>
                        <div class="flex-grow-1 d-flex align-items-center justify-content-center" style="min-height: 300px;">
                            <canvas id="baselineRadarChart" style="max-height: 350px;"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center my-auto">
                            <i class="bi bi-exclamation-triangle-fill fs-1 text-warning mb-2"></i><br>
                            ยังไม่มีข้อมูลการประเมินทักษะพื้นฐาน
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- รายงาน AI -->
        <?php if($report): ?>
        <div class="col-12 mt-2">
            <div class="card shadow-sm border-info">
                <div class="card-header bg-info text-white fw-bold py-3">
                    <i class="bi bi-robot"></i> วิเคราะห์ทักษะโดย AI (จากแบบประเมินเริ่มต้น)
                </div>
                <div class="card-body bg-light row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <h6 class="fw-bold text-dark"><i class="bi bi-search text-primary"></i> ผลการวิเคราะห์ (Analysis)</h6>
                        <p class="text-secondary"><?= nl2br(htmlspecialchars($report['analysis_text'])) ?></p>
                    </div>
                    <div class="col-md-6 border-start border-2 border-white">
                        <h6 class="fw-bold text-dark"><i class="bi bi-lightbulb text-warning"></i> คำแนะนำเพื่อการพัฒนา (Recommendation)</h6>
                        <p class="text-secondary"><?= nl2br(htmlspecialchars($report['recommendation_text'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
<?php if(!empty($labels)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('baselineRadarChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'คะแนนการประเมินพื้นฐาน',
                    data: <?= json_encode($scoresData) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    angleLines: { display: true },
                    suggestedMin: 0,
                    suggestedMax: 10,
                    ticks: { display:false }
                }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
