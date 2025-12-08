<?php
// pages/loans/student_returns.php - FIXED VERSION

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin();

$user = currentUser();
$userId = $user['id'] ?? null;

// Only students can access this page
if ($user['role'] !== 'STUDENT') {
    header('Location: index.php?page=loans_active');
    exit;
}

$pdo = getDBConnection();

$message = '';
$messageType = '';
$FINE_PER_DAY = 5.00;

// Handle return request from student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_loan_id'])) {
    $loanId = (int)$_POST['return_loan_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Verify the loan belongs to this student
        $stmt = $pdo->prepare("
            SELECT l.*, b.title, b.id as book_id
            FROM loans l
            JOIN books b ON l.book_id = b.id
            WHERE l.id = :loan_id AND l.user_id = :user_id
        ");
        $stmt->execute(['loan_id' => $loanId, 'user_id' => $userId]);
        $loan = $stmt->fetch();
        
        if (!$loan) {
            throw new Exception('Loan not found or does not belong to you.');
        }
        
        // Check if already returned
        $returned = $loan['returned_at'] ?? null;
        if ($returned && $returned !== '0000-00-00 00:00:00' && $returned !== '0000-00-00') {
            throw new Exception('This book has already been returned.');
        }
        
        // Calculate fine if overdue
        $fine = 0;
        $daysOverdue = 0;
        $dueDate = $loan['due_date'] ?? null;
        if ($dueDate && $dueDate !== '0000-00-00' && strtotime($dueDate) < time()) {
            $daysOverdue = floor((time() - strtotime($dueDate)) / 86400);
            $fine = $daysOverdue * $FINE_PER_DAY;
        }
        
        // Update loan record - mark as returned
        $stmt = $pdo->prepare("UPDATE loans SET returned_at = NOW() WHERE id = :loan_id");
        $stmt->execute(['loan_id' => $loanId]);
        
        // Update book availability
        $stmt = $pdo->prepare("
            UPDATE books 
            SET available_copies = available_copies + 1,
                status = CASE 
                    WHEN available_copies + 1 > 0 THEN 'AVAILABLE'
                    ELSE status
                END
            WHERE id = :book_id
        ");
        $stmt->execute(['book_id' => $loan['book_id']]);
        
        $pdo->commit();
        
        $messageType = 'success';
        $message = "Book '{$loan['title']}' returned successfully!";
        
        if ($fine > 0) {
            $message .= " Please visit the library to pay your overdue fine of $" . number_format($fine, 2) . ".";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $messageType = 'error';
        $message = $e->getMessage();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $messageType = 'error';
        $message = 'Error processing return. Please try again.';
    }
}

// Load student's borrowed books - FIXED QUERY
$borrowedBooks = [];
try {
    $sql = "
        SELECT 
            l.id,
            l.loan_date,
            l.due_date,
            l.returned_at,
            b.title as book_title,
            b.author as book_author,
            b.isbn,
            b.cover_url
        FROM loans l
        JOIN books b ON l.book_id = b.id
        WHERE l.user_id = :user_id
        AND (l.returned_at IS NULL OR l.returned_at = '0000-00-00 00:00:00' OR l.returned_at = '0000-00-00')
        ORDER BY l.due_date ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $borrowedBooks = $stmt->fetchAll();
    
    // Calculate fines
    foreach ($borrowedBooks as &$book) {
        $book['is_overdue'] = false;
        $book['days_overdue'] = 0;
        $book['fine'] = 0;
        
        $dueDate = $book['due_date'] ?? null;
        if ($dueDate && $dueDate !== '0000-00-00' && strtotime($dueDate) < time()) {
            $book['is_overdue'] = true;
            $book['days_overdue'] = floor((time() - strtotime($dueDate)) / 86400);
            $book['fine'] = $book['days_overdue'] * $FINE_PER_DAY;
        }
    }
    unset($book);
    
} catch (PDOException $e) {
    $borrowedBooks = [];
    $message = 'Error loading borrowed books: ' . $e->getMessage();
    $messageType = 'error';
}

// Calculate statistics
$stats = [
    'total_borrowed' => count($borrowedBooks),
    'overdue_count' => 0,
    'total_fines' => 0
];

foreach ($borrowedBooks as $book) {
    if ($book['is_overdue']) {
        $stats['overdue_count']++;
        $stats['total_fines'] += $book['fine'];
    }
}
?>

<style>
/* Modern Return Page Styles */
.student-returns-page {
    max-width: 1400px;
    margin: 0 auto;
    animation: fadeIn 0.6s ease;
}

/* Hero Header */
.returns-hero {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    animation: slideDown 0.6s ease;
}

.returns-hero h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.returns-hero p {
    font-size: 15px;
    opacity: 0.95;
    margin: 0;
}

.hero-icon {
    font-size: 40px;
    animation: bounce 2s infinite;
}

/* Alert Messages */
.alert-return {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideInUp 0.4s ease;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.alert-icon {
    font-size: 24px;
}

/* Info Card */
.info-card-return {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border: 2px solid #93c5fd;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 32px;
    animation: slideInUp 0.5s ease 0.1s backwards;
}

.info-card-return h3 {
    color: #1e40af;
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-card-return p {
    color: #1e40af;
    font-size: 14px;
    margin: 0;
    line-height: 1.6;
}

/* Statistics Cards */
.stats-grid-return {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card-return {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
    animation: fadeInScale 0.5s ease backwards;
}

.stat-card-return:nth-child(1) { animation-delay: 0.2s; }
.stat-card-return:nth-child(2) { animation-delay: 0.3s; }
.stat-card-return:nth-child(3) { animation-delay: 0.4s; }

.stat-card-return:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon-return {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 16px;
}

.stat-icon-green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-icon-red { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-icon-yellow { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }

.stat-value-return {
    font-size: 36px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
    line-height: 1;
}

.stat-label-return {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

/* Books Grid */
.books-return-grid {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.return-book-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 2px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    display: flex;
    gap: 24px;
    padding: 24px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeInScale 0.5s ease backwards;
}

.return-book-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
    border-color: #10b981;
}

.return-book-card.overdue {
    border-color: #fca5a5;
    background: linear-gradient(to right, #fff5f5 0%, white 10%);
}

.return-book-cover {
    width: 120px;
    height: 160px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.return-book-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.return-book-card:hover .return-book-cover img {
    transform: scale(1.05);
}

.return-cover-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
}

.return-book-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.return-book-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.return-book-author {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 20px;
    font-style: italic;
}

.return-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 12px;
}

.return-detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.return-detail-label {
    font-size: 12px;
    color: #9ca3af;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.return-detail-value {
    font-size: 15px;
    color: #374151;
    font-weight: 600;
}

.status-badge-return {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    width: fit-content;
}

.status-on-time {
    background: #dcfce7;
    color: #166534;
}

.status-overdue {
    background: #fee2e2;
    color: #b91c1c;
}

.fine-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 2px solid #fbbf24;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.fine-warning-icon {
    font-size: 32px;
    animation: pulse 2s infinite;
}

.fine-warning-content {
    flex: 1;
}

.fine-warning-label {
    font-size: 13px;
    color: #92400e;
    font-weight: 600;
    margin-bottom: 4px;
}

.fine-warning-amount {
    font-size: 24px;
    font-weight: 800;
    color: #b91c1c;
    margin-bottom: 4px;
}

.fine-warning-details {
    font-size: 12px;
    color: #92400e;
}

.return-actions {
    margin-top: auto;
    padding-top: 20px;
    border-top: 2px solid #f3f4f6;
}

.btn-return-student {
    padding: 14px 32px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-return-student:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

.btn-return-student:active {
    transform: translateY(0);
}

/* Empty State */
.empty-state-return {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 20px;
    border: 2px dashed #e5e7eb;
    animation: fadeIn 0.6s ease;
}

.empty-icon-return {
    font-size: 80px;
    margin-bottom: 20px;
    animation: float 3s ease-in-out infinite;
}

.empty-state-return h3 {
    font-size: 24px;
    color: #111827;
    margin-bottom: 8px;
}

.empty-state-return p {
    color: #6b7280;
    font-size: 16px;
    margin-bottom: 24px;
}

.btn-browse {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 14px 32px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-browse:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    color: white;
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

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
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

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Responsive */
@media (max-width: 768px) {
    .returns-hero {
        padding: 32px 24px;
    }
    
    .returns-hero h1 {
        font-size: 28px;
    }
    
    .return-book-card {
        flex-direction: column;
        padding: 20px;
    }
    
    .return-book-cover {
        width: 100%;
        height: 240px;
    }
    
    .return-details-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid-return {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="student-returns-page">
    <!-- Hero Header -->
    <div class="returns-hero">
        <h1>
            <span class="hero-icon">📥</span>
            Return Books
        </h1>
        <p>Return your borrowed books and check for any fines</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-return alert-<?php echo $messageType; ?>">
            <span class="alert-icon"><?php echo $messageType === 'success' ? '✓' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Info Card -->
    <div class="info-card-return">
        <h3>
            <span>ℹ️</span>
            How to Return Books
        </h3>
        <p>You can return your borrowed books using this page. If the book is overdue, you'll need to pay the fine at the library counter. Click the "Return Book" button below to process your return.</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid-return">
        <div class="stat-card-return">
            <div class="stat-icon-return stat-icon-green">📚</div>
            <div class="stat-value-return"><?php echo $stats['total_borrowed']; ?></div>
            <div class="stat-label-return">Books to Return</div>
        </div>

        <div class="stat-card-return">
            <div class="stat-icon-return stat-icon-red">⚠️</div>
            <div class="stat-value-return"><?php echo $stats['overdue_count']; ?></div>
            <div class="stat-label-return">Overdue Books</div>
        </div>

        <div class="stat-card-return">
            <div class="stat-icon-return stat-icon-yellow">💰</div>
            <div class="stat-value-return">$<?php echo number_format($stats['total_fines'], 2); ?></div>
            <div class="stat-label-return">Total Fines</div>
        </div>
    </div>

    <!-- Borrowed Books List -->
    <?php if (empty($borrowedBooks)): ?>
        <div class="empty-state-return">
            <div class="empty-icon-return">📚</div>
            <h3>No borrowed books</h3>
            <p>You don't have any books to return at the moment</p>
            <a href="index.php?page=books_student" class="btn-browse">Browse Books</a>
        </div>
    <?php else: ?>
        <div class="books-return-grid">
            <?php foreach ($borrowedBooks as $index => $book): ?>
                <?php
                $bookTitle = $book['book_title'] ?? 'Unknown Book';
                $bookAuthor = $book['book_author'] ?? 'Unknown Author';
                $bookCover = $book['cover_url'] ?? null;
                $loanDate = $book['loan_date'] ? date('M d, Y', strtotime($book['loan_date'])) : 'N/A';
                $dueDate = $book['due_date'] ? date('M d, Y', strtotime($book['due_date'])) : 'N/A';
                $isOverdue = $book['is_overdue'];
                $fine = $book['fine'];
                $daysOverdue = $book['days_overdue'];
                
                $status = $isOverdue ? 'Overdue' : 'On Time';
                $statusClass = $isOverdue ? 'status-overdue' : 'status-on-time';
                $statusIcon = $isOverdue ? '⚠️' : '✓';
                
                $delay = $index * 0.1;
                ?>
                
                <div class="return-book-card <?php echo $isOverdue ? 'overdue' : ''; ?>" style="animation-delay: <?php echo $delay; ?>s">
                    <!-- Book Cover -->
                    <div class="return-book-cover">
                        <?php if ($bookCover): ?>
                            <img src="<?php echo htmlspecialchars($bookCover); ?>" alt="<?php echo htmlspecialchars($bookTitle); ?>">
                        <?php else: ?>
                            <div class="return-cover-placeholder">📖</div>
                        <?php endif; ?>
                    </div>

                    <!-- Book Info -->
                    <div class="return-book-info">
                        <h3 class="return-book-title"><?php echo htmlspecialchars($bookTitle); ?></h3>
                        <div class="return-book-author">by <?php echo htmlspecialchars($bookAuthor); ?></div>

                        <!-- Details -->
                        <div class="return-details-grid">
                            <div class="return-detail-item">
                                <div class="return-detail-label">📅 Loan Date</div>
                                <div class="return-detail-value"><?php echo $loanDate; ?></div>
                            </div>

                            <div class="return-detail-item">
                                <div class="return-detail-label">⏰ Due Date</div>
                                <div class="return-detail-value"><?php echo $dueDate; ?></div>
                            </div>

                            <div class="return-detail-item">
                                <div class="return-detail-label">Status</div>
                                <div class="status-badge-return <?php echo $statusClass; ?>">
                                    <span><?php echo $statusIcon; ?></span>
                                    <span><?php echo $status; ?></span>
                                </div>
                            </div>

                            <?php if ($book['isbn']): ?>
                            <div class="return-detail-item">
                                <div class="return-detail-label">📖 ISBN</div>
                                <div class="return-detail-value"><?php echo htmlspecialchars($book['isbn']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Fine Warning -->
                        <?php if ($isOverdue && $fine > 0): ?>
                            <div class="fine-warning">
                                <div class="fine-warning-icon">⚠️</div>
                                <div class="fine-warning-content">
                                    <div class="fine-warning-label">OVERDUE FINE</div>
                                    <div class="fine-warning-amount">$<?php echo number_format($fine, 2); ?></div>
                                    <div class="fine-warning-details">
                                        <?php echo $daysOverdue; ?> day(s) overdue × $<?php echo number_format($FINE_PER_DAY, 2); ?>/day<br>
                                        Please pay at the library counter
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Return Button -->
                        <div class="return-actions">
                            <form method="post" onsubmit="return confirm('Confirm return for: <?php echo htmlspecialchars($bookTitle); ?>?');">
                                <input type="hidden" name="return_loan_id" value="<?php echo $book['id']; ?>">
                                <button type="submit" class="btn-return-student">
                                    <span>✅</span>
                                    <span>Return This Book</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>