<?php
// CLI tool: exercises the real "Soft-Skill" Ollama model (not the rule-based fallback)
// against a fixed battery of synthetic score profiles and renders the results as a
// standalone HTML report (model_evaluation.html, project root) — separate from the live
// app, so it can be re-run after any Modelfile change to confirm the model still follows
// the grounding rules it's required to (issu.md #24/#25/#27).
//
// Usage: php tools/run_model_evaluation.php
//
// No DB connection needed: get_ai_analysis() only needs synthetic skill-score rows shaped
// like finalize_skill_row() output, and talks to Ollama directly via config/ai.php.

require_once __DIR__ . '/../includes/functions.php';

$skillIdByName = ['Communication' => 1, 'Teamwork' => 2, 'Problem Solving' => 3, 'Leadership' => 4, 'Working under pressure' => 5];
function row($skill_name, $total, $max_score) {
    global $skillIdByName;
    return finalize_skill_row(['skill_id' => $skillIdByName[$skill_name] ?? 0, 'skill_name' => $skill_name, 'total' => $total, 'max_score' => $max_score]);
}

// Each test case: rows (+ optional preRows for 'post'), type, and a human label.
// Expectations (expected strong/weak skill names) are derived from the SAME strong_skills()/
// weak_skills() functions the app uses, not re-typed by hand, so the test can't drift from
// the real grounding rule it's checking.
$cases = [
    [
        'label' => 'คะแนนสูงทุกด้าน (ทุกทักษะ >= 80%)',
        'type' => 'pre', 'activity' => 'ค่ายผู้นำนักศึกษา',
        'rows' => [row('Communication', 18, 20), row('Teamwork', 17, 20), row('Problem Solving', 19, 20), row('Leadership', 16, 20), row('Working under pressure', 17, 20)],
    ],
    [
        'label' => 'คะแนนต่ำทุกด้าน (ทุกทักษะ < 60%)',
        'type' => 'pre', 'activity' => 'ค่ายผู้นำนักศึกษา',
        'rows' => [row('Communication', 5, 20), row('Teamwork', 6, 20), row('Problem Solving', 4, 20), row('Leadership', 7, 20), row('Working under pressure', 5, 20)],
    ],
    [
        'label' => 'คะแนนผสม (มีทั้งเด่นและอ่อน)',
        'type' => 'pre', 'activity' => 'อบรมการสื่อสารเชิงบวก',
        'rows' => [row('Communication', 17, 20), row('Teamwork', 10, 20), row('Problem Solving', 13, 20), row('Leadership', 8, 20), row('Working under pressure', 15, 20)],
    ],
    [
        'label' => 'ทักษะเด่นเพียงด้านเดียว (ต้องไม่ยกอีกด้านขึ้นมาแทน)',
        'type' => 'pre', 'activity' => 'กิจกรรมจิตอาสา',
        'rows' => [row('Communication', 19, 20), row('Teamwork', 8, 20), row('Problem Solving', 7, 20), row('Leadership', 6, 20), row('Working under pressure', 9, 20)],
    ],
    [
        'label' => 'ขอบเขตพอดี 60% (ต้องนับเป็นโดดเด่น)',
        'type' => 'pre', 'activity' => 'ทดสอบขอบเขตคะแนน',
        'rows' => [row('Communication', 12, 20), row('Teamwork', 9, 20), row('Problem Solving', 9, 20), row('Leadership', 9, 20), row('Working under pressure', 9, 20)],
    ],
    [
        // issu.md #27: activity with its own custom question set covering only 2 of the 5
        // skills (fewer rows than the standard assessment) - the model must not invent
        // data for the missing skills or assume the standard 20-question set was used.
        'label' => 'กิจกรรมใช้แบบประเมินที่ผู้จัดตั้งเอง มีแค่ 2 ทักษะ (issu.md #27)',
        'type' => 'pre', 'activity' => 'เวิร์กชอปภาวะผู้นำ (คำถามกำหนดเอง)',
        'rows' => [row('Teamwork', 17, 20), row('Leadership', 9, 20)],
    ],
    [
        'label' => 'Baseline สมาชิกใหม่',
        'type' => 'baseline', 'activity' => 'แบบประเมินสำหรับสมาชิกใหม่',
        'rows' => [row('Communication', 11, 20), row('Teamwork', 14, 20), row('Problem Solving', 10, 20), row('Leadership', 8, 20), row('Working under pressure', 12, 20)],
    ],
    [
        'label' => 'Post-test พร้อมเทียบผลก่อนเข้าร่วม (มีพัฒนาการชัดเจน)',
        'type' => 'post', 'activity' => 'ค่ายผู้นำนักศึกษา',
        'rows' => [row('Communication', 17, 20), row('Teamwork', 16, 20), row('Problem Solving', 15, 20), row('Leadership', 14, 20), row('Working under pressure', 16, 20)],
        'preRows' => [row('Communication', 10, 20), row('Teamwork', 9, 20), row('Problem Solving', 8, 20), row('Leadership', 7, 20), row('Working under pressure', 9, 20)],
    ],
];

$results = [];
foreach ($cases as $c) {
    $expectedStrong = array_column(strong_skills($c['rows'], 2), 'skill_name');
    $expectedWeak = array_column(weak_skills($c['rows'], 2), 'skill_name');
    $overall = overall_pct($c['rows']);

    $t0 = microtime(true);
    $out = get_ai_analysis($c['rows'], $c['type'], 'นักศึกษาทดสอบ', $c['activity'], $c['preRows'] ?? null);
    $elapsed = round(microtime(true) - $t0, 1);

    $checks = [];
    $checks['ใช้โมเดลจริง (ไม่ fallback)'] = ($out['source'] ?? '') === 'ollama';
    if ($expectedStrong) {
        foreach ($expectedStrong as $name) {
            $checks["กล่าวถึงทักษะโดดเด่นที่ถูกต้อง: $name"] = strpos($out['analysis'], $name) !== false;
        }
    } else {
        $checks['ไม่มีทักษะโดดเด่น -> ต้องใช้ประโยคที่กำหนดตรงๆ'] = strpos($out['analysis'], 'ยังไม่มีทักษะด้านใดอยู่ในระดับโดดเด่นเป็นพิเศษ') !== false;
    }
    // Anti-hallucination: a skill explicitly flagged "มีทักษะ X ที่โดดเด่น" / "ทักษะที่โดดเด่น คือ X"
    // must be one of the expected ones. (A plain proximity check was tried first and threw
    // false positives on correct contrastive phrasing like "...ขณะที่ Leadership เป็นทักษะที่
    // ควรพัฒนา", where "โดดเด่น" from the PRECEDING clause about a different, correct skill
    // just happened to sit close by - so this matches the actual "X ที่โดดเด่น" construction
    // instead of raw character distance.)
    $claimedChunks = [];
    if (preg_match_all('/([ก-๙A-Za-z ]+?)(?:ที่โดดเด่น|เป็นทักษะที่โดดเด่น)/u', $out['analysis'], $m)) {
        $claimedChunks = array_merge($claimedChunks, $m[1]);
    }
    if (preg_match('/โดดเด่น[^ก-๙A-Za-z]{0,10}คือ\s*([ก-๙A-Za-z ]+)/u', $out['analysis'], $m2)) {
        $claimedChunks[] = $m2[1];
    }
    foreach ($claimedChunks as $claimed) {
        foreach (array_column($c['rows'], 'skill_name') as $name) {
            if (mb_stripos($claimed, $name) === false) continue;
            $key = "ไม่เรียก \"$name\" ว่าโดดเด่นทั้งที่ไม่ถึงเกณฑ์";
            $checks[$key] = ($checks[$key] ?? true) && in_array($name, $expectedStrong, true);
        }
    }
    $checks['มีคำแนะนำ (Recommendation) ไม่ว่างเปล่า'] = trim($out['recommendation'] ?? '') !== '';

    $results[] = [
        'label' => $c['label'],
        'type' => $c['type'],
        'rows' => $c['rows'],
        'overall' => $overall,
        'expectedStrong' => $expectedStrong,
        'expectedWeak' => $expectedWeak,
        'analysis' => $out['analysis'] ?? '',
        'recommendation' => $out['recommendation'] ?? '',
        'source' => $out['source'] ?? 'rule_based',
        'elapsed' => $elapsed,
        'checks' => $checks,
        'allPass' => !in_array(false, $checks, true),
    ];
    fwrite(STDERR, "done: {$c['label']} ({$elapsed}s, source={$out['source']})\n");
}

$total = count($results);
$passed = count(array_filter($results, fn($r) => $r['allPass']));
$avgTime = $total ? round(array_sum(array_column($results, 'elapsed')) / $total, 1) : 0;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

ob_start();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>รายงานผลการประเมินโมเดล AI (Soft-Skill)</title>
<style>
  :root { --ok:#1a7f37; --bad:#c0392b; --bg:#f6f7f9; --card:#ffffff; --border:#e1e4e8; --text:#1b1f23; --muted:#6a737d; }
  * { box-sizing: border-box; }
  body { font-family: "Segoe UI", "Noto Sans Thai", sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 24px; line-height: 1.6; }
  h1 { font-size: 1.5rem; margin: 0 0 4px; }
  .sub { color: var(--muted); margin-bottom: 20px; font-size: .9rem; }
  .summary { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
  .stat { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 14px 20px; min-width: 150px; }
  .stat .n { font-size: 1.6rem; font-weight: 700; }
  .stat .l { color: var(--muted); font-size: .8rem; }
  .case { background: var(--card); border: 1px solid var(--border); border-radius: 10px; margin-bottom: 18px; overflow: hidden; }
  .case-head { padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
  .case-head h3 { margin: 0; font-size: 1rem; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .78rem; font-weight: 600; color: #fff; }
  .badge.pass { background: var(--ok); }
  .badge.fail { background: var(--bad); }
  .badge.fallback { background: #b58105; }
  .case-body { padding: 0 18px 18px; display: none; }
  .case.open .case-body { display: block; }
  table { border-collapse: collapse; width: 100%; margin: 8px 0; font-size: .88rem; }
  th, td { border: 1px solid var(--border); padding: 6px 10px; text-align: left; }
  th { background: #f0f2f4; }
  .checks li { margin-bottom: 4px; }
  .checks .ok::before { content: "✓ "; color: var(--ok); font-weight: 700; }
  .checks .no::before { content: "✗ "; color: var(--bad); font-weight: 700; }
  .ai-block { background: #f0f7ff; border: 1px solid #cfe3ff; border-radius: 8px; padding: 10px 14px; margin: 8px 0; white-space: pre-wrap; font-size: .9rem; }
  .meta { color: var(--muted); font-size: .8rem; margin-top: 4px; }
</style>
</head>
<body>
<h1>รายงานผลการประเมินโมเดล AI (Soft-Skill)</h1>
<div class="sub">สร้างเมื่อ <?= h(date('Y-m-d H:i:s')) ?> — ทดสอบด้วยโมเดลจริงผ่าน Ollama (ไม่ใช่ rule-based fallback) ตามเกณฑ์ grounding ที่กำหนดใน Modelfile (issu.md #24, #25, #27)</div>

<div class="summary">
  <div class="stat"><div class="n"><?= $passed ?>/<?= $total ?></div><div class="l">เคสที่ผ่านทุกเกณฑ์</div></div>
  <div class="stat"><div class="n"><?= $avgTime ?>s</div><div class="l">เวลาตอบเฉลี่ย</div></div>
  <div class="stat"><div class="n"><?= count(array_filter($results, fn($r) => $r['source'] === 'ollama')) ?>/<?= $total ?></div><div class="l">ตอบจากโมเดลจริง (ไม่ fallback)</div></div>
</div>

<?php foreach ($results as $i => $r): ?>
<div class="case" id="case-<?= $i ?>">
  <div class="case-head" onclick="document.getElementById('case-<?= $i ?>').classList.toggle('open')">
    <h3>เคสที่ <?= $i + 1 ?>: <?= h($r['label']) ?></h3>
    <span>
      <?= $r['source'] !== 'ollama' ? '<span class="badge fallback">FALLBACK</span> ' : '' ?>
      <span class="badge <?= $r['allPass'] ? 'pass' : 'fail' ?>"><?= $r['allPass'] ? 'PASS' : 'FAIL' ?></span>
    </span>
  </div>
  <div class="case-body">
    <table>
      <thead><tr><th>ทักษะ</th><th>คะแนน</th><th>%</th></tr></thead>
      <tbody>
        <?php foreach ($r['rows'] as $row): ?>
        <tr><td><?= h($row['skill_name']) ?></td><td><?= $row['total'] ?>/<?= $row['max_score'] ?></td><td><?= $row['pct'] ?>%</td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="meta">
      คะแนนรวม: <?= $r['overall'] ?>% &nbsp;|&nbsp; ทักษะโดดเด่นที่คาดหวัง: <?= $r['expectedStrong'] ? h(implode(', ', $r['expectedStrong'])) : 'ไม่มี' ?>
      &nbsp;|&nbsp; เวลาตอบ: <?= $r['elapsed'] ?>s &nbsp;|&nbsp; แหล่งที่มา: <?= h($r['source']) ?>
    </div>

    <div class="ai-block"><strong>Analysis:</strong>
<?= h($r['analysis']) ?></div>
    <div class="ai-block"><strong>Recommendation:</strong>
<?= h($r['recommendation']) ?></div>

    <strong>ผลการตรวจสอบ grounding:</strong>
    <ul class="checks">
      <?php foreach ($r['checks'] as $label => $ok): ?>
      <li class="<?= $ok ? 'ok' : 'no' ?>"><?= h($label) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endforeach; ?>

<script>
document.querySelectorAll('.case').forEach(function (el, i) { if (i === 0) el.classList.add('open'); });
</script>
</body>
</html>
<?php
$html = ob_get_clean();
$outPath = __DIR__ . '/../model_evaluation.html';
file_put_contents($outPath, $html);
fwrite(STDERR, "\nWrote $outPath\n");
fwrite(STDERR, "$passed / $total cases passed all grounding checks. Average response time: {$avgTime}s.\n");

// Small summary admin/dashboard.php reads to show a benchmark-style table (issu.md #40).
// Rows are grounding CAPABILITIES (aggregated across all 8 scenarios), not per-scenario
// labels -- "Benchmark" should read like a model-card capability list (MATH-500, GSM8K, ...),
// not a dump of our own internal test-case names (issu.md #41/#42).
function categorize_check($label) {
    if ($label === 'ใช้โมเดลจริง (ไม่ fallback)') return 'ตอบจากโมเดลจริง (ไม่ fallback)';
    if (str_starts_with($label, 'กล่าวถึงทักษะโดดเด่นที่ถูกต้อง')) return 'ระบุทักษะโดดเด่นถูกต้องตรงกับข้อมูล';
    if ($label === 'ไม่มีทักษะโดดเด่น -> ต้องใช้ประโยคที่กำหนดตรงๆ') return 'ใช้ประโยคบังคับเมื่อไม่มีทักษะโดดเด่น';
    if (str_starts_with($label, 'ไม่เรียก "')) return 'ไม่หลอนทักษะที่ไม่ถึงเกณฑ์ว่าโดดเด่น';
    if ($label === 'มีคำแนะนำ (Recommendation) ไม่ว่างเปล่า') return 'ให้คำแนะนำที่ไม่ว่างเปล่าเสมอ';
    return $label;
}
$categoryTotals = [];
foreach ($results as $r) {
    foreach ($r['checks'] as $label => $ok) {
        $cat = categorize_check($label);
        $categoryTotals[$cat] ??= ['pass' => 0, 'total' => 0];
        $categoryTotals[$cat]['total']++;
        if ($ok) $categoryTotals[$cat]['pass']++;
    }
}
$benchmarkRows = [];
foreach ($categoryTotals as $label => $d) {
    $benchmarkRows[] = [
        'label' => $label,
        'score_pct' => $d['total'] ? round($d['pass'] / $d['total'] * 100) : 0,
        'pass' => $d['pass'] === $d['total'],
    ];
}
usort($benchmarkRows, fn($a, $b) => $b['score_pct'] <=> $a['score_pct']);

$summary = [
    'generated_at' => date('Y-m-d H:i:s'),
    'passed' => $passed,
    'total' => $total,
    'avg_time' => $avgTime,
    'cases' => $benchmarkRows,
];
$summaryPath = __DIR__ . '/../model_evaluation_summary.json';
file_put_contents($summaryPath, json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fwrite(STDERR, "Wrote $summaryPath\n");
