<?php
session_start();

// Проверка роли (только teacher или admin)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

include 'config.php';

// Проверка соединения с базой данных
if (!$conn || $conn->connect_error) {
    die("Ошибка подключения к базе данных: " . ($conn ? $conn->connect_error : 'Объект подключения отсутствует'));
}

// Фильтры
$selected_student = (int)($_GET['student'] ?? 0);
$selected_course = (int)($_GET['course'] ?? 0);

// Получение списка студентов для фильтра
$students_result = $conn->query("SELECT id, username, full_name FROM users WHERE role = 'student' ORDER BY full_name");
$students = $students_result->fetch_all(MYSQLI_ASSOC);

// Получение списка курсов (для admin — все, для teacher — только свои)
$where_course = ($role === 'teacher') ? "WHERE teacher_id = $user_id" : "";
$courses_result = $conn->query("SELECT id, name FROM courses $where_course ORDER BY name");
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);

// Получение списка допов для выставления оценок
$where_clause = ($role === 'teacher') ? "AND c.teacher_id = $user_id" : "";
if ($selected_student > 0) {
    $where_clause .= " AND e.student_id = $selected_student";
}
if ($selected_course > 0) {
    $where_clause .= " AND e.course_id = $selected_course";
}
$result = $conn->query("
    SELECT e.id AS enrollment_id, u.username AS student_name, u.full_name, u.id AS student_id, 
           c.name AS course_name, c.id AS course_id
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.status = 'approved' $where_clause
    ORDER BY u.full_name, c.name
");
if (!$result) {
    die("Ошибка запроса: " . $conn->error);
}
$enrollments = $result->fetch_all(MYSQLI_ASSOC);

// Обработка массового выставления оценок
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grades'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        logSecurityEvent('csrf_token_invalid', ['action' => 'add_grades']);
        die("Ошибка CSRF-токена");
    }

    $selected_enrollments = $_POST['enrollments'] ?? [];
    $grade = (int)($_POST['grade'] ?? -1);
    $date = sanitizeInput($_POST['grade_date'] ?? '');

    // Валидация
    if (empty($selected_enrollments)) {
        $error = "Выберите хотя бы один доп.";
    } elseif (!in_array($grade, [0, 1, 2])) {
        $error = "Неверная оценка. Допустимые значения: 0, 1, 2.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
        $error = "Неверный формат даты (должен быть ГГГГ-ММ-ДД).";
    } else {
        $success_count = 0;
        foreach ($selected_enrollments as $enrollment_id) {
            $enrollment_id = (int)$enrollment_id;

            // Проверка прав (для teacher — только свои курсы)
            $check_stmt = $conn->prepare("
                SELECT 1 FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                WHERE e.id = ? AND e.status = 'approved'" . ($role === 'teacher' ? " AND c.teacher_id = ?" : "")
            );
            if ($role === 'teacher') {
                $check_stmt->bind_param("ii", $enrollment_id, $user_id);
            } else {
                $check_stmt->bind_param("i", $enrollment_id);
            }
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows === 0) {
                $error = "Недостаточно прав для одного или нескольких допов.";
                logSecurityEvent('unauthorized_grade_attempt', ['enrollment_id' => $enrollment_id, 'user_id' => $user_id]);
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();

            // Вставка оценки
            $stmt = $conn->prepare("INSERT INTO grades (enrollment_id, grade, date) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $enrollment_id, $grade, $date);
            try {
                $stmt->execute();
                $success_count++;
            } catch (Exception $e) {
                $error = "Ошибка: " . $e->getMessage();
                error_log("Ошибка вставки оценки для enrollment_id=$enrollment_id: " . $e->getMessage());
                $stmt->close();
                break;
            }
            $stmt->close();
        }
        
        if ($success_count > 0 && !isset($error)) {
            $success = "Успешно выставлено оценок: $success_count";
            logSecurityEvent('grades_added', ['count' => $success_count, 'grade' => $grade]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выставить баллы - EduTrack+</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .card-hover:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        }
        .grade-badge-0 { background-color: #fef2f2; color: #dc2626; }
        .grade-badge-1 { background-color: #fffbeb; color: #d97706; }
        .grade-badge-2 { background-color: #f0fdf4; color: #16a34a; }
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
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Выставление баллов</h2>
                        <p class="text-sm text-gray-600 mt-1">Управление оценками учащихся</p>
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

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6" data-aos="fade-up">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i data-feather="filter" class="w-5 h-5 mr-2 text-indigo-600"></i>
                        Фильтры
                    </h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-feather="user" class="w-4 h-4 inline mr-1"></i>
                                Фильтр по студенту
                            </label>
                            <select name="student" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" onchange="this.form.submit()">
                                <option value="0">Все студенты</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo $selected_student == $student['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['full_name'] ?? $student['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-feather="book" class="w-4 h-4 inline mr-1"></i>
                                Фильтр по курсу
                            </label>
                            <select name="course" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" onchange="this.form.submit()">
                                <option value="0">Все курсы</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo $selected_course == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- Grade Assignment Form -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden" data-aos="fade-up" data-aos-delay="100">
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i data-feather="edit" class="w-5 h-5 mr-2 text-indigo-600"></i>
                            Массовое выставление оценок
                        </h3>
                    </div>
                    
                    <form method="POST" class="p-4 sm:p-6">
                        <input type="hidden" name="add_grades" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        
                        <!-- Grade Selection -->
                        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-feather="star" class="w-4 h-4 inline mr-1"></i>
                                    Оценка
                                </label>
                                <select name="grade" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Выберите оценку</option>
                                    <option value="2" class="text-green-700">2 (Отлично)</option>
                                    <option value="1" class="text-yellow-700">1 (Хорошо)</option>
                                    <option value="0" class="text-red-700">0 (Не зачтено)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-feather="calendar" class="w-4 h-4 inline mr-1"></i>
                                    Дата выставления
                                </label>
                                <input type="date" name="grade_date" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <!-- Students Selection -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-md font-medium text-gray-900">Выберите студентов</h4>
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="select-all" class="text-sm text-gray-600">Выбрать всех</label>
                                </div>
                            </div>
                            
                            <?php if (count($enrollments) > 0): ?>
                                <!-- Mobile Cards View -->
                                <div class="sm:hidden space-y-3 max-h-96 overflow-y-auto">
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                            <div class="flex items-start space-x-3">
                                                <input type="checkbox" name="enrollments[]" value="<?php echo $enrollment['enrollment_id']; ?>" 
                                                       class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 enrollment-checkbox">
                                                <div class="flex-1 min-w-0">
                                                    <h5 class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($enrollment['full_name'] ?? $enrollment['student_name']); ?>
                                                    </h5>
                                                    <p class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($enrollment['course_name']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        @<?php echo htmlspecialchars($enrollment['student_name']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Desktop Table View -->
                                <div class="hidden sm:block overflow-hidden border border-gray-200 rounded-lg">
                                    <div class="max-h-96 overflow-y-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50 sticky top-0">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        <input type="checkbox" id="select-all-desktop" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                    </th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Учащийся</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дополнительное занятие</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($enrollments as $enrollment): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <input type="checkbox" name="enrollments[]" value="<?php echo $enrollment['enrollment_id']; ?>" 
                                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 enrollment-checkbox">
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="flex items-center">
                                                                <div class="flex-shrink-0 h-8 w-8">
                                                                    <div class="h-8 w-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                                                        <i data-feather="user" class="w-4 h-4 text-indigo-600"></i>
                                                                    </div>
                                                                </div>
                                                                <div class="ml-3">
                                                                    <div class="text-sm font-medium text-gray-900">
                                                                        <?php echo htmlspecialchars($enrollment['full_name'] ?? $enrollment['student_name']); ?>
                                                                    </div>
                                                                    <div class="text-sm text-gray-500">
                                                                        @<?php echo htmlspecialchars($enrollment['student_name']); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            <?php echo htmlspecialchars($enrollment['course_name']); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <button type="submit" 
                                            class="w-full sm:w-auto bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-lg font-medium hover:from-indigo-700 hover:to-purple-700 transition-all duration-300 flex items-center justify-center"
                                            onclick="return confirm('Выставить выбранные оценки?');">
                                        <i data-feather="check" class="w-5 h-5 mr-2"></i>
                                        Выставить оценки
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                        <i data-feather="users" class="w-8 h-8 text-gray-400"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">Нет доступных записей</h3>
                                    <p class="text-gray-600">Нет учащихся для выставления оценок.</p>
                                    <?php if ($selected_student || $selected_course): ?>
                                        <p class="text-sm text-gray-500 mt-2">Попробуйте изменить фильтры.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Recent Grades -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden" data-aos="fade-up" data-aos-delay="200">
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i data-feather="clock" class="w-5 h-5 mr-2 text-purple-600"></i>
                            Последние выставленные оценки
                        </h3>
                    </div>
                    
                    <div class="p-4 sm:p-6">
                        <?php
                        $where_clause = ($role === 'teacher') ? "AND c.teacher_id = $user_id" : "";
                        $result = $conn->query("
                            SELECT u.username AS student_name, u.full_name, c.name AS course_name, g.grade, g.date
                            FROM grades g
                            JOIN enrollments e ON g.enrollment_id = e.id
                            JOIN users u ON e.student_id = u.id
                            JOIN courses c ON e.course_id = c.id
                            WHERE 1=1 $where_clause
                            ORDER BY g.date DESC, g.id DESC
                            LIMIT 10
                        ");
                        ?>
                        
                        <!-- Mobile Cards View -->
                        <div class="sm:hidden space-y-3">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($grade = $result->fetch_assoc()): ?>
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                        <div class="flex justify-between items-start mb-2">
                                            <div class="flex-1 min-w-0">
                                                <h5 class="font-medium text-gray-900 truncate">
                                                    <?php echo htmlspecialchars($grade['full_name'] ?? $grade['student_name']); ?>
                                                </h5>
                                                <p class="text-sm text-gray-600 truncate">
                                                    <?php echo htmlspecialchars($grade['course_name']); ?>
                                                </p>
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
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i data-feather="star" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                                    <p>Нет выставленных оценок</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Desktop Table View -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Учащийся</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дополнительное занятие</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Оценка</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php
                                    // Reset result pointer for desktop table
                                    $result = $conn->query("
                                        SELECT u.username AS student_name, u.full_name, c.name AS course_name, g.grade, g.date
                                        FROM grades g
                                        JOIN enrollments e ON g.enrollment_id = e.id
                                        JOIN users u ON e.student_id = u.id
                                        JOIN courses c ON e.course_id = c.id
                                        WHERE 1=1 $where_clause
                                        ORDER BY g.date DESC, g.id DESC
                                        LIMIT 10
                                    ");
                                    if ($result && $result->num_rows > 0):
                                        while ($grade = $result->fetch_assoc()):
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($grade['full_name'] ?? $grade['student_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($grade['course_name']); ?>
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
                                                Нет выставленных оценок.
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

            // Select all functionality for mobile
            const selectAllMobile = document.getElementById('select-all');
            const selectAllDesktop = document.getElementById('select-all-desktop');
            const enrollmentCheckboxes = document.querySelectorAll('.enrollment-checkbox');
            
            function toggleAll(checked) {
                enrollmentCheckboxes.forEach(checkbox => {
                    checkbox.checked = checked;
                });
            }
            
            if (selectAllMobile) {
                selectAllMobile.addEventListener('change', function() {
                    toggleAll(this.checked);
                    if (selectAllDesktop) selectAllDesktop.checked = this.checked;
                });
            }
            
            if (selectAllDesktop) {
                selectAllDesktop.addEventListener('change', function() {
                    toggleAll(this.checked);
                    if (selectAllMobile) selectAllMobile.checked = this.checked;
                });
            }

            // Update select all checkboxes when individual checkboxes change
            enrollmentCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const checkedCount = document.querySelectorAll('.enrollment-checkbox:checked').length;
                    const totalCount = enrollmentCheckboxes.length;
                    const allChecked = checkedCount === totalCount;
                    
                    if (selectAllMobile) selectAllMobile.checked = allChecked;
                    if (selectAllDesktop) selectAllDesktop.checked = allChecked;
                });
            });
        });
    </script>
</body>
</html>
