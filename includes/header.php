<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบวิเคราะห์ Soft Skill</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/Soft-Skill/assets/css/style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); border:none; border-radius: 10px; }
        .navbar { box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container">
    <a class="navbar-brand" href="/Soft-Skill/index.php">Soft Skill System</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <?php if(isset($_SESSION['user_id'])): ?>
            <li class="nav-item">
               <a class="nav-link text-white fw-bold">ผู้ใช้งาน: <?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo ucfirst($_SESSION['role']); ?>)</a>
            </li>
            
            <?php if($_SESSION['role'] === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="/Soft-Skill/admin/dashboard.php">ระบบจัดการ</a></li>
            <?php elseif($_SESSION['role'] === 'manager'): ?>
                <li class="nav-item"><a class="nav-link" href="/Soft-Skill/manager/dashboard.php">จัดการกิจกรรม</a></li>
            <?php elseif($_SESSION['role'] === 'student'): ?>
                <li class="nav-item"><a class="nav-link" href="/Soft-Skill/student/activities.php">ลงทะเบียนกิจกรรม</a></li>
            <?php endif; ?>

            <li class="nav-item ms-3">
              <form method="post" action="/Soft-Skill/logout.php" class="mt-1">
                <?php echo csrf_field(); ?>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.form.submit()">ออกจากระบบ</button>
              </form>
            </li>
        <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="/Soft-Skill/index.php">เข้าสู่ระบบ</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="/Soft-Skill/register.php">สมัครสมาชิก</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container">
