<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// 1. Verifica che la pagina sia accessibile solo a un utente autenticato.
if (!isLoggedIn()) {
    header('Location: /auth/register.php');
    exit;
}

global $pdo;
$userId = currentUserId();
$username = $_SESSION['username'] ?? 'Utente';

// 2. Se richiesto, prepara il report CSV e termina senza generare la pagina HTML.
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    // Recupera soltanto le registrazioni dell'utente corrente.
    $stmt = $pdo->prepare("
        SELECT entry_date, project_name, hours, description
        FROM time_entries
        WHERE user_id = :user_id
        ORDER BY entry_date DESC, created_at DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Comunica al browser che la risposta è un file CSV da scaricare.
    $filename = 'report_ore_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Aggiunge il BOM e l'intestazione per aprire correttamente il CSV in Excel.
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['Data', 'Progetto', 'Ore Lavorate', 'Descrizione'], ';');

    // Scrive una riga per ogni attività e somma le ore da riportare in fondo.
    $totalHours = 0.0;
    foreach ($entries as $entry) {
        $hours = (float) $entry['hours'];
        $totalHours += $hours;

        fputcsv($output, [
            $entry['entry_date'],
            $entry['project_name'],
            number_format($hours, 2, ',', ''),
            $entry['description'] ?? '',
        ], ';');
    }

    // Chiude il report con il totale e termina la richiesta di download.
    fputcsv($output, ['TOTALE', '', number_format($totalHours, 2, ',', ''), ''], ';');
    fclose($output);
    exit;
}

// 3. Questa pagina viene caricata come frammento dalla navigazione SPA.
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}

$message = '';
$messageType = 'info';

// 4. Se il modulo richiede un'eliminazione, valida l'ID e limita l'operazione
//    all'utente corrente anche nella query SQL.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'delete'
) {
    $entryId = filter_var($_POST['entry_id'] ?? 0, FILTER_VALIDATE_INT);

    if ($entryId !== false && $entryId > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM time_entries
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            'id' => $entryId,
            'user_id' => $userId,
        ]);

        // rowCount distingue un record eliminato da uno inesistente o non proprio.
        if ($stmt->rowCount() > 0) {
            $message = 'Registrazione eliminata con successo.';
            $messageType = 'success';
        } else {
            $message = 'Impossibile eliminare la registrazione.';
            $messageType = 'danger';
        }
    } else {
        $message = 'La registrazione da eliminare non è valida.';
        $messageType = 'danger';
    }
}

// 5. In assenza di un'azione di eliminazione, gestisce il salvataggio del modulo.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (!isset($_POST['action']) || $_POST['action'] === 'create')
) {
    // Legge i campi, rimuove gli spazi superflui e converte le ore in un numero.
    $project = trim($_POST['project_name'] ?? '');
    $hours = filter_var($_POST['hours'] ?? 0, FILTER_VALIDATE_FLOAT);
    $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');

    // Salva solo se progetto, ore e data rispettano i requisiti minimi.
    if ($project !== '' && $hours !== false && $hours > 0 && $entryDate !== '') {
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
        $message = 'Ore registrate con successo!';
        $messageType = 'success';
    } else {
        $message = 'Compila tutti i campi obbligatori con valori validi.';
        $messageType = 'danger';
    }
}

// 6. Ricarica lo storico dell'utente dopo le eventuali modifiche.
$stmt = $pdo->prepare("
    SELECT id, project_name, description, hours, entry_date
    FROM time_entries
    WHERE user_id = :user_id
    ORDER BY entry_date DESC, created_at DESC
");
$stmt->execute(['user_id' => $userId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Calcola il totale da mostrare nella tabella.
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
    .btn-export { background: #28a745; color: #fff; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9rem; float: right; }
    .btn-export:hover { background: #218838; }
    .section-header { overflow: hidden; margin-top: 2rem; margin-bottom: 1rem; }
    .section-header h3 { float: left; margin: 0; }
    .tracker-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .tracker-table th, .tracker-table td { border: 1px solid #ddd; padding: 0.75rem; text-align: left; }
    .tracker-table th { background-color: #f2f2f2; }
    .tracker-table tfoot tr { background-color: #e9ecef; font-weight: bold; }
    
</style>

<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Time-Tracker</h1>
        <p>Benvenuto, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</p>
    </header>

    <div class="service-content">
        <?php if ($message !== ''): ?>
            <div class="alert-msg alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- 8. Il modulo invia i dati al server; il router SPA aggiorna poi il frammento. -->
        <form method="POST" action="/time-tracker.php" class="tracker-form">
            <input type="hidden" name="action" value="create">
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

        <!-- 9. Mostra lo storico, il totale e l'esportazione solo se ci sono dati. -->
        <div class="section-header">
            <h3>Storico Attività</h3>
            <?php if (count($entries) > 0): ?>
                <a href="/time-tracker.php?action=export_csv" class="btn-export">📥 Scarica Report CSV</a>
            <?php endif; ?>
        </div>

        <?php if (count($entries) > 0): ?>
            <table class="tracker-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Progetto</th>
                        <th>Ore</th>
                        <th>Descrizione</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['entry_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><strong><?php echo htmlspecialchars($entry['project_name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $entry['hours'], ENT_QUOTES, 'UTF-8'); ?> h</td>
                            <td><?php echo htmlspecialchars($entry['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <form method="POST" action="/time-tracker.php" class="delete-form">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="entry_id" value="<?php echo (int) $entry['id']; ?>">
                                    <button type="submit" class="btn-delete">Elimina</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Totale ore</td>
                        <td><?php echo htmlspecialchars(number_format((float) $totalHours, 2, ',', ''), ENT_QUOTES, 'UTF-8'); ?> h</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <p>Nessuna attività registrata per ora.</p>
        <?php endif; ?>
    </div>
</div>
