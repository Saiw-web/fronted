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

// Установка CSRF-токена
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Фильтры
$selected_student = (int)($_GET['student'] ?? 0);
$selected_course = (int)($_GET['course'] ?? 0);

// Получение списка студентов для фильтра
$students_result = $conn->query("SELECT id, username FROM users WHERE role = 'student' ORDER BY username");
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
    SELECT e.id AS enrollment_id, u.username AS student_name, u.id AS student_id, c.name AS course_name, c.id AS course_id
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.status = 'approved' $where_clause
    ORDER BY u.username, c.name
");
if (!$result) {
    die("Ошибка запроса: " . $conn->error);
}
$enrollments = $result->fetch_all(MYSQLI_ASSOC);

// Обработка массового выставления оценок
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grades'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if ($csrf_token !== $_SESSION['csrf_token']) {
        die("Ошибка CSRF-токена");
    }

    $selected_enrollments = $_POST['enrollments'] ?? [];
    $grade = (int)($_POST['grade'] ?? -1);
    $date = filter_input(INPUT_POST, 'grade_date', FILTER_SANITIZE_STRING) ?? '';

    // Валидация
    if (empty($selected_enrollments)) {
        $error = "Выберите хотя бы один доп.";
    } elseif (!in_array($grade, [0, 1, 2])) {
        $error = "Неверная оценка. Допустимые значения: 0, 1, 2.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
        $error = "Неверный формат даты (должен быть ГГГГ-ММ-ДД).";
    } else {
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
                error_log("Попытка выставления оценки на чужой доп: enrollment_id=$enrollment_id, user_id=$user_id");
                continue;
            }
            $check_stmt->close();

            // Вставка оценки
            $stmt = $conn->prepare("INSERT INTO grades (enrollment_id, grade, date) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $enrollment_id, $grade, $date);
            try {
                $stmt->execute();
                $success = "Оценки успешно выставлены!";
            } catch (Exception $e) {
                $error = "Ошибка: " . $e->getMessage();
                error_log("Ошибка вставки оценки для enrollment_id=$enrollment_id: " . $e->getMessage());
                break;
            }
            $stmt->close();
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Выставить баллы</h1>

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

                <!-- Фильтры -->
                <form method="GET" class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Фильтр по студенту:</label>
                        <select name="student" class="border border-gray-300 rounded px-3 py-2 w-full" onchange="this.form.submit()">
                            <option value="0">Все студенты</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $selected_student == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Фильтр по курсу:</label>
                        <select name="course" class="border border-gray-300 rounded px-3 py-2 w-full" onchange="this.form.submit()">
                            <option value="0">Все курсы</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo $selected_course == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <!-- Форма для массового выставления оценок -->
                <form method="POST" class="bg-white shadow-md rounded-lg p-6 mb-6">
                    <input type="hidden" name="add_grades" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-4 flex space-x-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Оценка:</label>
                            <select name="grade" required class="border border-gray-300 rounded px-3 py-2">
                                <option value="">Выберите оценку</option>
                                <option value="2">2 (Отлично)</option>
                                <option value="1">1 (Хорошо)</option>
                                <option value="0">0 (Не зачтено)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Дата выставления:</label>
                            <input type="date" name="grade_date" required class="border border-gray-300 rounded px-3 py-2" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Учащийся</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Доп. занятие</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($enrollments) > 0): ?>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <input type="checkbox" name="enrollments[]" value="<?php echo $enrollment['enrollment_id']; ?>" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($enrollment['student_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($enrollment['course_name']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">
                                            Нет доступных допов для оценки. <?php if ($selected_student || $selected_course): ?>Попробуйте изменить фильтры.<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($enrollments) > 0): ?>
                        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors" onclick="return confirm('Выставить выбранные оценки?');">
                            Выставить оценки
                        </button>
                    <?php endif; ?>
                </form>

                <!-- Таблица последних выставленных оценок -->
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Последние выставленные оценки</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Учащийся</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Доп. занятие</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Оценка</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                $where_clause = ($role === 'teacher') ? "AND c.teacher_id = $user_id" : "";
                                $result = $conn->query("
                                    SELECT u.username AS student_name, c.name AS course_name, g.grade, g.date
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
                                            <?php echo htmlspecialchars($grade['student_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($grade['course_name']); ?>
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
                                            Нет выставленных оценок.
                                        </td>
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
    <script>
        // Инициализация Feather Icons и AOS
        if (typeof feather !== 'undefined') {
            feather.replace();
        } else {
            console.error('Feather Icons не загружен');
        }
        if (typeof AOS !== 'undefined') {
            AOS.init({ duration: 1000, once: true });
        } else {
            console.error('AOS не загружен');
        }
        // Выбор всех чекбоксов
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="enrollments[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });
    </script>
</body>
</html>