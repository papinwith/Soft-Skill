<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('manager');
$manager_id = $_SESSION['user_id'];

$activities = $pdo->prepare("SELECT id, title FROM activities WHERE manager_id = ? ORDER BY id DESC");
$activities->execute([$manager_id]);
$activities = $activities->fetchAll();

$selected_activity = (int)($_GET['activity_id'] ?? ($activities[0]['id'] ?? 0));
if (!in_array($selected_activity, array_map('intval', array_column($activities, 'id')), true)) {
    $selected_activity = 0;
}
$views = [
    'compare' => 'เปรียบเทียบก่อน-หลัง',
    'trend' => 'แนวโน้มการพัฒนาทักษะ',
    'groups' => 'จำแนกกลุ่มตามระดับทักษะ',
    'summary' => 'สรุปผลวิเคราะห์ (AI)',
    'evaluation' => 'ผลการประเมินกิจกรรม',
];
$view = isset($views[$_GET['view'] ?? '']) ? $_GET['view'] : 'compare';
$activityTitle = '';
foreach ($activities as $a) if ((int)$a['id'] === $selected_activity) $activityTitle = $a['title'];

$header = [];
$tableRows = [];
$groupCounts = [];
$trendSkills = [];

if ($selected_activity) {
    $stmt = $pdo->prepare("SELECT ap.student_id, u.name, u.student_id AS student_code
        FROM activity_participants ap JOIN students u ON ap.student_id = u.id
        WHERE ap.activity_id = ? AND ap.status = 'attended' ORDER BY u.name");
    $stmt->execute([$selected_activity]);
    $students = $stmt->fetchAll();

    $data = [];
    $skillAgg = [];
    $studentIds = array_column($students, 'student_id');
    $preMap = get_skill_scores_bulk($pdo, $studentIds, $selected_activity, 'pre');
    $postMap = get_skill_scores_bulk($pdo, $studentIds, $selected_activity, 'post');
    foreach ($students as $s) {
        $pre = $preMap[(int)$s['student_id']] ?? [];
        $post = $postMap[(int)$s['student_id']] ?? [];
        $prePct = $pre ? overall_pct($pre) : null;
        $postPct = $post ? overall_pct($post) : null;
        $diff = ($prePct !== null && $postPct !== null) ? $postPct - $prePct : null;
        $latest = $postPct ?? $prePct;
        $data[] = ['s' => $s, 'pre' => $prePct, 'post' => $postPct, 'diff' => $diff,
            'group' => $latest !== null ? skill_group($latest)['label'] : 'ยังไม่ทำแบบประเมิน',
            'dev' => $diff !== null ? development_level($diff)['label'] : '-'];
        if ($pre && $post) {
            $postByName = array_column($post, null, 'skill_name');
            foreach ($pre as $r) {
                if (!isset($postByName[$r['skill_name']])) continue;
                $skillAgg[$r['skill_name']]['pre'][] = $r['pct'];
                $skillAgg[$r['skill_name']]['post'][] = $postByName[$r['skill_name']]['pct'];
            }
        }
    }
    $fmt = function ($v) { return $v === null ? '-' : $v . '%'; };

    if ($view === 'compare') {
        $header = ['รหัสนักศึกษา', 'ชื่อ-นามสกุล', 'คะแนนก่อนร่วม (Pre)', 'คะแนนหลังร่วม (Post)', 'ความก้าวหน้า', 'ระดับการพัฒนา', 'กลุ่มประเมินผลทักษะ'];
        foreach ($data as $d) {
            $tableRows[] = [$d['s']['student_code'], $d['s']['name'], $fmt($d['pre']), $fmt($d['post']),
                $d['diff'] === null ? '-' : ($d['diff'] > 0 ? '+' : '') . $d['diff'] . '%', $d['dev'], $d['group']];
        }
    } elseif ($view === 'groups') {
        $header = ['กลุ่มประเมินผลทักษะ', 'จำนวน (คน)', 'รายชื่อ'];
        $byGroup = [];
        foreach ($data as $d) $byGroup[$d['group']][] = $d['s']['name'];
        foreach (['ดีเยี่ยม (Excellent)', 'ดี (Good)', 'พอใช้ (Fair)', 'ควรปรับปรุง (Needs Improvement)', 'ยังไม่ทำแบบประเมิน'] as $g) {
            $n = count($byGroup[$g] ?? []);
            $groupCounts[$g] = $n;
            $tableRows[] = [$g, $n, implode(', ', $byGroup[$g] ?? [])];
        }
    } elseif ($view === 'trend') {
        $header = ['ทักษะ', 'ค่าเฉลี่ยก่อนร่วม (Pre)', 'ค่าเฉลี่ยหลังร่วม (Post)', 'ผลต่าง', 'ระดับการพัฒนา'];
        foreach ($skillAgg as $name => $agg) {
            $avgPre = round(array_sum($agg['pre']) / count($agg['pre']), 1);
            $avgPost = round(array_sum($agg['post']) / count($agg['post']), 1);
            $diff = round($avgPost - $avgPre, 1);
            $trendSkills[] = ['name' => $name, 'pre' => $avgPre, 'post' => $avgPost];
            $tableRows[] = [$name, $avgPre . '%', $avgPost . '%', ($diff > 0 ? '+' : '') . $diff . '%', development_level($diff)['label']];
        }
    } elseif ($view === 'summary') {
        $header = ['รหัสนักศึกษา', 'ชื่อ-นามสกุล', 'ช่วงประเมิน', 'ผลการวิเคราะห์ (AI)', 'คำแนะนำ'];
        $ai = $pdo->prepare("SELECT student_id, type, analysis_text, recommendation_text FROM ai_reports WHERE activity_id = ?");
        $ai->execute([$selected_activity]);
        $repMap = [];
        foreach ($ai->fetchAll() as $rep) $repMap[(int)$rep['student_id']][$rep['type']] = $rep;
        foreach ($data as $d) {
            $reps = $repMap[(int)$d['s']['student_id']] ?? [];
            $rep = $reps['post'] ?? $reps['pre'] ?? null;
            $tableRows[] = [$d['s']['student_code'], $d['s']['name'], isset($reps['post']) ? 'หลังเข้าร่วม' : (isset($reps['pre']) ? 'ก่อนเข้าร่วม' : '-'),
                $rep['analysis_text'] ?? '-', $rep['recommendation_text'] ?? '-'];
        }
    } elseif ($view === 'evaluation') {
        $header = ['หัวข้อ', 'ค่า'];
        $st = $pdo->prepare("SELECT status, COUNT(*) c FROM activity_participants WHERE activity_id = ? GROUP BY status");
        $st->execute([$selected_activity]);
        $counts = array_column($st->fetchAll(), 'c', 'status');
        $total = array_sum($counts);
        $preDone = $pdo->prepare("SELECT COUNT(*) FROM student_assessments WHERE activity_id = ? AND type = 'pre'");
        $preDone->execute([$selected_activity]);
        $postDone = $pdo->prepare("SELECT COUNT(*) FROM student_assessments WHERE activity_id = ? AND type = 'post'");
        $postDone->execute([$selected_activity]);
        $diffs = array_filter(array_column($data, 'diff'), function ($v) { return $v !== null; });
        $improved = count(array_filter($diffs, function ($v) { return $v > 0; }));
        $tableRows = [
            ['จำนวนผู้สมัครทั้งหมด', $total],
            ['รอยืนยัน', $counts['applied'] ?? 0],
            ['ยืนยันแล้ว (ยังไม่เช็คชื่อ)', $counts['approved'] ?? 0],
            ['เข้าร่วมแล้ว', $counts['attended'] ?? 0],
            ['ถูกปฏิเสธ', $counts['rejected'] ?? 0],
            ['ทำ Pre-test แล้ว (คน)', (int)$preDone->fetchColumn()],
            ['ทำ Post-test แล้ว (คน)', (int)$postDone->fetchColumn()],
            ['คะแนนเฉลี่ยพัฒนาการ (Post - Pre)', $diffs ? round(array_sum($diffs) / count($diffs), 1) . '%' : '-'],
            ['จำนวนผู้ที่คะแนนดีขึ้น', $improved . ' / ' . count($diffs) . ' คน'],
        ];
    }

    if (isset($_GET['export'])) {
        csv_download('report_' . $view . '_activity' . $selected_activity . '.csv', $header, $tableRows);
    }
}

include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-3 d-print-none">
    <div>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
        <h3 class="mb-0">ติดตามการประเมินทักษะและจำแนกกลุ่มนักศึกษา</h3>
    </div>
    <div>
        <button class="btn btn-outline-secondary" onclick="window.print()">พิมพ์รายงาน</button>
        <?php if ($selected_activity): ?><a class="btn btn-outline-success" href="?activity_id=<?= $selected_activity ?>&view=<?= $view ?>&export=1">ส่งออก CSV</a><?php endif; ?>
    </div>
</div>
<?php if (in_array($view, ['compare', 'groups', 'trend'], true)): ?><div class="mb-3"><?= skill_level_legend_html() ?></div><?php endif; ?>

<div class="card shadow-sm mb-4 d-print-none">
    <div class="card-body">
        <form method="GET" class="row gx-3 gy-2 align-items-end">
            <div class="col-md-6">
                <label>เลือกกิจกรรมที่ต้องการดูรายงาน:</label>
                <select name="activity_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- เลือกกิจกรรม --</option>
                    <?php foreach($activities as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $selected_activity == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label>ประเภทรายงาน:</label>
                <select name="view" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($views as $k => $label): ?><option value="<?= $k ?>" <?= $view === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if($selected_activity): ?>
<div class="card shadow-sm">
    <div class="card-header bg-info text-white"><?= htmlspecialchars($views[$view]) ?> - <?= htmlspecialchars($activityTitle) ?></div>
    <div class="card-body">
        <?php if ($view === 'groups'): ?>
            <div class="row mb-3">
                <?php foreach ($groupCounts as $g => $n): ?>
                    <div class="col-md"><div class="border rounded p-2 text-center"><div class="fs-3 fw-bold"><?= $n ?></div><small><?= htmlspecialchars($g) ?></small></div></div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($view === 'trend' && $trendSkills): ?>
            <canvas id="trendChart" style="max-height: 320px;" class="mb-3"></canvas>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                new Chart(document.getElementById('trendChart'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($trendSkills, 'name')) ?>,
                        datasets: [
                            { label: 'ก่อนร่วม (Pre) %', data: <?= json_encode(array_column($trendSkills, 'pre')) ?>, backgroundColor: 'rgba(54,162,235,0.6)' },
                            { label: 'หลังร่วม (Post) %', data: <?= json_encode(array_column($trendSkills, 'post')) ?>, backgroundColor: 'rgba(75,192,192,0.6)' }
                        ]
                    },
                    options: { scales: { y: { beginAtZero: true, max: 100 } } }
                });
            });
            </script>
        <?php endif; ?>
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark"><tr><?php foreach ($header as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
                <?php foreach ($tableRows as $row): ?>
                    <tr><?php foreach ($row as $cell): ?><td><?= nl2br(htmlspecialchars((string)$cell)) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                <?php if (empty($tableRows)): ?><tr><td colspan="<?= max(1, count($header)) ?>" class="text-center">ไม่มีข้อมูลนักศึกษาที่ "เข้าร่วมแล้ว" ในกิจกรรมนี้</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
