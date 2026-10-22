<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn() || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Richiesta non autorizzata']);
    exit;
}

global $pdo;
$userId = currentUserId();
$profile = [
    'first_name' => trim((string) ($_POST['nome'] ?? '')),
    'last_name' => trim((string) ($_POST['cognome'] ?? '')),
    'birth_date' => trim((string) ($_POST['data_di_nascita'] ?? '')),
    'birth_place' => trim((string) ($_POST['luogo_di_nascita'] ?? '')),
    'birth_country' => trim((string) ($_POST['stato'] ?? '')),
    'cf' => strtoupper(trim((string) ($_POST['codice_fiscale'] ?? ''))),
    'address' => trim((string) ($_POST['indirizzo'] ?? '')),
    'city' => trim((string) ($_POST['citta'] ?? '')),
    'postal_code' => trim((string) ($_POST['cap'] ?? '')),
];

if (in_array('', $profile, true)) {
    echo json_encode(['success' => false, 'message' => 'Compila tutti i dati obbligatori del profilo']);
    exit;
}

if ($profile['birth_date'] !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $profile['birth_date']);
    if (!$date || $date->format('Y-m-d') !== $profile['birth_date']) {
        echo json_encode(['success' => false, 'message' => 'Data di nascita non valida']);
        exit;
    }
}

try {
    // UPSERT: inserisce se non esiste, altrimenti aggiorna
    $stmt = $pdo->prepare("
        INSERT INTO users_profiles
            (user_id, first_name, last_name, birth_date, birth_place, birth_country, cf, address, city, postal_code)
        VALUES
            (:user_id, :first_name, :last_name, NULLIF(:birth_date, ''), :birth_place, :birth_country, NULLIF(:cf, ''), :address, :city, :postal_code)
        ON DUPLICATE KEY UPDATE
            first_name   = VALUES(first_name),
            last_name    = VALUES(last_name),
            birth_date   = VALUES(birth_date),
            birth_place  = VALUES(birth_place),
            birth_country= VALUES(birth_country),
            cf           = VALUES(cf),
            address      = VALUES(address),
            city         = VALUES(city),
            postal_code  = VALUES(postal_code)
    ");
    $stmt->execute([
        ':user_id'      => $userId,
        ':first_name'   => $profile['first_name'],
        ':last_name'    => $profile['last_name'],
        ':birth_date'   => $profile['birth_date'],
        ':birth_place'  => $profile['birth_place'],
        ':birth_country'=> $profile['birth_country'],
        ':cf'           => $profile['cf'],
        ':address'      => $profile['address'],
        ':city'         => $profile['city'],
        ':postal_code'  => $profile['postal_code'],
    ]);

    $_SESSION['user_info_set'] = true;
    foreach ($profile as $field => $value) {
        $_SESSION[$field === 'first_name' ? 'first_name' : ($field === 'last_name' ? 'last_name' : $field)] = $value;
    }

    echo json_encode(['success' => true, 'message' => 'Profilo aggiornato']);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Impossibile salvare il profilo']);
}
