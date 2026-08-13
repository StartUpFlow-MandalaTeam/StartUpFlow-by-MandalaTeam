<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This script must be run from the command line.\n");
	exit(1);
}

$tables = [
	'password_resets' => 'expires_at < NOW() OR used_at IS NOT NULL',
	'email_verifications' => 'expires_at < NOW() OR used_at IS NOT NULL',
	'remember_me_tokens' => 'expires_at < NOW() OR used_at IS NOT NULL',
];

$deletedCounts = [];

try {
	$pdo->beginTransaction();

	foreach ($tables as $table => $condition) {
		$stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$condition}");
		$stmt->execute();
		$deletedCounts[$table] = $stmt->rowCount();
	}

	$pdo->commit();

	foreach ($deletedCounts as $table => $count) {
		echo sprintf("%s: deleted %d expired/used token row(s)\n", $table, $count);
	}

	exit(0);
} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}

	fwrite(STDERR, 'Token cleanup failed: ' . $e->getMessage() . "\n");
	exit(1);
}