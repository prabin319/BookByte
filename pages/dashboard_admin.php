<?php
// pages/dashboard_admin.php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

// Check if user is admin
$user = currentUser();
if (!$user || $user['role'] !== 'ADMIN') {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();

// Default stats
$stats = [
    'total_users'          => 0,
    'total_books'          => 0,
    'active_loans'         => 0,
    'overdue_loans'        => 0,
];

$recentActivity = [];

try {
    // Total users
    $row = $pdo->query('SELECT COUNT(*) AS cnt FROM users')->fetch();
    if ($row) $stats['total_users'] = (int)$row['cnt'];

    // Total books
    $row = $pdo->query('SELECT COUNT(*) AS cnt FROM books')->fetch();
    if ($row) $stats['total_books'] = (int)$row['cnt'];

    // Active loans
    $row = $pdo->query("
        SELECT COUNT(*) AS cnt 
        FROM loans 
        WHERE returned_at IS NULL OR returned_at = '0000-00-00 00:00:00'
    ")->fetch();
    if ($row) $stats['active_loans'] = (int)$row['cnt'];

    // Overdue loans
    $row = $pdo->query("
        SELECT COUNT(*) AS cnt
        FROM loans
        WHERE (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')
          AND due_date < CURDATE()
    ")->fetch();
    if ($row) $stats['overdue_loans'] = (int)$row['cnt'];

    // Recent activity
    $sql = "
        SELECT
            l.id,
            l.loan_date,
            l.due_date,
            l.returned_at,
            u.email AS user_email,
            u.full_name AS user_name,
            b.title AS book_title,
            b.author AS book_author
        FROM loans l
        LEFT JOIN users u ON u.id = l.user_id
        LEFT JOIN books b ON b.id = l.book_id
        ORDER BY l.loan_date DESC
        LIMIT 8
    ";
    $recentActivity = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    // Keep defaults
}
?>

<div class="admin-dashboard">
    <!-- Hero Header -->
    <div class="admin-hero">
        <div class="hero-content-admin">
            <h1 class="admin-title">
                <span class="admin-icon">👨‍💼</span>
                Admin Dashboard
            </h1>
            <p class="admin-subtitle">Complete overview of your library management system</p>
        </div>
        <div class="admin-welcome">
            <div class="welcome-text">Welcome back,</div>
            <div class="welcome-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-section">
        <h2 class="section-title-small">
            <span class="title-icon-small">⚡</span>
            Quick Actions
        </h2>
        <div class="quick-actions-grid">
            <a href="index.php?page=users_manage" class="action-card action-card-1">
                <div class="action-icon">👥</div>
                <div class="action-content">
                    <div class="action-title">Manage Users</div>
                    <div class="action-desc">Add, edit, or remove users</div>
                </div>
                <div class="action-arrow">→</div>
            </a>

            <a href="index.php?page=books_manage" class="action-card action-card-2">
                <div class="action-icon">📚</div>
                <div class="action-content">
                    <div class="action-title">Manage Books</div>
                    <div class="action-desc">Update library catalog</div>
                </div>
                <div class="action-arrow">→</div>
            </a>

            <a href="index.php?page=loans_active" class="action-card action-card-3">
                <div class="action-icon">🔄</div>
                <div class="action-content">
                    <div class="action-title">Active Loans</div>
                    <div class="action-desc">Process returns & renewals</div>
                </div>
                <div class="action-arrow">→</div>
            </a>

            <a href="index.php?page=reports" class="action-card action-card-4">
                <div class="action-icon">📊</div>
                <div class="action-content">
                    <div class="action-title">View Reports</div>
                    <div class="action-desc">Analytics & statistics</div>
                </div>
                <div class="action-arrow">→</div>
            </a>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-section">
        <h2 class="section-title-small">
            <span class="title-icon-small">📈</span>
            Key Metrics
        </h2>
        <div class="stats-grid-admin">
            <div class="stat-card-admin stat-card-purple">
                <div class="stat-icon-admin">👥</div>
                <div class="stat-data">
                    <div class="stat-value-admin"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label-admin">Total Users</div>
                    <div class="stat-change positive">+12% this month</div>
                </div>
            </div>

            <div class="stat-card-admin stat-card-blue">
                <div class="stat-icon-admin">📚</div>
                <div class="stat-data">
                    <div class="stat-value-admin"><?php echo number_format($stats['total_books']); ?></div>
                    <div class="stat-label-admin">Total Books</div>
                    <div class="stat-change positive">+24 new books</div>
                </div>
            </div>

            <div class="stat-card-admin stat-card-green">
                <div class="stat-icon-admin">📖</div>
                <div class="stat-data">
                    <div class="stat-value-admin"><?php echo number_format($stats['active_loans']); ?></div>
                    <div class="stat-label-admin">Active Loans</div>
                    <div class="stat-change">Currently borrowed</div>
                </div>
            </div>

            <div class="stat-card-admin stat-card-red">
                <div class="stat-icon-admin">⚠️</div>
                <div class="stat-data">
                    <div class="stat-value-admin"><?php echo number_format($stats['overdue_loans']); ?></div>
                    <div class="stat-label-admin">Overdue Loans</div>
                    <div class="stat-change negative">Needs attention</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="activity-section">
        <div class="section-header-modern">
            <div>
                <h2 class="section-title-small">
                    <span class="title-icon-small">🕒</span>
                    Recent Activity
                </h2>
                <p class="section-subtitle-small">Latest loan transactions in your library</p>
            </div>
            <a href="index.php?page=loans_active" class="btn-view-all">
                View All
                <span class="btn-arrow-small">→</span>
            </a>
        </div>

        <?php if (empty($recentActivity)): ?>
            <div class="empty-activity">
                <div class="empty-icon-activity">📋</div>
                <h3>No recent activity</h3>
                <p>Loan transactions will appear here</p>
            </div>
        <?php else: ?>
            <div class="activity-grid">
                <?php foreach ($recentActivity as $index => $activity): 
                    $bookTitle = htmlspecialchars($activity['book_title'] ?? 'Unknown Book');
                    $bookAuthor = htmlspecialchars($activity['book_author'] ?? 'Unknown Author');
                    $userName = htmlspecialchars($activity['user_name'] ?? $activity['user_email'] ?? 'Unknown User');
                    $loanDate = $activity['loan_date'] ? date('M d, Y', strtotime($activity['loan_date'])) : 'N/A';
                    $dueDate = $activity['due_date'] ? date('M d, Y', strtotime($activity['due_date'])) : 'N/A';
                    $returned = $activity['returned_at'] ?? null;
                    
                    $statusClass = 'activity-status-active';
                    $statusLabel = 'Active';
                    $statusIcon = '📖';
                    
                    if (!empty($returned) && $returned !== '0000-00-00 00:00:00') {
                        $statusClass = 'activity-status-returned';
                        $statusLabel = 'Returned';
                        $statusIcon = '✓';
                    } elseif (!empty($activity['due_date']) && strtotime($activity['due_date']) < time()) {
                        $statusClass = 'activity-status-overdue';
                        $statusLabel = 'Overdue';
                        $statusIcon = '⚠️';
                    }
                    
                    $delay = ($index % 8) * 0.05;
                ?>
                    <div class="activity-card" style="animation-delay: <?php echo $delay; ?>s">
                        <div class="activity-icon-wrapper">
                            <span class="activity-icon"><?php echo $statusIcon; ?></span>
                        </div>
                        <div class="activity-info">
                            <div class="activity-book-title"><?php echo $bookTitle; ?></div>
                            <div class="activity-book-author">by <?php echo $bookAuthor; ?></div>
                            <div class="activity-meta">
                                <span class="meta-item">
                                    <span class="meta-icon">👤</span>
                                    <?php echo $userName; ?>
                                </span>
                                <span class="meta-item">
                                    <span class="meta-icon">📅</span>
                                    <?php echo $loanDate; ?>
                                </span>
                            </div>
                        </div>
                        <div class="activity-status <?php echo $statusClass; ?>">
                            <?php echo $statusLabel; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Admin Dashboard Styles */
.admin-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    animation: fadeIn 0.6s ease;
}

/* Hero Section */
.admin-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 24px;
    padding: 48px;
    margin-bottom: 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
    animation: slideDown 0.6s ease;
}

.hero-content-admin {
    color: white;
}

.admin-title {
    font-size: 40px;
    font-weight: 800;
    margin: 0 0 12px 0;
    color: white;
    display: flex;
    align-items: center;
    gap: 16px;
}

.admin-icon {
    font-size: 48px;
    animation: bounce 2s infinite;
}

.admin-subtitle {
    font-size: 18px;
    margin: 0;
    opacity: 0.95;
}

.admin-welcome {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    padding: 24px 32px;
    border-radius: 16px;
    text-align: right;
}

.welcome-text {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 4px;
}

.welcome-name {
    font-size: 24px;
    font-weight: 700;
    color: white;
}

/* Section Titles */
.section-title-small {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.title-icon-small {
    font-size: 28px;
}

.section-subtitle-small {
    font-size: 14px;
    color: #6b7280;
    margin: 0;
}

/* Quick Actions */
.quick-actions-section {
    margin-bottom: 40px;
    animation: slideUp 0.6s ease 0.2s backwards;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
}

.action-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    text-decoration: none;
    border: 2px solid transparent;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.action-card:hover::before {
    transform: scaleX(1);
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
    border-color: #667eea;
}

.action-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
}

.action-content {
    flex: 1;
}

.action-title {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.action-desc {
    font-size: 13px;
    color: #6b7280;
}

.action-arrow {
    font-size: 24px;
    color: #667eea;
    transition: transform 0.3s ease;
}

.action-card:hover .action-arrow {
    transform: translateX(4px);
}

/* Stats Section */
.stats-section {
    margin-bottom: 40px;
    animation: slideUp 0.6s ease 0.3s backwards;
}

.stats-grid-admin {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 24px;
}

.stat-card-admin {
    background: white;
    border-radius: 20px;
    padding: 28px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
}

.stat-card-admin:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
}

.stat-icon-admin {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    flex-shrink: 0;
}

.stat-card-purple .stat-icon-admin {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card-blue .stat-icon-admin {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card-green .stat-icon-admin {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-card-red .stat-icon-admin {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.stat-data {
    flex: 1;
}

.stat-value-admin {
    font-size: 36px;
    font-weight: 800;
    color: #111827;
    line-height: 1;
    margin-bottom: 6px;
}

.stat-label-admin {
    font-size: 14px;
    color: #6b7280;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-change {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
}

.stat-change.positive {
    color: #10b981;
}

.stat-change.negative {
    color: #ef4444;
}

/* Activity Section */
.activity-section {
    animation: slideUp 0.6s ease 0.4s backwards;
}

.section-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}

.btn-view-all {
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.btn-view-all:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
}

.btn-arrow-small {
    transition: transform 0.3s ease;
}

.btn-view-all:hover .btn-arrow-small {
    transform: translateX(4px);
}

.activity-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 16px;
}

.activity-card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
    animation: fadeInScale 0.5s ease backwards;
}

.activity-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    border-color: #667eea;
}

.activity-icon-wrapper {
    width: 48px;
    height: 48px;
    background: #f3f4f6;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.activity-icon {
    font-size: 24px;
}

.activity-info {
    flex: 1;
    min-width: 0;
}

.activity-book-title {
    font-size: 15px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.activity-book-author {
    font-size: 13px;
    color: #6b7280;
    font-style: italic;
    margin-bottom: 8px;
}

.activity-meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #9ca3af;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
}

.meta-icon {
    font-size: 14px;
}

.activity-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.activity-status-active {
    background: #dbeafe;
    color: #1e40af;
}

.activity-status-returned {
    background: #dcfce7;
    color: #166534;
}

.activity-status-overdue {
    background: #fee2e2;
    color: #b91c1c;
}

/* Empty State */
.empty-activity {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    border: 2px dashed #e5e7eb;
}

.empty-icon-activity {
    font-size: 64px;
    margin-bottom: 16px;
    animation: float 3s ease-in-out infinite;
}

.empty-activity h3 {
    font-size: 20px;
    color: #111827;
    margin-bottom: 8px;
}

.empty-activity p {
    color: #6b7280;
    font-size: 14px;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
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
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

/* Responsive */
@media (max-width: 768px) {
    .admin-hero {
        flex-direction: column;
        gap: 24px;
        padding: 32px 24px;
    }
    
    .admin-title {
        font-size: 32px;
    }
    
    .admin-welcome {
        width: 100%;
        text-align: center;
    }
    
    .quick-actions-grid,
    .stats-grid-admin,
    .activity-grid {
        grid-template-columns: 1fr;
    }
}
</style>