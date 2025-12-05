<?php
// pages/loans/user_loans.php

require_role(['ADMIN', 'LIBRARIAN']);

$error = '';
$notice = '';
$userId = null;
$selectedUser = null;
$loans = [];
$users = [];

// Load all users for selection list
try {
    $stmtUsers = $pdo->query("
        SELECT id, name, email, role, is_active
        FROM users
        ORDER BY name
    ");
    $users = $stmtUsers->fetchAll();
} catch (PDOException $e) {
    $error = 'Error loading users: ' . htmlspecialchars($e->getMessage());
}

// Check if a user_id is selected
if (isset($_GET['user_id']) && ctype_digit($_GET['user_id'])) {
    $userId = (int) $_GET['user_id'];

    try {
        // Load selected user
        $stmtUser = $pdo->prepare("
            SELECT id, name, email, role, is_active
            FROM users
            WHERE id = :id
        ");
        $stmtUser->execute(['id' => $userId]);
        $selectedUser = $stmtUser->fetch();

        if ($selectedUser) {
            // Load that user's loans
            $stmtLoans = $pdo->prepare("
                SELECT l.*, b.title, b.isbn
                FROM loans l
                JOIN books b ON l.book_id = b.id
                WHERE l.user_id = :uid
                ORDER BY l.borrowed_date DESC
            ");
            $stmtLoans->execute(['uid' => $userId]);
            $loans = $stmtLoans->fetchAll();
        } else {
            $error = 'Selected user not found.';
        }
    } catch (PDOException $e) {
        $error = 'Error loading user loans: ' . htmlspecialchars($e->getMessage());
    }
}
?>

<h1 class="mb-4">Loans by User</h1>

<p>
    <a href="index.php?page=loans_active" class="btn btn-outline-primary btn-sm me-2">Active Loans Report</a>
    <a href="index.php?page=dashboard_admin" class="btn btn-outline-secondary btn-sm me-2">Admin Dashboard</a>
    <a href="index.php?page=dashboard_librarian" class="btn btn-outline-secondary btn-sm">Librarian Dashboard</a>
</p>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<h2 class="h5 mt-4">Select User</h2>

<?php if (empty($users)): ?>
    <div class="alert alert-info">
        No users found.
    </div>
<?php else: ?>
    <form method="get" action="index.php" class="row g-3 mb-4">
        <input type="hidden" name="page" value="loans_user">
        <div class="col-md-6">
            <label for="user_id" class="form-label">User</label>
            <select id="user_id" name="user_id" class="form-select" required>
                <option value="">-- Choose a user --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>"
                        <?php echo ($userId === (int)$user['id']) ? 'selected' : ''; ?>>
                        <?php
                            echo htmlspecialchars($user['name'])
                                 . ' (' . htmlspecialchars($user['role']) . ') - '
                                 . htmlspecialchars($user['email']);
                            if (!$user['is_active']) {
                                echo ' [INACTIVE]';
                            }
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary">View Loans</button>
        </div>
    </form>
<?php endif; ?>

<?php if ($selectedUser): ?>
    <h2 class="h5 mb-3">Loan History for: 
        <?php echo htmlspecialchars($selectedUser['name']); ?>
        (<?php echo htmlspecialchars($selectedUser['role']); ?>)
    </h2>

    <?php if (empty($loans)): ?>
        <div class="alert alert-info">
            This user has no loans.
        </div>
    <?php else: ?>
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Book Title</th>
                    <th>ISBN</th>
                    <th>Borrowed Date</th>
                    <th>Returned Date / Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($loans as $index => $loan): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($loan['title']); ?></td>
                    <td><?php echo htmlspecialchars($loan['isbn']); ?></td>
                    <td><?php echo htmlspecialchars($loan['borrowed_date']); ?></td>
                    <td>
                        <?php if ($loan['returned_date'] === null): ?>
                            <span class="badge bg-warning text-dark">Active</span>
                        <?php else: ?>
                            Returned at: <?php echo htmlspecialchars($loan['returned_date']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>
