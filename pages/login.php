<?php
// pages/login.php

// Handle login submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = handleLogin();

    if (!empty($result)) {
        $error = $result;
    } else {
        if (isLoggedIn()) {
            $user = currentUser();
            $role = isset($user['role']) ? $user['role'] : '';

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
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Simple Centered Login Card */
.auth-card-inner {
    width: 100%;
    max-width: 460px;
    background: white;
    border-radius: 24px;
    padding: 48px 40px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

/* Logo & Title */
.auth-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 40px;
}

.auth-logo-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    margin-bottom: 20px;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
}

.auth-logo-text {
    text-align: center;
}

.auth-app-name {
    font-size: 28px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
}

/* Error Alert */
.auth-alert {
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 24px;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}

.auth-alert-error {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}

.auth-alert-error::before {
    content: '⚠️';
    font-size: 16px;
}

/* Form */
.auth-form {
    width: 100%;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    outline: none;
    background: #ffffff;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.form-control::placeholder {
    color: #9ca3af;
}

/* Password Group */
.password-group {
    position: relative;
}

.password-group .form-control {
    padding-right: 45px;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    margin-top: 8px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    padding: 4px;
    opacity: 0.6;
    transition: opacity 0.2s;
}

.password-toggle:hover {
    opacity: 1;
}

/* Submit Button */
.btn-block {
    width: 100%;
    padding: 14px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
    margin-bottom: 20px;
    margin-top: 8px;
}

.btn-block:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-block:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Forgot Password Link */
.forgot-link {
    text-align: center;
    margin-top: 16px;
    margin-bottom: 24px;
}

.forgot-link a {
    color: #667eea;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
}

.forgot-link a:hover {
    text-decoration: underline;
}

/* Footer */
.auth-footer-text {
    text-align: center;
    color: #9ca3af;
    font-size: 13px;
    margin: 0;
}

/* Responsive */
@media (max-width: 640px) {
    .auth-card-inner {
        padding: 36px 28px;
        max-width: 100%;
        border-radius: 20px;
    }
    
    .auth-logo-icon {
        width: 70px;
        height: 70px;
        font-size: 36px;
    }
    
    .auth-app-name {
        font-size: 24px;
    }
}
</style>

<div class="auth-card-inner">
    <!-- Logo -->
    <div class="auth-logo">
        <div class="auth-logo-icon">📚</div>
        <div class="auth-logo-text">
            <div class="auth-app-name">BookByte LMS</div>
        </div>
    </div>

    <!-- Error Message -->
    <?php if (!empty($error)): ?>
        <div class="auth-alert auth-alert-error">
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="post" action="index.php?page=login" class="auth-form" id="loginForm">
        <div class="form-group">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                placeholder="Enter your email"
                required
                autofocus
            >
        </div>

        <div class="form-group password-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="Enter your password"
                required
            >
            <button type="button" class="password-toggle" onclick="togglePassword()" tabindex="-1">
                <span id="toggleIcon">👁️</span>
            </button>
        </div>

        <button type="submit" class="btn-block" id="loginBtn">
            Login
        </button>
    </form>

    <!-- Forgot Password Link -->
    <div class="forgot-link">
        <a href="#" onclick="event.preventDefault(); alert('Please contact admin to reset password');">Forgot password?</a>
    </div>

    <!-- Footer -->
    <p class="auth-footer-text">
        © BookByte LMS <?php echo date('Y'); ?>
    </p>
</div>

<script>
// Password Toggle
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.textContent = '🙈';
    } else {
        passwordInput.type = 'password';
        toggleIcon.textContent = '👁️';
    }
}

// Loading State on Submit
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.textContent = 'Logging in...';
    btn.disabled = true;
});
</script>