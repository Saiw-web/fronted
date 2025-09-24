<?php
$role = $_SESSION['role'];
?>

<!-- Mobile menu button -->
<div class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-gray-200 px-4 py-3">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-indigo-700">EduTrack+</h1>
        <button id="mobile-menu-btn" class="p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>
</div>

<!-- Mobile menu overlay -->
<div id="mobile-menu-overlay" class="lg:hidden fixed inset-0 z-40 bg-black bg-opacity-50 transition-opacity opacity-0 pointer-events-none">
</div>

<!-- Sidebar -->
<div id="sidebar" class="sidebar bg-white w-64 border-r border-gray-200 flex flex-col fixed lg:static inset-y-0 left-0 z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="p-4 border-b border-gray-200 mt-16 lg:mt-0">
        <h1 class="text-xl font-bold text-indigo-700 hidden lg:block">EduTrack+</h1>
        <p class="text-xs text-gray-500 hidden lg:block">Система доп. образования</p>
        <!-- Mobile close button -->
        <button id="mobile-close-btn" class="lg:hidden absolute top-4 right-4 p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
    <nav class="flex-1 overflow-y-auto">
        <div class="p-4">
            <div class="mb-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Основное</h3>
                <ul class="space-y-1">
                    <li>
                        <a href="index.php" class="flex items-center px-3 py-3 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="home" class="mr-3 w-5 h-5"></i>
                            Главная
                        </a>
                    </li>
                    <?php if ($role != 'student'): ?>
                    <li>
                        <a href="students.php" class="flex items-center px-3 py-3 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) == 'students.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="users" class="mr-3 w-5 h-5"></i>
                            Учащиеся
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="courses.php" class="flex items-center px-3 py-3 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="book" class="mr-3 w-5 h-5"></i>
                            <?php echo ($role == 'student' ? 'Мои допы' : 'Доп. занятия'); ?>
                        </a>
                    </li>
                </ul>
            </div>
            <?php if ($role != 'student'): ?>
            <div class="mb-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Оценки</h3>
                <ul class="space-y-1">
                    <li>
                        <a href="grades.php" class="flex items-center px-3 py-3 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) == 'grades.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="star" class="mr-3 w-5 h-5"></i>
                            Выставить баллы
                        </a>
                    </li>
                    <li>
                        <a href="stats.php" class="flex items-center px-3 py-3 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="bar-chart-2" class="mr-3 w-5 h-5"></i>
                            Статистика
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($role == 'teacher'): ?>
            <div class="mb-6">
                <ul class="space-y-1">
                    <li>
                        <a href="teacher_approvals.php" class="flex items-center px-3 py-3 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) == 'teacher_approvals.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="check-square" class="mr-3 w-5 h-5"></i>
                            Одобрения записей
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($role == 'admin'): ?>
            <div class="mb-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Админ</h3>
                <ul class="space-y-1">
                    <li>
                        <a href="admin_users.php" class="flex items-center px-3 py-3 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="user-plus" class="mr-3 w-5 h-5"></i>
                            Управление аккаунтами
                        </a>
                    </li>
                    <li>
                        <a href="admin_courses.php" class="flex items-center px-3 py-3 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_courses.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="book-open" class="mr-3 w-5 h-5"></i>
                            Управление доп. занятиями
                        </a>
                    </li>
                    <li>
                        <a href="admin_requests.php" class="flex items-center px-3 py-3 text-sm font-medium text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_requests.php' ? 'text-indigo-700 bg-indigo-50' : ''); ?>">
                            <i data-feather="check-square" class="mr-3 w-5 h-5"></i>
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
            <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                <i data-feather="user" class="w-5 h-5 text-indigo-600"></i>
            </div>
            <div class="ml-3 flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                <p class="text-xs text-gray-500 capitalize"><?php echo ucfirst($_SESSION['role']); ?></p>
            </div>
            <a href="logout.php" class="ml-2 p-2 text-gray-400 hover:text-red-500 transition-colors" title="Выйти">
                <i data-feather="log-out" class="w-5 h-5"></i>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileCloseBtn = document.getElementById('mobile-close-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-menu-overlay');
    
    function toggleMobileMenu() {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('opacity-0');
        overlay.classList.toggle('pointer-events-none');
    }
    
    mobileMenuBtn?.addEventListener('click', toggleMobileMenu);
    mobileCloseBtn?.addEventListener('click', toggleMobileMenu);
    overlay?.addEventListener('click', toggleMobileMenu);
});
</script>
