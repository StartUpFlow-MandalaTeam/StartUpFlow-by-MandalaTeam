<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php'; // Fornisce $pdo

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
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if (!$isAjax && !$isPost) {
    header('Location: /');
    exit;
}

$userId   = (int)currentUserId();
$username = $_SESSION['username'] ?? 'Utente';
$oggi     = new DateTimeImmutable('today');

// Toggle dello stato "pagato" di una fattura (richiesta fetch con X-Requested-With)
if ($isPost && isset($_POST['toggle_invoice_id'])) {
    $invId = (int)$_POST['toggle_invoice_id'];

    $stmtToggle = $pdo->prepare('UPDATE invoices SET is_paid = NOT is_paid WHERE id = :id AND user_id = :user_id');
    $stmtToggle->execute([':id' => $invId, ':user_id' => $userId]);

    if ($isAjax) {
        $stmtStatus = $pdo->prepare('SELECT is_paid, due_date FROM invoices WHERE id = :id AND user_id = :user_id');
        $stmtStatus->execute([':id' => $invId, ':user_id' => $userId]);
        $updated = $stmtStatus->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => true,
            'is_paid'  => (bool)($updated['is_paid'] ?? 0),
            'due_date' => $updated['due_date'] ?? '',
        ]);
        exit;
    }
}

// 1. Entrate: fatture clienti
$stmt = $pdo->prepare(
    'SELECT i.id, i.document_number, i.due_date, i.total, i.is_paid,
            COALESCE(c.company_name, CONCAT(COALESCE(c.first_name, ""), " ", COALESCE(c.last_name, ""))) AS cliente_nome
     FROM invoices i
     LEFT JOIN clients c ON i.client_id = c.id
     WHERE i.user_id = :user_id
     ORDER BY i.due_date ASC'
);
$stmt->execute([':user_id' => $userId]);
$fatture = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Uscite: note spese
$stmtSpese = $pdo->prepare(
    'SELECT id, expense_date, description, category, amount
     FROM expenses
     WHERE user_id = :user_id
     ORDER BY expense_date DESC'
);
$stmtSpese->execute([':user_id' => $userId]);
$spese = $stmtSpese->fetchAll(PDO::FETCH_ASSOC);

// Riepilogo
$totaleEntrate = 0.0;
$totaleUscite  = 0.0;
foreach ($fatture as $f) {
    $totaleEntrate += (float)$f['total'];
}
foreach ($spese as $s) {
    $totaleUscite += (float)$s['amount'];
}
?>
<div class="service-page scadenzario-wrapper">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Scadenzario</h1>
        <p class="scadenzario-intro">Monitoraggio dei pagamenti in entrata e in uscita aziendale.</p>
    </header>

    <div class="service-content">
        <div class="scad-summary-cards">
            <div class="scad-card scad-card--in">
                <span class="scad-card-label">Entrate previste (fatture)</span>
                <span class="scad-card-val">+<?= number_format($totaleEntrate, 2, ',', '.') ?> €</span>
            </div>
            <div class="scad-card scad-card--out">
                <span class="scad-card-label">Uscite registrate (spese)</span>
                <span class="scad-card-val">-<?= number_format($totaleUscite, 2, ',', '.') ?> €</span>
            </div>
        </div>

        <section class="scad-section">
            <h2>Pagamenti in entrata (fatture clienti)</h2>
            <div class="scad-table-wrap">
                <table class="scad-table">
                    <thead>
                        <tr>
                            <th>Scadenza</th>
                            <th>Fattura</th>
                            <th>Cliente</th>
                            <th>Importo</th>
                            <th>Stato</th>
                            <th>Azione</th>
                        </tr>
                    </thead>
                    <tbody id="fatture-tbody">
                        <?php if (empty($fatture)): ?>
                            <tr>
                                <td colspan="6" class="scad-empty">Nessuna fattura registrata nello scadenzario.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fatture as $f):
                                if ((bool)$f['is_paid']) {
                                    $classe = 'pagato';
                                    $testo  = 'Pagato';
                                } elseif ($f['due_date'] < $oggi->format('Y-m-d')) {
                                    $classe = 'scaduto';
                                    $testo  = 'Scaduto';
                                } else {
                                    $classe = 'in-attesa';
                                    $testo  = 'In attesa';
                                }
                            ?>
                                <tr id="inv-row-<?= (int)$f['id'] ?>">
                                    <td><?= htmlspecialchars($f['due_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><strong><?= htmlspecialchars($f['document_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars(trim((string)$f['cliente_nome']) ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= number_format((float)$f['total'], 2, ',', '.') ?> €</td>
                                    <td><span class="<?= $classe ?>"><?= $testo ?></span></td>
                                    <td>
                                        <button type="button"
                                                class="btn-toggle-status"
                                                data-inv-id="<?= (int)$f['id'] ?>"
                                                data-due-date="<?= htmlspecialchars($f['due_date'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= (bool)$f['is_paid'] ? 'Segna in attesa' : 'Segna pagato' ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="scad-section">
            <h2>Pagamenti in uscita (note spese e fornitori)</h2>
            <div class="scad-table-wrap">
                <table class="scad-table">
                    <thead>
                        <tr>
                            <th>Data uscita</th>
                            <th>Descrizione</th>
                            <th>Categoria</th>
                            <th>Importo</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($spese)): ?>
                            <tr>
                                <td colspan="5" class="scad-empty">Nessuna spesa o uscita registrata.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($spese as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['expense_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($s['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge-cat"><?= htmlspecialchars($s['category'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td>-<?= number_format((float)$s['amount'], 2, ',', '.') ?> €</td>
                                    <td><span class="pagato">Saldato</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<style>
body:has(.scadenzario-wrapper) {
    background: #eb7e09;
}
.main:has(.scadenzario-wrapper) {
    max-width: none;
    background: transparent;
}
.scaduto {
    color: #dc2626 !important;
    font-weight: 700;
    animation: lampeggioScaduto 1.2s infinite ease-in-out;
    display: inline-block;
}
@keyframes lampeggioScaduto {
    0%, 100% { opacity: 1; }
    50%      { opacity: 0.15; }
}
.in-attesa {
    color: #b45309 !important;
    font-weight: 600;
    display: inline-block;
}
.pagato {
    color: #15803d !important;
    font-weight: 600;
    display: inline-block;
}

.scadenzario-wrapper {
    width: 100%;
    max-width: 1050px;
    margin: 0 auto;
    padding: 1.5rem 1rem;
    box-sizing: border-box;
    float: none;
}
.scadenzario-wrapper *,
.scadenzario-wrapper *::before,
.scadenzario-wrapper *::after {
    box-sizing: border-box;
}
.scadenzario-wrapper .back-link {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.5rem 1rem;
    background: #000;
    color: #fff;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    border-radius: 4px;
    border: 1px solid #000;
}
.scadenzario-wrapper .back-link:hover {
    background: #333;
    border-color: #000;
}
.scadenzario-wrapper h1 {
    margin: 0.5rem 0 0.25rem;
}
.scadenzario-intro {
    color: #1f2937;
    font-size: 0.95rem;
    margin: 0.25rem 0 0;
}

.scad-summary-cards {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin: 1.5rem 0 2rem 0;
}
@media (min-width: 640px) {
    .scad-summary-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
.scad-card {
    background: #ffffff;
    border-radius: 8px;
    padding: 1.25rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
}
.scad-card--in  { border-left: 4px solid #10b981; }
.scad-card--out { border-left: 4px solid #ef4444; }
.scad-card-label {
    font-size: 0.85rem;
    color: #6b7280;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.03em;
}
.scad-card-val {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin-top: 0.4rem;
}

.scad-section {
    display: block;
    width: 100%;
    margin-bottom: 2.5rem;
}
.scad-section h2 {
    font-size: 1.15rem;
    color: #111827;
    margin: 0 0 0.75rem 0;
    padding-bottom: 0.4rem;
    border-bottom: 2px solid #e5e7eb;
}
.scad-table-wrap {
    width: 100%;
    overflow-x: auto;
    border-radius: 8px;
}
.scad-table {
    width: 100%;
    min-width: 560px;
    border-collapse: collapse;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
}
.scad-table th {
    background-color: #1f2937;
    color: #f9fafb;
    padding: 12px 14px;
    text-align: left;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.scad-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #e5e7eb;
    color: #374151;
    font-size: 0.95rem;
    vertical-align: middle;
}
.scad-table tr:hover {
    background-color: #f9fafb;
}
.scad-empty {
    text-align: center;
    color: #6b7280;
    padding: 2rem !important;
}

.badge-cat {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    background: #f3f4f6;
    color: #4b5563;
    font-size: 0.85rem;
}

.btn-toggle-status {
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 4px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-toggle-status:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}
</style>

<script>
(() => {
    const today = '<?= $oggi->format('Y-m-d') ?>';
    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
        btn.addEventListener('click', async () => {
            const invoiceId = btn.dataset.invId;
            const dueDate   = btn.dataset.dueDate;
            btn.disabled = true;

            try {
                const body = new URLSearchParams({ toggle_invoice_id: invoiceId });
                const res = await fetch('/scadenzario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error("Errore durante l'aggiornamento dello stato.");
                }

                const statusSpan = document
                    .getElementById('inv-row-' + invoiceId)
                    .querySelector('td:nth-child(5) span');

                if (data.is_paid) {
                    statusSpan.className = 'pagato';
                    statusSpan.textContent = 'Pagato';
                    btn.textContent = 'Segna in attesa';
                } else if (dueDate < today) {
                    statusSpan.className = 'scaduto';
                    statusSpan.textContent = 'Scaduto';
                    btn.textContent = 'Segna pagato';
                } else {
                    statusSpan.className = 'in-attesa';
                    statusSpan.textContent = 'In attesa';
                    btn.textContent = 'Segna pagato';
                }
            } catch (err) {
                alert('Errore: ' + err.message);
            } finally {
                btn.disabled = false;
            }
        });
    });
})();
</script>
