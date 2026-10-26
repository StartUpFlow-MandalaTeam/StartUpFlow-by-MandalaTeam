<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        exit;
    }
    header('Location: /auth/login.php');
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
            number_format($hours, 4, ',', ''),
            $entry['description'] ?? '',
        ], ';');
    }

    // Chiude il report con il totale e termina la richiesta di download.
    fputcsv($output, ['TOTALE', '', number_format($totalHours, 4, ',', ''), ''], ';');
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

// Ricarica lo storico dell'utente dopo l'eventuale eliminazione.
$stmt = $pdo->prepare("
    SELECT id, project_name, description, hours, entry_date
    FROM time_entries
    WHERE user_id = :user_id
    ORDER BY entry_date DESC, created_at DESC
");
$stmt->execute(['user_id' => $userId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcola il totale da mostrare nella tabella.
$totalHours = array_sum(array_column($entries, 'hours'));
?>

<div class="service-page time-tracker">
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

        <section class="tracker-form" aria-live="polite">
            <h3>Rilevamento automatico</h3>
            <p>Il tempo di creazione viene avviato quando scegli «Nuova fattura» e registrato qui dopo il salvataggio riuscito.</p>
        </section>

        <!-- Mostra lo storico, il totale e l'esportazione solo se ci sono dati. -->
        <div class="section-header">
            <h3>Storico Attività</h3>
            <?php if (count($entries) > 0): ?>
                <a href="/time-tracker.php?action=export_csv" class="btn-export">📥 Scarica Report CSV</a>
            <?php endif; ?>
        </div>

        <?php if (count($entries) > 0): ?>
            <div class="tracker-table-container">
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
                                <td><?php echo htmlspecialchars(number_format((float) $entry['hours'], 4, ',', ''), ENT_QUOTES, 'UTF-8'); ?> h</td>
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
                            <td><?php echo htmlspecialchars(number_format((float) $totalHours, 4, ',', ''), ENT_QUOTES, 'UTF-8'); ?> h</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <p>Nessuna attività registrata per ora.</p>
        <?php endif; ?>
    </div>
</div>
