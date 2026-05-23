<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$csrfToken = csrfToken();
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
            <div class="form-box">
                <?php if (isset($_GET['registered'])): ?>
                    <p id="result">Registration successful. Now log in.</p>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'exists'): ?>
                    <script>
                        alert('You are already registered. Please log in.');
                    </script>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
                    <p id="result">Security check failed. Please try again.</p>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'empty'): ?>
                    <p id="result">Please fill in both fields.</p>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
                    <p id="result">Invalid credentials.</p>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'locked'): ?>
                    <p id="result">Too many attempts. Try again later.</p>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'inactive'): ?>
                    <script>
                        alert('Your account is inactive. Contact support.');
                    </script>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'database'): ?>
                    <p id="result">Login temporarily unavailable.</p>
                <?php endif; ?>
                <form action="process-login.php" method="POST" name="Formfill" onsubmit="return loginValidation()">
                    <h2>Login</h2>
                    <?php echo csrfInput(); ?>
                    <div class="input-box">
                        <i class='bx bxs-user'></i>
                        <input type="text" name="Username" placeholder="Username">
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-lock-alt' ></i>
                        <input type="password" name="Password" placeholder="Password">
                    </div>
                    <div class="button">
                        <input type="submit" class="btn" value="Sign in">
                    </div>
                    <div class="group">
                        <span><a href="reset-password.php">Forget-Password?</a></span>
                    </div>
                </form>
            </div>
        </div>
        <script src="../assets/js/index.js" defer></script>
    </body>
</html>