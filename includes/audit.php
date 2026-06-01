<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function audit_log(?int $userId, string $action, array $meta = [], ?string $ip = null): void
{
    global $pdo;

    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, ip_address, action, meta) VALUES (:user_id, :ip, :action, :meta)');
    $stmt->execute([
        ':user_id' => $userId,
        ':ip' => $ip,
        ':action' => $action,
        ':meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
    ]);
}

function client_ip(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return (string) $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return (string) strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
