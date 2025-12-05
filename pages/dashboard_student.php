<?php
// pages/dashboard_student.php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

// Check if user is student
$user = currentUser();
if (!$user || $user['role'] !== 'STUDENT') {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();
$userId = $user['id'] ?? null;

// Helper function to find columns
function stu_findColumn(array $available, array $candidates): ?string
{
    foreach ($candidates as $name) {
        if (in_array($name, $available, true)) {
            return $name;
        }
    }
    return null;
}

// Get loan stats
$loanCols = [];
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM loans');
    $loanCols = array_column($stmt->fetchAll(), 'Field');
} catch (PDOException $e) {
    $loanCols = [];
}

$loanDateCol = stu_findColumn($loanCols, ['loan_date', 'borrow_date', 'issued_date', 'issue_date', 'created_at']);
$dueDateCol  = stu_findColumn($loanCols, ['due_date', 'return_due_date', 'expected_return_date', 'due_on']);
$returnCol   = stu_findColumn($loanCols, ['returned_at', 'return_date', 'returned_date', 'actual_return_date']);
$userIdCol   = stu_findColumn($loanCols, ['user_id', 'borrower_id']);
$bookIdCol   = stu_findColumn($loanCols, ['book_id', 'copy_id']);

// Loan statistics
$totalLoans = 0;
$activeLoans = 0;
$overdueLoans = 0;

if ($userId && $userIdCol !== null) {
    try {
        $sql = "SELECT COUNT(*) AS c FROM loans WHERE `$userIdCol` = :uid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        if ($row) $totalLoans = (int)$row['c'];

        if ($returnCol !== null) {
            $sql = "
                SELECT COUNT(*) AS c
                FROM loans
                WHERE `$userIdCol` = :uid
                  AND (`$returnCol` IS NULL OR `$returnCol` = '0000-00-00' OR `$returnCol` = '0000-00-00 00:00:00')
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch();
            if ($row) $activeLoans = (int)$row['c'];
        }

        if ($returnCol !== null && $dueDateCol !== null) {
            $sql = "
                SELECT COUNT(*) AS c
                FROM loans
                WHERE `$userIdCol` = :uid
                  AND (`$returnCol` IS NULL OR `$returnCol` = '0000-00-00' OR `$returnCol` = '0000-00-00 00:00:00')
                  AND `$dueDateCol` < CURDATE()
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch();
            if ($row) $overdueLoans = (int)$row['c'];
        }
    } catch (PDOException $e) {
        // Keep defaults
    }
}

// Get top 10 popular books (most borrowed)
$popularBooks = [];
try {
    $sql = "
        SELECT 
            b.*,
            COUNT(l.id) as loan_count
        FROM books b
        LEFT JOIN loans l ON l.book_id = b.id
        GROUP BY b.id
        ORDER BY loan_count DESC, b.title ASC
        LIMIT 10
    ";
    $popularBooks = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    $popularBooks = [];
}
?>

<div class="student-dashboard">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h1>
            <p class="hero-subtitle">Discover your next great read in our library</p>
        </div>
        <div class="hero-actions">
            <a href="index.php?page=books_student" class="btn-hero btn-hero-primary">
                <span class="btn-icon">📚</span>
                Browse All Books
            </a>
            <a href="index.php?page=loans_my" class="btn-hero btn-hero-secondary">
                <span class="btn-icon">📄</span>
                My Loans
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <section class="stats-grid">
        <div class="stat-card-modern stat-card-slide" style="animation-delay: 0.1s">
            <div class="stat-icon stat-icon-blue">📖</div>
            <div class="stat-content">
                <div class="stat-value-modern"><?php echo $activeLoans; ?></div>
                <div class="stat-label-modern">Active Loans</div>
            </div>
        </div>

        <div class="stat-card-modern stat-card-slide" style="animation-delay: 0.2s">
            <div class="stat-icon stat-icon-red">⚠️</div>
            <div class="stat-content">
                <div class="stat-value-modern"><?php echo $overdueLoans; ?></div>
                <div class="stat-label-modern">Overdue Books</div>
            </div>
        </div>

        <div class="stat-card-modern stat-card-slide" style="animation-delay: 0.3s">
            <div class="stat-icon stat-icon-green">✅</div>
            <div class="stat-content">
                <div class="stat-value-modern"><?php echo $totalLoans; ?></div>
                <div class="stat-label-modern">Total Borrowed</div>
            </div>
        </div>
    </section>

    <!-- Popular Books Section -->
    <section class="popular-section">
        <div class="section-header">
            <h2 class="section-title">
                <span class="title-icon">🔥</span>
                Popular Books
            </h2>
            <p class="section-subtitle">Most borrowed books in our library</p>
        </div>

        <?php if (empty($popularBooks)): ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>No books available yet</h3>
                <p>Check back soon for popular recommendations!</p>
            </div>
        <?php else: ?>
            <div class="books-grid">
                <?php foreach ($popularBooks as $index => $book): 
                    $title = htmlspecialchars($book['title'] ?? 'Untitled');
                    $author = htmlspecialchars($book['author'] ?? 'Unknown Author');
                    $category = htmlspecialchars($book['category'] ?? 'General');
                    $year = htmlspecialchars($book['year_of_release'] ?? $book['year'] ?? 'N/A');
                    $description = htmlspecialchars($book['description'] ?? 'No description available for this book.');
                    $review = htmlspecialchars($book['review'] ?? '');
                    $coverUrl = htmlspecialchars($book['cover_url'] ?? '');
                    $isbn = htmlspecialchars($book['isbn'] ?? '');
                    $loanCount = (int)($book['loan_count'] ?? 0);
                    
                    // Truncate description
                    $shortDesc = mb_strlen($description) > 120 ? mb_substr($description, 0, 120) . '...' : $description;
                    $shortReview = $review && mb_strlen($review) > 80 ? mb_substr($review, 0, 80) . '...' : $review;
                    
                    // Generate placeholder color
                    $colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4'];
                    $colorIndex = $index % count($colors);
                    $bgColor = $colors[$colorIndex];
                    
                    $delay = ($index * 0.1);
                ?>
                    <div class="book-card" style="animation-delay: <?php echo $delay; ?>s">
                        <div class="book-cover-container">
                            <?php if ($coverUrl): ?>
                                <img src="<?php echo $coverUrl; ?>" alt="<?php echo $title; ?>" class="book-cover-img">
                            <?php else: ?>
                                <div class="book-cover-placeholder" style="background: <?php echo $bgColor; ?>">
                                    <div class="book-cover-icon">📖</div>
                                    <div class="book-cover-title"><?php echo mb_substr($title, 0, 30); ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="book-rank">#<?php echo $index + 1; ?></div>
                            <?php if ($loanCount > 0): ?>
                                <div class="book-popularity">
                                    <span class="fire-icon">🔥</span>
                                    <span><?php echo $loanCount; ?> loans</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="book-content">
                            <div class="book-category"><?php echo $category; ?></div>
                            <h3 class="book-title"><?php echo $title; ?></h3>
                            <div class="book-author">by <?php echo $author; ?></div>
                            
                            <div class="book-meta">
                                <span class="book-meta-item">
                                    <span class="meta-icon">📅</span>
                                    <?php echo $year; ?>
                                </span>
                                <?php if ($isbn): ?>
                                    <span class="book-meta-item">
                                        <span class="meta-icon">🏷️</span>
                                        <?php echo $isbn; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="book-description"><?php echo $shortDesc; ?></p>
                            
                            <?php if ($shortReview): ?>
                                <div class="book-review">
                                    <div class="review-icon">💭</div>
                                    <p class="review-text"><?php echo $shortReview; ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="book-actions">
                                <a href="index.php?page=book_detail&id=<?php echo (int)$book['id']; ?>" class="btn-book btn-book-primary">
                                    <span>View Details</span>
                                    <span class="btn-arrow">→</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<style>
/* Student Dashboard Styles */
.student-dashboard {
    max-width: 1400px;
    margin: 0 auto;
}

/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 48px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
    animation: fadeInUp 0.6s ease;
}

.hero-content {
    margin-bottom: 24px;
}

.hero-title {
    font-size: 36px;
    font-weight: 800;
    margin-bottom: 8px;
    animation: slideInLeft 0.8s ease;
}

.hero-subtitle {
    font-size: 18px;
    opacity: 0.95;
    animation: slideInLeft 0.8s ease 0.2s backwards;
}

.hero-actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    animation: slideInLeft 0.8s ease 0.4s backwards;
}

.btn-hero {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-hero-primary {
    background: white;
    color: #667eea;
}

.btn-hero-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
}

.btn-hero-secondary {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    backdrop-filter: blur(10px);
}

.btn-hero-secondary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

.btn-icon {
    font-size: 20px;
}

/* Modern Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card-modern {
    background: white;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.stat-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
}

.stat-card-slide {
    animation: slideInUp 0.6s ease backwards;
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
}

.stat-icon-blue {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon-red {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-icon-green {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-content {
    flex: 1;
}

.stat-value-modern {
    font-size: 32px;
    font-weight: 800;
    color: #111827;
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label-modern {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

/* Popular Section */
.popular-section {
    animation: fadeIn 0.8s ease;
}

.section-header {
    margin-bottom: 32px;
}

.section-title {
    font-size: 28px;
    font-weight: 800;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.title-icon {
    font-size: 32px;
    animation: bounce 2s infinite;
}

.section-subtitle {
    font-size: 16px;
    color: #6b7280;
}

/* Books Grid */
.books-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 28px;
}

.book-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeInScale 0.6s ease backwards;
}

.book-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.book-cover-container {
    position: relative;
    width: 100%;
    height: 280px;
    overflow: hidden;
    background: #f3f4f6;
}

.book-cover-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.book-card:hover .book-cover-img {
    transform: scale(1.05);
}

.book-cover-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    padding: 20px;
    text-align: center;
}

.book-cover-icon {
    font-size: 48px;
    margin-bottom: 12px;
    animation: float 3s ease-in-out infinite;
}

.book-cover-title {
    font-size: 18px;
    font-weight: 700;
    line-height: 1.3;
}

.book-rank {
    position: absolute;
    top: 16px;
    left: 16px;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 700;
    backdrop-filter: blur(10px);
}

.book-popularity {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.95);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(10px);
}

.fire-icon {
    animation: pulse 1.5s infinite;
}

.book-content {
    padding: 24px;
}

.book-category {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.book-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 8px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.book-author {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 12px;
    font-style: italic;
}

.book-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.book-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    color: #6b7280;
}

.meta-icon {
    font-size: 16px;
}

.book-description {
    font-size: 14px;
    color: #4b5563;
    line-height: 1.6;
    margin-bottom: 16px;
}

.book-review {
    background: #f9fafb;
    border-left: 3px solid #667eea;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 16px;
}

.review-icon {
    font-size: 18px;
    margin-bottom: 4px;
}

.review-text {
    font-size: 13px;
    color: #4b5563;
    font-style: italic;
    line-height: 1.5;
    margin: 0;
}

.book-actions {
    display: flex;
    gap: 8px;
}

.btn-book {
    flex: 1;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-book-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-book-primary:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-arrow {
    transition: transform 0.3s ease;
}

.btn-book-primary:hover .btn-arrow {
    transform: translateX(4px);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
    border: 2px dashed #e5e7eb;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    animation: float 3s ease-in-out infinite;
}

.empty-state h3 {
    font-size: 20px;
    color: #111827;
    margin-bottom: 8px;
}

.empty-state p {
    color: #6b7280;
    font-size: 14px;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Responsive */
@media (max-width: 768px) {
    .hero-section {
        padding: 32px 24px;
    }
    
    .hero-title {
        font-size: 28px;
    }
    
    .books-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>