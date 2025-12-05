<?php
/**
 * EMERGENCY FIX - Fixes Everything
 * 
 * Place in: public/emergency_fix.php
 * Visit ONCE: http://localhost/bookbyte/public/emergency_fix.php
 * DELETE after running!
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Emergency Fix</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
        .ok { color: #0f0; }
        .error { color: #f00; }
        .warn { color: #ff0; }
        pre { background: #111; padding: 10px; border-left: 3px solid #0f0; }
    </style>
</head>
<body>
    <h1>🔧 EMERGENCY FIX</h1>
<?php

try {
    $pdo = getDBConnection();
    echo "<p class='ok'>✓ Connected to database</p>";
    
    // =========================================
    // FIX 1: Backup and recreate loans table
    // =========================================
    echo "<h2>Step 1: Fix Loans Table</h2>";
    
    try {
        // Backup existing loans
        $pdo->exec("DROP TABLE IF EXISTS loans_backup_temp");
        $pdo->exec("CREATE TABLE loans_backup_temp AS SELECT * FROM loans");
        echo "<p class='ok'>✓ Backed up existing loans</p>";
        
        // Drop and recreate
        $pdo->exec("DROP TABLE loans");
        
        $createLoans = "
        CREATE TABLE loans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            loan_date DATE NOT NULL,
            due_date DATE NOT NULL,
            returned_at DATETIME NULL,
            renewal_count INT DEFAULT 0,
            renewed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($createLoans);
        echo "<p class='ok'>✓ Created new loans table</p>";
        
        // Try to restore data
        try {
            $stmt = $pdo->query("SELECT * FROM loans_backup_temp");
            $oldLoans = $stmt->fetchAll();
            
            foreach ($oldLoans as $loan) {
                $loanDate = $loan['loan_date'] ?? $loan['borrow_date'] ?? $loan['issued_date'] ?? date('Y-m-d');
                $dueDate = $loan['due_date'] ?? $loan['return_date'] ?? date('Y-m-d', strtotime('+14 days'));
                
                $insert = $pdo->prepare("
                    INSERT INTO loans (id, user_id, book_id, loan_date, due_date, returned_at, renewal_count)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $loan['id'] ?? null,
                    $loan['user_id'] ?? 1,
                    $loan['book_id'] ?? 1,
                    $loanDate,
                    $dueDate,
                    $loan['returned_at'] ?? null,
                    $loan['renewal_count'] ?? 0
                ]);
            }
            echo "<p class='ok'>✓ Restored " . count($oldLoans) . " loans</p>";
        } catch (Exception $e) {
            echo "<p class='warn'>⚠ Could not restore old loans (starting fresh)</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
    }
    
    // =========================================
    // FIX 2: Ensure books columns exist
    // =========================================
    echo "<h2>Step 2: Fix Books Table</h2>";
    
    try {
        // Get existing columns
        $stmt = $pdo->query("SHOW COLUMNS FROM books");
        $existingColumns = array_column($stmt->fetchAll(), 'Field');
        
        // Add missing columns
        if (!in_array('available_copies', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN available_copies INT DEFAULT 1");
            echo "<p class='ok'>✓ Added available_copies</p>";
        }
        
        if (!in_array('total_copies', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN total_copies INT DEFAULT 1");
            echo "<p class='ok'>✓ Added total_copies</p>";
        }
        
        if (!in_array('status', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN status VARCHAR(20) DEFAULT 'AVAILABLE'");
            echo "<p class='ok'>✓ Added status</p>";
        }
        
        echo "<p class='ok'>✓ Books table structure OK</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
    }
    
    // =========================================
    // FIX 3: Set default values
    // =========================================
    echo "<h2>Step 3: Set Default Values</h2>";
    
    try {
        $pdo->exec("
            UPDATE books 
            SET 
                available_copies = COALESCE(available_copies, 1),
                total_copies = COALESCE(total_copies, 1),
                status = COALESCE(status, 'AVAILABLE')
        ");
        echo "<p class='ok'>✓ Set default values for all books</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
    }
    
    // =========================================
    // FIX 4: Calculate availability
    // =========================================
    echo "<h2>Step 4: Calculate Real Availability</h2>";
    
    try {
        // Get all books
        $stmt = $pdo->query("SELECT id, total_copies FROM books");
        $books = $stmt->fetchAll();
        
        foreach ($books as $book) {
            // Count active loans
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt 
                FROM loans 
                WHERE book_id = ? AND (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')
            ");
            $stmt->execute([$book['id']]);
            $result = $stmt->fetch();
            $activeLoans = $result['cnt'] ?? 0;
            
            $availableCopies = max(0, $book['total_copies'] - $activeLoans);
            $status = $availableCopies > 0 ? 'AVAILABLE' : 'UNAVAILABLE';
            
            // Update book
            $update = $pdo->prepare("UPDATE books SET available_copies = ?, status = ? WHERE id = ?");
            $update->execute([$availableCopies, $status, $book['id']]);
        }
        
        echo "<p class='ok'>✓ Updated availability for " . count($books) . " books</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
    }
    
    // =========================================
    // SHOW RESULTS
    // =========================================
    echo "<h2>✓ Summary</h2>";
    
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM books) as total_books,
            (SELECT COUNT(*) FROM books WHERE available_copies > 0) as available_books,
            (SELECT COUNT(*) FROM loans) as total_loans,
            (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL) as active_loans
    ")->fetch();
    
    echo "<pre>";
    echo "Total Books:      " . $stats['total_books'] . "\n";
    echo "Available Books:  " . $stats['available_books'] . "\n";
    echo "Total Loans:      " . $stats['total_loans'] . "\n";
    echo "Active Loans:     " . $stats['active_loans'] . "\n";
    echo "</pre>";
    
    // Sample books
    echo "<h2>Sample Books</h2>";
    $stmt = $pdo->query("SELECT id, title, available_copies, total_copies, status FROM books LIMIT 5");
    $sampleBooks = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5' style='color:#0f0; border-color:#0f0;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Available</th><th>Total</th><th>Status</th></tr>";
    foreach ($sampleBooks as $b) {
        echo "<tr>";
        echo "<td>{$b['id']}</td>";
        echo "<td>{$b['title']}</td>";
        echo "<td>{$b['available_copies']}</td>";
        echo "<td>{$b['total_copies']}</td>";
        echo "<td>{$b['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2 class='ok'>🎉 ALL DONE!</h2>";
    echo "<p>Now DELETE this file and test your system:</p>";
    echo "<p><a href='index.php?page=books_student' style='color:#0ff;'>Browse Books</a> | ";
    echo "<a href='index.php?page=dashboard_student' style='color:#0ff;'>Dashboard</a></p>";
    
    // Cleanup
    $pdo->exec("DROP TABLE IF EXISTS loans_backup_temp");
    
} catch (Exception $e) {
    echo "<p class='error'>FATAL ERROR: " . $e->getMessage() . "</p>";
    echo "<p>Check your config/db.php file</p>";
}

?>
</body>
</html>