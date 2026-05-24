<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$csrfToken = csrfToken();

$statusMessage = '';
$statusType = '';

if (isset($_GET['registered'])) {
    $statusMessage = 'Registration successful. Check your email to activate your account.';
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
        <link rel="stylesheet" href="../assets/css/style.css">
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
        <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    </head>
    <body>
        <div class="container">
            <div id="result" class="result-message <?php echo $statusClass; ?>" role="alert" aria-live="polite" aria-hidden="<?php echo $statusMessage !== '' ? 'false' : 'true'; ?>">
                <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="form-box">
                <form action="process-login.php" method="POST" name="Formfill" onsubmit="return loginValidation()">
                    <h2>Login</h2>
                    <?php echo csrfInput(); ?>
                    <div class="input-box">
                        <i class='bx bxs-user'></i>
                        <input type="text" name="Username" id="login-username" placeholder=" ">
                        <label for="login-username">Username</label>
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-lock-alt' ></i>
                        <input type="password" name="Password" id="login-password" placeholder=" ">
                        <label for="login-password">Password</label>
                    </div>
                    <div class="button">
                        <input type="submit" class="btn" value="Sign in">
                    </div>
                    <div class="remember-me">
                        <label>
                            <input type="checkbox" name="remember_me" value="1">
                            Remember me
                        </label>
                    </div>
                    <div class="group">
                        <span><a href="reset-password.php">Forgot password?</a></span>
                    </div>
                </form>
            </div>
        </div>
        <script src="../assets/js/index.js" defer></script>
    </body>
</html>