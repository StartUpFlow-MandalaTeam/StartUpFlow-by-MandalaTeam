<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (!isLoggedIn()) {
    header('Location: /auth/register.php');
    exit;
}
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}
global $pdo;
$userId = currentUserId();
$username = $_SESSION['username'] ?? 'Utente';
?>
<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /auth/register.php');
    exit;
}

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}

global $pdo;
$userId = currentUserId();
$username = $_SESSION['username'] ?? 'Utente';
$message = '';

// Gestione dell'invio del form (Salvataggio ore)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project = trim($_POST['project_name'] ?? '');
    $hours = filter_var($_POST['hours'] ?? 0, FILTER_VALIDATE_FLOAT);
    $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');

    if (!empty($project) && $hours !== false && $hours > 0 && !empty($entryDate)) {
        $stmt = $pdo->prepare("
            INSERT INTO time_entries (user_id, project_name, description, hours, entry_date)
            VALUES (:user_id, :project_name, :description, :hours, :entry_date)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'project_name' => $project,
            'description' => $description,
            'hours' => $hours,
            'entry_date' => $entryDate,
        ]);
        $message = "Ore registrate con successo!";
    } else {
        $message = "Compila tutti i campi obbligatori con valori validi.";
    }
}

// Recupero del registro ore dell'utente
$stmt = $pdo->prepare("
    SELECT project_name, description, hours, entry_date 
    FROM time_entries 
    WHERE user_id = :user_id 
    ORDER BY entry_date DESC, created_at DESC
");
$stmt->execute(['user_id' => $userId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Time-Tracker</h1>
        <p>Benvenuto, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</p>
    </header>
    <div class="service-content">
        <div class="alert alert-info">
            <strong>Modulo Time-Tracker</strong>
            <p>Questo spazio è riservato al team di sviluppo per implementare il servizio.</p>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
    <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
<style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>

<style>
    .back-link { display: inline-block; margin-bottom: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; border-radius: 4px; }
    .back-link:hover { background: #333; }
    .tracker-form { background: #f9f9f9; padding: 1.5rem; border-radius: 6px; margin-bottom: 2rem; border: 1px solid #eee; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.3rem; font-weight: bold; }
    .form-group input, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .btn-submit { background: #007bff; color: #fff; padding: 0.6rem 1.2rem; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-submit:hover { background: #0056b3; }
    .tracker-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .tracker-table th, .tracker-table td { border: 1px solid #ddd; padding: 0.75rem; text-align: left; }
    .tracker-table th { background-color: #f2f2f2; }
    .alert-msg { padding: 0.75rem; background: #e2e3e5; color: #383d41; border-radius: 4px; margin-bottom: 1rem; }
</style>

        <!-- Form inserimento ore -->
        <form method="POST" class="tracker-form">
            <h3>Nuova Registrazione</h3>
            <div class="form-group">
                <label for="project_name">Progetto *</label>
                <input type="text" id="project_name" name="project_name" required placeholder="Es. Restyling Sito Web">
            </div>
            <div class="form-group">
                <label for="hours">Ore Lavorate *</label>
                <input type="number" step="0.25" min="0.25" id="hours" name="hours" required placeholder="Es. 2.5">
            </div>
            <div class="form-group">
                <label for="entry_date">Data *</label>
                <input type="date" id="entry_date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Descrizione Attività</label>
                <textarea id="description" name="description" rows="3" placeholder="Dettagli sulle mansioni svolte..."></textarea>
            </div>
            <button type="submit" class="btn-submit">Salva Ore</button>
        </form>

        <!-- Tabella elenco ore registrate -->
        <h3>Storico Attività</h3>
        <?php if (count($entries) > 0): ?>
            <table class="tracker-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Progetto</th>
                        <th>Ore</th>
                        <th>Descrizione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['entry_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><strong><?php echo htmlspecialchars($entry['project_name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)$entry['hours'], ENT_QUOTES, 'UTF-8'); ?> h</td>
                            <td><?php echo htmlspecialchars($entry['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nessuna attività registrata per ora.</p>
        <?php endif; ?>
    </div>
        </div'>
        // Recupero del registro ore dell'utente
        \stmt = $pdo ->prepare("
        SELECT id, project_name, description, hours, entry_date
        FROM time_entries
        WHERE user_id=:user_id
        ORDER BY entry_date DESC, created_at DESC
        ");
        \stmt->execute (['user_id' => $userId]);
        \(entries =\)stmt->fetchAll(PDO::FETCH_ASSOC);
        // Calcolo del totale delle ore lavorate
        \(totalHours = array_sum(array_column(\)entries, 'hours'));
<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /auth/register.php');
    exit;
}

global $pdo;
$userId = currentUserId();
$username = $_SESSION['username'] ?? 'Utente';

// --- GESTIONE ESPORTAZIONE CSV ---
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    // Recupera tutte le voci dell'utente
    $stmt = $pdo->prepare("
        SELECT entry_date, project_name, hours, description 
        FROM time_entries 
        WHERE user_id = :user_id 
        ORDER BY entry_date DESC, created_at DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Imposta gli header HTTP per forzare il download del file
    $filename = "report_ore_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Apre l'output stream di PHP
    $output = fopen('php://output', 'w');

    // Aggiunge il BOM UTF-8 per la compatibilità con Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Intestazione delle colonne del CSV
    fputcsv($output, ['Data', 'Progetto', 'Ore Lavorate', 'Descrizione'], ';');

    // Righe dei dati
    $totalHours = 0.0;
    foreach ($entries as $row) {
        $hours = (float)$row['hours'];
        $totalHours += $hours;
        fputcsv($output, [
            $row['entry_date'],
            $row['project_name'],
            number_format($hours, 2, ',', ''),
            $row['description'] ?? ''
        ], ';');
    }

    // Riga finale con il totale
    fputcsv($output, ['TOTALE', '', number_format($totalHours, 2, ',', ''), ''], ';');

    fclose($output);
    exit;
}

// Check per la richiesta AJAX (per la navigazione SPA)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}

$message = '';

// --- GESTIONE SALVATAGGIO ORE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project = trim($_POST['project_name'] ?? '');
    $hours = filter_var($_POST['hours'] ?? 0, FILTER_VALIDATE_FLOAT);
    $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');

    if (!empty($project) && $hours !== false && $hours > 0 && !empty($entryDate)) {
        $stmt = $pdo->prepare("
            INSERT INTO time_entries (user_id, project_name, description, hours, entry_date)
            VALUES (:user_id, :project_name, :description, :hours, :entry_date)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'project_name' => $project,
            'description' => $description,
            'hours' => $hours,
            'entry_date' => $entryDate,
        ]);
        $message = "Ore registrate con successo!";
    } else {
        $message = "Compila tutti i campi obbligatori con valori validi.";
    }
}

// Recupero del registro ore dell'utente
$stmt = $pdo->prepare("
    SELECT id, project_name, description, hours, entry_date 
    FROM time_entries 
    WHERE user_id = :user_id 
    ORDER BY entry_date DESC, created_at DESC
");
$stmt->execute(['user_id' => $userId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcolo del totale delle ore lavorate
$totalHours = array_sum(array_column($entries, 'hours'));
?>

<style>
    .back-link { display: inline-block; margin-bottom: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; border-radius: 4px; }
    .back-link:hover { background: #333; }
    .tracker-form { background: #f9f9f9; padding: 1.5rem; border-radius: 6px; margin-bottom: 2rem; border: 1px solid #eee; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.3rem; font-weight: bold; }
    .form-group input, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .btn-submit { background: #007bff; color: #fff; padding: 0.6rem 1.2rem; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-submit:hover { background: #0056b3; }
    .btn-export { display: inline-block; background: #28a745; color: #fff; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9rem; float: right; }
    .btn-export:hover { background: #218838; }
    .section-header { overflow: hidden; margin-top: 2rem; margin-bottom: 1rem; }
    .section-header h3 { float: left; margin: 0; }
    .tracker-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .tracker-table th, .tracker-table td { border: 1px solid #ddd; padding: 0.75rem; text-align: left; }
    .tracker-table th { background-color: #f2f2f2; }
    .tracker-table tfoot tr { background-color: #e9ecef; font-weight: bold; }
    .alert-msg { padding: 0.75rem; background: #e2e3e5; color: #383d41; border-radius: 4px; margin-bottom: 1rem; }
</style>

<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Time-Tracker</h1>
        <p>Benvenuto, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</p>
    </header>

    <div class="service-content">
        <?php if (!empty($message)): ?>
            <div class="alert-msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Form inserimento ore -->
        <form method="POST" class="tracker-form">
            <h3>Nuova Registrazione</h3>
            <div class="form-group">
                <label for="project_name">Progetto *</label>
                <input type="text" id="project_name" name="project_name" required placeholder="Es. Restyling Sito Web">
            </div>
            <div class="form-group">
                <label for="hours">Ore Lavorate *</label>
                <input type="number" step="0.25" min="0.25" id="hours" name="hours" required placeholder="Es. 2.5">
            </div>
            <div class="form-group">
                <label for="entry_date">Data *</label>
                <input type="date" id="entry_date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Descrizione Attività</label>
                <textarea id="description" name="description" rows="3" placeholder="Dettagli sulle mansioni svolte..."></textarea>
            </div>
            <button type="submit" class="btn-submit">Salva Ore</button>
        </form>

        <!-- Intestazione sezione storico + Pulsante Export -->
        <div class="section-header">
            <h3>Storico Attività</h3>
            <?php if (count($entries) > 0): ?>
                <a href="/time-tracker.php?action=export_csv" class="btn-export">📥 Scarica Report CSV</a>
            <?php endif; ?>
        </div>
        <?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /auth/register.php');
    exit;
}

global $pdo;
$userId = currentUserId();
$username = $_SESSION['username'] ?? 'Utente';

// --- GESTIONE ESPORTAZIONE CSV ---
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $stmt = $pdo->prepare("
        SELECT entry_date, project_name, hours, description 
        FROM time_entries 
        WHERE user_id = :user_id 
        ORDER BY entry_date DESC, created_at DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "report_ore_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM per Excel
    fputcsv($output, ['Data', 'Progetto', 'Ore Lavorate', 'Descrizione'], ';');

    $totalHours = 0.0;
    foreach ($entries as $row) {
        $hours = (float)$row['hours'];
        $totalHours += $hours;
        fputcsv($output, [
            $row['entry_date'],
            $row['project_name'],
            number_format($hours, 2, ',', ''),
            $row['description'] ?? ''
        ], ';');
    }

    fputcsv($output, ['TOTALE', '', number_format($totalHours, 2, ',', ''), ''], ';');
    fclose($output);
    exit;
}

// Check per la richiesta AJAX (per la navigazione SPA)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}

$message = '';
$messageType = 'info';

// --- GESTIONE ELIMINAZIONE VOCE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $entryId = filter_var($_POST['entry_id'] ?? 0, FILTER_VALIDATE_INT);

    if ($entryId) {
        // Garantisce che l'utente elimini solo un proprio record
        $stmt = $pdo->prepare("DELETE FROM time_entries WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            'id' => $entryId,
            'user_id' => $userId
        ]);

        if ($stmt->rowCount() > 0) {
            $message = "Registrazione eliminata con successo.";
            $messageType = 'success';
        } else {
            $message = "Impossibile eliminare la registrazione.";
            $messageType = 'danger';
        }
    }
}

// --- GESTIONE SALVATAGGIO ORE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'create')) {
    $project = trim($_POST['project_name'] ?? '');
    $hours = filter_var($_POST['hours'] ?? 0, FILTER_VALIDATE_FLOAT);
    $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');

    if (!empty($project) && $hours !== false && $hours > 0 && !empty($entryDate)) {
        $stmt = $pdo->prepare("
            INSERT INTO time_entries (user_id, project_name, description, hours, entry_date)
            VALUES (:user_id, :project_name, :description, :hours, :entry_date)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'project_name' => $project,
            'description' => $description,
            'hours' => $hours,
            'entry_date' => $entryDate,
        ]);
        $message = "Ore registrate con successo!";
        $messageType = 'success';
    } else {
        $message = "Compila tutti i campi obbligatori con valori validi.";
        $messageType = 'danger';
    }
}

// Recupero del registro ore dell'utente
$stmt = $pdo->prepare("
    SELECT id, project_name, description, hours, entry_date 
    FROM time_entries 
    WHERE user_id = :user_id 
    ORDER BY entry_date DESC, created_at DESC
");
$stmt->execute(['user_id' => $userId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcolo del totale delle ore lavorate
$totalHours = array_sum(array_column($entries, 'hours'));
?>

<style>
    .back-link { display: inline-block; margin-bottom: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; border-radius: 4px; }
    .back-link:hover { background: #333; }
    .tracker-form { background: #f9f9f9; padding: 1.5rem; border-radius: 6px; margin-bottom: 2rem; border: 1px solid #eee; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.3rem; font-weight: bold; }
    .form-group input, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .btn-submit { background: #007bff; color: #fff; padding: 0.6rem 1.2rem; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-submit:hover { background: #0056b3; }
    .btn-export { display: inline-block; background: #28a745; color: #fff; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9rem; float: right; }
    .btn-export:hover { background: #218838; }
    .btn-delete { background: #dc3545; color: #fff; border: none; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; }
    .btn-delete:hover { background: #c82333; }
    .section-header { overflow: hidden; margin-top: 2rem; margin-bottom: 1rem; }
    .section-header h3 { float: left; margin: 0; }
    .tracker-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .tracker-table th, .tracker-table td { border: 1px solid #ddd; padding: 0.75rem; text-align: left; }
    .tracker-table th { background-color: #f2f2f2; }
    .tracker-table tfoot tr { background-color: #e9ecef; font-weight: bold; }
    .alert-msg { padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>