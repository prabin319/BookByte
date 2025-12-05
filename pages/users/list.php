<?php
// pages/users/list.php

require_role(['ADMIN']);

$currentUser = get_current_user_data();
$notice = '';
$error  = '';

// Handle activate/deactivate actions
$action = $_GET['action'] ?? null;
$id     = $_GET['id'] ?? null;

if ($action && $id && ctype_digit($id)) {
    $userId = (int) $id;

    // Prevent admin from deactivating themselves (optional safety)
    if ($userId === (int) $currentUser['id']) {
        $error = 'You cannot change your own active status.';
    } else {
        if ($action === 'deactivate' || $action === 'activate') {
            $newStatus = ($action === 'activate') ? 1 : 0;

            try {
                $stmt = $pdo->prepare("UPDATE users SET is_active = :status WHERE id = :id");
                $stmt->execute([
                    'status' => $newStatus,
                    'id'     => $userId,
                ]);

                $notice = ($newStatus === 1)
                    ? 'User has been activated.'
                    : 'User has been deactivated.';
            } catch (PDOException $e) {
                $error = 'Error updating user status: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Load all users
try {
    $stmt = $pdo->query("SELECT id, name, email, role, is_active FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error loading users: ' . htmlspecialchars($e->getMessage());
    $users = [];
}
?>

<h1 class="mb-4">User Management</h1>

<p>
    <a href="index.php?page=user_form" class="btn btn-primary">Add New User</a>
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

<?php if (empty($users)): ?>
    <div class="alert alert-info">
        No users found.
    </div>
<?php else: ?>
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th style="width: 180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $index => $user): ?>
            <tr>
                <td><?php echo $index + 1; ?></td>
                <td><?php echo htmlspecialchars($user['name']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td>
                    <?php if ($user['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="index.php?page=user_form&id=<?php echo $user['id']; ?>"
                       class="btn btn-sm btn-warning">
                        Edit
                    </a>

                    <?php if ($user['id'] != $currentUser['id']): ?>
                        <?php if ($user['is_active']): ?>
                            <a href="index.php?page=users_list&action=deactivate&id=<?php echo $user['id']; ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Deactivate this user?');">
                                Deactivate
                            </a>
                        <?php else: ?>
                            <a href="index.php?page=users_list&action=activate&id=<?php echo $user['id']; ?>"
                               class="btn btn-sm btn-outline-success"
                               onclick="return confirm('Activate this user?');">
                                Activate
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted small">This is you</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
