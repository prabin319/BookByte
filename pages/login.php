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
<style>
/* Enhanced Login Page Styles */
.auth-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

/* Animated Background Elements */
.auth-page::before {
    content: '';
    position: absolute;
    width: 600px;
    height: 600px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    top: -300px;
    left: -200px;
    animation: float 20s infinite ease-in-out;
}

.auth-page::after {
    content: '';
    position: absolute;
    width: 400px;
    height: 400px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 50%;
    bottom: -200px;
    right: -150px;
    animation: float 15s infinite ease-in-out reverse;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0) scale(1);
    }
    50% {
        transform: translateY(-30px) scale(1.05);
    }
}

/* Login Card */
.auth-card {
    width: 100%;
    max-width: 460px;
    position: relative;
    z-index: 1;
    animation: slideUp 0.6s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.auth-card-inner {
    background: white;
    border-radius: 24px;
    padding: 48px 40px;
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(10px);
}

/* Logo Section */
.auth-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-bottom: 32px;
}

.auth-logo-icon {
    font-size: 48px;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

.auth-logo-text {
    text-align: left;
}

.auth-app-name {
    display: block;
    font-size: 28px;
    font-weight: 800;
    color: #111827;
    line-height: 1.2;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.auth-app-subtitle {
    display: block;
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
    margin-top: 2px;
}

/* Header Section */
.auth-card-header {
    text-align: center;
    margin-bottom: 36px;
}

.auth-card-header h1 {
    font-size: 32px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 8px;
}

.auth-card-header p {
    font-size: 15px;
    color: #6b7280;
    margin: 0;
}

/* Alert Messages */
.auth-alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: shake 0.5s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.auth-alert-error {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}

.auth-alert-error::before {
    content: '⚠️';
    font-size: 18px;
}

/* Form Styling */
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
    transition: all 0.3s ease;
    background: #f9fafb;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.form-control::placeholder {
    color: #9ca3af;
}

/* Password Input with Icon */
.form-group.password-group {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 16px;
    top: 42px;
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
.auth-form-footer {
    margin-top: 28px;
}

.btn-block {
    width: 100%;
    padding: 14px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-block:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-block:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

/* Demo Credentials Section */
.demo-credentials {
    margin-top: 32px;
    padding: 20px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: 12px;
    border: 2px solid #bae6fd;
}

.demo-credentials h3 {
    font-size: 14px;
    font-weight: 700;
    color: #075985;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.demo-credentials h3::before {
    content: '🔑';
    font-size: 16px;
}

.demo-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.demo-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    background: white;
    border-radius: 8px;
    font-size: 13px;
}

.demo-role {
    font-weight: 600;
    color: #0c4a6e;
}

.demo-email {
    color: #6b7280;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.demo-password {
    background: #667eea;
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    font-family: 'Courier New', monospace;
}

/* Footer Text */
.auth-footer-text {
    text-align: center;
    margin-top: 32px;
    color: #9ca3af;
    font-size: 13px;
}

/* Responsive Design */
@media (max-width: 640px) {
    .auth-card-inner {
        padding: 36px 28px;
    }

    .auth-card-header h1 {
        font-size: 26px;
    }

    .auth-logo-icon {
        font-size: 40px;
    }

    .auth-app-name {
        font-size: 24px;
    }
}

/* Loading State */
.btn-block.loading {
    position: relative;
    color: transparent;
    pointer-events: none;
}

.btn-block.loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="auth-card-inner">
    <div class="auth-logo">
        <span class="auth-logo-icon">📚</span>
        <div class="auth-logo-text">
            <span class="auth-app-name">BookByte LMS</span>
            <span class="auth-app-subtitle">Library Management System</span>
        </div>
    </div>

    <div class="auth-card-header">
        <h1>Welcome Back</h1>
        <p>Sign in to access your library account</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="auth-alert auth-alert-error">
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="index.php?page=login" class="auth-form" id="loginForm">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                placeholder="your@email.com"
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
            <button type="button" class="password-toggle" onclick="togglePassword()">
                <span id="toggleIcon">👁️</span>
            </button>
        </div>

        <div class="auth-form-footer">
            <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
                Sign In
            </button>
        </div>
    </form>

    <!-- Demo Credentials Box -->
    <div class="demo-credentials">
        <h3>Demo Login Credentials</h3>
        <div class="demo-list">
            <div class="demo-item">
                <div>
                    <div class="demo-role">👨‍💼 Admin</div>
                    <div class="demo-email">admin@bookbyte.com</div>
                </div>
                <div class="demo-password">admin123</div>
            </div>
            <div class="demo-item">
                <div>
                    <div class="demo-role">📚 Librarian</div>
                    <div class="demo-email">librarian@bookbyte.com</div>
                </div>
                <div class="demo-password">librarian123</div>
            </div>
            <div class="demo-item">
                <div>
                    <div class="demo-role">👨‍🎓 Student</div>
                    <div class="demo-email">student@bookbyte.com</div>
                </div>
                <div class="demo-password">student123</div>
            </div>
        </div>
    </div>

    <div class="auth-footer-text">
        © <?php echo date('Y'); ?> BookByte LMS. All rights reserved.
    </div>
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
    btn.classList.add('loading');
});

// Auto-fill demo credentials on click
const demoItems = document.querySelectorAll('.demo-item');
demoItems.forEach(item => {
    item.style.cursor = 'pointer';
    item.addEventListener('click', function() {
        const email = this.querySelector('.demo-email').textContent;
        const password = this.querySelector('.demo-password').textContent;
        
        document.getElementById('email').value = email;
        document.getElementById('password').value = password;
        
        // Visual feedback
        this.style.transform = 'scale(0.98)';
        setTimeout(() => {
            this.style.transform = 'scale(1)';
        }, 150);
    });
});
</script>