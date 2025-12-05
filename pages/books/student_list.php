<?php
// pages/books/student_list.php

require_role(['STUDENT']);

$currentUser = get_current_user_data();
$notice = '';
$error  = '';

$search = trim($_GET['q'] ?? '');

// Handle borrow action
$action = $_GET['action'] ?? null;
$id     = $_GET['id'] ?? null;

if ($action === 'borrow' && $id && ctype_digit($id)) {
    $bookId = (int) $id;

    try {
        // 1. Check book exists and is available
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id");
        $stmt->execute(['id' => $bookId]);
        $book = $stmt->fetch();

        if (!$book) {
            $error = 'Book not found.';
        } elseif ($book['status'] !== 'AVAILABLE') {
            $error = 'This book is not available.';
        } else {
            // 2. Insert loan record
            $insertLoan = $pdo->prepare("
                INSERT INTO loans (user_id, book_id, borrowed_date, returned_date)
                VALUES (:uid, :bid, NOW(), NULL)
            ");
            $insertLoan->execute([
                'uid' => $currentUser['id'],
                'bid' => $bookId,
            ]);

            // 3. Update book status
            $updateBook = $pdo->prepare("
                UPDATE books SET status = 'BORROWED' WHERE id = :id
            ");
            $updateBook->execute(['id' => $bookId]);

            $notice = 'You have successfully borrowed the book: ' . htmlspecialchars($book['title']);
        }
    } catch (PDOException $e) {
        $error = 'Error borrowing book: ' . htmlspecialchars($e->getMessage());
    }
}

// Load books (with optional search)
try {
    if ($search !== '') {
        $like = '%' . $search . '%';

        $sql = "
            SELECT *
            FROM books
            WHERE title  LIKE :s1
               OR author LIKE :s2
               OR isbn   LIKE :s3
            ORDER BY title
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            's1' => $like,
            's2' => $like,
            's3' => $like,
        ]);
    } else {
        $stmt = $pdo->query("SELECT * FROM books ORDER BY title");
    }

    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error loading books: ' . htmlspecialchars($e->getMessage());
    $books = [];
}
?>

<h1 class="mb-4">Browse Books (Student)</h1>

<p class="mb-3">
    Logged in as: <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong>
</p>

<p>
    <a href="index.php?page=my_loans" class="btn btn-outline-primary">My Loans</a>
</p>

<form method="get" action="index.php" class="row g-3 mb-3">
    <input type="hidden" name="page" value="student_books">
    <div class="col-md-6 col-lg-4">
        <label for="search" class="form-label">Search by title, author, or ISBN</label>
        <input
            type="text"
            class="form-control"
            id="search"
            name="q"
            placeholder="e.g. fantasy, Tolkien, 9780..."
            value="<?php echo htmlspecialchars($search); ?>"
        >
    </div>
    <div class="col-md-3 col-lg-2 align-self-end">
        <button type="submit" class="btn btn-primary w-100">Search</button>
    </div>
    <div class="col-md-3 col-lg-2 align-self-end">
        <a href="index.php?page=student_books" class="btn btn-secondary w-100">Clear</a>
    </div>
</form>

<?php if ($search !== ''): ?>
    <p>
        Showing results for:
        <strong><?php echo htmlspecialchars($search); ?></strong>
    </p>
<?php endif; ?>

<?php if ($notice): ?>
    <div class="alert alert-success">
        <?php echo $notice; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (empty($books)): ?>
    <div class="alert alert-info">
        No books found.
    </div>
<?php else: ?>
    <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Cover</th>
                <th>Title & Author</th>
                <th>Category</th>
                <th>Status</th>
                <th style="width: 160px;">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($books as $index => $book): ?>
            <tr>
                <td><?php echo $index + 1; ?></td>
                <td style="width: 90px;">
                    <?php if (!empty($book['cover_url'])): ?>
                        <img
                            src="<?php echo htmlspecialchars($book['cover_url']); ?>"
                            alt="Cover"
                            style="max-height: 70px; max-width: 70px; object-fit: cover;"
                            class="rounded border"
                        >
                    <?php else: ?>
                        <span class="text-muted small">No image</span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($book['author']); ?></span><br>
                    <?php if (!empty($book['description'])): ?>
                        <span class="small">
                            <?php
                            $snippet = mb_substr($book['description'], 0, 90);
                            if (mb_strlen($book['description']) > 90) {
                                $snippet .= '...';
                            }
                            echo htmlspecialchars($snippet);
                            ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($book['category']); ?></td>
                <td>
                    <?php if ($book['status'] === 'AVAILABLE'): ?>
                        <span class="badge bg-success">Available</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Borrowed</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($book['status'] === 'AVAILABLE'): ?>
                        <a href="index.php?page=student_books&action=borrow&id=<?php echo $book['id']; ?>"
                           class="btn btn-sm btn-primary"
                           onclick="return confirm('Borrow this book?');">
                            Borrow
                        </a>
                    <?php else: ?>
                        <span class="text-muted">Not available</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
