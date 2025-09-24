<?php
session_start();

// Проверка сессии и роли
if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

include 'config.php';

// Проверка соединения с базой данных
if (!$conn || $conn->connect_error) {
    die("Ошибка подключения к базе данных: " . ($conn ? $conn->connect_error : 'Объект подключения отсутствует'));
}

// Функция для проверки CSRF-токена
function verifyCsrf($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Ошибка CSRF-токена");
    }
}

// Установка CSRF-токена
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Статистика для студента
$student_stats = null;
if ($role === 'student') {
    // Количество текущих допов
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status = 'approved'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['current_courses'] = $row['count'] ?? 0;
    $stmt->close();

    // Средний балл
    $stmt = $conn->prepare("SELECT AVG(g.grade) as avg_grade FROM grades g JOIN enrollments e ON g.enrollment_id = e.id WHERE e.student_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['avg_grade'] = $row['avg_grade'] ? number_format($row['avg_grade'], 2) : 'Нет оценок';
    $stmt->close();

    // Количество заявок на рассмотрении
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status IN ('pending', 'replaced_pending', 'drop_pending')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['pending_requests'] = $row['count'] ?? 0;
    $stmt->close();

    // Список текущих допов с последними оценками
    $stmt = $conn->prepare("
        SELECT c.name AS course_name, g.grade, g.date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN grades g ON e.id = g.enrollment_id
        WHERE e.student_id = ? AND e.status = 'approved'
        ORDER BY c.name
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['courses'] = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Ошибка запроса допов студента: " . $conn->error);
        $stats['courses'] = [];
    }
    $stmt->close();
}

// Обработка добавления аккаунта (admin/teacher)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user']) && ($role == 'admin' || $role == 'teacher')) {
    verifyCsrf($_POST['csrf_token']);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $class = filter_input(INPUT_POST, 'class', FILTER_SANITIZE_STRING);
    $role_new = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    if ($role == 'teacher') $role_new = 'student';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT);

    if (empty($username) || strlen($username) < 3) {
        $error = "Логин слишком короткий или пустой";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Неверный email";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, full_name, email, class) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $password, $role_new, $full_name, $email, $class);
        try {
            $stmt->execute();
            $success = "Аккаунт создан!";
        } catch (Exception $e) {
            $error = "Ошибка: " . $e->getMessage();
            error_log("Ошибка добавления пользователя: " . $e->getMessage());
        }
        $stmt->close();
    }
}

// Обработка добавления курса (admin/teacher)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course']) && ($role == 'admin' || $role == 'teacher')) {
    verifyCsrf($_POST['csrf_token']);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $schedule = filter_input(INPUT_POST, 'schedule', FILTER_SANITIZE_STRING) ?? '';
    $teacher_input = $_POST['teacher_id'] ?? 0;
    $teacher_id = $role == 'teacher' ? $user_id : (int)$teacher_input;

    if (empty($name) || strlen($name) < 3) {
        $error = "Название курса слишком короткое";
    } elseif ($teacher_id <= 0) {
        $error = "Неверный ID преподавателя";
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (name, description, teacher_id, schedule) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $name, $description, $teacher_id, $schedule);
        try {
            $stmt->execute();
            $success = "Курс добавлен!";
        } catch (Exception $e) {
            $error = "Ошибка добавления курса: " . $e->getMessage();
            error_log("Ошибка добавления курса: " . $e->getMessage());
        }
        $stmt->close();
    }
}

// Обработка выбора допов (student)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_courses']) && $role == 'student') {
    verifyCsrf($_POST['csrf_token']);
    $selected = $_POST['courses'] ?? [];
    if (count($selected) < 3) {
        $error_student = "Выберите минимум 3 доп. занятия!";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'approved'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_row();
        $current = $row[0] ?? 0;
        $stmt->close();
        if ($current >= 3) {
            $stmt_update = $conn->prepare("UPDATE enrollments SET status = 'replaced_pending' WHERE student_id = ? AND status = 'approved'");
            $stmt_update->bind_param("i", $user_id);
            $stmt_update->execute();
            $stmt_update->close();
        }
        foreach ($selected as $course_id) {
            $course_id = (int)$course_id;
            if ($course_id > 0) {
                $stmt_enroll = $conn->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'pending')");
                $stmt_enroll->bind_param("ii", $user_id, $course_id);
                $stmt_enroll->execute();
                $stmt_enroll->close();
            }
        }
        $success_student = "Заявка на выбор допов отправлена!";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная - EduTrack+</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .card-hover:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        }
        .gradient-bg { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
        }
        .grade-badge-0 { background-color: #fef2f2; color: #dc2626; }
        .grade-badge-1 { background-color: #fffbeb; color: #d97706; }
        .grade-badge-2 { background-color: #f0fdf4; color: #16a34a; }
        
        /* Mobile optimizations */
        @media (max-width: 640px) {
            .mobile-card { margin-bottom: 1rem; }
            .mobile-form { padding: 1rem; }
            .mobile-table { font-size: 0.875rem; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="flex-1 overflow-y-auto lg:ml-0">
            <!-- Mobile header spacing -->
            <div class="h-16 lg:h-0"></div>
            
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="px-4 sm:px-6 py-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">
                            Привет, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php 
                            $roleNames = [
                                'student' => 'Студент', 
                                'teacher' => 'Преподаватель', 
                                'admin' => 'Администратор'
                            ];
                            echo $roleNames[$role] ?? ucfirst($role);
                            ?>
                        </p>
                    </div>
                    <div class="hidden sm:flex items-center space-x-3">
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900"><?php echo date('d.m.Y'); ?></p>
                            <p class="text-xs text-gray-500"><?php echo date('H:i'); ?></p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 space-y-6">
                <!-- Notifications -->
                <?php if (isset($success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md" data-aos="fade-down">
                        <div class="flex">
                            <i data-feather="check-circle" class="w-5 h-5 text-green-400 mr-2 flex-shrink-0 mt-0.5"></i>
                            <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-md" data-aos="shake">
                        <div class="flex">
                            <i data-feather="alert-circle" class="w-5 h-5 text-red-400 mr-2 flex-shrink-0 mt-0.5"></i>
                            <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (isset($success_student)): ?>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md" data-aos="fade-down">
                        <div class="flex">
                            <i data-feather="info" class="w-5 h-5 text-blue-400 mr-2 flex-shrink-0 mt-0.5"></i>
                            <p class="text-blue-700"><?php echo htmlspecialchars($success_student); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (isset($error_student)): ?>
                    <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded-md" data-aos="shake">
                        <div class="flex">
                            <i data-feather="alert-triangle" class="w-5 h-5 text-orange-400 mr-2 flex-shrink-0 mt-0.5"></i>
                            <p class="text-orange-700"><?php echo htmlspecialchars($error_student); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Student Statistics -->
                <?php if ($role === 'student'): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden mobile-card" data-aos="fade-up">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i data-feather="trending-up" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                Ваша статистика
                            </h3>
                        </div>
                        <div class="p-4 sm:p-6">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4 sm:p-6 rounded-xl text-white card-hover transition-all duration-300">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-blue-100 text-sm font-medium">Текущие допы</p>
                                            <p class="text-2xl sm:text-3xl font-bold"><?php echo $stats['current_courses']; ?></p>
                                        </div>
                                        <i data-feather="book-open" class="w-8 h-8 text-blue-200"></i>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-green-500 to-green-600 p-4 sm:p-6 rounded-xl text-white card-hover transition-all duration-300">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-green-100 text-sm font-medium">Средний балл</p>
                                            <p class="text-2xl sm:text-3xl font-bold"><?php echo $stats['avg_grade']; ?></p>
                                        </div>
                                        <i data-feather="award" class="w-8 h-8 text-green-200"></i>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-orange-500 to-orange-600 p-4 sm:p-6 rounded-xl text-white card-hover transition-all duration-300">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-orange-100 text-sm font-medium">Заявки</p>
                                            <p class="text-2xl sm:text-3xl font-bold"><?php echo $stats['pending_requests']; ?></p>
                                        </div>
                                        <i data-feather="clock" class="w-8 h-8 text-orange-200"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile-optimized courses table -->
                            <div class="space-y-4">
                                <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <i data-feather="list" class="w-5 h-5 mr-2"></i>
                                    Ваши дополнительные занятия
                                </h4>
                                
                                <!-- Mobile view -->
                                <div class="sm:hidden space-y-3">
                                    <?php if (count($stats['courses']) > 0): ?>
                                        <?php foreach ($stats['courses'] as $course): ?>
                                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                                <div class="flex justify-between items-start mb-2">
                                                    <h5 class="font-medium text-gray-900"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                                    <?php if ($course['grade'] !== null): ?>
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full grade-badge-<?php echo $course['grade']; ?>">
                                                            <?php echo $course['grade']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-xs text-gray-500 bg-gray-200 px-2 py-1 rounded-full">Нет оценки</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-600">
                                                    <i data-feather="calendar" class="w-4 h-4 inline mr-1"></i>
                                                    <?php echo $course['date'] ? htmlspecialchars($course['date']) : 'Дата не указана'; ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-8 text-gray-500">
                                            <i data-feather="book" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                                            <p>У вас нет текущих допов</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Desktop table view -->
                                <div class="hidden sm:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дополнительное занятие</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Оценка</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (count($stats['courses']) > 0): ?>
                                                <?php foreach ($stats['courses'] as $course): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php if ($course['grade'] !== null): ?>
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full grade-badge-<?php echo $course['grade']; ?>">
                                                                    <?php echo $course['grade']; ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-sm text-gray-500">Нет оценки</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            <?php echo $course['date'] ? htmlspecialchars($course['date']) : '—'; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="px-6 py-4 text-center text-gray-500">
                                                        У вас нет текущих допов.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add User Form (Admin/Teacher) -->
                <?php if ($role == 'admin' || $role == 'teacher'): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden mobile-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i data-feather="user-plus" class="w-5 h-5 mr-2 text-green-600"></i>
                                Добавить аккаунт
                            </h3>
                        </div>
                        <form method="POST" class="p-4 sm:p-6 mobile-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="add_user" value="1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="user" class="w-4 h-4 inline mr-1"></i>
                                        Логин
                                    </label>
                                    <input type="text" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Введите логин">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="lock" class="w-4 h-4 inline mr-1"></i>
                                        Пароль
                                    </label>
                                    <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Введите пароль">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="user-check" class="w-4 h-4 inline mr-1"></i>
                                        ФИО
                                    </label>
                                    <input type="text" name="full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Фамилия Имя Отчество">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="mail" class="w-4 h-4 inline mr-1"></i>
                                        Email
                                    </label>
                                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="email@example.com">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="users" class="w-4 h-4 inline mr-1"></i>
                                        Класс
                                    </label>
                                    <input type="text" name="class" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Например: 10А">
                                </div>
                                <?php if ($role == 'admin'): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="shield" class="w-4 h-4 inline mr-1"></i>
                                        Роль
                                    </label>
                                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="student">Учащийся</option>
                                        <option value="teacher">Преподаватель</option>
                                        <option value="admin">Администратор</option>
                                    </select>
                                </div>
                                <?php else: ?>
                                <input type="hidden" name="role" value="student">
                                <?php endif; ?>
                            </div>
                            <div class="mt-6">
                                <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-lg font-medium hover:from-indigo-700 hover:to-purple-700 transition-all duration-300 flex items-center justify-center">
                                    <i data-feather="plus" class="w-5 h-5 mr-2"></i>
                                    Создать аккаунт
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Add Course Form (Admin/Teacher) -->
                <?php if ($role == 'admin' || $role == 'teacher'): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden mobile-card" data-aos="fade-up" data-aos-delay="200">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i data-feather="book-open" class="w-5 h-5 mr-2 text-blue-600"></i>
                                Добавить дополнительное занятие
                            </h3>
                        </div>
                        <form method="POST" class="p-4 sm:p-6 mobile-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="add_course" value="1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="type" class="w-4 h-4 inline mr-1"></i>
                                        Название
                                    </label>
                                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Название занятия">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="file-text" class="w-4 h-4 inline mr-1"></i>
                                        Описание
                                    </label>
                                    <input type="text" name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Краткое описание">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="calendar" class="w-4 h-4 inline mr-1"></i>
                                        Расписание
                                    </label>
                                    <input type="text" name="schedule" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Например: Пн, Ср 15:00-16:00">
                                </div>
                                <?php if ($role == 'admin'): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-feather="user-check" class="w-4 h-4 inline mr-1"></i>
                                        Преподаватель
                                    </label>
                                    <select name="teacher_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">Выберите преподавателя</option>
                                        <?php
                                        $result = $conn->query("SELECT id, full_name FROM users WHERE role = 'teacher'");
                                        while ($teacher = $result->fetch_assoc()) {
                                            echo "<option value='{$teacher['id']}'>" . htmlspecialchars($teacher['full_name']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <?php else: ?>
                                <input type="hidden" name="teacher_id" value="<?php echo $user_id; ?>">
                                <?php endif; ?>
                            </div>
                            <div class="mt-6">
                                <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-medium hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 flex items-center justify-center">
                                    <i data-feather="plus-circle" class="w-5 h-5 mr-2"></i>
                                    Добавить занятие
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Recent Grades -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden mobile-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i data-feather="star" class="w-5 h-5 mr-2 text-purple-600"></i>
                            Последние оценки
                        </h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <!-- Mobile view -->
                        <div class="sm:hidden space-y-3 mobile-table">
                            <?php
                            $where_clause = ($role === 'student') ? "AND e.student_id = $user_id" : "";
                            $result = $conn->query("
                                SELECT u.full_name, c.name as course, g.grade, g.date
                                FROM grades g
                                JOIN enrollments e ON g.enrollment_id = e.id
                                JOIN users u ON e.student_id = u.id
                                JOIN courses c ON e.course_id = c.id
                                WHERE 1=1 $where_clause
                                ORDER BY g.date DESC
                                LIMIT 5
                            ");
                            if ($result && $result->num_rows > 0):
                                while ($grade = $result->fetch_assoc()):
                            ?>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1 min-w-0">
                                            <h5 class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($grade['full_name']); ?></h5>
                                            <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($grade['course']); ?></p>
                                        </div>
                                        <span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full grade-badge-<?php echo $grade['grade']; ?>">
                                            <?php echo $grade['grade']; ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        <i data-feather="calendar" class="w-3 h-3 inline mr-1"></i>
                                        <?php echo htmlspecialchars($grade['date']); ?>
                                    </p>
                                </div>
                            <?php endwhile; else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i data-feather="star" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                                    <p>Нет последних оценок</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Desktop table view -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Учащийся</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дополнительное занятие</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Балл</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php
                                    // Reset result pointer
                                    $where_clause = ($role === 'student') ? "AND e.student_id = $user_id" : "";
                                    $result = $conn->query("
                                        SELECT u.full_name, c.name as course, g.grade, g.date
                                        FROM grades g
                                        JOIN enrollments e ON g.enrollment_id = e.id
                                        JOIN users u ON e.student_id = u.id
                                        JOIN courses c ON e.course_id = c.id
                                        WHERE 1=1 $where_clause
                                        ORDER BY g.date DESC
                                        LIMIT 5
                                    ");
                                    if ($result && $result->num_rows > 0):
                                        while ($grade = $result->fetch_assoc()):
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($grade['full_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($grade['course']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full grade-badge-<?php echo $grade['grade']; ?>">
                                                    <?php echo $grade['grade']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($grade['date']); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                                Нет последних оценок.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AOS
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 800,
                    once: true,
                    offset: 50
                });
            }
            
            // Initialize Feather Icons
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });
    </script>
</body>
</html>
