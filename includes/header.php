<?php
// Подключаем конфигурацию (сессия уже начата в config.php)
include 'config.php';

// Проверка авторизации (редирект на login, если не авторизован)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Включаем буферизацию вывода
ob_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack+</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar { transition: all 0.3s ease; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .grade-0 { background-color: #fef2f2; color: #dc2626; }
        .grade-1 { background-color: #fffbeb; color: #d97706; }
        .grade-2 { background-color: #f0fdf4; color: #16a34a; }
    </style>
</head>
<body class="bg-gray-50">
<?php
// Не очищаем буфер здесь, оставляем это для вызывающего скрипта
?>