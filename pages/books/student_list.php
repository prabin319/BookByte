<?php
// pages/books/student_list.php

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

$search = trim($_GET['q'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');

// Load all books
$books = [];
try {
    $sql = "SELECT * FROM books ORDER BY title";
    $stmt = $pdo->query($sql);
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $books = [];
}

// Helper functions
function getBookTitle($book) {
    return $book['title'] ?? 'Untitled';
}

function getBookAuthor($book) {
    return $book['author'] ?? 'Unknown Author';
}

function getBookCategory($book) {
    return $book['category'] ?? 'Uncategorized';
}

function getBookISBN($book) {
    return $book['isbn'] ?? '';
}

function getBookCopies($book) {
    if (isset($book['available_copies'])) return (int)$book['available_copies'];
    if (isset($book['copies'])) return (int)$book['copies'];
    return null;
}

function getBookStatus($book) {
    if (isset($book['status'])) {
        return strtoupper(trim($book['status']));
    }
    $copies = getBookCopies($book);
    if ($copies !== null) {
        return $copies > 0 ? 'AVAILABLE' : 'UNAVAILABLE';
    }
    return 'UNKNOWN';
}

// Filter books
$filteredBooks = array_filter($books, function($book) use ($search, $filterCategory) {
    $title = mb_strtolower(getBookTitle($book));
    $author = mb_strtolower(getBookAuthor($book));
    $category = mb_strtolower(getBookCategory($book));
    $isbn = mb_strtolower(getBookISBN($book));
    
    // Search filter
    if ($search !== '') {
        $s = mb_strtolower($search);
        if (mb_strpos($title, $s) === false && 
            mb_strpos($author, $s) === false && 
            mb_strpos($isbn, $s) === false) {
            return false;
        }
    }
    
    // Category filter
    if ($filterCategory !== '' && $category !== mb_strtolower($filterCategory)) {
        return false;
    }
    
    return true;
});

// Get unique categories
$categories = [];
foreach ($books as $book) {
    $cat = trim(getBookCategory($book));
    if ($cat !== '' && $cat !== 'Uncategorized' && !in_array($cat, $categories, true)) {
        $categories[] = $cat;
    }
}
sort($categories);
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Browse Books</h1>
            <p class="dashboard-subtitle">
                Search and explore our library collection.
            </p>
        </div>
    </div>

    <!-- Search & Filter Toolbar -->
    <div class="page-toolbar card">
        <form method="get" action="index.php" class="page-toolbar-form">
            <input type="hidden" name="page" value="books_student">
            
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

                    <?php if (!empty($categories)): ?>
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"
                                    <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="page-toolbar-right">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="index.php?page=books_student" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Books List -->
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Available Books</h2>
                <p class="card-subtitle">
                    <?php echo count($filteredBooks); ?> book(s) found.
                </p>
            </div>
        </div>

        <?php if (empty($filteredBooks)): ?>
            <p class="text-muted">No books match your search criteria.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>ISBN</th>
                        <th>Available</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filteredBooks as $book): ?>
                        <?php
                        $title = getBookTitle($book);
                        $author = getBookAuthor($book);
                        $category = getBookCategory($book);
                        $isbn = getBookISBN($book);
                        $copies = getBookCopies($book);
                        $status = getBookStatus($book);
                        
                        // Status badge class
                        $statusClass = 'badge-blue';
                        $statusLabel = 'Unknown';
                        
                        if ($status === 'AVAILABLE') {
                            $statusClass = 'badge-green';
                            $statusLabel = 'Available';
                        } elseif ($status === 'UNAVAILABLE' || $status === 'BORROWED') {
                            $statusClass = 'badge-red';
                            $statusLabel = 'Unavailable';
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($title); ?></strong></td>
                            <td><?php echo htmlspecialchars($author); ?></td>
                            <td>
                                <span class="badge badge-category">
                                    <?php echo htmlspecialchars($category); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($isbn); ?></td>
                            <td><?php echo $copies !== null ? (int)$copies : '—'; ?></td>
                            <td>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $statusLabel; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<style>
.badge-category {
    background: #eff6ff;
    color: #1d4ed8;
}

.filter-select {
    min-width: 180px;
    padding: 9px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #ffffff;
    transition: all 0.2s ease;
}

.filter-select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}
</style>