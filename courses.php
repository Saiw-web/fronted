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

$message = $_GET['msg'] ?? '';

// Получение списка курсов
if (strtolower($role) === 'student') {
    $stmt = $conn->prepare("SELECT e.id AS enroll_id, c.id AS course_id, c.name, c.description, c.schedule
                            FROM enrollments e 
                            JOIN courses c ON e.course_id = c.id 
                            WHERE e.student_id = ? AND LOWER(e.status) = 'approved'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $enrollments = $stmt->get_result();
    $enrollments_data = $enrollments->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $query = "SELECT c.id, c.name, c.description, c.schedule, u.full_name AS teacher_name,
                     COUNT(e.id) as enrolled_count
              FROM courses c
              LEFT JOIN users u ON c.teacher_id = u.id
              LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'approved'";
    if (strtolower($role) === 'teacher') {
        $query .= " WHERE c.teacher_id = ?";
        $query .= " GROUP BY c.id, c.name, c.description, c.schedule, u.full_name";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $query .= " GROUP BY c.id, c.name, c.description, c.schedule, u.full_name";
        $result = $conn->query($query);
    }
    if (!$result) {
        error_log("courses.php - Ошибка запроса: " . $conn->error);
        die("Ошибка выполнения запроса");
    }
    $courses = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (strtolower($role) === 'student' ? 'Мои допы' : 'Дополнительные занятия'); ?> - EduTrack+</title>
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
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">
                            <?php echo (strtolower($role) === 'student' ? 'Мои дополнительные занятия' : 'Дополнительные занятия'); ?>
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php echo (strtolower($role) === 'student' ? 'Ваши текущие записи' : 'Управление курсами'); ?>
                        </p>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <?php if ($message): ?>
                    <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md" data-aos="fade-down">
                        <div class="flex">
                            <i data-feather="info" class="w-5 h-5 text-blue-400 mr-2 flex-shrink-0 mt-0.5"></i>
                            <p class="text-blue-700"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (strtolower($role) === 'student'): ?>
                    <!-- Student View - Mobile Optimized -->
                    <div class="space-y-4">
                        <?php if (count($enrollments_data) > 0): ?>
                            <!-- Mobile Cards View -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                                <?php foreach ($enrollments_data as $row): ?>
                                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden card-hover transition-all duration-300" data-aos="fade-up">
                                        <div class="p-4 sm:p-6">
                                            <div class="flex items-start justify-between mb-4">
                                                <div class="flex-1 min-w-0">
                                                    <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                                                        <?php echo htmlspecialchars($row['name'] ?? '—'); ?>
                                                    </h3>
                                                    <?php if (!empty($row['description'])): ?>
                                                        <p class="text-sm text-gray-600 mb-3 line-clamp-3">
                                                            <?php echo htmlspecialchars($row['description']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($row['schedule'])): ?>
                                                        <div class="flex items-center text-sm text-gray-500 mb-4">
                                                            <i data-feather="calendar" class="w-4 h-4 mr-2"></i>
                                                            <?php echo htmlspecialchars($row['schedule']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex flex-col sm:flex-row gap-2">
                                                <a href="drop_course.php?enroll_id=<?php echo (int)($row['enroll_id'] ?? 0); ?>" 
                                                   class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-center flex items-center justify-center"
                                                   onclick="return confirm('Вы уверены, что хотите выписаться?');">
                                                    <i data-feather="user-minus" class="w-4 h-4 mr-2"></i>
                                                    Выписаться
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-xl shadow-sm p-8 text-center" data-aos="fade-up">
                                <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i data-feather="book" class="w-8 h-8 text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Нет записей</h3>
                                <p class="text-gray-600">У вас нет записанных дополнительных занятий.</p>
                                <a href="index.php" class="inline-flex items-center mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                    <i data-feather="plus" class="w-4 h-4 mr-2"></i>
                                    Записаться на занятия
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Admin/Teacher View -->
                    <div class="space-y-6">
                        <!-- Quick Stats -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" data-aos="fade-up">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4 sm:p-6 rounded-xl text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-blue-100 text-sm font-medium">Всего курсов</p>
                                        <p class="text-2xl font-bold"><?php echo count($courses); ?></p>
                                    </div>
                                    <i data-feather="book-open" class="w-8 h-8 text-blue-200"></i>
                                </div>
                            </div>
                            <div class="bg-gradient-to-r from-green-500 to-green-600 p-4 sm:p-6 rounded-xl text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-green-100 text-sm font-medium">Записей</p>
                                        <p class="text-2xl font-bold">
                                            <?php 
                                            $total_enrolled = array_sum(array_column($courses, 'enrolled_count'));
                                            echo $total_enrolled; 
                                            ?>
                                        </p>
                                    </div>
                                    <i data-feather="users" class="w-8 h-8 text-green-200"></i>
                                </div>
                            </div>
                            <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-4 sm:p-6 rounded-xl text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-purple-100 text-sm font-medium">Средняя заполненность</p>
                                        <p class="text-2xl font-bold">
                                            <?php 
                                            echo count($courses) > 0 ? round($total_enrolled / count($courses), 1) : 0; 
                                            ?>
                                        </p>
                                    </div>
                                    <i data-feather="trending-up" class="w-8 h-8 text-purple-200"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Courses Grid/List -->
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden" data-aos="fade-up" data-aos-delay="100">
                            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-feather="list" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Список дополнительных занятий
                                </h3>
                            </div>
                            
                            <?php if (count($courses) > 0): ?>
                                <!-- Mobile Cards View -->
                                <div class="sm:hidden divide-y divide-gray-200">
                                    <?php foreach ($courses as $course): ?>
                                        <div class="p-4 hover:bg-gray-50">
                                            <div class="flex justify-between items-start mb-2">
                                                <h4 class="font-medium text-gray-900 flex-1 pr-2">
                                                    <?php echo htmlspecialchars($course['name'] ?? '—'); ?>
                                                </h4>
                                                <span class="px-2 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 rounded-full">
                                                    <?php echo $course['enrolled_count']; ?> записей
                                                </span>
                                            </div>
                                            <?php if (!empty($course['description'])): ?>
                                                <p class="text-sm text-gray-600 mb-2">
                                                    <?php echo htmlspecialchars($course['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="flex flex-col space-y-1">
                                                <?php if (!empty($course['teacher_name'])): ?>
                                                    <div class="flex items-center text-sm text-gray-500">
                                                        <i data-feather="user" class="w-4 h-4 mr-1"></i>
                                                        <?php echo htmlspecialchars($course['teacher_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($course['schedule'])): ?>
                                                    <div class="flex items-center text-sm text-gray-500">
                                                        <i data-feather="calendar" class="w-4 h-4 mr-1"></i>
                                                        <?php echo htmlspecialchars($course['schedule']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Desktop Table View -->
                                <div class="hidden sm:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Название</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Описание</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Преподаватель</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Расписание</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Записей</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($courses as $course): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($course['name'] ?? '—'); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm text-gray-900 max-w-xs truncate">
                                                            <?php echo htmlspecialchars($course['description'] ?? '—'); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <div class="flex items-center">
                                                            <i data-feather="user" class="w-4 h-4 mr-2"></i>
                                                            <?php echo htmlspecialchars($course['teacher_name'] ?? '—'); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 text-sm text-gray-500">
                                                        <div class="flex items-center">
                                                            <i data-feather="calendar" class="w-4 h-4 mr-2"></i>
                                                            <?php echo htmlspecialchars($course['schedule'] ?? '—'); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                                            <?php echo $course['enrolled_count']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-8 text-center">
                                    <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                        <i data-feather="book-open" class="w-8 h-8 text-gray-400"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">Нет курсов</h3>
                                    <p class="text-gray-600 mb-4">Пока не создано ни одного дополнительного занятия.</p>
                                    <a href="index.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                        <i data-feather="plus" class="w-4 h-4 mr-2"></i>
                                        Добавить занятие
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
