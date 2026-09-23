<?php
require_once '../config/db.php';
check_login('admin');

$assessment_id = $_GET['id'] ?? 0;
$assessment = $pdo->prepare("SELECT * FROM assessments WHERE id = ?");
$assessment->execute([$assessment_id]);
$assessment = $assessment->fetch();

if (!$assessment) {
    die("Assessment not found");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'add') {
        $skill_id = $_POST['skill_id'];
        $question = $_POST['question_text'];
        $stmt = $pdo->prepare("INSERT INTO assessment_questions (assessment_id, skill_id, question_text) VALUES (?, ?, ?)");
        $stmt->execute([$assessment_id, $skill_id, $question]);
        header("Location: assessment_questions.php?id=$assessment_id&success=Added"); exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM assessment_questions WHERE id = ?")->execute([$id]);
        header("Location: assessment_questions.php?id=$assessment_id&success=Deleted"); exit;
    }
}

$questions = $pdo->prepare("SELECT q.*, s.skill_name FROM assessment_questions q JOIN soft_skills s ON q.skill_id = s.id WHERE q.assessment_id = ? ORDER BY s.id, q.id");
$questions->execute([$assessment_id]);
$questions = $questions->fetchAll();

$skills = $pdo->query("SELECT * FROM soft_skills")->fetchAll();

include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h3>คำถามในแบบประเมิน: <?= htmlspecialchars($assessment['title']) ?></h3>
    <div>
        <a href="manage_assessments.php" class="btn btn-secondary">กลับไปหน้าแบบประเมิน</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่มคำถาม</button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr><th>ID</th><th>ทักษะที่เกี่ยวข้อง (Soft Skill)</th><th>คำถาม</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach($questions as $q): ?>
                <tr>
                    <td><?= $q['id'] ?></td>
                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($q['skill_name']) ?></span></td>
                    <td><?= htmlspecialchars($q['question_text']) ?></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($questions)): ?><tr><td colspan="4" class="text-center">ไม่มีคำถามในแบบประเมินนี้</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">เพิ่มคำถามประเมินผล</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">เชื่อมโยงกับทักษะ (Soft Skill)</label>
            <select name="skill_id" class="form-control" required>
                <option value="">-- เลือกทักษะ --</option>
                <?php foreach($skills as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['skill_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">คำถามประเมินผล</label><textarea name="question_text" class="form-control" rows="3" required></textarea></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
