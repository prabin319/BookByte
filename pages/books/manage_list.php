<?php
// pages/books/manage_list.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

// Only ADMIN and LIBRARIAN can access
$user = currentUser();
$role = $user['role'] ?? '';
if (!in_array($role, ['ADMIN', 'LIBRARIAN'], true)) {
    requireLogin(); // will redirect if not logged in
}

$search       = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterCat    = isset($_GET['category']) ? trim($_GET['category']) : '';
$filterStatus = isset($_GET['availability']) ? trim($_GET['availability']) : '';

$books = [];
try {
    $pdo = getDBConnection();
    // keep SQL simple so we don't depend on exact column names
    $stmt = $pdo->query('SELECT * FROM books');
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $books = [];
}

// ---------- Helper functions that adapt to your schema ----------

function getBookTitle(array $book)
{
    return $book['title'] ?? ($book['book_title'] ?? 'Untitled');
}

function getBookAuthor(array $book)
{
    if (isset($book['author'])) return $book['author'];
    if (isset($book['author_name'])) return $book['author_name'];
    if (isset($book['authors'])) return $book['authors'];
    return 'Unknown';
}

function getBookCategory(array $book)
{
    if (isset($book['category'])) return $book['category'];
    if (isset($book['genre'])) return $book['genre'];
    if (isset($book['subject'])) return $book['subject'];
    return 'Uncategorized';
}

function getBookIsbn(array $book)
{
    if (isset($book['isbn'])) return $book['isbn'];
    if (isset($book['isbn13'])) return $book['isbn13'];
    return '';
}

/**
 * Try to detect how many copies we have.
 * Add your actual column name here if needed.
 */
function getBookCopies(array $book)
{
    // 👉 If your books table has a column like quantity/stock/etc.,
    //    add it here and this page will start showing real numbers.
    if (isset($book['available_copies'])) return (int)$book['available_copies'];
    if (isset($book['copies_available'])) return (int)$book['copies_available'];
    if (isset($book['copies'])) return (int)$book['copies'];
    if (isset($book['total_copies'])) return (int)$book['total_copies'];
    if (isset($book['quantity'])) return (int)$book['quantity'];
    if (isset($book['stock'])) return (int)$book['stock'];
    if (isset($book['no_of_copies'])) return (int)$book['no_of_copies'];

    return null; // unknown
}

/**
 * Optional: if you have a string status column like 'status' or 'availability'.
 */
function getBookStatusText(array $book)
{
    if (isset($book['status'])) {
        return strtoupper(trim($book['status']));
    }
    if (isset($book['availability'])) {
        return strtoupper(trim($book['availability']));
    }
    return null;
}

// ---------- Apply filters in PHP ----------

$filteredBooks = array_filter($books, function ($book) use ($search, $filterCat, $filterStatus) {
    $title    = mb_strtolower(getBookTitle($book));
    $author   = mb_strtolower(getBookAuthor($book));
    $category = mb_strtolower(getBookCategory($book));
    $isbn     = mb_strtolower(getBookIsbn($book));

    if ($search !== '') {
        $s = mb_strtolower($search);
        if (
            mb_strpos($title, $s) === false &&
            mb_strpos($author, $s) === false &&
            mb_strpos($isbn, $s) === false
        ) {
            return false;
        }
    }

    if ($filterCat !== '') {
        if ($category !== mb_strtolower($filterCat)) {
            return false;
        }
    }

    if ($filterStatus !== '') {
        $copies      = getBookCopies($book);
        $statusText  = getBookStatusText($book); // e.g. AVAILABLE/UNAVAILABLE

        // If we have numeric copies, prefer that logic
        if ($copies !== null) {
            if ($filterStatus === 'available' && $copies <= 0) return false;
            if ($filterStatus === 'unavailable' && $copies > 0) return false;
            if ($filterStatus === 'unknown') return false; // numeric data → not "unknown"
        } elseif ($statusText !== null) {
            // Use string status field (e.g. AVAILABLE/UNAVAILABLE)
            if ($filterStatus === 'available' && $statusText !== 'AVAILABLE') return false;
            if ($filterStatus === 'unavailable' && $statusText === 'AVAILABLE') return false;
            if ($filterStatus === 'unknown') return false; // we actually know the status
        } else {
            // no copies and no status → it's really unknown
            if ($filterStatus !== 'unknown') return false;
        }
    }

    return true;
});

// Build category filter options from data
$categoryOptions = [];
foreach ($books as $b) {
    $cat = trim(getBookCategory($b));
    if ($cat !== '' && !in_array($cat, $categoryOptions, true) && $cat !== 'Uncategorized') {
        $categoryOptions[] = $cat;
    }
}
sort($categoryOptions);
?>
<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Manage Books</h1>
            <p class="dashboard-subtitle">
                Search, filter, and maintain the library catalog.
            </p>
        </div>
        <div class="dashboard-welcome">
            <a href="index.php?page=book_form" class="btn btn-primary">
                + Add New Book
            </a>
        </div>
    </div>

    <!-- Toolbar: search + filters -->
    <div class="page-toolbar card">
        <form method="get" action="index.php" class="page-toolbar-form">
            <input type="hidden" name="page" value="books_manage">

            <div class="page-toolbar-row">
                <div class="page-toolbar-left">
                    <div class="search-box">
                        <input
                            type="text"
                            name="q"
                            class="search-input"
                            placeholder="Search by title, author, or ISBN..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select">
                            <option value="">All</option>
                            <?php foreach ($categoryOptions as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"
                                    <?php echo ($filterCat === $cat) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Availability</label>
                        <select name="availability" class="filter-select">
                            <option value="">All</option>
                            <option value="available" <?php echo $filterStatus === 'available' ? 'selected' : ''; ?>>
                                Available
                            </option>
                            <option value="unavailable" <?php echo $filterStatus === 'unavailable' ? 'selected' : ''; ?>>
                                Unavailable
                            </option>
                            <option value="unknown" <?php echo $filterStatus === 'unknown' ? 'selected' : ''; ?>>
                                Unknown
                            </option>
                        </select>
                    </div>
                </div>

                <div class="page-toolbar-right">
                    <button type="submit" class="btn btn-primary">
                        Apply
                    </button>
                    <a href="index.php?page=books_manage" class="btn btn-secondary">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Books table -->
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Books Catalog</h2>
                <p class="card-subtitle">
                    <?php echo count($filteredBooks); ?> book(s) found.
                </p>
            </div>
        </div>

        <?php if (empty($filteredBooks)): ?>
            <p class="text-muted">No books match your current filters.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Author(s)</th>
                    <th>Category</th>
                    <th>ISBN</th>
                    <th>Copies</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($filteredBooks as $book): ?>
                    <?php
                    $id       = $book['id'] ?? null;
                    $title    = getBookTitle($book);
                    $author   = getBookAuthor($book);
                    $category = getBookCategory($book);
                    $isbn     = getBookIsbn($book);
                    $copies   = getBookCopies($book);
                    $rowStatus = getBookStatusText($book);

                    // Decide status label/class
                    $statusLabel = 'Unknown';
                    $statusClass = 'badge-blue';

                    if ($copies !== null) {
                        if ($copies > 0) {
                            $statusLabel = 'Available';
                            $statusClass = 'badge-green';
                        } else {
                            $statusLabel = 'Unavailable';
                            $statusClass = 'badge-red';
                        }
                    } elseif ($rowStatus !== null) {
                        if ($rowStatus === 'AVAILABLE') {
                            $statusLabel = 'Available';
                            $statusClass = 'badge-green';
                        } elseif (in_array($rowStatus, ['UNAVAILABLE', 'OUT_OF_STOCK'], true)) {
                            $statusLabel = 'Unavailable';
                            $statusClass = 'badge-red';
                        } else {
                            $statusLabel = ucfirst(strtolower($rowStatus));
                            $statusClass = 'badge-blue';
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($title); ?></td>
                        <td><?php echo htmlspecialchars($author); ?></td>
                        <td>
                            <span class="badge badge-category">
                                <?php echo htmlspecialchars($category); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($isbn); ?></td>
                        <td><?php echo $copies === null ? '—' : (int)$copies; ?></td>
                        <td>
                            <span class="badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <div class="table-actions">
                                <?php if ($id !== null): ?>
                                    <a href="index.php?page=book_detail&id=<?php echo (int)$id; ?>"
                                       class="btn-icon" title="View">
                                        👁
                                    </a>
                                    <a href="index.php?page=book_form&id=<?php echo (int)$id; ?>"
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
