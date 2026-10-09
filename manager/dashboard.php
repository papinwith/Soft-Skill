<?php
require_once '../config/db.php';
check_login('manager');
include '../includes/header.php';

$manager_id = $_SESSION['user_id'];
$myActivityCount = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE manager_id = ?");
$myActivityCount->execute([$manager_id]);
$myActivityCount = $myActivityCount->fetchColumn();

// Count total participants in my activities (all status)
$participantCount = $pdo->prepare("SELECT COUNT(*) FROM activity_participants ap JOIN activities a ON ap.activity_id = a.id WHERE a.manager_id = ?");
$participantCount->execute([$manager_id]);
$participantCount = $participantCount->fetchColumn();

// Count pre/post responses for my activities
$responsesCount = $pdo->prepare("SELECT COUNT(*) FROM student_assessments sa JOIN activities a ON sa.activity_id = a.id WHERE a.manager_id = ?");
$responsesCount->execute([$manager_id]);
$responsesCount = $responsesCount->fetchColumn();
?>
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-primary shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">กิจกรรมที่รับผิดชอบ</h5>
                <h2 class="mb-0"><?= $myActivityCount ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-success shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">นักศึกษาที่สมัครเข้าร่วม (รวม)</h5>
                <h2 class="mb-0"><?= $participantCount ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-info shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">จำนวนการประเมินผล(แบบฟอร์ม)</h5>
                <h2 class="mb-0"><?= $responsesCount ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-md-8 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">เมนูจัดการกิจกรรม (Activity Manager)</div>
            <div class="card-body">
                <div class="list-group">
                    <a href="my_activities.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        จัดการข้อมูลกิจกรรมของตนเอง <span class="badge bg-primary rounded-pill"><i class="bi bi-calendar"></i></span>
                    </a>
                    <a href="participants.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        ตรวจสอบรายชื่อนักศึกษาและยืนยันการเข้าร่วม <span class="badge bg-success rounded-pill"><i class="bi bi-people"></i></span>
                    </a>
                    <a href="tracking_reports.php" class="list-group-item list-group-item-action list-group-item-info d-flex justify-content-between align-items-center">
                        ตรวจสอบผลการประเมินและติดตามการพัฒนา <span class="badge bg-info text-dark rounded-pill"><i class="bi bi-graph-up"></i></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
