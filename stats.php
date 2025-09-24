<?php
include 'includes/header.php';
include 'includes/sidebar.php';

// Получаем реальные данные из БД
// Количество студентов
$stmt_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$total_students = $stmt_students->fetchColumn();

// Количество курсов
$stmt_courses = $pdo->query("SELECT COUNT(*) FROM courses");
$total_courses = $stmt_courses->fetchColumn();

// Средний балл (AVG с CAST для DECIMAL)
$stmt_avg_grade = $pdo->query("SELECT AVG(CAST(grade AS DECIMAL(3,2))) FROM grades");
$avg_grade = number_format($stmt_avg_grade->fetchColumn() ?: 0, 1);

// Распределение баллов (для pie chart)
$stmt_grades_dist = $pdo->prepare("SELECT grade, COUNT(*) as count FROM grades GROUP BY grade");
$stmt_grades_dist->execute();
$grades_dist = $stmt_grades_dist->fetchAll(PDO::FETCH_ASSOC);
$grades_labels = [];
$grades_data = [];
foreach ($grades_dist as $dist) {
    $grades_labels[] = $dist['grade'];
    $grades_data[] = $dist['count'];
}
if (empty($grades_labels)) {
    $grades_labels = ['0', '1', '2'];
    $grades_data = [0, 0, 0];
}

// Топ студентов (LIMIT 3, с AVG баллом)
$stmt_top_students = $pdo->query("SELECT u.id, u.full_name, u.class, AVG(CAST(g.grade AS DECIMAL(3,2))) as avg_grade 
                                  FROM grades g 
                                  JOIN enrollments e ON g.enrollment_id = e.id 
                                  JOIN users u ON e.student_id = u.id 
                                  GROUP BY u.id 
                                  ORDER BY avg_grade DESC 
                                  LIMIT 3");
$top_students = $stmt_top_students->fetchAll();

// Данные для графиков (пример: по месяцам)
$stmt_monthly_grades = $pdo->query("SELECT DATE_FORMAT(g.date, '%Y-%m') as month, AVG(CAST(g.grade AS DECIMAL(3,2))) as avg_grade 
                                    FROM grades g 
                                    GROUP BY month 
                                    ORDER BY month DESC 
                                    LIMIT 3");
$monthly_grades = $stmt_monthly_grades->fetchAll();
$grades_chart_labels = [];
$grades_chart_data = [];
foreach ($monthly_grades as $mg) {
    $grades_chart_labels[] = $mg['month'];
    $grades_chart_data[] = number_format($mg['avg_grade'], 1);
}
if (empty($grades_chart_labels)) {
    $grades_chart_labels = ['Нет данных'];
    $grades_chart_data = [0];
}

$stmt_monthly_courses = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                                     FROM courses 
                                     GROUP BY month 
                                     ORDER BY month DESC 
                                     LIMIT 3");
$monthly_courses = $stmt_monthly_courses->fetchAll();
$courses_chart_labels = [];
$courses_chart_data = [];
foreach ($monthly_courses as $mc) {
    $courses_chart_labels[] = $mc['month'];
    $courses_chart_data[] = $mc['count'];
}
if (empty($courses_chart_labels)) {
    $courses_chart_labels = ['Нет данных'];
    $courses_chart_data = [0];
}

$grades_chart_data = json_encode($grades_chart_data);
$courses_chart_data = json_encode($courses_chart_data);
$grades_dist_labels = json_encode($grades_labels);
$grades_dist_data = json_encode($grades_data);
$grades_chart_labels = json_encode($grades_chart_labels);
$courses_chart_labels = json_encode($courses_chart_labels);
?>

<div class="flex-1 overflow-y-auto">
    <header class="bg-white shadow-sm">
        <div class="px-6 py-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Статистика</h2>
            <div class="flex items-center space-x-4">
                <button class="p-2 rounded-full text-gray-500 hover:text-gray-600 hover:bg-gray-100">
                    <i data-feather="bell" class="w-5 h-5"></i>
                </button>
                <button class="p-2 rounded-full text-gray-500 hover:text-gray-600 hover:bg-gray-100">
                    <i data-feather="help-circle" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
    </header>

    <main class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 card-hover" data-aos="fade-up">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Всего учащихся</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars($total_students); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i data-feather="users" class="w-6 h-6"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <canvas id="studentsChart" height="150"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 card-hover" data-aos="fade-up" data-aos-delay="100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Всего занятий</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars($total_courses); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i data-feather="book" class="w-6 h-6"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <canvas id="coursesChart" height="150"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 card-hover" data-aos="fade-up" data-aos-delay="200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Средний балл</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars($avg_grade); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i data-feather="star" class="w-6 h-6"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <canvas id="gradesChart" height="150"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6 card-hover" data-aos="fade-right">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Распределение баллов</h3>
                <canvas id="gradesDistributionChart" height="250"></canvas>
            </div>
            <div class="bg-white rounded-lg shadow p-6 card-hover" data-aos="fade-left">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Топ учащихся</h3>
                <div class="space-y-4">
                    <?php if (empty($top_students)): ?>
                        <p class="text-gray-500">Нет данных о студентах.</p>
                    <?php else: ?>
                        <?php foreach ($top_students as $student): ?>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <img class="h-10 w-10 rounded-full" src="http://static.photos/people/200x200/<?php echo htmlspecialchars($student['id'] ?? rand(1,10)); ?>" alt="">
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($student['class'] ?? ''); ?></p>
                                        </div>
                                        <div class="text-lg font-semibold text-gray-900"><?php echo number_format($student['avg_grade'], 1); ?></div>
                                    </div>
                                    <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo ($student['avg_grade'] / 2) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    AOS.init();
    feather.replace();

    // График для студентов (заглушка, можно заменить реальными данными)
    new Chart(document.getElementById('studentsChart'), {
        type: 'bar',
        data: {
            labels: ['Студенты'],
            datasets: [{
                label: 'Количество студентов',
                data: [<?php echo $total_students; ?>],
                backgroundColor: '#3b82f6',
                borderColor: '#2563eb',
                borderWidth: 1
            }]
        },
        options: {
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });

    // График для курсов (по месяцам)
    new Chart(document.getElementById('coursesChart'), {
        type: 'line',
        data: {
            labels: <?php echo $courses_chart_labels; ?>,
            datasets: [{
                label: 'Количество курсов',
                data: <?php echo $courses_chart_data; ?>,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.2)',
                fill: true
            }]
        },
        options: {
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: true } }
        }
    });

    // График для среднего балла (по месяцам)
    new Chart(document.getElementById('gradesChart'), {
        type: 'line',
        data: {
            labels: <?php echo $grades_chart_labels; ?>,
            datasets: [{
                label: 'Средний балл',
                data: <?php echo $grades_chart_data; ?>,
                borderColor: '#eab308',
                backgroundColor: 'rgba(234, 179, 8, 0.2)',
                fill: true
            }]
        },
        options: {
            scales: { y: { beginAtZero: true, max: 2 } },
            plugins: { legend: { display: true } }
        }
    });

    // Распределение баллов (pie chart)
    new Chart(document.getElementById('gradesDistributionChart'), {
        type: 'pie',
        data: {
            labels: <?php echo $grades_dist_labels; ?>,
            datasets: [{
                data: <?php echo $grades_dist_data; ?>,
                backgroundColor: ['#ef4444', '#f59e0b', '#22c55e']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>

<?php
include 'includes/footer.php';
ob_end_flush();
?>