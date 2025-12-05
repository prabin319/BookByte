<?php
/**
 * Fix Book Availability Script
 * 
 * Place this file in: public/fix_availability.php
 * Run once by visiting: http://localhost/bookbyte/public/fix_availability.php
 * Then DELETE this file after running!
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fix Book Availability - BookByte</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f3f4f6;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .success {
            background: #dcfce7;
            border: 2px solid #86efac;
            color: #166534;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .error {
            background: #fee2e2;
            border: 2px solid #fca5a5;
            color: #b91c1c;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .info {
            background: #dbeafe;
            border: 2px solid #93c5fd;
            color: #1e40af;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
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
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        .badge-danger {
            background: #fee2e2;
            color: #b91c1c;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        .btn:hover {
            background: #5568d3;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Book Availability Status</h1>
        <p>This script will update all books to show correct availability based on active loans.</p>

<?php
try {
    $pdo = getDBConnection();
    
    echo '<div class="info"><strong>Step 1:</strong> Checking database structure...</div>';
    
    // Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM books");
    $columns = array_column($stmt->fetchAll(), 'Field');
    
    $hasAvailableCopies = in_array('available_copies', $columns);
    $hasTotalCopies = in_array('total_copies', $columns);
    $hasStatus = in_array('status', $columns);
    
    echo '<ul>';
    echo '<li><code>available_copies</code> column: ' . ($hasAvailableCopies ? '✓ Exists' : '✗ Missing') . '</li>';
    echo '<li><code>total_copies</code> column: ' . ($hasTotalCopies ? '✓ Exists' : '✗ Missing') . '</li>';
    echo '<li><code>status</code> column: ' . ($hasStatus ? '✓ Exists' : '✗ Missing') . '</li>';
    echo '</ul>';
    
    // Add missing columns
    if (!$hasAvailableCopies) {
        echo '<div class="info">Adding <code>available_copies</code> column...</div>';
        $pdo->exec("ALTER TABLE books ADD COLUMN available_copies INT DEFAULT 1");
        $hasAvailableCopies = true;
    }
    
    if (!$hasTotalCopies) {
        echo '<div class="info">Adding <code>total_copies</code> column...</div>';
        $pdo->exec("ALTER TABLE books ADD COLUMN total_copies INT DEFAULT 1");
        $hasTotalCopies = true;
    }
    
    if (!$hasStatus) {
        echo '<div class="info">Adding <code>status</code> column...</div>';
        $pdo->exec("ALTER TABLE books ADD COLUMN status ENUM('AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE', 'LOST') DEFAULT 'AVAILABLE'");
        $hasStatus = true;
    }
    
    echo '<div class="success"><strong>✓ Step 1 Complete:</strong> Database structure is ready!</div>';
    
    // Step 2: Set default values
    echo '<div class="info"><strong>Step 2:</strong> Setting default values for books...</div>';
    
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
            END
    ");
    
    echo '<div class="success"><strong>✓ Step 2 Complete:</strong> Default values set!</div>';
    
    // Step 3: Calculate active loans
    echo '<div class="info"><strong>Step 3:</strong> Calculating active loans...</div>';
    
    // Get loan counts per book
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
    
    echo '<div class="success"><strong>✓ Step 3 Complete:</strong> Found ' . count($loanCounts) . ' books with active loans!</div>';
    
    // Step 4: Update availability
    echo '<div class="info"><strong>Step 4:</strong> Updating book availability...</div>';
    
    $stmt = $pdo->query("SELECT id, title, total_copies FROM books");
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
    
    echo '<div class="success"><strong>✓ Step 4 Complete:</strong> Updated ' . $updated . ' books!</div>';
    
    // Step 5: Show results
    echo '<h2>📊 Updated Books Summary</h2>';
    
    $stmt = $pdo->query("
        SELECT 
            id,
            title,
            available_copies,
            total_copies,
            status,
            (SELECT COUNT(*) FROM loans WHERE book_id = books.id AND (returned_at IS NULL OR returned_at = '0000-00-00 00:00:00')) as active_loans
        FROM books
        ORDER BY title
        LIMIT 20
    ");
    
    $results = $stmt->fetchAll();
    
    if (!empty($results)) {
        echo '<table>';
        echo '<thead><tr>';
        echo '<th>Title</th>';
        echo '<th>Available</th>';
        echo '<th>Total</th>';
        echo '<th>Active Loans</th>';
        echo '<th>Status</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($results as $row) {
            $statusBadge = $row['status'] === 'AVAILABLE' ? 'badge-success' : 'badge-danger';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['title']) . '</td>';
            echo '<td>' . $row['available_copies'] . '</td>';
            echo '<td>' . $row['total_copies'] . '</td>';
            echo '<td>' . $row['active_loans'] . '</td>';
            echo '<td><span class="badge ' . $statusBadge . '">' . $row['status'] . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    // Statistics
    echo '<h2>📈 Statistics</h2>';
    
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_books,
            SUM(CASE WHEN available_copies > 0 THEN 1 ELSE 0 END) as available_books,
            SUM(CASE WHEN available_copies = 0 THEN 1 ELSE 0 END) as unavailable_books,
            (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL OR returned_at = '0000-00-00 00:00:00') as active_loans
        FROM books
    ")->fetch();
    
    echo '<table>';
    echo '<tr><th>Total Books</th><td>' . $stats['total_books'] . '</td></tr>';
    echo '<tr><th>Available Books</th><td><span class="badge badge-success">' . $stats['available_books'] . '</span></td></tr>';
    echo '<tr><th>Unavailable Books</th><td><span class="badge badge-danger">' . $stats['unavailable_books'] . '</span></td></tr>';
    echo '<tr><th>Active Loans</th><td>' . $stats['active_loans'] . '</td></tr>';
    echo '</table>';
    
    echo '<div class="success" style="margin-top: 30px;">';
    echo '<strong>🎉 All Done!</strong><br>';
    echo 'All books have been updated with correct availability status.<br><br>';
    echo '<strong>Important:</strong> Please delete this file (<code>public/fix_availability.php</code>) for security.';
    echo '</div>';
    
    echo '<p style="margin-top: 20px;">';
    echo '<a href="index.php?page=books_manage" class="btn">Go to Manage Books</a> ';
    echo '<a href="index.php?page=books_student" class="btn">Go to Browse Books</a>';
    echo '</p>';
    
} catch (PDOException $e) {
    echo '<div class="error">';
    echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}
?>

    </div>
</body>
</html>