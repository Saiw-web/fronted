<?php
$role = $_SESSION['role'];
?>

<!-- УДАЛЕНО: <div class="flex h-screen overflow-hidden"> — это дублирует контейнер из index.php -->

<div class="sidebar bg-white w-64 border-r border-gray-200 flex flex-col">
    <div class="p-4 border-b border-gray-200">
        <h1 class="text-xl font-bold text-indigo-700">EduTrack+</h1>
        <p class="text-xs text-gray-500">Система доп. образования</p>
    </div>
    <nav class="flex-1 overflow-y-auto">
        <div class="p-4">
            <div class="mb-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Основное</h3>
                <ul class="mt-2">
                    <li class="mb-1">
                        <a href="index.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="home" class="mr-3 w-4 h-4"></i>
                            Главная
                        </a>
                    </li>
                    <?php if ($role != 'student'): ?>
                    <li class="mb-1">
                        <a href="students.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'students.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="users" class="mr-3 w-4 h-4"></i>
                            Учащиеся
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="mb-1">
                        <a href="courses.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="book" class="mr-3 w-4 h-4"></i>
                            <?php echo ($role == 'student' ? 'Мои допы' : 'Доп. занятия'); ?>
                        </a>
                    </li>
                </ul>
            </div>
            <?php if ($role != 'student'): ?>
            <div class="mb-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Оценки</h3>
                <ul class="mt-2">
                    <li class="mb-1">
                        <a href="grades.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'grades.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="star" class="mr-3 w-4 h-4"></i>
                            Выставить баллы
                        </a>
                    </li>
                    <li class="mb-1">
                        <a href="stats.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="bar-chart-2" class="mr-3 w-4 h-4"></i>
                            Статистика
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($role == 'teacher'): ?>
            <li class="mb-1">
                <a href="teacher_approvals.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'teacher_approvals.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                    <i data-feather="check-square" class="mr-3 w-4 h-4"></i>
                    Одобрения записей
                </a>
            </li>
            <?php endif; ?>
            <?php if ($role == 'admin'): ?>
            <div class="mb-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Админ</h3>
                <ul class="mt-2">
                    <li class="mb-1">
                        <a href="admin_users.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="user-plus" class="mr-3 w-4 h-4"></i>
                            Управление аккаунтами
                        </a>
                    </li>
                    <li class="mb-1">
                        <a href="admin_courses.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_courses.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="book-open" class="mr-3 w-4 h-4"></i>
                            Управление доп. занятиями
                        </a>
                    </li>
                    <li class="mb-1">
                        <a href="admin_requests.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_requests.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="check-square" class="mr-3 w-4 h-4"></i>
                            Одобрения выписок
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="p-4 border-t border-gray-200">
        <div class="flex items-center">
            <img src="http://static.photos/people/200x200/1" alt="User" class="w-8 h-8 rounded-full">
            <div class="ml-3">
                <p class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                <p class="text-xs text-gray-500"><?php echo ucfirst($_SESSION['role']); ?></p>
            </div>
            <a href="logout.php" class="ml-auto text-gray-400 hover:text-gray-500">
                <i data-feather="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</div>

<!-- Закрывающий </div> для основного flex из index.php добавляется в index.php после include -->