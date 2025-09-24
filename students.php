<?php
session_start();

// Проверка роли
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'teacher'])) {
    header('Location: index.php');
    exit;
}

include 'config.php';

// Проверка подключения PDO
if (!isset($pdo)) {
    error_log("students.php - Ошибка: PDO-объект не инициализирован");
    die("Ошибка: PDO-объект не инициализирован. Проверьте config.php.");
}

// Отладка сессии
error_log("students.php - User ID: {$_SESSION['user_id']}, Role: {$_SESSION['role']}");

// Установка CSRF-токена
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Функция verifyCsrf
function verifyCsrf($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Ошибка CSRF-токена");
    }
}

$role = $_SESSION['role'];
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);

// Получение списка студентов
try {
    if ($search) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(role) = 'student' AND full_name LIKE ? ORDER BY full_name");
        $stmt->execute(['%' . $search . '%']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(role) = 'student' ORDER BY full_name");
        $stmt->execute();
    }
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Отладка: проверка данных
    error_log("students.php - Количество студентов: " . count($students));
    error_log("students.php - Данные студентов: " . json_encode($students, JSON_UNESCAPED_UNICODE));
    error_log("students.php - Тип массива \$students: " . gettype($students));
    error_log("students.php - Проверка foreach: Начинается");
    if (count($students) > 0) {
        error_log("students.php - В foreach вошли " . count($students) . " записей");
        error_log("students.php - Первый элемент: " . json_encode($students[0], JSON_UNESCAPED_UNICODE));
    } else {
        error_log("students.php - В foreach НЕ вошли, массив пуст");
    }
} catch (PDOException $e) {
    error_log("students.php - Ошибка запроса: " . $e->getMessage());
    die("Ошибка выполнения запроса: " . $e->getMessage());
}

// Обработка удаления студента
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_student']) && strtolower($role) == 'admin') {
    verifyCsrf($_POST['csrf_token']);
    $student_id = (int)$_POST['student_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND LOWER(role) = 'student'");
        $stmt->execute([$student_id]);
        header('Location: students.php?success=deleted');
        exit;
    } catch (PDOException $e) {
        error_log("students.php - Ошибка удаления: " . $e->getMessage());
        die("Ошибка удаления студента: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список учащихся - EduTrack+</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css" />
    <script src="https://unpkg.com/feather-icons@4.28.0/dist/feather.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="flex-1 overflow-y-auto">
        <header class="bg-white shadow-sm">
            <div class="px-6 py-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Учащиеся</h2>
                <div class="flex items-center space-x-4">
                    <button class="p-2 rounded-full text-gray-500 hover:text-gray-600 hover:bg-gray-100">
                        <i data-feather="bell" class="w-5 h-5"></i>
                    </button>
                    <button class="p-2 rounded-full text-gray-500 hover:text-gray-600 hover:bg-gray-100">
                        <i data-feather="help-circle" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
        </header>

        <main class="p-6">
            <!-- Отладка для теста -->


            <div class="mb-6 flex justify-between items-center">
                <form class="relative w-full max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-feather="search" class="h-5 w-5 text-gray-400"></i>
                    </div>
                    <input type="text" name="search" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Поиск учащихся..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    <button type="submit" class="hidden"></button>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Список учащихся</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Учащийся</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Класс</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Допы</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Средний балл</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $student): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <img class="h-10 w-10 rounded-full" src="http://static.photos/people/200x200/<?php echo htmlspecialchars($student['id'] ?? ''); ?>" alt="">
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name'] ?? 'Не указано'); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['email'] ?? '—'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($student['class'] ?? '—'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-wrap gap-1">
                                                <?php
                                                $stmt = $pdo->prepare("SELECT c.name FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? AND LOWER(e.status) = 'approved'");
                                                $stmt->execute([$student['id']]);
                                                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                error_log("students.php - Курсы для студента ID {$student['id']}: " . json_encode($courses, JSON_UNESCAPED_UNICODE));
                                                foreach ($courses as $course):
                                                ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo htmlspecialchars($course['name'] ?? '—'); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $stmt = $pdo->prepare("SELECT AVG(CAST(grade AS DECIMAL)) FROM grades g JOIN enrollments e ON g.enrollment_id = e.id WHERE e.student_id = ?");
                                            $stmt->execute([$student['id']]);
                                            $avg = number_format($stmt->fetchColumn() ?: 0, 2);
                                            ?>
                                            <div class="text-sm text-gray-900"><?php echo $avg; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button class="text-indigo-600 hover:text-indigo-900 mr-3">Редактировать</button>
                                            <?php if (strtolower($role) == 'admin'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['id'] ?? ''); ?>">
                                                    <button type="submit" name="delete_student" class="text-red-600 hover:text-red-900">Удалить</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        Нет учащихся для отображения. Проверьте таблицу users (LOWER(role) = 'student').
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
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