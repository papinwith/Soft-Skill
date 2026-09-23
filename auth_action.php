<?php
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['student_id'] = $user['student_id'];

            if ($user['role'] === 'admin') header("Location: admin/dashboard.php");
            elseif ($user['role'] === 'manager') header("Location: manager/dashboard.php");
            else header("Location: student/dashboard.php");
            exit;
        } else {
            header("Location: index.php?error=" . urlencode("ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง"));
            exit;
        }
    } elseif ($action === 'register') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $name = trim($_POST['name']);
        $student_id = trim($_POST['student_id']);
        $department = trim($_POST['department']);
        $email = trim($_POST['email']);
        
        // check duplicate
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            header("Location: register.php?error=" . urlencode("ชื่อผู้ใช้งานนี้ถูกใช้ไปแล้ว"));
            exit;
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'student';
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, student_id, department, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$username, $hashed_password, $role, $name, $student_id, $department, $email])) {
            $new_user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['role'] = $role;
            $_SESSION['name'] = $name;
            $_SESSION['student_id'] = $student_id;
            
            header("Location: student/assessment.php?type=baseline");
            exit;
        } else {
            header("Location: register.php?error=" . urlencode("เกิดข้อผิดพลาดในการบันทึกข้อมูล"));
            exit;
        }
    }
} else {
    header("Location: index.php");
    exit;
}
