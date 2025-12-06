<?php
// pages/reminders/manage.php - UC07: Send Reminders & Overdue Notices

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin();

$user = currentUser();
$role = $user['role'] ?? '';
$isAdmin = in_array($role, ['ADMIN', 'LIBRARIAN'], true);

if (!$isAdmin) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();

$message = '';
$messageType = '';

// Handle manual reminder send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_reminders') {
        try {
            // Get all loans due in next 3 days
            $stmt = $pdo->query("
                SELECT l.*, u.id as user_id, u.full_name, u.email, b.title
                FROM loans l
                JOIN users u ON l.user_id = u.id
                JOIN books b ON l.book_id = b.id
                WHERE l.returned_at IS NULL
                AND l.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            ");
            $upcomingLoans = $stmt->fetchAll();
            
            $sent = 0;
            foreach ($upcomingLoans as $loan) {
                $daysRemaining = floor((strtotime($loan['due_date']) - time()) / 86400);
                
                $title = "Book Return Reminder";
                $message = "Hi {$loan['full_name']}, your book '{$loan['title']}' is due in {$daysRemaining} day(s) on " . date('M d, Y', strtotime($loan['due_date'])) . ". Please return on time.";
                
                // Create notification
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, loan_id, type, title, message, sent_via, status, sent_at)
                    VALUES (:user_id, :loan_id, 'REMINDER', :title, :message, 'APP', 'SENT', NOW())
                ");
                $stmt->execute([
                    'user_id' => $loan['user_id'],
                    'loan_id' => $loan['id'],
                    'title' => $title,
                    'message' => $message
                ]);
                $sent++;
            }
            
            $messageType = 'success';
            $message = "Sent {$sent} reminder(s) successfully!";
        } catch (PDOException $e) {
            $messageType = 'error';
            $message = 'Error sending reminders.';
        }
    }
    
    elseif ($_POST['action'] === 'send_overdue') {
        try {
            // Get all overdue loans
            $stmt = $pdo->query("
                SELECT l.*, u.id as user_id, u.full_name, u.email, b.title
                FROM loans l
                JOIN users u ON l.user_id = u.id
                JOIN books b ON l.book_id = b.id
                WHERE l.returned_at IS NULL
                AND l.due_date < CURDATE()
            ");
            $overdueLoans = $stmt->fetchAll();
            
            $sent = 0;
            foreach ($overdueLoans as $loan) {
                $daysOverdue = floor((time() - strtotime($loan['due_date'])) / 86400);
                $fine = $daysOverdue * 5.00;
                
                $title = "Overdue Book Notice";
                $message = "URGENT: Your book '{$loan['title']}' is {$daysOverdue} day(s) overdue. Fine: $" . number_format($fine, 2) . ". Please return immediately.";
                
                // Create notification
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, loan_id, type, title, message, sent_via, status, sent_at)
                    VALUES (:user_id, :loan_id, 'OVERDUE', :title, :message, 'APP', 'SENT', NOW())
                ");
                $stmt->execute([
                    'user_id' => $loan['user_id'],
                    'loan_id' => $loan['id'],
                    'title' => $title,
                    'message' => $message
                ]);
                $sent++;
            }
            
            $messageType = 'success';
            $message = "Sent {$sent} overdue notice(s) successfully!";
        } catch (PDOException $e) {
            $messageType = 'error';
            $message = 'Error sending notices.';
        }
    }
}

// Get statistics
$stats = [
    'upcoming_due' => 0,
    'overdue' => 0,
    'sent_today' => 0,
    'pending' => 0
];

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM loans 
        WHERE returned_at IS NULL 
        AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ");
    $stats['upcoming_due'] = $stmt->fetch()['cnt'] ?? 0;
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM loans 
        WHERE returned_at IS NULL AND due_date < CURDATE()
    ");
    $stats['overdue'] = $stmt->fetch()['cnt'] ?? 0;
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM notifications 
        WHERE DATE(sent_at) = CURDATE()
    ");
    $stats['sent_today'] = $stmt->fetch()['cnt'] ?? 0;
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM notifications WHERE status = 'PENDING'
    ");
    $stats['pending'] = $stmt->fetch()['cnt'] ?? 0;
} catch (PDOException $e) {
    // Keep defaults
}

// Get recent notifications
$recentNotifications = [];
try {
    $stmt = $pdo->query("
        SELECT n.*, u.full_name, u.email
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        ORDER BY n.created_at DESC
        LIMIT 20
    ");
    $recentNotifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentNotifications = [];
}
?>

<style>
.reminders-page {
    max-width: 1400px;
    margin: 0 auto;
}

.reminders-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.reminders-header h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
}

.reminders-header p {
    font-size: 15px;
    opacity: 0.95;
    margin: 0;
}

.alert-reminder {
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

.stats-grid-reminders {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card-reminder {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.stat-card-reminder:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon-reminder {
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
.icon-orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.icon-green { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.icon-purple { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }

.stat-value-reminder {
    font-size: 32px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
}

.stat-label-reminder {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.action-card-reminder {
    background: white;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border: 2px solid #e5e7eb;
    transition: all 0.3s ease;
}

.action-card-reminder:hover {
    border-color: #667eea;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.action-card-title {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 8px;
}

.action-card-desc {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 16px;
}

.btn-send-reminder {
    width: 100%;
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-send-reminder:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.btn-send-overdue {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.notifications-list {
    background: white;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.notifications-list h2 {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 20px;
}

.notification-item {
    display: flex;
    gap: 16px;
    padding: 16px;
    border-radius: 12px;
    background: #f9fafb;
    margin-bottom: 12px;
    transition: all 0.3s ease;
}

.notification-item:hover {
    background: #f3f4f6;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.icon-reminder { background: #dbeafe; }
.icon-overdue { background: #fee2e2; }
.icon-confirmation { background: #dcfce7; }

.notification-content {
    flex: 1;
}

.notification-title {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
}

.notification-message {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 4px;
}

.notification-meta {
    font-size: 12px;
    color: #9ca3af;
}

.notification-status {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-sent { background: #dcfce7; color: #166534; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-failed { background: #fee2e2; color: #b91c1c; }

.empty-state-reminders {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon-reminders {
    font-size: 64px;
    margin-bottom: 16px;
}
</style>

<div class="reminders-page">
    <!-- Header -->
    <div class="reminders-header">
        <h1>📧 Reminders & Notices</h1>
        <p>Automated notification system for due and overdue books</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-reminder alert-<?php echo $messageType; ?>">
            <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✓' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid-reminders">
        <div class="stat-card-reminder">
            <div class="stat-icon-reminder icon-blue">⏰</div>
            <div class="stat-value-reminder"><?php echo $stats['upcoming_due']; ?></div>
            <div class="stat-label-reminder">Due in 3 Days</div>
        </div>

        <div class="stat-card-reminder">
            <div class="stat-icon-reminder icon-orange">⚠️</div>
            <div class="stat-value-reminder"><?php echo $stats['overdue']; ?></div>
            <div class="stat-label-reminder">Overdue Books</div>
        </div>

        <div class="stat-card-reminder">
            <div class="stat-icon-reminder icon-green">📨</div>
            <div class="stat-value-reminder"><?php echo $stats['sent_today']; ?></div>
            <div class="stat-label-reminder">Sent Today</div>
        </div>

        <div class="stat-card-reminder">
            <div class="stat-icon-reminder icon-purple">⏳</div>
            <div class="stat-value-reminder"><?php echo $stats['pending']; ?></div>
            <div class="stat-label-reminder">Pending</div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <div class="action-card-reminder">
            <div class="action-card-title">📬 Send Due Date Reminders</div>
            <div class="action-card-desc">
                Send reminders to users with books due in the next 3 days (<?php echo $stats['upcoming_due']; ?> user(s))
            </div>
            <form method="post">
                <input type="hidden" name="action" value="send_reminders">
                <button type="submit" class="btn-send-reminder" <?php echo $stats['upcoming_due'] == 0 ? 'disabled' : ''; ?>>
                    Send Reminders
                </button>
            </form>
        </div>

        <div class="action-card-reminder">
            <div class="action-card-title">🚨 Send Overdue Notices</div>
            <div class="action-card-desc">
                Send urgent notices to users with overdue books (<?php echo $stats['overdue']; ?> user(s))
            </div>
            <form method="post">
                <input type="hidden" name="action" value="send_overdue">
                <button type="submit" class="btn-send-reminder btn-send-overdue" <?php echo $stats['overdue'] == 0 ? 'disabled' : ''; ?>>
                    Send Overdue Notices
                </button>
            </form>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="notifications-list">
        <h2>📋 Recent Notifications</h2>
        
        <?php if (empty($recentNotifications)): ?>
            <div class="empty-state-reminders">
                <div class="empty-icon-reminders">📭</div>
                <h3>No notifications sent yet</h3>
                <p>Start sending reminders using the buttons above</p>
            </div>
        <?php else: ?>
            <?php foreach ($recentNotifications as $notif): ?>
                <?php
                $typeClass = strtolower($notif['type']);
                $iconClass = 'icon-' . $typeClass;
                $statusClass = 'status-' . strtolower($notif['status']);
                ?>
                <div class="notification-item">
                    <div class="notification-icon <?php echo $iconClass; ?>">
                        <?php 
                        echo match($notif['type']) {
                            'REMINDER' => '⏰',
                            'OVERDUE' => '⚠️',
                            'RETURN_CONFIRMATION' => '✓',
                            'FINE' => '💰',
                            default => '📧'
                        };
                        ?>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                        <div class="notification-meta">
                            To: <?php echo htmlspecialchars($notif['full_name']); ?> (<?php echo htmlspecialchars($notif['email']); ?>) • 
                            <?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?>
                        </div>
                    </div>
                    <span class="notification-status <?php echo $statusClass; ?>">
                        <?php echo $notif['status']; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>