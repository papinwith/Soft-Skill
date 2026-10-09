<?php
require __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
