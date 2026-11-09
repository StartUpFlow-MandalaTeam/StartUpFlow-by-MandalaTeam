<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

//Rate limit configuration; max attemps before temporary lock
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;

//Only accept POST requests for login processing to prevent CSRF and other issues
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Validate the CSRF token to protect against cross-site request forgery attacks
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: login.php?error=csrf');
    exit;
}

//retrieve submitted username and password from the POST data
$username = trim($_POST['Username'] ?? '');
$password = $_POST['Password'] ?? '';

// Ensure that the username and password are not empty
if ($username === '' || $password === '') {
    header('Location: login.php?error=empty');
    exit;
}

try {// Look up the user in the database by username
    $stmt = $pdo->prepare('
        SELECT id, username, email, password_hash, is_active, failed_login_attempts, locked_until
        FROM users
        WHERE username = :username
        LIMIT 1
    ');
    $stmt->execute([':username' => $username]);

    $user = $stmt->fetch();

    // If the account exists but is not active, deny login
    if ($user && (int) $user['is_active'] !== 1) {
        header('Location: login.php?error=unverified');
        exit;
    }

    // If account is temporarily locked due to too many failed login attempts, deny login
    if ($user && !empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        header('Location: login.php?error=locked');
        exit;
    }

    // Verify the password
    if (!$user || !password_verify($password, $user['password_hash'])) {
        if ($user) {// If the user exists, increment the failed login attempts and potentially lock the account
            $attempts = (int) $user['failed_login_attempts'] + 1;
            $lockUntil = null;

            if ($attempts >= LOGIN_MAX_ATTEMPTS) {//compute lockout time if max attempts exceeded
                $lockUntil = date('Y-m-d H:i:s', time() + (LOGIN_LOCK_MINUTES * 60));
            }

            // Persist failed attempt count and lock time
            $failed = $pdo->prepare('
                UPDATE users
                SET failed_login_attempts = :attempts,
                    locked_until = :locked_until
                WHERE id = :id
            ');
            $failed->execute([
                ':attempts' => $attempts,
                ':locked_until' => $lockUntil,
                ':id' => $user['id'],
            ]);

            // If the account was just locked, inform the user via redirect
            if ($lockUntil !== null) {
                header('Location: login.php?error=locked');
                exit;
            }
        }

        // Generic error message for invalid credentials to avoid revealing whether the username/email exists
        header('Location: login.php?error=invalid_credentials');
        exit;
    }

    // Successful authentication: create session and store minimal user info, regenerate session ID to prevent fixation attacks
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];

    // Fetch user's information through query and stores it in the session
    $userInfoQry = $pdo->prepare('SELECT first_name, last_name, birth_date, birth_place, birth_country, cf, address, city, postal_code
        FROM users_profiles
        WHERE user_id = :user_id
    ');
    $userInfoQry->execute([":user_id" => $user['id']]);
    $userInfo = $userInfoQry->fetch();
    $_SESSION['user_info_set'] = true;
    $_SESSION['first_name'] = $userInfo['first_name'];
    $_SESSION['last_name'] = $userInfo['last_name'];
    $_SESSION['birth_date'] = $userInfo['birth_date'];
    $_SESSION['birth_place'] = $userInfo['birth_place'];
    $_SESSION['birth_country'] = $userInfo['birth_country'];
    $_SESSION['cf'] = $userInfo['cf'];
    $_SESSION['address'] = $userInfo['address'];
    $_SESSION['city'] = $userInfo['city'];
    $_SESSION['postal_code'] = $userInfo['postal_code'];

    // If the "remember me" checkbox is checked, issue a remember-me token
    if (!empty($_POST['remember_me'])) {
        clearRememberMeToken();
        issueRememberMeToken((int) $user['id']);
    } else {//otherwise, clear any existing remember-me tokens for this user
        clearRememberMeToken();
    }

    // Reset counters and update last login timestamp
    $update = $pdo->prepare('
        UPDATE users
        SET last_login_at = NOW()
            , failed_login_attempts = 0
            , locked_until = NULL
        WHERE id = :id
    ');
    $update->execute([
        ':id' => $user['id'],
    ]);

    //Redirect to the dashboard
    header('Location: ../index.php');
    exit;
} catch (PDOException $e) {
    header('Location: login.php?error=database');
    exit;
}