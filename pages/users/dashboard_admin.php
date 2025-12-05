<?php
// C:\xampp\htdocs\bookbyte\pages\users\dashboard_admin.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin('ADMIN');

$user = currentUser();

// Default stats
$stats = [
    'total_users'          => 'N/A',
    'total_books'          => 'N/A',
    'books_borrowed_today' => 'N/A',
    'overdue_loans'        => 'N/A',
];

$recentLoans = [];

try {
    $pdo = getDBConnection();

    // Total users
    try {
        $row = $pdo->query('SELECT COUNT(*) AS cnt FROM users')->fetch();
        if ($row && isset($row['cnt'])) {
            $stats['total_users'] = (int)$row['cnt'];
        }
    } catch (PDOException $e) {}

    // Total books
    try {
        $row = $pdo->query('SELECT COUNT(*) AS cnt FROM books')->fetch();
        if ($row && isset($row['cnt'])) {
            $stats['total_books'] = (int)$row['cnt'];
        }
    } catch (PDOException $e) {}

    // Books borrowed today
    try {
        $row = $pdo->query("SELECT COUNT(*) AS cnt FROM loans WHERE DATE(loan_date) = CURDATE()")->fetch();
        if ($row && isset($row['cnt'])) {
            $stats['books_borrowed_today'] = (int)$row['cnt'];
        }
    } catch (PDOException $e) {}

    // Overdue loans
    try {
        $sql = "SELECT COUNT(*) AS cnt
                FROM loans
                WHERE due_date < CURDATE()
                  AND (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')";
        $row = $pdo->query($sql)->fetch();
        if ($row && isset($row['cnt'])) {
            $stats['overdue_loans'] = (int)$row['cnt'];
        }
    } catch (PDOException $e) {}

    // Recent activity
    try {
        $sql = "
            SELECT
                l.id,
                l.loan_date,
                l.due_date,
                l.returned_at,
                u.email AS user_email,
                u.full_name AS user_name,
                b.title AS book_title
            FROM loans l
            LEFT JOIN users u ON u.id = l.user_id
            LEFT JOIN books b ON b.id = l.book_id
            ORDER BY l.loan_date DESC
            LIMIT 5
        ";
        $recentLoans = $pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        $recentLoans = [];
    }

} catch (PDOException $e) {
    echo '<div class="card"><p>Unable to load dashboard data. Please check the database connection.</p></div>';
    return;
}
?>
<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Admin Dashboard</h1>
            <p class="dashboard-subtitle">
                Overview of library activity and key statistics.
            </p>
        </div>
        <div class="dashboard-welcome">
            <span class="text-muted">Logged in as</span>
            <span class="dashboard-user-name">
                <?php echo htmlspecialchars($user['full_name']); ?>
            </span>
        </div>
    </div>

    <section class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?php echo htmlspecialchars($stats['total_users']); ?></div>
            <div class="stat-caption">All registered accounts</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Books</div>
            <div class="stat-value"><?php echo htmlspecialchars($stats['total_books']); ?></div>
            <div class="stat-caption">Books in catalog</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Borrowed Today</div>
            <div class="stat-value"><?php echo htmlspecialchars($stats['books_borrowed_today']); ?></div>
            <div class="stat-caption">Loans created today</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Overdue Loans</div>
            <div class="stat-value"><?php echo htmlspecialchars($stats['overdue_loans']); ?></div>
            <div class="stat-caption">Past due and not returned</div>
        </div>
    </section>

    <section class="card dashboard-recent">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent Loan Activity</h2>
                <p class="card-subtitle">Last 5 loans recorded in the system.</p>
            </div>
        </div>

        <?php if (empty($recentLoans)): ?>
            <p class="text-muted">No recent loans found or activity could not be loaded.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Book</th>
                    <th>User</th>
                    <th>Loan Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentLoans as $loan): ?>
                    <?php
                    $bookTitle = $loan['book_title'] ?? 'Unknown book';
                    $userName  = $loan['user_name'] ?? ($loan['user_email'] ?? 'Unknown user');
                    $loanDate  = $loan['loan_date'] ? date('Y-m-d', strtotime($loan['loan_date'])) : '-';
                    $dueDate   = $loan['due_date'] ? date('Y-m-d', strtotime($loan['due_date'])) : '-';

                    $status     = 'Active';
                    $badgeClass = 'badge-blue';

                    if (!empty($loan['returned_at']) && $loan['returned_at'] !== '0000-00-00 00:00:00') {
                        $status     = 'Returned';
                        $badgeClass = 'badge-green';
                    } elseif (!empty($loan['due_date']) && strtotime($loan['due_date']) < strtotime('today')) {
                        $status     = 'Overdue';
                        $badgeClass = 'badge-red';
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bookTitle); ?></td>
                        <td><?php echo htmlspecialchars($userName); ?></td>
                        <td><?php echo htmlspecialchars($loanDate); ?></td>
                        <td><?php echo htmlspecialchars($dueDate); ?></td>
                        <td>
                            <span class="badge <?php echo $badgeClass; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
