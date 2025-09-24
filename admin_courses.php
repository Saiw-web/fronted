<?php
session_start();

// Проверка роли администратора
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

include 'config.php';

// Проверка соединения с базой данных
if (!$conn || $conn->connect_error) {
    die("Ошибка подключения к базе данных: " . ($conn ? $conn->connect_error : 'Объект подключения отсутствует'));
}

// Установка CSRF-токена, если не установлен
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Обработка добавления курса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if ($csrf_token !== $_SESSION['csrf_token']) {
        die("Ошибка CSRF-токена");
    }

    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $schedule = filter_input(INPUT_POST, 'schedule', FILTER_SANITIZE_STRING);
    $teacher_id = (int)$_POST['teacher_id'];

    $stmt = $conn->prepare("INSERT INTO courses (name, description, teacher_id, schedule) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        die("Ошибка подготовки запроса: " . $conn->error);
    }
    $stmt->bind_param("ssis", $name, $description, $teacher_id, $schedule);
    try {
        $stmt->execute();
        $message = "Курс успешно добавлен!";
    } catch (Exception $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
    $stmt->close();
}

// Обработка удаления курса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if ($csrf_token !== $_SESSION['csrf_token']) {
        die("Ошибка CSRF-токена");
    }

    $course_id = (int)$_POST['course_id'];
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    if (!$stmt) {
        die("Ошибка подготовки запроса: " . $conn->error);
    }
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $stmt->close();
    $message = "Курс удален!";
}

// Получение списка курсов и преподавателей
$result = $conn->query("
    SELECT c.id, c.name, c.description, c.schedule, u.full_name AS teacher_name
    FROM courses c
    LEFT JOIN users u ON c.teacher_id = u.id
    ORDER BY c.name
");
if (!$result) {
    die("Ошибка выполнения запроса: " . $conn->error);
}
$courses = $result->fetch_all(MYSQLI_ASSOC);

// Получение списка преподавателей для формы
$teacher_result = $conn->query("SELECT id, full_name FROM users WHERE role = 'teacher'");
if (!$teacher_result) {
    die("Ошибка выполнения запроса: " . $conn->error);
}
$teachers = $teacher_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление дополнительными занятиями - EduTrack+</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Управление дополнительными занятиями</h1>

                <?php if (isset($message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Форма добавления курса -->
                <div class="bg-white rounded-lg shadow p-6 mb-6" data-aos="fade-up">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Добавить новый доп</h3>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="add_course" value="1">
                        <input type="text" name="name" placeholder="Название допа (например, 'Робототехника')" required class="px-3 py-2 border rounded-md focus:ring-indigo-500">
                        <input type="text" name="description" placeholder="Описание" class="px-3 py-2 border rounded-md focus:ring-indigo-500">
                        <input type="text" name="schedule" placeholder="Расписание (например, 'Пн 15:00')" required class="px-3 py-2 border rounded-md focus:ring-indigo-500">
                        <select name="teacher_id" class="px-3 py-2 border rounded-md focus:ring-indigo-500" required>
                            <option value="">Выберите преподавателя</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="md:col-span-2 bg-indigo-600 text-white py-2 rounded-md hover:bg-indigo-700 transition-colors">
                            Добавить
                        </button>
                    </form>
                </div>

                <!-- Список курсов -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($courses) > 0): ?>
                                    <?php foreach ($courses as $course): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($course['name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($course['description'] ?: '—'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($course['teacher_name'] ?: '—'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($course['schedule'] ?: '—'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <form method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить этот курс?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="delete_course" value="1">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800">Удалить</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">Нет курсов для отображения.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>