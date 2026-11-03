<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('admin');

$fieldErrors = [];
$reopenData = [];
$reopenModal = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($action === 'add' || $action === 'edit') {
        if ($title === '') $fieldErrors['title'] = 'กรุณากรอกชื่อแบบประเมิน';
        if (empty($fieldErrors)) {
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO assessments (title, description) VALUES (?, ?)")->execute([$title, $desc]);
                log_action($pdo, 'assessment.add', $title);
                header("Location: manage_assessments.php?success=Added"); exit;
            } else {
                $pdo->prepare("UPDATE assessments SET title = ?, description = ? WHERE id = ?")->execute([$title, $desc, (int)($_POST['id'] ?? 0)]);
                log_action($pdo, 'assessment.edit', $title);
                header("Location: manage_assessments.php?success=Updated"); exit;
            }
        }
        $reopenModal = $action === 'add' ? 'addModal' : 'editModal';
        $reopenData = $_POST;
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === 1) { header("Location: manage_assessments.php?error=" . urlencode('ไม่สามารถลบแบบประเมินมาตรฐานได้ (ใช้กับแบบประเมินสมาชิกใหม่)')); exit; }
        $pdo->prepare("DELETE FROM assessments WHERE id = ?")->execute([$id]);
        log_action($pdo, 'assessment.delete', "#$id");
        header("Location: manage_assessments.php?success=Deleted"); exit;
    }
}

$assessments = $pdo->query("SELECT a.*, m.name AS manager_name, act.title AS activity_title,
        (SELECT COUNT(*) FROM assessment_questions q WHERE q.assessment_id = a.id) AS q_count
    FROM assessments a
    LEFT JOIN activity_supervisors m ON m.id = a.created_by_manager_id
    LEFT JOIN activities act ON act.id = a.activity_id")->fetchAll();

include '../includes/header.php';

function assessment_fields() { ?>
    <div class="mb-3"><label class="form-label">ชื่อแบบประเมิน</label><input type="text" name="title" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">รายละเอียด</label><textarea name="description" class="form-control" rows="3"></textarea></div>
<?php } ?>
<div class="mb-3"><a href="dashboard.php" class="btn btn-sm btn-secondary">&larr; ย้อนกลับ</a></div>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการแบบประเมิน</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่มแบบประเมิน</button>
</div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr><th>ID</th><th>ชื่อแบบประเมิน</th><th>รายละเอียด</th><th>เจ้าของ</th><th>กิจกรรม</th><th>จำนวนคำถาม</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach ($assessments as $a): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><?= htmlspecialchars($a['title']) ?></td>
                    <td><?= htmlspecialchars($a['description'] ?? '') ?></td>
                    <td><?= $a['manager_name'] ? '<span class="badge bg-warning text-dark">' . htmlspecialchars($a['manager_name']) . '</span>' : '<span class="badge bg-dark">มาตรฐาน (Admin)</span>' ?></td>
                    <td><?= $a['activity_title'] ? htmlspecialchars($a['activity_title']) : '<span class="text-muted">-</span>' ?></td>
                    <td><?= $a['q_count'] ?></td>
                    <td class="text-nowrap">
                        <a href="assessment_questions.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-info">จัดการคำถาม</a>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" data-fill="<?= htmlspecialchars(json_encode(['id' => $a['id'], 'title' => $a['title'], 'description' => $a['description']]), ENT_QUOTES) ?>">แก้ไข</button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ? คำถามทั้งหมดในแบบประเมินนี้จะถูกลบด้วย');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($assessments)): ?><tr><td colspan="7" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
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
        <h5 class="modal-title">เพิ่มแบบประเมิน</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php assessment_fields(); ?></div>
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
        <h5 class="modal-title">แก้ไขแบบประเมิน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php assessment_fields(); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button></div>
    </form>
  </div>
</div>
<?= $reopenModal ? reopen_modal_script($reopenModal, $reopenData, $fieldErrors) : '' ?>
<?php include '../includes/footer.php'; ?>
