<?php
require_once '../config/db.php';
require_once '../includes/activity_helpers.php';
require_once '../includes/functions.php';
check_login('admin');

$fieldErrors = [];
$reopenData = [];
$reopenModal = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        [$data, $fieldErrors] = parse_activity_post();
        $manager = (int)($_POST['manager_id'] ?? 0);
        $chk = $pdo->prepare("SELECT id FROM activity_supervisors WHERE id = ?");
        $chk->execute([$manager]);
        if (!$chk->fetch()) $fieldErrors['manager_id'] = 'กรุณาเลือกผู้ดูแลกิจกรรม';
        if (empty($fieldErrors)) {
            if ($action === 'add') {
                save_activity($pdo, $data, $manager);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                save_activity($pdo, $data, $manager, $id);
                $pdo->prepare("UPDATE activities SET manager_id = ? WHERE id = ?")->execute([$manager, $id]);
            }
            log_action($pdo, $action === 'add' ? 'activity.add' : 'activity.edit', $data['title'] ?? null);
            header("Location: manage_activities.php?success=Saved"); exit;
        }
        $reopenModal = $action === 'add' ? 'addModal' : 'editModal';
        $reopenData = $_POST;
        unset($reopenData['q_skill'], $reopenData['q_text']);
        $reopenData['questions'] = $data['questions'];
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $title = $pdo->prepare("SELECT title FROM activities WHERE id = ?"); $title->execute([$id]); $title = $title->fetchColumn();
        $pdo->prepare("DELETE FROM assessments WHERE activity_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM activities WHERE id = ?")->execute([$id]);
        log_action($pdo, 'activity.delete', $title ?: "#$id");
        header("Location: manage_activities.php?success=Deleted"); exit;
    }
}

$activities = $pdo->query("SELECT a.*, u.name as manager_name FROM activities a JOIN activity_supervisors u ON a.manager_id = u.id ORDER BY a.id DESC")->fetchAll();
$managers = $pdo->query("SELECT id, name FROM activity_supervisors ORDER BY name")->fetchAll();
$skills = $pdo->query("SELECT id, skill_name FROM soft_skills ORDER BY id")->fetchAll();
$skillMap = activity_skill_map($pdo);

include '../includes/header.php';

function manager_select($managers) { ?>
    <div class="mb-3">
        <label class="form-label">ผู้ดูแลกิจกรรม</label>
        <select name="manager_id" class="form-select" required>
            <option value="">-- เลือกผู้ดูแล --</option>
            <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
<?php } ?>
<div class="mb-3"><a href="dashboard.php" class="btn btn-sm btn-secondary">&larr; ย้อนกลับ</a></div>
<div class="d-flex justify-content-between mb-3">
    <h3>จัดการกิจกรรม</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">เพิ่มกิจกรรม</button>
</div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr><th>ID</th><th>ชื่อกิจกรรม</th><th>ผู้ดูแล</th><th>เริ่ม</th><th>สิ้นสุด</th><th>สถานที่</th><th>จำนวนรับ</th><th>สถานะ</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $a):
                    $fill = ['id' => $a['id'], 'title' => $a['title'], 'description' => $a['description'], 'manager_id' => $a['manager_id'],
                        'start_date' => $a['start_date'], 'end_date' => $a['end_date'],
                        'registration_close_at' => datetime_local_value($a['registration_close_at']),
                        'location' => $a['location'], 'capacity' => $a['capacity'],
                        'status' => $a['status'], 'skills' => $skillMap[$a['id']] ?? [], 'questions' => activity_custom_questions($pdo, $a['id'])]; ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><?= htmlspecialchars($a['title']) ?></td>
                    <td><?= htmlspecialchars($a['manager_name']) ?></td>
                    <td><?= $a['start_date'] ?></td>
                    <td><?= $a['end_date'] ?></td>
                    <td><?= htmlspecialchars($a['location'] ?? '-') ?></td>
                    <td><?= $a['capacity'] > 0 ? $a['capacity'] : 'ไม่จำกัด' ?></td>
                    <td><?= $a['status'] === 'open' ? '<span class="badge bg-success">เปิดรับ</span>' : '<span class="badge bg-secondary">ปิดรับ</span>' ?></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" data-fill="<?= htmlspecialchars(json_encode($fill), ENT_QUOTES) ?>">แก้ไข</button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($activities)): ?><tr><td colspan="9" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">เพิ่มกิจกรรม</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php manager_select($managers); activity_form_fields($skills); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึก</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="editModal">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">แก้ไขกิจกรรม</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php manager_select($managers); activity_form_fields($skills); ?></div>
      <div class="modal-footer"><button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button></div>
    </form>
  </div>
</div>
<?= $reopenModal ? reopen_modal_script($reopenModal, $reopenData, $fieldErrors) : '' ?>
<?php include '../includes/footer.php'; ?>
