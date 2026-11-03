<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
check_login('admin');

$roleFilter = $_GET['role'] ?? '';
if (!in_array($roleFilter, ['admin', 'manager', 'student'], true)) $roleFilter = '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$where = [];
$params = [];
if ($roleFilter !== '') { $where[] = 'actor_role = ?'; $params[] = $roleFilter; }
if ($q !== '') { $where[] = '(actor_name LIKE ? OR action LIKE ? OR detail LIKE ?)'; $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]); }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM activity_logs $whereSql");
$total->execute($params);
$total = (int)$total->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM activity_logs $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$roleLabel = ['admin' => 'Admin', 'manager' => 'ผู้จัดกิจกรรม', 'student' => 'นักศึกษา'];

include '../includes/header.php';
?>
<div class="mb-3"><a href="dashboard.php" class="btn btn-sm btn-secondary">&larr; ย้อนกลับ</a></div>
<h3 class="mb-3">บันทึกการใช้งานระบบ (Logs)</h3>
<p class="text-muted small">บันทึกการเพิ่ม/แก้ไข/ลบข้อมูลโดย Admin และผู้จัดกิจกรรม เรียงจากล่าสุดไปเก่าสุด ทั้งหมด <?= $total ?> รายการ</p>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">บทบาทผู้ทำรายการ</label>
                <select name="role" class="form-select" onchange="this.form.submit()">
                    <option value="">ทั้งหมด</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="manager" <?= $roleFilter === 'manager' ? 'selected' : '' ?>>ผู้จัดกิจกรรม</option>
                    <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>นักศึกษา</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">ค้นหา (ชื่อผู้ทำรายการ / การกระทำ / รายละเอียด)</label>
                <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr><th>เวลา</th><th>ผู้ทำรายการ</th><th>บทบาท</th><th>การกระทำ</th><th>รายละเอียด</th></tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="text-nowrap"><?= htmlspecialchars($l['created_at']) ?></td>
                    <td><?= htmlspecialchars($l['actor_name']) ?></td>
                    <td><?= htmlspecialchars($roleLabel[$l['actor_role']] ?? $l['actor_role']) ?></td>
                    <td><code><?= htmlspecialchars($l['action']) ?></code></td>
                    <td><?= htmlspecialchars($l['detail'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?><tr><td colspan="5" class="text-center">ไม่มีข้อมูล</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination mb-0">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&role=<?= urlencode($roleFilter) ?>&q=<?= urlencode($q) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
