<?php
require_once '../config/db.php';
check_login('admin');
include '../includes/header.php';

// Summary queries
$userCount = $pdo->query("SELECT (SELECT COUNT(*) FROM students) + (SELECT COUNT(*) FROM activity_supervisors) + (SELECT COUNT(*) FROM admins)")->fetchColumn();
$activityCount = $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
$skillCount = $pdo->query("SELECT COUNT(*) FROM soft_skills")->fetchColumn();
$assessmentCount = $pdo->query("SELECT COUNT(*) FROM assessments")->fetchColumn();
?>
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">ผู้ใช้งานระบบ</h5>
                <h2 class="mb-0"><?= $userCount ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">กิจกรรมทั้งหมด</h5>
                <h2 class="mb-0"><?= $activityCount ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Soft Skills</h5>
                <h2 class="mb-0"><?= $skillCount ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">แบบประเมิน</h5>
                <h2 class="mb-0"><?= $assessmentCount ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header bg-dark text-white">เมนูการจัดการหลัก</div>
            <div class="card-body">
                <div class="list-group">
                    <a href="manage_users.php?role=manager" class="list-group-item list-group-item-action">จัดการผู้ดูแลกิจกรรม (Activity Managers)</a>
                    <a href="manage_users.php?role=student" class="list-group-item list-group-item-action">จัดการนักศึกษา (Students)</a>
                    <a href="manage_activities.php" class="list-group-item list-group-item-action">จัดการกิจกรรม (Activities)</a>
                    <a href="manage_departments.php" class="list-group-item list-group-item-action">จัดการคณะและสาขา (Faculties/Majors)</a>
                    <a href="manage_curricula.php" class="list-group-item list-group-item-action">จัดการหลักสูตร (Curricula)</a>
                    <a href="manage_skills.php" class="list-group-item list-group-item-action">จัดการ Soft Skills</a>
                    <a href="manage_assessments.php" class="list-group-item list-group-item-action">จัดการข้อมูลแบบประเมินและคำถาม</a>
                    <a href="reports.php" class="list-group-item list-group-item-action list-group-item-primary">ออกรายงานสรุปผลต่างๆ</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
