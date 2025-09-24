<?php
function getIndexData($pdo, $role, $user_id) {
    $data = [];

    // Статистика для студента
    if ($role === 'student') {
        // Количество текущих допов
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status = 'approved'");
        $stmt->execute([$user_id]);
        $data['stats']['current_courses'] = $stmt->fetchColumn() ?? 0;

        // Средний балл
        $stmt = $pdo->prepare("SELECT AVG(g.grade) as avg_grade FROM grades g JOIN enrollments e ON g.enrollment_id = e.id WHERE e.student_id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
        $data['stats']['avg_grade'] = $row['avg_grade'] ? number_format($row['avg_grade'], 2) : 'Нет оценок';

        // Количество заявок на рассмотрении
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status IN ('pending', 'replaced_pending', 'drop_pending')");
        $stmt->execute([$user_id]);
        $data['stats']['pending_requests'] = $stmt->fetchColumn() ?? 0;

        // Список текущих допов с последними оценками
        $stmt = $pdo->prepare("
            SELECT c.name AS course_name, g.grade, g.date
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN grades g ON e.id = g.enrollment_id
            WHERE e.student_id = ? AND e.status = 'approved'
            ORDER BY c.name
        ");
        $stmt->execute([$user_id]);
        $data['stats']['courses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Доступные курсы
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.description, c.schedule
            FROM courses c
            WHERE c.id NOT IN (
                SELECT course_id FROM enrollments WHERE student_id = ? AND status IN ('approved', 'pending', 'replaced_pending')
            )
            ORDER BY c.name
        ");
        $stmt->execute([$user_id]);
        $data['available_courses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Последние оценки (для всех ролей)
    $where_clause = ($role === 'student') ? "AND e.student_id = $user_id" : "";
    $stmt = $pdo->prepare("
        SELECT u.full_name, c.name as course, g.grade, g.date
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.id
        JOIN users u ON e.student_id = u.id
        JOIN courses c ON e.course_id = c.id
        WHERE 1=1 $where_clause
        ORDER BY g.date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $data['recent_grades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $data;
}
?>