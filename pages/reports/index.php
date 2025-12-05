<?php
// pages/reports/index.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin('ADMIN'); // reports are admin-only for now

$pdo = getDBConnection();

$stats = [
    'total_loans'       => 'N/A',
    'active_loans'      => 'N/A',
    'overdue_loans'     => 'N/A',
    'loans_this_month'  => 'N/A',
];

$topBooks  = [];
$topUsers  = [];

try {
    // Total loans
    $row = $pdo->query("SELECT COUNT(*) AS c FROM loans")->fetch();
    if ($row && isset($row['c'])) $stats['total_loans'] = (int)$row['c'];

    // Active loans
    $row = $pdo->query("
        SELECT COUNT(*) AS c 
        FROM loans 
        WHERE returned_at IS NULL OR returned_at = '0000-00-00 00:00:00'
    ")->fetch();
    if ($row && isset($row['c'])) $stats['active_loans'] = (int)$row['c'];

    // Overdue loans
    $row = $pdo->query("
        SELECT COUNT(*) AS c 
        FROM loans 
        WHERE (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')
          AND due_date < CURDATE()
    ")->fetch();
    if ($row && isset($row['c'])) $stats['overdue_loans'] = (int)$row['c'];

    // Loans created this month
    $row = $pdo->query("
        SELECT COUNT(*) AS c 
        FROM loans 
        WHERE loan_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ")->fetch();
    if ($row && isset($row['c'])) $stats['loans_this_month'] = (int)$row['c'];

    // Top 5 most borrowed books
    $sql = "
        SELECT 
            b.title AS book_title,
            COUNT(*) AS loan_count
        FROM loans l
        LEFT JOIN books b ON b.id = l.book_id
        GROUP BY l.book_id, b.title
        ORDER BY loan_count DESC
        LIMIT 5
    ";
    $topBooks = $pdo->query($sql)->fetchAll();

    // Top 5 users by number of loans
    $sql = "
        SELECT 
            u.full_name AS user_name,
            u.email     AS user_email,
            COUNT(*)    AS loan_count
        FROM loans l
        LEFT JOIN users u ON u.id = l.user_id
        GROUP BY l.user_id, u.full_name, u.email
        ORDER BY loan_count DESC
        LIMIT 5
    ";
    $topUsers = $pdo->query($sql)->fetchAll();

} catch (PDOException $e) {
    // If any query fails, we just keep partial data.
}
?>
<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Reports &amp; Tracking</h1>
            <p class="dashboard-subtitle">
                High-level overview of borrowing activity and top performers.
            </p>
        </div>
    </div>

    <!-- Summary cards -->
    <section class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-label">Total Loans</div>
            <div class="stat-value"><?php echo htmlspecialchars($stats['total_loans']); ?></div>
            <div class="stat-caption">All-time loan records</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Active Loans</div>
            <div class="stat-value"><?php echo htmlspecialchars($stats['active_loans']); ?></div>
            <div class="stat-caption">Currently borrowed books</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Overdue Loans</div>
            <div class="stat-value"><?php echo htmlspecialchars($stats['overdue_loans']); ?></div>
            <div class="stat-caption">Past due and not returned</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Loans This Month</div>
            <div class="stat-value"><?php echo htmlspecialchars($stats['loans_this_month']); ?></div>
            <div class="stat-caption">New loans since month start</div>
        </div>
    </section>

    <!-- Top books -->
    <section class="card" style="margin-top:16px;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Top Borrowed Books</h2>
                <p class="card-subtitle">Books with the highest number of loans.</p>
            </div>
        </div>

        <?php if (empty($topBooks)): ?>
            <p class="text-muted">No book loan data available.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Book</th>
                    <th>Loans</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($topBooks as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['book_title'] ?? 'Unknown'); ?></td>
                        <td><?php echo (int)($row['loan_count'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Top users -->
    <section class="card" style="margin-top:16px;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Top Borrowers</h2>
                <p class="card-subtitle">Users with the highest number of loans.</p>
            </div>
        </div>

        <?php if (empty($topUsers)): ?>
            <p class="text-muted">No user loan data available.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Loans</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($topUsers as $row): ?>
                    <?php
                    $name  = $row['user_name'] ?? ($row['user_email'] ?? 'Unknown user');
                    $email = $row['user_email'] ?? '';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($name); ?></td>
                        <td><?php echo htmlspecialchars($email); ?></td>
                        <td><?php echo (int)($row['loan_count'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
