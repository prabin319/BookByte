<?php
// pages/books/detail.php - FIXED VERSION

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin();

$user = currentUser();
$pdo = getDBConnection();

$bookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$book = null;
$error = '';
$successMessage = '';

// Handle borrow action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {
    $userId = $user['id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if book is available
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id LIMIT 1 FOR UPDATE");
        $stmt->execute([':id' => $bookId]);
        $bookData = $stmt->fetch();
        
        if (!$bookData) {
            throw new Exception('Book not found.');
        }
        
        // Check availability
        $availableCopies = isset($bookData['available_copies']) ? (int)$bookData['available_copies'] : 0;
        
        if ($availableCopies <= 0) {
            throw new Exception('Sorry, this book is currently unavailable.');
        }
        
        // Check if user already borrowed this book (and hasn't returned it)
        // FIXED: Use returned_at instead of returned_date
        $stmt = $pdo->prepare("
            SELECT * FROM loans 
            WHERE user_id = :uid AND book_id = :bid 
            AND returned_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId, ':bid' => $bookId]);
        
        if ($stmt->fetch()) {
            throw new Exception('You have already borrowed this book.');
        }
        
        // Create loan record with loan_date
        $loanDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+14 days'));
        
        $stmt = $pdo->prepare("
            INSERT INTO loans (user_id, book_id, loan_date, due_date, returned_at)
            VALUES (:uid, :bid, :loan_date, :due_date, NULL)
        ");
        
        $stmt->execute([
            ':uid' => $userId,
            ':bid' => $bookId,
            ':loan_date' => $loanDate,
            ':due_date' => $dueDate
        ]);
        
        // Update available copies
        $newCopies = $availableCopies - 1;
        $newStatus = $newCopies > 0 ? 'AVAILABLE' : 'UNAVAILABLE';
        
        $stmt = $pdo->prepare("
            UPDATE books 
            SET available_copies = :copies, status = :status 
            WHERE id = :id
        ");
        $stmt->execute([
            ':copies' => $newCopies, 
            ':status' => $newStatus,
            ':id' => $bookId
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $successMessage = 'Book borrowed successfully! Please return it within 14 days.';
        
        // Reload book data
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $bookId]);
        $book = $stmt->fetch();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error processing your request. Please try again.';
    }
}

// Load book details if not already loaded
if (!$book) {
    if ($bookId <= 0) {
        $error = 'Invalid book ID.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $bookId]);
            $book = $stmt->fetch();
            
            if (!$book) {
                $error = 'Book not found.';
            }
        } catch (PDOException $e) {
            $error = 'Error loading book details.';
        }
    }
}

// Get book availability
function getBookAvailability($book) {
    if (isset($book['available_copies'])) {
        return (int)$book['available_copies'];
    }
    if (isset($book['status'])) {
        return strtoupper($book['status']) === 'AVAILABLE' ? 1 : 0;
    }
    return 0;
}

$availableCopies = $book ? getBookAvailability($book) : 0;
$isAvailable = $availableCopies > 0;
?>

<?php if ($error && !$book): ?>
    <div class="book-detail-error">
        <div class="error-icon">⚠️</div>
        <h2><?php echo htmlspecialchars($error); ?></h2>
        <a href="index.php?page=books_student" class="btn-back-error">← Back to Books</a>
    </div>
<?php else: ?>
    <div class="book-detail-simple">
        <!-- Back Button -->
        <a href="javascript:history.back()" class="btn-back-simple">
            ← Back to Book List
        </a>

        <!-- Success/Error Messages -->
        <?php if ($successMessage): ?>
            <div class="message-success">
                <span class="msg-icon">✓</span>
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($error && $book): ?>
            <div class="message-error">
                <span class="msg-icon">⚠️</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="detail-card">
            <div class="detail-grid">
                <!-- Book Cover -->
                <div class="cover-section">
                    <?php if (!empty($book['cover_url'])): ?>
                        <img src="<?php echo htmlspecialchars($book['cover_url']); ?>" 
                             alt="<?php echo htmlspecialchars($book['title']); ?>" 
                             class="book-cover">
                    <?php else: ?>
                        <div class="cover-placeholder">
                            <span class="placeholder-icon">📖</span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Status Badge -->
                    <div class="status-badge <?php echo $isAvailable ? 'status-available' : 'status-unavailable'; ?>">
                        <?php if ($isAvailable): ?>
                            <span class="status-icon">✓</span>
                            <span>Available (<?php echo $availableCopies; ?>)</span>
                        <?php else: ?>
                            <span class="status-icon">✗</span>
                            <span>Unavailable</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Book Info -->
                <div class="info-section">
                    <span class="category-badge">
                        <?php echo htmlspecialchars($book['category'] ?? 'General'); ?>
                    </span>

                    <h1 class="book-title">
                        <?php echo htmlspecialchars($book['title'] ?? 'Untitled'); ?>
                    </h1>

                    <p class="book-author">
                        by <?php echo htmlspecialchars($book['author'] ?? 'Unknown Author'); ?>
                    </p>

                    <!-- Meta Info -->
                    <div class="meta-info">
                        <?php if (!empty($book['isbn'])): ?>
                        <div class="meta-row">
                            <span class="meta-label">📚 ISBN</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['isbn']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($book['publisher'])): ?>
                        <div class="meta-row">
                            <span class="meta-label">🏢 Publisher</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['publisher']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($book['year_of_release']) || !empty($book['year'])): ?>
                        <div class="meta-row">
                            <span class="meta-label">📅 Year</span>
                            <span class="meta-value">
                                <?php echo htmlspecialchars($book['year_of_release'] ?? $book['year'] ?? 'N/A'); ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($book['edition'])): ?>
                        <div class="meta-row">
                            <span class="meta-label">📖 Edition</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['edition']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($book['description'])): ?>
                    <div class="description-box">
                        <h3>📝 Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Review -->
                    <?php if (!empty($book['review'])): ?>
                    <div class="review-box">
                        <h3>💭 Review</h3>
                        <p><?php echo nl2br(htmlspecialchars($book['review'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if ($user['role'] === 'STUDENT'): ?>
                            <?php if ($isAvailable): ?>
                                <form method="POST" style="flex: 1;" onsubmit="return confirm('Borrow this book?');">
                                    <input type="hidden" name="action" value="borrow">
                                    <button type="submit" class="btn-borrow-main">
                                        📖 Borrow This Book
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn-disabled" disabled>
                                    🔒 Currently Unavailable
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <a href="javascript:history.back()" class="btn-secondary-main">
                            Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
/* Simple Book Detail Styles */
.book-detail-simple {
    max-width: 1000px;
    margin: 0 auto;
}

.btn-back-simple {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #4b5563;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
    transition: all 0.2s;
}

.btn-back-simple:hover {
    border-color: #667eea;
    color: #667eea;
}

/* Messages */
.message-success,
.message-error {
    padding: 16px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 500;
}

.message-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #22c55e;
}

.message-error {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #ef4444;
}

.msg-icon {
    font-size: 20px;
}

/* Detail Card */
.detail-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.detail-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 40px;
}

/* Cover Section */
.cover-section {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.book-cover {
    width: 100%;
    height: 400px;
    object-fit: cover;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.cover-placeholder {
    width: 100%;
    height: 400px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.placeholder-icon {
    font-size: 80px;
    filter: brightness(0) invert(1);
}

.status-badge {
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.status-available {
    background: #dcfce7;
    color: #166534;
}

.status-unavailable {
    background: #fee2e2;
    color: #b91c1c;
}

.status-icon {
    font-size: 16px;
}

/* Info Section */
.info-section {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.category-badge {
    display: inline-block;
    width: fit-content;
    padding: 6px 14px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.book-title {
    font-size: 32px;
    font-weight: 700;
    color: #111827;
    margin: 0;
    line-height: 1.2;
}

.book-author {
    font-size: 18px;
    color: #6b7280;
    font-style: italic;
    margin: 0;
}

/* Meta Info */
.meta-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 8px;
}

.meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}

.meta-label {
    color: #6b7280;
    font-weight: 500;
}

.meta-value {
    color: #111827;
    font-weight: 600;
}

/* Description & Review */
.description-box,
.review-box {
    padding: 16px;
    background: #f9fafb;
    border-radius: 8px;
}

.description-box h3,
.review-box h3 {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 10px 0;
}

.description-box p,
.review-box p {
    font-size: 14px;
    line-height: 1.6;
    color: #4b5563;
    margin: 0;
}

.review-box {
    background: #eff6ff;
    border-left: 3px solid #667eea;
}

.review-box p {
    font-style: italic;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.btn-borrow-main {
    flex: 1;
    padding: 14px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-borrow-main:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-disabled {
    flex: 1;
    padding: 14px 24px;
    background: #9ca3af;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-secondary-main {
    padding: 14px 24px;
    background: white;
    color: #4b5563;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-secondary-main:hover {
    border-color: #667eea;
    color: #667eea;
}

/* Error State */
.book-detail-error {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    max-width: 500px;
    margin: 0 auto;
}

.error-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.btn-back-error {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 24px;
    background: #667eea;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .book-cover,
    .cover-placeholder {
        height: 350px;
    }
    
    .book-title {
        font-size: 24px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>