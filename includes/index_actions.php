<?php
function verifyCsrf($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Ошибка CSRF-токена");
    }
}

function handleIndexActions($pdo, $role, $user_id) {
    $messages = [];

    // Обработка добавления аккаунта (admin/teacher)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user']) && ($role == 'admin' || $role == 'teacher')) {
        verifyCsrf($_POST['csrf_token']);
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $class = filter_input(INPUT_POST, 'class', FILTER_SANITIZE_STRING);
        $role_new = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
        if ($role == 'teacher') $role_new = 'student';
        $password = password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT);

        if (empty($username) || strlen($username) < 3) {
            $messages['error'] = "Логин слишком короткий или пустой";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $messages['error'] = "Неверный email";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email, class) VALUES (?, ?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$username, $password, $role_new, $full_name, $email, $class]);
                $messages['success'] = "Аккаунт создан!";
            } catch (PDOException $e) {
                $messages['error'] = "Ошибка: " . $e->getMessage();
                error_log("Ошибка добавления пользователя: " . $e->getMessage());
            }
        }
    }

    // Обработка добавления курса (admin/teacher)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course']) && ($role == 'admin' || $role == 'teacher')) {
        verifyCsrf($_POST['csrf_token']);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $schedule = filter_input(INPUT_POST, 'schedule', FILTER_SANITIZE_STRING) ?? '';
        $teacher_id = $role == 'teacher' ? $user_id : (int)$_POST['teacher_id'];

        if (empty($name) || strlen($name) < 3) {
            $messages['error'] = "Название курса слишком короткое";
        } elseif ($teacher_id <= 0) {
            $messages['error'] = "Неверный ID преподавателя";
        } else {
            $stmt = $pdo->prepare("INSERT INTO courses (name, description, teacher_id, schedule) VALUES (?, ?, ?, ?)");
            try {
                $stmt->execute([$name, $description, $teacher_id, $schedule]);
                $messages['success'] = "Курс добавлен!";
            } catch (PDOException $e) {
                $messages['error'] = "Ошибка добавления курса: " . $e->getMessage();
                error_log("Ошибка добавления курса: " . $e->getMessage());
            }
        }
    }

    // Обработка выбора допов (student)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_courses']) && $role == 'student') {
        verifyCsrf($_POST['csrf_token']);
        $selected = $_POST['selected_courses'] ?? [];
        if (count($selected) < 3) {
            $messages['error_student'] = "Выберите минимум 3 доп. занятия!";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'approved'");
            $stmt->execute([$user_id]);
            $current = $stmt->fetchColumn();
            if ($current >= 3) {
                $stmt_update = $pdo->prepare("UPDATE enrollments SET status = 'replaced_pending' WHERE student_id = ? AND status = 'approved'");
                $stmt_update->execute([$user_id]);
            }
            foreach ($selected as $course_id) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$user_id, $course_id]);
            }
            $messages['success_student'] = "Заявка отправлена на одобрение!";
        }
    }

    return $messages;
}
?>