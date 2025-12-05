<?php
// pages/books/manage_list.php - ENHANCED VERSION

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

$user = currentUser();
$role = $user['role'] ?? '';
if (!in_array($role, ['ADMIN', 'LIBRARIAN'], true)) {
    requireLogin();
}

$search       = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterCat    = isset($_GET['category']) ? trim($_GET['category']) : '';
$filterStatus = isset($_GET['availability']) ? trim($_GET['availability']) : '';

$books = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query('SELECT * FROM books ORDER BY title');
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $books = [];
}

// Helper functions
function getBookTitle(array $book) {
    return $book['title'] ?? ($book['book_title'] ?? 'Untitled');
}

function getBookAuthor(array $book) {
    if (isset($book['author'])) return $book['author'];
    if (isset($book['author_name'])) return $book['author_name'];
    if (isset($book['authors'])) return $book['authors'];
    return 'Unknown';
}

function getBookCategory(array $book) {
    if (isset($book['category'])) return $book['category'];
    if (isset($book['genre'])) return $book['genre'];
    if (isset($book['subject'])) return $book['subject'];
    return 'Uncategorized';
}

function getBookIsbn(array $book) {
    if (isset($book['isbn'])) return $book['isbn'];
    if (isset($book['isbn13'])) return $book['isbn13'];
    return '';
}

function getBookCopies(array $book) {
    // Try to get available copies
    $available = null;
    if (isset($book['available_copies'])) {
        $available = (int)$book['available_copies'];
    } elseif (isset($book['copies_available'])) {
        $available = (int)$book['copies_available'];
    } elseif (isset($book['copies'])) {
        $available = (int)$book['copies'];
    } elseif (isset($book['total_copies'])) {
        $available = (int)$book['total_copies'];
    } elseif (isset($book['quantity'])) {
        $available = (int)$book['quantity'];
    } elseif (isset($book['stock'])) {
        $available = (int)$book['stock'];
    } elseif (isset($book['no_of_copies'])) {
        $available = (int)$book['no_of_copies'];
    }
    
    // Default to 1 if no column found (assume book exists)
    return $available !== null ? $available : 1;
}

function getBookCover(array $book) {
    if (isset($book['cover_url']) && !empty($book['cover_url'])) {
        return $book['cover_url'];
    }
    if (isset($book['cover_image']) && !empty($book['cover_image'])) {
        return $book['cover_image'];
    }
    return null;
}

function getBookStatusText(array $book) {
    if (isset($book['status'])) {
        return strtoupper(trim($book['status']));
    }
    if (isset($book['availability'])) {
        return strtoupper(trim($book['availability']));
    }
    return null;
}

// Apply filters
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
        $statusText  = getBookStatusText($book);

        if ($copies !== null) {
            if ($filterStatus === 'available' && $copies <= 0) return false;
            if ($filterStatus === 'unavailable' && $copies > 0) return false;
            if ($filterStatus === 'unknown') return false;
        } elseif ($statusText !== null) {
            if ($filterStatus === 'available' && $statusText !== 'AVAILABLE') return false;
            if ($filterStatus === 'unavailable' && $statusText === 'AVAILABLE') return false;
            if ($filterStatus === 'unknown') return false;
        } else {
            if ($filterStatus !== 'unknown') return false;
        }
    }

    return true;
});

// Build category options
$categoryOptions = [];
foreach ($books as $b) {
    $cat = trim(getBookCategory($b));
    if ($cat !== '' && !in_array($cat, $categoryOptions, true) && $cat !== 'Uncategorized') {
        $categoryOptions[] = $cat;
    }
}
sort($categoryOptions);
?>

<style>
/* Enhanced Modern Book Management Styles */
.enhanced-manage-books {
    max-width: 1600px;
    margin: 0 auto;
    animation: fadeIn 0.5s ease;
}

.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 32px 40px;
    margin-bottom: 28px;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-left h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
}

.header-left p {
    font-size: 15px;
    opacity: 0.95;
    margin: 0;
}

.btn-add-book {
    background: white;
    color: #667eea;
    padding: 12px 28px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-add-book:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
}

/* Enhanced Toolbar */
.enhanced-toolbar {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.toolbar-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 16px;
    align-items: end;
}

.filter-group-modern {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-label-modern {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.search-input-modern {
    padding: 12px 16px 12px 44px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%236b7280" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>') no-repeat 14px center;
}

.search-input-modern:focus {
    outline: none;
    border-color: #667eea;
    background-color: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.select-modern {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    background: #f9fafb;
    cursor: pointer;
    transition: all 0.3s ease;
}

.select-modern:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.toolbar-actions {
    display: flex;
    gap: 10px;
}

.btn-apply {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-apply:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-reset {
    background: #f3f4f6;
    color: #374151;
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-reset:hover {
    background: #e5e7eb;
}

/* Enhanced Books Grid */
.books-grid-view {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.book-card-enhanced {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    animation: fadeInUp 0.5s ease backwards;
}

.book-card-enhanced:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.12);
    border-color: #667eea;
}

.book-cover-section {
    position: relative;
    width: 100%;
    height: 320px;
    overflow: hidden;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
}

.book-cover-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.book-card-enhanced:hover .book-cover-image {
    transform: scale(1.05);
}

.book-cover-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    padding: 20px;
    text-align: center;
}

.placeholder-icon {
    font-size: 64px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.placeholder-title {
    font-size: 16px;
    font-weight: 600;
    color: #6b7280;
    line-height: 1.3;
}

.book-badge-overlay {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.status-badge-overlay {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-available {
    background: rgba(220, 252, 231, 0.95);
    color: #166534;
}

.status-unavailable {
    background: rgba(254, 226, 226, 0.95);
    color: #b91c1c;
}

.status-unknown {
    background: rgba(224, 242, 254, 0.95);
    color: #075985;
}

.copies-badge {
    background: rgba(17, 24, 39, 0.85);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.book-info-section {
    padding: 20px;
}

.book-category-tag {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.book-title-enhanced {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 8px 0;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 48px;
}

.book-author-enhanced {
    font-size: 14px;
    color: #6b7280;
    margin: 0 0 12px 0;
}

.book-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 12px 0;
    border-top: 1px solid #f3f4f6;
    border-bottom: 1px solid #f3f4f6;
    margin-bottom: 16px;
}

.meta-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.meta-label {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.meta-value {
    font-size: 13px;
    color: #374151;
    font-weight: 500;
}

.book-actions-enhanced {
    display: flex;
    gap: 8px;
}

.btn-action {
    flex: 1;
    padding: 10px 16px;
    border-radius: 10px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
}

.btn-view {
    background: #f3f4f6;
    color: #374151;
}

.btn-view:hover {
    background: #e5e7eb;
    transform: translateY(-2px);
}

.btn-edit {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

/* Results Info */
.results-info {
    background: white;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.results-count {
    font-size: 15px;
    color: #6b7280;
}

.results-count strong {
    color: #111827;
    font-weight: 700;
}

/* Empty State */
.empty-state-enhanced {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 20px;
    border: 2px dashed #e5e7eb;
}

.empty-icon-large {
    font-size: 80px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state-enhanced h3 {
    font-size: 24px;
    color: #111827;
    margin-bottom: 8px;
}

.empty-state-enhanced p {
    color: #6b7280;
    font-size: 16px;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 1024px) {
    .toolbar-grid {
        grid-template-columns: 1fr;
    }
    
    .books-grid-view {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    }
}

@media (max-width: 768px) {
    .page-header-modern {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .books-grid-view {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="enhanced-manage-books">
    <!-- Enhanced Header -->
    <div class="page-header-modern">
        <div class="header-left">
            <h1>📚 Manage Books</h1>
            <p>Search, filter, and maintain the library catalog</p>
        </div>
        <a href="index.php?page=book_form" class="btn-add-book">
            <span style="font-size: 18px;">+</span>
            Add New Book
        </a>
    </div>

    <!-- Enhanced Toolbar -->
    <div class="enhanced-toolbar">
        <form method="get" action="index.php">
            <input type="hidden" name="page" value="books_manage">
            
            <div class="toolbar-grid">
                <div class="filter-group-modern">
                    <label class="filter-label-modern">Search Books</label>
                    <input
                        type="text"
                        name="q"
                        class="search-input-modern"
                        placeholder="Search by title, author, or ISBN..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>

                <div class="filter-group-modern">
                    <label class="filter-label-modern">Category</label>
                    <select name="category" class="select-modern">
                        <option value="">All Categories</option>
                        <?php foreach ($categoryOptions as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"
                                <?php echo ($filterCat === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group-modern">
                    <label class="filter-label-modern">Availability</label>
                    <select name="availability" class="select-modern">
                        <option value="">All Status</option>
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

                <div class="toolbar-actions">
                    <button type="submit" class="btn-apply">Apply</button>
                    <a href="index.php?page=books_manage" class="btn-reset">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Info -->
    <div class="results-info">
        <div class="results-count">
            Showing <strong><?php echo count($filteredBooks); ?></strong> of <strong><?php echo count($books); ?></strong> books
        </div>
    </div>

    <!-- Books Grid -->
    <?php if (empty($filteredBooks)): ?>
        <div class="empty-state-enhanced">
            <div class="empty-icon-large">📖</div>
            <h3>No books found</h3>
            <p>Try adjusting your search or filters</p>
        </div>
    <?php else: ?>
        <div class="books-grid-view">
            <?php 
            $colors = ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#30cfd0'];
            foreach ($filteredBooks as $index => $book): 
                $id       = $book['id'] ?? null;
                $title    = getBookTitle($book);
                $author   = getBookAuthor($book);
                $category = getBookCategory($book);
                $isbn     = getBookIsbn($book);
                $copies   = getBookCopies($book);
                $cover    = getBookCover($book);
                $rowStatus = getBookStatusText($book);

                // Status determination
                $statusLabel = 'Unknown';
                $statusClass = 'status-unknown';
                $statusIcon = '❓';

                if ($copies !== null) {
                    if ($copies > 0) {
                        $statusLabel = 'Available';
                        $statusClass = 'status-available';
                        $statusIcon = '✓';
                    } else {
                        $statusLabel = 'Unavailable';
                        $statusClass = 'status-unavailable';
                        $statusIcon = '✗';
                    }
                } elseif ($rowStatus !== null) {
                    if ($rowStatus === 'AVAILABLE') {
                        $statusLabel = 'Available';
                        $statusClass = 'status-available';
                        $statusIcon = '✓';
                    } elseif (in_array($rowStatus, ['UNAVAILABLE', 'OUT_OF_STOCK'], true)) {
                        $statusLabel = 'Unavailable';
                        $statusClass = 'status-unavailable';
                        $statusIcon = '✗';
                    }
                }

                $colorIndex = $index % count($colors);
                $bgColor = $colors[$colorIndex];
                $delay = ($index % 12) * 0.05;
            ?>
                <div class="book-card-enhanced" style="animation-delay: <?php echo $delay; ?>s">
                    <!-- Cover Section -->
                    <div class="book-cover-section">
                        <?php if ($cover): ?>
                            <img src="<?php echo htmlspecialchars($cover); ?>" 
                                 alt="<?php echo htmlspecialchars($title); ?>" 
                                 class="book-cover-image">
                        <?php else: ?>
                            <div class="book-cover-placeholder" style="background: linear-gradient(135deg, <?php echo $bgColor; ?> 0%, <?php echo $bgColor; ?>dd 100%);">
                                <div class="placeholder-icon">📖</div>
                                <div class="placeholder-title"><?php echo htmlspecialchars(mb_substr($title, 0, 40)); ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Badges -->
                        <div class="book-badge-overlay">
                            <div class="status-badge-overlay <?php echo $statusClass; ?>">
                                <span><?php echo $statusIcon; ?></span>
                                <span><?php echo $statusLabel; ?></span>
                            </div>
                            <?php if ($copies !== null): ?>
                                <div class="copies-badge">
                                    📦 <?php echo $copies; ?> copies
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info Section -->
                    <div class="book-info-section">
                        <div class="book-category-tag"><?php echo htmlspecialchars($category); ?></div>
                        
                        <h3 class="book-title-enhanced"><?php echo htmlspecialchars($title); ?></h3>
                        <div class="book-author-enhanced">by <?php echo htmlspecialchars($author); ?></div>

                        <div class="book-meta-grid">
                            <div class="meta-item">
                                <div class="meta-label">ISBN</div>
                                <div class="meta-value"><?php echo htmlspecialchars($isbn) ?: 'N/A'; ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Status</div>
                                <div class="meta-value"><?php echo $statusLabel; ?></div>
                            </div>
                        </div>

                        <div class="book-actions-enhanced">
                            <?php if ($id !== null): ?>
                                <a href="index.php?page=book_detail&id=<?php echo (int)$id; ?>" 
                                   class="btn-action btn-view">
                                    <span>👁</span>
                                    View
                                </a>
                                <a href="index.php?page=book_form&id=<?php echo (int)$id; ?>" 
                                   class="btn-action btn-edit">
                                    <span>✏️</span>
                                    Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>