<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// If the user is not logged in, show the registration page first
if (!isLoggedIn()) {
	header('Location: /auth/register.php');
	exit;
}

$username = htmlspecialchars($_SESSION['username'] ?? 'utente', ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Dashboard</title>
	<style>
		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #fff;
			font-family: Arial, sans-serif;
			color: #111;
		}

		.main {
			text-align: center;
		}

		h1 {
			margin: 0 0 20px;
			font-size: 28px;
		}

		button {
			padding: 10px 18px;
			border: 0;
			border-radius: 6px;
			background: #111;
			color: #fff;
			cursor: pointer;
			font-size: 16px;
		}
	</style>
</head>
<body>
	<div class="main">
		<h1>Ciao, <?php echo $username; ?></h1>
		<form action="auth/process-logout.php" method="post">
			<?php echo csrfInput(); ?>
			<button type="submit">Logout</button>
		</form>
	</div>
</body>
</html>
