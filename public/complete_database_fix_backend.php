<?php
/**
 * Complete Database Fix - Backend (Foreign Key Safe Version)
 * 
 * This file does the actual database modifications
 * Place as: public/complete_database_fix_backend.php
 * DELETE after use!
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$results = [
    'overall_success' => false,
    'steps' => []
];

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ==========================================
    // STEP 1: Backup existing loans table
    // ==========================================
    try {
        $pdo->exec("DROP TABLE IF EXISTS loans_backup_emergency");
        $pdo->exec("CREATE TABLE loans_backup_emergency AS SELECT * FROM loans");
        
        $results['steps'][] = [
            'success' => true,
            'icon' => '💾',
            'title' => 'Step 1: Backup Created',
            'message' => '<p>Successfully backed up existing loans table to <code>loans_backup_emergency</code></p>'
        ];
    } catch (Exception $e) {
        $results['steps'][] = [
            'success' => false,
            'icon' => '❌',
            'title' => 'Step 1: Backup Failed',
            'message' => '<p>Could not create backup: ' . htmlspecialchars($e->getMessage()) . '</p>'
        ];
        throw $e;
    }
    
    // ==========================================
    // STEP 2: Fix loans table structure
    // ==========================================
    try {
        // DISABLE FOREIGN KEY CHECKS
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Drop the old loans table
        $pdo->exec("DROP TABLE IF EXISTS loans");
        
        // Create new loans table with correct structure
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_id (user_id),
            KEY idx_book_id (book_id),
            KEY idx_returned_at (returned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createLoans);
        
        // Restore data from backup
        $stmt = $pdo->query("SELECT * FROM loans_backup_emergency");
        $oldLoans = $stmt->fetchAll();
        
        $restored = 0;
        foreach ($oldLoans as $loan) {
            // Map old column names to new ones
            $loanDate = $loan['loan_date'] ?? $loan['borrow_date'] ?? $loan['borrowed_date'] ?? $loan['issued_date'] ?? date('Y-m-d');
            $dueDate = $loan['due_date'] ?? $loan['return_date'] ?? $loan['expected_return_date'] ?? date('Y-m-d', strtotime('+14 days'));
            $returnedAt = $loan['returned_at'] ?? $loan['return_date'] ?? $loan['returned_date'] ?? $loan['actual_return_date'] ?? null;
            
            // Clean up returned_at value
            if ($returnedAt === '0000-00-00' || $returnedAt === '0000-00-00 00:00:00' || $returnedAt === '') {
                $returnedAt = null;
            }
            
            $insert = $pdo->prepare("
                INSERT INTO loans (user_id, book_id, loan_date, due_date, returned_at, renewal_count)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $insert->execute([
                $loan['user_id'] ?? 1,
                $loan['book_id'] ?? 1,
                $loanDate,
                $dueDate,
                $returnedAt,
                $loan['renewal_count'] ?? 0
            ]);
            $restored++;
        }
        
        // RE-ENABLE FOREIGN KEY CHECKS
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $results['steps'][] = [
            'success' => true,
            'icon' => '✅',
            'title' => 'Step 2: Loans Table Fixed',
            'message' => "<p>Successfully recreated loans table and restored <strong>{$restored}</strong> loan records</p>"
        ];
    } catch (Exception $e) {
        // Make sure to re-enable foreign key checks even on error
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $results['steps'][] = [
            'success' => false,
            'icon' => '❌',
            'title' => 'Step 2: Loans Table Fix Failed',
            'message' => '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>'
        ];
        throw $e;
    }
    
    // ==========================================
    // STEP 3: Fix books table structure
    // ==========================================
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM books");
        $existingColumns = array_column($stmt->fetchAll(), 'Field');
        
        $changes = [];
        
        if (!in_array('available_copies', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN available_copies INT DEFAULT 1");
            $changes[] = 'available_copies';
        }
        
        if (!in_array('total_copies', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN total_copies INT DEFAULT 1");
            $changes[] = 'total_copies';
        }
        
        if (!in_array('status', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN status VARCHAR(20) DEFAULT 'AVAILABLE'");
            $changes[] = 'status';
        }
        
        if (!in_array('cover_url', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN cover_url VARCHAR(500) NULL");
            $changes[] = 'cover_url';
        }
        
        if (!in_array('description', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN description TEXT NULL");
            $changes[] = 'description';
        }
        
        if (!in_array('publisher', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN publisher VARCHAR(255) NULL");
            $changes[] = 'publisher';
        }
        
        if (!in_array('year_of_release', $existingColumns)) {
            $pdo->exec("ALTER TABLE books ADD COLUMN year_of_release INT NULL");
            $changes[] = 'year_of_release';
        }
        
        // Set default values
        $pdo->exec("
            UPDATE books 
            SET 
                available_copies = COALESCE(NULLIF(available_copies, 0), 1),
                total_copies = COALESCE(NULLIF(total_copies, 0), 1),
                status = COALESCE(status, 'AVAILABLE')
        ");
        
        $message = empty($changes) 
            ? '<p>Books table structure was already correct</p>'
            : '<p>Added columns: <code>' . implode('</code>, <code>', $changes) . '</code></p>';
        
        $results['steps'][] = [
            'success' => true,
            'icon' => '📚',
            'title' => 'Step 3: Books Table Fixed',
            'message' => $message
        ];
    } catch (Exception $e) {
        $results['steps'][] = [
            'success' => false,
            'icon' => '❌',
            'title' => 'Step 3: Books Table Fix Failed',
            'message' => '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>'
        ];
        throw $e;
    }
    
    // ==========================================
    // STEP 4: Calculate real availability
    // ==========================================
    try {
        $stmt = $pdo->query("SELECT id, total_copies FROM books");
        $books = $stmt->fetchAll();
        
        $updated = 0;
        foreach ($books as $book) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt 
                FROM loans 
                WHERE book_id = ? AND returned_at IS NULL
            ");
            $stmt->execute([$book['id']]);
            $activeLoans = $stmt->fetch()['cnt'];
            
            $available = max(0, $book['total_copies'] - $activeLoans);
            $status = $available > 0 ? 'AVAILABLE' : 'UNAVAILABLE';
            
            $update = $pdo->prepare("
                UPDATE books 
                SET available_copies = ?, status = ? 
                WHERE id = ?
            ");
            $update->execute([$available, $status, $book['id']]);
            $updated++;
        }
        
        $results['steps'][] = [
            'success' => true,
            'icon' => '📊',
            'title' => 'Step 4: Availability Calculated',
            'message' => "<p>Updated availability for <strong>{$updated}</strong> books based on active loans</p>"
        ];
    } catch (Exception $e) {
        $results['steps'][] = [
            'success' => false,
            'icon' => '❌',
            'title' => 'Step 4: Availability Calculation Failed',
            'message' => '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>'
        ];
        throw $e;
    }
    
    // ==========================================
    // STEP 5: Final verification
    // ==========================================
    try {
        $stats = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM books) as total_books,
                (SELECT COUNT(*) FROM books WHERE available_copies > 0) as available_books,
                (SELECT COUNT(*) FROM loans) as total_loans,
                (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL) as active_loans
        ")->fetch();
        
        $message = "
            <table>
                <tr><th>Metric</th><th>Count</th></tr>
                <tr><td>Total Books</td><td>{$stats['total_books']}</td></tr>
                <tr><td>Available Books</td><td><span class='badge badge-success'>{$stats['available_books']}</span></td></tr>
                <tr><td>Total Loans</td><td>{$stats['total_loans']}</td></tr>
                <tr><td>Active Loans</td><td><span class='badge badge-warning'>{$stats['active_loans']}</span></td></tr>
            </table>
        ";
        
        $results['steps'][] = [
            'success' => true,
            'icon' => '✅',
            'title' => 'Step 5: Verification Complete',
            'message' => $message
        ];
        
        $results['overall_success'] = true;
        
    } catch (Exception $e) {
        $results['steps'][] = [
            'success' => false,
            'icon' => '❌',
            'title' => 'Step 5: Verification Failed',
            'message' => '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>'
        ];
    }
    
} catch (Exception $e) {
    $results['overall_success'] = false;
    // Make sure foreign key checks are re-enabled
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $e2) {
        // Ignore
    }
}

echo json_encode($results);