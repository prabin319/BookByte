<?php
// pages/loans/active_loans.php - ENHANCED WITH RETURN FUNCTIONALITY

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

$user = currentUser();
$role = $user['role'] ?? '';
$isStaff = in_array($role, ['ADMIN', 'LIBRARIAN'], true);

if (!$isStaff) {
    requireLogin();
}

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
            $notifMessage = "'{$loan['title']}' has been returned successfully.";
            
            if ($fine > 0) {
                $notifMessage .= " Overdue fine: $" . number_format($fine, 2) . " ({$daysOverdue} days × $" . number_format($FINE_PER_DAY, 2) . "/day).";
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
            $message .= " Overdue Fine: $" . number_format($fine, 2) . " ({$daysOverdue} days overdue)";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $messageType = 'error';
        $message = $e->getMessage();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $messageType = 'error';
        $message = 'Error processing return.';
    }
}

// Load loans with search - FIXED to show all loans by default
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterState = isset($_GET['state']) ? trim($_GET['state']) : '';

$activeLoans = [];
try {
    $sql = "
        SELECT 
            l.id,
            l.loan_date,
            l.due_date,
            l.returned_at,
            l.renewal_count,
            l.book_id,
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
        WHERE 1=1
    ";
    
    // Apply filters
    if ($filterState === 'active') {
        $sql .= " AND (l.returned_at IS NULL OR l.returned_at = '0000-00-00' OR l.returned_at = '0000-00-00 00:00:00')";
    } elseif ($filterState === 'overdue') {
        $sql .= " AND (l.returned_at IS NULL OR l.returned_at = '0000-00-00' OR l.returned_at = '0000-00-00 00:00:00') AND l.due_date < CURDATE()";
    } elseif ($filterState === 'returned') {
        $sql .= " AND l.returned_at IS NOT NULL AND l.returned_at != '0000-00-00' AND l.returned_at != '0000-00-00 00:00:00'";
    }
    
    if ($search !== '') {
        $sql .= " AND (
            u.full_name LIKE :search OR 
            u.email LIKE :search OR 
            b.title LIKE :search OR 
            b.isbn LIKE :search
        )";
    }
    
    $sql .= " ORDER BY 
        CASE 
            WHEN l.returned_at IS NULL OR l.returned_at = '0000-00-00' OR l.returned_at = '0000-00-00 00:00:00' THEN 0 
            ELSE 1 
        END,
        l.due_date ASC
        LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    
    if ($search !== '') {
        $stmt->execute(['search' => "%{$search}%"]);
    } else {
        $stmt->execute();
    }
    
    $activeLoans = $stmt->fetchAll();
    
    // Calculate fines for each loan
    foreach ($activeLoans as &$loan) {
        $loan['loan_date'] = $loan['loan_date'] ?? $loan['borrow_date'] ?? $loan['borrowed_date'] ?? null;
        $loan['due_date'] = $loan['due_date'] ?? $loan['return_due_date'] ?? null;
        $loan['returned_at'] = $loan['returned_at'] ?? $loan['return_date'] ?? $loan['returned_date'] ?? null;
        
        $returned = $loan['returned_at'];
        $loan['is_active'] = ($returned === null || $returned === '0000-00-00' || $returned === '0000-00-00 00:00:00');
        
        $loan['is_overdue'] = false;
        $loan['days_overdue'] = 0;
        $loan['fine'] = 0;
        
        if ($loan['is_active'] && $loan['due_date'] && strtotime($loan['due_date']) < time()) {
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
    'total_active' => 0,
    'overdue' => 0,
    'due_today' => 0,
    'returned_today' => 0,
    'total_fines' => 0
];

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM loans 
        WHERE returned_at IS NULL OR returned_at = '0000-00-00 00:00:00'
    ");
    $stats['total_active'] = (int)($stmt->fetch()['cnt'] ?? 0);
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM loans 
        WHERE (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')
        AND due_date < CURDATE()
    ");
    $stats['overdue'] = (int)($stmt->fetch()['cnt'] ?? 0);
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM loans 
        WHERE (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')
        AND DATE(due_date) = CURDATE()
    ");
    $stats['due_today'] = (int)($stmt->fetch()['cnt'] ?? 0);
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM loans 
        WHERE DATE(returned_at) = CURDATE()
    ");
    $stats['returned_today'] = (int)($stmt->fetch()['cnt'] ?? 0);
    
    // Calculate total fines from overdue loans
    foreach ($activeLoans as $loan) {
        if ($loan['is_overdue']) {
            $stats['total_fines'] += $loan['fine'];
        }
    }
    
} catch (PDOException $e) {
    // Keep defaults
}
?>

<style>
.borrow-return-page {
    max-width: 1600px;
    margin: 0 auto;
}

.page-header-combined {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.page-header-combined h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
}

.page-header-combined p {
    font-size: 15px;
    opacity: 0.95;
    margin: 0;
}

.alert-combined {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 15px;
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

.stats-grid-combined {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card-combined {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.stat-card-combined:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon-combined {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.icon-blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.icon-red { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.icon-yellow { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
.icon-green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }

.stat-value-combined {
    font-size: 32px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
}

.stat-label-combined {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.toolbar-combined {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.toolbar-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.toolbar-left {
    display: flex;
    gap: 12px;
    flex: 1;
}

.search-group {
    flex: 1;
    min-width: 250px;
}

.search-input-combined {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
}

.search-input-combined:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.filter-select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    background: white;
    min-width: 150px;
}

.toolbar-actions {
    display: flex;
    gap: 8px;
}

.btn-toolbar {
    padding: 12px 20px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-apply {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-reset {
    background: #e5e7eb;
    color: #374151;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.loans-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.loan-card-combined {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border: 2px solid #e5e7eb;
    display: flex;
    gap: 20px;
    transition: all 0.3s ease;
}

.loan-card-combined:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.loan-card-combined.overdue {
    border-color: #fca5a5;
    background: linear-gradient(to right, #fff5f5 0%, white 10%);
}

.loan-card-combined.returned {
    opacity: 0.7;
    border-color: #d1d5db;
}

.loan-book-cover {
    width: 100px;
    height: 140px;
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
    font-size: 40px;
}

.loan-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.loan-book-title {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.loan-book-author {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 16px;
}

.loan-borrower {
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

.loan-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.loan-detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label-combined {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    font-weight: 600;
}

.detail-value-combined {
    font-size: 14px;
    color: #374151;
    font-weight: 600;
}

.status-badge-combined {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    width: fit-content;
}

.status-active {
    background: #dbeafe;
    color: #1e40af;
}

.status-overdue-badge {
    background: #fee2e2;
    color: #b91c1c;
}

.status-returned-badge {
    background: #dcfce7;
    color: #166534;
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

.loan-actions {
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

.empty-state-combined {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 20px;
    border: 2px dashed #e5e7eb;
}

.empty-icon-combined {
    font-size: 80px;
    margin-bottom: 20px;
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

@media (max-width: 768px) {
    .loan-card-combined {
        flex-direction: column;
    }
    
    .loan-book-cover {
        width: 100%;
        height: 200px;
    }
    
    .toolbar-form {
        flex-direction: column;
    }
    
    .toolbar-left {
        flex-direction: column;
    }
}
</style>

<div class="borrow-return-page">
    <!-- Header -->
    <div class="page-header-combined">
        <h1>🔄 Borrow & Return</h1>
        <p>Manage book loans, process returns, and track overdue items</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-combined alert-<?php echo $messageType; ?>">
            <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✓' : '⚠️'; ?></span>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid-combined">
        <div class="stat-card-combined">
            <div class="stat-icon-combined icon-blue">📚</div>
            <div class="stat-value-combined"><?php echo $stats['total_active']; ?></div>
            <div class="stat-label-combined">Active Loans</div>
        </div>

        <div class="stat-card-combined">
            <div class="stat-icon-combined icon-red">⚠️</div>
            <div class="stat-value-combined"><?php echo $stats['overdue']; ?></div>
            <div class="stat-label-combined">Overdue</div>
        </div>

        <div class="stat-card-combined">
            <div class="stat-icon-combined icon-yellow">⏰</div>
            <div class="stat-value-combined"><?php echo $stats['due_today']; ?></div>
            <div class="stat-label-combined">Due Today</div>
        </div>

        <div class="stat-card-combined">
            <div class="stat-icon-combined icon-green">✅</div>
            <div class="stat-value-combined"><?php echo $stats['returned_today']; ?></div>
            <div class="stat-label-combined">Returned Today</div>
        </div>
    </div>

    <!-- Search & Filter Toolbar -->
    <div class="toolbar-combined">
        <form method="get" action="index.php" class="toolbar-form">
            <input type="hidden" name="page" value="loans_active">
            
            <div class="toolbar-left">
                <div class="search-group">
                    <input 
                        type="text" 
                        name="q" 
                        class="search-input-combined" 
                        placeholder="Search by student name, email, book title, or ISBN..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>
                
                <select name="state" class="filter-select">
                    <option value="">All Loans</option>
                    <option value="active" <?php echo $filterState === 'active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="overdue" <?php echo $filterState === 'overdue' ? 'selected' : ''; ?>>Overdue Only</option>
                    <option value="returned" <?php echo $filterState === 'returned' ? 'selected' : ''; ?>>Returned</option>
                </select>
            </div>
            
            <div class="toolbar-actions">
                <button type="submit" class="btn-toolbar btn-apply">Apply</button>
                <a href="index.php?page=loans_active" class="btn-toolbar btn-reset">Reset</a>
            </div>
        </form>
    </div>

    <!-- Loans List -->
    <?php if (empty($activeLoans)): ?>
        <div class="empty-state-combined">
            <div class="empty-icon-combined">📚</div>
            <h3>No loans found</h3>
            <p>Try adjusting your search or filters</p>
        </div>
    <?php else: ?>
        <div class="loans-grid">
            <?php foreach ($activeLoans as $loan): ?>
                <?php
                $bookTitle = $loan['book_title'] ?? 'Unknown Book';
                $bookAuthor = $loan['book_author'] ?? 'Unknown Author';
                $bookCover = $loan['cover_url'] ?? null;
                $loanDate = $loan['loan_date'] ? date('M d, Y', strtotime($loan['loan_date'])) : 'N/A';
                $dueDate = $loan['due_date'] ? date('M d, Y', strtotime($loan['due_date'])) : 'N/A';
                $isActive = $loan['is_active'];
                $isOverdue = $loan['is_overdue'];
                $fine = $loan['fine'];
                $daysOverdue = $loan['days_overdue'];
                
                // Status
                if (!$isActive) {
                    $status = 'Returned';
                    $statusClass = 'status-returned-badge';
                    $statusIcon = '✓';
                    $cardClass = 'returned';
                } elseif ($isOverdue) {
                    $status = 'Overdue';
                    $statusClass = 'status-overdue-badge';
                    $statusIcon = '⚠️';
                    $cardClass = 'overdue';
                } else {
                    $status = 'Active';
                    $statusClass = 'status-active';
                    $statusIcon = '📖';
                    $cardClass = '';
                }
                ?>
                
                <div class="loan-card-combined <?php echo $cardClass; ?>">
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

                        <!-- Borrower Info -->
                        <div class="loan-borrower">
                            <div class="borrower-label">Borrowed By</div>
                            <div class="borrower-name"><?php echo htmlspecialchars($loan['full_name']); ?></div>
                            <div class="borrower-email"><?php echo htmlspecialchars($loan['email']); ?></div>
                        </div>

                        <!-- Details Grid -->
                        <div class="loan-details-grid">
                            <div class="loan-detail-item">
                                <div class="detail-label-combined">Loan Date</div>
                                <div class="detail-value-combined"><?php echo $loanDate; ?></div>
                            </div>

                            <div class="loan-detail-item">
                                <div class="detail-label-combined">Due Date</div>
                                <div class="detail-value-combined"><?php echo $dueDate; ?></div>
                            </div>

                            <div class="loan-detail-item">
                                <div class="detail-label-combined">Status</div>
                                <div class="status-badge-combined <?php echo $statusClass; ?>">
                                    <span><?php echo $statusIcon; ?></span>
                                    <span><?php echo $status; ?></span>
                                </div>
                            </div>

                            <?php if ($loan['isbn']): ?>
                            <div class="loan-detail-item">
                                <div class="detail-label-combined">ISBN</div>
                                <div class="detail-value-combined"><?php echo htmlspecialchars($loan['isbn']); ?></div>
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
                        <?php if ($isActive): ?>
                            <div class="loan-actions">
                                <form method="post" onsubmit="return confirm('Process return for: <?php echo htmlspecialchars($bookTitle); ?>?');">
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
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>