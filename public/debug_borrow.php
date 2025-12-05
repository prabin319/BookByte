<?php
/**
 * Debug & Fix Borrow System
 * 
 * Place in: public/debug_borrow.php
 * Visit: http://localhost/bookbyte/public/debug_borrow.php
 * DELETE after use!
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/auth.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debug Borrow System - BookByte</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #1e293b;
            color: white;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 { color: #60a5fa; margin-bottom: 20px; font-size: 32px; }
        h2 { color: #34d399; margin: 30px 0 15px; font-size: 24px; }
        h3 { color: #fbbf24; margin: 20px 0 10px; font-size: 18px; }
        .section {
            background: #334155;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            border-left: 4px solid #60a5fa;
        }
        .success { border-left-color: #10b981; background: #064e3b; }
        .error { border-left-color: #ef4444; background: #7f1d1d; }
        .warning { border-left-color: #f59e0b; background: #78350f; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: #1e293b;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #475569;
        }
        th {
            background: #0f172a;
            font-weight: 600;
            color: #60a5fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-ok { background: #10b981; color: white; }
        .badge-error { background: #ef4444; color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        code {
            background: rgba(0,0,0,0.3);
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #fbbf24;
        }
        pre {
            background: #0f172a;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 10px 0;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 5px 5px 0 0;
        }
        .btn:hover { background: #2563eb; }
        ul { margin: 10px 0 10px 25px; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Borrow System</h1>

<?php
try {
    $pdo = getDBConnection();
    
    // ==========================================
    // CHECK 1: Database Tables
    // ==========================================
    echo '<h2>1️⃣ Database Tables Check</h2>';
    
    $tables = ['users', 'books', 'loans'];
    $tableIssues = [];
    
    foreach ($tables as $table) {
        echo '<div class="section">';
        echo '<h3>Table: ' . $table . '</h3>';
        
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM $table");
            $columns = $stmt->fetchAll();
            
            echo '<table>';
            echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>';
            foreach ($columns as $col) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($col['Field']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Default'] ?? 'None') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Check required columns
            $columnNames = array_column($columns, 'Field');
            
            if ($table === 'loans') {
                $required = ['id', 'user_id', 'book_id', 'loan_date', 'due_date', 'returned_at'];
                $missing = array_diff($required, $columnNames);
                if (!empty($missing)) {
                    $tableIssues[] = "loans table missing: " . implode(', ', $missing);
                    echo '<p class="badge badge-error">Missing columns: ' . implode(', ', $missing) . '</p>';
                } else {
                    echo '<p class="badge badge-ok">✓ All required columns present</p>';
                }
            }
            
            if ($table === 'books') {
                $required = ['id', 'title', 'author'];
                $recommended = ['available_copies', 'total_copies', 'status'];
                $missing = array_diff($required, $columnNames);
                $missingRecommended = array_diff($recommended, $columnNames);
                
                if (!empty($missing)) {
                    $tableIssues[] = "books table missing: " . implode(', ', $missing);
                    echo '<p class="badge badge-error">Missing required: ' . implode(', ', $missing) . '</p>';
                }
                if (!empty($missingRecommended)) {
                    echo '<p class="badge badge-warning">Missing recommended: ' . implode(', ', $missingRecommended) . '</p>';
                } else {
                    echo '<p class="badge badge-ok">✓ All recommended columns present</p>';
                }
            }
            
        } catch (PDOException $e) {
            echo '<p class="badge badge-error">Table not found or error</p>';
            echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            $tableIssues[] = "$table table: " . $e->getMessage();
        }
        
        echo '</div>';
    }
    
    // ==========================================
    // CHECK 2: Session & User
    // ==========================================
    echo '<h2>2️⃣ Session & User Check</h2>';
    echo '<div class="section">';
    
    if (isLoggedIn()) {
        $user = currentUser();
        echo '<p class="badge badge-ok">✓ User is logged in</p>';
        echo '<table>';
        echo '<tr><th>Property</th><th>Value</th></tr>';
        echo '<tr><td>User ID</td><td>' . htmlspecialchars($user['id'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Name</td><td>' . htmlspecialchars($user['full_name'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Email</td><td>' . htmlspecialchars($user['email'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Role</td><td>' . htmlspecialchars($user['role'] ?? 'N/A') . '</td></tr>';
        echo '</table>';
    } else {
        echo '<p class="badge badge-error">✗ No user logged in</p>';
        echo '<p>You must be logged in to test borrowing</p>';
    }
    
    echo '</div>';
    
    // ==========================================
    // CHECK 3: Books Data
    // ==========================================
    echo '<h2>3️⃣ Books Data Check</h2>';
    echo '<div class="section">';
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM books");
        $result = $stmt->fetch();
        $totalBooks = $result['total'];
        
        echo '<p>Total books: <strong>' . $totalBooks . '</strong></p>';
        
        // Check books with availability data
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN available_copies > 0 THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN available_copies IS NULL THEN 1 ELSE 0 END) as null_copies
            FROM books
        ");
        $stats = $stmt->fetch();
        
        echo '<table>';
        echo '<tr><th>Metric</th><th>Count</th></tr>';
        echo '<tr><td>Total Books</td><td>' . $stats['total'] . '</td></tr>';
        echo '<tr><td>Available Books</td><td class="badge badge-ok">' . $stats['available'] . '</td></tr>';
        echo '<tr><td>NULL Copies</td><td>' . ($stats['null_copies'] > 0 ? '<span class="badge badge-warning">' . $stats['null_copies'] . '</span>' : '0') . '</td></tr>';
        echo '</table>';
        
        if ($stats['null_copies'] > 0) {
            echo '<p class="badge badge-warning">⚠️ Some books have NULL available_copies - run fix script</p>';
        }
        
        // Show sample books
        echo '<h3>Sample Books:</h3>';
        $stmt = $pdo->query("SELECT id, title, author, available_copies, total_copies, status FROM books LIMIT 5");
        $books = $stmt->fetchAll();
        
        echo '<table>';
        echo '<tr><th>ID</th><th>Title</th><th>Author</th><th>Available</th><th>Total</th><th>Status</th></tr>';
        foreach ($books as $book) {
            echo '<tr>';
            echo '<td>' . $book['id'] . '</td>';
            echo '<td>' . htmlspecialchars($book['title']) . '</td>';
            echo '<td>' . htmlspecialchars($book['author']) . '</td>';
            echo '<td>' . ($book['available_copies'] ?? 'NULL') . '</td>';
            echo '<td>' . ($book['total_copies'] ?? 'NULL') . '</td>';
            echo '<td>' . ($book['status'] ?? 'NULL') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">Error checking books</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    }
    
    echo '</div>';
    
    // ==========================================
    // CHECK 4: Loans Data
    // ==========================================
    echo '<h2>4️⃣ Loans Data Check</h2>';
    echo '<div class="section">';
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans");
        $result = $stmt->fetch();
        $totalLoans = $result['total'];
        
        echo '<p>Total loans: <strong>' . $totalLoans . '</strong></p>';
        
        // Check active loans
        $stmt = $pdo->query("
            SELECT COUNT(*) as active 
            FROM loans 
            WHERE returned_at IS NULL OR returned_at = '0000-00-00 00:00:00'
        ");
        $result = $stmt->fetch();
        $activeLoans = $result['active'];
        
        echo '<p>Active loans: <strong>' . $activeLoans . '</strong></p>';
        
        // Show recent loans
        if ($totalLoans > 0) {
            echo '<h3>Recent Loans:</h3>';
            $stmt = $pdo->query("
                SELECT l.*, u.email, b.title 
                FROM loans l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN books b ON l.book_id = b.id
                ORDER BY l.id DESC
                LIMIT 5
            ");
            $loans = $stmt->fetchAll();
            
            echo '<table>';
            echo '<tr><th>ID</th><th>User</th><th>Book</th><th>Loan Date</th><th>Due Date</th><th>Returned</th></tr>';
            foreach ($loans as $loan) {
                echo '<tr>';
                echo '<td>' . $loan['id'] . '</td>';
                echo '<td>' . htmlspecialchars($loan['email'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($loan['title'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($loan['loan_date'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($loan['due_date'] ?? 'N/A') . '</td>';
                echo '<td>' . ($loan['returned_at'] ? htmlspecialchars($loan['returned_at']) : '<span class="badge badge-ok">Active</span>') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">Error checking loans</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    }
    
    echo '</div>';
    
    // ==========================================
    // SUMMARY & FIXES
    // ==========================================
    echo '<h2>5️⃣ Summary & Recommended Fixes</h2>';
    
    if (empty($tableIssues)) {
        echo '<div class="section success">';
        echo '<h3>✓ All Checks Passed!</h3>';
        echo '<p>Your database structure looks good. If you still have issues:</p>';
        echo '<ul>';
        echo '<li>Make sure you ran the <code>auto_fix_database.php</code> script</li>';
        echo '<li>Clear your browser cache</li>';
        echo '<li>Try logging out and back in</li>';
        echo '<li>Check browser console for JavaScript errors</li>';
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<div class="section error">';
        echo '<h3>⚠️ Issues Found</h3>';
        echo '<ul>';
        foreach ($tableIssues as $issue) {
            echo '<li>' . htmlspecialchars($issue) . '</li>';
        }
        echo '</ul>';
        echo '<p><strong>Solution:</strong> Run the auto-fix script to resolve these issues.</p>';
        echo '</div>';
    }
    
    // SQL Fix
    echo '<div class="section warning">';
    echo '<h3>🔧 Quick SQL Fix</h3>';
    echo '<p>Copy this SQL and run it in phpMyAdmin:</p>';
    echo '<pre>-- Fix loans table
ALTER TABLE loans 
ADD COLUMN IF NOT EXISTS returned_at DATETIME NULL,
ADD COLUMN IF NOT EXISTS renewal_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS renewed_at DATETIME NULL;

-- Fix books table
ALTER TABLE books 
ADD COLUMN IF NOT EXISTS available_copies INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS total_copies INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS status ENUM(\'AVAILABLE\', \'UNAVAILABLE\') DEFAULT \'AVAILABLE\';

-- Set default values
UPDATE books 
SET available_copies = 1, total_copies = 1 
WHERE available_copies IS NULL OR available_copies = 0;</pre>';
    echo '</div>';
    
    // Action buttons
    echo '<div style="margin-top: 30px;">';
    echo '<a href="index.php?page=books_student" class="btn">📚 Browse Books</a>';
    echo '<a href="index.php?page=dashboard_student" class="btn">🏠 Dashboard</a>';
    echo '<a href="auto_fix_database.php" class="btn">🔧 Auto Fix Script</a>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="section error">';
    echo '<h2>❌ Database Connection Error</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<p>Check your database configuration in <code>config/db.php</code></p>';
    echo '</div>';
}
?>

    </div>
</body>
</html>