<?php
/**
 * Auto Fix Database Script
 * 
 * This script will automatically fix all database issues:
 * - Add missing columns
 * - Set proper defaults
 * - Fix book availability
 * 
 * Place in: public/auto_fix_database.php
 * Run ONCE: http://localhost/bookbyte/public/auto_fix_database.php
 * Then DELETE this file!
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auto Fix Database - BookByte</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 32px;
        }
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .step {
            background: #f9fafb;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .step h2 {
            color: #374151;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .success {
            background: #dcfce7;
            border-left-color: #10b981;
            color: #166534;
        }
        .error {
            background: #fee2e2;
            border-left-color: #ef4444;
            color: #b91c1c;
        }
        .warning {
            background: #fef3c7;
            border-left-color: #f59e0b;
            color: #92400e;
        }
        .info {
            background: #dbeafe;
            border-left-color: #3b82f6;
            color: #1e40af;
        }
        ul { margin: 10px 0 10px 20px; }
        li { margin: 5px 0; }
        code {
            background: rgba(0,0,0,0.05);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
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
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .icon { font-size: 24px; margin-right: 8px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 14px;
        }
        th, td {
            padding: 10px;
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
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-error { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="icon">🔧</span> Auto Fix Database</h1>
        <p class="subtitle">This will automatically fix all database issues in your BookByte LMS</p>

<?php
$fixes = [];
$errors = [];

try {
    $pdo = getDBConnection();
    
    // =========================================
    // STEP 1: Fix Loans Table
    // =========================================
    echo '<div class="step info">';
    echo '<h2><span class="icon">📋</span> Step 1: Checking Loans Table</h2>';
    
    try {
        // Check existing columns
        $stmt = $pdo->query("SHOW COLUMNS FROM loans");
        $existingColumns = array_column($stmt->fetchAll(), 'Field');
        
        $requiredColumns = [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'user_id' => 'INT NOT NULL',
            'book_id' => 'INT NOT NULL',
            'loan_date' => 'DATE NOT NULL',
            'due_date' => 'DATE NOT NULL',
            'returned_at' => 'DATETIME NULL',
            'renewal_count' => 'INT DEFAULT 0',
            'renewed_at' => 'DATETIME NULL'
        ];
        
        $added = 0;
        foreach ($requiredColumns as $col => $def) {
            if (!in_array($col, $existingColumns)) {
                $pdo->exec("ALTER TABLE loans ADD COLUMN $col $def");
                $fixes[] = "Added column: loans.$col";
                $added++;
            }
        }
        
        if ($added > 0) {
            echo '<p class="badge badge-success">✓ Added ' . $added . ' missing column(s) to loans table</p>';
        } else {
            echo '<p class="badge badge-success">✓ Loans table structure is correct</p>';
        }
        
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        $errors[] = 'Loans table: ' . $e->getMessage();
    }
    
    echo '</div>';
    
    // =========================================
    // STEP 2: Fix Books Table
    // =========================================
    echo '<div class="step info">';
    echo '<h2><span class="icon">📚</span> Step 2: Checking Books Table</h2>';
    
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM books");
        $existingColumns = array_column($stmt->fetchAll(), 'Field');
        
        $requiredColumns = [
            'available_copies' => 'INT DEFAULT 1',
            'total_copies' => 'INT DEFAULT 1',
            'status' => "ENUM('AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE', 'LOST') DEFAULT 'AVAILABLE'",
            'cover_url' => 'VARCHAR(500) NULL',
            'description' => 'TEXT NULL',
            'review' => 'TEXT NULL',
            'publisher' => 'VARCHAR(255) NULL',
            'edition' => 'VARCHAR(100) NULL',
            'year_of_release' => 'INT NULL'
        ];
        
        $added = 0;
        foreach ($requiredColumns as $col => $def) {
            if (!in_array($col, $existingColumns)) {
                $pdo->exec("ALTER TABLE books ADD COLUMN $col $def");
                $fixes[] = "Added column: books.$col";
                $added++;
            }
        }
        
        if ($added > 0) {
            echo '<p class="badge badge-success">✓ Added ' . $added . ' missing column(s) to books table</p>';
        } else {
            echo '<p class="badge badge-success">✓ Books table structure is correct</p>';
        }
        
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        $errors[] = 'Books table: ' . $e->getMessage();
    }
    
    echo '</div>';
    
    // =========================================
    // STEP 3: Set Default Values
    // =========================================
    echo '<div class="step info">';
    echo '<h2><span class="icon">⚙️</span> Step 3: Setting Default Values</h2>';
    
    try {
        // Fix NULL or 0 copies
        $stmt = $pdo->exec("
            UPDATE books 
            SET 
                available_copies = CASE 
                    WHEN available_copies IS NULL OR available_copies = 0 THEN 1
                    ELSE available_copies
                END,
                total_copies = CASE 
                    WHEN total_copies IS NULL OR total_copies = 0 THEN 1
                    ELSE total_copies
                END,
                status = CASE
                    WHEN status IS NULL THEN 'AVAILABLE'
                    ELSE status
                END
        ");
        
        echo '<p class="badge badge-success">✓ Set default values for all books</p>';
        $fixes[] = 'Set default book values';
        
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        $errors[] = 'Default values: ' . $e->getMessage();
    }
    
    echo '</div>';
    
    // =========================================
    // STEP 4: Calculate Real Availability
    // =========================================
    echo '<div class="step info">';
    echo '<h2><span class="icon">📊</span> Step 4: Calculating Real Availability</h2>';
    
    try {
        // Count active loans per book
        $loanCounts = [];
        $stmt = $pdo->query("
            SELECT 
                book_id,
                COUNT(*) as active_loans
            FROM loans
            WHERE returned_at IS NULL OR returned_at = '0000-00-00 00:00:00'
            GROUP BY book_id
        ");
        
        while ($row = $stmt->fetch()) {
            $loanCounts[$row['book_id']] = (int)$row['active_loans'];
        }
        
        // Update each book
        $stmt = $pdo->query("SELECT id, total_copies FROM books");
        $books = $stmt->fetchAll();
        
        $updated = 0;
        foreach ($books as $book) {
            $bookId = $book['id'];
            $totalCopies = (int)$book['total_copies'];
            $activeLoanCount = isset($loanCounts[$bookId]) ? $loanCounts[$bookId] : 0;
            $availableCopies = max(0, $totalCopies - $activeLoanCount);
            $status = $availableCopies > 0 ? 'AVAILABLE' : 'UNAVAILABLE';
            
            $updateStmt = $pdo->prepare("
                UPDATE books 
                SET available_copies = :available, status = :status 
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':available' => $availableCopies,
                ':status' => $status,
                ':id' => $bookId
            ]);
            $updated++;
        }
        
        echo '<p class="badge badge-success">✓ Updated availability for ' . $updated . ' books</p>';
        echo '<p style="margin-top:10px;">Found ' . count($loanCounts) . ' books with active loans</p>';
        $fixes[] = 'Calculated real availability for all books';
        
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        $errors[] = 'Availability calculation: ' . $e->getMessage();
    }
    
    echo '</div>';
    
    // =========================================
    // STEP 5: Summary
    // =========================================
    echo '<div class="step success">';
    echo '<h2><span class="icon">✓</span> Summary</h2>';
    
    if (empty($errors)) {
        echo '<p style="font-size:18px; font-weight:600; margin-bottom:15px;">All fixes completed successfully! 🎉</p>';
        echo '<ul>';
        foreach ($fixes as $fix) {
            echo '<li>✓ ' . htmlspecialchars($fix) . '</li>';
        }
        echo '</ul>';
        
        // Show statistics
        $stats = $pdo->query("
            SELECT 
                COUNT(*) as total_books,
                SUM(CASE WHEN available_copies > 0 THEN 1 ELSE 0 END) as available_books,
                SUM(CASE WHEN available_copies = 0 THEN 1 ELSE 0 END) as unavailable_books,
                (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL OR returned_at = '0000-00-00 00:00:00') as active_loans
            FROM books
        ")->fetch();
        
        echo '<table style="margin-top:20px;">';
        echo '<tr><th>Metric</th><th>Count</th></tr>';
        echo '<tr><td>Total Books</td><td>' . $stats['total_books'] . '</td></tr>';
        echo '<tr><td>Available Books</td><td><span class="badge badge-success">' . $stats['available_books'] . '</span></td></tr>';
        echo '<tr><td>Unavailable Books</td><td><span class="badge badge-error">' . $stats['unavailable_books'] . '</span></td></tr>';
        echo '<tr><td>Active Loans</td><td>' . $stats['active_loans'] . '</td></tr>';
        echo '</table>';
        
    } else {
        echo '<p style="color:#b91c1c; font-weight:600;">Some errors occurred:</p>';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>✗ ' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
    }
    
    echo '</div>';
    
    // =========================================
    // Final Instructions
    // =========================================
    echo '<div class="step warning">';
    echo '<h2><span class="icon">⚠️</span> Important: Delete This File!</h2>';
    echo '<p style="margin-bottom:15px;">For security reasons, please <strong>delete this file</strong> now:</p>';
    echo '<code>public/auto_fix_database.php</code>';
    echo '<p style="margin-top:15px;">Then refresh your BookByte system and everything should work perfectly!</p>';
    echo '</div>';
    
    echo '<div style="margin-top:30px;">';
    echo '<a href="index.php?page=books_student" class="btn">📚 Browse Books</a>';
    echo '<a href="index.php?page=dashboard_student" class="btn">🏠 Go to Dashboard</a>';
    echo '<a href="index.php?page=loans_my" class="btn">📄 My Loans</a>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="step error">';
    echo '<h2><span class="icon">✗</span> Database Connection Error</h2>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p style="margin-top:15px;">Please check your database configuration in <code>config/db.php</code></p>';
    echo '</div>';
}
?>

    </div>
</body>
</html>