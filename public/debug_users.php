<?php
/**
 * Debug Users Tool - Shows all users and their roles
 * 
 * Place in: public/debug_users.php
 * Visit: http://localhost/bookbyte/public/debug_users.php
 * DELETE after debugging!
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debug Users - BookByte</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f3f4f6;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .stat-value {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-student { background: #dbeafe; color: #1e40af; }
        .badge-admin { background: #fee2e2; color: #b91c1c; }
        .badge-librarian { background: #dcfce7; color: #166534; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin: 20px 0;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 2px solid #93c5fd;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 2px solid #86efac;
        }
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #fde68a;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            margin: 10px 10px 0 0;
        }
        .btn:hover {
            background: #5568d3;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Users - BookByte LMS</h1>
        <p>This tool shows all users in your system and why they may or may not appear in Library Cards</p>

<?php
try {
    $pdo = getDBConnection();
    
    // First, detect column names in users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll();
    $columnNames = array_column($columns, 'Field');
    
    // Detect name column
    $nameColumn = 'email'; // fallback
    if (in_array('full_name', $columnNames)) {
        $nameColumn = 'full_name';
    } elseif (in_array('name', $columnNames)) {
        $nameColumn = 'name';
    } elseif (in_array('first_name', $columnNames)) {
        $nameColumn = 'first_name';
    }
    
    // Get all users - using detected column
    $stmt = $pdo->query("
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM library_cards WHERE user_id = u.id AND status = 'ACTIVE') as has_active_card
        FROM users u
        ORDER BY 
            CASE 
                WHEN u.role = 'STUDENT' THEN 1
                WHEN u.role = 'LIBRARIAN' THEN 2
                WHEN u.role = 'ADMIN' THEN 3
                ELSE 4
            END,
            u.$nameColumn
    ");
    $users = $stmt->fetchAll();
    
    // Calculate statistics
    $totalUsers = count($users);
    $students = array_filter($users, function($u) { return strtoupper($u['role']) === 'STUDENT'; });
    $studentsCount = count($students);
    $studentsWithCards = array_filter($students, function($u) { return $u['has_active_card'] > 0; });
    $studentsWithCardsCount = count($studentsWithCards);
    $studentsWithoutCards = $studentsCount - $studentsWithCardsCount;
    
    // Display statistics
    echo '<div class="stats">';
    echo '<div class="stat-card">';
    echo '<div class="stat-value">' . $totalUsers . '</div>';
    echo '<div class="stat-label">Total Users</div>';
    echo '</div>';
    
    echo '<div class="stat-card">';
    echo '<div class="stat-value">' . $studentsCount . '</div>';
    echo '<div class="stat-label">Students</div>';
    echo '</div>';
    
    echo '<div class="stat-card">';
    echo '<div class="stat-value">' . $studentsWithCardsCount . '</div>';
    echo '<div class="stat-label">Students with Cards</div>';
    echo '</div>';
    
    echo '<div class="stat-card">';
    echo '<div class="stat-value">' . $studentsWithoutCards . '</div>';
    echo '<div class="stat-label">Students without Cards</div>';
    echo '</div>';
    echo '</div>';
    
    // Check for issues
    if ($studentsCount === 0) {
        echo '<div class="alert alert-warning">';
        echo '<strong>⚠️ No Students Found!</strong><br>';
        echo 'There are no users with role = "STUDENT" in your database.<br>';
        echo 'When adding users, make sure to select <strong>STUDENT</strong> as the role.';
        echo '</div>';
    } else {
        echo '<div class="alert alert-success">';
        echo '<strong>✓ Students Found!</strong><br>';
        echo 'You have ' . $studentsCount . ' student(s) in the system.';
        if ($studentsWithoutCards > 0) {
            echo '<br>' . $studentsWithoutCards . ' student(s) are ready to receive library cards.';
        }
        echo '</div>';
    }
    
    // Display all users
    echo '<h2>👥 All Users in System</h2>';
    
    echo '<div class="alert alert-info">';
    echo '<strong>🔍 Detected Columns:</strong> ' . implode(', ', array_map(function($c) { 
        return '<code>' . $c . '</code>'; 
    }, $columnNames)) . '<br>';
    echo '<strong>Using name column:</strong> <code>' . $nameColumn . '</code>';
    echo '</div>';
    
    if (empty($users)) {
        echo '<div class="alert alert-warning">';
        echo '<strong>No users found in database!</strong><br>';
        echo 'Please add users through the Manage Users page.';
        echo '</div>';
    } else {
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Name</th>';
        echo '<th>Email</th>';
        echo '<th>Role</th>';
        echo '<th>Status</th>';
        echo '<th>Has Library Card?</th>';
        echo '<th>Eligible for Card?</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($users as $user) {
            $role = strtoupper($user['role']);
            $isStudent = $role === 'STUDENT';
            $hasCard = $user['has_active_card'] > 0;
            $isActive = isset($user['status']) 
                ? strtoupper($user['status']) === 'ACTIVE'
                : (isset($user['is_active']) ? $user['is_active'] == 1 : true);
            
            // Get display name using detected column
            $displayName = $user[$nameColumn] ?? 'N/A';
            if ($nameColumn === 'first_name' && isset($user['last_name'])) {
                $displayName .= ' ' . $user['last_name'];
            }
            
            echo '<tr>';
            echo '<td>' . $user['id'] . '</td>';
            echo '<td>' . htmlspecialchars($displayName) . '</td>';
            echo '<td>' . htmlspecialchars($user['email']) . '</td>';
            
            // Role badge
            $roleBadge = $role === 'STUDENT' ? 'badge-student' 
                : ($role === 'ADMIN' ? 'badge-admin' : 'badge-librarian');
            echo '<td><span class="badge ' . $roleBadge . '">' . $role . '</span></td>';
            
            // Status badge
            $statusBadge = $isActive ? 'badge-active' : 'badge-inactive';
            $statusText = $isActive ? 'ACTIVE' : 'INACTIVE';
            echo '<td><span class="badge ' . $statusBadge . '">' . $statusText . '</span></td>';
            
            // Has card
            echo '<td>' . ($hasCard ? '✅ Yes' : '❌ No') . '</td>';
            
            // Eligible for card
            if (!$isStudent) {
                echo '<td style="color: #ef4444;">❌ Not a student</td>';
            } elseif (!$isActive) {
                echo '<td style="color: #f59e0b;">⚠️ Inactive account</td>';
            } elseif ($hasCard) {
                echo '<td style="color: #10b981;">✅ Already has card</td>';
            } else {
                echo '<td style="color: #10b981; font-weight: 600;">✅ YES - Ready for card!</td>';
            }
            
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    // Show SQL query for verification
    echo '<h2>🔍 SQL Query Used</h2>';
    echo '<div class="alert alert-info">';
    echo '<p>The Library Cards page uses this query to find students:</p>';
    echo '<pre style="background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow-x: auto;">';
    echo "SELECT u.id, u.$nameColumn, u.email\n";
    echo "FROM users u\n";
    echo "WHERE u.role = 'STUDENT'\n";
    echo "ORDER BY u.$nameColumn";
    echo '</pre>';
    echo '</div>';
    
    // Recommendations
    echo '<h2>💡 Recommendations</h2>';
    echo '<div class="alert alert-info">';
    echo '<ul style="margin: 10px 0 10px 20px;">';
    
    if ($studentsCount === 0) {
        echo '<li>Add users with role = <strong>STUDENT</strong> through the Manage Users page</li>';
        echo '<li>Make sure to select "STUDENT" from the role dropdown when creating users</li>';
    } else {
        echo '<li>✅ You have ' . $studentsCount . ' student(s) in the system</li>';
        if ($studentsWithoutCards > 0) {
            echo '<li>✅ ' . $studentsWithoutCards . ' student(s) are ready to receive library cards</li>';
            echo '<li>Go to <strong>Library Cards</strong> page to issue cards to these students</li>';
        } else {
            echo '<li>All students already have library cards!</li>';
        }
    }
    
    echo '</ul>';
    echo '</div>';
    
    // Action buttons
    echo '<div style="margin-top: 30px;">';
    echo '<a href="../public/index.php?page=users_manage" class="btn">👥 Go to Manage Users</a>';
    echo '<a href="../public/index.php?page=library_cards" class="btn">🎫 Go to Library Cards</a>';
    echo '<a href="../public/index.php?page=dashboard_admin" class="btn">🏠 Go to Dashboard</a>';
    echo '</div>';
    
    echo '<div class="alert alert-warning" style="margin-top: 30px;">';
    echo '<strong>⚠️ Important:</strong> Delete this file after debugging!<br>';
    echo 'File location: <code>public/debug_users.php</code>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="alert alert-warning">';
    echo '<strong>Database Error:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
}
?>

    </div>
</body>
</html>