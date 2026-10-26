<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php'; // Fornisce $pdo

const NS_CARTELLA_RELATIVA = 'storage/spese';
const NS_CARTELLA_UPLOAD   = __DIR__ . '/' . NS_CARTELLA_RELATIVA;
const NS_CATEGORIE         = ['Cibo', 'Trasporti', 'Software', 'Altro'];

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

/** Invia il file al browser. */
function inviaFile(string $percorso, int $id): never
{
    $mime = rilevaMime($percorso);
    if ($mime === null) {
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
        $id       = filter_input(INPUT_GET, 'scontrino', FILTER_VALIDATE_INT);
        $percorso = $id ? percorsoScontrino($pdo, $id, $userId) : null;
        if ($percorso === null) {
            http_response_code(404);
            exit('Scontrino non trovato.');
        }
        inviaFile($percorso, $id);
    }

    $id       = filter_input(INPUT_GET, 'anteprima', FILTER_VALIDATE_INT);
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
        <a href="/nota-spese.php" class="back-link">← Torna alle spese</a>
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

// ---------- CONTROLLI DI ACCESSO ----------

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

if (!$isAjax && !$isPost && empty($_SESSION['ns_flash'])) {
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

    if ($dataSpesa === '') {
        $errori[] = 'La data è obbligatoria.';
    }
    if ($descrizione === '' || mb_strlen($descrizione) > 255) {
        $errori[] = 'La descrizione è obbligatoria e non può superare i 255 caratteri.';
    }
    if (!in_array($categoria, NS_CATEGORIE, true)) {
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
                    throw new RuntimeException('Il file supera la dimensione massima consentita (5 MB).');
                }

                $mimeType = rilevaMime($file['tmp_name']);
                if ($mimeType === null) {
                    throw new RuntimeException('Formato file non valido. Sono accettati solo JPG, PNG e PDF.');
                }

                if (!is_dir(NS_CARTELLA_UPLOAD) && !mkdir(NS_CARTELLA_UPLOAD, 0755, true) && !is_dir(NS_CARTELLA_UPLOAD)) {
                    throw new RuntimeException('Impossibile creare la cartella ' . NS_CARTELLA_RELATIVA . ' sul server.');
                }
                if (!is_writable(NS_CARTELLA_UPLOAD)) {
                    throw new RuntimeException('La cartella ' . NS_CARTELLA_RELATIVA . ' non è scrivibile dal server.');
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
                    throw new RuntimeException('Impossibile salvare il file nello storage.');
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
            if ($destinazione !== null && is_file($destinazione)) {
                unlink($destinazione);
            }
            $errori[] = $e->getMessage();
        } catch (PDOException $e) {
            if ($destinazione !== null && is_file($destinazione)) {
                unlink($destinazione);
            }
            error_log('Errore salvataggio spesa DB: ' . $e->getMessage());
            $errori[] = 'Impossibile salvare la spesa nel database. Riprova.';
        }
    }

    if (!$isAjax) {
        $_SESSION['ns_flash'] = ['ok' => $messaggi, 'err' => $errori];
        header('Location: /nota-spese.php');
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

$totaleSpese = 0.0;
foreach ($spese as $s) {
    $totaleSpese += (float)$s['amount'];
}
?>
<div class="service-page ns">
    <header class="service-header ns-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Nota Spese</h1>
        <p>Registra le spese di lavoro e consulta quelle archiviate.</p>
    </header>

    <div class="service-content">
        <?php foreach ($messaggi as $m): ?>
            <div class="ns-alert ns-alert--ok"><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
        <?php foreach ($errori as $e): ?>
            <div class="ns-alert ns-alert--err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <!-- Menu: due azioni, come in Fatturazione -->
        <nav class="ns-actions-menu" aria-label="Azioni nota spese">
            <button type="button" class="ns-action" data-ns-action="browse">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5.5h16M4 12h16M4 18.5h16M2.5 5.5h.01M2.5 12h.01M2.5 18.5h.01" /></svg>
                <span>Spese archiviate</span>
            </button>
            <button type="button" class="ns-action" data-ns-action="create">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14M4 3.5h16a.5.5 0 0 1 .5.5v16a.5.5 0 0 1-.5.5H4a.5.5 0 0 1-.5-.5V4a.5.5 0 0 1 .5-.5Z" /></svg>
                <span>Nuova spesa</span>
            </button>
        </nav>

        <!-- Vista: spese archiviate -->
        <section class="ns-view ns-browse" id="ns-browse" hidden>
            <div class="ns-view-heading">
                <button type="button" class="ns-back" data-ns-action="home" aria-label="Torna al menu" title="Torna indietro">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 5-7 7 7 7M8 12h13" /></svg>
                </button>
                <h2>Spese archiviate</h2>
            </div>

            <div class="ns-totale">
                <span>Totale spese</span>
                <strong><?= number_format($totaleSpese, 2, ',', '.') ?> €</strong>
            </div>

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
                                    <td data-label="Categoria"><span class="ns-badge ns-badge--<?= htmlspecialchars(strtolower($s['category']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($s['category'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td data-label="Importo" class="ns-importo"><?= number_format((float)$s['amount'], 2, ',', '.') ?> €</td>
                                    <td data-label="Scontrino">
                                        <?php if ($s['receipt_path']): ?>
                                            <a href="/nota-spese.php?scontrino=<?= (int)$s['id'] ?>" target="_blank" rel="noopener">Apri</a>
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

        <!-- Vista: nuova spesa -->
        <section class="ns-view ns-create" id="ns-create" hidden>
            <div class="ns-view-heading">
                <button type="button" class="ns-back" data-ns-action="home" aria-label="Torna al menu" title="Torna indietro">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 5-7 7 7 7M8 12h13" /></svg>
                </button>
                <h2>Nuova spesa</h2>
            </div>

            <form id="form-nota-spese" class="ns-form" method="post" action="/nota-spese.php" enctype="multipart/form-data">
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
                            <?php foreach (NS_CATEGORIE as $cat): ?>
                                <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
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
    </div>
    <script src="/assets/js/nota-spese.js" defer></script>
</div>