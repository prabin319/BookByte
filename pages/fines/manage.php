<?php
// pages/fines/manage.php - UC08: Manage Fines

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin();

$user = currentUser();
$role = $user['role'] ?? '';
$isAdmin = in_array($role, ['ADMIN', 'LIBRARIAN'], true);

$pdo = getDBConnection();

$message = '';
$messageType = '';

// Settings
$FINE_PER_DAY = 5.00;

// Handle fine payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'record_payment' && $isAdmin) {
        $loanId = (int)$_POST['loan_id'];
        $amount = (float)$_POST['amount'];
        $paymentMethod = $_POST['payment_method'];
        
        try {
            // Record fine payment
            $stmt = $pdo->prepare("
                INSERT INTO fine_payments (loan_id, amount, payment_method, collected_by, payment_date)
                VALUES (:loan_id, :amount, :payment_method, :collected_by, NOW())
            ");
            $stmt->execute([
                'loan_id' => $loanId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'collected_by' => $user['id']
            ]);
            
            $messageType = 'success';
            $message = "Payment of $" . number_format($amount, 2) . " recorded successfully!";
        } catch (PDOException $e) {
            $messageType = 'error';
            $message = 'Error recording payment.';
        }
    }
    
    elseif ($_POST['action'] === 'waive_fine' && $isAdmin) {
        $loanId = (int)$_POST['loan_id'];
        $reason = trim($_POST['reason']);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO fine_waivers (loan_id, waived_by, reason, waived_at)
                VALUES (:loan_id, :waived_by, :reason, NOW())
            ");
            $stmt->execute([
                'loan_id' => $loanId,
                'waived_by' => $user['id'],
                'reason' => $reason
            ]);
            
            $messageType = 'success';
            $message = 'Fine waived successfully!';
        } catch (PDOException $e) {
            $messageType = 'error';
            $message = 'Error waiving fine.';
        }
    }
}

// Calculate all fines
$overdueLoans = [];
$totalOutstandingFines = 0;

try {
    $stmt = $pdo->query("
        SELECT 
            l.*,
            u.id as user_id,
            u.full_name,
            u.email,
            b.title as book_title,
            b.isbn,
            COALESCE(SUM(fp.amount), 0) as paid_amount,
            (SELECT COUNT(*) FROM fine_waivers WHERE loan_id = l.id) as is_waived
        FROM loans l
        JOIN users u ON l.user_id = u.id
        JOIN books b ON l.book_id = b.id
        LEFT JOIN fine_payments fp ON l.id = fp.loan_id
        WHERE l.returned_at IS NULL
        AND l.due_date < CURDATE()
        GROUP BY l.id
        ORDER BY l.due_date ASC
    ");
    
    $overdueLoans = $stmt->fetchAll();
    
    foreach ($overdueLoans as &$loan) {
        $daysOverdue = floor((time() - strtotime($loan['due_date'])) / 86400);
        $totalFine = $daysOverdue * $FINE_PER_DAY;
        $paidAmount = (float)$loan['paid_amount'];
        $isWaived = (int)$loan['is_waived'] > 0;
        
        $loan['days_overdue'] = $daysOverdue;
        $loan['total_fine'] = $totalFine;
        $loan['paid_amount'] = $paidAmount;
        $loan['outstanding'] = $isWaived ? 0 : max(0, $totalFine - $paidAmount);
        $loan['is_waived'] = $isWaived;
        
        if (!$isWaived) {
            $totalOutstandingFines += $loan['outstanding'];
        }
    }
    unset($loan);
} catch (PDOException $e) {
    $overdueLoans = [];
}

// Statistics
$stats = [
    'total_overdue' => count($overdueLoans),
    'total_outstanding' => $totalOutstandingFines,
    'paid_today' => 0,
    'waived_count' => 0
];

try {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total FROM fine_payments WHERE DATE(payment_date) = CURDATE()
    ");
    $stats['paid_today'] = (float)($stmt->fetch()['total'] ?? 0);
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM fine_waivers WHERE DATE(waived_at) = CURDATE()
    ");
    $stats['waived_count'] = (int)($stmt->fetch()['cnt'] ?? 0);
} catch (PDOException $e) {
    // Keep defaults
}
?>

<style>
.fines-page {
    max-width: 1600px;
    margin: 0 auto;
}

.fines-header {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(240, 147, 251, 0.3);
}

.fines-header h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
}

.fines-header p {
    font-size: 15px;
    opacity: 0.95;
    margin: 0;
}

.alert-fine {
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

.stats-grid-fines {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card-fine {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.stat-icon-fine {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.icon-red { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.icon-yellow { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
.icon-green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.icon-blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }

.stat-value-fine {
    font-size: 32px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
}

.stat-label-fine {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.fines-table-section {
    background: white;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.fines-table-section h2 {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 20px;
}

.fines-table {
    width: 100%;
    border-collapse: collapse;
}

.fines-table thead {
    background: #f9fafb;
}

.fines-table th {
    padding: 12px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    border-bottom: 2px solid #e5e7eb;
}

.fines-table td {
    padding: 16px 12px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.fine-amount {
    font-size: 18px;
    font-weight: 700;
    color: #ef4444;
}

.fine-paid {
    color: #10b981;
}

.fine-outstanding {
    color: #f59e0b;
}

.fine-waived {
    color: #6b7280;
    text-decoration: line-through;
}

.btn-action-fine {
    padding: 6px 14px;
    border-radius: 8px;
    border: none;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-right: 6px;
}

.btn-pay {
    background: #dcfce7;
    color: #166534;
}

.btn-pay:hover {
    background: #bbf7d0;
}

.btn-waive {
    background: #fef3c7;
    color: #92400e;
}

.btn-waive:hover {
    background: #fde68a;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    padding: 32px;
    max-width: 500px;
    width: 90%;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 20px;
}

.form-group-fine {
    margin-bottom: 16px;
}

.form-label-fine {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

.form-input-fine {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 15px;
}

.form-input-fine:focus {
    outline: none;
    border-color: #667eea;
}

.modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.btn-modal {
    flex: 1;
    padding: 12px 24px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary-modal {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-secondary-modal {
    background: #e5e7eb;
    color: #374151;
}

.empty-state-fines {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon-fines {
    font-size: 64px;
    margin-bottom: 16px;
}
</style>

<div class="fines-page">
    <!-- Header -->
    <div class="fines-header">
        <h1>💰 Fines Management</h1>
        <p>Track, collect, and manage overdue fines</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-fine alert-<?php echo $messageType; ?>">
            <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✓' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid-fines">
        <div class="stat-card-fine">
            <div class="stat-icon-fine icon-red">📚</div>
            <div class="stat-value-fine"><?php echo $stats['total_overdue']; ?></div>
            <div class="stat-label-fine">Overdue Loans</div>
        </div>

        <div class="stat-card-fine">
            <div class="stat-icon-fine icon-yellow">💸</div>
            <div class="stat-value-fine">$<?php echo number_format($stats['total_outstanding'], 2); ?></div>
            <div class="stat-label-fine">Outstanding Fines</div>
        </div>

        <div class="stat-card-fine">
            <div class="stat-icon-fine icon-green">✅</div>
            <div class="stat-value-fine">$<?php echo number_format($stats['paid_today'], 2); ?></div>
            <div class="stat-label-fine">Collected Today</div>
        </div>

        <div class="stat-card-fine">
            <div class="stat-icon-fine icon-blue">📋</div>
            <div class="stat-value-fine"><?php echo $stats['waived_count']; ?></div>
            <div class="stat-label-fine">Waived Today</div>
        </div>
    </div>

    <!-- Fines Table -->
    <div class="fines-table-section">
        <h2>📊 Overdue Loans & Fines</h2>
        
        <?php if (empty($overdueLoans)): ?>
            <div class="empty-state-fines">
                <div class="empty-icon-fines">🎉</div>
                <h3>No overdue books!</h3>
                <p>All books are returned on time</p>
            </div>
        <?php else: ?>
            <table class="fines-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Book</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                        <th>Total Fine</th>
                        <th>Paid</th>
                        <th>Outstanding</th>
                        <?php if ($isAdmin): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdueLoans as $loan): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($loan['full_name']); ?></strong><br>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($loan['email']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($loan['book_title']); ?></strong><br>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($loan['isbn']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($loan['due_date'])); ?></td>
                            <td><span style="color: #ef4444; font-weight: 600;"><?php echo $loan['days_overdue']; ?> days</span></td>
                            <td class="fine-amount">$<?php echo number_format($loan['total_fine'], 2); ?></td>
                            <td class="fine-paid">$<?php echo number_format($loan['paid_amount'], 2); ?></td>
                            <td class="fine-outstanding <?php echo $loan['is_waived'] ? 'fine-waived' : ''; ?>">
                                <?php if ($loan['is_waived']): ?>
                                    <span style="color: #6b7280;">Waived</span>
                                <?php else: ?>
                                    $<?php echo number_format($loan['outstanding'], 2); ?>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td>
                                <?php if (!$loan['is_waived'] && $loan['outstanding'] > 0): ?>
                                    <button class="btn-action-fine btn-pay" onclick="openPaymentModal(<?php echo $loan['id']; ?>, <?php echo $loan['outstanding']; ?>)">
                                        💵 Pay
                                    </button>
                                    <button class="btn-action-fine btn-waive" onclick="openWaiveModal(<?php echo $loan['id']; ?>)">
                                        🔓 Waive
                                    </button>
                                <?php else: ?>
                                    <span style="color: #10b981; font-weight: 600;">✓ Cleared</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <h3 class="modal-title">💵 Record Fine Payment</h3>
        <form method="post" id="paymentForm">
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="loan_id" id="payment_loan_id">
            
            <div class="form-group-fine">
                <label class="form-label-fine">Amount</label>
                <input type="number" name="amount" id="payment_amount" class="form-input-fine" step="0.01" required>
            </div>
            
            <div class="form-group-fine">
                <label class="form-label-fine">Payment Method</label>
                <select name="payment_method" class="form-input-fine" required>
                    <option value="CASH">Cash</option>
                    <option value="CARD">Card</option>
                    <option value="ONLINE">Online Transfer</option>
                    <option value="CHECK">Check</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-modal btn-primary-modal">Record Payment</button>
                <button type="button" class="btn-modal btn-secondary-modal" onclick="closeModal('paymentModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Waive Modal -->
<div id="waiveModal" class="modal">
    <div class="modal-content">
        <h3 class="modal-title">🔓 Waive Fine</h3>
        <form method="post" id="waiveForm">
            <input type="hidden" name="action" value="waive_fine">
            <input type="hidden" name="loan_id" id="waive_loan_id">
            
            <div class="form-group-fine">
                <label class="form-label-fine">Reason for Waiving</label>
                <textarea name="reason" class="form-input-fine" rows="3" required placeholder="Enter reason..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-modal btn-primary-modal">Waive Fine</button>
                <button type="button" class="btn-modal btn-secondary-modal" onclick="closeModal('waiveModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(loanId, amount) {
    document.getElementById('payment_loan_id').value = loanId;
    document.getElementById('payment_amount').value = amount.toFixed(2);
    document.getElementById('paymentModal').classList.add('show');
}

function openWaiveModal(loanId) {
    document.getElementById('waive_loan_id').value = loanId;
    document.getElementById('waiveModal').classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Close modal on background click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});
</script>