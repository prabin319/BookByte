<?php
// pages/library_cards/manage.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin('ADMIN');

$pdo = getDBConnection();
$user = currentUser();
$adminId = $user['id'];

$message = '';
$messageType = '';

// Generate unique card number
function generateCardNumber() {
    $year = date('Y');
    $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    return "LIB-{$year}-{$random}";
}

// Handle card issuance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'issue_card') {
        $userId = (int)$_POST['user_id'];
        $validityMonths = (int)$_POST['validity_months'];
        
        try {
            // Check if user already has an active card
            $stmt = $pdo->prepare("
                SELECT id FROM library_cards 
                WHERE user_id = :user_id AND status = 'ACTIVE'
            ");
            $stmt->execute(['user_id' => $userId]);
            
            if ($stmt->fetch()) {
                $messageType = 'error';
                $message = 'User already has an active library card.';
            } else {
                $cardNumber = generateCardNumber();
                $issueDate = date('Y-m-d');
                $expiryDate = date('Y-m-d', strtotime("+{$validityMonths} months"));
                
                $stmt = $pdo->prepare("
                    INSERT INTO library_cards (card_number, user_id, issue_date, expiry_date, status, issued_by)
                    VALUES (:card_number, :user_id, :issue_date, :expiry_date, 'ACTIVE', :issued_by)
                ");
                
                $stmt->execute([
                    'card_number' => $cardNumber,
                    'user_id' => $userId,
                    'issue_date' => $issueDate,
                    'expiry_date' => $expiryDate,
                    'issued_by' => $adminId
                ]);
                
                $messageType = 'success';
                $message = "Library card {$cardNumber} issued successfully!";
            }
        } catch (PDOException $e) {
            $messageType = 'error';
            $message = 'Error issuing card: ' . $e->getMessage();
        }
    }
    
    elseif ($action === 'update_status') {
        $cardId = (int)$_POST['card_id'];
        $newStatus = $_POST['new_status'];
        
        try {
            $stmt = $pdo->prepare("
                UPDATE library_cards 
                SET status = :status 
                WHERE id = :id
            ");
            $stmt->execute(['status' => $newStatus, 'id' => $cardId]);
            
            $messageType = 'success';
            $message = 'Card status updated successfully!';
        } catch (PDOException $e) {
            $messageType = 'error';
            $message = 'Error updating status.';
        }
    }
    
    elseif ($action === 'renew_card') {
        $cardId = (int)$_POST['card_id'];
        $extensionMonths = (int)$_POST['extension_months'];
        
        try {
            $stmt = $pdo->prepare("SELECT expiry_date FROM library_cards WHERE id = :id");
            $stmt->execute(['id' => $cardId]);
            $card = $stmt->fetch();
            
            if ($card) {
                $currentExpiry = $card['expiry_date'];
                $newExpiry = date('Y-m-d', strtotime($currentExpiry . " +{$extensionMonths} months"));
                
                $stmt = $pdo->prepare("
                    UPDATE library_cards 
                    SET expiry_date = :expiry_date, status = 'ACTIVE' 
                    WHERE id = :id
                ");
                $stmt->execute(['expiry_date' => $newExpiry, 'id' => $cardId]);
                
                $messageType = 'success';
                $message = 'Card renewed successfully!';
            }
        } catch (PDOException $e) {
            $messageType = 'error';
            $message = 'Error renewing card.';
        }
    }
}

// Load all library cards
$cards = [];
try {
    $stmt = $pdo->query("
        SELECT 
            lc.*,
            u.full_name, u.email, u.role,
            CONCAT(admin.full_name) as issued_by_name
        FROM library_cards lc
        LEFT JOIN users u ON lc.user_id = u.id
        LEFT JOIN users admin ON lc.issued_by = admin.id
        ORDER BY lc.created_at DESC
    ");
    $cards = $stmt->fetchAll();
} catch (PDOException $e) {
    $cards = [];
}

// Load students without cards
$studentsWithoutCards = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.full_name, u.email
        FROM users u
        WHERE u.role = 'STUDENT'
        AND u.id NOT IN (
            SELECT user_id FROM library_cards WHERE status = 'ACTIVE'
        )
        ORDER BY u.full_name
    ");
    $studentsWithoutCards = $stmt->fetchAll();
} catch (PDOException $e) {
    $studentsWithoutCards = [];
}
?>

<style>
.library-cards-page {
    max-width: 1400px;
    margin: 0 auto;
}

.page-header-cards {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-content-cards h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
}

.header-content-cards p {
    font-size: 15px;
    opacity: 0.95;
    margin: 0;
}

.btn-issue-card {
    background: white;
    color: #667eea;
    padding: 12px 28px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-issue-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
}

.alert-card {
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

.issue-card-form {
    background: white;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 32px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.issue-card-form h2 {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 16px;
    align-items: end;
}

.form-group-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label-card {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.form-select-card {
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    background: #f9fafb;
    transition: all 0.3s ease;
}

.form-select-card:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.btn-issue {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-issue:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 24px;
}

.library-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border: 2px solid #e5e7eb;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.library-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 8px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
}

.library-card.status-expired::before {
    background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
}

.library-card.status-suspended::before {
    background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
}

.library-card.status-lost::before {
    background: linear-gradient(90deg, #6b7280 0%, #4b5563 100%);
}

.library-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 16px;
}

.card-number {
    font-size: 18px;
    font-weight: 800;
    color: #667eea;
    font-family: 'Courier New', monospace;
}

.card-status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-active {
    background: #dcfce7;
    color: #166534;
}

.badge-expired {
    background: #fee2e2;
    color: #b91c1c;
}

.badge-suspended {
    background: #fef3c7;
    color: #92400e;
}

.badge-lost {
    background: #f3f4f6;
    color: #4b5563;
}

.card-holder {
    margin-bottom: 16px;
}

.holder-name {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.holder-email {
    font-size: 13px;
    color: #6b7280;
}

.card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 16px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    font-weight: 600;
}

.detail-value {
    font-size: 14px;
    color: #374151;
    font-weight: 600;
}

.card-actions {
    display: flex;
    gap: 8px;
}

.btn-action-card {
    flex: 1;
    padding: 8px 12px;
    border-radius: 8px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-renew {
    background: #dbeafe;
    color: #1e40af;
}

.btn-renew:hover {
    background: #bfdbfe;
}

.btn-suspend {
    background: #fef3c7;
    color: #92400e;
}

.btn-suspend:hover {
    background: #fde68a;
}

.btn-activate {
    background: #dcfce7;
    color: #166534;
}

.btn-activate:hover {
    background: #bbf7d0;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    border: 2px dashed #e5e7eb;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .cards-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="library-cards-page">
    <!-- Header -->
    <div class="page-header-cards">
        <div class="header-content-cards">
            <h1>🎫 Library Card Management</h1>
            <p>Issue and manage library cards for students</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-card alert-<?php echo $messageType; ?>">
            <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✓' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Issue New Card Form -->
    <?php if (!empty($studentsWithoutCards)): ?>
    <div class="issue-card-form">
        <h2>📝 Issue New Library Card</h2>
        <form method="post">
            <input type="hidden" name="action" value="issue_card">
            <div class="form-row">
                <div class="form-group-card">
                    <label class="form-label-card">Select Student</label>
                    <select name="user_id" class="form-select-card" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($studentsWithoutCards as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group-card">
                    <label class="form-label-card">Validity (Months)</label>
                    <select name="validity_months" class="form-select-card" required>
                        <option value="6">6 Months</option>
                        <option value="12" selected>12 Months</option>
                        <option value="24">24 Months</option>
                        <option value="36">36 Months</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-issue">Issue Card</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Library Cards Grid -->
    <?php if (empty($cards)): ?>
        <div class="empty-state">
            <div class="empty-icon">🎫</div>
            <h3>No library cards issued yet</h3>
            <p>Start issuing cards to students above</p>
        </div>
    <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($cards as $card): ?>
                <?php
                $status = strtolower($card['status']);
                $isExpired = strtotime($card['expiry_date']) < time();
                $daysUntilExpiry = floor((strtotime($card['expiry_date']) - time()) / 86400);
                ?>
                <div class="library-card status-<?php echo $status; ?>">
                    <div class="card-header">
                        <div class="card-number"><?php echo htmlspecialchars($card['card_number']); ?></div>
                        <span class="card-status-badge badge-<?php echo $status; ?>">
                            <?php echo strtoupper($card['status']); ?>
                        </span>
                    </div>
                    
                    <div class="card-holder">
                        <div class="holder-name"><?php echo htmlspecialchars($card['full_name']); ?></div>
                        <div class="holder-email"><?php echo htmlspecialchars($card['email']); ?></div>
                    </div>
                    
                    <div class="card-details">
                        <div class="detail-item">
                            <div class="detail-label">Issue Date</div>
                            <div class="detail-value"><?php echo date('M d, Y', strtotime($card['issue_date'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Expiry Date</div>
                            <div class="detail-value"><?php echo date('M d, Y', strtotime($card['expiry_date'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Issued By</div>
                            <div class="detail-value"><?php echo htmlspecialchars($card['issued_by_name'] ?? 'Admin'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Validity</div>
                            <div class="detail-value">
                                <?php 
                                if ($isExpired) {
                                    echo '<span style="color: #ef4444;">Expired</span>';
                                } elseif ($daysUntilExpiry <= 30) {
                                    echo '<span style="color: #f59e0b;">' . $daysUntilExpiry . ' days left</span>';
                                } else {
                                    echo '<span style="color: #10b981;">' . $daysUntilExpiry . ' days left</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <?php if ($card['status'] === 'ACTIVE'): ?>
                            <form method="post" style="flex: 1;">
                                <input type="hidden" name="action" value="renew_card">
                                <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                <input type="hidden" name="extension_months" value="12">
                                <button type="submit" class="btn-action-card btn-renew">
                                    🔄 Renew
                                </button>
                            </form>
                            <form method="post" style="flex: 1;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                <input type="hidden" name="new_status" value="SUSPENDED">
                                <button type="submit" class="btn-action-card btn-suspend" onclick="return confirm('Suspend this card?');">
                                    ⛔ Suspend
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="flex: 1;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                <input type="hidden" name="new_status" value="ACTIVE">
                                <button type="submit" class="btn-action-card btn-activate">
                                    ✓ Activate
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>