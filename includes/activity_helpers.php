<?php
// Shared by admin/manage_activities.php and manager/my_activities.php

// Returns [$data, $fieldErrors]; $fieldErrors is empty when everything validated.
function parse_activity_post() {
    $title = trim($_POST['title'] ?? '');
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $regCloseRaw = trim($_POST['registration_close_at'] ?? '');
    $regClose = null;
    $fieldErrors = [];
    if ($regCloseRaw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $regCloseRaw);
        if (!$dt || !empty(DateTime::getLastErrors()['warning_count'])) {
            $fieldErrors['registration_close_at'] = 'รูปแบบวันปิดรับสมัครไม่ถูกต้อง';
        } else {
            $regClose = $dt->format('Y-m-d H:i:00');
        }
    }
    if ($title === '') $fieldErrors['title'] = 'กรุณากรอกชื่อกิจกรรม';
    if ($start === '') $fieldErrors['start_date'] = 'กรุณาเลือกวันที่เริ่ม';
    if ($end === '') $fieldErrors['end_date'] = 'กรุณาเลือกวันที่สิ้นสุด';
    elseif ($start !== '' && $end < $start) $fieldErrors['end_date'] = 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม';
    if (!isset($fieldErrors['registration_close_at']) && $regClose !== null && $end !== '' && substr($regClose, 0, 10) > $end) {
        $fieldErrors['registration_close_at'] = 'วันปิดรับสมัครต้องไม่หลังวันที่สิ้นสุดกิจกรรม';
    }

    // The organizer's own assessment questions, typed directly into this form (issu.md #27).
    // Each question row must name the skill it measures; a row with only one of the two
    // fields filled in is treated as a mistake, not silently dropped.
    $qSkills = (array)($_POST['q_skill'] ?? []);
    $qTexts = (array)($_POST['q_text'] ?? []);
    $questions = [];
    for ($i = 0; $i < max(count($qSkills), count($qTexts)); $i++) {
        $sid = (int)($qSkills[$i] ?? 0);
        $txt = trim($qTexts[$i] ?? '');
        if ($sid === 0 && $txt === '') continue;
        if ($sid === 0 || $txt === '') { $fieldErrors['questions'] = 'กรุณาระบุทั้งทักษะและข้อความคำถามให้ครบทุกข้อ'; continue; }
        $questions[] = ['skill_id' => $sid, 'text' => $txt];
    }

    return [[
        'title' => $title,
        'description' => trim($_POST['description'] ?? ''),
        'start_date' => $start,
        'end_date' => $end,
        'registration_close_at' => $regClose,
        'location' => trim($_POST['location'] ?? '') ?: null,
        'capacity' => max(0, (int)($_POST['capacity'] ?? 0)),
        'status' => ($_POST['status'] ?? 'open') === 'closed' ? 'closed' : 'open',
        'skills' => array_map('intval', (array)($_POST['skills'] ?? [])),
        'questions' => $questions,
    ], $fieldErrors];
}

function save_activity($pdo, $data, $manager_id, $id = null) {
    if ($id === null) {
        $pdo->prepare("INSERT INTO activities (title, description, manager_id, start_date, end_date, registration_close_at, location, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$data['title'], $data['description'], $manager_id, $data['start_date'], $data['end_date'], $data['registration_close_at'], $data['location'], $data['capacity'], $data['status']]);
        $id = (int)$pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE activities SET title = ?, description = ?, start_date = ?, end_date = ?, registration_close_at = ?, location = ?, capacity = ?, status = ? WHERE id = ?")
            ->execute([$data['title'], $data['description'], $data['start_date'], $data['end_date'], $data['registration_close_at'], $data['location'], $data['capacity'], $data['status'], $id]);
    }
    $pdo->prepare("DELETE FROM activity_skills WHERE activity_id = ?")->execute([$id]);
    $ins = $pdo->prepare("INSERT IGNORE INTO activity_skills (activity_id, skill_id) SELECT ?, id FROM soft_skills WHERE id = ?");
    foreach (array_unique($data['skills']) as $sid) $ins->execute([$id, $sid]);

    // Each activity has at most one dedicated, inline-authored assessment (issu.md #27).
    // Questions entered -> create/replace it and point the activity at it. No questions
    // (or all rows cleared) -> drop any leftover dedicated assessment and fall back to the
    // standard one (activity_assessment_id() already defaults to it when assessment_id is NULL).
    $existing = $pdo->prepare("SELECT id FROM assessments WHERE activity_id = ?");
    $existing->execute([$id]);
    $existingAssessmentId = $existing->fetchColumn();

    if (!empty($data['questions'])) {
        if ($existingAssessmentId) {
            $assessmentId = (int)$existingAssessmentId;
            $pdo->prepare("DELETE FROM assessment_questions WHERE assessment_id = ?")->execute([$assessmentId]);
        } else {
            $pdo->prepare("INSERT INTO assessments (title, created_by_manager_id, activity_id) VALUES (?, ?, ?)")
                ->execute([$data['title'] . ' - แบบประเมิน', $manager_id, $id]);
            $assessmentId = (int)$pdo->lastInsertId();
        }
        $insQ = $pdo->prepare("INSERT INTO assessment_questions (assessment_id, skill_id, question_text, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
        foreach ($data['questions'] as $i => $q) $insQ->execute([$assessmentId, $q['skill_id'], $q['text'], $i]);
        $pdo->prepare("UPDATE activities SET assessment_id = ? WHERE id = ?")->execute([$assessmentId, $id]);
    } elseif ($existingAssessmentId) {
        $pdo->prepare("DELETE FROM assessments WHERE id = ?")->execute([$existingAssessmentId]);
        $pdo->prepare("UPDATE activities SET assessment_id = NULL WHERE id = ?")->execute([$id]);
    }
    return $id;
}

// Existing custom questions for an activity's dedicated assessment, if any (used to
// pre-fill the edit form's question rows). Empty array when the activity uses the
// standard assessment.
function activity_custom_questions($pdo, $activity_id) {
    $s = $pdo->prepare("SELECT q.skill_id, q.question_text AS text FROM assessment_questions q
        JOIN assessments a ON a.id = q.assessment_id WHERE a.activity_id = ? ORDER BY q.sort_order, q.id");
    $s->execute([$activity_id]);
    return $s->fetchAll();
}

// Converts a DB "Y-m-d H:i:s" value to the "Y-m-dTH:i" shape <input type=datetime-local> expects.
function datetime_local_value($dbValue) {
    return $dbValue ? str_replace(' ', 'T', substr($dbValue, 0, 16)) : '';
}

function activity_skill_map($pdo) {
    $map = [];
    foreach ($pdo->query("SELECT activity_id, skill_id FROM activity_skills")->fetchAll() as $r) {
        $map[$r['activity_id']][] = (int)$r['skill_id'];
    }
    return $map;
}

// One question row: a skill dropdown + the question text. Reused both to render the
// form's initial row and as the clone template the "+ เพิ่มคำถาม" button copies.
function question_row_html($skills, $skill_id = '', $text = '') { ob_start(); ?>
<div class="row g-2 mb-2 question-row">
    <div class="col-md-3">
        <select name="q_skill[]" class="form-select form-select-sm">
            <option value="">-- ทักษะ --</option>
            <?php foreach ($skills as $s): ?><option value="<?= $s['id'] ?>" <?= (string)$skill_id === (string)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['skill_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-8">
        <input type="text" name="q_text[]" class="form-control form-control-sm" placeholder="ข้อความคำถาม เช่น ฉันกล้าตัดสินใจในสถานการณ์ที่กดดัน" value="<?= htmlspecialchars($text) ?>">
    </div>
    <div class="col-md-1">
        <button type="button" class="btn btn-outline-danger btn-sm remove-question-row" title="ลบข้อนี้">&times;</button>
    </div>
</div>
<?php return ob_get_clean(); }

function activity_form_fields($skills) { ?>
    <div class="mb-3"><label class="form-label">ชื่อกิจกรรม</label><input type="text" name="title" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">รายละเอียด</label><textarea name="description" class="form-control" rows="3"></textarea></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">วันที่เริ่ม</label><input type="date" name="start_date" class="form-control" required></div>
        <div class="col-md-6 mb-3"><label class="form-label">วันที่สิ้นสุด</label><input type="date" name="end_date" class="form-control" required></div>
    </div>
    <div class="mb-3"><label class="form-label">วันปิดรับสมัคร <small class="text-muted">(เว้นว่าง = ไม่กำหนด)</small></label><input type="datetime-local" name="registration_close_at" class="form-control"></div>
    <div class="mb-3"><label class="form-label">สถานที่</label><input type="text" name="location" class="form-control"></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">จำนวนที่รับ <small class="text-muted">(0 = ไม่จำกัด)</small></label><input type="number" name="capacity" class="form-control" min="0" value="0"></div>
        <div class="col-md-6 mb-3"><label class="form-label">สถานะรับสมัคร</label>
            <select name="status" class="form-select"><option value="open">เปิดรับสมัคร</option><option value="closed">ปิดรับสมัคร</option></select></div>
    </div>
    <div class="mb-3 question-rows-wrap">
        <label class="form-label d-block">คำถามแบบประเมินของกิจกรรมนี้</label>
        <div class="form-text mb-2">ตั้งคำถามของกิจกรรมนี้เอง แต่ละข้อต้องระบุทักษะ (Soft Skill) ที่วัดด้วย ถ้าไม่เพิ่มคำถามเลยระบบจะใช้แบบประเมินมาตรฐาน (5 ทักษะ) แทนให้อัตโนมัติ</div>
        <div class="question-rows"><?= question_row_html($skills) ?></div>
        <button type="button" class="btn btn-sm btn-outline-primary add-question-row" data-template="<?= htmlspecialchars(question_row_html($skills), ENT_QUOTES) ?>">+ เพิ่มคำถาม</button>
    </div>
    <div class="mb-3"><label class="form-label d-block">ทักษะที่กิจกรรมนี้ช่วยพัฒนา (ใช้แนะนำกิจกรรมให้นักศึกษา)</label>
        <div class="form-text mb-2">เลือกได้หลายทักษะ เป็นคนละส่วนกับทักษะที่ตั้งคำถามด้านบน ใช้เพื่อแนะนำกิจกรรมนี้ให้นักศึกษาที่ยังขาดทักษะด้านนั้น ไม่จำเป็นต้องตรงกับจำนวน/ชนิดทักษะที่มีคำถามวัดผลจริง</div>
        <?php foreach ($skills as $s): ?>
            <label class="me-3"><input class="form-check-input" type="checkbox" name="skills[]" value="<?= $s['id'] ?>"> <?= htmlspecialchars($s['skill_name']) ?></label>
        <?php endforeach; ?>
    </div>
<?php }
