<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('student');
$student_id = $_SESSION['user_id'];
$activity_id = $_GET['activity_id'] ?? 0;

// Fetch activities the student has assessed
$activities = $pdo->prepare("SELECT DISTINCT a.id, a.title FROM activities a JOIN assessment_responses ar ON a.id = ar.activity_id WHERE ar.student_id = ?");
$activities->execute([$student_id]);
$activities = $activities->fetchAll();

if (!$activity_id && !empty($activities)) {
    $activity_id = $activities[0]['id'];
}

$chartData = [];
$labels = [];
$preData = [];
$postData = [];
$reports = [];

if ($activity_id) {
    // Get Pre scores
    $preScores = $pdo->prepare("SELECT s.skill_name, SUM(ar.score) as total FROM assessment_responses ar JOIN assessment_questions q ON ar.question_id = q.id JOIN soft_skills s ON q.skill_id = s.id WHERE ar.student_id = ? AND ar.activity_id = ? AND ar.type = 'pre' GROUP BY s.id ORDER BY s.id");
    $preScores->execute([$student_id, $activity_id]);
    foreach($preScores->fetchAll() as $row) {
        $labels[] = $row['skill_name'];
        $preData[] = $row['total'];
    }

    // Get Post scores
    $postScores = $pdo->prepare("SELECT s.skill_name, SUM(ar.score) as total FROM assessment_responses ar JOIN assessment_questions q ON ar.question_id = q.id JOIN soft_skills s ON q.skill_id = s.id WHERE ar.student_id = ? AND ar.activity_id = ? AND ar.type = 'post' GROUP BY s.id ORDER BY s.id");
    $postScores->execute([$student_id, $activity_id]);
    foreach($postScores->fetchAll() as $row) {
        $postData[] = $row['total'];
    }

    // Get AI Reports
    $aiSt = $pdo->prepare("SELECT type, analysis_text, recommendation_text FROM ai_reports WHERE student_id = ? AND activity_id = ?");
    $aiSt->execute([$student_id, $activity_id]);
    foreach($aiSt->fetchAll() as $r) {
        $reports[$r['type']] = $r;
    }
}

// Ensure array sizes match for ChartJS
if (empty($postData) && !empty($preData)) {
    $postData = array_fill(0, count($preData), 0);
} elseif (empty($preData) && !empty($postData)) {
    $preData = array_fill(0, count($postData), 0);
}

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-4 mt-3">
    <h3>รายงานผลวิเคราะห์ Soft Skill <span class="badge bg-secondary">Generative AI</span></h3>
</div>

<div class="card shadow-sm mb-4 border-0">
    <div class="card-body bg-light">
        <form method="GET" class="row gx-3 align-items-center">
            <div class="col-md-6">
                <label class="form-label fw-bold">เลือกกิจกรรมที่ต้องการดูรายงาน:</label>
                <select name="activity_id" class="form-select border-primary" onchange="this.form.submit()">
                    <option value="">-- กรุณาเลือก --</option>
                    <?php foreach($activities as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $activity_id == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 text-end d-none d-md-block">
                <i class="bi bi-robot text-primary" style="font-size: 2rem;"></i>
            </div>
        </form>
    </div>
</div>

<?php if($activity_id && !empty($labels)): ?>
<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-dark text-white fw-bold py-3"><i class="bi bi-graph-up"></i> แผนภูมิเปรียบเทียบทักษะ 5 ด้าน</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="radarChart" style="max-height: 400px;"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-7 mb-4">
        <div class="row h-100">
            <!-- Pre Report -->
            <?php if(isset($reports['pre'])): ?>
            <div class="col-12 mb-4">
                <div class="card shadow-sm border-info h-100">
                    <div class="card-header bg-info text-white fw-bold py-3">
                        <i class="bi bi-robot"></i> วิเคราะห์ทักษะ "ก่อนเข้าร่วมกิจกรรม" (Pre-test)
                    </div>
                    <div class="card-body bg-light">
                        <h6 class="fw-bold text-dark"><i class="bi bi-search text-primary"></i> ผลการวิเคราะห์ (Analysis)</h6>
                        <p class="text-secondary"><?= nl2br(htmlspecialchars($reports['pre']['analysis_text'])) ?></p>
                        <hr>
                        <h6 class="fw-bold text-dark"><i class="bi bi-lightbulb text-warning"></i> คำแนะนำเพื่อการพัฒนา (Recommendation)</h6>
                        <p class="text-secondary"><?= nl2br(htmlspecialchars($reports['pre']['recommendation_text'])) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Post Report -->
            <?php if(isset($reports['post'])): ?>
            <div class="col-12">
                <div class="card shadow-sm border-success h-100">
                    <div class="card-header bg-success text-white fw-bold py-3">
                        <i class="bi bi-robot"></i> วิเคราะห์พัฒนาการ "หลังเข้าร่วมกิจกรรม" (Post-test)
                    </div>
                    <div class="card-body bg-light">
                        <h6 class="fw-bold text-dark"><i class="bi bi-search text-primary"></i> ผลการวิเคราะห์ (Analysis)</h6>
                        <p class="text-secondary"><?= nl2br(htmlspecialchars($reports['post']['analysis_text'])) ?></p>
                        <hr>
                        <h6 class="fw-bold text-dark"><i class="bi bi-lightbulb text-warning"></i> คำแนะนำเพื่อการพัฒนา (Recommendation)</h6>
                        <p class="text-secondary"><?= nl2br(htmlspecialchars($reports['post']['recommendation_text'])) ?></p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="col-12">
                <div class="alert alert-warning border-warning border-2 h-100 d-flex flex-column justify-content-center align-items-center">
                    <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                    <p class="mt-2 fw-bold text-center">ท่านยังไม่ได้ทำแบบประเมินหลังเข้าร่วมกิจกรรม (Post-test)</p>
                    <a href="activities.php" class="btn btn-warning">ตรวจสอบสถานะเพื่อทำแบบประเมิน</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('radarChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'คะแนนก่อนเข้าร่วม (Pre)',
                    data: <?= json_encode($preData) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    fill: true
                },
                {
                    label: 'คะแนนหลังเข้าร่วม (Post)',
                    data: <?= json_encode($postData) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    pointBackgroundColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
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
</script>
<?php elseif(empty($labels) && $activity_id): ?>
<div class="alert alert-info py-5 text-center">ยังไม่มีข้อมูลการประเมินสำหรับกิจกรรมนี้</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
