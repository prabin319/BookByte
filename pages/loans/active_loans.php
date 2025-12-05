<?php
// pages/loans/active_loans.php
// Borrow & Return – main admin/librarian view

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

// ADMIN + LIBRARIAN can access
$user = currentUser();
$role = $user['role'] ?? '';
if (!in_array($role, ['ADMIN', 'LIBRARIAN'], true)) {
    requireLogin(); // will redirect if not logged in
}

$pdo = getDBConnection();

$search      = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterState = isset($_GET['state']) ? trim($_GET['state']) : ''; // active / overdue / returned

// Handle "mark as returned"
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'return') {
    $loanId = (int)$_GET['id'];
    if ($loanId > 0) {
        try {
            $sql = "UPDATE loans 
                    SET returned_at = NOW() 
                    WHERE id = :id 
                      AND (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $loanId]);
        } catch (PDOException $e) {
            // We silently ignore errors here; page will still render list
        }
        header('Location: index.php?page=loans_active');
        exit;
    }
}

// Load all loans with joined user + book
$loans = [];
try {
    $sql = "
        SELECT 
            l.*,
            u.full_name AS user_name,
            u.email AS user_email,
            b.title AS book_title
        FROM loans l
        LEFT JOIN users u ON u.id = l.user_id
        LEFT JOIN books b ON b.id = l.book_id
        ORDER BY l.loan_date DESC, l.id DESC
        ";
    $stmt = $pdo->query($sql);
    $loans = $stmt->fetchAll();
} catch (PDOException $e) {
    $loans = [];
}

// Helper to determine status
function getLoanStatus(array $loan): array
{
    $returnedAt = $loan['returned_at'] ?? null;
    $dueDate    = $loan['due_date'] ?? null;

    $now = strtotime('today');

    if (!empty($returnedAt) && $returnedAt !== '0000-00-00 00:00:00') {
        return ['Returned', 'badge-blue'];
    }

    if (!empty($dueDate) && strtotime($dueDate) < $now) {
        return ['Overdue', 'badge-red'];
    }

    return ['Active', 'badge-green'];
}

// Apply filters in PHP
$filtered = array_filter($loans, function ($loan) use ($search, $filterState) {
    [$statusLabel, ] = getLoanStatus($loan);

    if ($filterState === 'active' && $statusLabel !== 'Active') {
        return false;
    }
    if ($filterState === 'overdue' && $statusLabel !== 'Overdue') {
        return false;
    }
    if ($filterState === 'returned' && $statusLabel !== 'Returned') {
        return false;
    }

    if ($search !== '') {
        $s = mb_strtolower($search);

        $userName  = mb_strtolower($loan['user_name']  ?? '');
        $userEmail = mb_strtolower($loan['user_email'] ?? '');
        $bookTitle = mb_strtolower($loan['book_title'] ?? '');

        if (
            mb_strpos($userName, $s) === false &&
            mb_strpos($userEmail, $s) === false &&
            mb_strpos($bookTitle, $s) === false
        ) {
            return false;
        }
    }

    return true;
});
?>
<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Borrow &amp; Return</h1>
            <p class="dashboard-subtitle">
                View active loans, overdue items, and recently returned books.
            </p>
        </div>
        <div class="dashboard-welcome">
            <!-- You can later add a dedicated "New Loan" page and link it here -->
            <span class="text-muted">Loans managed by</span>
            <span class="dashboard-user-name">
                <?php echo htmlspecialchars($user['full_name']); ?>
            </span>
        </div>
    </div>

    <!-- Toolbar: search + status filter -->
    <div class="page-toolbar card">
        <form method="get" action="index.php" class="page-toolbar-form">
            <input type="hidden" name="page" value="loans_active">
            <div class="page-toolbar-row">
                <div class="page-toolbar-left">
                    <div class="search-box">
                        <input
                            type="text"
                            name="q"
                            class="search-input"
                            placeholder="Search by user or book..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="state" class="filter-select">
                            <option value="">All</option>
                            <option value="active"   <?php echo $filterState === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="overdue"  <?php echo $filterState === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="returned" <?php echo $filterState === 'returned' ? 'selected' : ''; ?>>Returned</option>
                        </select>
                    </div>
                </div>

                <div class="page-toolbar-right">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="index.php?page=loans_active" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Loans table -->
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Loan Records</h2>
                <p class="card-subtitle">
                    <?php echo count($filtered); ?> loan(s) found.
                </p>
            </div>
        </div>

        <?php if (empty($filtered)): ?>
            <p class="text-muted">No loans match the current filters.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Book</th>
                    <th>User</th>
                    <th>Loan Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($filtered as $loan): ?>
                    <?php
                    $loanId = $loan['id'] ?? null;
                    $bookTitle = $loan['book_title'] ?? 'Unknown book';
                    $userName  = $loan['user_name']  ?? ($loan['user_email'] ?? 'Unknown user');
                    $loanDate  = !empty($loan['loan_date']) ? date('Y-m-d', strtotime($loan['loan_date'])) : '—';
                    $dueDate   = !empty($loan['due_date']) ? date('Y-m-d', strtotime($loan['due_date'])) : '—';
                    [$statusLabel, $statusClass] = getLoanStatus($loan);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bookTitle); ?></td>
                        <td><?php echo htmlspecialchars($userName); ?></td>
                        <td><?php echo htmlspecialchars($loanDate); ?></td>
                        <td><?php echo htmlspecialchars($dueDate); ?></td>
                        <td>
                            <span class="badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <div class="table-actions">
                                <?php if ($loanId !== null && $statusLabel !== 'Returned'): ?>
                                    <a href="index.php?page=loans_active&action=return&id=<?php echo (int)$loanId; ?>"
                                       class="btn-icon"
                                       title="Mark as returned"
                                       onclick="return confirm('Mark this loan as returned?');">
                                        🔁
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">No actions</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
