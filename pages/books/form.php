<?php
// C:\xampp\htdocs\bookbyte\pages\books\form.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

// Only ADMIN and LIBRARIAN can use this form
$user = currentUser();
$role = $user['role'] ?? '';
if (!in_array($role, ['ADMIN', 'LIBRARIAN'], true)) {
    requireLogin(); // redirects if not logged in
}

/*
 * CONFIG: adjust these if your column names are DIFFERENT.
 * By default we expect a table "books" with at least:
 *  id (PK), title, author, category, isbn, available_copies
 */
$tableName     = 'books';
$primaryKey    = 'id';
$fieldMapping  = [
    'title'            => 'title',
    'author'           => 'author',
    'category'         => 'category',
    'isbn'             => 'isbn',
    'available_copies' => 'available_copies', // change to "copies" / "quantity" etc if needed
];

$pdo          = getDBConnection();
$existingCols = [];

// Load existing column names safely
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
    $existingCols = array_column($colsStmt->fetchAll(), 'Field');
} catch (PDOException $e) {
    // If this fails we still show the form but saving will show an error message
}

// Keep only fields that actually exist in the DB
$usableFields = [];
foreach ($fieldMapping as $logical => $column) {
    if (in_array($column, $existingCols, true)) {
        $usableFields[$logical] = $column;
    }
}

// Determine whether we are editing or creating
$bookId       = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit       = $bookId !== null && $bookId > 0;
$loadError    = '';
$saveError    = '';
$saveSuccess  = false;

// Default values
$formValues = [
    'title'            => '',
    'author'           => '',
    'category'         => '',
    'isbn'             => '',
    'available_copies' => '',
];

// Load existing book for edit
if ($isEdit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `{$tableName}` WHERE `{$primaryKey}` = :id LIMIT 1");
        $stmt->execute([':id' => $bookId]);
        $row = $stmt->fetch();

        if ($row) {
            foreach ($fieldMapping as $logical => $column) {
                if (isset($row[$column])) {
                    $formValues[$logical] = $row[$column];
                }
            }
        } else {
            $loadError = 'The selected book could not be found.';
            $isEdit    = false;
            $bookId    = null;
        }
    } catch (PDOException $e) {
        $loadError = 'Could not load book data from the database.';
        $isEdit    = false;
        $bookId    = null;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get submitted values
    foreach ($formValues as $logical => $_) {
        $formValues[$logical] = isset($_POST[$logical]) ? trim($_POST[$logical]) : '';
    }

    // Basic validation
    if ($formValues['title'] === '' || $formValues['author'] === '') {
        $saveError = 'Please fill in at least Title and Author.';
    } else {
        // Build INSERT or UPDATE dynamically using only usable fields
        try {
            if (empty($usableFields)) {
                $saveError = 'Cannot save: no matching columns were found in the "books" table. Please adjust field mapping in form.php.';
            } else {
                // Build data array for query
                $data = [];
                foreach ($usableFields as $logical => $column) {
                    $data[$column] = $formValues[$logical];
                }

                if ($isEdit) {
                    // UPDATE
                    $setParts = [];
                    foreach ($data as $column => $_) {
                        $setParts[] = "`{$column}` = :{$column}";
                    }
                    $sql = "UPDATE `{$tableName}` SET " . implode(', ', $setParts) . " WHERE `{$primaryKey}` = :pk LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    foreach ($data as $column => $value) {
                        $stmt->bindValue(':' . $column, $value);
                    }
                    $stmt->bindValue(':pk', $bookId, PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    // INSERT
                    $columns = array_keys($data);
                    $placeholders = array_map(function ($c) { return ':' . $c; }, $columns);

                    $sql = "INSERT INTO `{$tableName}` (" . implode(', ', $columns) . ")
                            VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    foreach ($data as $column => $value) {
                        $stmt->bindValue(':' . $column, $value);
                    }
                    $stmt->execute();
                    $bookId = (int)$pdo->lastInsertId();
                    $isEdit = true;
                }

                $saveSuccess = true;

                // Redirect back to Manage Books (prevents form resubmit)
                header('Location: index.php?page=books_manage');
                exit;
            }
        } catch (PDOException $e) {
            $saveError = 'Could not save book (database error).';
        }
    }
}
?>
<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">
                <?php echo $isEdit ? 'Edit Book' : 'Add New Book'; ?>
            </h1>
            <p class="dashboard-subtitle">
                Fill in the details of the book to keep the catalog up to date.
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
                <label for="title">Title<span style="color:#b91c1c;"> *</span></label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    class="form-control"
                    required
                    value="<?php echo htmlspecialchars($formValues['title']); ?>"
                >
            </div>

            <div class="form-group">
                <label for="author">Author(s)<span style="color:#b91c1c;"> *</span></label>
                <input
                    type="text"
                    id="author"
                    name="author"
                    class="form-control"
                    required
                    value="<?php echo htmlspecialchars($formValues['author']); ?>"
                >
            </div>

            <div class="form-group">
                <label for="category">Category / Genre</label>
                <input
                    type="text"
                    id="category"
                    name="category"
                    class="form-control"
                    value="<?php echo htmlspecialchars($formValues['category']); ?>"
                >
            </div>

            <div class="form-group">
                <label for="isbn">ISBN</label>
                <input
                    type="text"
                    id="isbn"
                    name="isbn"
                    class="form-control"
                    value="<?php echo htmlspecialchars($formValues['isbn']); ?>"
                >
            </div>

            <div class="form-group">
                <label for="available_copies">Number of copies (available/total)</label>
                <input
                    type="number"
                    id="available_copies"
                    name="available_copies"
                    class="form-control"
                    min="0"
                    value="<?php echo htmlspecialchars($formValues['available_copies']); ?>"
                >
            </div>

            <div class="auth-form-footer" style="margin-top:14px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">
                    <?php echo $isEdit ? 'Save Changes' : 'Create Book'; ?>
                </button>
                <a href="index.php?page=books_manage" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
