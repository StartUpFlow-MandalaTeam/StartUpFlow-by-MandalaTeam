<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
	$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	session_set_cookie_params([
		'lifetime' => 0,
		'path' => '/',
		'domain' => '',
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	ini_set('session.use_only_cookies', '1');
	ini_set('session.use_strict_mode', '1');
	session_start();
}

function isLoggedIn(): bool
{
	return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function requireLogin(): void
{
	if (!isLoggedIn()) {
		header('Location: /auth/login.php');
		exit;
	}
}

function currentUserId(): ?int
{
	return isLoggedIn() ? (int) $_SESSION['user_id'] : null;
}

function csrfToken(): string
{
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	return (string) $_SESSION['csrf_token'];
}

function csrfInput(): string
{
	return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrfToken(?string $token): bool
{
	return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function logoutUser(): void
{
	$_SESSION = [];

	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params['path'],
			$params['domain'],
			$params['secure'],
			$params['httponly']
		);
	}

	session_destroy();
}
