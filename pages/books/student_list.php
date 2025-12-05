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

<div class="browse-books-container">
    <!-- Header Section -->
    <div class="browse-header">
        <div class="header-content">
            <h1 class="browse-title">
                <span class="title-icon">📚</span>
                Browse Books
            </h1>
            <p class="browse-subtitle">Search and explore our library collection</p>
        </div>
        <div class="header-stats">
            <div class="stat-badge">
                <span class="stat-number"><?php echo count($books); ?></span>
                <span class="stat-label">Total Books</span>
            </div>
            <div class="stat-badge">
                <span class="stat-number"><?php echo count($filteredBooks); ?></span>
                <span class="stat-label">Showing</span>
            </div>
        </div>
    </div>

    <!-- Search & Filter Section -->
    <div class="search-section">
        <form method="get" action="index.php" class="search-form">
            <input type="hidden" name="page" value="books_student">
            
            <div class="search-row">
                <div class="search-input-group">
                    <span class="search-icon">🔍</span>
                    <input
                        type="text"
                        name="q"
                        class="search-input-modern"
                        placeholder="Search by title, author, or ISBN..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>

                <?php if (!empty($categories)): ?>
                <div class="filter-group-modern">
                    <span class="filter-icon">📑</span>
                    <select name="category" class="filter-select-modern">
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

                <div class="search-actions">
                    <button type="submit" class="btn-search">
                        <span>Search</span>
                    </button>
                    <a href="index.php?page=books_student" class="btn-reset">
                        <span>Reset</span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Books Grid -->
    <div class="books-list-section">
        <?php if (empty($filteredBooks)): ?>
            <div class="empty-state-books">
                <div class="empty-icon-large">📖</div>
                <h3>No books found</h3>
                <p>Try adjusting your search or filters</p>
            </div>
        <?php else: ?>
            <div class="books-modern-grid">
                <?php 
                $colors = ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#30cfd0'];
                foreach ($filteredBooks as $index => $book): 
                    $title = getBookTitle($book);
                    $author = getBookAuthor($book);
                    $category = getBookCategory($book);
                    $isbn = getBookISBN($book);
                    $copies = getBookCopies($book);
                    $status = getBookStatus($book);
                    $coverUrl = htmlspecialchars($book['cover_url'] ?? '');
                    $description = htmlspecialchars($book['description'] ?? '');
                    
                    // Status badge
                    $statusClass = 'status-unknown';
                    $statusLabel = 'Unknown';
                    $statusIcon = '❓';
                    
                    if ($status === 'AVAILABLE') {
                        $statusClass = 'status-available';
                        $statusLabel = 'Available';
                        $statusIcon = '✓';
                    } elseif ($status === 'UNAVAILABLE' || $status === 'BORROWED') {
                        $statusClass = 'status-unavailable';
                        $statusLabel = 'Unavailable';
                        $statusIcon = '✗';
                    }
                    
                    $colorIndex = $index % count($colors);
                    $bgColor = $colors[$colorIndex];
                    $delay = ($index % 12) * 0.05;
                ?>
                    <div class="book-item-modern" style="animation-delay: <?php echo $delay; ?>s">
                        <!-- Book Cover -->
                        <div class="book-cover-mini">
                            <?php if ($coverUrl): ?>
                                <img src="<?php echo $coverUrl; ?>" alt="<?php echo htmlspecialchars($title); ?>" class="cover-image">
                            <?php else: ?>
                                <div class="cover-placeholder" style="background: linear-gradient(135deg, <?php echo $bgColor; ?> 0%, <?php echo $bgColor; ?>dd 100%);">
                                    <span class="cover-icon">📖</span>
                                </div>
                            <?php endif; ?>
                            <div class="book-hover-overlay">
                                <span class="hover-icon">👁️</span>
                            </div>
                        </div>

                        <!-- Book Info -->
                        <div class="book-info-modern">
                            <div class="book-category-badge"><?php echo htmlspecialchars($category); ?></div>
                            
                            <h3 class="book-title-modern"><?php echo htmlspecialchars($title); ?></h3>
                            <p class="book-author-modern">by <?php echo htmlspecialchars($author); ?></p>
                            
                            <div class="book-details-row">
                                <div class="detail-item">
                                    <span class="detail-icon">🏷️</span>
                                    <span class="detail-text"><?php echo htmlspecialchars($isbn) ?: 'N/A'; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-icon">📦</span>
                                    <span class="detail-text"><?php echo $copies !== null ? $copies . ' copies' : 'N/A'; ?></span>
                                </div>
                            </div>

                            <?php if ($description): ?>
                                <p class="book-description-mini">
                                    <?php 
                                    $shortDesc = mb_strlen($description) > 80 ? mb_substr($description, 0, 80) . '...' : $description;
                                    echo $shortDesc;
                                    ?>
                                </p>
                            <?php endif; ?>

                            <div class="book-footer-modern">
                                <div class="status-badge-modern <?php echo $statusClass; ?>">
                                    <span class="status-icon"><?php echo $statusIcon; ?></span>
                                    <span><?php echo $statusLabel; ?></span>
                                </div>
                                <button class="btn-view-book" onclick="alert('View details for: <?php echo htmlspecialchars($title); ?>')">
                                    <span>View</span>
                                    <span class="view-arrow">→</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Browse Books Modern Styles */
.browse-books-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0;
    animation: fadeIn 0.6s ease;
}

/* Header Section */
.browse-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    animation: slideDown 0.6s ease;
}

.header-content {
    color: white;
}

.browse-title {
    font-size: 36px;
    font-weight: 800;
    margin: 0 0 8px 0;
    color: white;
    display: flex;
    align-items: center;
    gap: 12px;
}

.title-icon {
    font-size: 40px;
    animation: bounce 2s infinite;
}

.browse-subtitle {
    font-size: 16px;
    margin: 0;
    opacity: 0.95;
}

.header-stats {
    display: flex;
    gap: 20px;
}

.stat-badge {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    padding: 16px 24px;
    border-radius: 16px;
    text-align: center;
    min-width: 100px;
}

.stat-number {
    display: block;
    font-size: 28px;
    font-weight: 800;
    color: white;
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    display: block;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
}

/* Search Section */
.search-section {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 32px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    animation: slideUp 0.6s ease;
}

.search-form {
    width: 100%;
}

.search-row {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input-group {
    flex: 1;
    min-width: 300px;
    position: relative;
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 20px;
    pointer-events: none;
}

.search-input-modern {
    width: 100%;
    padding: 14px 16px 14px 48px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
}

.search-input-modern:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.filter-group-modern {
    position: relative;
    min-width: 200px;
}

.filter-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    pointer-events: none;
}

.filter-select-modern {
    width: 100%;
    padding: 14px 16px 14px 48px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    background: #f9fafb;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-select-modern:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.search-actions {
    display: flex;
    gap: 8px;
}

.btn-search, .btn-reset {
    padding: 14px 28px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-search {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-search:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-reset {
    background: #f3f4f6;
    color: #4b5563;
}

.btn-reset:hover {
    background: #e5e7eb;
}

/* Books Grid */
.books-list-section {
    animation: fadeInUp 0.8s ease;
}

.books-modern-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
}

.book-item-modern {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    display: flex;
    gap: 16px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeInScale 0.5s ease backwards;
}

.book-item-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
    border-color: #667eea;
}

/* Book Cover Mini */
.book-cover-mini {
    width: 80px;
    height: 120px;
    flex-shrink: 0;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.cover-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.book-item-modern:hover .cover-image {
    transform: scale(1.05);
}

.cover-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cover-icon {
    font-size: 32px;
    filter: brightness(0) invert(1);
}

.book-hover-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.book-item-modern:hover .book-hover-overlay {
    opacity: 1;
}

.hover-icon {
    font-size: 28px;
}

/* Book Info */
.book-info-modern {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.book-category-badge {
    display: inline-block;
    width: fit-content;
    padding: 4px 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.book-title-modern {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 4px 0;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.book-author-modern {
    font-size: 13px;
    color: #6b7280;
    margin: 0 0 12px 0;
    font-style: italic;
}

.book-details-row {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #6b7280;
}

.detail-icon {
    font-size: 14px;
}

.book-description-mini {
    font-size: 12px;
    color: #6b7280;
    line-height: 1.5;
    margin: 0 0 12px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.book-footer-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

.status-badge-modern {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-available {
    background: #dcfce7;
    color: #166534;
}

.status-unavailable {
    background: #fee2e2;
    color: #b91c1c;
}

.status-unknown {
    background: #e0f2fe;
    color: #075985;
}

.status-icon {
    font-size: 14px;
}

.btn-view-book {
    padding: 8px 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.btn-view-book:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.view-arrow {
    transition: transform 0.3s ease;
}

.btn-view-book:hover .view-arrow {
    transform: translateX(4px);
}

/* Empty State */
.empty-state-books {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 20px;
    border: 2px dashed #e5e7eb;
}

.empty-icon-large {
    font-size: 80px;
    margin-bottom: 20px;
    animation: float 3s ease-in-out infinite;
}

.empty-state-books h3 {
    font-size: 24px;
    color: #111827;
    margin-bottom: 8px;
}

.empty-state-books p {
    color: #6b7280;
    font-size: 16px;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

/* Responsive */
@media (max-width: 768px) {
    .browse-header {
        flex-direction: column;
        gap: 20px;
        padding: 32px 24px;
    }
    
    .browse-title {
        font-size: 28px;
    }
    
    .header-stats {
        width: 100%;
        justify-content: center;
    }
    
    .search-row {
        flex-direction: column;
    }
    
    .search-input-group,
    .filter-group-modern {
        width: 100%;
        min-width: 100%;
    }
    
    .search-actions {
        width: 100%;
    }
    
    .btn-search,
    .btn-reset {
        flex: 1;
    }
    
    .books-modern-grid {
        grid-template-columns: 1fr;
    }
}
</style>