<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        [$user, $role] = find_account($pdo, $username);

        if (!$user) {
            // Students may log in with their student ID (only when it is unambiguous)
            $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
            $stmt->execute([$username]);
            $matches = $stmt->fetchAll();
            if (count($matches) === 1) { $user = $matches[0]; $role = 'student'; }
        }

        if ($user && password_verify($password, $user['password']) && $role === 'manager' && ($user['status'] ?? 'active') === 'pending') {
            header("Location: index.php?error=" . urlencode("บัญชีผู้จัดกิจกรรมของคุณกำลังรอการอนุมัติจาก Admin กรุณารอการติดต่อกลับ"));
            exit;
        }

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $role;
            $_SESSION['name'] = $user['name'];
            $_SESSION['student_id'] = $user['student_id'] ?? null;
            // Consumed by header.php on the very next page load (issu.md #49) so the tab that
            // just actually typed the password isn't immediately bounced to reverify.php too.
            $_SESSION['just_authenticated'] = true;

            if ($role === 'admin') header("Location: admin/dashboard.php");
            elseif ($role === 'manager') header("Location: manager/dashboard.php");
            else header("Location: student/profile.php");
            exit;
        } else {
            error_log("Failed login attempt for username '$username' from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            header("Location: index.php?error=" . urlencode("ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง"));
            exit;
        }
    } elseif ($action === 'register') {
        $regRole = ($_POST['reg_role'] ?? 'student') === 'manager' ? 'manager' : 'student';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '') ?: null;

        $regRolePage = $regRole === 'manager' ? 'manager_register.php' : 'register.php';
        $fail = function ($msg) use ($regRolePage) { header("Location: $regRolePage?error=" . urlencode($msg)); exit; };

        if ($username === '' || $name === '') $fail("กรุณากรอกข้อมูลที่จำเป็นให้ครบ");
        $pwError = password_strength_error($password);
        if ($pwError) $fail($pwError);
        if ($password !== $confirm) $fail("รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน");
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) $fail("รูปแบบอีเมลไม่ถูกต้อง");

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        if ($regRole === 'manager') {
            $phone = trim($_POST['phone'] ?? '') ?: null;
            $conflict = identifier_conflict($pdo, $username, null, null, $email);
            if ($conflict) $fail($conflict);

            $stmt = $pdo->prepare("INSERT INTO activity_supervisors (username, password, name, email, phone, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            if ($stmt->execute([$username, $hashed_password, $name, $email, $phone])) {
                // Notify the central organizer-side contact -- there's no admins.email column
                // to send to -- so a new application doesn't just sit unnoticed until someone
                // checks the admin page (issu.md #37).
                $centralId = central_manager_id($pdo);
                $centralEmail = null;
                if ($centralId) {
                    $emailStmt = $pdo->prepare("SELECT email FROM activity_supervisors WHERE id = ?");
                    $emailStmt->execute([$centralId]);
                    $centralEmail = $emailStmt->fetchColumn();
                }
                if ($centralEmail) {
                    send_mail($centralEmail, 'มีผู้สมัครเป็นผู้ดูแลกิจกรรมใหม่รอการอนุมัติ',
                        "มีผู้สมัครเป็นผู้ดูแลกิจกรรมใหม่ในระบบ รอการอนุมัติจาก Admin:\n\nชื่อ: $name\nUsername: $username\n\nกรุณาเข้าไปตรวจสอบและอนุมัติที่หน้า \"จัดการผู้ดูแลกิจกรรม\" ในระบบ Admin");
                }
                header("Location: index.php?success=" . urlencode("สมัครสำเร็จ บัญชีผู้จัดกิจกรรมของคุณกำลังรอการอนุมัติจาก Admin ก่อนเข้าใช้งานได้"));
                exit;
            }
            $fail("เกิดข้อผิดพลาดในการบันทึกข้อมูล");
        }

        $student_id = trim($_POST['student_id'] ?? '');
        $year_level = ($_POST['year_level'] ?? '') === '' ? null : max(1, min(4, (int)$_POST['year_level']));
        $faculty_id = (int)($_POST['faculty_id'] ?? 0);
        $department_id = (int)($_POST['department_id'] ?? 0);

        if ($student_id === '') $fail("กรุณากรอกข้อมูลที่จำเป็นให้ครบ");
        if (strlen($student_id) > 10) $fail("รหัสนักศึกษาต้องมีความยาวไม่เกิน 10 ตัวอักษร");

        $dept = $pdo->prepare("SELECT name FROM departments WHERE id = ? AND faculty_id = ?");
        $dept->execute([$department_id, $faculty_id]);
        $department_name = $dept->fetchColumn();
        if ($department_name === false) $fail("กรุณาเลือกคณะและสาขาให้ถูกต้อง");

        $conflict = identifier_conflict($pdo, $username, $student_id, null, $email);
        if ($conflict) $fail($conflict);

        $stmt = $pdo->prepare("INSERT INTO students (username, password, name, student_id, department, email, faculty_id, department_id, year_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$username, $hashed_password, $name, $student_id, $department_name, $email, $faculty_id, $department_id, $year_level])) {
            $new_user_id = $pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['role'] = 'student';
            $_SESSION['name'] = $name;
            $_SESSION['student_id'] = $student_id;
            $_SESSION['just_authenticated'] = true;

            header("Location: student/assessment.php?type=baseline");
            exit;
        } else {
            $fail("เกิดข้อผิดพลาดในการบันทึกข้อมูล");
        }
    }
} else {
    header("Location: index.php");
    exit;
}
