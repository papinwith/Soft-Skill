<?php
require_once '../config/db.php';
check_login('manager');
include '../includes/header.php';

$manager_id = $_SESSION['user_id'];
$activities = $pdo->prepare("SELECT id, title FROM activities WHERE manager_id = ? ORDER BY id DESC");
$activities->execute([$manager_id]);
$activities = $activities->fetchAll();

$selected_activity = $_GET['activity_id'] ?? ($activities[0]['id'] ?? 0);

$reportData = [];
if ($selected_activity) {
    // get students who attended
    $stmt = $pdo->prepare("SELECT ap.student_id, u.name, u.student_id as student_code 
        FROM activity_participants ap JOIN users u ON ap.student_id = u.id 
        WHERE ap.activity_id = ? AND ap.status = 'attended'");
    $stmt->execute([$selected_activity]);
    $students = $stmt->fetchAll();

    foreach($students as $s) {
        $sid = $s['student_id'];
        
        // get scores per skill grouping could be better, but we do overall here
        $pre = $pdo->prepare("SELECT SUM(score) FROM assessment_responses WHERE student_id=? AND activity_id=? AND type='pre'");
        $pre->execute([$sid, $selected_activity]);
        $pre_score = (int)$pre->fetchColumn();

        $post = $pdo->prepare("SELECT SUM(score) FROM assessment_responses WHERE student_id=? AND activity_id=? AND type='post'");
        $post->execute([$sid, $selected_activity]);
        $post_score = (int)$post->fetchColumn();

        $group = 'N/A';
        if($post_score > 0) {
            if ($post_score >= 40) $group = 'ดีเยี่ยม (Excellent)';
            elseif ($post_score >= 30) $group = 'ดี (Good)';
            elseif ($post_score >= 20) $group = 'พอใช้ (Fair)';
            else $group = 'ควรปรับปรุง (Needs Improvement)';
        }

        $reportData[] = [
            'code' => $s['student_code'],
            'name' => $s['name'],
            'pre' => $pre_score,
            'post' => $post_score,
            'diff' => $post_score - $pre_score,
            'group' => $group
        ];
    }
}
?>
<div class="d-flex justify-content-between mb-3">
    <h3>ติดตามการประเมินทักษะและจำแนกกลุ่มนักศึกษา</h3>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row gx-3 gy-2 align-items-center">
            <div class="col-md-6">
                <label>เลือกกิจกรรมที่ต้องการดูรายงาน:</label>
                <select name="activity_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- เลือกกิจกรรม --</option>
                    <?php foreach($activities as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $selected_activity == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if($selected_activity): ?>
<div class="card shadow-sm">
    <div class="card-header bg-info text-white">รายงานผลการประเมิน (Pre-test และ Post-test)</div>
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>รหัสนักศึกษา</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>คะแนนก่อนร่วม (Pre)</th>
                    <th>คะแนนหลังร่วม (Post)</th>
                    <th>ความก้าวหน้า</th>
                    <th>กลุ่มประเมินผลทักษะ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($reportData as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['code']) ?></td>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td><?= $r['pre'] > 0 ? $r['pre'] : '<span class="text-muted">ยังไม่ทำ</span>' ?></td>
                    <td><?= $r['post'] > 0 ? $r['post'] : '<span class="text-muted">ยังไม่ทำ</span>' ?></td>
                    <td>
                        <?php 
                        if ($r['pre'] > 0 && $r['post'] > 0) {
                            if($r['diff'] > 0) echo '<span class="text-success"><i class="bi bi-arrow-up"></i> +'.$r['diff'].'</span>';
                            elseif($r['diff'] < 0) echo '<span class="text-danger"><i class="bi bi-arrow-down"></i> '.$r['diff'].'</span>';
                            else echo '<span class="text-secondary">เท่าเดิม</span>';
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><span class="badge bg-secondary"><?= $r['group'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($reportData)): ?><tr><td colspan="6" class="text-center">ไม่มีข้อมูลนักศึกษาที่ "เข้าร่วมแล้ว" ในกิจกรรมนี้</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
