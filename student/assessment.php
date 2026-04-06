<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('student');
$student_id = $_SESSION['user_id'];
$activity_id = $_GET['activity_id'] ?? null;
$type = $_GET['type'] ?? 'pre';

if ($type !== 'baseline' && !$activity_id) die("Invalid Activity ID");

// Check if already submitted
if ($type === 'baseline') {
    $check = $pdo->prepare("SELECT id FROM assessment_responses WHERE student_id = ? AND type = 'baseline' LIMIT 1");
    $check->execute([$student_id]);
    if ($check->fetch()) {
        header("Location: dashboard.php"); exit;
    }
    $activityData = ['title' => 'แบบประเมินสำหรับสมาชิกใหม่ (Baseline Assessment)'];
    $activity_id = null;
} else {
    $check = $pdo->prepare("SELECT id FROM assessment_responses WHERE student_id = ? AND activity_id = ? AND type = ? LIMIT 1");
    $check->execute([$student_id, $activity_id, $type]);
    if ($check->fetch()) {
        header("Location: analysis_report.php?activity_id=$activity_id"); exit;
    }

    // Get Activity Name
    $act = $pdo->prepare("SELECT title FROM activities WHERE id = ?");
    $act->execute([$activity_id]);
    $activityData = $act->fetch();
    if (!$activityData) die("Activity not found.");
}

// For simplicity, grab all questions from assessment ID 1
$questions = $pdo->query("SELECT q.*, s.skill_name FROM assessment_questions q JOIN soft_skills s ON q.skill_id = s.id ORDER BY s.id, q.id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scores = $_POST['score'] ?? [];
    if (count($scores) === count($questions)) {
        // Save responses
        $stmt = $pdo->prepare("INSERT INTO assessment_responses (student_id, activity_id, type, question_id, score) VALUES (?, ?, ?, ?, ?)");
        
        $skillScores = []; // For AI analysis
        
        foreach($scores as $q_id => $score) {
            $stmt->execute([$student_id, $activity_id, $type, $q_id, $score]);
            // find skill for this q_id to sum up for AI
            foreach($questions as $q) {
                if($q['id'] == $q_id) {
                    $skillName = $q['skill_name'];
                    if(!isset($skillScores[$skillName])) $skillScores[$skillName] = 0;
                    $skillScores[$skillName] += $score;
                    break;
                }
            }
        }
        
        // Generate AI Report immediately after submission
        $aiResult = get_ai_analysis($skillScores, $type, $_SESSION['name'], $activityData['title']);
        $reportStmt = $pdo->prepare("INSERT INTO ai_reports (student_id, activity_id, type, analysis_text, recommendation_text) VALUES (?, ?, ?, ?, ?)");
        $reportStmt->execute([$student_id, $activity_id, $type, $aiResult['analysis'], $aiResult['recommendation']]);
        
        if ($type === 'baseline') {
            header("Location: dashboard.php?success=baseline"); exit;
        } else {
            header("Location: analysis_report.php?activity_id=$activity_id&success=Submitted"); exit;
        }
    }
}

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h3>แบบประเมินตนเอง <?php echo $type == 'pre' ? 'ก่อนเข้าร่วม (Pre-test)' : ($type == 'post' ? 'หลังเข้าร่วม (Post-test)' : 'สำหรับสมาชิกใหม่ (Baseline)'); ?></h3>
</div>

<div class="card shadow border-0">
    <div class="card-header bg-primary text-white py-3 fw-bold fs-5">
        กิจกรรม: <?= htmlspecialchars($activityData['title']) ?>
    </div>
    <div class="card-body bg-light p-4">
        <form method="POST">
            <?php 
            $currentSkill = '';
            foreach($questions as $index => $q): 
                if($currentSkill !== $q['skill_name']) {
                    if($index > 0) echo "</div></div>"; // close previous card
                    $currentSkill = $q['skill_name'];
                    echo '<div class="card mb-4 border-0 shadow-sm"><div class="card-header bg-secondary text-white fw-bold">ด้าน: ' . htmlspecialchars($currentSkill) . '</div><div class="card-body bg-white">';
                }
            ?>
                <div class="mb-4 pb-3 border-bottom">
                    <p class="mb-3 fw-bold fs-6"><?= ($index+1) ?>. <?= htmlspecialchars($q['question_text']) ?></p>
                    <div class="d-flex justify-content-between align-items-center mt-3 px-md-5">
                        <span class="text-muted small d-none d-md-inline">น้อยที่สุด</span>
                        <?php for($i=1; $i<=5; $i++): ?>
                        <div class="form-check form-check-inline mx-auto cursor-pointer">
                            <input class="form-check-input" type="radio" name="score[<?= $q['id'] ?>]" value="<?= $i ?>" required id="q<?= $q['id'] ?>_<?= $i ?>" style="transform: scale(1.5);">
                            <label class="form-check-label ms-2 fw-bold form-text pt-1" for="q<?= $q['id'] ?>_<?= $i ?>"><?= $i ?></label>
                        </div>
                        <?php endfor; ?>
                        <span class="text-muted small d-none d-md-inline">มากที่สุด</span>
                    </div>
                </div>
            <?php endforeach; echo "</div></div>"; ?> <!-- close last card -->
            
            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm py-3 mt-3">ส่งแบบประเมินและรับการวิเคราะห์จาก AI</button>
        </form>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
