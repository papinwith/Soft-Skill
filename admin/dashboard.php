<?php
require_once '../config/db.php';
check_login('admin');
include '../includes/header.php';
?>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php
// Summary queries
$userCount = $pdo->query("SELECT (SELECT COUNT(*) FROM students) + (SELECT COUNT(*) FROM activity_supervisors) + (SELECT COUNT(*) FROM admins)")->fetchColumn();
$activityCount = $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
$skillCount = $pdo->query("SELECT COUNT(*) FROM soft_skills")->fetchColumn();
$assessmentCount = $pdo->query("SELECT COUNT(*) FROM assessments")->fetchColumn();

// AI model grounding-check results from tools/run_model_evaluation.php (issu.md #40) --
// a static summary file, not a DB table, since the evaluation itself is a slow offline run
// against the real Ollama model, re-run manually after Modelfile changes.
$evalSummary = null;
$evalPath = __DIR__ . '/../model_evaluation_summary.json';
if (file_exists($evalPath)) {
    $evalSummary = json_decode(file_get_contents($evalPath), true);
}
?>
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">ผู้ใช้งานระบบ</h5>
                <h2 class="mb-0"><?= $userCount ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">กิจกรรมทั้งหมด</h5>
                <h2 class="mb-0"><?= $activityCount ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Soft Skills</h5>
                <h2 class="mb-0"><?= $skillCount ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">แบบประเมิน</h5>
                <h2 class="mb-0"><?= $assessmentCount ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header bg-dark text-white">เมนูการจัดการหลัก</div>
            <div class="card-body">
                <div class="list-group">
                    <a href="manage_users.php?role=manager" class="list-group-item list-group-item-action">จัดการผู้ดูแลกิจกรรม (Activity Managers)</a>
                    <a href="manage_users.php?role=student" class="list-group-item list-group-item-action">จัดการนักศึกษา (Students)</a>
                    <a href="manage_activities.php" class="list-group-item list-group-item-action">จัดการกิจกรรม (Activities)</a>
                    <a href="manage_departments.php" class="list-group-item list-group-item-action">จัดการคณะและสาขา (Faculties/Majors)</a>
                    <a href="manage_curricula.php" class="list-group-item list-group-item-action">จัดการหลักสูตร (Curricula)</a>
                    <a href="manage_skills.php" class="list-group-item list-group-item-action">จัดการ Soft Skills</a>
                    <a href="manage_assessments.php" class="list-group-item list-group-item-action">จัดการข้อมูลแบบประเมินและคำถาม</a>
                    <a href="reports.php" class="list-group-item list-group-item-action list-group-item-primary">ออกรายงานสรุปผลต่างๆ</a>
                    <a href="logs.php" class="list-group-item list-group-item-action">บันทึกการใช้งานระบบ (Logs)</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm" style="background:#0d1117; color:#e6edf3; border-radius: 14px;">
            <div class="card-body">
                <h5 class="fw-bold mb-3" style="color:#e6edf3;">Evaluation</h5>
                <?php if (!$evalSummary): ?>
                    <p class="small mb-0" style="color:#8b949e;">ยังไม่เคยรันการประเมินโมเดล<br>รันได้ด้วย <code style="color:#e6edf3;">php tools/run_model_evaluation.php</code></p>
                <?php else: ?>
                    <table class="table table-sm mb-2" style="color:#e6edf3; border-color:#30363d;">
                        <thead>
                            <tr style="border-bottom: 1px solid #30363d;">
                                <th style="color:#8b949e; font-weight:600; border-color:#30363d;">Benchmark</th>
                                <th style="color:#8b949e; font-weight:600; border-color:#30363d;">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evalSummary['cases'] as $c): ?>
                            <tr style="border-bottom: 1px solid #21262d;">
                                <td style="border-color:#21262d;"><?= htmlspecialchars($c['label']) ?></td>
                                <td style="border-color:#21262d; color: <?= $c['pass'] ? '#3fb950' : '#f85149' ?>; font-weight:600;"><?= $c['score_pct'] ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="small mb-1" style="color:#8b949e;">
                        รวม <?= $evalSummary['passed'] ?>/<?= $evalSummary['total'] ?> เคสผ่านทุกเกณฑ์ &middot; เฉลี่ย <?= $evalSummary['avg_time'] ?>s/เคส
                    </p>
                    <p class="small mb-2" style="color:#8b949e;">รันล่าสุด: <?= htmlspecialchars($evalSummary['generated_at']) ?></p>
                    <a href="../model_evaluation.html" class="btn btn-sm btn-outline-light" target="_blank">ดูรายงานฉบับเต็ม</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
