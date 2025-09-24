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
$success_msg = $_GET['success'] ?? '';

// Получение списка студентов
try {
    if ($search) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(role) = 'student' AND (full_name LIKE ? OR username LIKE ?) ORDER BY full_name");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(role) = 'student' ORDER BY full_name");
        $stmt->execute();
    }
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .card-hover:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
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
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Учащиеся</h2>
                        <p class="text-sm text-gray-600 mt-1">Управление учетными записями учащихся</p>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <!-- Success Message -->
                <?php if ($success_msg === 'deleted'): ?>
                    <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-md" data-aos="fade-down">
                        <div class="flex">
                            <i data-feather="check-circle" class="w-5 h-5 text-green-400 mr-2 flex-shrink-0 mt-0.5"></i>
                            <p class="text-green-700">Учащийся успешно удален.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Search and Actions -->
                <div class="mb-6 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                    <form class="flex-1 max-w-md">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-feather="search" class="h-5 w-5 text-gray-400"></i>
                            </div>
                            <input 
                                type="text" 
                                name="search" 
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
                                placeholder="Поиск по имени или логину..." 
                                value="<?php echo htmlspecialchars($search ?? ''); ?>"
                            >
                            <button type="submit" class="hidden"></button>
                        </div>
                    </form>
                    
                    <!-- Quick Stats -->
                    <div class="flex items-center space-x-4 text-sm">
                        <div class="bg-indigo-100 text-indigo-800 px-3 py-2 rounded-lg font-medium">
                            <i data-feather="users" class="w-4 h-4 inline mr-1"></i>
                            Всего: <?php echo count($students); ?>
                        </div>
                    </div>
                </div>

                <!-- Students List -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden" data-aos="fade-up">
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i data-feather="users" class="w-5 h-5 mr-2 text-indigo-600"></i>
                            Список учащихся
                        </h3>
                    </div>
                    
                    <?php if (count($students) > 0): ?>
                        <!-- Mobile Cards View -->
                        <div class="sm:hidden divide-y divide-gray-200">
                            <?php foreach ($students as $student): ?>
                                <div class="p-4 hover:bg-gray-50">
                                    <div class="flex items-start space-x-4">
                                        <div class="flex-shrink-0">
                                            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                                                <i data-feather="user" class="w-6 h-6 text-indigo-600"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-900 truncate">
                                                        <?php echo htmlspecialchars($student['full_name'] ?? 'Не указано'); ?>
                                                    </h4>
                                                    <p class="text-sm text-gray-500">
                                                        @<?php echo htmlspecialchars($student['username']); ?>
                                                    </p>
                                                </div>
                                                <?php if (!empty($student['class'])): ?>
                                                    <span class="px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">
                                                        <?php echo htmlspecialchars($student['class']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Student courses -->
                                            <div class="mb-3">
                                                <div class="flex flex-wrap gap-1">
                                                    <?php
                                                    $stmt = $pdo->prepare("SELECT c.name FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? AND LOWER(e.status) = 'approved'");
                                                    $stmt->execute([$student['id']]);
                                                    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    if (count($courses) > 0):
                                                        foreach ($courses as $course):
                                                    ?>
                                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">
                                                            <?php echo htmlspecialchars($course['name']); ?>
                                                        </span>
                                                    <?php endforeach; else: ?>
                                                        <span class="text-xs text-gray-500">Нет записей на курсы</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Average grade -->
                                            <div class="mb-3">
                                                <?php
                                                $stmt = $pdo->prepare("SELECT AVG(CAST(grade AS DECIMAL)) as avg_grade FROM grades g JOIN enrollments e ON g.enrollment_id = e.id WHERE e.student_id = ?");
                                                $stmt->execute([$student['id']]);
                                                $avg = $stmt->fetchColumn();
                                                $avg_formatted = $avg ? number_format($avg, 2) : 'Нет оценок';
                                                ?>
                                                <div class="flex items-center text-sm">
                                                    <i data-feather="star" class="w-4 h-4 mr-1 text-yellow-500"></i>
                                                    <span class="text-gray-600">Средний балл: </span>
                                                    <span class="font-medium ml-1"><?php echo $avg_formatted; ?></span>
                                                </div>
                                            </div>
                                            
                                            <!-- Actions -->
                                            <div class="flex flex-col sm:flex-row gap-2">
                                                <button class="flex items-center justify-center px-3 py-2 text-sm text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                                    <i data-feather="edit-2" class="w-4 h-4 mr-1"></i>
                                                    Редактировать
                                                </button>
                                                <?php if (strtolower($role) == 'admin'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['id']); ?>">
                                                        <button type="submit" name="delete_student" 
                                                                onclick="return confirm('Вы уверены, что хотите удалить этого учащегося?')"
                                                                class="w-full flex items-center justify-center px-3 py-2 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                                                            <i data-feather="trash-2" class="w-4 h-4 mr-1"></i>
                                                            Удалить
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table View -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Учащийся</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Класс</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дополнительные занятия</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Средний балл</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($students as $student): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                                            <i data-feather="user" class="w-5 h-5 text-indigo-600"></i>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($student['full_name'] ?? 'Не указано'); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo htmlspecialchars($student['email'] ?? '—'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if (!empty($student['class'])): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        <?php echo htmlspecialchars($student['class']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-sm text-gray-500">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-wrap gap-1">
                                                    <?php
                                                    $stmt = $pdo->prepare("SELECT c.name FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? AND LOWER(e.status) = 'approved'");
                                                    $stmt->execute([$student['id']]);
                                                    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    foreach ($courses as $course):
                                                    ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                            <?php echo htmlspecialchars($course['name']); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($courses) === 0): ?>
                                                        <span class="text-sm text-gray-500">Нет записей</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $stmt = $pdo->prepare("SELECT AVG(CAST(grade AS DECIMAL)) FROM grades g JOIN enrollments e ON g.enrollment_id = e.id WHERE e.student_id = ?");
                                                $stmt->execute([$student['id']]);
                                                $avg = number_format($stmt->fetchColumn() ?: 0, 2);
                                                ?>
                                                <div class="flex items-center">
                                                    <i data-feather="star" class="w-4 h-4 mr-2 text-yellow-500"></i>
                                                    <span class="text-sm text-gray-900"><?php echo $avg; ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex justify-end space-x-2">
                                                    <button class="text-indigo-600 hover:text-indigo-900 p-2 rounded-lg hover:bg-indigo-50 transition-colors">
                                                        <i data-feather="edit-2" class="w-4 h-4"></i>
                                                    </button>
                                                    <?php if (strtolower($role) == 'admin'): ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['id']); ?>">
                                                            <button type="submit" name="delete_student" 
                                                                    onclick="return confirm('Вы уверены, что хотите удалить этого учащегося?')"
                                                                    class="text-red-600 hover:text-red-900 p-2 rounded-lg hover:bg-red-50 transition-colors">
                                                                <i data-feather="trash-2" class="w-4 h-4"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center">
                            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i data-feather="user-x" class="w-8 h-8 text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">
                                <?php echo $search ? 'Учащиеся не найдены' : 'Нет учащихся'; ?>
                            </h3>
                            <p class="text-gray-600 mb-4">
                                <?php echo $search ? 'Попробуйте изменить условия поиска.' : 'Пока не зарегистрировано ни одного учащегося.'; ?>
                            </p>
                            <?php if ($search): ?>
                                <a href="students.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                                    <i data-feather="x" class="w-4 h-4 mr-2"></i>
                                    Очистить поиск
                                </a>
                            <?php else: ?>
                                <a href="index.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                    <i data-feather="user-plus" class="w-4 h-4 mr-2"></i>
                                    Добавить учащегося
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
