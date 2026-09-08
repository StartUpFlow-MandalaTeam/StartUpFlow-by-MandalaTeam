<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// If the user is already logged in, redirect to the dashboard
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}
// Create a CSRF token for the registration form and store it in the session for later verification
$csrfToken = csrfToken();

// Prepare user-visible status messages based on URL flags for display in the registration form
$statusHtml = '';
$statusType = '';

// Map URL flags to user-friendly messages and types for display in the registration form
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'csrf':
            $statusHtml = 'Security check failed. Please try again.';
            $statusType = 'error';
            break;
        case 'exists':
            $statusHtml = 'You are already registered!';
            $statusType = 'error';
            break;
        case 'email_exists':
            $statusHtml = 'This email address is already in use. Please use another email.';
            $statusType = 'error';
            break;
        case 'username_exists':
            $statusHtml = 'This username is already in use. Please choose another one.';
            $statusType = 'error';
            break;
        case 'empty':
            $statusHtml = 'Please complete all required fields.';
            $statusType = 'error';
            break;
        case 'username_short':
            $statusHtml = 'Username must be at least 6 characters.';
            $statusType = 'error';
            break;
        case 'username_invalid':
            $statusHtml = 'Username can contain only letters, numbers, and underscores.';
            $statusType = 'error';
            break;
        case 'email_invalid':
            $statusHtml = 'Please enter a valid email address.';
            $statusType = 'error';
            break;
        case 'password_short':
            $statusHtml = 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.';
            $statusType = 'error';
            break;
        case 'password_mismatch':
            $statusHtml = 'Passwords do not match.';
            $statusType = 'error';
            break;
        case 'database':
            $statusHtml = 'Registration temporarily unavailable. Please try again.';
            $statusType = 'error';
            break;
        case 'verification_email_failed':
            $statusHtml = 'Registration could not be completed because the verification email could not be sent. Please try again.';
            $statusType = 'error';
            break;
    }
}

// Build CSS classes for the status message based on whether there is a message to show and its type
$statusClass = '';
if ($statusHtml !== '') {
    $statusClass = 'is-visible ' . ($statusType === 'success' ? 'is-success' : 'is-error');
}

?>

<!DOCTYPE html>

<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title></title>
        <meta name="description" content="register and login">
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
            <div id="result" class="result-message <?php echo $statusClass; ?>" role="alert" aria-live="polite" aria-hidden="<?php echo $statusHtml !== '' ? 'false' : 'true'; ?>">
                <?php echo htmlspecialchars($statusHtml, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <!-- Registration form -->
            <div class="form-box">
                <form action="process-register.php" method="POST" name="Formfill" onsubmit="return validation()" novalidate>
                    <h2>Register</h2>
                    <?php echo csrfInput(); ?><!-- CSRF token for security -->
                    <div class="input-box">
                        <i class='bx bxs-user'></i>
                        <input type="text" name="Username" id="register-username" placeholder=" ">
                        <label for="register-username">Username</label>
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-envelope'></i>
                        <input type="email" name="Email" id="register-email" placeholder=" ">
                        <label for="register-email">Email</label>
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-lock-alt' ></i>
                        <input type="password" name="Password" id="register-password" placeholder=" ">
                        <label for="register-password">Password</label>
                        <button type="button" class="password-toggle" data-password-toggle="register-password" aria-label="Mostra password" aria-pressed="false">
                            <i class="bx bx-show" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-lock-alt'></i>
                        <input type="password" name="CPassword" id="register-cpassword" placeholder=" ">
                        <label for="register-cpassword">Confirm Password</label>
                        <button type="button" class="password-toggle" data-password-toggle="register-cpassword" aria-label="Mostra password" aria-pressed="false">
                            <i class="bx bx-show" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="button">
                        <input type="submit" class="btn" value="Sign up"></div>
                    <!-- Link to login page for users who already have an account -->
                    <div class="group">
                        <span><a href="login.php">Already have an account? <strong class="auth-login-link">Log in</strong> here</a></span>
                    </div>
                </form>
            </div>
        </div>
        <!-- Client-side validation and UI behavior -->
        <script src="/assets/js/index.js" defer></script>
    </body>
</html>