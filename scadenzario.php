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

if (!$isAjax) {
    header('Location: /');
    exit;
}

$userId   = (int)currentUserId();
$oggi     = new DateTimeImmutable('today');

// 1. Entrate: fatture clienti
$stmt = $pdo->prepare(
    'SELECT i.id, i.document_number, i.due_date, i.total, i.is_paid, o.payment_status,
            COALESCE(c.company_name, CONCAT(COALESCE(c.first_name, ""), " ", COALESCE(c.last_name, ""))) AS cliente_nome
     FROM invoices i
     LEFT JOIN clients c ON i.client_id = c.id
     LEFT JOIN orders o ON o.id = i.order_id AND o.user_id = i.user_id
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fatture)): ?>
                            <tr>
                                <td colspan="5" class="scad-empty">Nessuna fattura registrata nello scadenzario.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fatture as $f):
                                $paymentStatus = $f['payment_status'];
                                if ($paymentStatus === 'paid' || ($paymentStatus === null && (bool)$f['is_paid'])) {
                                    $classe = 'pagato';
                                    $testo  = 'Pagato';
                                } elseif ($paymentStatus === 'partial') {
                                    $classe = 'pagato-parziale';
                                    $testo  = 'Pagato parzialmente';
                                } elseif ($f['due_date'] < $oggi->format('Y-m-d')) {
                                    $classe = 'scaduto';
                                    $testo  = 'Scaduto';
                                } else {
                                    $classe = 'in-attesa';
                                    $testo  = 'In attesa';
                                }
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['due_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><strong><?= htmlspecialchars($f['document_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars(trim((string)$f['cliente_nome']) ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= number_format((float)$f['total'], 2, ',', '.') ?> €</td>
                                    <td><span class="<?= $classe ?>"><?= $testo ?></span></td>
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
