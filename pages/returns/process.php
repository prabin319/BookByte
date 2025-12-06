<?php
// pages/returns/process.php - UC03: Return Book

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin();

$user = currentUser();
$role = $user['role'] ?? '';
$isStaff = in_array($role, ['ADMIN', 'LIBRARIAN'], true);

$pdo = getDBConnection();

$message = '';
$messageType = '';
$FINE_PER_DAY = 5.00;

// Handle book return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_loan_id'])) {
    $loanId = (int)$_POST['return_loan_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get loan details
        $stmt = $pdo->prepare("
            SELECT l.*, b.title, b.id as book_id, u.full_name, u.email
            FROM loans l
            JOIN books b ON l.book_id = b.id
            JOIN users u ON l.user_id = u.id
            WHERE l.id = :loan_id
        ");
        $stmt->execute(['loan_id' => $loanId]);
        $loan = $stmt->fetch();
        
        if (!$loan) {
            throw new Exception('Loan not found.');
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
        
        // Update book availability - increment available copies
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
        
        // Create return confirmation notification
        try {
            $notifTitle = "Book Returned Successfully";
            $notifMessage = "You have successfully returned '{$loan['title']}'. Thank you!";
            
            if ($fine > 0) {
                $notifMessage .= " Note: You have an overdue fine of $" . number_format($fine, 2) . " ({$daysOverdue} days × $" . number_format($FINE_PER_DAY, 2) . "/day).";
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, loan_id, type, title, message, sent_via, status, sent_at)
                VALUES (:user_id, :loan_id, 'RETURN_CONFIRMATION', :title, :message, 'APP', 'SENT', NOW())
            ");
            $stmt->execute([
                'user_id' => $loan['user_id'],
                'loan_id' => $loanId,
                'title' => $notifTitle,
                'message' => $notifMessage
            ]);
        } catch (PDOException $e) {
            // Notification is optional, continue
        }
        
        $pdo->commit();
        
        $messageType = 'success';
        $message = "Book '{$loan['title']}' returned successfully!";
        
        if ($fine > 0) {
            $message .= " <br><strong>Overdue Fine: $" . number_format($fine, 2) . "</strong> ({$daysOverdue} days overdue)";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $messageType = 'error';
        $message = $e->getMessage();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $messageType = 'error';
        $message = 'Error processing return: ' . $e->getMessage();
    }
}

// Load active loans based on user role
$activeLoans = [];
$searchQuery = trim($_GET['q'] ?? '');

try {
    if ($isStaff) {
        // Staff can see ALL active loans
        $sql = "
            SELECT 
                l.*,
                u.id as user_id,
                u.full_name,
                u.email,
                u.role as user_role,
                b.title as book_title,
                b.author as book_author,
                b.isbn,
                b.cover_url
            FROM loans l
            JOIN users u ON l.user_id = u.id
            JOIN books b ON l.book_id = b.id
            WHERE (l.returned_at IS NULL OR l.returned_at = '0000-00-00 00:00:00')
        ";
        
        if ($searchQuery !== '') {
            $sql .= " AND (
                u.full_name LIKE :search OR 
                u.email LIKE :search OR 
                b.title LIKE :search OR 
                b.isbn LIKE :search
            )";
        }
        
        $sql .= " ORDER BY l.due_date ASC";
        
        $stmt = $pdo->prepare($sql);
        
        if ($searchQuery !== '') {
            $stmt->execute(['search' => "%{$searchQuery}%"]);
        } else {
            $stmt->execute();
        }
        
        $activeLoans = $stmt->fetchAll();
        
    } else {
        // Students can only see their own loans
        $sql = "
            SELECT 
                l.*,
                u.full_name,
                u.email,
                b.title as book_title,
                b.author as book_author,
                b.isbn,
                b.cover_url
            FROM loans l
            JOIN users u ON l.user_id = u.id
            JOIN books b ON l.book_id = b.id
            WHERE l.user_id = :user_id
            AND (l.returned_at IS NULL OR l.returned_at = '0000-00-00 00:00:00')
            ORDER BY l.due_date ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user['id']]);
        $activeLoans = $stmt->fetchAll();
    }
    
    // Calculate fines for each loan
    foreach ($activeLoans as &$loan) {
        $loan['loan_date'] = $loan['loan_date'] ?? $loan['borrow_date'] ?? $loan['borrowed_date'] ?? null;
        $loan['due_date'] = $loan['due_date'] ?? $loan['return_due_date'] ?? null;
        
        $loan['is_overdue'] = false;
        $loan['days_overdue'] = 0;
        $loan['fine'] = 0;
        
        if ($loan['due_date'] && strtotime($loan['due_date']) < time()) {
            $loan['is_overdue'] = true;
            $loan['days_overdue'] = floor((time() - strtotime($loan['due_date'])) / 86400);
            $loan['fine'] = $loan['days_overdue'] * $FINE_PER_DAY;
        }
    }
    unset($loan);
    
} catch (PDOException $e) {
    $activeLoans = [];
}

// Statistics
$stats = [
    'total_active' => count($activeLoans),
    'overdue' => 0,
    'due_today' => 0,
    'total_fines' => 0
];

foreach ($activeLoans as $loan) {
    if ($loan['is_overdue']) {
        $stats['overdue']++;
        $stats['total_fines'] += $loan['fine'];
    }
    
    if ($loan['due_date'] && date('Y-m-d', strtotime($loan['due_date'])) === date('Y-m-d')) {
        $stats['due_today']++;
    }
}
?>

<style>
.returns-page {
    max-width: 1600px;
    margin: 0 auto;
}

.returns-header {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(79, 172, 254, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-content-returns h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
}

.header-content-returns p {
    font-size: 15px;
    opacity: 0.95;
    margin: 0;
}

.alert-returns {
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

.stats-grid-returns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card-returns {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.stat-card-returns:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon-returns {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.icon-cyan { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.icon-orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.icon-purple { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
.icon-amber { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }

.stat-value-returns {
    font-size: 32px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
}

.stat-label-returns {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.search-section-returns {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.search-form-returns {
    display: flex;
    gap: 12px;
}

.search-input-returns {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
}

.search-input-returns:focus {
    outline: none;
    border-color: #4facfe;
    box-shadow: 0 0 0 4px rgba(79, 172, 254, 0.1);
}

.btn-search-returns {
    padding: 12px 24px;
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-search-returns:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(79, 172, 254, 0.4);
}

.returns-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.return-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border: 2px solid #e5e7eb;
    display: flex;
    gap: 20px;
    transition: all 0.3s ease;
}

.return-card:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.return-card.overdue {
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
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 40px;
}

.return-info {
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

.return-borrower {
    background: #f9fafb;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 16px;
}

.borrower-label {
    font-size: 12px;
    color: #9ca3af;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.borrower-name {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
}

.borrower-email {
    font-size: 13px;
    color: #6b7280;
}

.return-details-grid {
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

.detail-label-returns {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    font-weight: 600;
}

.detail-value-returns {
    font-size: 14px;
    color: #374151;
    font-weight: 600;
}

.status-badge-returns {
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

.status-due-today {
    background: #fef3c7;
    color: #92400e;
}

.status-overdue-badge {
    background: #fee2e2;
    color: #b91c1c;
}

.fine-display {
    background: #fef3c7;
    border: 2px solid #fbbf24;
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 16px;
}

.fine-label {
    font-size: 12px;
    color: #92400e;
    font-weight: 600;
    margin-bottom: 4px;
}

.fine-amount {
    font-size: 24px;
    font-weight: 800;
    color: #b91c1c;
}

.fine-details {
    font-size: 12px;
    color: #92400e;
    margin-top: 4px;
}

.return-actions {
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid #f3f4f6;
}

.btn-return {
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

.btn-return:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.empty-state-returns {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 20px;
    border: 2px dashed #e5e7eb;
}

.empty-icon-returns {
    font-size: 80px;
    margin-bottom: 20px;
}

.empty-state-returns h3 {
    font-size: 24px;
    color: #111827;
    margin-bottom: 8px;
}

.empty-state-returns p {
    color: #6b7280;
    font-size: 16px;
}

@media (max-width: 768px) {
    .return-card {
        flex-direction: column;
    }
    
    .return-book-cover {
        width: 100%;
        height: 200px;
    }
    
    .return-details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="returns-page">
    <!-- Header -->
    <div class="returns-header">
        <div class="header-content-returns">
            <h1>📥 Return Books</h1>
            <p>Process book returns and manage overdue fines</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-returns alert-<?php echo $messageType; ?>">
            <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✓' : '⚠️'; ?></span>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid-returns">
        <div class="stat-card-returns">
            <div class="stat-icon-returns icon-cyan">📚</div>
            <div class="stat-value-returns"><?php echo $stats['total_active']; ?></div>
            <div class="stat-label-returns">Active Loans</div>
        </div>

        <div class="stat-card-returns">
            <div class="stat-icon-returns icon-orange">⚠️</div>
            <div class="stat-value-returns"><?php echo $stats['overdue']; ?></div>
            <div class="stat-label-returns">Overdue</div>
        </div>

        <div class="stat-card-returns">
            <div class="stat-icon-returns icon-purple">⏰</div>
            <div class="stat-value-returns"><?php echo $stats['due_today']; ?></div>
            <div class="stat-label-returns">Due Today</div>
        </div>

        <div class="stat-card-returns">
            <div class="stat-icon-returns icon-amber">💰</div>
            <div class="stat-value-returns">$<?php echo number_format($stats['total_fines'], 2); ?></div>
            <div class="stat-label-returns">Total Fines</div>
        </div>
    </div>

    <!-- Search (for staff only) -->
    <?php if ($isStaff): ?>
    <div class="search-section-returns">
        <form method="get" action="index.php" class="search-form-returns">
            <input type="hidden" name="page" value="returns">
            <input 
                type="text" 
                name="q" 
                class="search-input-returns" 
                placeholder="Search by student name, email, book title, or ISBN..."
                value="<?php echo htmlspecialchars($searchQuery); ?>"
            >
            <button type="submit" class="btn-search-returns">🔍 Search</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Active Loans List -->
    <?php if (empty($activeLoans)): ?>
        <div class="empty-state-returns">
            <div class="empty-icon-returns">🎉</div>
            <h3>No active loans</h3>
            <p><?php echo $isStaff ? 'All books have been returned' : 'You have no books to return'; ?></p>
        </div>
    <?php else: ?>
        <div class="returns-grid">
            <?php foreach ($activeLoans as $loan): ?>
                <?php
                $bookTitle = $loan['book_title'] ?? 'Unknown Book';
                $bookAuthor = $loan['book_author'] ?? 'Unknown Author';
                $bookCover = $loan['cover_url'] ?? null;
                $loanDate = $loan['loan_date'] ? date('M d, Y', strtotime($loan['loan_date'])) : 'N/A';
                $dueDate = $loan['due_date'] ? date('M d, Y', strtotime($loan['due_date'])) : 'N/A';
                $isOverdue = $loan['is_overdue'];
                $daysOverdue = $loan['days_overdue'];
                $fine = $loan['fine'];
                
                // Status
                $isDueToday = $loan['due_date'] && date('Y-m-d', strtotime($loan['due_date'])) === date('Y-m-d');
                
                if ($isOverdue) {
                    $statusLabel = 'Overdue';
                    $statusClass = 'status-overdue-badge';
                    $statusIcon = '⚠️';
                } elseif ($isDueToday) {
                    $statusLabel = 'Due Today';
                    $statusClass = 'status-due-today';
                    $statusIcon = '⏰';
                } else {
                    $statusLabel = 'On Time';
                    $statusClass = 'status-on-time';
                    $statusIcon = '✓';
                }
                ?>
                
                <div class="return-card <?php echo $isOverdue ? 'overdue' : ''; ?>">
                    <!-- Book Cover -->
                    <div class="return-book-cover">
                        <?php if ($bookCover): ?>
                            <img src="<?php echo htmlspecialchars($bookCover); ?>" alt="<?php echo htmlspecialchars($bookTitle); ?>">
                        <?php else: ?>
                            <div class="return-cover-placeholder">📖</div>
                        <?php endif; ?>
                    </div>

                    <!-- Loan Info -->
                    <div class="return-info">
                        <h3 class="return-book-title"><?php echo htmlspecialchars($bookTitle); ?></h3>
                        <div class="return-book-author">by <?php echo htmlspecialchars($bookAuthor); ?></div>

                        <!-- Borrower Info (for staff) -->
                        <?php if ($isStaff): ?>
                        <div class="return-borrower">
                            <div class="borrower-label">Borrowed By</div>
                            <div class="borrower-name"><?php echo htmlspecialchars($loan['full_name']); ?></div>
                            <div class="borrower-email"><?php echo htmlspecialchars($loan['email']); ?></div>
                        </div>
                        <?php endif; ?>

                        <!-- Details Grid -->
                        <div class="return-details-grid">
                            <div class="return-detail-item">
                                <div class="detail-label-returns">Loan Date</div>
                                <div class="detail-value-returns"><?php echo $loanDate; ?></div>
                            </div>

                            <div class="return-detail-item">
                                <div class="detail-label-returns">Due Date</div>
                                <div class="detail-value-returns"><?php echo $dueDate; ?></div>
                            </div>

                            <div class="return-detail-item">
                                <div class="detail-label-returns">Status</div>
                                <div class="status-badge-returns <?php echo $statusClass; ?>">
                                    <span><?php echo $statusIcon; ?></span>
                                    <span><?php echo $statusLabel; ?></span>
                                </div>
                            </div>

                            <?php if ($loan['isbn']): ?>
                            <div class="return-detail-item">
                                <div class="detail-label-returns">ISBN</div>
                                <div class="detail-value-returns"><?php echo htmlspecialchars($loan['isbn']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Fine Display -->
                        <?php if ($isOverdue && $fine > 0): ?>
                            <div class="fine-display">
                                <div class="fine-label">⚠️ OVERDUE FINE</div>
                                <div class="fine-amount">$<?php echo number_format($fine, 2); ?></div>
                                <div class="fine-details">
                                    <?php echo $daysOverdue; ?> day(s) overdue × $<?php echo number_format($FINE_PER_DAY, 2); ?>/day
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Return Button -->
                        <div class="return-actions">
                            <form method="post" onsubmit="return confirm('Confirm book return for: <?php echo htmlspecialchars($bookTitle); ?>?');">
                                <input type="hidden" name="return_loan_id" value="<?php echo $loan['id']; ?>">
                                <button type="submit" class="btn-return">
                                    <span>✅</span>
                                    <span>Process Return</span>
                                    <?php if ($fine > 0): ?>
                                        <span>(+$<?php echo number_format($fine, 2); ?> fine)</span>
                                    <?php endif; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>