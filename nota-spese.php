<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php'; // fornisce $pdo

if (!isLoggedIn()) {
    header('Location: /auth/register.php');
    exit;
}

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}

$userId   = (int)currentUserId();
$username = $_SESSION['username'] ?? 'Utente';
$messaggio = '';

// Salvataggio della spesa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data        = $_POST['data_spesa'] ?? '';
    $descrizione = trim($_POST['descrizione'] ?? '');
    $categoria   = $_POST['categoria'] ?? '';
    $importo     = (float)($_POST['importo'] ?? 0);

    $categorieValide = ['Cibo', 'Trasporti', 'Software', 'Altro'];

    if ($data !== '' && $descrizione !== '' && in_array($categoria, $categorieValide, true) && $importo > 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO spese (user_id, data_spesa, descrizione, categoria, importo)
             VALUES (:user_id, :data, :descrizione, :categoria, :importo)'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':data'        => $data,
            ':descrizione' => $descrizione,
            ':categoria'   => $categoria,
            ':importo'     => $importo,
        ]);
        $messaggio = 'Spesa salvata.';
    } else {
        $messaggio = 'Compila tutti i campi correttamente.';
    }
}

// Elenco delle spese dell'utente
$stmt = $pdo->prepare(
    'SELECT data_spesa, descrizione, categoria, importo
     FROM spese
     WHERE user_id = :user_id
     ORDER BY data_spesa DESC, id DESC'
);
$stmt->execute([':user_id' => $userId]);
$spese = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Nota Spese</h1>
        <p>Benvenuto, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!</p>
    </header>

    <div class="service-content">
        <?php if ($messaggio !== ''): ?>
            <div class="alert alert-info"><?= htmlspecialchars($messaggio, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="/nota-spese">
            <label for="data_spesa">Data</label>
            <input type="date" id="data_spesa" name="data_spesa" required>

            <label for="descrizione">Descrizione</label>
            <input type="text" id="descrizione" name="descrizione" maxlength="255" required>

            <label for="categoria">Categoria</label>
            <select id="categoria" name="categoria" required>
                <option value="">Scegli...</option>
                <option value="Cibo">Cibo</option>
                <option value="Trasporti">Trasporti</option>
                <option value="Software">Software</option>
                <option value="Altro">Altro</option>
            </select>

            <label for="importo">Importo (€)</label>
            <input type="number" id="importo" name="importo" step="0.01" min="0.01" required>

            <button type="submit">Salva spesa</button>
        </form>

        <h2>Elenco spese</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrizione</th>
                    <th>Categoria</th>
                    <th>Importo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($spese as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['data_spesa'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($s['descrizione'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($s['categoria'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((float)$s['importo'], 2, ',', '.') ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>