<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/audit.php';

// Expect GET params id and token
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = isset($_GET['token']) ? (string)$_GET['token'] : '';

if ($id <= 0 || $token === '') {
    http_response_code(400);
    echo 'Richiesta non valida.';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM email_change_requests WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$req = $stmt->fetch();

if (!$req) {
    http_response_code(404);
    echo 'Richiesta non trovata.';
    exit;
}

if (!empty($req['used_at'])) {
    echo 'Questa richiesta è già stata utilizzata.';
    exit;
}

if (strtotime($req['expires_at']) < time()) {
    echo 'Il link è scaduto.';
    exit;
}

if (!hash_equals($req['token_hash'], hash('sha256', $token))) {
    echo 'Token non valido.';
    exit;
}

$pdo->beginTransaction();

try {
    // fetch user
    $u = $pdo->prepare('SELECT id, email FROM users WHERE id = :id LIMIT 1');
    $u->execute([':id' => $req['user_id']]);
    $user = $u->fetch();

    if (!$user) {
        throw new Exception('Utente non trovato.');
    }

    $oldEmail = (string)$user['email'];
    $newEmail = (string)$req['new_email'];

    // update user email
    $up = $pdo->prepare('UPDATE users SET email = :email, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $up->execute([':email' => $newEmail, ':id' => $user['id']]);

    // mark request used
    $used = $pdo->prepare('UPDATE email_change_requests SET used_at = NOW() WHERE id = :id');
    $used->execute([':id' => $id]);

    // remove other pending requests for this user
    $del = $pdo->prepare('DELETE FROM email_change_requests WHERE user_id = :user_id AND id != :id');
    $del->execute([':user_id' => $user['id'], ':id' => $id]);

    // invalidate remember me tokens for this user (force logout elsewhere)
    $delrm = $pdo->prepare('DELETE FROM remember_me_tokens WHERE user_id = :user_id');
    $delrm->execute([':user_id' => $user['id']]);

    $pdo->commit();

    // send notification to old email
    if ($oldEmail !== '' && $oldEmail !== $newEmail) {
        sendChangeEmailNotice($newEmail, $oldEmail);
    }
    // audit success
    audit_log((int)$user['id'], 'change_email_confirm', ['old_email' => $oldEmail, 'new_email' => $newEmail], client_ip());

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Email cambiata</title></head><body><h1>Indirizzo email aggiornato</h1><p>La tua email è stata cambiata correttamente.</p><p><a href="/">Torna alla dashboard</a></p></body></html>';
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo 'Errore server: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
