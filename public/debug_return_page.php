<?php
/**
 * Debug Return Page Issues
 * 
 * Place in: public/debug_return_page.php
 * Visit: http://localhost/bookbyte/public/debug_return_page.php
 * DELETE after fixing!
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug Return Page</title>
    <style>
        body { font-family: system-ui; background: #0f172a; color: #e2e8f0; padding: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #60a5fa; font-size: 32px; margin-bottom: 20px; }
        h2 { color: #34d399; margin: 30px 0 15px; font-size: 24px; }
        h3 { color: #fbbf24; margin: 20px 0 10px; font-size: 18px; }
        .section { background: #1e293b; border-radius: 12px; padding: 20px; margin: 15px 0; border-left: 4px solid #60a5fa; }
        .success { border-left-color: #10b981; background: #064e3b; }
        .error { border-left-color: #ef4444; background: #7f1d1d; }
        .warning { border-left-color: #f59e0b; background: #78350f; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; background: #0f172a; border-radius: 8px; overflow: hidden; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #1e293b; font-weight: 600; color: #60a5fa; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-ok { background: #10b981; color: white; }
        .badge-error { background: #ef4444; color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        code { background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 4px; font-family: 'Courier New', monospace; color: #fbbf24; }
        pre { background: #0f172a; padding: 15px; border-radius: 8px; overflow-x: auto; margin: 10px 0; font-size: 13px; }
        .btn { display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 5px 5px 0 0; }
        .btn:hover { background: #2563eb; }
        ul { margin: 10px 0 10px 25px; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Return Page - Why No Books Showing?</h1>

<?php
try {
    $pdo = getDBConnection();
    
    // Check if user is logged in
    if (!isLoggedIn()) {
        echo '<div class="section error">';
        echo '<h2>❌ Not Logged In</h2>';
        echo '<p>You must be logged in as a STUDENT to test this page.</p>';
        echo '<p><a href="../public/index.php?page=login" class="btn">Go to Login</a></p>';
        echo '</div>';
        echo '</div></body></html>';
        exit;
    }
    
    $user = currentUser();
    $userId = $user['id'] ?? null;
    
    echo '<div class="section success">';
    echo '<h2>✓ User Information</h2>';
    echo '<table>';
    echo '<tr><th>Property</th><th>Value</th></tr>';
    echo '<tr><td>User ID</td><td>' . htmlspecialchars($userId) . '</td></tr>';
    echo '<tr><td>Name</td><td>' . htmlspecialchars($user['full_name'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td>Email</td><td>' . htmlspecialchars($user['email'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td>Role</td><td>' . htmlspecialchars($user['role'] ?? 'N/A') . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    // ==========================================
    // CHECK 1: Loans table structure
    // ==========================================
    echo '<h2>1️⃣ Loans Table Structure</h2>';
    echo '<div class="section">';
    
    $stmt = $pdo->query("SHOW COLUMNS FROM loans");
    $loanColumns = $stmt->fetchAll();
    
    echo '<table>';
    echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>';
    foreach ($loanColumns as $col) {
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars($col['Field']) . '</code></td>';
        echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
        echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
        echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    $columnNames = array_column($loanColumns, 'Field');
    $hasReturnedAt = in_array('returned_at', $columnNames);
    $hasReturnedDate = in_array('returned_date', $columnNames);
    $hasLoanDate = in_array('loan_date', $columnNames);
    $hasBorrowDate = in_array('borrow_date', $columnNames);
    
    echo '<p><strong>Column Mapping:</strong></p>';
    echo '<ul>';
    echo '<li><code>returned_at</code>: ' . ($hasReturnedAt ? '<span class="badge badge-ok">EXISTS</span>' : '<span class="badge badge-error">MISSING</span>') . '</li>';
    echo '<li><code>returned_date</code>: ' . ($hasReturnedDate ? '<span class="badge badge-ok">EXISTS</span>' : '<span class="badge badge-warning">Not found</span>') . '</li>';
    echo '<li><code>loan_date</code>: ' . ($hasLoanDate ? '<span class="badge badge-ok">EXISTS</span>' : '<span class="badge badge-warning">Not found</span>') . '</li>';
    echo '<li><code>borrow_date</code>: ' . ($hasBorrowDate ? '<span class="badge badge-ok">EXISTS</span>' : '<span class="badge badge-warning">Not found</span>') . '</li>';
    echo '</ul>';
    
    echo '</div>';
    
    // ==========================================
    // CHECK 2: Total loans for this user
    // ==========================================
    echo '<h2>2️⃣ Total Loans for User ID: ' . $userId . '</h2>';
    echo '<div class="section">';
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM loans WHERE user_id = :uid");
    $stmt->execute(['uid' => $userId]);
    $totalLoans = $stmt->fetch()['total'];
    
    echo '<p><strong>Total loans (all time):</strong> ' . $totalLoans . '</p>';
    
    if ($totalLoans == 0) {
        echo '<p class="badge badge-error">⚠️ No loans found for this user! User needs to borrow books first.</p>';
    }
    
    echo '</div>';
    
    // ==========================================
    // CHECK 3: Show ALL loans for this user
    // ==========================================
    echo '<h2>3️⃣ ALL Loans for This User (Raw Data)</h2>';
    echo '<div class="section">';
    
    if ($totalLoans > 0) {
        $stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = :uid ORDER BY id DESC");
        $stmt->execute(['uid' => $userId]);
        $allLoans = $stmt->fetchAll();
        
        echo '<table>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Book ID</th>';
        foreach ($columnNames as $col) {
            if (!in_array($col, ['id', 'user_id', 'book_id'])) {
                echo '<th>' . htmlspecialchars($col) . '</th>';
            }
        }
        echo '</tr>';
        
        foreach ($allLoans as $loan) {
            echo '<tr>';
            echo '<td>' . $loan['id'] . '</td>';
            echo '<td>' . $loan['book_id'] . '</td>';
            foreach ($columnNames as $col) {
                if (!in_array($col, ['id', 'user_id', 'book_id'])) {
                    $value = $loan[$col] ?? 'NULL';
                    if ($value === null || $value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
                        $value = '<span class="badge badge-warning">NULL/Empty</span>';
                    }
                    echo '<td>' . $value . '</td>';
                }
            }
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p class="badge badge-error">No loans to display - user has never borrowed any books</p>';
    }
    
    echo '</div>';
    
    // ==========================================
    // CHECK 4: Test different return queries
    // ==========================================
    echo '<h2>4️⃣ Testing Different Return Queries</h2>';
    
    // Try Query 1: Using returned_at
    echo '<div class="section">';
    echo '<h3>Query 1: Using <code>returned_at IS NULL</code></h3>';
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt
            FROM loans 
            WHERE user_id = :uid 
            AND returned_at IS NULL
        ");
        $stmt->execute(['uid' => $userId]);
        $count1 = $stmt->fetch()['cnt'];
        echo '<p>Books found: <strong>' . $count1 . '</strong></p>';
        
        if ($count1 > 0) {
            echo '<p class="badge badge-ok">✓ This query works!</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';
    
    // Try Query 2: Using returned_at with zero dates
    echo '<div class="section">';
    echo '<h3>Query 2: Using <code>returned_at IS NULL OR returned_at = \'0000-00-00 00:00:00\'</code></h3>';
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt
            FROM loans 
            WHERE user_id = :uid 
            AND (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute(['uid' => $userId]);
        $count2 = $stmt->fetch()['cnt'];
        echo '<p>Books found: <strong>' . $count2 . '</strong></p>';
        
        if ($count2 > 0) {
            echo '<p class="badge badge-ok">✓ This query works!</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';
    
    // Try Query 3: Using returned_date
    echo '<div class="section">';
    echo '<h3>Query 3: Using <code>returned_date IS NULL</code></h3>';
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt
            FROM loans 
            WHERE user_id = :uid 
            AND returned_date IS NULL
        ");
        $stmt->execute(['uid' => $userId]);
        $count3 = $stmt->fetch()['cnt'];
        echo '<p>Books found: <strong>' . $count3 . '</strong></p>';
        
        if ($count3 > 0) {
            echo '<p class="badge badge-ok">✓ This query works!</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';
    
    // ==========================================
    // CHECK 5: Show full query with books
    // ==========================================
    echo '<h2>5️⃣ Full Query with Book Details</h2>';
    echo '<div class="section">';
    
    try {
        // Try to detect the correct column name
        $returnColumn = 'returned_at';
        if (!$hasReturnedAt && $hasReturnedDate) {
            $returnColumn = 'returned_date';
        }
        
        $sql = "
            SELECT 
                l.id,
                l.book_id,
                l.user_id,
                l.$returnColumn as returned,
                b.title as book_title,
                b.author as book_author
            FROM loans l
            LEFT JOIN books b ON l.book_id = b.id
            WHERE l.user_id = :uid
            AND (l.$returnColumn IS NULL OR l.$returnColumn = '0000-00-00 00:00:00')
            ORDER BY l.id DESC
        ";
        
        echo '<p><strong>SQL Query Used:</strong></p>';
        echo '<pre>' . htmlspecialchars($sql) . '</pre>';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        $results = $stmt->fetchAll();
        
        echo '<p><strong>Results found:</strong> ' . count($results) . '</p>';
        
        if (count($results) > 0) {
            echo '<p class="badge badge-ok">✓ Books found!</p>';
            echo '<table>';
            echo '<tr><th>Loan ID</th><th>Book ID</th><th>Book Title</th><th>Author</th><th>Returned</th></tr>';
            foreach ($results as $row) {
                echo '<tr>';
                echo '<td>' . $row['id'] . '</td>';
                echo '<td>' . $row['book_id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['book_title'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['book_author'] ?? 'N/A') . '</td>';
                echo '<td>' . ($row['returned'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="badge badge-error">❌ No unreturned books found</p>';
        }
        
    } catch (PDOException $e) {
        echo '<p class="badge badge-error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    
    echo '</div>';
    
    // ==========================================
    // SOLUTION
    // ==========================================
    echo '<h2>6️⃣ Solution & Fix</h2>';
    echo '<div class="section warning">';
    
    if ($totalLoans == 0) {
        echo '<h3>⚠️ Problem: User Has No Loans</h3>';
        echo '<p><strong>Solution:</strong> User needs to borrow books first!</p>';
        echo '<ol>';
        echo '<li>Go to <a href="../public/index.php?page=books_student" class="btn">Browse Books</a></li>';
        echo '<li>Click on any book</li>';
        echo '<li>Click "Borrow This Book"</li>';
        echo '<li>Then come back to <a href="../public/index.php?page=student_returns" class="btn">Return Books</a></li>';
        echo '</ol>';
    } else {
        // Check if all books are already returned
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM loans 
            WHERE user_id = :uid 
            AND (returned_at IS NOT NULL AND returned_at != '0000-00-00 00:00:00')
        ");
        $stmt->execute(['uid' => $userId]);
        $returnedCount = $stmt->fetch()['cnt'];
        
        if ($returnedCount == $totalLoans) {
            echo '<h3>✓ All Books Already Returned</h3>';
            echo '<p>User has ' . $totalLoans . ' loan(s) but all are already returned.</p>';
            echo '<p><strong>Solution:</strong> Borrow new books to test the return page.</p>';
        } else {
            echo '<h3>🔧 Column Name Issue</h3>';
            echo '<p>The return page is looking for the wrong column name.</p>';
            echo '<p><strong>Your database uses:</strong> <code>' . htmlspecialchars($returnColumn) . '</code></p>';
        }
    }
    
    echo '</div>';
    
    // Quick actions
    echo '<div class="section">';
    echo '<h3>Quick Actions</h3>';
    echo '<a href="../public/index.php?page=books_student" class="btn">📚 Browse Books</a> ';
    echo '<a href="../public/index.php?page=student_returns" class="btn">📥 Return Books Page</a> ';
    echo '<a href="../public/index.php?page=loans_my" class="btn">📄 My Loans</a> ';
    echo '<a href="../public/index.php?page=dashboard_student" class="btn">🏠 Dashboard</a>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="section error">';
    echo '<h2>❌ Database Error</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '</div>';
}
?>

    </div>
</body>
</html>