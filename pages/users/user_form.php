<?php
// pages/users/user_form.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin('ADMIN');

$currentAdmin = currentUser();

$pdo = getDBConnection();

// Read table structure so we don't guess column names blindly
$columns = [];
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM users');
    $columns = array_column($stmt->fetchAll(), 'Field');
} catch (PDOException $e) {
    $columns = [];
}

// Detect columns
$nameColumn  = null;
$firstColumn = null;
$lastColumn  = null;
$emailColumn = in_array('email', $columns, true) ? 'email' : null;
$roleColumn  = null;
$statusColumn = null;
$passwordColumn = null;

if (in_array('full_name', $columns, true))  $nameColumn = 'full_name';
elseif (in_array('name', $columns, true))   $nameColumn = 'name';

if (in_array('first_name', $columns, true)) $firstColumn = 'first_name';
if (in_array('last_name', $columns, true))  $lastColumn  = 'last_name';

foreach (['role', 'user_role', 'type'] as $col) {
    if (in_array($col, $columns, true)) {
        $roleColumn = $col;
        break;
    }
}

foreach (['status', 'is_active', 'active'] as $col) {
    if (in_array($col, $columns, true)) {
        $statusColumn = $col;
        break;
    }
}

foreach (['password_hash', 'password', 'pwd_hash'] as $col) {
    if (in_array($col, $columns, true)) {
        $passwordColumn = $col;
        break;
    }
}

$allowedRoles = ['ADMIN', 'LIBRARIAN', 'STUDENT'];

// determine if edit or new
$userId  = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit  = $userId !== null && $userId > 0;
$loadError = '';
$saveError = '';

$formValues = [
    'full_name' => '',
    'email'     => '',
    'role'      => 'STUDENT',
    'status'    => 'ACTIVE',
];

// load existing user if editing
if ($isEdit) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if ($row) {
            // name
            if ($nameColumn && !empty($row[$nameColumn])) {
                $formValues['full_name'] = $row[$nameColumn];
            } else {
                $parts = [];
                if ($firstColumn && !empty($row[$firstColumn])) $parts[] = $row[$firstColumn];
                if ($lastColumn  && !empty($row[$lastColumn]))  $parts[] = $row[$lastColumn];
                if (!empty($parts)) {
                    $formValues['full_name'] = implode(' ', $parts);
                }
            }

            if ($emailColumn && !empty($row[$emailColumn])) {
                $formValues['email'] = $row[$emailColumn];
            }

            // role
            if ($roleColumn && !empty($row[$roleColumn])) {
                $formValues['role'] = strtoupper($row[$roleColumn]);
            } else {
                $formValues['role'] = 'STUDENT';
            }

            // status
            if ($statusColumn) {
                $val = $row[$statusColumn];
                if ($statusColumn === 'status') {
                    $formValues['status'] = strtoupper((string)$val);
                } else { // is_active / active boolean-ish
                    $formValues['status'] = $val ? 'ACTIVE' : 'INACTIVE';
                }
            }
        } else {
            $loadError = 'User not found.';
            $isEdit = false;
            $userId = null;
        }
    } catch (PDOException $e) {
        $loadError = 'Failed to load user.';
        $isEdit = false;
        $userId = null;
    }
}

// handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['full_name'] = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $formValues['email']     = isset($_POST['email']) ? trim($_POST['email']) : '';
    $formValues['role']      = isset($_POST['role']) ? strtoupper(trim($_POST['role'])) : 'STUDENT';
    $formValues['status']    = isset($_POST['status']) ? strtoupper(trim($_POST['status'])) : 'ACTIVE';
    $passwordPlain           = isset($_POST['password']) ? (string)$_POST['password'] : '';

    // basic validation
    if ($formValues['full_name'] === '' || $formValues['email'] === '') {
        $saveError = 'Please provide both full name and email.';
    } elseif (!in_array($formValues['role'], $allowedRoles, true)) {
        $saveError = 'Invalid role selected.';
    } elseif (!$isEdit && $passwordPlain === '') {
        $saveError = 'Please set a password for the new user.';
    } else {
        try {
            $data = [];

            // name mapping
            if ($nameColumn) {
                $data[$nameColumn] = $formValues['full_name'];
            } else {
                if ($firstColumn) $data[$firstColumn] = $formValues['full_name'];
                if ($lastColumn)  $data[$lastColumn]  = '';
            }

            if ($emailColumn) {
                $data[$emailColumn] = $formValues['email'];
            }

            if ($roleColumn) {
                $data[$roleColumn] = $formValues['role'];
            }

            if ($statusColumn) {
                if ($statusColumn === 'status') {
                    $data[$statusColumn] = $formValues['status'];
                } else {
                    $data[$statusColumn] = ($formValues['status'] === 'ACTIVE') ? 1 : 0;
                }
            }

            if ($passwordColumn && $passwordPlain !== '') {
                $data[$passwordColumn] = password_hash($passwordPlain, PASSWORD_DEFAULT);
            }

            if (empty($data)) {
                $saveError = 'Cannot save user: no matching columns found in users table.';
            } else {
                if ($isEdit) {
                    // UPDATE
                    $sets = [];
                    foreach ($data as $col => $_) {
                        $sets[] = "`$col` = :$col";
                    }
                    $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    foreach ($data as $col => $val) {
                        $stmt->bindValue(':' . $col, $val);
                    }
                    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    // INSERT
                    $cols = array_keys($data);
                    $placeholders = array_map(function ($c) { return ':' . $c; }, $cols);
                    $sql = "INSERT INTO users (" . implode(', ', $cols) . ")
                            VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    foreach ($data as $col => $val) {
                        $stmt->bindValue(':' . $col, $val);
                    }
                    $stmt->execute();
                    $userId = (int)$pdo->lastInsertId();
                    $isEdit = true;
                }

                // redirect back to manage users
                header('Location: index.php?page=users_manage');
                exit;
            }
        } catch (PDOException $e) {
            $saveError = 'Failed to save user (database error).';
        }
    }
}
?>
<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">
                <?php echo $isEdit ? 'Edit User' : 'Add New User'; ?>
            </h1>
            <p class="dashboard-subtitle">
                Manage user accounts and roles for BookByte LMS.
            </p>
        </div>
    </div>

    <div class="card">
        <?php if ($loadError): ?>
            <div class="auth-alert auth-alert-error" style="margin-bottom:10px;">
                <?php echo htmlspecialchars($loadError); ?>
            </div>
        <?php endif; ?>

        <?php if ($saveError): ?>
            <div class="auth-alert auth-alert-error" style="margin-bottom:10px;">
                <?php echo htmlspecialchars($saveError); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    class="form-control"
                    required
                    value="<?php echo htmlspecialchars($formValues['full_name']); ?>"
                >
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    required
                    value="<?php echo htmlspecialchars($formValues['email']); ?>"
                >
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" class="form-control">
                    <option value="ADMIN"     <?php echo $formValues['role'] === 'ADMIN' ? 'selected' : ''; ?>>ADMIN</option>
                    <option value="LIBRARIAN" <?php echo $formValues['role'] === 'LIBRARIAN' ? 'selected' : ''; ?>>LIBRARIAN</option>
                    <option value="STUDENT"   <?php echo $formValues['role'] === 'STUDENT' ? 'selected' : ''; ?>>STUDENT</option>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="ACTIVE"   <?php echo $formValues['status'] === 'ACTIVE' ? 'selected' : ''; ?>>ACTIVE</option>
                    <option value="INACTIVE" <?php echo $formValues['status'] === 'INACTIVE' ? 'selected' : ''; ?>>INACTIVE</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">
                    Password <?php echo $isEdit ? '(leave blank to keep current)' : ''; ?>
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    <?php echo $isEdit ? '' : 'required'; ?>
                >
            </div>

            <div class="auth-form-footer" style="margin-top:14px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">
                    <?php echo $isEdit ? 'Save Changes' : 'Create User'; ?>
                </button>
                <a href="index.php?page=users_manage" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
