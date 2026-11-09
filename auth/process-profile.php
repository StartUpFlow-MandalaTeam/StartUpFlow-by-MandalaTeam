<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profile-fiscal.php';

header('Content-Type: application/json; charset=utf-8');

$respond = static function (int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn() || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $respond(403, ['success' => false, 'message' => 'Richiesta non autorizzata']);
}

global $pdo;
$userId = currentUserId();
if ($userId === null) {
    $respond(401, ['success' => false, 'message' => 'Utente non autenticato']);
}

// Validazione e normalizzazione (limiti di lunghezza, formati e caratteri secondo FatturaPA).
$result = fiscalValidateProfile($_POST);
if ($result['errors'] !== []) {
    $respond(422, [
        'success' => false,
        'message' => (string) reset($result['errors']),
        'errors' => $result['errors'],
    ]);
}
$profile = $result['data'];

try {
    // UPSERT: inserisce se non esiste, altrimenti aggiorna. Le colonne arrivano da una lista fissa (non dall'input).
    $columns = array_keys($profile);
    $sql = 'INSERT INTO users_profiles (user_id, ' . implode(', ', $columns) . ') VALUES (:user_id, '
        . implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns)) . ') '
        . 'ON DUPLICATE KEY UPDATE '
        . implode(', ', array_map(static fn (string $column): string => $column . ' = VALUES(' . $column . ')', $columns));

    $params = [':user_id' => $userId];
    foreach ($profile as $column => $value) {
        $params[':' . $column] = $value;
    }
    $pdo->prepare($sql)->execute($params);

    $_SESSION['user_info_set'] = true;
    foreach ($profile as $column => $value) {
        $_SESSION[$column] = $value ?? '';
    }

    $saved = loadUserFiscalProfile($pdo, $userId);
    $respond(200, [
        'success' => true,
        'message' => 'Profilo aggiornato',
        'fiscal_complete' => fiscalProfileMissing($saved) === [],
    ]);
} catch (PDOException $exception) {
    // 1062 = chiave duplicata: codice fiscale o partita IVA già usati da un altro account.
    if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
        $isVat = strpos($exception->getMessage(), 'uk_users_profiles_vat') !== false;
        $respond(409, [
            'success' => false,
            'message' => $isVat
                ? 'Questa partita IVA è già associata a un altro account'
                : 'Questo codice fiscale è già associato a un altro account',
        ]);
    }
    error_log('Profile save failed: ' . $exception->getMessage());
    $respond(500, ['success' => false, 'message' => 'Impossibile salvare il profilo']);
} catch (Throwable $exception) {
    error_log('Profile save failed: ' . $exception->getMessage());
    $respond(500, ['success' => false, 'message' => 'Impossibile salvare il profilo']);
}