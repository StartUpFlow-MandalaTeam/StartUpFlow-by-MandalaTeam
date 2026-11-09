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
    echo '<div class="burn-rate-meter"><div class="service-content"><div class="burn-rate-notice" role="alert"><p>'
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

try {
    $cashStatement = $pdo->prepare('SELECT initial_cash FROM burn_rate_settings WHERE user_id = :user_id');
    $cashStatement->execute([':user_id' => $userId]);
    $savedCash = $cashStatement->fetchColumn();
    $cassaIniziale = $savedCash === false ? 50000.0 : (float) $savedCash;

    $incomeStatement = $pdo->prepare(
        'SELECT COALESCE(SUM(total), 0)
         FROM invoices
         WHERE user_id = :user_id
           AND is_paid = 1
           AND currency = \'EUR\'
           AND issue_date >= :month_start
           AND issue_date < :next_month_start'
    );
    $incomeStatement->execute([
        ':user_id' => $userId,
        ':month_start' => $monthStart->format('Y-m-d'),
        ':next_month_start' => $nextMonthStart->format('Y-m-d'),
    ]);
    $entrateMensili = (float) $incomeStatement->fetchColumn();

    $expenseTotalStatement = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0)
         FROM burn_rate_expenses
         WHERE user_id = :user_id
           AND expense_date >= :month_start
           AND expense_date < :next_month_start'
    );
    $expenseTotalStatement->execute([
        ':user_id' => $userId,
        ':month_start' => $monthStart->format('Y-m-d'),
        ':next_month_start' => $nextMonthStart->format('Y-m-d'),
    ]);
    $speseMensili = (float) $expenseTotalStatement->fetchColumn();

    $expensesStatement = $pdo->prepare(
        'SELECT description, amount, expense_date
         FROM burn_rate_expenses
         WHERE user_id = :user_id
         ORDER BY expense_date DESC, id DESC
         LIMIT 10'
    );
    $expensesStatement->execute([':user_id' => $userId]);
    $ultimeSpese = $expensesStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Burn rate data load failed: ' . $exception->getMessage());
    $showError(500, 'Non è stato possibile caricare i dati. Verifica che il database sia aggiornato e riprova.');
}

$username = $_SESSION['username'] ?? 'Utente';
$netBurnRate = $speseMensili - $entrateMensili;
$runwayMesi = $netBurnRate > 0 ? round($cassaIniziale / $netBurnRate, 1) : INF;
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
    'cash' => 'Cassa iniziale aggiornata.',
][(string) ($_GET['success'] ?? '')] ?? null;
$meseCorrente = $monthStart->format('m/Y');
?>

<div class="burn-rate-meter">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Burn Rate & Runway Meter</h1>
        <p>Benvenuto, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</p>
    </header>

    <div class="service-content">
        <?php if ($successMessage !== null): ?>
            <div class="burn-rate-notice burn-rate-notice--success" role="status">
                <p><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

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
                    <span class="label">Autonomia (runway)</span>
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
                <p class="meter-caption">Runway calcolata sul saldo del mese corrente e sulla cassa iniziale.</p>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimeSpese as $spesa): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((new DateTimeImmutable($spesa['expense_date']))->format('d/m/Y'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($spesa['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>€ <?php echo number_format((float) $spesa['amount'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
