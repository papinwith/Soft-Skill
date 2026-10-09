<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('student');
$student_id = $_SESSION['user_id'];
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : null;
$type = $_GET['type'] ?? 'pre';

if (!in_array($type, ['baseline', 'pre', 'post'], true)) die("Invalid assessment type");
if ($type !== 'baseline' && !$activity_id) die("Invalid Activity ID");

if ($type === 'baseline') {
    $check = $pdo->prepare("SELECT id FROM student_assessments WHERE student_id = ? AND type = 'baseline' LIMIT 1");
    $check->execute([$student_id]);
    if ($check->fetch()) {
        header("Location: profile.php"); exit;
    }
    $activityData = ['title' => 'แบบประเมินสำหรับสมาชิกใหม่ (Baseline Assessment)'];
    $activity_id = null;
    $assessment_id = baseline_assessment_id($pdo);
    if ($assessment_id === null) {
        header("Location: profile.php"); exit;
    }
} else {
    $act = $pdo->prepare("SELECT title FROM activities WHERE id = ?");
    $act->execute([$activity_id]);
    $activityData = $act->fetch();
    if (!$activityData) die("Activity not found.");

    $part = $pdo->prepare("SELECT status FROM activity_participants WHERE activity_id = ? AND student_id = ?");
    $part->execute([$activity_id, $student_id]);
    $status = $part->fetchColumn();
    if ($status === false) die("คุณยังไม่ได้สมัครเข้าร่วมกิจกรรมนี้");
    if ($status === 'rejected') die("คำขอเข้าร่วมกิจกรรมนี้ถูกปฏิเสธ");
    if ($type === 'post' && $status !== 'attended') {
        die("ทำแบบประเมินหลังเข้าร่วมกิจกรรมได้เมื่อผู้ดูแลกิจกรรมยืนยันว่าคุณเข้าร่วมกิจกรรมแล้ว");
    }

    $check = $pdo->prepare("SELECT id FROM student_assessments WHERE student_id = ? AND activity_id = ? AND type = ? LIMIT 1");
    $check->execute([$student_id, $activity_id, $type]);
    if ($check->fetch()) {
        header("Location: analysis_report.php?activity_id=$activity_id"); exit;
    }
    if ($type === 'post') {
        $pre = $pdo->prepare("SELECT id FROM student_assessments WHERE student_id = ? AND activity_id = ? AND type = 'pre' LIMIT 1");
        $pre->execute([$student_id, $activity_id]);
        if (!$pre->fetch()) die("กรุณาทำแบบประเมินก่อนเข้าร่วม (Pre-test) ก่อน");
    }
    $assessment_id = activity_assessment_id($pdo, $activity_id);
}

$questions = get_assessment_questions($pdo, $assessment_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scores = $_POST['score'] ?? [];
    $valid_ids = array_map('strval', array_column($questions, 'id'));
    $scores_valid = is_array($scores) && count($scores) === count($questions);
    if ($scores_valid) {
        foreach ($scores as $q_id => $score) {
            if (!in_array((string)$q_id, $valid_ids, true) || !ctype_digit((string)$score) || $score < 1 || $score > SCORE_MAX_PER_QUESTION) {
                $scores_valid = false;
                break;
            }
        }
    }
    if ($scores_valid) {
        $pdo->beginTransaction();
        $now = date('Y-m-d H:i:s');
        $sa = $pdo->prepare("INSERT INTO student_assessments (student_id, assessment_id, activity_id, type, status, started_at, submitted_at) VALUES (?, ?, ?, ?, 'submitted', ?, ?)");
        $sa->execute([$student_id, $assessment_id, $activity_id, $type, $now, $now]);
        $student_assessment_id = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO answers (student_assessment_id, question_id, score) VALUES (?, ?, ?)");
        foreach ($scores as $q_id => $score) {
            $stmt->execute([$student_assessment_id, (int)$q_id, (int)$score]);
        }
        $pdo->commit();

        $rows = get_skill_scores($pdo, $student_id, $activity_id, $type);
        $preRows = $type === 'post' ? get_skill_scores($pdo, $student_id, $activity_id, 'pre') : null;
        $aiResult = get_ai_analysis($rows, $type, $_SESSION['name'], $activityData['title'], $preRows);
        save_ai_report($pdo, $student_id, $activity_id, $type, $aiResult);

        if ($type === 'baseline') {
            header("Location: profile.php?success=baseline"); exit;
        } else {
            header("Location: analysis_report.php?activity_id=$activity_id&success=Submitted"); exit;
        }
    }
    $form_error = 'กรุณาตอบคำถามให้ครบทุกข้อ (คะแนน 1-' . SCORE_MAX_PER_QUESTION . ')';
}

include '../includes/header.php';
$backHref = $type === 'baseline' ? 'profile.php' : 'activities.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">แบบประเมินตนเอง <?php echo $type == 'pre' ? 'ก่อนเข้าร่วม (Pre-test)' : ($type == 'post' ? 'หลังเข้าร่วม (Post-test)' : 'สำหรับสมาชิกใหม่ (Baseline)'); ?></h3>
    <?php if ($type !== 'baseline'): ?>
    <a href="<?= $backHref ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> กลับ</a>
    <?php endif; ?>
</div>
<?php if (!empty($form_error)): ?><div class="alert alert-danger"><?= htmlspecialchars($form_error) ?></div><?php endif; ?>

<div class="card shadow border-0">
    <div class="card-header bg-primary text-white py-3 fw-bold fs-5">
        กิจกรรม: <?= htmlspecialchars($activityData['title']) ?>
    </div>
    <div class="card-body bg-light p-4">
        <?php if (empty($questions)): ?>
            <div class="alert alert-warning mb-0">ยังไม่มีคำถามในแบบประเมินนี้ กรุณาติดต่อผู้ดูแลระบบ</div>
        <?php else: ?>
        <form method="POST">
            <?= csrf_field() ?>
            <?php
            $currentSkill = '';
            foreach($questions as $index => $q):
                if($currentSkill !== $q['skill_name']) {
                    if($index > 0) echo "</div></div>";
                    $currentSkill = $q['skill_name'];
                    echo '<div class="card mb-4 border-0 shadow-sm"><div class="card-header bg-secondary text-white fw-bold">ด้าน: ' . htmlspecialchars($currentSkill) . '</div><div class="card-body bg-white">';
                }
            ?>
                <div class="mb-4 pb-3 border-bottom">
                    <p class="mb-3 fw-bold fs-6"><?= ($index+1) ?>. <?= htmlspecialchars($q['question_text']) ?></p>
                    <div class="d-flex justify-content-between align-items-center mt-3 px-md-5">
                        <span class="text-muted small d-none d-md-inline">น้อยที่สุด</span>
                        <?php for($i=1; $i<=SCORE_MAX_PER_QUESTION; $i++): ?>
                        <div class="form-check form-check-inline mx-auto cursor-pointer">
                            <input class="form-check-input" type="radio" name="score[<?= $q['id'] ?>]" value="<?= $i ?>" required id="q<?= $q['id'] ?>_<?= $i ?>" style="transform: scale(1.5);">
                            <label class="form-check-label ms-2 fw-bold form-text pt-1" for="q<?= $q['id'] ?>_<?= $i ?>"><?= $i ?></label>
                        </div>
                        <?php endfor; ?>
                        <span class="text-muted small d-none d-md-inline">มากที่สุด</span>
                    </div>
                </div>
            <?php endforeach; echo "</div></div>"; ?>

            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm py-3 mt-3">ส่งแบบประเมินและรับการวิเคราะห์จาก AI</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
