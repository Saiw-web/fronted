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

// Получение списка студентов для фильтра
$students_result = $conn->query("SELECT id, username FROM users WHERE role = 'student' ORDER BY username");
$students = $students_result->fetch_all(MYSQLI_ASSOC);

// Фильтр по студенту
$selected_student = (int)($_GET['student'] ?? 0);

// Обработка массового одобрения/отклонения/удаления заявок (для выбранного студента или всех)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_requests'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if ($csrf_token !== $_SESSION['csrf_token']) {
        die("Ошибка CSRF-токена");
    }

    $action = $_POST['action'] ?? '';
    $selected_requests = $_POST['requests'] ?? [];
    $student_filter = (int)($_POST['student_filter'] ?? 0);

    if ($action === 'approve' || $action === 'reject' || $action === 'delete') {
        foreach ($selected_requests as $enrollment_id) {
            $enrollment_id = (int)$enrollment_id;
            $status = ($action === 'approve') ? 'approved' : (($action === 'reject') ? 'rejected' : 'deleted');
            
            // Для delete: Удаляем запись из enrollments
            if ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ? AND (status IN ('pending', 'replaced_pending', 'drop_pending'))");
                $stmt->bind_param("i", $enrollment_id);
                $stmt->execute();
                error_log("Admin deleted enrollment $enrollment_id for student $student_filter"); // Логирование
            } else {
                // Для approve/reject: Обновляем статус
                $stmt = $conn->prepare("UPDATE enrollments SET status = ? WHERE id = ? AND status IN ('pending', 'replaced_pending', 'drop_pending')");
                $stmt->bind_param("si", $status, $enrollment_id);
                $stmt->execute();
                
                // Если drop_pending, обновляем requests тоже
                if ($status === 'approved' || $status === 'rejected') {
                    $req_stmt = $conn->prepare("UPDATE requests SET status = ? WHERE type = 'drop' AND student_id = ? AND course_id IN (SELECT course_id FROM enrollments WHERE id = ?)");
                    $req_stmt->bind_param("sii", $status, $student_filter, $enrollment_id);
                    $req_stmt->execute();
                    $req_stmt->close();
                }
                error_log("Admin $action enrollment $enrollment_id for student $student_filter"); // Логирование
            }
            $stmt->close();
        }
        $message = ($action === 'approve') ? "Выбранные допы одобрены!" : (($action === 'reject') ? "Выбранные допы отклонены!" : "Выбранные допы удалены!");
    } else {
        $error = "Неверное действие!";
    }
}

// Получение списка ожидающих заявок (с фильтром по студенту, включая drop из requests)
$where_clause = $selected_student > 0 ? "AND e.student_id = $selected_student" : "";
$result = $conn->query("
    SELECT e.id AS enrollment_id, u.username AS student_name, u.id AS student_id, c.name AS course_name, e.status
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.status IN ('pending', 'replaced_pending', 'drop_pending')
    $where_clause
    ORDER BY u.username, c.name
");
if (!$result) {
    die("Ошибка выполнения запроса: " . $conn->error);
}
$requests = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Одобрения выписок - EduTrack+</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Одобрения выписок (пачкой для студента)</h1>

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

                <!-- Фильтр по студенту -->
                <form method="GET" class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Фильтр по студенту:</label>
                    <select name="student" class="border border-gray-300 rounded px-3 py-2 mr-4" onchange="this.form.submit()">
                        <option value="0">Все студенты</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>" <?php echo $selected_student == $student['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <form method="POST" class="bg-white shadow-md rounded-lg p-6">
                    <input type="hidden" name="process_requests" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="student_filter" value="<?php echo $selected_student; ?>">
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Учащийся</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Доп. занятие</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($requests) > 0): ?>
                                    <?php foreach ($requests as $request): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <input type="checkbox" name="requests[]" value="<?php echo $request['enrollment_id']; ?>" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['student_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($request['course_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['status'] === 'pending' ? 'Ожидает' : ($request['status'] === 'replaced_pending' ? 'Ожидает замены' : 'Ожидает выписки')); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Нет ожидающих запросов. <?php if ($selected_student > 0): ?>Для выбранного студента.<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($requests) > 0): ?>
                        <div class="mt-4 flex space-x-4">
                            <button type="submit" name="action" value="approve" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                                Одобрить выбранные
                            </button>
                            <button type="submit" name="action" value="reject" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors">
                                Отклонить выбранные
                            </button>
                            <button type="submit" name="action" value="delete" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition-colors" onclick="return confirm('Удалить выбранные допы? Это необратимо!');">
                                Удалить выбранные
                            </button>
                        </div>
                    <?php endif; ?>
                </form>

                <!-- JavaScript для выбора всех чекбоксов -->
                <script>
                    document.getElementById('select-all').addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('input[name="requests[]"]');
                        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
                    });
                </script>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>