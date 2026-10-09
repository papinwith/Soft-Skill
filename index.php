<?php
require_once 'config/db.php';
if(isset($_SESSION['user_id'])) {
    if($_SESSION['role'] === 'admin') header('Location: admin/dashboard.php');
    elseif($_SESSION['role'] === 'manager') header('Location: manager/dashboard.php');
    else header('Location: student/profile.php');
    exit;
}
include 'includes/header.php';
?>
<div class="row justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">เข้าสู่ระบบ (Login)</h4>
            </div>
            <div class="card-body p-4">
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>
                <?php if(isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php endif; ?>
                <form action="auth_action.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อผู้ใช้งาน (Username) หรือ รหัสนักศึกษา</label>
                        <input type="text" name="username" class="form-control" required placeholder="admin / รหัสนักศึกษา">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">รหัสผ่าน (Password)</label>
                        <input type="password" name="password" class="form-control" required placeholder="รหัสผ่าน">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">เข้าสู่ระบบ</button>
                    <div class="text-center mt-2">
                        <a href="forgot_password.php" class="text-decoration-none">ลืมรหัสผ่าน? (Forgot Password)</a>
                    </div>
                    <div class="text-center mt-3">
                        <span>ยังไม่มีบัญชีผู้ใช้งานใช่ไหม? <a href="register.php" class="text-decoration-none">สมัครสมาชิกสำหรับนักศึกษา</a></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
