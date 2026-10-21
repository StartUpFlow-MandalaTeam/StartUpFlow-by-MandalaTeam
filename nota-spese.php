<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php'; // Fornisce $pdo

const NS_CARTELLA_RELATIVA = 'uploads/spese';
const NS_CARTELLA_UPLOAD   = __DIR__ . '/' . NS_CARTELLA_RELATIVA;
const NS_MIME_AMMESSI      = ['image/jpeg', 'image/png', 'application/pdf'];

/** Riconosce JPG, PNG o PDF dalla firma dei primi byte. Restituisce il MIME o null. */
function rilevaMime(string $percorso): ?string
{
    $h = @fopen($percorso, 'rb');
    if ($h === false) {
        return null;
    }
    $inizio = (string)fread($h, 8);
    fclose($h);

    if (str_starts_with($inizio, "\xFF\xD8\xFF")) {
        return 'image/jpeg';
    }
    if (str_starts_with($inizio, "\x89PNG\r\n\x1a\n")) {
        return 'image/png';
    }
    if (str_starts_with($inizio, '%PDF')) {
        return 'application/pdf';
    }
    return null;
}

/** Percorso assoluto dello scontrino se la spesa appartiene all'utente, altrimenti null. */
function percorsoScontrino(PDO $pdo, int $idSpesa, int $userId): ?string
{
    $stmt = $pdo->prepare('SELECT receipt_path FROM expenses WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $idSpesa, ':uid' => $userId]);
    $relativo = $stmt->fetchColumn();
    if (!$relativo) {
        return null;
    }

    if (!str_contains($relativo, '/')) {
        $relativo = NS_CARTELLA_RELATIVA . '/' . $relativo;
    }

    $assoluto = realpath(__DIR__ . '/' . $relativo);
    $base     = realpath(NS_CARTELLA_UPLOAD);
    if ($assoluto === false || $base === false || !str_starts_with($assoluto, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return is_file($assoluto) ? $assoluto : null;
}

/** Invia il file al browser con il tipo MIME reale. */
function inviaFile(string $percorso, int $id): never
{
    $mime = rilevaMime($percorso);
    if ($mime === null || !in_array($mime, NS_MIME_AMMESSI, true)) {
        http_response_code(415);
        exit('Formato non supportato.');
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($percorso));
    header('Content-Disposition: inline; filename="scontrino-' . $id . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($percorso);
    exit;
}

// ---------- VISUALIZZAZIONE SCONTRINO (GET, pagine separate) ----------

if (isset($_GET['scontrino']) || isset($_GET['anteprima'])) {
    if (!isLoggedIn()) {
        http_response_code(403);
        exit('Accesso negato.');
    }

    $userId = (int)currentUserId();

    if (isset($_GET['scontrino'])) {
        $id        = filter_input(INPUT_GET, 'scontrino', FILTER_VALIDATE_INT);
        $percorso = $id ? percorsoScontrino($pdo, $id, $userId) : null;
        if ($percorso === null) {
            http_response_code(404);
            exit('Scontrino non trovato.');
        }
        inviaFile($percorso, $id);
    }

    $id        = filter_input(INPUT_GET, 'anteprima', FILTER_VALIDATE_INT);
    $percorso = $id ? percorsoScontrino($pdo, $id, $userId) : null;
    if ($percorso === null) {
        http_response_code(404);
        exit('Scontrino non trovato.');
    }

    $mime    = rilevaMime($percorso);
    $urlFile = '/nota-spese.php?scontrino=' . $id;
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scontrino #<?= (int)$id ?></title>
    <link rel="stylesheet" href="/assets/css/nota-spese.css">
</head>
<body>
<div class="ns">
    <header class="ns-header">
        <a href="/nota-spese" class="back-link">← Torna alle spese</a>
        <h1>Scontrino #<?= (int)$id ?></h1>
        <p><a href="<?= htmlspecialchars($urlFile, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Apri a schermo intero</a></p>
    </header>
    <section class="ns-card">
        <?php if ($mime === 'application/pdf'): ?>
            <iframe class="ns-preview-frame" src="<?= htmlspecialchars($urlFile, ENT_QUOTES, 'UTF-8') ?>" title="Scontrino"></iframe>
        <?php else: ?>
            <img class="ns-preview-img" src="<?= htmlspecialchars($urlFile, ENT_QUOTES, 'UTF-8') ?>" alt="Scontrino #<?= (int)$id ?>">
        <?php endif; ?>
    </section>
</div>
</body>
</html>
    <?php
    exit;
}

// ---------- CONTROLLI DI ACCESSO (SPA e form) ----------

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if (!isLoggedIn()) {
    if ($isAjax) {
        http_response_code(401);
        exit;
    }
    header('Location: /auth/login.php');
    exit;
}

if (!$isAjax && !$isPost) {
    header('Location: /');
    exit;
}

$userId   = (int)currentUserId();
$username = $_SESSION['username'] ?? 'Utente';

$flash    = $_SESSION['ns_flash'] ?? null;
unset($_SESSION['ns_flash']);
$messaggi = $flash['ok']  ?? [];
$errori   = $flash['err'] ?? [];

// ---------- SALVATAGGIO ----------
if ($isPost) {
    $dataSpesa   = trim($_POST['data_spesa'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');
    $categoria   = trim($_POST['categoria'] ?? '');
    $importoStr  = trim($_POST['importo'] ?? '');

    if (empty($dataSpesa)) {
        $errori[] = 'La data è obbligatoria.';
    }
    if (empty($descrizione) || mb_strlen($descrizione) > 255) {
        $errori[] = 'La descrizione è obbligatoria e non può superare i 255 caratteri.';
    }
    if (!in_array($categoria, ['Cibo', 'Trasporti', 'Software', 'Altro'], true)) {
        $errori[] = 'Seleziona una categoria valida.';
    }
    if (!is_numeric($importoStr) || (float)$importoStr <= 0) {
        $errori[] = "L'importo deve essere un numero maggiore di zero.";
    } else {
        $importoFatturato = (float)$importoStr;
    }

    if (!$errori) {
        $percorsoDb   = null;
        $destinazione = null;
        try {
            $file = $_FILES['scontrino'] ?? null;
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Errore durante il caricamento del file.');
                }
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new RuntimeException('Il file supera la dimensione massima consentita (5MB).');
                }

                // Sostituito finfo con la funzione rilevaMime per compatibilità universale
                $mimeType = rilevaMime($file['tmp_name']);
                if ($mimeType === null || !in_array($mimeType, NS_MIME_AMMESSI, true)) {
                    throw new RuntimeException('Formato file non valido. Sono accettati solo JPG, PNG e PDF.');
                }

                if (!is_dir(NS_CARTELLA_UPLOAD)) {
                    mkdir(NS_CARTELLA_UPLOAD, 0755, true);
                }

                $ext = match ($mimeType) {
                    'image/jpeg'      => 'jpg',
                    'image/png'       => 'png',
                    'application/pdf' => 'pdf',
                };

                $nomeFile     = bin2hex(random_bytes(16)) . '.' . $ext;
                $destinazione = NS_CARTELLA_UPLOAD . '/' . $nomeFile;
                $percorsoDb   = NS_CARTELLA_RELATIVA . '/' . $nomeFile;

                if (!move_uploaded_file($file['tmp_name'], $destinazione)) {
                    throw new RuntimeException('Impossibile salvare il file sul server.');
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO expenses (user_id, expense_date, description, category, amount, receipt_path)
                 VALUES (:user_id, :expense_date, :description, :category, :amount, :receipt_path)'
            );
            $stmt->execute([
                ':user_id'      => $userId,
                ':expense_date' => $dataSpesa,
                ':description'  => $descrizione,
                ':category'     => $categoria,
                ':amount'       => sprintf('%.2f', $importoFatturato),
                ':receipt_path' => $percorsoDb,
            ]);

            $messaggi[] = 'Spesa salvata con successo.';
        } catch (RuntimeException $e) {
            $errori[] = $e->getMessage();
        } catch (PDOException $e) {
            if ($destinazione !== null && is_file($destinazione)) {
                unlink($destinazione);
            }
            error_log('Errore salvataggio spesa DB: ' . $e->getMessage());
            $errori[] = 'Impossibile salvare la spesa nel database.';
        }
    }

    if (!$isAjax) {
        $_SESSION['ns_flash'] = ['ok' => $messaggi, 'err' => $errori];
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }
}

// ---------- ELENCO SPESE ----------
$stmt = $pdo->prepare(
    'SELECT id, expense_date, description, category, amount, receipt_path
     FROM expenses
     WHERE user_id = :user_id
     ORDER BY expense_date DESC, id DESC'
);
$stmt->execute([':user_id' => $userId]);
$spese = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="/assets/css/nota-spese.css">

<div class="service-page ns">
    <header class="service-header ns-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Nota Spese</h1>
        <p>Benvenuto, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!</p>
    </header>

    <div class="service-content">
        <?php foreach ($messaggi as $m): ?>
            <div class="ns-alert ns-alert--ok"><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
        <?php foreach ($errori as $e): ?>
            <div class="ns-alert ns-alert--err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <section class="ns-card">
            <h2>Nuova spesa</h2>

            <form method="post" action="/nota-spese.php" enctype="multipart/form-data">
                <div class="ns-grid">
                    <div class="ns-field">
                        <label for="data_spesa">Data</label>
                        <input type="date" id="data_spesa" name="data_spesa" required>
                    </div>

                    <div class="ns-field">
                        <label for="importo">Importo (€)</label>
                        <input type="number" id="importo" name="importo" step="0.01" min="0.01" inputmode="decimal" placeholder="0.00" required>
                    </div>

                    <div class="ns-field">
                        <label for="categoria">Categoria</label>
                        <select id="categoria" name="categoria" required>
                            <option value="">Scegli...</option>
                            <option value="Cibo">Cibo</option>
                            <option value="Trasporti">Trasporti</option>
                            <option value="Software">Software</option>
                            <option value="Altro">Altro</option>
                        </select>
                    </div>

                    <div class="ns-field ns-full">
                        <label for="descrizione">Descrizione</label>
                        <input type="text" id="descrizione" name="descrizione" maxlength="255" placeholder="Es. Pranzo con il cliente" required>
                    </div>

                    <div class="ns-field">
                        <label for="scontrino">Foto dello scontrino (facoltativa)</label>
                        <input type="file" id="scontrino" name="scontrino" accept="image/jpeg,image/png,application/pdf">
                        <small class="ns-hint">JPG, PNG o PDF, massimo 5 MB</small>
                    </div>
                </div>

                <div class="ns-actions">
                    <button type="submit" class="ns-btn">Salva spesa</button>
                </div>
            </form>
        </section>

        <section class="ns-card">
            <h2>Elenco spese</h2>

            <div class="ns-table-wrap">
                <table class="ns-table">
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
                        <?php if (empty($spese)): ?>
                            <tr class="ns-vuoto">
                                <td colspan="5" class="ns-empty">Nessuna spesa registrata.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($spese as $s): ?>
                                <tr>
                                    <td data-label="Data"><?= htmlspecialchars($s['expense_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Descrizione"><?= htmlspecialchars($s['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Categoria"><?= htmlspecialchars($s['category'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Importo" class="ns-importo"><?= number_format((float)$s['amount'], 2, ',', '.') ?> €</td>
                                    <td data-label="Scontrino">
                                        <?php if ($s['receipt_path']): ?>
                                            <a href="/nota-spese.php?anteprima=<?= (int)$s['id'] ?>" target="_blank" rel="noopener">Anteprima</a>
                                            · <a href="/nota-spese.php?scontrino=<?= (int)$s['id'] ?>" target="_blank" rel="noopener">Apri</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
