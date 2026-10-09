<?php
// includes/functions.php

const SCORE_MAX_PER_QUESTION = 5;

// Shared password rule: at least 8 chars, with both a letter and a digit.
function password_strength_error($password) {
    if (strlen($password) < 8) return 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลขอย่างน้อย 1 ตัว';
    }
    return null;
}

// Shown next to any skill-level badge so the 80/60/40% cutoffs are never a mystery
function skill_level_legend_html() {
    return '<p class="small text-muted mb-0">
        เกณฑ์ระดับทักษะ: <span class="badge bg-success">ดีเยี่ยม</span> ตั้งแต่ 80% ขึ้นไป ·
        <span class="badge bg-primary">ดี</span> 60–79% ·
        <span class="badge bg-warning text-dark">พอใช้</span> 40–59% ·
        <span class="badge bg-danger">ควรปรับปรุง</span> ต่ำกว่า 40%
    </p>';
}

function skill_group($pct) {
    if ($pct >= 80) return ['label' => 'ดีเยี่ยม (Excellent)', 'class' => 'success'];
    if ($pct >= 60) return ['label' => 'ดี (Good)', 'class' => 'primary'];
    if ($pct >= 40) return ['label' => 'พอใช้ (Fair)', 'class' => 'warning'];
    return ['label' => 'ควรปรับปรุง (Needs Improvement)', 'class' => 'danger'];
}

function development_level($diffPct) {
    if ($diffPct >= 10) return ['label' => 'พัฒนาขึ้นมาก', 'class' => 'success'];
    if ($diffPct > 0) return ['label' => 'พัฒนาขึ้นเล็กน้อย', 'class' => 'info'];
    if ($diffPct == 0) return ['label' => 'เท่าเดิม', 'class' => 'secondary'];
    return ['label' => 'ลดลง', 'class' => 'danger'];
}

function finalize_skill_row($r) {
    $r['total'] = (int)$r['total'];
    $r['max_score'] = (int)$r['max_score'];
    $r['pct'] = $r['max_score'] > 0 ? round($r['total'] * 100 / $r['max_score']) : 0;
    $r['group'] = skill_group($r['pct']);
    return $r;
}

function get_skill_scores($pdo, $student_id, $activity_id, $type) {
    return get_skill_scores_bulk($pdo, [$student_id], $activity_id, $type)[$student_id] ?? [];
}

// One query for many students: returns [student_id => [skill rows]]
function get_skill_scores_bulk($pdo, $student_ids, $activity_id, $type) {
    $ids = array_values(array_unique(array_map('intval', $student_ids)));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT sa.student_id, s.id AS skill_id, s.skill_name, SUM(an.score) AS total, COUNT(*) * " . SCORE_MAX_PER_QUESTION . " AS max_score
        FROM student_assessments sa
        JOIN answers an ON an.student_assessment_id = sa.id
        JOIN assessment_questions q ON an.question_id = q.id
        JOIN soft_skills s ON q.skill_id = s.id
        WHERE sa.student_id IN ($in) AND sa.type = ? AND sa.activity_id <=> ?
        GROUP BY sa.student_id, s.id ORDER BY sa.student_id, s.id");
    $stmt->execute(array_merge($ids, [$type, $activity_id]));
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $sid = (int)$r['student_id'];
        unset($r['student_id']);
        $out[$sid][] = finalize_skill_row($r);
    }
    return $out;
}

// The three account tables that replaced the single `users` table, each as [table, role].
const ACCOUNT_TABLES = [['students', 'student'], ['activity_supervisors', 'manager'], ['admins', 'admin']];

// Looks up a login by username across all three account tables. Returns [$row, $role] or [null, null].
function find_account($pdo, $username) {
    foreach (ACCOUNT_TABLES as [$table, $role]) {
        $q = $pdo->prepare("SELECT * FROM `$table` WHERE username = ?");
        $q->execute([$username]);
        if ($row = $q->fetch()) return [$row, $role];
    }
    return [null, null];
}

// Returns an error message when the username / student ID would collide with another account.
// $exclude is [table, id] of the account being edited (so it doesn't conflict with itself), or null when adding.
function identifier_conflict($pdo, $username, $student_id, $exclude = null) {
    foreach (ACCOUNT_TABLES as [$table, ]) {
        $sql = "SELECT 1 FROM `$table` WHERE username = ?" . ($exclude && $exclude[0] === $table ? " AND id <> ?" : "") . " LIMIT 1";
        $q = $pdo->prepare($sql);
        $q->execute($exclude && $exclude[0] === $table ? [$username, $exclude[1]] : [$username]);
        if ($q->fetch()) return 'ชื่อผู้ใช้งานนี้ถูกใช้ไปแล้ว';
    }
    $sql = "SELECT 1 FROM students WHERE student_id = ?" . ($exclude && $exclude[0] === 'students' ? " AND id <> ?" : "") . " LIMIT 1";
    $q = $pdo->prepare($sql);
    $q->execute($exclude && $exclude[0] === 'students' ? [$username, $exclude[1]] : [$username]);
    if ($q->fetch()) return 'ชื่อผู้ใช้งานนี้ซ้ำกับรหัสนักศึกษาของผู้ใช้อื่น';

    if ($student_id !== null && $student_id !== '') {
        $sql = "SELECT 1 FROM students WHERE student_id = ?" . ($exclude && $exclude[0] === 'students' ? " AND id <> ?" : "") . " LIMIT 1";
        $q = $pdo->prepare($sql);
        $q->execute($exclude && $exclude[0] === 'students' ? [$student_id, $exclude[1]] : [$student_id]);
        if ($q->fetch()) return 'รหัสนักศึกษานี้ถูกใช้ไปแล้ว';

        foreach (ACCOUNT_TABLES as [$table, ]) {
            $sql = "SELECT 1 FROM `$table` WHERE username = ?" . ($exclude && $exclude[0] === $table ? " AND id <> ?" : "") . " LIMIT 1";
            $q = $pdo->prepare($sql);
            $q->execute($exclude && $exclude[0] === $table ? [$student_id, $exclude[1]] : [$student_id]);
            if ($q->fetch()) return 'รหัสนักศึกษานี้ซ้ำกับชื่อผู้ใช้งานของผู้ใช้อื่น';
        }
    }
    return null;
}

// Validates faculty/department ids. Returns [faculty_id, department_id, department_name, error]
function resolve_faculty_department($pdo, $faculty_id, $department_id) {
    if ($department_id) {
        $s = $pdo->prepare("SELECT faculty_id, name FROM departments WHERE id = ?");
        $s->execute([$department_id]);
        $d = $s->fetch();
        if (!$d) return [null, null, null, 'ไม่พบสาขาที่เลือก'];
        if ($faculty_id && (int)$faculty_id !== (int)$d['faculty_id']) return [null, null, null, 'สาขาที่เลือกไม่อยู่ในคณะนี้'];
        return [(int)$d['faculty_id'], (int)$department_id, $d['name'], null];
    }
    if ($faculty_id) {
        $s = $pdo->prepare("SELECT id FROM faculties WHERE id = ?");
        $s->execute([$faculty_id]);
        if (!$s->fetch()) return [null, null, null, 'ไม่พบคณะที่เลือก'];
        return [(int)$faculty_id, null, null, null];
    }
    return [null, null, null, null];
}

function overall_pct($rows) {
    $t = array_sum(array_column($rows, 'total'));
    $m = array_sum(array_column($rows, 'max_score'));
    return $m > 0 ? round($t * 100 / $m) : 0;
}

// Lowest-scoring skills that are still below the "Excellent" level (80%)
function weak_skills($rows, $limit = 2) {
    $rows = array_values(array_filter($rows, function ($r) { return $r['pct'] < 80; }));
    usort($rows, function ($a, $b) { return $a['pct'] <=> $b['pct']; });
    return array_slice($rows, 0, $limit);
}

function strong_skills($rows, $limit = 2) {
    usort($rows, function ($a, $b) { return $b['pct'] <=> $a['pct']; });
    return array_slice($rows, 0, $limit);
}

function activity_assessment_id($pdo, $activity_id) {
    if (!$activity_id) return 1;
    $s = $pdo->prepare("SELECT assessment_id FROM activities WHERE id = ?");
    $s->execute([$activity_id]);
    $id = $s->fetchColumn();
    return $id ? (int)$id : 1;
}

function get_assessment_questions($pdo, $assessment_id) {
    $s = $pdo->prepare("SELECT q.*, s.skill_name FROM assessment_questions q JOIN soft_skills s ON q.skill_id = s.id
        WHERE q.assessment_id = ? AND q.is_active = 1 ORDER BY s.id, q.sort_order, q.id");
    $s->execute([$assessment_id]);
    return $s->fetchAll();
}

function recommend_activities($pdo, $student_id, $weakRows, $limit = 3) {
    $ids = array_map('intval', array_column($weakRows, 'skill_id'));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT a.id, a.title, a.start_date, a.end_date, GROUP_CONCAT(DISTINCT s.skill_name SEPARATOR ', ') AS matched_skills
        FROM activities a
        JOIN activity_skills ask ON ask.activity_id = a.id
        JOIN soft_skills s ON s.id = ask.skill_id
        WHERE ask.skill_id IN ($in) AND a.status = 'open' AND a.end_date >= CURDATE()
        AND a.id NOT IN (SELECT activity_id FROM activity_participants WHERE student_id = ?)
        GROUP BY a.id ORDER BY COUNT(DISTINCT ask.skill_id) DESC, a.start_date LIMIT " . (int)$limit;
    $s = $pdo->prepare($sql);
    $s->execute(array_merge($ids, [$student_id]));
    return $s->fetchAll();
}

function rule_based_analysis($rows, $type, $studentName, $activityName, $preRows = null) {
    $overall = overall_pct($rows);
    $grp = skill_group($overall)['label'];
    $strong = strong_skills($rows, 2);
    $weak = weak_skills($rows, 2);
    if (count($rows) > 2) {
        $strongIds = array_column($strong, 'skill_id');
        $weak = array_values(array_filter($weak, function ($w) use ($strongIds) { return !in_array($w['skill_id'], $strongIds); })) ?: $weak;
    }
    $names = function ($list) { return implode(', ', array_column($list, 'skill_name')); };
    $weakText = $weak ? "ส่วนทักษะที่ควรพัฒนาเพิ่มเติมคือ " . $names($weak) : "และทุกทักษะอยู่ในระดับดีเยี่ยม";

    if ($type === 'post' && $preRows) {
        $diff = $overall - overall_pct($preRows);
        $trend = $diff > 0 ? "พัฒนาขึ้น $diff%" : ($diff < 0 ? "ลดลง " . abs($diff) . "%" : "เท่าเดิม");
        $analysis = "หลังเข้าร่วมกิจกรรม $activityName $studentName มีคะแนนภาพรวม $overall% ($grp) เมื่อเทียบกับก่อนเข้าร่วมกิจกรรมถือว่า$trend ทักษะที่โดดเด่นคือ " . $names($strong) . " $weakText";
    } else {
        $when = $type === 'baseline' ? 'ระดับพื้นฐาน' : 'ก่อนเข้าร่วมกิจกรรม ' . $activityName;
        $analysis = "จากการประเมิน$when $studentName มีคะแนนภาพรวม $overall% ($grp) ทักษะที่โดดเด่นคือ " . $names($strong) . " $weakText";
    }
    $recommendation = $weak
        ? "แนะนำให้ฝึกฝนทักษะ " . $names($weak) . " อย่างต่อเนื่อง โดยเลือกเข้าร่วมกิจกรรมที่เน้นทักษะดังกล่าวและลงมือปฏิบัติจริงกับผู้อื่น พร้อมทบทวนผลประเมินเป็นระยะเพื่อติดตามพัฒนาการ"
        : "ควรรักษาระดับทักษะที่ดีเยี่ยมนี้ต่อไป และลองรับบทบาทที่ท้าทายขึ้น เช่น เป็นผู้นำหรือช่วยแนะนำเพื่อนในกิจกรรมถัดไป";
    return ['analysis' => $analysis, 'recommendation' => $recommendation, 'source' => 'rule'];
}

// $systemPrompt is optional: pass null to use the model's own baked-in SYSTEM prompt
// (the "Soft-Skill" custom model's ./Modelfile already carries persona + output format).
function call_ollama($userPrompt, $systemPrompt = null) {
    $cfg = require __DIR__ . '/../config/ai.php';
    if (!function_exists('curl_init')) return null;
    @set_time_limit($cfg['connect_timeout'] + $cfg['timeout'] + 30);
    $payload = ['model' => $cfg['model'], 'prompt' => $userPrompt, 'stream' => false];
    if ($systemPrompt !== null) $payload['system'] = $systemPrompt;
    $ch = curl_init($cfg['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_CONNECTTIMEOUT => $cfg['connect_timeout'],
        CURLOPT_TIMEOUT => $cfg['timeout'],
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $status !== 200) return null;
    $data = json_decode($response, true);
    $text = trim($data['response'] ?? '');
    return $text !== '' ? $text : null;
}

/**
 * Analyse skill scores with the local Typhoon model served by Ollama.
 * Falls back to a rule-based analysis when Ollama isn't reachable.
 */
function get_ai_analysis($rows, $type, $studentName, $activityName, $preRows = null) {
    $fallback = rule_based_analysis($rows, $type, $studentName, $activityName, $preRows);
    if (!$rows) return $fallback;

    // Persona, tone, and the Analysis/Recommendation output format are baked into the
    // "Soft-Skill" custom model's SYSTEM prompt (see ./Modelfile) - only the data goes here.
    $typeLabel = $type === 'baseline' ? 'ระดับพื้นฐานของสมาชิกใหม่' : ($type === 'pre' ? 'ก่อนเข้าร่วมกิจกรรม' : 'หลังเข้าร่วมกิจกรรม');
    $prompt = "วิเคราะห์ผลการประเมินของนักศึกษาชื่อ $studentName ($typeLabel: $activityName)\n";
    foreach ($rows as $r) {
        $prompt .= "- {$r['skill_name']}: {$r['total']}/{$r['max_score']} ({$r['pct']}%)\n";
    }
    if ($type === 'post' && $preRows) {
        $prompt .= "ผลก่อนเข้าร่วมกิจกรรม:\n";
        foreach ($preRows as $r) $prompt .= "- {$r['skill_name']}: {$r['pct']}%\n";
    }

    $text = call_ollama($prompt);
    if ($text === null) return $fallback;

    $recPos = stripos($text, 'Recommendation:');
    if ($recPos === false) return $fallback;
    $analysis = trim(preg_replace('/^\s*Analysis:\s*/i', '', substr($text, 0, $recPos)));
    $recommendation = trim(substr($text, $recPos + strlen('Recommendation:')));
    if ($analysis === '' || $recommendation === '') return $fallback;
    return ['analysis' => $analysis, 'recommendation' => $recommendation, 'source' => 'ollama'];
}

function save_ai_report($pdo, $student_id, $activity_id, $type, $result) {
    if ($activity_id === null) {
        $pdo->prepare("DELETE FROM ai_reports WHERE student_id = ? AND type = ? AND activity_id IS NULL")->execute([$student_id, $type]);
    }
    $stmt = $pdo->prepare("INSERT INTO ai_reports (student_id, activity_id, type, analysis_text, recommendation_text) VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE analysis_text = VALUES(analysis_text), recommendation_text = VALUES(recommendation_text), created_at = CURRENT_TIMESTAMP");
    $stmt->execute([$student_id, $activity_id, $type, $result['analysis'], $result['recommendation']]);
}

/**
 * Send an email via SMTP (PHPMailer), e.g. Gmail with an App Password.
 * Returns false (and logs the reason) when SMTP isn't configured or sending fails,
 * so callers can fall back gracefully instead of fataling.
 */
function send_mail($to, $subject, $bodyText) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('send_mail: vendor/autoload.php missing - run "composer install"');
        return false;
    }
    require_once $autoload;

    $cfg = require __DIR__ . '/../config/mail.php';
    if ($cfg['username'] === '' || $cfg['password'] === '') {
        error_log('send_mail: MAIL_USERNAME/MAIL_PASSWORD are not set, skipping send to ' . $to);
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = $cfg['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['username'];
        $mail->Password = $cfg['password'];
        $mail->SMTPSecure = $cfg['encryption'] === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(false);
        $mail->Body = $bodyText;
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('send_mail failed: ' . $e->getMessage());
        return false;
    }
}

function csv_download($filename, $header, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $safe = function ($cell) {
        $cell = (string)$cell;
        if ($cell !== '' && preg_match('/^[=+\-@\t\r]/', $cell) && !preg_match('/^([+\-]?\d+(\.\d+)?%?|-)$/', $cell)) {
            return "'" . $cell;
        }
        return $cell;
    };
    fputcsv($out, array_map($safe, $header));
    foreach ($rows as $r) fputcsv($out, array_map($safe, $r));
    fclose($out);
    exit;
}
?>
