<?php
// includes/header.php

// Ensure variables exist to avoid notices
if (!isset($isLoginPage)) {
    $isLoginPage = false;
}
if (!isset($user)) {
    $user = null;
}
if (!isset($dashboardPage)) {
    $dashboardPage = 'home';
}
if (!isset($page)) {
    $page = '';
}

$userRole   = isset($user['role']) ? $user['role'] : null;
$fullName   = isset($user['full_name']) ? $user['full_name'] : 'Guest User';
$email      = isset($user['email']) ? $user['email'] : '';
$displayRole = $userRole ? ucfirst(strtolower($userRole)) : '';

$nameParts = preg_split('/\s+/', trim($fullName));
$initials  = '';
if (!empty($nameParts)) {
    $initials .= strtoupper(substr($nameParts[0], 0, 1));
    if (count($nameParts) > 1) {
        $initials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
    }
}
if ($initials === '') {
    $initials = 'U';
}

// Helper to mark active nav item
function isActiveNav($currentPage, $targetPages)
{
    if (!is_array($targetPages)) {
        $targetPages = [$targetPages];
    }
    return in_array($currentPage, $targetPages, true) ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BookByte LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?php echo ($isLoginPage || $user === null) ? 'body-auth' : 'body-app'; ?>">

<?php if (!$isLoginPage && $user !== null): ?>
    <!-- Main Layout Wrapper -->
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <a href="index.php?page=<?php echo urlencode($dashboardPage); ?>"
                   class="nav-item<?php echo isActiveNav($page, ['dashboard_admin', 'dashboard_librarian', 'dashboard_student', 'home']); ?>">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-label">Dashboard</span>
                </a>

                <?php if ($userRole === 'ADMIN'): ?>
                    <a href="index.php?page=users_manage"
                       class="nav-item<?php echo isActiveNav($page, ['users_manage']); ?>">
                        <span class="nav-icon">👥</span>
                        <span class="nav-label">Manage Users</span>
                    </a>
                <?php endif; ?>

                <?php if ($userRole === 'ADMIN' || $userRole === 'LIBRARIAN'): ?>
                    <a href="index.php?page=books_manage"
                       class="nav-item<?php echo isActiveNav($page, ['books_manage', 'books']); ?>">
                        <span class="nav-icon">📘</span>
                        <span class="nav-label">Manage Books</span>
                    </a>

                    <a href="index.php?page=loans_active"
                       class="nav-item<?php echo isActiveNav($page, ['loans_active', 'loans_user']); ?>">
                        <span class="nav-icon">🔁</span>
                        <span class="nav-label">Borrow &amp; Return</span>
                    </a>

                    <a href="index.php?page=reports"
                       class="nav-item<?php echo isActiveNav($page, ['reports']); ?>">
                        <span class="nav-icon">📊</span>
                        <span class="nav-label">Reports &amp; Tracking</span>
                    </a>
                <?php endif; ?>

                <?php if ($userRole === 'STUDENT'): ?>
                    <a href="index.php?page=books_student"
                       class="nav-item<?php echo isActiveNav($page, ['books_student']); ?>">
                        <span class="nav-icon">📚</span>
                        <span class="nav-label">Browse Books</span>
                    </a>

                    <a href="index.php?page=loans_my"
                       class="nav-item<?php echo isActiveNav($page, ['loans_my']); ?>">
                        <span class="nav-icon">📄</span>
                        <span class="nav-label">My Loans</span>
                    </a>
                <?php endif; ?>

                <!-- Common items (all roles) -->
                <a href="index.php?page=notifications"
                   class="nav-item<?php echo isActiveNav($page, ['notifications']); ?>">
                    <span class="nav-icon">📨</span>
                    <span class="nav-label">Notifications</span>
                </a>

                <a href="index.php?page=settings"
                   class="nav-item<?php echo isActiveNav($page, ['settings', 'profile']); ?>">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-label">Settings / Profile</span>
                </a>

                <div class="nav-separator"></div>

                <a href="index.php?page=logout" class="nav-item nav-item-danger">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-label">Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Column (Topbar + Content) -->
        <div class="main-column">
            <!-- Topbar -->
            <header class="topbar">
                <div class="topbar-left">
                    <div class="app-logo">
                        <span class="app-logo-icon">📚</span>
                        <div class="app-logo-text">
                            <span class="app-name">BookByte LMS</span>
                            <span class="app-subtitle">Library Management System</span>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <button class="icon-button" aria-label="Notifications">
                        <span class="icon-bell">🔔</span>
                    </button>
                    <div class="user-badge">
                        <div class="avatar-circle"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="user-info">
                            <span class="user-name">Hi, <?php echo htmlspecialchars($fullName); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($displayRole); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content Wrapper -->
            <div class="page-wrapper">
<?php endif; ?>