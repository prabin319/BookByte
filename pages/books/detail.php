<?php
// htdocs/bookbyte/pages/books/detail.php

require_once __DIR__ . '/../../config/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Invalid book ID.</div></div>';
    return;
}

try {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM books WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $book = false;
    $error = "Error loading book: " . $e->getMessage();
}
?>

<div class="container mt-4">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!$book): ?>
        <div class="alert alert-warning">Book not found.</div>
        <a href="index.php?page=books" class="btn btn-secondary mt-2">Back to Book List</a>
    <?php else: ?>
        <div class="row">
            <div class="col-md-4">
                <?php if (!empty($book['cover_url'])): ?>
                    <img
                        src="<?php echo htmlspecialchars($book['cover_url']); ?>"
                        alt="Book cover for <?php echo htmlspecialchars($book['title']); ?>"
                        class="img-fluid rounded mb-3"
                    >
                <?php else: ?>
                    <div class="border bg-light d-flex align-items-center justify-content-center mb-3" style="height: 250px;">
                        <span class="text-muted">No cover available</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <h2><?php echo htmlspecialchars($book['title']); ?></h2>
                <h5 class="text-muted mb-3"><?php echo htmlspecialchars($book['author']); ?></h5>

                <p><strong>Category:</strong> <?php echo htmlspecialchars($book['category']); ?></p>
                <p><strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?></p>

                <?php if (!empty($book['publisher'])): ?>
                    <p><strong>Publisher:</strong> <?php echo htmlspecialchars($book['publisher']); ?></p>
                <?php endif; ?>

                <?php if (!empty($book['edition'])): ?>
                    <p><strong>Edition:</strong> <?php echo htmlspecialchars($book['edition']); ?></p>
                <?php endif; ?>

                <?php if (!empty($book['year_of_release'])): ?>
                    <p><strong>Year of Release:</strong> <?php echo htmlspecialchars($book['year_of_release']); ?></p>
                <?php endif; ?>

                <p><strong>Status:</strong> <?php echo htmlspecialchars($book['status']); ?></p>

                <?php if (!empty($book['description'])): ?>
                    <hr>
                    <h5>Description</h5>
                    <p><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
                <?php endif; ?>

                <?php if (!empty($book['review'])): ?>
                    <hr>
                    <h5>Review</h5>
                    <p><?php echo nl2br(htmlspecialchars($book['review'])); ?></p>
                <?php endif; ?>

                <a href="index.php?page=books" class="btn btn-secondary mt-3">Back to Book List</a>
            </div>
        </div>
    <?php endif; ?>
</div>
