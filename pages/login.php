<?php
// pages/login.php

// We assume auth.php is already loaded by public/index.php
// and session is already started.

// Handle login submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Let auth.php handle the login logic.
    // We assume handleLogin() reads $_POST internally and
    // sets $_SESSION['user'] on success and returns an error
    // message (string) on failure or null/empty on success.
    $result = handleLogin();

    if (!empty($result)) {
        // Treat result as error message
        $error = $result;
    } else {
        // If login succeeded, user should now be logged in
        if (isLoggedIn()) {
            $user = currentUser();
            $role = isset($user['role']) ? $user['role'] : '';

            // Redirect to appropriate dashboard
            if ($role === 'ADMIN') {
                header('Location: index.php?page=dashboard_admin');
            } elseif ($role === 'LIBRARIAN') {
                header('Location: index.php?page=dashboard_librarian');
            } else {
                header('Location: index.php?page=dashboard_student');
            }
            exit;
        } else {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<div class="auth-card-inner">
    <div class="auth-card-header">
        <div class="auth-logo">
            <span class="auth-logo-icon">📚</span>
            <div class="auth-logo-text">
                <span class="auth-app-name">BookByte LMS</span>
                <span class="auth-app-subtitle">Library Management System</span>
            </div>
        </div>
        <h1>Sign in</h1>
        <p>Welcome back! Please sign in to continue.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="auth-alert auth-alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="index.php?page=login" class="auth-form">
        <div class="form-group">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                placeholder="you@example.com"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="Enter your password"
                required
            >
        </div>

        <div class="auth-form-footer">
            <button type="submit" class="btn btn-primary btn-block">
                Login
            </button>
        </div>

        <div class="auth-extra">
            <a href="#" class="auth-link">Forgot password?</a>
        </div>
    </form>

    <div class="auth-footer-text">
        © BookByte LMS <?php echo date('Y'); ?>
    </div>
</div>
