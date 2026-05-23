<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$token = $_GET['token'] ?? null;
$csrf = csrfToken();
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
            <div class="form-box">
                <?php if (isset($_GET['sent'])): ?>
                    <script>alert('If an account with that email exists, you will receive reset instructions.');</script>
                    <?php if (defined('APP_ENV') && APP_ENV === 'dev' && !empty($_GET['debug_link'])): ?>
                        <p id="debug">Dev debug link: <a href="<?php echo htmlspecialchars($_GET['debug_link'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($_GET['debug_link'], ENT_QUOTES, 'UTF-8'); ?></a></p>
                    <?php endif; ?>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'invalid_token'): ?>
                    <p id="result">Invalid or expired token.</p>
                <?php endif; ?>

                <?php if ($token): ?>
                    <form action="process-reset.php" method="POST" name="Formfill" onsubmit="return resetValidation()">
                        <h2>Set New Password</h2>
                        <p id="result"></p>
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="input-box">
                            <i class='bx bxs-lock-alt' ></i>
                            <input type="password" name="Password" placeholder="New Password">
                        </div>
                        <div class="input-box">
                            <i class='bx bxs-lock-alt'></i>
                            <input type="password" name="CPassword" placeholder="Confirm Password">
                        </div>
                        <div class="button">
                            <input type="submit" class="btn" value="Reset Password">
                        </div>
                    </form>
                <?php else: ?>
                    <form action="process-reset.php" method="POST" name="Formfill" onsubmit="return requestResetValidation()">
                        <h2>Password Reset</h2>
                        <p id="result"></p>
                        <?php echo csrfInput(); ?>
                        <div class="input-box">
                            <i class='bx bxs-envelope'></i>
                            <input type="email" name="email" placeholder="Your account email">
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
