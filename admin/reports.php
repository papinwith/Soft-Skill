<?php
require_once '../config/db.php';
check_login('admin');
include '../includes/header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <h3>รายงานผลระบบ (Admin Reports)</h3>
</div>

<div class="row">
    <!-- User Report -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">รายงานผู้ใช้งานระบบ</div>
            <div class="card-body">
                <p>จำนวนนักศึกษา: <b><?= $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?></b> บัญชี</p>
                <p>จำนวนผู้ดูแลกิจกรรม: <b><?= $pdo->query("SELECT COUNT(*) FROM activity_supervisors")->fetchColumn() ?></b> บัญชี</p>
            </div>
            <div class="card-footer">
                <a href="report_detail.php?type=users" class="btn btn-sm btn-outline-primary">ดูรายงานผู้ใช้งาน</a>
            </div>
        </div>
    </div>

    <!-- Activity Report -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-success text-white">รายงานกิจกรรม</div>
            <div class="card-body">
                <p>กิจกรรมทั้งหมด: <b><?= $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn() ?></b> กิจกรรม</p>
                <p>จำนวนผู้เข้าร่วมทั้งหมด: <b><?= $pdo->query("SELECT COUNT(*) FROM activity_participants WHERE status='attended'")->fetchColumn() ?></b> ครั้งที่เข้าร่วม</p>
            </div>
            <div class="card-footer">
                <a href="report_detail.php?type=activities" class="btn btn-sm btn-outline-success">ดูรายละเอียดกิจกรรม</a>
            </div>
        </div>
    </div>

    <!-- Overview Soft Skill Report -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-info text-white">รายงานการประเมิน Soft Skill</div>
            <div class="card-body">
                <p>จำนวนการประเมิน Pre-test: <b><?= $pdo->query("SELECT COUNT(*) FROM student_assessments WHERE type='pre'")->fetchColumn() ?></b> ครั้ง</p>
                <p>จำนวนการประเมิน Post-test: <b><?= $pdo->query("SELECT COUNT(*) FROM student_assessments WHERE type='post'")->fetchColumn() ?></b> ครั้ง</p>
            </div>
            <div class="card-footer">
                <a href="report_detail.php?type=softskills" class="btn btn-sm btn-outline-info">ดูสรุปผล Soft Skills ของนักศึกษา</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
