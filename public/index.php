<?php
// public/index.php

require_once __DIR__ . '/../lib/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user     = isLoggedIn() ? currentUser() : null;
$userRole = isset($user['role']) ? $user['role'] : null;

// requested page
$page = isset($_GET['page']) ? $_GET['page'] : '';

// handle logout
if ($page === 'logout') {
    handleLogout();
    header('Location: index.php?page=login');
    exit;
}

// decide default page
if ($user === null) {
    // not logged in → login
    $page = 'login';
} else {
    // logged in
    if ($page === '' || $page === 'login') {
        if ($userRole === 'ADMIN') {
            $page = 'dashboard_admin';
        } elseif ($userRole === 'LIBRARIAN') {
            $page = 'dashboard_librarian';
        } else {
            $page = 'dashboard_student';
        }
    }
}

// route map - FIXED PATHS
$routes = [
    // Auth
    'login'               => __DIR__ . '/../pages/login.php',

    // Home / dashboards - CORRECTED PATHS
    'home'                => __DIR__ . '/../pages/home.php',
    'dashboard_admin'     => __DIR__ . '/../pages/dashboard_admin.php',
    'dashboard_librarian' => __DIR__ . '/../pages/dashboard_librarian.php',
    'dashboard_student'   => __DIR__ . '/../pages/dashboard_student.php',

    // Add this line in the routes array (around line 50):
    'reminders'      => __DIR__ . '/../pages/reminders/manage.php',
    'fines'          => __DIR__ . '/../pages/fines/manage.php',
    'library_cards'       => __DIR__ . '/../pages/library_cards/manage.php',
    'student_returns'     => __DIR__ . '/../pages/loans/student_returns.php',
    // Books
    'books'               => __DIR__ . '/../pages/books/list.php',
    'books_manage'        => __DIR__ . '/../pages/books/manage_list.php',
    'books_student'       => __DIR__ . '/../pages/books/student_list.php',
    'book_detail'         => __DIR__ . '/../pages/books/detail.php',
    'book_form'           => __DIR__ . '/../pages/books/form.php',

    // Loans
    'loans_active'        => __DIR__ . '/../pages/loans/active_loans.php',
    'loans_my'            => __DIR__ . '/../pages/loans/my_loans.php',
    'loans_user'          => __DIR__ . '/../pages/loans/user_loans.php',

    // Users (admin)
    'users_manage'        => __DIR__ . '/../pages/users/users_manage.php',
    'user_form'           => __DIR__ . '/../pages/users/user_form.php',

    // Reports, notifications
    'reports'             => __DIR__ . '/../pages/reports/index.php',
    'notifications'       => __DIR__ . '/../pages/notifications/index.php',

    // Settings / Profile (same page)
    'settings'            => __DIR__ . '/../pages/settings/index.php',
    'profile'             => __DIR__ . '/../pages/settings/index.php',
];

// fallback if route missing
if (!array_key_exists($page, $routes)) {
    if ($user === null) {
        $page = 'login';
    } else {
        if ($userRole === 'ADMIN') {
            $page = 'dashboard_admin';
        } elseif ($userRole === 'LIBRARIAN') {
            $page = 'dashboard_librarian';
        } else {
            $page = 'dashboard_student';
        }
    }
}

$contentFile = $routes[$page];

// flags for layout
$isLoginPage = ($page === 'login');

if ($userRole === 'ADMIN') {
    $dashboardPage = 'dashboard_admin';
} elseif ($userRole === 'LIBRARIAN') {
    $dashboardPage = 'dashboard_librarian';
} elseif ($userRole === 'STUDENT') {
    $dashboardPage = 'dashboard_student';
} else {
    $dashboardPage = 'home';
}

// header (topbar + sidebar if logged in)
include __DIR__ . '/../includes/header.php';

// CONTENT
if ($isLoginPage || $user === null): ?>
    <div class="auth-page">
        <div class="auth-card">
            <?php
            if (file_exists($contentFile)) {
                require $contentFile;
            } else {
                echo "<p>Login page file not found: <code>" . htmlspecialchars($contentFile) . "</code></p>";
            }
            ?>
        </div>
    </div>
<?php else: ?>
    <?php
    if (file_exists($contentFile)) {
        require $contentFile;
    } else {
        echo '<div class="page-wrapper">';
        echo '<div class="card"><h2>Page missing</h2>';
        echo '<p>The requested page file was not found on the server.</p>';
        echo '<p><strong>Looking for:</strong> <code>' . htmlspecialchars($contentFile) . '</code></p>';
        echo '<p><strong>Page requested:</strong> <code>' . htmlspecialchars($page) . '</code></p>';
        echo '</div>';
        echo '</div>';
    }
    ?>
<?php endif;

// footer
include __DIR__ . '/../includes/footer.php';