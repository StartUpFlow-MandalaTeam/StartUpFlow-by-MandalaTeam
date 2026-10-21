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
    $stmt = $pdo->prepare("UPDATE users_profiles SET first_name = :first_name, last_name = :last_name, birth_date = NULLIF(:birth_date, ''), birth_place = :birth_place, birth_country = :birth_country, cf = NULLIF(:cf, ''), address = :address, city = :city, postal_code = :postal_code WHERE user_id = :user_id");
    $stmt->execute([
        ':user_id' => $userId,
        ':first_name' => $profile['first_name'],
        ':last_name' => $profile['last_name'],
        ':birth_date' => $profile['birth_date'],
        ':birth_place' => $profile['birth_place'],
        ':birth_country' => $profile['birth_country'],
        ':cf' => $profile['cf'],
        ':address' => $profile['address'],
        ':city' => $profile['city'],
        ':postal_code' => $profile['postal_code'],
    ]);

    $profileFound = true;
    if ($stmt->rowCount() === 0) {
        $profileExists = $pdo->prepare('SELECT user_id FROM users_profiles WHERE user_id = :user_id LIMIT 1');
        $profileExists->execute([':user_id' => $userId]);
        $profileFound = (bool) $profileExists->fetchColumn();
    }

    if (!$profileFound) {
        echo json_encode(['success' => false, 'message' => 'Profilo utente non trovato']);
        exit;
    }

    $_SESSION['user_info_set'] = true;
    foreach ($profile as $field => $value) {
        $_SESSION[$field === 'first_name' ? 'first_name' : ($field === 'last_name' ? 'last_name' : $field)] = $value;
    }

    echo json_encode(['success' => true, 'message' => 'Profilo aggiornato']);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Impossibile salvare il profilo']);
}
