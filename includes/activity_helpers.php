<?php
// Shared by admin/manage_activities.php and manager/my_activities.php

function parse_activity_post() {
    $title = trim($_POST['title'] ?? '');
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $error = null;
    if ($title === '' || $start === '' || $end === '') $error = 'กรุณากรอกข้อมูลให้ครบ';
    elseif ($end < $start) $error = 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม';
    return [[
        'title' => $title,
        'description' => trim($_POST['description'] ?? ''),
        'start_date' => $start,
        'end_date' => $end,
        'location' => trim($_POST['location'] ?? '') ?: null,
        'capacity' => max(0, (int)($_POST['capacity'] ?? 0)),
        'status' => ($_POST['status'] ?? 'open') === 'closed' ? 'closed' : 'open',
        'assessment_id' => ($_POST['assessment_id'] ?? '') === '' ? null : (int)$_POST['assessment_id'],
        'skills' => array_map('intval', (array)($_POST['skills'] ?? [])),
    ], $error];
}

function save_activity($pdo, $data, $manager_id, $id = null) {
    if ($id === null) {
        $pdo->prepare("INSERT INTO activities (title, description, manager_id, start_date, end_date, location, capacity, status, assessment_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$data['title'], $data['description'], $manager_id, $data['start_date'], $data['end_date'], $data['location'], $data['capacity'], $data['status'], $data['assessment_id']]);
        $id = (int)$pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE activities SET title = ?, description = ?, start_date = ?, end_date = ?, location = ?, capacity = ?, status = ?, assessment_id = ? WHERE id = ?")
            ->execute([$data['title'], $data['description'], $data['start_date'], $data['end_date'], $data['location'], $data['capacity'], $data['status'], $data['assessment_id'], $id]);
    }
    $pdo->prepare("DELETE FROM activity_skills WHERE activity_id = ?")->execute([$id]);
    $ins = $pdo->prepare("INSERT IGNORE INTO activity_skills (activity_id, skill_id) SELECT ?, id FROM soft_skills WHERE id = ?");
    foreach (array_unique($data['skills']) as $sid) $ins->execute([$id, $sid]);
    return $id;
}

function activity_skill_map($pdo) {
    $map = [];
    foreach ($pdo->query("SELECT activity_id, skill_id FROM activity_skills")->fetchAll() as $r) {
        $map[$r['activity_id']][] = (int)$r['skill_id'];
    }
    return $map;
}

function activity_form_fields($assessments, $skills) { ?>
    <div class="mb-3"><label class="form-label">ชื่อกิจกรรม</label><input type="text" name="title" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">รายละเอียด</label><textarea name="description" class="form-control" rows="3"></textarea></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">วันที่เริ่ม</label><input type="date" name="start_date" class="form-control" required></div>
        <div class="col-md-6 mb-3"><label class="form-label">วันที่สิ้นสุด</label><input type="date" name="end_date" class="form-control" required></div>
    </div>
    <div class="mb-3"><label class="form-label">สถานที่</label><input type="text" name="location" class="form-control"></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">จำนวนที่รับ <small class="text-muted">(0 = ไม่จำกัด)</small></label><input type="number" name="capacity" class="form-control" min="0" value="0"></div>
        <div class="col-md-6 mb-3"><label class="form-label">สถานะรับสมัคร</label>
            <select name="status" class="form-select"><option value="open">เปิดรับสมัคร</option><option value="closed">ปิดรับสมัคร</option></select></div>
    </div>
    <div class="mb-3"><label class="form-label">แบบประเมินที่ใช้</label>
        <select name="assessment_id" class="form-select">
            <option value="">ค่าเริ่มต้น (แบบประเมินมาตรฐาน)</option>
            <?php foreach ($assessments as $a): ?><option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['title']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="mb-3"><label class="form-label d-block">ทักษะที่กิจกรรมนี้ช่วยพัฒนา (ใช้แนะนำกิจกรรมให้นักศึกษา)</label>
        <?php foreach ($skills as $s): ?>
            <label class="me-3"><input class="form-check-input" type="checkbox" name="skills[]" value="<?= $s['id'] ?>"> <?= htmlspecialchars($s['skill_name']) ?></label>
        <?php endforeach; ?>
    </div>
<?php }
