<?php
// lib/auth.php

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Handle login form submission.
 *
 * Reads $_POST['email'] and $_POST['password'].
 * On success: sets $_SESSION['user'] and returns ''.
 * On failure: returns a human-readable error message.
 */
function handleLogin()
{
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($email === '' || $password === '') {
        return 'Please enter both email and password.';
    }

    try {
        $pdo = getDBConnection();

        // Be flexible: fetch all columns so we don’t depend on exact names
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return 'Invalid email or password.';
        }

        // Detect password column
        $passwordHash = null;
        if (isset($user['password_hash'])) {
            $passwordHash = $user['password_hash'];
        } elseif (isset($user['password'])) {
            $passwordHash = $user['password'];
        } elseif (isset($user['pwd_hash'])) {
            $passwordHash = $user['pwd_hash'];
        }

        if ($passwordHash === null) {
            // No password column we recognise
            return 'Login configuration error: no password column found for this user.';
        }

        // If hashes are stored with password_hash(), use password_verify
        $isValid = false;
        if (strlen($passwordHash) >= 20) {
            // probably a hash
            $isValid = password_verify($password, $passwordHash);
        } else {
            // probably plain text (not recommended, but handle just in case)
            $isValid = ($password === $passwordHash);
        }

        if (!$isValid) {
            return 'Invalid email or password.';
        }

        // Build full_name for the session
        if (isset($user['full_name']) && $user['full_name'] !== '') {
            $fullName = $user['full_name'];
        } elseif (isset($user['name']) && $user['name'] !== '') {
            $fullName = $user['name'];
        } else {
            $parts = [];
            if (isset($user['first_name']) && $user['first_name'] !== '') {
                $parts[] = $user['first_name'];
            }
            if (isset($user['last_name']) && $user['last_name'] !== '') {
                $parts[] = $user['last_name'];
            }
            if (!empty($parts)) {
                $fullName = implode(' ', $parts);
            } else {
                // Fallback: use email as display name
                $fullName = $user['email'] ?? $email;
            }
        }

        // Build role for the session
        if (isset($user['role']) && $user['role'] !== '') {
            $role = strtoupper($user['role']);
        } elseif (isset($user['user_role']) && $user['user_role'] !== '') {
            $role = strtoupper($user['user_role']);
        } elseif (isset($user['type']) && $user['type'] !== '') {
            $role = strtoupper($user['type']);
        } else {
            $role = 'STUDENT'; // safe default
        }

        // Normalise role values
        $allowedRoles = ['ADMIN', 'LIBRARIAN', 'STUDENT'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'STUDENT';
        }

        // Build the session user array (at least these keys)
        $sessionUser = [
            'id'        => isset($user['id']) ? $user['id'] : null,
            'full_name' => $fullName,
            'email'     => $user['email'] ?? $email,
            'role'      => $role,
        ];

        $_SESSION['user'] = $sessionUser;

        // success: no error message
        return '';
    } catch (PDOException $e) {
        // Log in real app; here we show friendly message
        return 'Login failed due to a database error.';
    } catch (Throwable $e) {
        return 'An unexpected error occurred during login.';
    }
}

/**
 * Check if a user is logged in.
 */
function isLoggedIn()
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

/**
 * Get the current logged-in user array or null.
 */
function currentUser()
{
    return isLoggedIn() ? $_SESSION['user'] : null;
}

/**
 * Require that a user is logged in, optionally with a specific role.
 * If not, redirect to login page.
 *
 * @param string|null $requiredRole e.g. 'ADMIN', 'LIBRARIAN', 'STUDENT'
 */
function requireLogin($requiredRole = null)
{
    if (!isLoggedIn()) {
        header('Location: /bookbyte/public/index.php?page=login');
        exit;
    }

    if ($requiredRole !== null) {
        $user = currentUser();
        $role = isset($user['role']) ? strtoupper($user['role']) : '';

        if ($role !== strtoupper($requiredRole)) {
            // Forbidden – simple redirect to default dashboard
            header('Location: /bookbyte/public/index.php');
            exit;
        }
    }
}

/**
 * Log out the current user.
 */
function handleLogout()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
