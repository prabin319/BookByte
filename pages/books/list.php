<?php
// htdocs/bookbyte/pages/books/list.php

// Also show errors here (in case index.php settings are ignored)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

echo "<div class='container mt-4'>";
echo "<h2>Books Page Test</h2>";
echo "<p>If you can see this text, routing is working.</p>";

try {
    $pdo = getDBConnection();
    echo "<p><strong>DB connection OK.</strong></p>";

    $stmt = $pdo->query("SELECT id, title, author, isbn, status FROM books LIMIT 5");
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($books)) {
        echo "<div class='alert alert-info'>No books found in database.</div>";
    } else {
        echo "<table class='table table-bordered'>";
        echo "<thead><tr>
                <th>ID</th>
                <th>Title</th>
                <th>Author</th>
                <th>ISBN</th>
                <th>Status</th>
              </tr></thead><tbody>";

        foreach ($books as $b) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($b['id']) . "</td>";
            echo "<td>" . htmlspecialchars($b['title']) . "</td>";
            echo "<td>" . htmlspecialchars($b['author']) . "</td>";
            echo "<td>" . htmlspecialchars($b['isbn']) . "</td>";
            echo "<td>" . htmlspecialchars($b['status']) . "</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
    }

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";
?>
