<?php
// pages/users/form.php

require_role(['ADMIN']);

$editing    = false;
$userId     = null;
$name       = '';
$email      = '';
$role       = 'STUDENT';
$isActive   = 1;
$error      = '';

// Check if we are editing an existing user
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $editing = true;
    $userId  = (int) $_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $existingUser = $stmt->fetch();

        if (!$existingUser) {
            $error = 'User not found.';
        } else {
            $name     = $existingUser['name'];
            $email    = $existingUser['email'];
            $role     = $existingUser['role'];
            $isActive = (int) $existingUser['is_active'];
        }
    } catch (PDOException $e) {
        $error = 'Error loading user: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'STUDENT';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $role === '') {
        $error = 'Name, email, and role are required.';
    } elseif (!$editing && $password === '') {
        $error = 'Password is required for new users.';
    } else {
        try {
            if ($editing) {
                // Update existing user
                if ($password !== '') {
                    // Update with new password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET name = :name,
                            email = :email,
                            role = :role,
                            is_active = :is_active,
                            password = :password
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'name'     => $name,
                        'email'    => $email,
                        'role'     => $role,
                        'is_active'=> $isActive,
                        'password' => $hashedPassword,
                        'id'       => $userId,
                    ]);
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET name = :name,
                            email = :email,
                            role = :role,
                            is_active = :is_active
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'name'     => $name,
                        'email'    => $email,
                        'role'     => $role,
                        'is_active'=> $isActive,
                        'id'       => $userId,
                    ]);
                }
            } else {
                // Insert new user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, is_active)
                    VALUES (:name, :email, :password, :role, :is_active)
                ");
                $stmt->execute([
                    'name'     => $name,
                    'email'    => $email,
                    'password' => $hashedPassword,
                    'role'     => $role,
                    'is_active'=> $isActive,
                ]);
            }

            // After success, go back to user list
            header("Location: index.php?page=users_list");
            exit;

        } catch (PDOException $e) {
            // Likely duplicate email, etc.
            $error = 'Error saving user: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<h1 class="mb-4">
    <?php echo $editing ? 'Edit User' : 'Add New User'; ?>
</h1>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<form method="post" action="index.php?page=user_form<?php echo $editing ? '&id=' . $userId : ''; ?>" class="col-md-6 col-lg-5">
    <div class="mb-3">
        <label for="name" class="form-label">Name</label>
        <input
            type="text"
            class="form-control"
            id="name"
            name="name"
            required
            value="<?php echo htmlspecialchars($name); ?>"
        >
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input
            type="email"
            class="form-control"
            id="email"
            name="email"
            required
            value="<?php echo htmlspecialchars($email); ?>"
        >
    </div>

    <div class="mb-3">
        <label for="role" class="form-label">Role</label>
        <select class="form-select" id="role" name="role" required>
            <option value="ADMIN" <?php echo ($role === 'ADMIN') ? 'selected' : ''; ?>>Admin</option>
            <option value="LIBRARIAN" <?php echo ($role === 'LIBRARIAN') ? 'selected' : ''; ?>>Librarian</option>
            <option value="STUDENT" <?php echo ($role === 'STUDENT') ? 'selected' : ''; ?>>Student</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">
            Password
            <?php if ($editing): ?>
                <small class="text-muted">(leave blank to keep current password)</small>
            <?php endif; ?>
        </label>
        <input
            type="password"
            class="form-control"
            id="password"
            name="password"
            <?php echo $editing ? '' : 'required'; ?>
        >
    </div>

    <div class="form-check mb-3">
        <input
            class="form-check-input"
            type="checkbox"
            value="1"
            id="is_active"
            name="is_active"
            <?php echo $isActive ? 'checked' : ''; ?>
        >
        <label class="form-check-label" for="is_active">
            Active
        </label>
    </div>

    <button type="submit" class="btn btn-success">
        <?php echo $editing ? 'Update User' : 'Create User'; ?>
    </button>
    <a href="index.php?page=users_list" class="btn btn-secondary">Cancel</a>
</form>
