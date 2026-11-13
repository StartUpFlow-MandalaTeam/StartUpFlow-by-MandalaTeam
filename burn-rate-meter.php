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

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}

$userId = currentUserId();
if ($userId === null) {
    http_response_code(401);
    exit;
}

$showError = static function (int $status, string $message): void {
    http_response_code($status);
    echo '<div class="service-page burn-rate-meter"><div class="service-content"><div class="burn-rate-notice" role="alert"><p>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p></div></div></div>';
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $showError(403, 'Sessione scaduta o richiesta non valida. Ricarica la pagina e riprova.');
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'add_expense') {
            $description = trim((string) ($_POST['description'] ?? ''));
            $amountInput = trim((string) ($_POST['amount'] ?? ''));
            $expenseDate = (string) ($_POST['expense_date'] ?? '');
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $expenseDate);
            $dateErrors = DateTimeImmutable::getLastErrors();
            $validDate = $parsedDate !== false
                && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
                && $parsedDate->format('Y-m-d') === $expenseDate;

            if ($description === '' || mb_strlen($description) > 255) {
                $showError(422, 'Inserisci una descrizione (massimo 255 caratteri).');
            }
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amountInput)
                || !is_finite((float) $amountInput)
                || (float) $amountInput <= 0
                || (float) $amountInput > 99999999.99
            ) {
                $showError(422, 'Inserisci un importo maggiore di zero, con massimo due decimali.');
            }
            if (!$validDate) {
                $showError(422, 'Inserisci una data valida.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO burn_rate_expenses (user_id, description, amount, expense_date)
                 VALUES (:user_id, :description, :amount, :expense_date)'
            );
            $statement->execute([
                ':user_id' => $userId,
                ':description' => $description,
                ':amount' => number_format((float) $amountInput, 2, '.', ''),
                ':expense_date' => $expenseDate,
            ]);
        } elseif ($action === 'update_cash') {
            $cashInput = trim((string) ($_POST['initial_cash'] ?? ''));
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $cashInput)
                || !is_finite((float) $cashInput)
                || (float) $cashInput < 0
                || (float) $cashInput > 9999999999.99
            ) {
                $showError(422, 'Inserisci una cassa iniziale valida, pari o superiore a zero.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO burn_rate_settings (user_id, initial_cash)
                 VALUES (:user_id, :initial_cash)
                 ON DUPLICATE KEY UPDATE initial_cash = VALUES(initial_cash)'
            );
            $statement->execute([
                ':user_id' => $userId,
                ':initial_cash' => number_format((float) $cashInput, 2, '.', ''),
            ]);
        } elseif ($action === 'delete_expense') {
            $expenseId = (int) ($_POST['expense_id'] ?? 0);
            $expenseSource = (string) ($_POST['expense_source'] ?? 'burn_rate');

            if ($expenseId > 0) {
                if ($expenseSource === 'expense') {
                    $statement = $pdo->prepare('DELETE FROM expenses WHERE id = :id AND user_id = :user_id');
                } else {
                    $statement = $pdo->prepare('DELETE FROM burn_rate_expenses WHERE id = :id AND user_id = :user_id');
                }
                $statement->execute([
                    ':id' => $expenseId,
                    ':user_id' => $userId,
                ]);
            }
        } else {
            $showError(400, 'Operazione non riconosciuta.');
        }
    } catch (PDOException $exception) {
        error_log('Burn rate update failed: ' . $exception->getMessage());
        $showError(500, 'Non è stato possibile salvare i dati. Verifica che il database sia aggiornato e riprova.');
    }
}

$monthStart = new DateTimeImmutable('first day of this month');
$nextMonthStart = $monthStart->modify('+1 month');

$cassaIniziale = 50000.0;
$entrateMensili = 0.0;
$speseMensili = 0.0;
$ultimeSpese = [];

try {
    $cashStatement = $pdo->prepare('SELECT initial_cash FROM burn_rate_settings WHERE user_id = :user_id');
    $cashStatement->execute([':user_id' => $userId]);
    $savedCash = $cashStatement->fetchColumn();
    $cassaIniziale = $savedCash === false ? 50000.0 : (float) $savedCash;

    $incomeStatement = $pdo->prepare(
        'SELECT COALESCE(SUM(i.total), 0)
         FROM invoices i
         LEFT JOIN orders o ON o.id = i.order_id AND o.user_id = i.user_id
         WHERE i.user_id = :user_id
           AND (i.is_paid = 1 OR o.payment_status IN (\'paid\', \'partial\'))
           AND i.currency = \'EUR\'
           AND i.issue_date >= :month_start
           AND i.issue_date < :next_month_start'
    );
    $incomeStatement->execute([
        ':user_id' => $userId,
        ':month_start' => $monthStart->format('Y-m-d'),
        ':next_month_start' => $nextMonthStart->format('Y-m-d'),
    ]);
    $entrateMensili = (float) $incomeStatement->fetchColumn();

    $expenseTotalStatement = $pdo->prepare(
        'SELECT (
            SELECT COALESCE(SUM(amount), 0)
            FROM burn_rate_expenses
            WHERE user_id = :user_id1
              AND expense_date >= :month_start1
              AND expense_date < :next_month_start1
         ) + (
            SELECT COALESCE(SUM(amount), 0)
            FROM expenses
            WHERE user_id = :user_id2
              AND expense_date >= :month_start2
              AND expense_date < :next_month_start2
         ) AS total_expenses'
    );
    $expenseTotalStatement->execute([
        ':user_id1' => $userId,
        ':month_start1' => $monthStart->format('Y-m-d'),
        ':next_month_start1' => $nextMonthStart->format('Y-m-d'),
        ':user_id2' => $userId,
        ':month_start2' => $monthStart->format('Y-m-d'),
        ':next_month_start2' => $nextMonthStart->format('Y-m-d'),
    ]);
    $speseMensili = (float) $expenseTotalStatement->fetchColumn();

    $expensesStatement = $pdo->prepare(
        'SELECT id, description, amount, expense_date, source
         FROM (
             SELECT id, description, amount, expense_date, \'burn_rate\' as source FROM burn_rate_expenses WHERE user_id = :user_id1
             UNION ALL
             SELECT id, description, amount, expense_date, \'expense\' as source FROM expenses WHERE user_id = :user_id2
         ) combined_expenses
         ORDER BY expense_date DESC, id DESC
         LIMIT 10'
    );
    $expensesStatement->execute([
        ':user_id1' => $userId,
        ':user_id2' => $userId,
    ]);
    $ultimeSpese = $expensesStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Burn rate data load failed: ' . $exception->getMessage());
    $showError(500, 'Non è stato possibile caricare i dati. Verifica che il database sia aggiornato e riprova.');
}

$username = $_SESSION['username'] ?? 'Utente';
$netBurnRate = $speseMensili - $entrateMensili;
$runwayMesi = $netBurnRate > 0 ? round($cassaIniziale / $netBurnRate, 1) : \INF;
$percentualeSalute = is_infinite($runwayMesi)
    ? 100
    : min(100, max(0, ($runwayMesi / 12) * 100));

$statoColore = 'success';
if ($runwayMesi < 6) {
    $statoColore = 'warning';
}
if ($runwayMesi < 3) {
    $statoColore = 'danger';
}

$successMessage = [
    'expense' => 'Spesa registrata.',
    'deleted' => 'Spesa eliminata.',
    'cash' => 'Cassa iniziale aggiornata.',
][(string) ($_GET['success'] ?? '')] ?? null;
$meseCorrente = $monthStart->format('m/Y');
?>

<div class="service-page burn-rate-meter">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Burn-Rate-Meter</h1>
    </header>

    <div class="service-content">
        <?php if ($successMessage !== null): ?>
            <div class="burn-rate-notice burn-rate-notice--success" role="status">
                <p><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <section class="burn-rate-card" style="margin-bottom: 1.5rem; background: #f8fafc; border-left: 4px solid #167c80; padding: 1rem 1.5rem;">
            <style>
                .burn-rate-dropdown summary::-webkit-details-marker { display: none; }
                .burn-rate-dropdown summary { list-style: none; display: flex; justify-content: space-between; align-items: center; }
                .burn-rate-dropdown summary::after {
                    content: '▼';
                    font-size: 0.8rem;
                    color: #167c80;
                    transition: transform 0.2s ease;
                }
                .burn-rate-dropdown[open] summary::after {
                    transform: rotate(180deg);
                }
            </style>
            <details class="burn-rate-dropdown" style="cursor: pointer;">
                <summary style="font-size: 1.15rem; font-weight: 600; color: #0f172a; outline: none;">
                    <span>Quanti mesi posso andare avanti prima di finire i soldi?</span>
                </summary>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <p style="margin: 0 0 1rem 0; color: #334155; font-size: 0.95rem; line-height: 1.5;">
                        Per rispondere a questa domanda, il sistema calcola la tua autonomia (runway) seguendo 3 semplici passaggi:
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem; color: #334155; font-size: 0.95rem; line-height: 1.5;">
                        <div>
                            <strong style="color: #0f172a;">1. Quanto spendi e quanto incassi ogni mese:</strong><br>
                            Si prendono tutte le spese del mese (le bollette, gli acquisti, i costi registrati nel modulo <a href="/nota-spese" data-spa="true" style="color: #0ea5e9; text-decoration: underline;">Nota Spese</a> e nello <a href="/scadenzario" data-spa="true" style="color: #0ea5e9; text-decoration: underline;">Scadenzario</a>) e si sottraggono i soldi incassati nello stesso mese dalle fatture che i clienti ti hanno pagato (o saldato in parte). Il risultato è la <strong>perdita netta mensile</strong> (quanti soldi in più escono rispetto a quelli che entrano). Se incassi più di quanto spendi, l'azienda è in attivo e la perdita netta è pari a zero!
                        </div>
                        <div>
                            <strong style="color: #0f172a;">2. Per quanti mesi bastano i tuoi risparmi:</strong><br>
                            Si prende il totale della tua cassa iniziale (i risparmi o la liquidità di partenza) e la si divide per la perdita netta mensile. Ad esempio, se hai 50.000 € in cassa e ogni mese perdi 5.000 € netti, i tuoi risparmi dureranno esattamente 10 mesi.
                        </div>
                        <div>
                            <strong style="color: #0f172a;">3. L'indice di sicurezza:</strong><br>
                            Il sistema confronta i mesi di autonomia trovati con un obiettivo ideale di 12 mesi, trasformandoli in una percentuale di sicurezza visiva (fino al 100%). Se incassi più di quanto spendi, l'autonomia è illimitata e la sicurezza è al massimo!
                        </div>
                    </div>
                </div>
            </details>
        </section>

        <section class="burn-rate-card" aria-labelledby="burn-rate-summary-title">
            <div class="section-header">
                <h2 id="burn-rate-summary-title">Situazione finanziaria</h2>
                <p>Riepilogo del mese <?php echo htmlspecialchars($meseCorrente, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="metrics-grid">
                <div class="metric">
                    <span class="label">Cassa iniziale</span>
                    <span class="value">€ <?php echo number_format($cassaIniziale, 2, ',', '.'); ?></span>
                </div>
                <div class="metric">
                    <span class="label">Entrate da fatture saldate</span>
                    <span class="value">€ <?php echo number_format($entrateMensili, 2, ',', '.'); ?></span>
                </div>
                <div class="metric">
                    <span class="label">Spese registrate</span>
                    <span class="value">€ <?php echo number_format($speseMensili, 2, ',', '.'); ?></span>
                </div>
                <div class="metric">
                    <span class="label">Saldo mensile</span>
                    <span class="value <?php echo $netBurnRate > 0 ? 'danger' : 'success'; ?>">
                        <?php echo $netBurnRate > 0 ? '- ' : '+ '; ?>€ <?php echo number_format(abs($netBurnRate), 2, ',', '.'); ?>
                    </span>
                </div>
                <div class="metric">
                    <span class="label">Autonomia (runaway)</span>
                    <span class="value <?php echo $statoColore; ?>">
                        <?php echo is_infinite($runwayMesi) ? 'Illimitata' : number_format($runwayMesi, 1, ',', '.') . ' mesi'; ?>
                    </span>
                </div>
            </div>

            <div class="meter-container">
                <div class="meter-heading">
                    <h3>Livello di sicurezza della cassa</h3>
                    <span><?php echo number_format($percentualeSalute, 0, ',', '.'); ?>%</span>
                </div>
                <div class="meter-bar-bg">
                    <div class="meter-bar-fill <?php echo $statoColore; ?>" style="width: <?php echo number_format($percentualeSalute, 2, '.', ''); ?>%;"></div>
                </div>
            </div>
        </section>

        <div class="burn-rate-forms">
            <section class="burn-rate-card" aria-labelledby="expense-form-title">
                <h2 id="expense-form-title">Registra una spesa</h2>
                <form action="/burn-rate-meter.php" method="post" data-return-url="/burn-rate-meter?success=expense">
                    <input type="hidden" name="action" value="add_expense">
                    <?php echo csrfInput(); ?>
                    <label>
                        Descrizione
                        <input type="text" name="description" maxlength="255" required>
                    </label>
                    <div class="burn-rate-form-row">
                        <label>
                            Importo (€)
                            <input type="number" name="amount" min="0.01" max="99999999.99" step="0.01" required>
                        </label>
                        <label>
                            Data
                            <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </label>
                    </div>
                    <button type="submit">Salva spesa</button>
                </form>
            </section>

            <section class="burn-rate-card" aria-labelledby="cash-form-title">
                <h2 id="cash-form-title">Imposta la cassa iniziale</h2>
                <form action="/burn-rate-meter.php" method="post" data-return-url="/burn-rate-meter?success=cash">
                    <input type="hidden" name="action" value="update_cash">
                    <?php echo csrfInput(); ?>
                    <label>
                        Liquidità disponibile (€)
                        <input
                            type="number"
                            name="initial_cash"
                            min="0"
                            max="9999999999.99"
                            step="0.01"
                            value="<?php echo number_format($cassaIniziale, 2, '.', ''); ?>"
                            required
                        >
                    </label>
                    <button type="submit">Aggiorna cassa</button>
                </form>
            </section>
        </div>

        <section class="burn-rate-card" aria-labelledby="expenses-list-title">
            <div class="section-header">
                <h2 id="expenses-list-title">Ultime spese registrate</h2>
                <p>Le spese sono associate al tuo account.</p>
            </div>
            <?php if ($ultimeSpese === []): ?>
                <p class="meter-caption">Non hai ancora registrato spese.</p>
            <?php else: ?>
                <div class="burn-rate-table-wrapper">
                    <table class="burn-rate-table">
                        <thead>
                            <tr>
                                <th scope="col">Data</th>
                                <th scope="col">Descrizione</th>
                                <th scope="col">Importo</th>
                                <th scope="col" style="text-align: right;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimeSpese as $spesa): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((new DateTimeImmutable($spesa['expense_date']))->format('d/m/Y'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($spesa['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>€ <?php echo number_format((float) $spesa['amount'], 2, ',', '.'); ?></td>
                                    <td style="text-align: right;">
                                        <form action="/burn-rate-meter.php" method="post" data-return-url="/burn-rate-meter?success=deleted" onsubmit="return confirm('Sei sicuro di voler eliminare questa spesa?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_expense">
                                            <input type="hidden" name="expense_id" value="<?php echo (int) $spesa['id']; ?>">
                                            <input type="hidden" name="expense_source" value="<?php echo htmlspecialchars($spesa['source'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo csrfInput(); ?>
                                            <button type="submit" style="background:#ef4444; color:#fff; border:none; padding:0.3rem 0.6rem; border-radius:4px; cursor:pointer; font-size:0.85rem;">Elimina</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
