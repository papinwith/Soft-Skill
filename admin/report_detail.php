<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('admin');

$types = ['users' => 'รายงานข้อมูลผู้ใช้งานระบบ', 'activities' => 'รายงานข้อมูลกิจกรรม', 'softskills' => 'รายงานสรุปผล Soft Skills ของนักศึกษา'];
$type = isset($types[$_GET['type'] ?? '']) ? $_GET['type'] : 'users';

$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll();
$allActivities = $pdo->query("SELECT id, title FROM activities ORDER BY id DESC")->fetchAll();
$f_role = in_array($_GET['role'] ?? '', ['student', 'manager', 'admin'], true) ? $_GET['role'] : '';
$f_faculty = (int)($_GET['faculty_id'] ?? 0);
$f_status = in_array($_GET['status'] ?? '', ['open', 'closed'], true) ? $_GET['status'] : '';
$f_activity = (int)($_GET['activity_id'] ?? 0);

$header = [];
$rows = [];

if ($type === 'users') {
    $header = ['Username', 'ชื่อ-นามสกุล', 'บทบาท', 'รหัสนักศึกษา', 'คณะ', 'สาขา', 'ชั้นปี', 'อีเมล', 'วันที่สมัคร'];
    $sql = "SELECT * FROM (
        SELECT s.username, s.name, 'student' AS role, s.student_id, f.name AS faculty_name, s.department, s.year_level, s.email, s.created_at, s.faculty_id
            FROM students s LEFT JOIN faculties f ON s.faculty_id = f.id
        UNION ALL
        SELECT username, name, 'manager' AS role, NULL, NULL, NULL, NULL, email, created_at, NULL FROM activity_supervisors
        UNION ALL
        SELECT username, name, 'admin' AS role, NULL, NULL, NULL, NULL, NULL, created_at, NULL FROM admins
    ) u WHERE 1=1";
    $params = [];
    if ($f_role) { $sql .= " AND u.role = ?"; $params[] = $f_role; }
    if ($f_faculty) { $sql .= " AND u.faculty_id = ?"; $params[] = $f_faculty; }
    $stmt = $pdo->prepare($sql . " ORDER BY u.role, u.username");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $u) {
        $rows[] = [$u['username'], $u['name'], $u['role'], $u['student_id'], $u['faculty_name'], $u['department'], $u['year_level'], $u['email'], $u['created_at']];
    }
} elseif ($type === 'activities') {
    $header = ['กิจกรรม', 'ผู้ดูแล', 'วันที่เริ่ม', 'วันที่สิ้นสุด', 'สถานที่', 'จำนวนรับ', 'สถานะ', 'ผู้สมัคร', 'เข้าร่วมแล้ว', 'ทำ Pre', 'ทำ Post'];
    $sql = "SELECT a.*, u.name AS manager_name,
        (SELECT COUNT(*) FROM activity_participants p WHERE p.activity_id = a.id) AS applied,
        (SELECT COUNT(*) FROM activity_participants p WHERE p.activity_id = a.id AND p.status = 'attended') AS attended,
        (SELECT COUNT(*) FROM student_assessments r WHERE r.activity_id = a.id AND r.type = 'pre') AS pre_done,
        (SELECT COUNT(*) FROM student_assessments r WHERE r.activity_id = a.id AND r.type = 'post') AS post_done
        FROM activities a JOIN activity_supervisors u ON a.manager_id = u.id WHERE 1=1";
    $params = [];
    if ($f_status) { $sql .= " AND a.status = ?"; $params[] = $f_status; }
    $stmt = $pdo->prepare($sql . " ORDER BY a.id DESC");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $a) {
        $rows[] = [$a['title'], $a['manager_name'], $a['start_date'], $a['end_date'], $a['location'], $a['capacity'] ?: 'ไม่จำกัด',
            $a['status'] === 'open' ? 'เปิดรับ' : 'ปิดรับ', $a['applied'], $a['attended'], $a['pre_done'], $a['post_done']];
    }
} else {
    $header = ['รหัสนักศึกษา', 'ชื่อ-นามสกุล', 'กิจกรรม', 'ก่อนร่วม (Pre)', 'หลังร่วม (Post)', 'ผลต่าง', 'กลุ่มประเมินผลทักษะ'];
    $sql = "SELECT DISTINCT sa.student_id, sa.activity_id, u.name, u.student_id AS code, a.title
        FROM student_assessments sa JOIN students u ON sa.student_id = u.id JOIN activities a ON sa.activity_id = a.id
        WHERE sa.activity_id IS NOT NULL";
    $params = [];
    if ($f_activity) { $sql .= " AND sa.activity_id = ?"; $params[] = $f_activity; }
    $stmt = $pdo->prepare($sql . " ORDER BY a.title, u.name");
    $stmt->execute($params);
    $pairs = $stmt->fetchAll();
    $byActivity = [];
    foreach ($pairs as $r) $byActivity[$r['activity_id']][] = $r['student_id'];
    $preMaps = $postMaps = [];
    foreach ($byActivity as $aid => $sids) {
        $preMaps[$aid] = get_skill_scores_bulk($pdo, $sids, $aid, 'pre');
        $postMaps[$aid] = get_skill_scores_bulk($pdo, $sids, $aid, 'post');
    }
    foreach ($pairs as $r) {
        $pre = $preMaps[$r['activity_id']][(int)$r['student_id']] ?? [];
        $post = $postMaps[$r['activity_id']][(int)$r['student_id']] ?? [];
        $p1 = $pre ? overall_pct($pre) : null;
        $p2 = $post ? overall_pct($post) : null;
        $latest = $p2 ?? $p1;
        $rows[] = [$r['code'], $r['name'], $r['title'], $p1 === null ? '-' : $p1 . '%', $p2 === null ? '-' : $p2 . '%',
            ($p1 !== null && $p2 !== null) ? (($p2 - $p1) > 0 ? '+' : '') . ($p2 - $p1) . '%' : '-',
            $latest !== null ? skill_group($latest)['label'] : '-'];
    }
}

if (isset($_GET['export'])) {
    csv_download('admin_report_' . $type . '.csv', $header, $rows);
}

include '../includes/header.php';
$qs = $_GET; unset($qs['export']);
?>
<div class="d-flex justify-content-between mb-3 d-print-none">
    <div><a href="reports.php" class="btn btn-sm btn-secondary">&larr; ย้อนกลับ</a> <span class="h4 ms-2 align-middle"><?= htmlspecialchars($types[$type]) ?></span></div>
    <div>
        <button class="btn btn-outline-secondary" onclick="window.print()">พิมพ์รายงาน</button>
        <a class="btn btn-outline-success" href="?<?= htmlspecialchars(http_build_query($qs + ['type' => $type, 'export' => 1])) ?>">ส่งออก CSV</a>
    </div>
</div>

<div class="card shadow-sm mb-3 d-print-none">
    <div class="card-body">
        <form method="GET" class="row gx-3 gy-2 align-items-end">
            <input type="hidden" name="type" value="<?= $type ?>">
            <?php if ($type === 'users'): ?>
                <div class="col-md-4"><label>บทบาท</label>
                    <select name="role" class="form-select"><option value="">ทั้งหมด</option>
                        <?php foreach (['student' => 'นักศึกษา', 'manager' => 'ผู้ดูแลกิจกรรม', 'admin' => 'ผู้ดูแลระบบ'] as $k => $l): ?><option value="<?= $k ?>" <?= $f_role === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-4"><label>คณะ</label>
                    <select name="faculty_id" class="form-select"><option value="">ทั้งหมด</option>
                        <?php foreach ($faculties as $f): ?><option value="<?= $f['id'] ?>" <?= $f_faculty === (int)$f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option><?php endforeach; ?>
                    </select></div>
            <?php elseif ($type === 'activities'): ?>
                <div class="col-md-4"><label>สถานะรับสมัคร</label>
                    <select name="status" class="form-select"><option value="">ทั้งหมด</option><option value="open" <?= $f_status === 'open' ? 'selected' : '' ?>>เปิดรับ</option><option value="closed" <?= $f_status === 'closed' ? 'selected' : '' ?>>ปิดรับ</option></select></div>
            <?php else: ?>
                <div class="col-md-6"><label>กิจกรรม</label>
                    <select name="activity_id" class="form-select"><option value="">ทั้งหมด</option>
                        <?php foreach ($allActivities as $a): ?><option value="<?= $a['id'] ?>" <?= $f_activity === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['title']) ?></option><?php endforeach; ?>
                    </select></div>
            <?php endif; ?>
            <div class="col-md-2"><button class="btn btn-primary w-100">กรอง</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-muted small">พิมพ์เมื่อ <?= date('Y-m-d H:i') ?> | จำนวน <?= count($rows) ?> รายการ</p>
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark"><tr><?php foreach ($header as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                <?php if (empty($rows)): ?><tr><td colspan="<?= count($header) ?>" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
