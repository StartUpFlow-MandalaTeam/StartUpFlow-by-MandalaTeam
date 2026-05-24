<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

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

const REMEMBER_ME_COOKIE = 'remember_me';
const REMEMBER_ME_TOKEN_BYTES = 32;
const REMEMBER_ME_SELECTOR_BYTES = 12;
const REMEMBER_ME_DAYS = 30;

function rememberMeCookieOptions(int $expires): array
{
	$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

	return [
		'expires' => $expires,
		'path' => '/',
		'domain' => '',
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax',
	];
}

function clearRememberMeCookie(): void
{
	setcookie(REMEMBER_ME_COOKIE, '', rememberMeCookieOptions(time() - 3600));
}

function rememberMeTokenDigest(string $token): string
{
	return hash('sha256', $token);
}

function issueRememberMeToken(int $userId): void
{
	global $pdo;

	$selector = bin2hex(random_bytes(REMEMBER_ME_SELECTOR_BYTES));
	$validator = bin2hex(random_bytes(REMEMBER_ME_TOKEN_BYTES));
	$tokenHash = rememberMeTokenDigest($validator);
	$expiresAt = date('Y-m-d H:i:s', time() + (REMEMBER_ME_DAYS * 86400));

	$stmt = $pdo->prepare('INSERT INTO remember_me_tokens (user_id, selector, token_hash, expires_at) VALUES (:user_id, :selector, :token_hash, :expires_at)');
	$stmt->execute([
		':user_id' => $userId,
		':selector' => $selector,
		':token_hash' => $tokenHash,
		':expires_at' => $expiresAt,
	]);

	setcookie(REMEMBER_ME_COOKIE, $selector . ':' . $validator, rememberMeCookieOptions(time() + (REMEMBER_ME_DAYS * 86400)));
}

function clearRememberMeToken(?string $cookieValue = null): void
{
	global $pdo;

	$value = $cookieValue ?? ($_COOKIE[REMEMBER_ME_COOKIE] ?? '');
	if (!is_string($value) || $value === '') {
		clearRememberMeCookie();
		return;
	}

	[$selector] = array_pad(explode(':', $value, 2), 2, '');
	if ($selector !== '') {
		$stmt = $pdo->prepare('DELETE FROM remember_me_tokens WHERE selector = :selector');
		$stmt->execute([':selector' => $selector]);
	}

	clearRememberMeCookie();
}

function loginUserFromRow(array $user): void
{
	session_regenerate_id(true);
	$_SESSION['user_id'] = (int) $user['id'];
	$_SESSION['username'] = $user['username'];
}

function tryAutoLoginFromRememberMe(): void
{
	global $pdo;

	if (isLoggedIn()) {
		return;
	}

	$cookie = $_COOKIE[REMEMBER_ME_COOKIE] ?? '';
	if (!is_string($cookie) || $cookie === '' || !str_contains($cookie, ':')) {
		return;
	}

	[$selector, $validator] = explode(':', $cookie, 2);
	if ($selector === '' || $validator === '') {
		clearRememberMeCookie();
		return;
	}

	$stmt = $pdo->prepare('
		SELECT rt.id AS token_id, rt.user_id, rt.token_hash, rt.expires_at, rt.used_at, u.username, u.is_active
		FROM remember_me_tokens rt
		JOIN users u ON u.id = rt.user_id
		WHERE rt.selector = :selector
		LIMIT 1
	');
	$stmt->execute([':selector' => $selector]);
	$token = $stmt->fetch();

	if (!$token || (int) $token['is_active'] !== 1 || !empty($token['used_at']) || strtotime($token['expires_at']) < time()) {
		clearRememberMeToken($cookie);
		return;
	}

	if (!hash_equals((string) $token['token_hash'], rememberMeTokenDigest($validator))) {
		clearRememberMeToken($cookie);
		return;
	}

	$pdo->beginTransaction();

	$delete = $pdo->prepare('DELETE FROM remember_me_tokens WHERE id = :id');
	$delete->execute([':id' => $token['token_id']]);

	loginUserFromRow([
		'id' => $token['user_id'],
		'username' => $token['username'],
	]);

	issueRememberMeToken((int) $token['user_id']);

	$pdo->commit();
}

tryAutoLoginFromRememberMe();

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
	clearRememberMeToken();
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
