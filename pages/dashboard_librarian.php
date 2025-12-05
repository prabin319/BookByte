<?php
// pages/dashboard_librarian.php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

// Check if user is librarian
$user = currentUser();
if (!$user || $user['role'] !== 'LIBRARIAN') {
    header('Location: index.php');
    exit;
}
$pdo = getDBConnection();

/**
 * Helper: safe COUNT(*) on a table.
 */
function lib_safeCount(PDO $pdo, string $table): int
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM `$table`");
        $row = $stmt->fetch();
        return $row ? (int)$row['c'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Helper: pick the first existing column from a list of candidates.
 */
function lib_findColumn(array $available, array $candidates): ?string
{
    foreach ($candidates as $name) {
        if (in_array($name, $available, true)) {
            return $name;
        }
    }
    return null;
}

// ---------- BASIC COUNTS ----------
$totalBooks      = lib_safeCount($pdo, 'books');
$totalLoans      = lib_safeCount($pdo, 'loans');
$totalUsers      = lib_safeCount($pdo, 'users');

// ---------- LOANS TABLE INSPECTION ----------
$loanCols = [];
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM loans');
    $loanCols = array_column($stmt->fetchAll(), 'Field');
} catch (PDOException $e) {
    $loanCols = [];
}

$loanDateCol = lib_findColumn($loanCols, ['loan_date', 'borrow_date', 'issued_date', 'issue_date', 'created_at']);
$dueDateCol  = lib_findColumn($loanCols, ['due_date', 'return_due_date', 'expected_return_date', 'due_on']);
$returnCol   = lib_findColumn($loanCols, ['returned_at', 'return_date', 'returned_date', 'actual_return_date']);
$userIdCol   = lib_findColumn($loanCols, ['user_id', 'borrower_id']);
$bookIdCol   = lib_findColumn($loanCols, ['book_id', 'copy_id']);

// ---------- LOAN STATS ----------
$activeLoans       = 0;
$overdueLoans      = 0;
$dueSoonLoans      = 0;

try {
    if ($returnCol !== null) {
        // Active loans
        $sql = "
            SELECT COUNT(*) AS c
            FROM loans
            WHERE `$returnCol` IS NULL
               OR `$returnCol` = '0000-00-00'
               OR `$returnCol` = '0000-00-00 00:00:00'
        ";
        $row = $pdo->query($sql)->fetch();
        if ($row) $activeLoans = (int)$row['c'];

        // Overdue loans (needs due date)
        if ($dueDateCol !== null) {
            $sql = "
                SELECT COUNT(*) AS c
                FROM loans
                WHERE (`$returnCol` IS NULL
                    OR `$returnCol` = '0000-00-00'
                    OR `$returnCol` = '0000-00-00 00:00:00')
                  AND `$dueDateCol` < CURDATE()
            ";
            $row = $pdo->query($sql)->fetch();
            if ($row) $overdueLoans = (int)$row['c'];

            // Due soon (next 7 days)
            $sql = "
                SELECT COUNT(*) AS c
                FROM loans
                WHERE (`$returnCol` IS NULL
                    OR `$returnCol` = '0000-00-00'
                    OR `$returnCol` = '0000-00-00 00:00:00')
                  AND `$dueDateCol` >= CURDATE()
                  AND `$dueDateCol` <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ";
            $row = $pdo->query($sql)->fetch();
            if ($row) $dueSoonLoans = (int)$row['c'];
        }
    }
} catch (PDOException $e) {
    // keep defaults
}

// ---------- RECENT ACTIVE LOANS ----------
$recentActive = [];
try {
    if ($returnCol !== null) {
        $loanDateExpr = $loanDateCol ? "l.`$loanDateCol`" : "l.id";
        $dueDateExpr  = $dueDateCol ? "l.`$dueDateCol`" : "NULL";

        $sql = "
            SELECT 
                $loanDateExpr AS loan_date,
                $dueDateExpr  AS due_date,
                b.title       AS book_title,
                u.full_name   AS user_name
            FROM loans l
            LEFT JOIN books b ON " . ($bookIdCol ? "b.id = l.`$bookIdCol`" : "1 = 1") . "
            LEFT JOIN users u ON " . ($userIdCol ? "u.id = l.`$userIdCol`" : "1 = 1") . "
            WHERE l.`$returnCol` IS NULL
               OR l.`$returnCol` = '0000-00-00'
               OR l.`$returnCol` = '0000-00-00 00:00:00'
            ORDER BY $loanDateExpr DESC
            LIMIT 6
        ";
        $recentActive = $pdo->query($sql)->fetchAll();
    }
} catch (PDOException $e) {
    $recentActive = [];
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Librarian Dashboard</h1>
            <p class="dashboard-subtitle">
                Quick overview of books, loans, and library activity.
            </p>
        </div>
        <div class="dashboard-welcome">
            <span class="text-muted">Logged in as</span>
            <span class="dashboard-user-name">
                <?php echo htmlspecialchars($user['full_name']); ?>
            </span>
        </div>
    </div>

    <!-- Top summary cards -->
    <section class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-label">Total Books</div>
            <div class="stat-value"><?php echo $totalBooks; ?></div>
            <div class="stat-caption">Books in catalog</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Active Loans</div>
            <div class="stat-value"><?php echo $activeLoans; ?></div>
            <div class="stat-caption">Currently borrowed by users</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Overdue Loans</div>
            <div class="stat-value"><?php echo $overdueLoans; ?></div>
            <div class="stat-caption">Past due, not returned</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Due Soon</div>
            <div class="stat-value"><?php echo $dueSoonLoans; ?></div>
            <div class="stat-caption">Due within next 7 days</div>
        </div>
    </section>

    <!-- Secondary stats -->
    <section class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-label">Total Loans</div>
            <div class="stat-value"><?php echo $totalLoans; ?></div>
            <div class="stat-caption">All-time loans recorded</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Registered Users</div>
            <div class="stat-value"><?php echo $totalUsers; ?></div>
            <div class="stat-caption">Students & staff with accounts</div>
        </div>
    </section>

    <!-- Active loans table -->
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Currently Borrowed Books</h2>
                <p class="card-subtitle">
                    Active loans with borrowers and due dates.
                </p>
            </div>
        </div>

        <?php if (empty($recentActive)): ?>
            <p class="text-muted">There are no active loans at the moment.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Book</th>
                    <th>Borrower</th>
                    <th>Loan Date</th>
                    <th>Due Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentActive as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['book_title'] ?? 'Unknown book'); ?></td>
                        <td><?php echo htmlspecialchars($row['user_name'] ?? 'Unknown user'); ?></td>
                        <td><?php echo htmlspecialchars($row['loan_date'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['due_date'] ?? '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>