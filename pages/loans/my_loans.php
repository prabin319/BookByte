<?php
// pages/loans/my_loans.php - FIXED VERSION (No Warnings)

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'STUDENT') {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();
$userId = $user['id'];

// System Settings
$FINE_PER_DAY = 5.00;
$MAX_RENEWALS = 2;
$RENEWAL_DAYS = 14;

$message = '';
$messageType = '';

// Handle Renewal Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_loan_id'])) {
    $loanId = (int)$_POST['renew_loan_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, b.title 
            FROM loans l
            JOIN books b ON b.id = l.book_id
            WHERE l.id = :loan_id AND l.user_id = :user_id
        ");
        $stmt->execute(['loan_id' => $loanId, 'user_id' => $userId]);
        $loan = $stmt->fetch();
        
        if ($loan) {
            $renewalCount = (int)($loan['renewal_count'] ?? 0);
            $dueDate = $loan['due_date'] ?? null;
            $isReturned = !empty($loan['returned_at']) && $loan['returned_at'] !== '0000-00-00 00:00:00';
            $isOverdue = $dueDate ? (strtotime($dueDate) < strtotime('today')) : false;
            
            if ($isReturned) {
                $messageType = 'error';
                $message = 'This book has already been returned.';
            } elseif ($renewalCount >= $MAX_RENEWALS) {
                $messageType = 'error';
                $message = 'Maximum renewal limit reached. Please return the book.';
            } elseif ($isOverdue) {
                $messageType = 'error';
                $message = 'Cannot renew overdue books. Please return the book and pay any fines.';
            } elseif (!$dueDate) {
                $messageType = 'error';
                $message = 'Cannot renew: Due date not set for this loan.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as reserved_count 
                    FROM reservations 
                    WHERE book_id = :book_id AND status = 'PENDING'
                ");
                $stmt->execute(['book_id' => $loan['book_id']]);
                $reservationCheck = $stmt->fetch();
                
                if ($reservationCheck && $reservationCheck['reserved_count'] > 0) {
                    $messageType = 'error';
                    $message = 'This book is reserved by another student. Renewal not allowed.';
                } else {
                    $newDueDate = date('Y-m-d', strtotime($dueDate . " + $RENEWAL_DAYS days"));
                    $newRenewalCount = $renewalCount + 1;
                    
                    $stmt = $pdo->prepare("
                        UPDATE loans 
                        SET due_date = :new_due_date, 
                            renewal_count = :renewal_count,
                            renewed_at = NOW()
                        WHERE id = :loan_id
                    ");
                    $stmt->execute([
                        'new_due_date' => $newDueDate,
                        'renewal_count' => $newRenewalCount,
                        'loan_id' => $loanId
                    ]);
                    
                    $messageType = 'success';
                    $message = "Book renewed successfully! New due date: " . date('M d, Y', strtotime($newDueDate));
                }
            }
        } else {
            $messageType = 'error';
            $message = 'Loan not found.';
        }
    } catch (PDOException $e) {
        $messageType = 'error';
        $message = 'Error processing renewal. Please try again.';
    }
}

// Load user's loans
$loans = [];
try {
    $sql = "
        SELECT 
            l.*,
            b.title AS book_title,
            b.author AS book_author,
            b.isbn AS book_isbn,
            b.cover_url AS book_cover
        FROM loans l
        LEFT JOIN books b ON b.id = l.book_id
        WHERE l.user_id = :uid
        ORDER BY l.id DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $userId]);
    $loans = $stmt->fetchAll();
} catch (PDOException $e) {
    $loans = [];
}

// Calculate fines and stats
$activeCount = 0;
$overdueCount = 0;
$totalFines = 0;

foreach ($loans as &$loan) {
    // Initialize all variables with defaults
    $loan['loan_date'] = $loan['loan_date'] ?? $loan['borrow_date'] ?? $loan['borrowed_date'] ?? null;
    $loan['due_date'] = $loan['due_date'] ?? $loan['return_due_date'] ?? null;
    $loan['returned_at'] = $loan['returned_at'] ?? $loan['return_date'] ?? $loan['returned_date'] ?? null;
    
    $returned = $loan['returned_at'];
    $isActive = ($returned === null || $returned === '0000-00-00' || $returned === '0000-00-00 00:00:00');
    
    $loan['fine'] = 0;
    $loan['is_overdue'] = false;
    $loan['days_overdue'] = 0;
    $loan['days_until_due'] = 0;
    $loan['renewal_count'] = (int)($loan['renewal_count'] ?? 0);
    
    if ($isActive) {
        $activeCount++;
        
        $dueDate = $loan['due_date'];
        if ($dueDate && $dueDate !== '0000-00-00') {
            $dueDateTimestamp = strtotime($dueDate);
            $todayTimestamp = strtotime('today');
            $daysDiff = floor(($dueDateTimestamp - $todayTimestamp) / 86400);
            
            if ($daysDiff < 0) {
                // Overdue
                $loan['is_overdue'] = true;
                $daysOverdue = abs($daysDiff);
                $loan['days_overdue'] = $daysOverdue;
                $loan['fine'] = $daysOverdue * $FINE_PER_DAY;
                $overdueCount++;
                $totalFines += $loan['fine'];
            } else {
                // Not overdue yet
                $loan['days_until_due'] = $daysDiff;
            }
        }
    }
    
    $loan['can_renew'] = $isActive && !$loan['is_overdue'] && $loan['renewal_count'] < $MAX_RENEWALS && $loan['due_date'];
}
unset($loan);
?>

<style>
/* Enhanced My Loans Styles */
.my-loans-enhanced {
    max-width: 1400px;
    margin: 0 auto;
}

.loans-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.loans-header h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
}

.loans-header p {
    font-size: 15px;
    opacity: 0.95;
    margin: 0;
}

/* Alert Messages */
.alert-message {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 15px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
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

/* Stats Grid */
.stats-grid-loans {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card-loan {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.stat-card-loan:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon-loan {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.stat-icon-blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-icon-red { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-icon-green { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-icon-yellow { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); }

.stat-value-loan {
    font-size: 32px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
}

.stat-label-loan {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

/* Loans Cards */
.loans-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.loan-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.loan-card:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.loan-card.overdue {
    border-color: #fca5a5;
    background: linear-gradient(to right, #fff5f5 0%, white 10%);
}

.loan-card-content {
    display: flex;
    gap: 20px;
    padding: 20px;
}

.loan-book-cover {
    width: 120px;
    height: 160px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.loan-book-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.loan-cover-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
}

.loan-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.loan-book-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 4px 0;
}

.loan-book-author {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 16px;
}

/* Due Date Section */
.loan-due-date-section {
    background: #f9fafb;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    border: 2px solid #e5e7eb;
}

.loan-due-date-section.due-soon {
    background: #fef3c7;
    border-color: #fbbf24;
}

.loan-due-date-section.overdue {
    background: #fee2e2;
    border-color: #ef4444;
}

.due-date-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.due-date-label {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    color: #6b7280;
    letter-spacing: 0.5px;
}

.due-date-value {
    font-size: 24px;
    font-weight: 800;
    color: #111827;
}

.due-date-countdown {
    font-size: 14px;
    font-weight: 600;
    margin-top: 4px;
}

.due-date-countdown.urgent {
    color: #dc2626;
}

.due-date-countdown.warning {
    color: #f59e0b;
}

.due-date-countdown.safe {
    color: #10b981;
}

.loan-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.loan-detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 12px;
    color: #9ca3af;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.detail-value {
    font-size: 15px;
    color: #374151;
    font-weight: 600;
}

.status-badge-loan {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    width: fit-content;
}

.status-active {
    background: #dbeafe;
    color: #1e40af;
}

.status-overdue {
    background: #fee2e2;
    color: #b91c1c;
}

.status-returned {
    background: #dcfce7;
    color: #166534;
}

.fine-warning {
    background: #fef3c7;
    border: 2px solid #fbbf24;
    border-radius: 12px;
    padding: 12px 16px;
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.fine-icon {
    font-size: 24px;
}

.fine-details {
    flex: 1;
}

.fine-title {
    font-size: 14px;
    font-weight: 700;
    color: #92400e;
    margin-bottom: 4px;
}

.fine-amount {
    font-size: 20px;
    font-weight: 800;
    color: #b91c1c;
}

.loan-actions {
    display: flex;
    gap: 12px;
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid #f3f4f6;
    flex-wrap: wrap;
}

.btn-renew {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-renew:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.btn-renew:disabled {
    background: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.renewal-info {
    font-size: 12px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Empty State */
.empty-state-loans {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 20px;
    border: 2px dashed #e5e7eb;
}

.empty-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.empty-state-loans h3 {
    font-size: 24px;
    color: #111827;
    margin-bottom: 8px;
}

.empty-state-loans p {
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

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .loan-card-content {
        flex-direction: column;
    }
    
    .loan-book-cover {
        width: 100%;
        height: 240px;
    }
    
    .loan-details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="my-loans-enhanced">
    <!-- Header -->
    <div class="loans-header">
        <h1>📚 My Loans</h1>
        <p>View and manage your borrowed books</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-message alert-<?php echo $messageType; ?>">
            <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✓' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid-loans">
        <div class="stat-card-loan">
            <div class="stat-icon-loan stat-icon-blue">📖</div>
            <div class="stat-value-loan"><?php echo $activeCount; ?></div>
            <div class="stat-label-loan">Active Loans</div>
        </div>

        <div class="stat-card-loan">
            <div class="stat-icon-loan stat-icon-red">⚠️</div>
            <div class="stat-value-loan"><?php echo $overdueCount; ?></div>
            <div class="stat-label-loan">Overdue Books</div>
        </div>

        <div class="stat-card-loan">
            <div class="stat-icon-loan stat-icon-yellow">💰</div>
            <div class="stat-value-loan">$<?php echo number_format($totalFines, 2); ?></div>
            <div class="stat-label-loan">Total Fines</div>
        </div>

        <div class="stat-card-loan">
            <div class="stat-icon-loan stat-icon-green">✅</div>
            <div class="stat-value-loan"><?php echo count($loans); ?></div>
            <div class="stat-label-loan">Total History</div>
        </div>
    </div>

    <!-- Loans List -->
    <?php if (empty($loans)): ?>
        <div class="empty-state-loans">
            <div class="empty-icon">📚</div>
            <h3>No loans yet</h3>
            <p>You haven't borrowed any books. Start exploring our collection!</p>
            <a href="index.php?page=books_student" class="btn-browse">Browse Books</a>
        </div>
    <?php else: ?>
        <div class="loans-list">
            <?php foreach ($loans as $loan): ?>
                <?php
                $bookTitle = $loan['book_title'] ?? 'Unknown Book';
                $bookAuthor = $loan['book_author'] ?? 'Unknown Author';
                $bookCover = $loan['book_cover'] ?? null;
                $loanDate = $loan['loan_date'] ? date('M d, Y', strtotime($loan['loan_date'])) : 'N/A';
                $dueDate = $loan['due_date'];
                $returned = $loan['returned_at'];
                $renewalCount = $loan['renewal_count'];
                $renewalsLeft = $MAX_RENEWALS - $renewalCount;
                
                $isActive = ($returned === null || $returned === '0000-00-00' || $returned === '0000-00-00 00:00:00');
                $isOverdue = $loan['is_overdue'];
                $fine = $loan['fine'];
                $daysOverdue = $loan['days_overdue'];
                $daysUntilDue = $loan['days_until_due'];
                $canRenew = $loan['can_renew'];
                
                // Status
                if (!$isActive) {
                    $status = 'Returned';
                    $statusClass = 'status-returned';
                    $statusIcon = '✓';
                } elseif ($isOverdue) {
                    $status = 'Overdue';
                    $statusClass = 'status-overdue';
                    $statusIcon = '⚠️';
                } else {
                    $status = 'Active';
                    $statusClass = 'status-active';
                    $statusIcon = '📖';
                }
                
                // Due date section styling
                $dueSectionClass = '';
                $countdownClass = 'safe';
                $countdownText = '';
                
                if ($isActive && $dueDate && $dueDate !== '0000-00-00') {
                    if ($isOverdue) {
                        $dueSectionClass = 'overdue';
                        $countdownClass = 'urgent';
                        $countdownText = "⚠️ Overdue by {$daysOverdue} day" . ($daysOverdue != 1 ? 's' : '');
                    } elseif ($daysUntilDue <= 3) {
                        $dueSectionClass = 'due-soon';
                        $countdownClass = 'warning';
                        $countdownText = "⏰ Due in {$daysUntilDue} day" . ($daysUntilDue != 1 ? 's' : '');
                    } else {
                        $countdownClass = 'safe';
                        $countdownText = "✓ {$daysUntilDue} days remaining";
                    }
                }
                ?>
                
                <div class="loan-card <?php echo $isOverdue ? 'overdue' : ''; ?>">
                    <div class="loan-card-content">
                        <!-- Book Cover -->
                        <div class="loan-book-cover">
                            <?php if ($bookCover): ?>
                                <img src="<?php echo htmlspecialchars($bookCover); ?>" alt="<?php echo htmlspecialchars($bookTitle); ?>">
                            <?php else: ?>
                                <div class="loan-cover-placeholder">📖</div>
                            <?php endif; ?>
                        </div>

                        <!-- Loan Info -->
                        <div class="loan-info">
                            <h3 class="loan-book-title"><?php echo htmlspecialchars($bookTitle); ?></h3>
                            <div class="loan-book-author">by <?php echo htmlspecialchars($bookAuthor); ?></div>

                            <!-- Due Date Display -->
                            <?php if ($isActive && $dueDate && $dueDate !== '0000-00-00'): ?>
                                <div class="loan-due-date-section <?php echo $dueSectionClass; ?>">
                                    <div class="due-date-header">
                                        <span class="due-date-label">📅 Return By</span>
                                        <div class="status-badge-loan <?php echo $statusClass; ?>">
                                            <span><?php echo $statusIcon; ?></span>
                                            <span><?php echo $status; ?></span>
                                        </div>
                                    </div>
                                    <div class="due-date-value"><?php echo date('M d, Y', strtotime($dueDate)); ?></div>
                                    <div class="due-date-countdown <?php echo $countdownClass; ?>">
                                        <?php echo $countdownText; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="loan-details-grid">
                                <div class="loan-detail-item">
                                    <div class="detail-label">Loan Date</div>
                                    <div class="detail-value"><?php echo $loanDate; ?></div>
                                </div>

                                <?php if (!$isActive): ?>
                                <div class="loan-detail-item">
                                    <div class="detail-label">Status</div>
                                    <div class="status-badge-loan <?php echo $statusClass; ?>">
                                        <span><?php echo $statusIcon; ?></span>
                                        <span><?php echo $status; ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($renewalCount > 0): ?>
                                <div class="loan-detail-item">
                                    <div class="detail-label">Renewals</div>
                                    <div class="detail-value"><?php echo $renewalCount; ?>/<?php echo $MAX_RENEWALS; ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Fine Warning -->
                            <?php if ($isOverdue && $fine > 0): ?>
                                <div class="fine-warning">
                                    <div class="fine-icon">💰</div>
                                    <div class="fine-details">
                                        <div class="fine-title">Overdue Fine</div>
                                        <div class="fine-amount">$<?php echo number_format($fine, 2); ?></div>
                                        <div style="font-size: 12px; color: #92400e; margin-top: 4px;">
                                            <?php echo $daysOverdue; ?> day(s) overdue × $<?php echo number_format($FINE_PER_DAY, 2); ?>/day
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Actions -->
                            <?php if ($isActive): ?>
                                <div class="loan-actions">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="renew_loan_id" value="<?php echo $loan['id']; ?>">
                                        <button type="submit" class="btn-renew" <?php echo !$canRenew ? 'disabled' : ''; ?>>
                                            <span>🔄</span>
                                            <?php echo $canRenew ? 'Renew Loan' : 'Cannot Renew'; ?>
                                        </button>
                                    </form>
                                    
                                    <?php if ($canRenew && $renewalsLeft > 0): ?>
                                        <div class="renewal-info">
                                            <span>ℹ️</span>
                                            <span><?php echo $renewalsLeft; ?> renewal(s) left</span>
                                        </div>
                                    <?php elseif (!$canRenew && $isOverdue): ?>
                                        <div class="renewal-info" style="color: #b91c1c;">
                                            <span>⚠️</span>
                                            <span>Return book to clear fine</span>
                                        </div>
                                    <?php elseif ($renewalCount >= $MAX_RENEWALS): ?>
                                        <div class="renewal-info" style="color: #f59e0b;">
                                            <span>⚠️</span>
                                            <span>Max renewals reached</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>