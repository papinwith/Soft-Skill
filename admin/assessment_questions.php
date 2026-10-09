<?php
require_once '../config/db.php';
check_login('admin');

$assessment_id = (int)($_GET['id'] ?? 0);
$assessment = $pdo->prepare("SELECT * FROM assessments WHERE id = ?");
$assessment->execute([$assessment_id]);
$assessment = $assessment->fetch();

if (!$assessment) {
    die("Assessment not found");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $question = trim($_POST['question_text'] ?? '');
    $skill_id = (int)($_POST['skill_id'] ?? 0);
    $sort = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) && $_POST['is_active'] === '0' ? 0 : 1;
    if ($action === 'add' && $question !== '' && $skill_id) {
        $pdo->prepare("INSERT INTO assessment_questions (assessment_id, skill_id, question_text, sort_order, is_active) VALUES (?, ?, ?, ?, ?)")
            ->execute([$assessment_id, $skill_id, $question, $sort, $active]);
        header("Location: assessment_questions.php?id=$assessment_id&success=Added"); exit;
    } elseif ($action === 'edit' && $question !== '' && $skill_id) {
        $pdo->prepare("UPDATE assessment_questions SET skill_id = ?, question_text = ?, sort_order = ?, is_active = ? WHERE id = ? AND assessment_id = ?")
            ->execute([$skill_id, $question, $sort, $active, (int)($_POST['id'] ?? 0), $assessment_id]);
        header("Location: assessment_questions.php?id=$assessment_id&success=Updated"); exit;
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM assessment_questions WHERE id = ? AND assessment_id = ?")->execute([(int)($_POST['id'] ?? 0), $assessment_id]);
        header("Location: assessment_questions.php?id=$assessment_id&success=Deleted"); exit;
    }
    header("Location: assessment_questions.php?id=$assessment_id&error=" . urlencode('กรุณากรอกข้อมูลให้ครบ')); exit;
}

$questions = $pdo->prepare("SELECT q.*, s.skill_name FROM assessment_questions q JOIN soft_skills s ON q.skill_id = s.id WHERE q.assessment_id = ? ORDER BY s.id, q.sort_order, q.id");
$questions->execute([$assessment_id]);
$questions = $questions->fetchAll();

$skills = $pdo->query("SELECT * FROM soft_skills")->fetchAll();

include '../includes/header.php';

function question_fields($skills) { ?>
    <div class="mb-3">
        <label class="form-label">เชื่อมโยงกับทักษะ (Soft Skill)</label>
        <select name="skill_id" class="form-select" required>
            <option value="">-- เลือกทักษะ --</option>
            <?php foreach ($skills as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['skill_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3"><label class="form-label">คำถามประเมินผล</label><textarea name="question_text" class="form-control" rows="3" required></textarea></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">ลำดับข้อ</label><input type="number" name="sort_order" class="form-control" value="0"></div>
        <div class="col-md-6 mb-3"><label class="form-label">สถานะ</label>
            <select name="is_active" class="form-select"><option value="1">ใช้งาน</option><option value="0">ปิดใช้งาน</option></select></div>
    </div>
<?php } ?>
<div class="d-flex justify-content-between mb-3">
    <h3>คำถามในแบบประเมิน: <?= htmlspecialchars($assessment['title']) ?></h3>
    <div>
        <a href="manage_assessments.php" class="btn btn-secondary">กลับไปหน้าแบบประเมิน</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่มคำถาม</button>
    </div>
</div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr><th>ID</th><th>ลำดับ</th><th>ทักษะที่เกี่ยวข้อง (Soft Skill)</th><th>คำถาม</th><th>สถานะ</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $q): ?>
                <tr>
                    <td><?= $q['id'] ?></td>
                    <td><?= $q['sort_order'] ?></td>
                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($q['skill_name']) ?></span></td>
                    <td><?= htmlspecialchars($q['question_text']) ?></td>
                    <td><?= $q['is_active'] ? '<span class="badge bg-success">ใช้งาน</span>' : '<span class="badge bg-secondary">ปิด</span>' ?></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" data-fill="<?= htmlspecialchars(json_encode(['id' => $q['id'], 'skill_id' => $q['skill_id'], 'question_text' => $q['question_text'], 'sort_order' => $q['sort_order'], 'is_active' => $q['is_active']]), ENT_QUOTES) ?>">แก้ไข</button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($questions)): ?><tr><td colspan="6" class="text-center">ไม่มีคำถามในแบบประเมินนี้</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">เพิ่มคำถามประเมินผล</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php question_fields($skills); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="editModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">แก้ไขคำถาม</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php question_fields($skills); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button></div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
