<?php
require_once '../config/db.php';
check_login('student');
include '../includes/header.php';
$student_id = $_SESSION['user_id'];
?>
<div class="row align-items-center mb-5 mt-3">
    <div class="col-md-12 text-center">
        <h2 class="display-5 fw-bold text-primary">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['name']) ?></h2>
        <p class="lead text-muted mt-2">ระบบประเมินและวิเคราะห์ Soft Skill ด้วย Generative AI แจ้งผลคะแนนทักษะในรูปแบบที่เข้าใจง่าย พร้อมได้รับคำปรึกษาพัฒนาทักษะแบบอัตโนมัติ</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-4 mb-4">
        <div class="card shadow border-0 h-100 bg-light text-center">
            <div class="card-body d-flex flex-column justify-content-center">
                <i class="bi bi-calendar-check text-primary mb-3" style="font-size: 3rem;"></i>
                <h4 class="card-title">ค้นหาและสมัครกิจกรรม</h4>
                <p class="card-text text-muted">เลือกกิจกรรมที่สนใจและสมัครเข้าร่วม</p>
                <div class="mt-auto"><a href="activities.php" class="btn btn-outline-primary btn-lg w-100">ดูรายชื่อกิจกรรม</a></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card shadow border-0 h-100 bg-light text-center">
            <div class="card-body d-flex flex-column justify-content-center">
                <i class="bi bi-pencil-square text-success mb-3" style="font-size: 3rem;"></i>
                <h4 class="card-title">ทำแบบประเมินตนเอง</h4>
                <p class="card-text text-muted">ประเมินทักษะ Soft skill ของท่านก่อน-หลังร่วมกิจกรรม</p>
                <div class="mt-auto"><a href="activities.php" class="btn btn-outline-success btn-lg w-100">ทำแบบประเมิน (ทางลัด)</a></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card shadow border-0 h-100 bg-light text-center">
            <div class="card-body d-flex flex-column justify-content-center">
                <i class="bi bi-bar-chart-fill text-info mb-3" style="font-size: 3rem;"></i>
                <h4 class="card-title">ดูรายงานวิเคราะห์จาก AI</h4>
                <p class="card-text text-muted">ตรวจสอบพัฒนาการและคำแนะนำที่ได้หลังจากการประเมิน</p>
                <div class="mt-auto"><a href="analysis_report.php" class="btn btn-info text-white btn-lg w-100">ดูรายงานรวม</a></div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
