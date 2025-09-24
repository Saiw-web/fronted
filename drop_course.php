<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
include 'config.php';

$error = '';
$success = false;
$course_to_drop_info = null;
$needed_new = 0;
$available_courses = null;

$enroll_id = (int)($_GET['enroll_id'] ?? 0);
if ($enroll_id <= 0) {
    header('Location: courses.php');
    exit;
}

// Получаем информацию о допе для выписки
$stmt = $conn->prepare("SELECT c.id, c.name FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.id = ? AND e.student_id = ? AND e.status = 'enrolled'");
$stmt->bind_param("ii", $enroll_id, $user_id);
$stmt->execute();
$course_to_drop_info = $stmt->get_result()->fetch_assoc();
if (!$course_to_drop_info) {
    header('Location: courses.php');
    exit;
}
$course_to_drop_id = $course_to_drop_info['id'];

// Подсчет текущих допов
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM enrollments WHERE student_id = ? AND status = 'enrolled'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$remaining_after_drop = $current_count - 1;
$needed_new = max(0, 3 - $remaining_after_drop);

// Доступные допы (не записанные)
$available_courses = $conn->query("SELECT id, name FROM courses 
                                   WHERE id NOT IN (SELECT course_id FROM enrollments WHERE student_id = $user_id AND status = 'enrolled') 
                                   ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_to_drop = (int)($_POST['course_to_drop'] ?? 0);
    $new_courses = $_POST['new_courses'] ?? [];
    $new_courses = array_map('intval', $new_courses);

    if (count($new_courses) < $needed_new) {
        $error = "Нужно выбрать минимум $needed_new новых допов, чтобы общее количество было не менее 3.";
    } elseif ($course_to_drop !== $course_to_drop_id) {
        $error = 'Неверные данные.';
    } else {
        // Записываем новые допы (самозапись)
        foreach ($new_courses as $nc_id) {
            if ($nc_id > 0) {
                $check_stmt = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'enrolled'");
                $check_stmt->bind_param("ii", $user_id, $nc_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows === 0) {
                    $insert_stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'enrolled')");
                    $insert_stmt->bind_param("ii", $user_id, $nc_id);
                    $insert_stmt->execute();
                }
                $check_stmt->close();
            }
        }

        // ИСПРАВЛЕНИЕ: Обновляем статус в enrollments на 'drop_pending' для синхронизации
        $update_enroll = $conn->prepare("UPDATE enrollments SET status = 'drop_pending' WHERE id = ? AND student_id = ?");
        $update_enroll->bind_param("ii", $enroll_id, $user_id);
        if ($update_enroll->execute()) {
            // Вставляем в requests для админов
            $insert_req = $conn->prepare("INSERT INTO requests (type, student_id, course_id, status, created_at) VALUES ('drop', ?, ?, 'pending', NOW())");
            $insert_req->bind_param("ii", $user_id, $course_to_drop_id);
            if ($insert_req->execute()) {
                header('Location: courses.php?msg=Запрос%20на%20выписку%20отправлен%20на%20одобрение');
                exit;
            } else {
                $error = "Ошибка отправки запроса админам.";
                error_log("Ошибка вставки в requests: " . $conn->error); // Логирование
            }
            $insert_req->close();
        } else {
            $error = "Ошибка обновления статуса.";
            error_log("Ошибка обновления enrollments: " . $conn->error);
        }
        $update_enroll->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выписка с допа - EduTrack+</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-2xl mx-auto">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800">Доп для выписки: <?php echo htmlspecialchars($course_to_drop_info['name']); ?></h2>
                    <p class="text-gray-600 mb-4">После выписки у вас останется <strong><?php echo $remaining_after_drop; ?></strong> допов.</p>
                    
                    <?php if ($needed_new > 0): ?>
                        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                            <strong>Внимание!</strong> Чтобы подать запрос, выберите минимум <?php echo $needed_new; ?> новых допов (самозапись).
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="course_to_drop" value="<?php echo $course_to_drop_id; ?>">
                            <h3 class="text-lg font-medium mb-3">Выберите новые допы:</h3>
                            <div class="grid grid-cols-2 gap-4 max-h-48 overflow-y-auto mb-4">
                                <?php while ($av = $available_courses->fetch_assoc()): ?>
                                    <label class="flex items-center p-3 bg-gray-50 rounded border">
                                        <input type="checkbox" name="new_courses[]" value="<?php echo (int)$av['id']; ?>" class="mr-2">
                                        <?php echo htmlspecialchars($av['name']); ?>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                                Отправить запрос на выписку
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="course_to_drop" value="<?php echo $course_to_drop_id; ?>">
                            <p class="text-gray-600 mb-4">Запрос на выписку будет отправлен на одобрение администратора.</p>
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg font-medium transition-colors"
                                    onclick="return confirm('Подтвердите выписку с "<?php echo htmlspecialchars($course_to_drop_info['name']); ?>"');">
                                Подтвердить выписку
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <a href="courses.php" class="text-indigo-600 hover:text-indigo-800 font-medium">← Назад к моим допам</a>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>