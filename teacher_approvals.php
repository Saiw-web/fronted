<?php
session_start();

// Проверка роли teacher
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
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

// Обработка массового одобрения/отклонения заявок (только для своих курсов)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_requests'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if ($csrf_token !== $_SESSION['csrf_token']) {
        die("Ошибка CSRF-токена");
    }

    $action = $_POST['action'] ?? '';
    $selected_requests = $_POST['requests'] ?? [];

    if ($action === 'approve' || $action === 'reject') {
        $teacher_id = (int)$_SESSION['user_id']; // ID текущего teacher
        foreach ($selected_requests as $enrollment_id) {
            $enrollment_id = (int)$enrollment_id;

            // Проверка: заявка на курс этого teacher
            $check_stmt = $conn->prepare("SELECT 1 FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.id = ? AND c.teacher_id = ? AND e.status IN ('pending', 'replaced_pending')");
            $check_stmt->bind_param("ii", $enrollment_id, $teacher_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $status = ($action === 'approve') ? 'approved' : 'rejected';
                $stmt = $conn->prepare("UPDATE enrollments SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $enrollment_id);
                $stmt->execute();
                $stmt->close();
            } else {
                error_log("Teacher $teacher_id tried to approve foreign enrollment $enrollment_id"); // Логирование попытки
            }
            $check_stmt->close();
        }
        $message = ($action === 'approve') ? "Выбранные заявки одобрены!" : "Выбранные заявки отклонены!";
    } else {
        $error = "Неверное действие!";
    }
}

// Получение списка ожидающих заявок ТОЛЬКО для курсов этого teacher
$teacher_id = (int)$_SESSION['user_id'];
$result = $conn->query("
    SELECT e.id AS enrollment_id, u.username AS student_name, c.name AS course_name, e.status
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.status IN ('pending', 'replaced_pending') AND c.teacher_id = $teacher_id
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
    <title>Одобрения записей - EduTrack+</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Одобрения записей на ваши допы (пачкой)</h1>

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

                <form method="POST" class="bg-white shadow-md rounded-lg p-6">
                    <input type="hidden" name="process_requests" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
                                                <?php echo htmlspecialchars($request['status'] === 'pending' ? 'Ожидает' : 'Ожидает замены'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Нет ожидающих записей на ваши допы.
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