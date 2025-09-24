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
    $result = $conn->query("
        SELECT c.name AS course_name, g.grade, g.date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN grades g ON e.id = g.enrollment_id
        WHERE e.student_id = $user_id AND e.status = 'approved'
        ORDER BY c.name
    ");
    if ($result) {
        $stats['courses'] = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Ошибка запроса допов студента: " . $conn->error);
        $stats['courses'] = [];
    }
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
                $stmt_enroll->bind_param("is", $user_id, $course_id);
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm">
                <div class="px-6 py-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Главная</h2>
                </div>
            </header>
            <main class="p-6">
                <?php if (isset($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($success_student)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($success_student); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error_student)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($error_student); ?>
                    </div>
                <?php endif; ?>

                <!-- Статистика для студента -->
                <?php if ($role === 'student'): ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Ваша статистика</h3>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-indigo-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">Текущие допы</p>
                                <p class="text-2xl font-bold text-indigo-700"><?php echo $stats['current_courses']; ?></p>
                            </div>
                            <div class="bg-indigo-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">Средний балл</p>
                                <p class="text-2xl font-bold text-indigo-700"><?php echo $stats['avg_grade']; ?></p>
                            </div>
                            <div class="bg-indigo-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">Заявки на рассмотрении</p>
                                <p class="text-2xl font-bold text-indigo-700"><?php echo $stats['pending_requests']; ?></p>
                            </div>
                        </div>
                        <div class="px-6 py-4">
                            <h4 class="text-md font-medium text-gray-800 mb-3">Ваши допы и оценки</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Доп. занятие</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Оценка</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (count($stats['courses']) > 0): ?>
                                            <?php foreach ($stats['courses'] as $course): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($course['grade'] !== null): ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($course['grade'] == 2 ? 'bg-green-100 text-green-800' : ($course['grade'] == 1 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')); ?>">
                                                                <?php echo $course['grade']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-sm text-gray-500">Нет оценки</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $course['date'] ? htmlspecialchars($course['date']) : '-'; ?>
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
                <?php endif; ?>

                <!-- Форма добавления аккаунта (для admin/teacher) -->
                <?php if ($role == 'admin' || $role == 'teacher'): ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Добавить аккаунт</h3>
                        </div>
                        <form method="POST" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="add_user" value="1">
                            <input type="text" name="username" placeholder="Логин" required class="border border-gray-300 rounded px-3 py-2">
                            <input type="password" name="password" placeholder="Пароль" required class="border border-gray-300 rounded px-3 py-2">
                            <input type="text" name="full_name" placeholder="ФИО" required class="border border-gray-300 rounded px-3 py-2">
                            <input type="email" name="email" placeholder="Email" required class="border border-gray-300 rounded px-3 py-2">
                            <input type="text" name="class" placeholder="Класс (для студентов)" class="border border-gray-300 rounded px-3 py-2">
                            <?php if ($role == 'admin'): ?>
                                <select name="role" class="border border-gray-300 rounded px-3 py-2">
                                    <option value="student">Учащийся</option>
                                    <option value="teacher">Преподаватель</option>
                                    <option value="admin">Администратор</option>
                                </select>
                            <?php else: ?>
                                <input type="hidden" name="role" value="student">
                            <?php endif; ?>
                            <button type="submit" class="md:col-span-2 bg-indigo-600 text-white py-2 rounded-md hover:bg-indigo-700">Добавить</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Форма добавления курса (для admin/teacher) -->
                <?php if ($role == 'admin' || $role == 'teacher'): ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Добавить доп. занятие</h3>
                        </div>
                        <form method="POST" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="add_course" value="1">
                            <input type="text" name="name" placeholder="Название" required class="border border-gray-300 rounded px-3 py-2">
                            <input type="text" name="description" placeholder="Описание" class="border border-gray-300 rounded px-3 py-2">
                            <input type="text" name="schedule" placeholder="Расписание" class="border border-gray-300 rounded px-3 py-2">
                            <?php if ($role == 'admin'): ?>
                                <select name="teacher_id" required class="border border-gray-300 rounded px-3 py-2">
                                    <option value="">Выберите преподавателя</option>
                                    <?php
                                    $result = $conn->query("SELECT id, full_name FROM users WHERE role = 'teacher'");
                                    while ($teacher = $result->fetch_assoc()) {
                                        echo "<option value='{$teacher['id']}'>" . htmlspecialchars($teacher['full_name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            <?php else: ?>
                                <input type="hidden" name="teacher_id" value="<?php echo $user_id; ?>">
                            <?php endif; ?>
                            <button type="submit" class="md:col-span-2 bg-indigo-600 text-white py-2 rounded-md hover:bg-indigo-700">Добавить</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Последние оценки (для всех ролей, но для студента — только свои) -->
                <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Последние оценки</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Учащийся</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Доп. занятие</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Балл</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
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
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($grade['full_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($grade['course']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($grade['grade'] == 2 ? 'bg-green-100 text-green-800' : ($grade['grade'] == 1 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')); ?>">
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
            </main>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        if (typeof AOS !== 'undefined') {
            AOS.init({ duration: 1000, once: true });
        }
    </script>
</body>
</html>