<?php
// pages/loans/student_returns.php - Student book return page

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
        if ($loan['returned_at'] && $loan['returned_at'] !== '0000-00-00 00:00:00') {
            throw new Exception('This book has already been returned.');
        }
        
        // Calculate fine if overdue
        $fine = 0;
        $daysOverdue = 0;
        if ($loan['due_date'] && strtotime($loan['due_date']) < time()) {
            $daysOverdue = floor((time() - strtotime($loan['due_date'])) / 86400);
            $fine = $daysOverdue * $FINE_PER_DAY;
        }
        
        // Update loan record - mark as returned
        $stmt = $pdo->prepare("
            UPDATE loans 
            SET returned_at = NOW() 
            WHERE id = :loan_id
        ");
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
        
        // Create notification
        try {
            $notifTitle = "Book Returned Successfully";
            $notifMessage = "You have successfully returned '{$loan['title']}'. Thank you!";
            
            if ($fine > 0) {
                $notifMessage .= " Note: You have an overdue fine of $" . number_format($fine, 2) . " ({$daysOverdue} days × $" . number_format($FINE_PER_DAY, 2) . "/day). Please visit the library to settle the fine.";
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, loan_id, type, title, message, sent_via, status, sent_at)
                VALUES (:user_id, :loan_id, 'RETURN_CONFIRMATION', :title, :message, 'APP', 'SENT', NOW())
            ");
            $stmt->execute([
                'user_id' => $userId,
                'loan_id' => $loanId,
                'title' => $notifTitle,
                'message' => $notifMessage
            ]);
        } catch (PDOException $e) {
            // Continue if notification fails
        }
        
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

// Load student's borrowed books
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
        AND (l.returned_at IS NULL OR l.returned_at = '0000-00-00' OR l.returned_at = '0000-00-00 00:00:00')
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
        
        if ($book['due_date'] && strtotime($book['due_date']) < time()) {
            $book['is_overdue'] = true;
            $book['days_overdue'] = floor((time() - strtotime($book['due_date'])) / 86400);
            $book['fine'] = $book['days_overdue'] * $FINE_PER_DAY;
        }
    }
    unset($book);
    
} catch (PDOException $e) {
    $borrowedBooks = [];
}
?>

<style>
.student-returns-page {
    max-width: 1200px;
    margin: 0 auto;
}

.returns-header-student {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
}

.returns-header-student h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
}

.returns-header-student p {
    font-size: 15px;
    opacity: 0.95;
    margin: 0;
}

.alert-student-return {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
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

.info-card {
    background: #dbeafe;
    border: 2px solid #93c5fd;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}

.info-card h3 {
    color: #1e40af;
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.info-card p {
    color: #1e40af;
    font-size: 14px;
    margin: 0;
}

.books-return-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.return-book-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border: 2px solid #e5e7eb;
    display: flex;
    gap: 20px;
    transition: all 0.3s ease;
}

.return-book-card:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.return-book-card.overdue {
    border-color: #fca5a5;
    background: linear-gradient(to right, #fff5f5 0%, white 10%);
}

.return-book-cover {
    width: 100px;
    height: 140px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.return-book-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.return-cover-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 40px;
}

.return-book-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.return-book-title {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.return-book-author {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 16px;
}

.return-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.return-detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.return-detail-label {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    font-weight: 600;
}

.return-detail-value {
    font-size: 14px;
    color: #374151;
    font-weight: 600;
}

.status-badge-return {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
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
    background: #fef3c7;
    border: 2px solid #fbbf24;
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 16px;
}

.fine-warning-label {
    font-size: 12px;
    color: #92400e;
    font-weight: 600;
    margin-bottom: 4px;
}

.fine-warning-amount {
    font-size: 20px;
    font-weight: 800;
    color: #b91c1c;
}

.fine-warning-details {
    font-size: 12px;
    color: #92400e;
    margin-top: 4px;
}

.return-actions {
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid #f3f4f6;
}

.btn-return-student {
    padding: 12px 28px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-return-student:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.empty-state-student-return {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 20px;
    border: 2px dashed #e5e7eb;
}

.empty-icon-student {
    font-size: 80px;
    margin-bottom: 20px;
}

.empty-state-student-return h3 {
    font-size: 24px;
    color: #111827;
    margin-bottom: 8px;
}

.empty-state-student-return p {
    color: #6b7280;
    font-size: 16px;
    margin-bottom: 24px;
}

.btn-browse {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 28px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-browse:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

@media (max-width: 768px) {
    .return-book-card {
        flex-direction: column;
    }
    
    .return-book-cover {
        width: 100%;
        height: 200px;
    }
}
</style>

<div class="student-returns-page">
    <!-- Header -->
    <div class="returns-header-student">
        <h1>📥 Return Books</h1>
        <p>Return your borrowed books and check for any fines</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-student-return alert-<?php echo $messageType; ?>">
            <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✓' : '⚠️'; ?></span>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <!-- Info Card -->
    <div class="info-card">
        <h3>ℹ️ How to Return Books</h3>
        <p>You can return your borrowed books using this page. If the book is overdue, you'll need to pay the fine at the library counter. Click the "Return Book" button below to process your return.</p>
    </div>

    <!-- Borrowed Books List -->
    <?php if (empty($borrowedBooks)): ?>
        <div class="empty-state-student-return">
            <div class="empty-icon-student">📚</div>
            <h3>No borrowed books</h3>
            <p>You don't have any books to return at the moment</p>
            <a href="index.php?page=books_student" class="btn-browse">Browse Books</a>
        </div>
    <?php else: ?>
        <div class="books-return-grid">
            <?php foreach ($borrowedBooks as $book): ?>
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
                ?>
                
                <div class="return-book-card <?php echo $isOverdue ? 'overdue' : ''; ?>">
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
                        <div class="return-details">
                            <div class="return-detail-item">
                                <div class="return-detail-label">Loan Date</div>
                                <div class="return-detail-value"><?php echo $loanDate; ?></div>
                            </div>

                            <div class="return-detail-item">
                                <div class="return-detail-label">Due Date</div>
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
                                <div class="return-detail-label">ISBN</div>
                                <div class="return-detail-value"><?php echo htmlspecialchars($book['isbn']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Fine Warning -->
                        <?php if ($isOverdue && $fine > 0): ?>
                            <div class="fine-warning">
                                <div class="fine-warning-label">⚠️ OVERDUE FINE</div>
                                <div class="fine-warning-amount">$<?php echo number_format($fine, 2); ?></div>
                                <div class="fine-warning-details">
                                    <?php echo $daysOverdue; ?> day(s) overdue × $<?php echo number_format($FINE_PER_DAY, 2); ?>/day<br>
                                    Please pay at the library counter
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