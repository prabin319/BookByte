<?php
// pages/loans/my_loans.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin();

// Check if user is student
$user = currentUser();
if (!$user || $user['role'] !== 'STUDENT') {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();
$userId = $user['id'];

// Detect loan table columns
$loanCols = [];
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM loans');
    $loanCols = array_column($stmt->fetchAll(), 'Field');
} catch (PDOException $e) {
    $loanCols = [];
}

// Find column names
function findColumn($available, $candidates) {
    foreach ($candidates as $name) {
        if (in_array($name, $available, true)) {
            return $name;
        }
    }
    return null;
}

$loanDateCol = findColumn($loanCols, ['loan_date', 'borrow_date', 'issued_date', 'created_at']);
$dueDateCol  = findColumn($loanCols, ['due_date', 'return_due_date', 'expected_return_date']);
$returnCol   = findColumn($loanCols, ['returned_at', 'return_date', 'returned_date']);
$userIdCol   = findColumn($loanCols, ['user_id', 'borrower_id']);
$bookIdCol   = findColumn($loanCols, ['book_id']);

// Load user's loans
$loans = [];
if ($userIdCol !== null) {
    try {
        $loanDateExpr = $loanDateCol ? "l.`$loanDateCol`" : "NULL";
        $dueDateExpr  = $dueDateCol ? "l.`$dueDateCol`" : "NULL";
        $returnExpr   = $returnCol ? "l.`$returnCol`" : "NULL";
        
        $sql = "
            SELECT 
                l.id,
                $loanDateExpr AS loan_date,
                $dueDateExpr AS due_date,
                $returnExpr AS returned_at,
                b.title AS book_title,
                b.author AS book_author,
                b.isbn AS book_isbn
            FROM loans l
            LEFT JOIN books b ON " . ($bookIdCol ? "b.id = l.`$bookIdCol`" : "1=1") . "
            WHERE l.`$userIdCol` = :uid
            ORDER BY $loanDateExpr DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        $loans = $stmt->fetchAll();
    } catch (PDOException $e) {
        $loans = [];
    }
}

// Calculate stats
$activeCount = 0;
$overdueCount = 0;

foreach ($loans as $loan) {
    $returned = $loan['returned_at'] ?? null;
    $isActive = ($returned === null || $returned === '0000-00-00' || $returned === '0000-00-00 00:00:00');
    
    if ($isActive) {
        $activeCount++;
        
        $dueDate = $loan['due_date'] ?? null;
        if ($dueDate && strtotime($dueDate) < strtotime('today')) {
            $overdueCount++;
        }
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">My Loans</h1>
            <p class="dashboard-subtitle">
                View and manage your borrowed books.
            </p>
        </div>
    </div>

    <!-- Stats Cards -->
    <section class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-label">Active Loans</div>
            <div class="stat-value"><?php echo $activeCount; ?></div>
            <div class="stat-caption">Books currently borrowed</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Overdue Loans</div>
            <div class="stat-value"><?php echo $overdueCount; ?></div>
            <div class="stat-caption">Books past due date</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Loans</div>
            <div class="stat-value"><?php echo count($loans); ?></div>
            <div class="stat-caption">All-time loan history</div>
        </div>
    </section>

    <!-- Loans Table -->
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Loan History</h2>
                <p class="card-subtitle">
                    All books you have borrowed.
                </p>
            </div>
        </div>

        <?php if (empty($loans)): ?>
            <p class="text-muted">You haven't borrowed any books yet. Visit Browse Books to borrow your first book!</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Loan Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                        $bookTitle = $loan['book_title'] ?? 'Unknown Book';
                        $bookAuthor = $loan['book_author'] ?? 'Unknown Author';
                        $loanDate = $loan['loan_date'] ?? '—';
                        $dueDate = $loan['due_date'] ?? '—';
                        $returned = $loan['returned_at'] ?? null;
                        
                        // Determine status
                        $status = 'Returned';
                        $statusClass = 'badge-blue';
                        
                        if ($returned === null || $returned === '0000-00-00' || $returned === '0000-00-00 00:00:00') {
                            // Check if overdue
                            if ($dueDate !== '—' && strtotime($dueDate) < strtotime('today')) {
                                $status = 'Overdue';
                                $statusClass = 'badge-red';
                            } else {
                                $status = 'Active';
                                $statusClass = 'badge-green';
                            }
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($bookTitle); ?></strong></td>
                            <td><?php echo htmlspecialchars($bookAuthor); ?></td>
                            <td><?php echo htmlspecialchars($loanDate); ?></td>
                            <td><?php echo htmlspecialchars($dueDate); ?></td>
                            <td>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php if ($overdueCount > 0): ?>
    <!-- Overdue Warning -->
    <section class="card" style="border-left: 4px solid #b91c1c;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 24px;">⚠️</span>
            <div>
                <h3 style="margin: 0; color: #b91c1c; font-size: 16px;">Overdue Books</h3>
                <p style="margin: 4px 0 0; font-size: 14px; color: #6b7280;">
                    You have <?php echo $overdueCount; ?> overdue book(s). Please return them as soon as possible to avoid late fees.
                </p>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>