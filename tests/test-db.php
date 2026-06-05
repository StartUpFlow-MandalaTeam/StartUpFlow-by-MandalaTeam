<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

try {
    $stmt = $pdo->query('SELECT 1 AS ok');
    $row = $stmt->fetch();

    if ($row && isset($row['ok']) && (int) $row['ok'] === 1) {
        echo 'Database connection OK';
    } else {
        echo 'Database connection failed: unexpected query result';
    }
} catch (Throwable $e) {
    echo 'Database connection failed: ' . $e->getMessage();
}
