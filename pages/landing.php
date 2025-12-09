<?php
// pages/landing.php - Horizontal Scrolling Landing Page

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

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: white;
            overflow: hidden;
        }

        /* Navigation - Fixed */
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
            cursor: pointer;
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
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        /* Horizontal Scroll Container */
        .scroll-container {
            display: flex;
            flex-direction: row;
            height: 100vh;
            width: 300vw;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            position: fixed;
            top: 0;
            left: 0;
        }

        .scroll-container::-webkit-scrollbar {
            display: none;
        }
        
        .scroll-container {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Individual Sections - Full Screen Width */
        .section {
            flex: 0 0 100vw;
            width: 100vw;
            min-width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            scroll-snap-align: start;
            position: relative;
            padding: 80px 5% 40px;
            box-sizing: border-box;
        }

        /* Hero Section */
        #hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        .hero-content {
            max-width: 1400px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .hero-text h1 {
            font-size: clamp(40px, 5vw, 64px);
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-text p {
            font-size: clamp(18px, 2vw, 22px);
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 40px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 18px 40px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
        }

        .hero-visual {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
        }

        .stat-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Features Section */
        #features {
            background: #1e293b;
        }

        .features-content {
            max-width: 1600px;
            width: 100%;
        }

        .section-title {
            font-size: clamp(36px, 4vw, 48px);
            font-weight: 800;
            text-align: center;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .section-subtitle {
            font-size: 18px;
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 50px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 35px;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(102, 126, 234, 0.5);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 22px;
            margin-bottom: 12px;
        }

        .feature-card p {
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.6;
            font-size: 15px;
        }

        /* About/Stats Section */
        #about {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .about-content {
            max-width: 1400px;
            width: 100%;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 50px;
            margin: 50px 0;
        }

        .stat-item h3 {
            font-size: 56px;
            font-weight: 900;
            margin-bottom: 10px;
        }

        .stat-item p {
            font-size: 18px;
            opacity: 0.9;
        }

        .cta-text {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .cta-subtitle {
            font-size: 20px;
            opacity: 0.9;
            margin-bottom: 40px;
        }

        /* Scroll Indicator */
        .scroll-indicator {
            position: fixed;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 100;
        }

        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s;
        }

        .dot.active {
            background: white;
            width: 30px;
            border-radius: 6px;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 968px) {
            .hero-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .hero-visual {
                grid-template-columns: 1fr 1fr;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }

            .nav-links a:not(.btn-login) {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .hero-visual {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Fixed Navigation -->
    <nav class="navbar">
        <a href="#hero" class="logo">
            <span class="logo-icon">📚</span>
            BookByte
        </a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="index.php?page=login" class="btn-login">Login</a>
        </div>
    </nav>

    <!-- Horizontal Scroll Container -->
    <div class="scroll-container">
        
        <!-- Section 1: Hero -->
        <section id="hero" class="section">
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
                    <div class="stat-card">
                        <div class="stat-icon">📚</div>
                        <div class="stat-number">10,000+</div>
                        <div class="stat-label">Books Managed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-number">5,000+</div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🔄</div>
                        <div class="stat-number">50,000+</div>
                        <div class="stat-label">Loans Processed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⚡</div>
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">System Uptime</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section 2: Features -->
        <section id="features" class="section">
            <div class="features-content">
                <h2 class="section-title">Powerful Features</h2>
                <p class="section-subtitle">Everything you need to run a modern library efficiently</p>
                
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
            </div>
        </section>

        <!-- Section 3: About/Stats -->
        <section id="about" class="section">
            <div class="about-content">
                <h2 class="cta-text">Ready to Transform Your Library?</h2>
                <p class="cta-subtitle">Join hundreds of libraries already using BookByte to streamline their operations.</p>
                
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

                <a href="index.php?page=login" class="btn-primary" style="font-size: 20px; padding: 20px 50px;">
                    Start Using BookByte
                    <span>→</span>
                </a>

                <p style="margin-top: 40px; opacity: 0.8;">
                    &copy; <?php echo date('Y'); ?> BookByte LMS. All rights reserved.
                </p>
            </div>
        </section>

    </div>

    <!-- Scroll Indicators -->
    <div class="scroll-indicator">
        <div class="dot active" onclick="scrollToSection('hero')"></div>
        <div class="dot" onclick="scrollToSection('features')"></div>
        <div class="dot" onclick="scrollToSection('about')"></div>
    </div>

    <script>
        const scrollContainer = document.querySelector('.scroll-container');
        const dots = document.querySelectorAll('.dot');
        
        // Smooth scroll to sections
        function scrollToSection(sectionId) {
            const section = document.getElementById(sectionId);
            if (section) {
                const sectionIndex = Array.from(document.querySelectorAll('.section')).indexOf(section);
                scrollContainer.scrollTo({
                    left: sectionIndex * window.innerWidth,
                    behavior: 'smooth'
                });
            }
        }
        
        function updateActiveDot() {
            const scrollPos = scrollContainer.scrollLeft;
            const windowWidth = window.innerWidth;
            const sectionIndex = Math.round(scrollPos / windowWidth);
            
            dots.forEach((dot, index) => {
                if (index === sectionIndex) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
        }
        
        scrollContainer.addEventListener('scroll', updateActiveDot);
        window.addEventListener('load', updateActiveDot);

        // Handle all navigation links
        document.querySelectorAll('a[href^="#"]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('href').substring(1);
                scrollToSection(targetId);
            });
        });
        
        // Handle navbar links specifically
        document.querySelector('.nav-links a[href="#features"]')?.addEventListener('click', (e) => {
            e.preventDefault();
            scrollToSection('features');
        });
        
        document.querySelector('.nav-links a[href="#about"]')?.addEventListener('click', (e) => {
            e.preventDefault();
            scrollToSection('about');
        });
        
        // Handle logo click
        document.querySelector('.logo')?.addEventListener('click', (e) => {
            e.preventDefault();
            scrollToSection('hero');
        });
    </script>
</body>
</html>