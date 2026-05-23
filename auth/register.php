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
            <div class="form-box">
                <form action="process-register.php" method="POST" name="Formfill" onsubmit="return validation()">
                    <h2>Register</h2>
                    <p id="result"></p>
                    <?php echo csrfInput(); ?>
                    <?php if (isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
                        <script>
                            alert('Security check failed. Please try again.');
                        </script>
                    <?php endif; ?>
                    <div class="input-box">
                        <i class='bx bxs-user'></i>
                        <input type="text" name="Username" placeholder="Username">
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-envelope'></i>
                        <input type="email" name="Email" placeholder="Email">
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-lock-alt' ></i>
                        <input type="password" name="Password" placeholder="Password">
                    </div>
                    <div class="input-box">
                        <i class='bx bxs-lock-alt'></i>
                        <input type="password" name="CPassword" placeholder="Confirm Password">
                    </div>
                    <div class="button">
                        <input type="submit" class="btn" value="Sign up"></div>
                    <div class="group">
                        <span><a href="login.php">Already have an account? Log in here</a></span>
                    </div>
                    
                </form>
            </div>
            <div class="popup" id="popup">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                <h2>Thank you!</h2>
                <p>You have registered successfully. Thanks!</p>
                <a href="login.php"><button type="button" class="popup-ok">OK</button></a>
            </div>
        </div>
        <script src="../assets/js/index.js" defer></script>
    </body>
</html>