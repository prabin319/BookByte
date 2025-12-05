<?php
// pages/users/dashboard_student.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin('STUDENT');

$pdo   = getDBConnection();
$user  = currentUser();
$userId = $user['id'] ?? null;

if ($userId === null) {
    // Safety: if id is missing, we can't show stats
    $totalLoans     = 0;
    $activeLoans    = 0;
    $overdueLoans   = 0;
    $recentLoans    = [];
} else {

    function stu_findColumn(array $available, array $candidates): ?string
    {
        foreach ($candidates as $name) {
            if (in_array($name, $available, true)) {
                return $name;
            }
        }
        return null;
    }

    // Inspect loans table
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

    // Defaults
    $totalLoans   = 0;
    $activeLoans  = 0;
    $overdueLoans = 0;
    $recentLoans  = [];

    if ($userIdCol !== null) {
        try {
            // Total loans for this user
            $sql = "SELECT COUNT(*) AS c FROM loans WHERE `$userIdCol` = :uid";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch();
            if ($row) $totalLoans = (int)$row['c'];

            // Active loans
            if ($returnCol !== null) {
                $sql = "
                    SELECT COUNT(*) AS c
                    FROM loans
                    WHERE `$userIdCol` = :uid
                      AND (`$returnCol` IS NULL
                           OR `$returnCol` = '0000-00-00'
                           OR `$returnCol` = '0000-00-00 00:00:00')
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['uid' => $userId]);
                $row = $stmt->fetch();
                if ($row) $activeLoans = (int)$row['c'];
            }

            // Overdue loans
            if ($returnCol !== null && $dueDateCol !== null) {
                $sql = "
                    SELECT COUNT(*) AS c
                    FROM loans
                    WHERE `$userIdCol` = :uid
                      AND (`$returnCol` IS NULL
                           OR `$returnCol` = '0000-00-00'
                           OR `$returnCol` = '0000-00-00 00:00:00')
                      AND `$dueDateCol` < CURDATE()
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['uid' => $userId]);
                $row = $stmt->fetch();
                if ($row) $overdueLoans = (int)$row['c'];
            }

            // Recent loans (last 5)
            $loanDateExpr = $loanDateCol ? "l.`$loanDateCol`" : "l.id";
            $dueDateExpr  = $dueDateCol ? "l.`$dueDateCol`" : "NULL";
            $returnExpr   = $returnCol ? "l.`$returnCol`" : "NULL";

            $sql = "
                SELECT 
                    $loanDateExpr AS loan_date,
                    $dueDateExpr  AS due_date,
                    $returnExpr   AS returned_at,
                    b.title       AS book_title
                FROM loans l
                LEFT JOIN books b ON " . ($bookIdCol ? "b.id = l.`$bookIdCol`" : "1 = 1") . "
                WHERE l.`$userIdCol` = :uid
                ORDER BY $loanDateExpr DESC
                LIMIT 5
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['uid' => $userId]);
            $recentLoans = $stmt->fetchAll();

        } catch (PDOException $e) {
            $totalLoans   = 0;
            $activeLoans  = 0;
            $overdueLoans = 0;
            $recentLoans  = [];
        }
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">My Library Dashboard</h1>
            <p class="dashboard-subtitle">
                See an overview of your borrowed books and loan history.
            </p>
        </div>
    </div>

    <!-- Summary cards -->
    <section class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-label">Active Loans</div>
            <div class="stat-value"><?php echo $activeLoans; ?></div>
            <div class="stat-caption">Books you currently have</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Overdue Loans</div>
            <div class="stat-value"><?php echo $overdueLoans; ?></div>
            <div class="stat-caption">Past due and not returned</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Loans</div>
            <div class="stat-value"><?php echo $totalLoans; ?></div>
            <div class="stat-caption">All loans you've made</div>
        </div>
    </section>

    <!-- Recent loans table -->
    <section class="card" style="margin-top:18px;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent Loans</h2>
                <p class="card-subtitle">
                    Your latest borrowed books and their status.
                </p>
            </div>
        </div>

        <?php if (empty($recentLoans)): ?>
            <p class="text-muted">You have no loan history yet.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Book</th>
                    <th>Loan Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentLoans as $row): ?>
                    <?php
                    $returned = $row['returned_at'] ?? null;
                    $status   = 'Returned';

                    if ($returned === null || $returned === '0000-00-00' || $returned === '0000-00-00 00:00:00') {
                        $status = 'Borrowed';
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['book_title'] ?? 'Unknown book'); ?></td>
                        <td><?php echo htmlspecialchars($row['loan_date'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['due_date'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($status); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card" style="margin-top:16px;">
        <p class="text-muted">
            Tip: You can view full details under <strong>My Loans</strong> in the menu.
        </p>
    </section>
</div>
