<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('student');
$student_id = $_SESSION['user_id'];
$activity_id = (int)($_GET['activity_id'] ?? 0);

$activities = $pdo->prepare("SELECT DISTINCT a.id, a.title FROM activities a JOIN student_assessments sa ON a.id = sa.activity_id WHERE sa.student_id = ?");
$activities->execute([$student_id]);
$activities = $activities->fetchAll();
$allowed_ids = array_map('intval', array_column($activities, 'id'));

if (!$activity_id && !empty($activities)) {
    $activity_id = (int)$activities[0]['id'];
}
if ($activity_id && !in_array($activity_id, $allowed_ids, true)) {
    $activity_id = 0;
}

$activityTitle = '';
foreach ($activities as $a) if ((int)$a['id'] === $activity_id) $activityTitle = $a['title'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate' && $activity_id) {
    $rtype = ($_POST['type'] ?? '') === 'post' ? 'post' : 'pre';
    $rows = get_skill_scores($pdo, $student_id, $activity_id, $rtype);
    if ($rows) {
        $preRows = $rtype === 'post' ? get_skill_scores($pdo, $student_id, $activity_id, 'pre') : null;
        save_ai_report($pdo, $student_id, $activity_id, $rtype, get_ai_analysis($rows, $rtype, $_SESSION['name'], $activityTitle, $preRows));
    }
    header("Location: analysis_report.php?activity_id=$activity_id&success=Regenerated"); exit;
}

$preRows = $postRows = $reports = $recommended = [];
if ($activity_id) {
    $preRows = get_skill_scores($pdo, $student_id, $activity_id, 'pre');
    $postRows = get_skill_scores($pdo, $student_id, $activity_id, 'post');

    $aiSt = $pdo->prepare("SELECT type, analysis_text, recommendation_text FROM ai_reports WHERE student_id = ? AND activity_id = ?");
    $aiSt->execute([$student_id, $activity_id]);
    foreach($aiSt->fetchAll() as $r) {
        $reports[$r['type']] = $r;
    }
    $latest = $postRows ?: $preRows;
    $recommended = recommend_activities($pdo, $student_id, weak_skills($latest, 2));
}

$labels = array_column($preRows ?: $postRows, 'skill_name');
$preData = $preRows ? array_column($preRows, 'pct') : array_fill(0, count($labels), 0);
$postData = $postRows ? array_column($postRows, 'pct') : array_fill(0, count($labels), 0);
$postByName = $preByName = [];
foreach ($postRows as $r) $postByName[$r['skill_name']] = $r;
foreach ($preRows as $r) $preByName[$r['skill_name']] = $r;

include '../includes/header.php';

function report_box($title, $class, $report, $activity_id, $type) { ?>
    <div class="card shadow-sm border-<?= $class ?> mb-4">
        <div class="card-header bg-<?= $class ?> text-white fw-bold py-3 d-flex justify-content-between align-items-center">
            <span><?= $title ?></span>
            <form method="POST" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="regenerate"><input type="hidden" name="type" value="<?= $type ?>">
                <button class="btn btn-sm btn-light" onclick="return confirm('วิเคราะห์ใหม่ด้วย AI? อาจใช้เวลาสักครู่');">วิเคราะห์ใหม่ด้วย AI</button>
            </form>
        </div>
        <div class="card-body bg-light">
            <h6 class="fw-bold text-dark">ผลการวิเคราะห์ (Analysis)</h6>
            <?php if (!$report): ?><p class="text-muted mb-0">ยังไม่มีรายงานวิเคราะห์ (ระบบ AI อาจขัดข้องระหว่างส่งแบบประเมิน) กดปุ่ม "วิเคราะห์ใหม่ด้วย AI" เพื่อสร้างรายงาน</p><?php else: ?>
            <p class="text-secondary"><?= nl2br(htmlspecialchars($report['analysis_text'] ?? '')) ?></p>
            <hr>
            <h6 class="fw-bold text-dark">คำแนะนำเพื่อการพัฒนา (Recommendation)</h6>
            <p class="text-secondary mb-0"><?= nl2br(htmlspecialchars($report['recommendation_text'] ?? '')) ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php } ?>
<div class="d-flex justify-content-between align-items-center mb-4 mt-3">
    <h3 class="mb-0">รายงานผลวิเคราะห์ Soft Skill <span class="badge bg-secondary">Generative AI</span></h3>
    <a href="activities.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> กลับ</a>
</div>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">ดำเนินการเรียบร้อยแล้ว</div><?php endif; ?>

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
        </form>
    </div>
</div>

<?php if($activity_id && !empty($labels)): ?>
<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-dark text-white fw-bold py-3">แผนภูมิเปรียบเทียบทักษะ (% คะแนน)</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="radarChart" style="max-height: 400px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold">ระดับทักษะแต่ละด้าน</div>
            <div class="card-body py-2 border-bottom"><?= skill_level_legend_html() ?></div>
            <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light"><tr><th>ทักษะ</th><th>ก่อน (Pre)</th><th>หลัง (Post)</th><th>ระดับ</th></tr></thead>
                <tbody>
                <?php foreach (($postRows ?: $preRows) as $r):
                    $pre = $preByName[$r['skill_name']] ?? null; $post = $postByName[$r['skill_name']] ?? null; ?>
                    <tr>
                        <td><?= htmlspecialchars($r['skill_name']) ?></td>
                        <td><?= $pre ? $pre['pct'] . '%' : '-' ?></td>
                        <td><?= $post ? $post['pct'] . '%' : '-' ?></td>
                        <td><span class="badge bg-<?= $r['group']['class'] ?>"><?= $r['group']['label'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <?php $weak = weak_skills($postRows ?: $preRows, 2); ?>
        <div class="card shadow-sm border-warning mb-4">
            <div class="card-header bg-warning fw-bold">จุดที่ควรพัฒนา</div>
            <div class="card-body">
                <?php foreach ($weak as $w): ?><span class="badge bg-warning text-dark me-1"><?= htmlspecialchars($w['skill_name']) ?> (<?= $w['pct'] ?>%)</span><?php endforeach; ?>
                <?php if (empty($weak)): ?><span class="text-success fw-bold">ทุกทักษะอยู่ในระดับดีเยี่ยม ไม่มีจุดที่ต้องเร่งพัฒนา</span><?php endif; ?>
            </div>
        </div>

        <?php if($preRows) report_box('วิเคราะห์ทักษะ "ก่อนเข้าร่วมกิจกรรม" (Pre-test)', 'info', $reports['pre'] ?? [], $activity_id, 'pre'); ?>
        <?php if($postRows): report_box('วิเคราะห์พัฒนาการ "หลังเข้าร่วมกิจกรรม" (Post-test)', 'success', $reports['post'] ?? [], $activity_id, 'post'); else: ?>
            <div class="alert alert-warning border-warning border-2 text-center">
                <p class="fw-bold mb-2">ท่านยังไม่ได้ทำแบบประเมินหลังเข้าร่วมกิจกรรม (Post-test)</p>
                <a href="activities.php" class="btn btn-warning">ตรวจสอบสถานะเพื่อทำแบบประเมิน</a>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white fw-bold">กิจกรรมที่แนะนำสำหรับคุณ</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($recommended as $r): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div><strong><?= htmlspecialchars($r['title']) ?></strong><br><small class="text-muted">พัฒนาทักษะ: <?= htmlspecialchars($r['matched_skills']) ?> | <?= $r['start_date'] ?> ถึง <?= $r['end_date'] ?></small></div>
                        <a href="activities.php" class="btn btn-sm btn-outline-primary">ดูกิจกรรม</a>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($recommended)): ?><li class="list-group-item text-muted">ยังไม่มีกิจกรรมที่ตรงกับทักษะที่ควรพัฒนาในขณะนี้</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('radarChart').getContext('2d');
    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'ก่อนเข้าร่วม (Pre)',
                    data: <?= json_encode($preData) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    fill: true
                },
                {
                    label: 'หลังเข้าร่วม (Post)',
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
            scales: { r: { angleLines: { display: true }, suggestedMin: 0, suggestedMax: 100, ticks: { display: false } } },
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>
<?php elseif(empty($labels) && $activity_id): ?>
<div class="alert alert-info py-5 text-center">ยังไม่มีข้อมูลการประเมินสำหรับกิจกรรมนี้</div>
<?php elseif(!$activity_id): ?>
<div class="alert alert-info py-5 text-center">ยังไม่มีข้อมูลการประเมิน กรุณาสมัครกิจกรรมและทำแบบประเมินก่อน</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
