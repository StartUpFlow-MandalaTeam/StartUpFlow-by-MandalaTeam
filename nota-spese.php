<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';            // fornisce $pdo
require_once __DIR__ . '/includes/nota-spese-logic.php';

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

const NS_CARTELLA_UPLOAD = __DIR__ . '/uploads/spese';

$userId   = (int)currentUserId();
$username = $_SESSION['username'] ?? 'Utente';
$messaggi = [];
$errori   = [];

// Salvataggio della spesa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $risultato = validaSpesa($_POST);
    $errori = $risultato['errori'];

    if (!$errori) {
        $nomeFile = null;
        try {
            // Salva lo scontrino (se presente) e ottiene il nome casuale del file
            $nomeFile = salvaScontrino(
                $_FILES['scontrino'] ?? ['error' => UPLOAD_ERR_NO_FILE],
                NS_CARTELLA_UPLOAD
            );

            $dati = $risultato['dati'];
            $stmt = $pdo->prepare(
                'INSERT INTO spese (user_id, data_spesa, descrizione, categoria, importo, scontrino_path)
                 VALUES (:user_id, :data, :descrizione, :categoria, :importo, :scontrino_path)'
            );
            $stmt->execute([
                ':user_id'        => $userId,
                ':data'           => $dati['data'],
                ':descrizione'    => $dati['descrizione'],
                ':categoria'      => $dati['categoria'],
                ':importo'        => sprintf('%.2f', $dati['importo_centesimi'] / 100),
                ':scontrino_path' => $nomeFile,
            ]);

            $messaggi[] = 'Spesa salvata.';
        } catch (RuntimeException $e) {
            // File non valido (tipo, dimensione, errore di upload)
            $errori[] = $e->getMessage();
        } catch (PDOException $e) {
            // Il file è già stato salvato: se il DB fallisce, lo cancelliamo
            if ($nomeFile !== null && is_file(NS_CARTELLA_UPLOAD . '/' . $nomeFile)) {
                unlink(NS_CARTELLA_UPLOAD . '/' . $nomeFile);
            }
            error_log('Errore salvataggio spesa: ' . $e->getMessage());
            $errori[] = 'Impossibile salvare la spesa. Riprova.';
        }
    }
}

// Elenco delle spese dell'utente
$stmt = $pdo->prepare(
    'SELECT data_spesa, descrizione, categoria, importo, scontrino_path
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
        <?php foreach ($messaggi as $m): ?>
            <div class="alert alert-success"><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
        <?php foreach ($errori as $e): ?>
            <div class="alert alert-error"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <form method="post" action="/nota-spese" enctype="multipart/form-data">
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

            <label for="scontrino">Foto dello scontrino (facoltativa)</label>
            <input type="file" id="scontrino" name="scontrino" accept="image/jpeg,image/png,application/pdf">

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
                    <th>Scontrino</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($spese as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['data_spesa'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($s['descrizione'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($s['categoria'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((float)$s['importo'], 2, ',', '.') ?> €</td>
                        <td><?= $s['scontrino_path'] ? 'Sì' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
