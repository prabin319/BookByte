<?php
// pages/users/users_manage.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin('ADMIN');

$user = currentUser();

$search    = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterRole = isset($_GET['role']) ? strtoupper(trim($_GET['role'])) : '';

$rows = [];
try {
    $pdo  = getDBConnection();
    $stmt = $pdo->query('SELECT * FROM users');
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    $rows = [];
}

// helper functions adapt to your existing users table
function getUserId(array $u)
{
    return $u['id'] ?? null;
}

function getUserNameFromRow(array $u)
{
    if (!empty($u['full_name'])) return $u['full_name'];
    if (!empty($u['name'])) return $u['name'];

    $parts = [];
    if (!empty($u['first_name'])) $parts[] = $u['first_name'];
    if (!empty($u['last_name']))  $parts[] = $u['last_name'];
    if (!empty($parts)) return implode(' ', $parts);

    return $u['email'] ?? 'Unknown';
}

function getUserEmailFromRow(array $u)
{
    if (!empty($u['email'])) return $u['email'];
    if (!empty($u['username'])) return $u['username'];
    return '';
}

function getUserRoleFromRow(array $u)
{
    if (!empty($u['role'])) return strtoupper($u['role']);
    if (!empty($u['user_role'])) return strtoupper($u['user_role']);
    if (!empty($u['type'])) return strtoupper($u['type']);
    return 'STUDENT';
}

function getUserStatusFromRow(array $u)
{
    if (isset($u['is_active'])) {
        return $u['is_active'] ? 'ACTIVE' : 'INACTIVE';
    }
    if (isset($u['active'])) {
        return $u['active'] ? 'ACTIVE' : 'INACTIVE';
    }
    if (!empty($u['status'])) {
        return strtoupper($u['status']);
    }
    return 'ACTIVE';
}

// filtering
$users = array_filter($rows, function ($u) use ($search, $filterRole) {
    $name  = mb_strtolower(getUserNameFromRow($u));
    $email = mb_strtolower(getUserEmailFromRow($u));
    $role  = getUserRoleFromRow($u);

    if ($search !== '') {
        $s = mb_strtolower($search);
        if (mb_strpos($name, $s) === false &&
            mb_strpos($email, $s) === false) {
            return false;
        }
    }

    if ($filterRole !== '' && $role !== $filterRole) {
        return false;
    }

    return true;
});

// build list of roles present
$roleOptions = [];
foreach ($rows as $u) {
    $r = getUserRoleFromRow($u);
    if (!in_array($r, $roleOptions, true)) {
        $roleOptions[] = $r;
    }
}
sort($roleOptions);
?>
<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Manage Users</h1>
            <p class="dashboard-subtitle">
                View and manage all user accounts for BookByte LMS.
            </p>
        </div>
        <div class="dashboard-welcome">
            <a href="index.php?page=user_form" class="btn btn-primary">
                + Add New User
            </a>
        </div>
    </div>

    <div class="page-toolbar card">
        <form method="get" action="index.php" class="page-toolbar-form">
            <input type="hidden" name="page" value="users_manage">
            <div class="page-toolbar-row">
                <div class="page-toolbar-left">
                    <div class="search-box">
                        <input
                            type="text"
                            name="q"
                            class="search-input"
                            placeholder="Search by name or email..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Role</label>
                        <select name="role" class="filter-select">
                            <option value="">All roles</option>
                            <?php foreach ($roleOptions as $roleOption): ?>
                                <option value="<?php echo htmlspecialchars($roleOption); ?>"
                                    <?php echo ($filterRole === $roleOption) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($roleOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="page-toolbar-right">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="index.php?page=users_manage" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">User Accounts</h2>
                <p class="card-subtitle">
                    <?php echo count($users); ?> user(s) found.
                </p>
            </div>
        </div>

        <?php if (empty($users)): ?>
            <p class="text-muted">No users match the current filters.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $row): ?>
                    <?php
                    $id     = getUserId($row);
                    $name   = getUserNameFromRow($row);
                    $email  = getUserEmailFromRow($row);
                    $role   = getUserRoleFromRow($row);
                    $status = getUserStatusFromRow($row);

                    $roleClass = 'badge-blue';
                    if ($role === 'ADMIN') $roleClass = 'badge-red';
                    if ($role === 'LIBRARIAN') $roleClass = 'badge-green';

                    $statusClass = ($status === 'ACTIVE') ? 'badge-green' : 'badge-red';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($name); ?></td>
                        <td><?php echo htmlspecialchars($email); ?></td>
                        <td>
                            <span class="badge <?php echo $roleClass; ?>">
                                <?php echo htmlspecialchars($role); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <div class="table-actions">
                                <?php if ($id !== null): ?>
                                    <a href="index.php?page=user_form&id=<?php echo (int)$id; ?>"
                                       class="btn-icon" title="Edit">
                                        ✏️
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
