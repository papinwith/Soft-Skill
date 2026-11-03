<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('admin');

$assessment_id = (int)($_GET['id'] ?? 0);
$assessment = $pdo->prepare("SELECT * FROM assessments WHERE id = ?");
$assessment->execute([$assessment_id]);
$assessment = $assessment->fetch();

if (!$assessment) {
    render_error_page('ไม่พบแบบประเมินนี้ อาจถูกลบไปแล้ว', 'manage_assessments.php');
}

$fieldErrors = [];
$reopenData = [];
$reopenModal = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $question = trim($_POST['question_text'] ?? '');
    $skill_id = (int)($_POST['skill_id'] ?? 0);
    $sort = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) && $_POST['is_active'] === '0' ? 0 : 1;
    if ($action === 'add' || $action === 'edit') {
        if ($question === '') $fieldErrors['question_text'] = 'กรุณากรอกคำถาม';
        if (!$skill_id) $fieldErrors['skill_id'] = 'กรุณาเลือกทักษะ';
        if (empty($fieldErrors)) {
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO assessment_questions (assessment_id, skill_id, question_text, sort_order, is_active) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$assessment_id, $skill_id, $question, $sort, $active]);
                log_action($pdo, 'question.add', "assessment #$assessment_id: " . mb_substr($question, 0, 60));
                header("Location: assessment_questions.php?id=$assessment_id&success=Added"); exit;
            } else {
                $pdo->prepare("UPDATE assessment_questions SET skill_id = ?, question_text = ?, sort_order = ?, is_active = ? WHERE id = ? AND assessment_id = ?")
                    ->execute([$skill_id, $question, $sort, $active, (int)($_POST['id'] ?? 0), $assessment_id]);
                log_action($pdo, 'question.edit', "assessment #$assessment_id: " . mb_substr($question, 0, 60));
                header("Location: assessment_questions.php?id=$assessment_id&success=Updated"); exit;
            }
        }
        $reopenModal = $action === 'add' ? 'addModal' : 'editModal';
        $reopenData = $_POST;
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM assessment_questions WHERE id = ? AND assessment_id = ?")->execute([(int)($_POST['id'] ?? 0), $assessment_id]);
        log_action($pdo, 'question.delete', "assessment #$assessment_id, question #" . (int)($_POST['id'] ?? 0));
        header("Location: assessment_questions.php?id=$assessment_id&success=Deleted"); exit;
    }
}

$skills = $pdo->query("SELECT * FROM soft_skills")->fetchAll();

// Bulk add (issu.md #44): paste many questions at once, AI suggests a skill per line, admin
// reviews/corrects the guesses before anything is actually saved.
$bulkReview = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_classify') {
    $lines = array_values(array_filter(array_map('trim', explode("\n", $_POST['bulk_text'] ?? '')), fn($l) => $l !== ''));
    if ($lines) {
        $guesses = classify_questions_by_skill($lines, $skills);
        $bulkReview = array_map(fn($i, $text) => ['text' => $text, 'skill_id' => $guesses[$i] ?? null], array_keys($lines), $lines);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ai_generate') {
    // issu.md #45: let AI author brand-new questions (not just classify admin-typed ones),
    // grounded in the training dataset's style -- same review-before-save step as bulk add.
    $genSkillIds = array_map('intval', $_POST['gen_skill_id'] ?? []);
    $perSkill = max(1, min(10, (int)($_POST['gen_count'] ?? 3)));
    $existing = $pdo->query("SELECT question_text FROM assessment_questions")->fetchAll(PDO::FETCH_COLUMN);
    $bulkReview = [];
    foreach ($skills as $s) {
        if (!in_array((int)$s['id'], $genSkillIds, true)) continue;
        foreach (generate_questions_for_skill($s['skill_name'], $perSkill, $existing) as $text) {
            $bulkReview[] = ['text' => $text, 'skill_id' => (int)$s['id']];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_save') {
    $texts = $_POST['bulk_question_text'] ?? [];
    $skillIds = $_POST['bulk_skill_id'] ?? [];
    $ins = $pdo->prepare("INSERT INTO assessment_questions (assessment_id, skill_id, question_text, sort_order, is_active) VALUES (?, ?, ?, 0, 1)");
    $added = 0;
    foreach ($texts as $i => $t) {
        $t = trim($t);
        $sid = (int)($skillIds[$i] ?? 0);
        if ($t === '' || !$sid) continue;
        $ins->execute([$assessment_id, $sid, $t]);
        $added++;
    }
    if ($added > 0) {
        log_action($pdo, 'question.bulk_add', "assessment #$assessment_id: $added questions");
        header("Location: assessment_questions.php?id=$assessment_id&success=Added"); exit;
    }
}

$questions = $pdo->prepare("SELECT q.*, s.skill_name FROM assessment_questions q JOIN soft_skills s ON q.skill_id = s.id WHERE q.assessment_id = ? ORDER BY s.id, q.sort_order, q.id");
$questions->execute([$assessment_id]);
$questions = $questions->fetchAll();

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
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkModal">เพิ่มหลายข้อ (AI ช่วยจัดหมวด)</button>
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#genModal">ให้ AI แต่งคำถามใหม่</button>
    </div>
</div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div><?php endif; ?>

<?php if ($bulkReview !== null): ?>
<div class="card shadow-sm mb-4 border-primary">
    <div class="card-header bg-primary text-white">ตรวจสอบก่อนบันทึก — แก้ไขได้ก่อนบันทึกจริง</div>
    <div class="card-body">
        <?php if (empty($bulkReview)): ?>
            <p class="text-muted mb-0">ไม่พบข้อความคำถามที่จะเพิ่ม</p>
        <?php else: ?>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_save">
            <table class="table table-bordered align-middle">
                <thead class="table-light"><tr><th style="width:45%">คำถาม</th><th>ทักษะ (ตรวจสอบ/แก้ไขได้)</th></tr></thead>
                <tbody>
                    <?php foreach ($bulkReview as $r): ?>
                    <tr>
                        <td>
                            <input type="hidden" name="bulk_question_text[]" value="<?= htmlspecialchars($r['text']) ?>">
                            <?= htmlspecialchars($r['text']) ?>
                        </td>
                        <td>
                            <select name="bulk_skill_id[]" class="form-select <?= $r['skill_id'] ? '' : 'border-warning' ?>" required>
                                <option value="">-- เลือกทักษะ --</option>
                                <?php foreach ($skills as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= (int)$r['skill_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['skill_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$r['skill_id']): ?><div class="form-text text-warning">AI จัดหมวดไม่ได้ กรุณาเลือกเอง</div><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-success">บันทึกทั้งหมด (<?= count($bulkReview) ?> ข้อ)</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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
<div class="modal fade" id="bulkModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="bulk_classify">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">เพิ่มหลายข้อ (AI ช่วยจัดหมวด)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">พิมพ์คำถามหลายข้อ บรรทัดละ 1 ข้อ ระบบจะส่งให้ AI ช่วยเดาว่าแต่ละข้อเกี่ยวข้องกับทักษะไหน แล้วให้ตรวจสอบ/แก้ไขก่อนบันทึกจริงในขั้นถัดไป</p>
        <textarea name="bulk_text" class="form-control" rows="10" required placeholder="คำถามข้อที่ 1&#10;คำถามข้อที่ 2&#10;คำถามข้อที่ 3&#10;..."></textarea>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">ให้ AI จัดหมวด</button></div>
    </form>
  </div>
</div>
<div class="modal fade" id="genModal">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="ai_generate">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">ให้ AI แต่งคำถามใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">เลือกทักษะที่ต้องการให้ AI แต่งคำถามใหม่ให้ โดยยึดสไตล์จากชุดคำถามจริงที่ใช้ในระบบ จะได้คำถามให้ตรวจสอบ/แก้ไขก่อนบันทึกจริงในขั้นถัดไปเช่นกัน</p>
        <div class="mb-3">
            <label class="form-label">ทักษะที่ต้องการให้แต่งคำถาม</label>
            <?php foreach ($skills as $s): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="gen_skill_id[]" value="<?= $s['id'] ?>" id="genSkill<?= $s['id'] ?>"><label class="form-check-label" for="genSkill<?= $s['id'] ?>"><?= htmlspecialchars($s['skill_name']) ?></label></div>
            <?php endforeach; ?>
        </div>
        <div class="mb-3">
            <label class="form-label">จำนวนข้อต่อทักษะ</label>
            <input type="number" name="gen_count" class="form-control" value="3" min="1" max="10">
        </div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-success">ให้ AI แต่งคำถาม</button></div>
    </form>
  </div>
</div>
<?= $reopenModal ? reopen_modal_script($reopenModal, $reopenData, $fieldErrors) : '' ?>
<?php include '../includes/footer.php'; ?>
