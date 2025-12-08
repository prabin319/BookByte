<?php
// pages/landing.php - Main landing page before login

// If user is already logged in, redirect to their dashboard
require_once __DIR__ . '/../lib/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isLoggedIn()) {
    $user = currentUser();
    $role = $user['role'] ?? 'STUDENT';
    
    if ($role === 'ADMIN') {
        header('Location: index.php?page=dashboard_admin');
    } elseif ($role === 'LIBRARIAN') {
        header('Location: index.php?page=dashboard_librarian');
    } else {
        header('Location: index.php?page=dashboard_student');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookByte - Modern Library Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow-x: hidden;
            max-width: 100vw;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: white;
            position: relative;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 800;
            color: white;
            text-decoration: none;
        }

        .logo-icon {
            font-size: 32px;
            animation: float 3s ease-in-out infinite;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .nav-links a:hover {
            color: white;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        /* Hero Section */
        .hero {
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 120px 5% 80px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: min(800px, 50vw);
            height: min(800px, 50vw);
            background: radial-gradient(circle, rgba(102, 126, 234, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 4s ease-in-out infinite;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -20%;
            width: min(600px, 40vw);
            height: min(600px, 40vw);
            background: radial-gradient(circle, rgba(118, 75, 162, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 5s ease-in-out infinite;
        }

        .hero-content {
            max-width: 1200px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: clamp(40px, 8vw, 80px);
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-text h1 {
            font-size: clamp(36px, 5vw, 64px);
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: slideInLeft 0.8s ease;
        }

        .hero-text p {
            font-size: clamp(16px, 2vw, 20px);
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 40px;
            line-height: 1.6;
            animation: slideInLeft 0.8s ease 0.2s backwards;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            animation: slideInLeft 0.8s ease 0.4s backwards;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 36px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: clamp(16px, 1.5vw, 18px);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            padding: 16px 36px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: clamp(16px, 1.5vw, 18px);
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
            white-space: nowrap;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
        }

        .hero-visual {
            position: relative;
            animation: slideInRight 0.8s ease;
            width: 100%;
        }

        .floating-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: clamp(20px, 4vw, 40px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
        }

        .card-stat {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .card-stat:last-child {
            margin-bottom: 0;
        }

        .stat-icon {
            width: clamp(50px, 6vw, 60px);
            height: clamp(50px, 6vw, 60px);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(24px, 3vw, 28px);
            flex-shrink: 0;
        }

        .stat-info h3 {
            font-size: clamp(24px, 3vw, 32px);
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stat-info p {
            font-size: clamp(12px, 1.5vw, 14px);
            color: rgba(255, 255, 255, 0.6);
        }

        /* Features Section */
        .features {
            width: 100%;
            padding: clamp(60px, 10vw, 100px) 5%;
            background: #1e293b;
        }

        .section-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 80px;
            padding: 0 20px;
        }

        .section-header h2 {
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-header p {
            font-size: clamp(16px, 2vw, 18px);
            color: rgba(255, 255, 255, 0.6);
        }

        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
            gap: clamp(20px, 4vw, 40px);
            width: 100%;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: clamp(30px, 4vw, 40px);
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(102, 126, 234, 0.5);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .feature-icon {
            width: clamp(60px, 7vw, 70px);
            height: clamp(60px, 7vw, 70px);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(28px, 4vw, 36px);
            margin-bottom: 24px;
        }

        .feature-card h3 {
            font-size: clamp(20px, 2.5vw, 24px);
            font-weight: 700;
            margin-bottom: 12px;
        }

        .feature-card p {
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.6;
            font-size: clamp(14px, 1.5vw, 16px);
        }

        /* Stats Section */
        .stats {
            width: 100%;
            padding: clamp(60px, 8vw, 80px) 5%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stats-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
            gap: clamp(30px, 6vw, 60px);
            text-align: center;
        }

        .stat-item h3 {
            font-size: clamp(40px, 6vw, 56px);
            font-weight: 900;
            margin-bottom: 8px;
        }

        .stat-item p {
            font-size: clamp(16px, 2vw, 18px);
            opacity: 0.9;
        }

        /* CTA Section */
        .cta {
            width: 100%;
            padding: clamp(60px, 10vw, 100px) 5%;
            background: #0f172a;
            text-align: center;
        }

        .cta h2 {
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .cta p {
            font-size: clamp(16px, 2vw, 20px);
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Footer */
        .footer {
            width: 100%;
            background: #0f172a;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px 5%;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
        }

        .footer p {
            font-size: clamp(13px, 1.5vw, 14px);
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.15; }
            50% { transform: scale(1.1); opacity: 0.2; }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Breakpoints */
        @media (max-width: 968px) {
            .hero-content {
                grid-template-columns: 1fr;
                gap: 60px;
            }

            .nav-links a:not(.btn-login) {
                font-size: 14px;
            }

            .hero-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 640px) {
            .navbar {
                padding: 15px 5%;
            }

            .nav-links {
                gap: 15px;
            }

            .nav-links a:not(.btn-login) {
                display: none;
            }

            .btn-login {
                padding: 10px 20px;
                font-size: 14px;
            }

            .hero {
                padding: 100px 5% 60px;
            }

            .hero-buttons {
                flex-direction: column;
                width: 100%;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
                justify-content: center;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }

        @media (max-width: 380px) {
            .logo {
                font-size: 20px;
            }

            .logo-icon {
                font-size: 28px;
            }

            .card-stat {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="index.php" class="logo">
            <span class="logo-icon">📚</span>
            BookByte
        </a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="index.php?page=login" class="btn-login">Login</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Modern Library Management Made Simple</h1>
                <p>Streamline your library operations with BookByte - the complete solution for managing books, loans, users, and more.</p>
                <div class="hero-buttons">
                    <a href="index.php?page=login" class="btn-primary">
                        Get Started
                        <span>→</span>
                    </a>
                    <a href="#features" class="btn-secondary">Learn More</a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="floating-card">
                    <div class="card-stat">
                        <div class="stat-icon">📚</div>
                        <div class="stat-info">
                            <h3>10,000+</h3>
                            <p>Books Managed</p>
                        </div>
                    </div>
                    <div class="card-stat">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3>5,000+</h3>
                            <p>Active Users</p>
                        </div>
                    </div>
                    <div class="card-stat">
                        <div class="stat-icon">🔄</div>
                        <div class="stat-info">
                            <h3>50,000+</h3>
                            <p>Loans Processed</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-header">
            <h2>Powerful Features</h2>
            <p>Everything you need to run a modern library efficiently</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📖</div>
                <h3>Book Management</h3>
                <p>Easily catalog, organize, and track your entire book collection with our intuitive interface.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔄</div>
                <h3>Smart Borrowing</h3>
                <p>Automated loan tracking, due date reminders, and seamless return processing.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>User Management</h3>
                <p>Manage students, staff, and admins with role-based access control.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Fine Management</h3>
                <p>Automatic fine calculation for overdue books with flexible payment tracking.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📧</div>
                <h3>Notifications</h3>
                <p>Automated email and SMS reminders for due dates and overdue books.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Reports & Analytics</h3>
                <p>Comprehensive reports on borrowing trends, popular books, and more.</p>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <h3>99.9%</h3>
                <p>Uptime Reliability</p>
            </div>
            <div class="stat-item">
                <h3>24/7</h3>
                <p>System Availability</p>
            </div>
            <div class="stat-item">
                <h3>100+</h3>
                <p>Libraries Trust Us</p>
            </div>
            <div class="stat-item">
                <h3>5★</h3>
                <p>User Satisfaction</p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" id="about">
        <h2>Ready to Transform Your Library?</h2>
        <p>Join hundreds of libraries already using BookByte to streamline their operations.</p>
        <a href="index.php?page=login" class="btn-primary">
            Start Using BookByte
            <span>→</span>
        </a>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> BookByte LMS. All rights reserved.</p>
        <p style="margin-top: 10px;">Modern Library Management System</p>
    </footer>
</body>
</html>