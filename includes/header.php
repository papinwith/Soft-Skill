<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบวิเคราะห์ Soft Skill</title>
    <?php
    // UX gate (issu.md #49), NOT a server-enforced access control: the PHP session cookie is
    // shared by every tab in the browser, so a brand-new tab would otherwise silently reuse
    // whichever account is logged in elsewhere. sessionStorage is per-tab in the common case
    // (plain new tab / new window) -- its absence sends this tab to reverify.php to re-enter
    // the password before continuing. Two known limits, left as-is because closing them fully
    // would require routing every link/form through a token round-trip app-wide: (1) some
    // browsers copy sessionStorage when a tab is explicitly *duplicated*, so a duplicated tab
    // can inherit an already-verified flag; (2) this is JS-side, so it does not stop a request
    // replayed with a stolen session cookie (anyone holding that cookie already bypasses the
    // password regardless of this check -- the actual defense against that is the cookie being
    // HttpOnly/Secure, set in config/db.php, not this gate).
    //
    // The stored flag is tied to a fingerprint of the *current* session identity (not just a
    // bare "1"), so if this session cookie's identity changes underneath the tab -- e.g. a
    // shared/lab computer where Tab 1 verified as Student A, then Tab 2 logs out and Student B
    // logs in (the cookie is shared, so Tab 1 picks up B's session on its next request) -- the
    // fingerprint mismatch correctly forces Tab 1 to re-verify as B rather than keep trusting
    // A's stale verification.
    if (isset($_SESSION['user_id']) && !in_array(basename($_SERVER['PHP_SELF']), ['reverify.php', 'logout.php'], true)):
        $justAuthenticated = !empty($_SESSION['just_authenticated']);
        unset($_SESSION['just_authenticated']);
        $sessionIdentity = hash('sha256', session_id() . '|' . $_SESSION['user_id'] . '|' . $_SESSION['role']);
    ?>
    <script>
    (function () {
        try {
            var identity = <?= json_encode($sessionIdentity) ?>;
            if (<?= $justAuthenticated ? 'true' : 'false' ?>) {
                sessionStorage.setItem('ss_tab_verified', identity);
            } else if (sessionStorage.getItem('ss_tab_verified') !== identity) {
                window.location.replace('/Soft-Skill/reverify.php?return=' + encodeURIComponent(location.pathname + location.search));
            }
        } catch (e) {}
    })();
    </script>
    <?php endif; ?>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (bi bi-* classes used across the app) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
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
