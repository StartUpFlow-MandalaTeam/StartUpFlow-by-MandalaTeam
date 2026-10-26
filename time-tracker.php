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

// Questa pagina viene caricata come frammento dalla navigazione SPA.
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}

// Carica lo storico dell'utente corrente.
$stmt = $pdo->prepare("
    SELECT project_name, description, hours, entry_date
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
        <section class="tracker-form" aria-live="polite">
            <h3>Rilevamento automatico</h3>
            <p>Il tempo di creazione viene avviato quando scegli «Nuova fattura» e registrato qui dopo il salvataggio riuscito.</p>
        </section>

        <!-- Mostra lo storico e il totale solo se ci sono dati. -->
        <div class="section-header">
            <h3>Storico Attività</h3>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['entry_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><strong><?php echo htmlspecialchars($entry['project_name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><?php echo htmlspecialchars(number_format((float) $entry['hours'], 4, ',', ''), ENT_QUOTES, 'UTF-8'); ?> h</td>
                                <td><?php echo htmlspecialchars($entry['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">Totale ore</td>
                            <td><?php echo htmlspecialchars(number_format((float) $totalHours, 4, ',', ''), ENT_QUOTES, 'UTF-8'); ?> h</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <p>Nessuna attività registrata per ora.</p>
        <?php endif; ?>
    </div>
</div>
