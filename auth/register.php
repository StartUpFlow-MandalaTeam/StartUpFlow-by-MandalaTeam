<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$csrfToken = csrfToken();

$statusHtml = '';
$statusType = '';

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
        <link rel="stylesheet" href="../assets/css/style.css">
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
        <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    </head>
    <body>
        <div class="container">
            <div id="result" class="result-message <?php echo $statusClass; ?>" role="alert" aria-live="polite" aria-hidden="<?php echo $statusHtml !== '' ? 'false' : 'true'; ?>">
                <?php echo htmlspecialchars($statusHtml, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="form-box">
                <form action="process-register.php" method="POST" name="Formfill" onsubmit="return validation()" novalidate>
                    <h2>Register</h2>
                    <?php echo csrfInput(); ?>
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
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-lock-alt'></i>
                        <input type="password" name="CPassword" id="register-cpassword" placeholder=" ">
                        <label for="register-cpassword">Confirm Password</label>
                    </div>
                    <div class="button">
                        <input type="submit" class="btn" value="Sign up"></div>
                    <div class="group">
                        <span><a href="login.php">Already have an account? Log in here</a></span>
                    </div>
                </form>
            </div>
        </div>
        <script src="../assets/js/index.js" defer></script>
    </body>
</html>