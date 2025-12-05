<?php
// pages/notifications/index.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin(); // all roles can see notifications, but content differs

$user = currentUser();
$role = $user['role'] ?? '';

$pdo = getDBConnection();

// We show:
//  - For ADMIN/LIBRARIAN: all overdue + due-within-3-days loans
//  - For STUDENT: only their own overdue + due-soon loans

$overdue   = [];
$dueSoon   = [];

try {
    $baseSql = "
        SELECT 
            l.*,
            u.full_name AS user_name,
            u.email     AS user_email,
            b.title     AS book_title
        FROM loans l
        LEFT JOIN users u ON u.id = l.user_id
        LEFT JOIN books b ON b.id = l.book_id
        WHERE (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')
          AND due_date IS NOT NULL
    ";

    if ($role === 'STUDENT') {
        $baseSql .= " AND l.user_id = :uid";
    }

    // Overdue: due_date < today
    $sqlOverdue = $baseSql . " AND due_date < CURDATE() ORDER BY due_date ASC";
    $stmt = $pdo->prepare($sqlOverdue);
    if ($role === 'STUDENT') {
        $stmt->execute([':uid' => $user['id']]);
    } else {
        $stmt->execute();
    }
    $overdue = $stmt->fetchAll();

    // Due soon: due_date between today and today+3 days
    $sqlSoon = $baseSql . " AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                            ORDER BY due_date ASC";
    $stmt = $pdo->prepare($sqlSoon);
    if ($role === 'STUDENT') {
        $stmt->execute([':uid' => $user['id']]);
    } else {
        $stmt->execute();
    }
    $dueSoon = $stmt->fetchAll();

} catch (PDOException $e) {
    $overdue = [];
    $dueSoon = [];
}
?>
<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Notifications</h1>
            <p class="dashboard-subtitle">
                <?php if ($role === 'STUDENT'): ?>
                    Book due reminders and overdue notices for your account.
                <?php else: ?>
                    Due-soon and overdue loans across the library.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Due Soon -->
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Due Soon (next 3 days)</h2>
                <p class="card-subtitle">
                    <?php echo count($dueSoon); ?> loan(s) approaching due date.
                </p>
            </div>
        </div>

        <?php if (empty($dueSoon)): ?>
            <p class="text-muted">No items due in the next few days.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Book</th>
                    <?php if ($role !== 'STUDENT'): ?>
                        <th>User</th>
                    <?php endif; ?>
                    <th>Due Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($dueSoon as $loan): ?>
                    <?php
                    $bookTitle = $loan['book_title'] ?? 'Unknown book';
                    $userName  = $loan['user_name'] ?? ($loan['user_email'] ?? 'Unknown user');
                    $dueDate   = !empty($loan['due_date']) ? date('Y-m-d', strtotime($loan['due_date'])) : '—';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bookTitle); ?></td>
                        <?php if ($role !== 'STUDENT'): ?>
                            <td><?php echo htmlspecialchars($userName); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($dueDate); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Overdue -->
    <section class="card" style="margin-top:16px;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Overdue Loans</h2>
                <p class="card-subtitle">
                    <?php echo count($overdue); ?> loan(s) past due date.</p>
            </div>
        </div>

        <?php if (empty($overdue)): ?>
            <p class="text-muted">No overdue items at the moment.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Book</th>
                    <?php if ($role !== 'STUDENT'): ?>
                        <th>User</th>
                    <?php endif; ?>
                    <th>Due Date</th>
                    <th>Days Overdue</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($overdue as $loan): ?>
                    <?php
                    $bookTitle = $loan['book_title'] ?? 'Unknown book';
                    $userName  = $loan['user_name'] ?? ($loan['user_email'] ?? 'Unknown user');
                    $dueDate   = !empty($loan['due_date']) ? date('Y-m-d', strtotime($loan['due_date'])) : '—';

                    $daysOver = '—';
                    if (!empty($loan['due_date'])) {
                        $daysOver = (int) floor(
                            (time() - strtotime($loan['due_date'])) / 86400
                        );
                        if ($daysOver < 0) $daysOver = 0;
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bookTitle); ?></td>
                        <?php if ($role !== 'STUDENT'): ?>
                            <td><?php echo htmlspecialchars($userName); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($dueDate); ?></td>
                        <td><?php echo htmlspecialchars($daysOver); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
