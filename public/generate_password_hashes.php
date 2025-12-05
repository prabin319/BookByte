<?php
// public/generate_password_hashes.php
// This script will show password_hash() output for our sample users.

$adminPlain     = 'admin123';
$librarianPlain = 'librarian123';
$studentPlain   = 'student123';

echo "<h2>Generated Password Hashes</h2>";

echo "<p><strong>Admin (admin123):</strong><br>";
echo password_hash($adminPlain, PASSWORD_DEFAULT) . "</p>";

echo "<p><strong>Librarian (librarian123):</strong><br>";
echo password_hash($librarianPlain, PASSWORD_DEFAULT) . "</p>";

echo "<p><strong>Student (student123):</strong><br>";
echo password_hash($studentPlain, PASSWORD_DEFAULT) . "</p>";
