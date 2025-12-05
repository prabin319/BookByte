<?php
// pages/settings/index.php

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

requireLogin();

$user = currentUser();
$userId = $user['id'] ?? null;

$pdo = getDBConnection();

// Detect columns in users table so we don't guess wrong names
$columns = [];
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM users');
    $columns = array_column($stmt->fetchAll(), 'Field');
} catch (PDOException $e) {
    $columns = [];
}

// Figure out column mapping
$nameCol = null;
$emailCol = null;
$phoneCol = null;
$roleCol = null;
$passwordCol = null;
$emailNotifCol = null;
$smsNotifCol = null;
$langCol = null;

if (in_array('full_name', $columns, true))  $nameCol = 'full_name';
elseif (in_array('name', $columns, true))   $nameCol = 'name';

if (in_array('email', $columns, true))      $emailCol = 'email';

foreach (['phone', 'phone_number', 'contact_number'] as $c) {
    if (in_array($c, $columns, true)) { $phoneCol = $c; break; }
}

foreach (['role', 'user_role', 'type'] as $c) {
    if (in_array($c, $columns, true)) { $roleCol = $c; break; }
}

foreach (['password_hash', 'password', 'pwd_hash'] as $c) {
    if (in_array($c, $columns, true)) { $passwordCol = $c; break; }
}

foreach (['email_notifications', 'notify_email'] as $c) {
    if (in_array($c, $columns, true)) { $emailNotifCol = $c; break; }
}
foreach (['sms_notifications', 'notify_sms'] as $c) {
    if (in_array($c, $columns, true)) { $smsNotifCol = $c; break; }
}
foreach (['language', 'ui_language'] as $c) {
    if (in_array($c, $columns, true)) { $langCol = $c; break; }
}

// Load fresh user row from DB
$currentRow = [];
if ($userId !== null) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $currentRow = $stmt->fetch() ?: [];
    } catch (PDOException $e) {
        $currentRow = [];
    }
}

// Build display values
$displayName = $user['full_name'] ?? ($user['name'] ?? 'User');
if ($nameCol && isset($currentRow[$nameCol]) && $currentRow[$nameCol] !== '') {
    $displayName = $currentRow[$nameCol];
}

$displayEmail = $user['email'] ?? '';
if ($emailCol && isset($currentRow[$emailCol]) && $currentRow[$emailCol] !== '') {
    $displayEmail = $currentRow[$emailCol];
}

$displayRole = strtoupper($user['role'] ?? 'USER');
if ($roleCol && isset($currentRow[$roleCol]) && $currentRow[$roleCol] !== '') {
    $displayRole = strtoupper($currentRow[$roleCol]);
}

$displayPhone = '';
if ($phoneCol && isset($currentRow[$phoneCol])) {
    $displayPhone = (string)$currentRow[$phoneCol];
}

$emailNotif = true;
if ($emailNotifCol && array_key_exists($emailNotifCol, $currentRow)) {
    $emailNotif = (bool)$currentRow[$emailNotifCol];
}

$smsNotif = false;
if ($smsNotifCol && array_key_exists($smsNotifCol, $currentRow)) {
    $smsNotif = (bool)$currentRow[$smsNotifCol];
}

$language = 'en';
if ($langCol && !empty($currentRow[$langCol])) {
    $language = (string)$currentRow[$langCol];
}

// Messages
$profileMessage = '';
$profileError   = '';
$passwordMessage = '';
$passwordError   = '';

// ---------- Handle profile save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'profile') {
    $newName  = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $newEmail = isset($_POST['email']) ? trim($_POST['email']) : '';
    $newPhone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $newLang  = isset($_POST['language']) ? trim($_POST['language']) : $language;
    $newEmailNotif = isset($_POST['email_notifications']) ? 1 : 0;
    $newSmsNotif   = isset($_POST['sms_notifications']) ? 1 : 0;

    if ($newName === '' || $newEmail === '') {
        $profileError = 'Full name and email are required.';
    } else {
        try {
            $updateCols = [];
            $params = [':id' => $userId];

            if ($nameCol) {
                $updateCols[] = "`$nameCol` = :nm";
                $params[':nm'] = $newName;
            }
            if ($emailCol) {
                $updateCols[] = "`$emailCol` = :em";
                $params[':em'] = $newEmail;
            }
            if ($phoneCol) {
                $updateCols[] = "`$phoneCol` = :ph";
                $params[':ph'] = $newPhone;
            }
            if ($emailNotifCol) {
                $updateCols[] = "`$emailNotifCol` = :enf";
                $params[':enf'] = $newEmailNotif;
            }
            if ($smsNotifCol) {
                $updateCols[] = "`$smsNotifCol` = :snf";
                $params[':snf'] = $newSmsNotif;
            }
            if ($langCol) {
                $updateCols[] = "`$langCol` = :lng";
                $params[':lng'] = $newLang;
            }

            if (!empty($updateCols)) {
                $sql = "UPDATE users SET " . implode(', ', $updateCols) . " WHERE id = :id LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Update session copy too
                if ($nameCol)  $_SESSION['user']['full_name'] = $newName;
                if ($emailCol) $_SESSION['user']['email'] = $newEmail;
                if ($roleCol && isset($currentRow[$roleCol])) {
                    $_SESSION['user']['role'] = strtoupper($currentRow[$roleCol]);
                }

                $profileMessage = 'Profile updated successfully.';
                $displayName  = $newName;
                $displayEmail = $newEmail;
                $displayPhone = $newPhone;
                $emailNotif   = (bool)$newEmailNotif;
                $smsNotif     = (bool)$newSmsNotif;
                $language     = $newLang;
            } else {
                $profileError = 'No matching columns found in users table to update.';
            }
        } catch (PDOException $e) {
            $profileError = 'Could not update profile (database error).';
        }
    }
}

// ---------- Handle password change ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'password') {
    $currentPw = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
    $newPw     = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
    $confirmPw = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

    if (!$passwordCol) {
        $passwordError = 'Password column not found in users table.';
    } elseif ($currentPw === '' || $newPw === '' || $confirmPw === '') {
        $passwordError = 'Please fill in all password fields.';
    } elseif ($newPw !== $confirmPw) {
        $passwordError = 'New password and confirmation do not match.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch();
            if (!$row) {
                $passwordError = 'User account not found.';
            } else {
                $storedHash = (string)$row[$passwordCol];

                $valid = false;
                if (password_get_info($storedHash)['algo']) {
                    $valid = password_verify($currentPw, $storedHash);
                } else {
                    // legacy plain-text password (not recommended)
                    $valid = ($currentPw === $storedHash);
                }

                if (!$valid) {
                    $passwordError = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($newPw, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET `$passwordCol` = :pw WHERE id = :id LIMIT 1");
                    $stmt->execute([':pw' => $newHash, ':id' => $userId]);
                    $passwordMessage = 'Password changed successfully.';
                }
            }
        } catch (PDOException $e) {
            $passwordError = 'Could not change password (database error).';
        }
    }
}

// Avatar initials
$initials = 'U';
$parts = preg_split('/\s+/', trim($displayName));
if (count($parts) >= 2) {
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
} elseif (!empty($parts[0])) {
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 2));
}

// Display role nicely formatted
$displayRoleFormatted = ucfirst(strtolower($displayRole));
if ($displayRole === 'ADMIN') $displayRoleFormatted = 'Administrator';
?>
<div class="settings-container">
    <!-- Page Header -->
    <div class="settings-page-header">
        <h1 class="settings-page-title">Profile &amp; Settings</h1>
    </div>

    <!-- Alert Messages -->
    <?php if ($profileMessage): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($profileMessage); ?></div>
    <?php endif; ?>
    <?php if ($profileError): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($profileError); ?></div>
    <?php endif; ?>
    <?php if ($passwordMessage): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($passwordMessage); ?></div>
    <?php endif; ?>
    <?php if ($passwordError): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($passwordError); ?></div>
    <?php endif; ?>

    <!-- User Profile Section -->
    <div class="settings-section">
        <div class="settings-section-header">
            <span class="settings-section-icon">👤</span>
            <h2 class="settings-section-title">User Profile</h2>
        </div>

        <form method="post">
            <input type="hidden" name="form_type" value="profile">

            <!-- Avatar Section -->
            <div class="profile-avatar-section">
                <div class="profile-avatar-large"><?php echo htmlspecialchars($initials); ?></div>
                <div class="profile-avatar-actions">
                    <button type="button" class="btn btn-secondary btn-avatar">Change Avatar</button>
                </div>
            </div>

            <!-- Form Fields in Grid -->
            <div class="form-grid">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?php echo htmlspecialchars($displayName); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($displayEmail); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <input 
                        type="text" 
                        id="role"
                        value="<?php echo htmlspecialchars($displayRoleFormatted); ?>" 
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="<?php echo htmlspecialchars($displayPhone); ?>"
                        placeholder="+1 (555) 123-4567"
                    >
                </div>
            </div>

            <!-- Save Button -->
            <div class="settings-form-actions">
                <button type="submit" class="btn btn-primary btn-save">
                    Save Profile Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Change Password Section -->
    <div class="settings-section">
        <div class="settings-section-header">
            <span class="settings-section-icon">🔒</span>
            <h2 class="settings-section-title">Change Password</h2>
        </div>

        <form method="post">
            <input type="hidden" name="form_type" value="password">

            <div class="password-form-group">
                <label for="current_password">Current Password</label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    required
                    placeholder="Enter your current password"
                >
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        required
                        placeholder="Enter new password"
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required
                        placeholder="Re-enter new password"
                    >
                </div>
            </div>

            <div class="settings-form-actions">
                <button type="submit" class="btn btn-secondary">
                    Update Password
                </button>
            </div>
        </form>
    </div>

    <!-- Notification Settings (Optional - uncomment if you want to include) -->
    <?php if ($emailNotifCol || $smsNotifCol || $langCol): ?>
    <div class="settings-section">
        <div class="settings-section-header">
            <span class="settings-section-icon">🔔</span>
            <h2 class="settings-section-title">Notification Preferences</h2>
        </div>

        <form method="post">
            <input type="hidden" name="form_type" value="profile">
            <input type="hidden" name="full_name" value="<?php echo htmlspecialchars($displayName); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($displayEmail); ?>">
            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($displayPhone); ?>">

            <?php if ($emailNotifCol): ?>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="email_notifications" <?php echo $emailNotif ? 'checked' : ''; ?>>
                    Email Notifications
                </label>
                <p style="font-size: 13px; color: #6b7280; margin-top: 4px;">Receive emails about due &amp; overdue books.</p>
            </div>
            <?php endif; ?>

            <?php if ($smsNotifCol): ?>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="sms_notifications" <?php echo $smsNotif ? 'checked' : ''; ?>>
                    SMS Notifications
                </label>
                <p style="font-size: 13px; color: #6b7280; margin-top: 4px;">Get text alerts for important updates.</p>
            </div>
            <?php endif; ?>

            <?php if ($langCol): ?>
            <div class="form-group">
                <label for="language">Language</label>
                <select id="language" name="language">
                    <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                    <option value="ko" <?php echo $language === 'ko' ? 'selected' : ''; ?>>Korean</option>
                    <option value="ne" <?php echo $language === 'ne' ? 'selected' : ''; ?>>Nepali</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="settings-form-actions">
                <button type="submit" class="btn btn-primary">
                    Save Preferences
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>