<?php
// Replaces the placeholder assessment questions (issu.md #19) with items grounded in
// published soft-skills literature, instead of freely invented wording. Old questions are
// soft-retired (is_active = 0) rather than deleted, so historical answers/reports tied to
// them (FK answers.question_id -> assessment_questions.id is ON DELETE CASCADE) are not lost.
// A PHP script (not .sql) because the `mysql` CLI on this machine defaults to a charset that
// mangles the Thai text in a plain .sql import; PDO here uses the same utf8mb4 connection as
// the rest of the app, which handles it correctly.
//
// References (see also assessments.description for the in-app citation):
// 1. Arratia Barrantes, S. I., Pinto Santos, H. V., Luna Carpio, J., & Alfonte Zapana, R.
//    (2023). Assessing the Soft Skills Development of Undergraduate Business Administration
//    Students. 3rd LACCEI International Multiconference on Entrepreneurship, Innovation and
//    Regional Development - LEIRD 2023. https://dx.doi.org/10.18687/LEIRD2023.1.1.281
// 2. Goleman, D. (2005). Emotional Intelligence. New York: Bantam Books.
// 3. Klein, C., DeRouin, R. E., & Salas, E. (2006). Uncovering Workplace Interpersonal
//    Skills: A Review, Framework, and Research Agenda. International Review of Industrial
//    and Organizational Psychology 2006, 79-126.
// 4. Lazarus, R. S., & Folkman, S. (1984). Stress, Appraisal, and Coping. New York: Springer.

require __DIR__ . '/config/db.php';

$already = $pdo->query("SELECT COUNT(*) FROM assessment_questions WHERE assessment_id=1 AND is_active=1 AND sort_order > 2")->fetchColumn();
if ($already > 0) {
    echo "Looks like this has already run (found " . $already . " extended-set questions). Nothing to do.\n";
    exit;
}

$pdo->exec("UPDATE assessment_questions SET is_active = 0 WHERE assessment_id = 1");

$pdo->prepare("UPDATE assessments SET description = ? WHERE id = 1")->execute([
    'แบบประเมินทักษะ Soft Skill มาตรฐานที่ครอบคลุม 5 ทักษะหลัก ข้อคำถามพัฒนาจากกรอบแนวคิดที่มีการอ้างอิง ได้แก่ Arratia Barrantes et al. (2023, LEIRD) ซึ่งอิงแบบประเมิน mindtools.com, Goleman (2005) Emotional Intelligence, Klein, DeRouin & Salas (2006) Workplace Interpersonal Skills, และ Lazarus & Folkman (1984) Stress, Appraisal, and Coping ให้คะแนนแบบ 1 (ไม่เคย) ถึง 5 (เป็นประจำ)',
]);

$questions = [
    // skill_id 1: Communication -- Goleman (2005); Arratia Barrantes et al. (2023)
    [1, 'ฉันสามารถถ่ายทอดความคิดของตนเองให้ผู้อื่นเข้าใจได้อย่างชัดเจนและตรงประเด็น'],
    [1, 'ฉันตั้งใจฟังผู้อื่นจนจบก่อนแสดงความคิดเห็นหรือตอบกลับ'],
    [1, 'ฉันปรับรูปแบบการสื่อสารให้เหมาะสมกับผู้ฟังแต่ละกลุ่ม'],
    [1, 'ฉันกล้าสอบถามเมื่อไม่เข้าใจเนื้อหาที่ผู้อื่นสื่อสารมา'],
    // skill_id 2: Teamwork -- Klein, DeRouin & Salas (2006)
    [2, 'ฉันให้ความร่วมมือกับสมาชิกในทีมเพื่อให้งานบรรลุเป้าหมายร่วมกัน'],
    [2, 'ฉันยอมรับและปรับตัวเข้ากับความคิดเห็นที่แตกต่างจากสมาชิกคนอื่นในทีม'],
    [2, 'ฉันช่วยเหลือเพื่อนร่วมทีมเมื่อพบว่าพวกเขากำลังประสบปัญหา'],
    [2, 'ฉันรับผิดชอบงานในส่วนของตนเองอย่างเต็มที่โดยไม่ต้องให้ผู้อื่นคอยตาม'],
    // skill_id 3: Problem Solving -- Arratia Barrantes et al. (2023)
    [3, 'เมื่อเจอปัญหา ฉันวิเคราะห์สาเหตุที่แท้จริงก่อนตัดสินใจแก้ไข'],
    [3, 'ฉันสามารถคิดหาทางเลือกในการแก้ปัญหาได้มากกว่าหนึ่งวิธี'],
    [3, 'ฉันประเมินข้อดีข้อเสียของแต่ละทางเลือกก่อนตัดสินใจ'],
    [3, 'ฉันสามารถแก้ปัญหาเฉพาะหน้าได้อย่างมีสติแม้ในสถานการณ์ที่ไม่คาดคิด'],
    // skill_id 4: Leadership -- Arratia Barrantes et al. (2023); Goleman (2005)
    [4, 'ฉันสามารถมอบหมายงานให้ผู้อื่นได้อย่างเหมาะสมกับความสามารถของแต่ละคน'],
    [4, 'ฉันกล้าตัดสินใจและรับผิดชอบต่อผลของการตัดสินใจนั้น'],
    [4, 'ฉันสามารถสร้างแรงจูงใจให้ผู้อื่นทำงานไปสู่เป้าหมายร่วมกันได้'],
    [4, 'ฉันรับฟังความคิดเห็นของผู้อื่นก่อนตัดสินใจในฐานะผู้นำ'],
    // skill_id 5: Working under pressure -- Lazarus & Folkman (1984)
    [5, 'ฉันยังคงทำงานได้อย่างมีคุณภาพแม้อยู่ภายใต้ข้อจำกัดด้านเวลา'],
    [5, 'ฉันสามารถควบคุมอารมณ์ของตนเองได้เมื่อเผชิญสถานการณ์กดดัน'],
    [5, 'ฉันจัดลำดับความสำคัญของงานได้อย่างเหมาะสมเมื่อมีภาระงานหลายอย่างพร้อมกัน'],
    [5, 'ฉันฟื้นตัวและกลับมาทำงานได้เป็นปกติหลังเผชิญความผิดพลาดหรือความล้มเหลว'],
];

$stmt = $pdo->prepare("INSERT INTO assessment_questions (assessment_id, skill_id, question_text, sort_order, is_active) VALUES (1, ?, ?, ?, 1)");
$sortBySkill = [];
foreach ($questions as [$skillId, $text]) {
    $sortBySkill[$skillId] = ($sortBySkill[$skillId] ?? 0) + 1;
    $stmt->execute([$skillId, $text, $sortBySkill[$skillId]]);
}

echo "Inserted " . count($questions) . " questions.\n";
