<?php
// pages/loans/my_loans.php

require_role(['STUDENT']);

$currentUser = get_current_user_data();
$notice = '';
$error  = '';

// Handle return action
$action = $_GET['action'] ?? null;
$id     = $_GET['id'] ?? null;

if ($action === 'return' && $id && ctype_digit($id)) {
    $loanId = (int) $id;

    try {
        // 1. Load the loan and make sure it belongs to this student and is not returned yet
        $stmt = $pdo->prepare("
            SELECT * FROM loans
            WHERE id = :id AND user_id = :uid AND returned_date IS NULL
        ");
        $stmt->execute([
            'id'  => $loanId,
            'uid' => $currentUser['id'],
        ]);
        $loan = $stmt->fetch();

        if (!$loan) {
            $error = 'Loan not found or already returned.';
        } else {
            // 2. Set returned_date
            $updateLoan = $pdo->prepare("
                UPDATE loans
                SET returned_date = NOW()
                WHERE id = :id
            ");
            $updateLoan->execute(['id' => $loanId]);

            // 3. Set book status back to AVAILABLE
            $updateBook = $pdo->prepare("
                UPDATE books
                SET status = 'AVAILABLE'
                WHERE id = :book_id
            ");
            $updateBook->execute(['book_id' => $loan['book_id']]);

            $notice = 'Book has been returned successfully.';
        }
    } catch (PDOException $e) {
        $error = 'Error returning book: ' . htmlspecialchars($e->getMessage());
    }
}

// Load all loans for this student
try {
    $stmt = $pdo->prepare("
        SELECT l.*, b.title
        FROM loans l
        JOIN books b ON l.book_id = b.id
        WHERE l.user_id = :uid
        ORDER BY l.borrowed_date DESC
    ");
    $stmt->execute(['uid' => $currentUser['id']]);
    $loans = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error loading loans: ' . htmlspecialchars($e->getMessage());
    $loans = [];
}

// Count active loans (for simple notification)
$activeLoans = 0;
foreach ($loans as $loan) {
    if ($loan['returned_date'] === null) {
        $activeLoans++;
    }
}
?>

<h1 class="mb-4">My Loans</h1>

<p class="mb-2">
    Logged in as: <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong>
</p>

<div class="alert alert-info">
    You currently have <strong><?php echo $activeLoans; ?></strong> active loan(s).
</div>

<p>
    <a href="index.php?page=student_books" class="btn btn-outline-primary">Browse Books</a>
</p>

<?php if ($notice): ?>
    <div class="alert alert-success">
        <?php echo $notice; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (empty($loans)): ?>
    <div class="alert alert-info">
        You have no loan history.
    </div>
<?php else: ?>
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Book Title</th>
                <th>Borrowed Date</th>
                <th>Returned Date / Status</th>
                <th style="width: 160px;">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($loans as $index => $loan): ?>
            <tr>
                <td><?php echo $index + 1; ?></td>
                <td><?php echo htmlspecialchars($loan['title']); ?></td>
                <td><?php echo htmlspecialchars($loan['borrowed_date']); ?></td>
                <td>
                    <?php if ($loan['returned_date'] === null): ?>
                        <span class="badge bg-warning text-dark">Active</span>
                    <?php else: ?>
                        Returned at: <?php echo htmlspecialchars($loan['returned_date']); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($loan['returned_date'] === null): ?>
                        <a href="index.php?page=my_loans&action=return&id=<?php echo $loan['id']; ?>"
                           class="btn btn-sm btn-success"
                           onclick="return confirm('Return this book?');">
                            Return
                        </a>
                    <?php else: ?>
                        <span class="text-muted">No action</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
