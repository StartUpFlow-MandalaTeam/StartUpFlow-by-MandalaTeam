<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// If the user is already logged in, redirect to the dashboard
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}
// create a CSRF token for the login form and store it in the session for later verification
$csrfToken = csrfToken();
// Check for status messages in the query parameters and prepare them for display
$statusMessage = '';
$statusType = '';

// Map URL flags to user-friendly messages and types for display in the login form
if (isset($_GET['registered'])) {
    $statusMessage = 'Registration successful. Check your email and activate your account within 24 hours.';
    $statusType = 'success';
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'exists':
            $statusMessage = 'You are already registered. Please log in.';
            break;
        case 'csrf':
            $statusMessage = 'Security check failed. Please try again.';
            break;
        case 'empty':
            $statusMessage = 'Please enter your username and password.';
            break;
        case 'invalid_credentials':
            $statusMessage = 'Invalid username or password.';
            break;
        case 'locked':
            $statusMessage = 'Your account is temporarily locked. Try again later.';
            break;
        case 'unverified':
            $statusMessage = 'Your account is not verified yet. Please check your email.';
            break;
        case 'verified':
            $statusMessage = 'Your email has been verified. You can log in now.';
            $statusType = 'success';
            break;
        case 'verification_invalid':
            $statusMessage = 'The verification link is invalid or has expired.';
            break;
        case 'inactive':
            $statusMessage = 'Your account is inactive. Contact support.';
            break;
        case 'database':
            $statusMessage = 'Login temporarily unavailable. Please try again.';
            break;
    }

    if ($statusMessage !== '' && $statusType === '') {
        $statusType = 'error';
    }
}
// Build CSS classes for the status message based on whether there is a message to show and its type (success or error)
$statusClass = '';
if ($statusMessage !== '') {
    $statusClass = 'is-visible ' . ($statusType === 'success' ? 'is-success' : 'is-error');
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Login</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>html{background:#dbad00;}</style>
        <link rel="preload" as="image" href="/assets/img/maxim-potyomkin-imviVpdI1OM-unsplash.jpg">
        <link rel="stylesheet" href="/assets/css/style.css">
        <link rel="stylesheet" href="/assets/css/page-transition.css">
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
        <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    </head>
    <body class="auth-page">
        <div class="container">
            <!-- Result banner: shows success or error messages to the user -->
            <div id="result" class="result-message <?php echo $statusClass; ?>" role="alert" aria-live="polite" aria-hidden="<?php echo $statusMessage !== '' ? 'false' : 'true'; ?>">
                <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="form-box">
                <form action="process-login.php" method="POST" name="Formfill" onsubmit="return loginValidation()">
                    <h2>Login</h2>
                    <?php echo csrfInput(); ?> <!-- Include a hidden input field with the CSRF token for security -->
                    <div class="input-box">
                        <i class='bx bxs-user'></i>
                        <input type="text" name="Username" id="login-username" placeholder=" ">
                        <label for="login-username">Username</label>
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-lock-alt' ></i>
                        <input type="password" name="Password" id="login-password" placeholder=" ">
                        <label for="login-password">Password</label>
                        <button type="button" class="password-toggle" data-password-toggle="login-password" aria-label="Mostra password" aria-pressed="false">
                            <i class="bx bx-show" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="button">
                        <input type="submit" class="btn" value="Sign in">
                    </div>
                    <!-- "Remember me" checkbox to allow users to stay logged in across sessions -->
                    <div class="remember-me">
                        <label>
                            <input type="checkbox" name="remember_me" value="1">
                            Remember me
                        </label>
                    </div>
                    <div class="group">
                        <!-- Link to password reset page for users who forgot their password -->
                        <span><a href="reset-password.php">Forgot password?</a></span>
                    </div>
                </form>
            </div>
        </div>
        <!-- Client-side validation and UI behavior -->
        <script src="/assets/js/index.js" defer></script>
    </body>
</html>