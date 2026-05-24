<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$token = $_GET['token'] ?? null;
$csrf = csrfToken();

$statusMessage = '';
$statusType = '';
$debugLink = '';

if (isset($_GET['sent'])) {
    $statusMessage = 'If an account with that email exists, you will receive reset instructions.';
    $statusType = 'success';
    if (defined('APP_ENV') && APP_ENV === 'dev' && !empty($_GET['debug_link'])) {
        $debugLink = (string) $_GET['debug_link'];
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty':
            $statusMessage = 'Please complete all required fields.';
            break;
        case 'password_mismatch':
            $statusMessage = 'Passwords do not match.';
            break;
        case 'password_short':
            $statusMessage = 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.';
            break;
        case 'invalid_token':
            $statusMessage = 'Invalid or expired token.';
            break;
        case 'database':
            $statusMessage = 'Password reset temporarily unavailable. Please try again.';
            break;
        case 'csrf':
            $statusMessage = 'Security check failed. Please try again.';
            break;
    }

    if ($statusMessage !== '') {
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
        <title>Reset Password</title>
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
                <?php if ($debugLink !== ''): ?>
                    <span class="result-message__debug">Dev debug link: <a href="<?php echo htmlspecialchars($debugLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($debugLink, ENT_QUOTES, 'UTF-8'); ?></a></span>
                <?php endif; ?>
            </div>
            <div class="form-box">
                <?php if ($token): ?>
                    <form action="process-reset.php" method="POST" name="Formfill" onsubmit="return resetValidation()">
                        <h2>Set New Password</h2>
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="input-box">
                            <i class='bx bxs-lock-alt' ></i>
                            <input type="password" name="Password" id="reset-password" placeholder=" ">
                            <label for="reset-password">New Password</label>
                        </div>
                        <div class="input-box">
                            <i class='bx bxs-lock-alt'></i>
                            <input type="password" name="CPassword" id="reset-cpassword" placeholder=" ">
                            <label for="reset-cpassword">Confirm Password</label>
                        </div>
                        <div class="button">
                            <input type="submit" class="btn" value="Reset Password">
                        </div>
                    </form>
                <?php else: ?>
                    <form action="process-reset.php" method="POST" name="Formfill" onsubmit="return requestResetValidation()">
                        <h2>Password Reset</h2>
                        <?php echo csrfInput(); ?>
                        <div class="input-box">
                            <i class='bx bxs-envelope'></i>
                            <input type="email" name="email" id="reset-email" placeholder=" ">
                            <label for="reset-email">Email address</label>
                        </div>
                        <div class="button">
                            <input type="submit" class="btn" value="Send reset link">
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <script src="../assets/js/index.js" defer></script>
    </body>
</html>
