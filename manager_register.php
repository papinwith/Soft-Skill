<?php
require_once 'config/db.php';
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
include 'includes/header.php';
?>
<div class="row justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="col-md-7">
        <div class="card shadow">
            <div class="card-header bg-success text-white text-center py-3">
                <h4 class="mb-0">สมัครเป็นผู้ดูแลกิจกรรม (Activity Organizer)</h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small">บัญชีของคุณจะต้องรอ Admin อนุมัติก่อนจึงจะเข้าใช้งานได้</p>
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>
                <form action="auth_action.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="reg_role" value="manager">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ชื่อ-นามสกุล (Full Name) <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">รหัสผ่าน (Password) <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="8" placeholder="อย่างน้อย 8 ตัวอักษร มีตัวเลขและตัวอักษร">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">ยืนยันรหัสผ่าน (Confirm Password) <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirm" class="form-control" required minlength="8">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">เบอร์โทร</label>
                            <input type="text" name="phone" class="form-control" placeholder="08XXXXXXXX">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">อีเมล (Email)</label>
                            <input type="email" name="email" class="form-control" placeholder="example@mail.com">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold mt-2">สมัครเป็นผู้ดูแลกิจกรรม</button>
                    <div class="text-center mt-3">
                        <a href="index.php" class="text-decoration-none text-secondary"><i class="bi bi-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
