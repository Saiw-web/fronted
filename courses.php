<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
include 'config.php';

// Проверка соединения MySQLi
if (!$conn || $conn->connect_error) {
    error_log("courses.php - Ошибка подключения MySQLi: " . ($conn ? $conn->connect_error : 'Объект подключения отсутствует'));
    die("Ошибка подключения к базе данных");
}

// Отладка сессии
error_log("courses.php - User ID: {$_SESSION['user_id']}, Role: {$_SESSION['role']}");

$message = $_GET['msg'] ?? '';

// Получение списка курсов
if (strtolower($role) === 'student') {
    $stmt = $conn->prepare("SELECT e.id AS enroll_id, c.id AS course_id, c.name
                            FROM enrollments e 
                            JOIN courses c ON e.course_id = c.id 
                            WHERE e.student_id = ? AND LOWER(e.status) = 'approved'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $enrollments = $stmt->get_result();
    $enrollments_data = $enrollments->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    error_log("courses.php - Количество записей (student): " . count($enrollments_data));
    error_log("courses.php - Данные записей: " . json_encode($enrollments_data, JSON_UNESCAPED_UNICODE));
    error_log("courses.php - Тип массива \$enrollments_data: " . gettype($enrollments_data));
    error_log("courses.php - Проверка foreach: Начинается");
    if (count($enrollments_data) > 0) {
        error_log("courses.php - В foreach вошли " . count($enrollments_data) . " записей");
        error_log("courses.php - Первый элемент: " . json_encode($enrollments_data[0], JSON_UNESCAPED_UNICODE));
    } else {
        error_log("courses.php - В foreach НЕ вошли, массив пуст");
    }
} else {
    $query = "SELECT c.id, c.name, c.description, c.schedule, u.full_name AS teacher_name
              FROM courses c
              LEFT JOIN users u ON c.teacher_id = u.id";
    if (strtolower($role) === 'teacher') {
        $query .= " WHERE c.teacher_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query($query);
    }
    if (!$result) {
        error_log("courses.php - Ошибка запроса: " . $conn->error);
        die("Ошибка выполнения запроса");
    }
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    
    error_log("courses.php - Количество курсов (admin/teacher): " . count($courses));
    error_log("courses.php - Данные курсов: " . json_encode($courses, JSON_UNESCAPED_UNICODE));
    error_log("courses.php - Тип массива \$courses: " . gettype($courses));
    error_log("courses.php - Проверка foreach: Начинается");
    if (count($courses) > 0) {
        error_log("courses.php - В foreach вошли " . count($courses) . " записей");
        error_log("courses.php - Первый элемент: " . json_encode($courses[0], JSON_UNESCAPED_UNICODE));
    } else {
        error_log("courses.php - В foreach НЕ вошли, массив пуст");
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (strtolower($role) === 'student' ? 'Мои допы' : 'Доп. занятия'); ?> - EduTrack+</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css" />
    <script src="https://unpkg.com/feather-icons@4.28.0/dist/feather.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">
                    <?php echo (strtolower($role) === 'student' ? 'Мои дополнительные занятия' : 'Дополнительные занятия'); ?>
                </h1>


                <?php if ($message): ?>
                    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if (strtolower($role) === 'student'): ?>
                    <div class="bg-white shadow-md rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Название допа</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($enrollments_data) > 0): ?>
                                    <?php foreach ($enrollments_data as $row): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($row['name'] ?? '—'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <a href="drop_course.php?enroll_id=<?php echo (int)($row['enroll_id'] ?? 0); ?>" 
                                                   class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                                                   onclick="return confirm('Вы уверены, что хотите выписаться?');">
                                                    Выписаться
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="px-6 py-4 text-center text-gray-500">
                                            У вас нет записанных допов. Проверьте таблицу enrollments (LOWER(status) = 'approved').
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="bg-white shadow-md rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Список дополнительных занятий</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Название</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Описание</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Преподаватель</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Расписание</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (count($courses) > 0): ?>
                                        <?php foreach ($courses as $course): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($course['name'] ?? '—'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($course['description'] ?? '—'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($course['teacher_name'] ?? '—'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($course['schedule'] ?? '—'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                                Нет курсов для отображения. Проверьте таблицу courses.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        // Инициализация Feather Icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        } else {
            console.error('Feather Icons не загружен');
        }

        // Инициализация AOS
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 1000,
                once: true
            });
        } else {
            console.error('AOS не загружен');
        }
    </script>
</body>
</html>