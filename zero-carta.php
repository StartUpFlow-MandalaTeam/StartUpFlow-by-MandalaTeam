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
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}
global $pdo;
$userId = (int) currentUserId();
$username = $_SESSION['username'] ?? 'Utente';

$clientsStatement = $pdo->prepare(
    'SELECT id, client_type, company_name, first_name, last_name
     FROM clients
     WHERE user_id = :user_id
     ORDER BY COALESCE(company_name, last_name, first_name)'
);
$clientsStatement->execute([':user_id' => $userId]);
$clients = $clientsStatement->fetchAll();

$documentsStatement = $pdo->prepare(
    'SELECT d.id, d.type, d.file_path, d.created_at, c.id AS client_id,
            c.company_name, c.first_name, c.last_name
     FROM documents d
     INNER JOIN clients c ON c.id = d.client_id
     WHERE c.user_id = :user_id
     ORDER BY d.created_at DESC, d.id DESC'
);
$documentsStatement->execute([':user_id' => $userId]);
$documents = $documentsStatement->fetchAll();
$storageRoot = realpath(__DIR__ . '/storage/contracts');
?>
<div class="service-page zero-carta">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>"Zero carta"</h1>
        <p>Benvenuto, <?php echo htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8'); ?>!</p>
    </header>
    <div class="service-content">
        <div class="zero-carta__cards" role="tablist" aria-label="Gestione documenti" data-zero-carta-cards>
            <button class="zero-carta__card" type="button" role="tab" id="zero-carta-tab-archive"
                aria-selected="false" aria-controls="zero-carta-panel-archive" data-zero-carta-tab="archive">
                <span class="zero-carta__card-title">Archivio</span>
                <span class="zero-carta__card-description">Visualizza e scarica i tuoi contratti e NDA.</span>
            </button>
            <button class="zero-carta__card" type="button" role="tab" id="zero-carta-tab-upload"
                aria-selected="false" aria-controls="zero-carta-panel-upload" data-zero-carta-tab="upload">
                <span class="zero-carta__card-title">Carica</span>
                <span class="zero-carta__card-description">Aggiungi un nuovo documento per un cliente.</span>
            </button>
        </div>

        <section class="zero-carta__panel" id="zero-carta-panel-archive" role="tabpanel"
            aria-labelledby="zero-carta-tab-archive" data-zero-carta-panel="archive" hidden>
            <button class="zero-carta__back" type="button" data-zero-carta-back aria-label="Torna alla scelta">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 12H5m0 0 7-7m-7 7 7 7"></path></svg>
            </button>
            <?php
            $visibleDocuments = [];
            foreach ($documents as $document) {
                $expectedPrefix = 'storage/contracts/' . $userId . '/';
                if (!str_starts_with((string) $document['file_path'], $expectedPrefix) || $storageRoot === false) {
                    continue;
                }
                $absolutePath = realpath(__DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $document['file_path']));
                if ($absolutePath === false || !str_starts_with($absolutePath, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($absolutePath)) {
                    continue;
                }
                $visibleDocuments[] = $document;
            }
            ?>
            <div class="zero-carta__filters" aria-label="Filtri archivio">
                <label class="zero-carta__filter">
                    <span>Cerca per nome file</span>
                    <input type="search" data-zero-carta-filter="name" placeholder="Es. contratto.pdf">
                </label>
                <label class="zero-carta__filter">
                    <span>Cliente</span>
                    <select data-zero-carta-filter="client">
                        <option value="">Tutti i clienti</option>
                        <?php foreach ($clients as $client): ?>
                            <?php
                            $filterClientName = $client['company_name'] ?: trim((string) $client['first_name'] . ' ' . (string) $client['last_name']);
                            ?>
                            <option value="<?php echo (int) $client['id']; ?>">
                                <?php echo htmlspecialchars((string) $filterClientName, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="zero-carta__filter">
                    <span>Tipo documento</span>
                    <select data-zero-carta-filter="type">
                        <option value="">Tutti i tipi</option>
                        <option value="NDA">NDA</option>
                        <option value="Contract">Contratti</option>
                    </select>
                </label>
            </div>
            <p class="zero-carta__empty" data-zero-carta-empty <?php echo $visibleDocuments !== [] ? 'hidden' : ''; ?>>
                L'archivio è vuoto. I documenti caricati appariranno qui.
            </p>
            <p class="zero-carta__empty" data-zero-carta-no-matches hidden>
                Nessun documento corrisponde ai filtri selezionati.
            </p>
            <?php if ($visibleDocuments !== []): ?>
                <div class="zero-carta__list" data-zero-carta-list>
                    <?php foreach ($visibleDocuments as $document): ?>
                        <?php
                        $clientName = $document['company_name'] ?: trim((string) $document['first_name'] . ' ' . (string) $document['last_name']);
                        $storedName = basename((string) $document['file_path']);
                        $displayName = preg_replace('/^(?:\d{8}-\d{6}-[a-f0-9]{16}-|[a-f0-9]{16}-)/', '', $storedName) ?: $storedName;
                        $fileExtension = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
                        $fileTypeLabel = $fileExtension === 'pdf' ? 'PDF' : 'DOCX';
                        ?>
                        <article class="zero-carta__document zero-carta__document--<?php echo strtolower((string) $document['type']) === 'nda' ? 'nda' : 'contract'; ?>" data-zero-carta-document
                            data-document-name="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>"
                            data-document-client="<?php echo (int) $document['client_id']; ?>"
                            data-document-type="<?php echo htmlspecialchars((string) $document['type'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="zero-carta__document-info">
                                <h3>
                                    <span class="zero-carta__file-icon zero-carta__file-icon--<?php echo strtolower($fileTypeLabel); ?>" aria-label="<?php echo htmlspecialchars($fileTypeLabel, ENT_QUOTES, 'UTF-8'); ?> file">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M6 2.75h8l4.25 4.3v14.2H6a2 2 0 0 1-2-2v-14.5a2 2 0 0 1 2-2Z"></path>
                                            <path d="M14 2.75v4.5h4.25M7.5 12h7M7.5 15.5h7"></path>
                                        </svg>
                                        <span><?php echo htmlspecialchars($fileTypeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                    <span><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                                </h3>
                                <p>
                                    <?php echo htmlspecialchars((string) $clientName, ENT_QUOTES, 'UTF-8'); ?>
                                    <span class="zero-carta__badge"><?php echo htmlspecialchars((string) $document['type'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>Caricato il <?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $document['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                </p>
                            </div>
                            <a class="zero-carta__download" href="/zero-paper/zero-carta-download.php?id=<?php echo (int) $document['id']; ?>">Scarica</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="zero-carta__panel" id="zero-carta-panel-upload" role="tabpanel"
            aria-labelledby="zero-carta-tab-upload" data-zero-carta-panel="upload" data-zero-carta-upload-panel hidden>
            <button class="zero-carta__back" type="button" data-zero-carta-back aria-label="Torna alla scelta">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 12H5m0 0 7-7m-7 7 7 7"></path></svg>
            </button>
            <div class="zero-carta__upload-content">
                <h2>Carica un documento</h2>
                <p class="zero-carta__intro">Scegli il tipo di documento e il cliente a cui associarlo.</p>
            <?php if ($clients === []): ?>
                <div class="zero-carta__empty">Aggiungi prima un cliente al CRM per poter caricare un documento.</div>
            <?php else: ?>
                <form class="zero-carta__form" action="/zero-paper/zero-carta-upload.php" method="post" enctype="multipart/form-data">
                    <?php echo csrfInput(); ?>
                    <label class="zero-carta__field">
                        <span>Tipo di documento</span>
                        <select name="type" required>
                            <option value="">Seleziona un tipo</option>
                            <option value="NDA">NDA</option>
                            <option value="Contract">Contratto</option>
                        </select>
                    </label>
                    <label class="zero-carta__field">
                        <span>Cliente</span>
                        <select name="client_id" required>
                            <option value="">Seleziona un cliente</option>
                            <?php foreach ($clients as $client): ?>
                                <?php
                                $clientName = $client['company_name'] ?: trim((string) $client['first_name'] . ' ' . (string) $client['last_name']);
                                ?>
                                <option value="<?php echo (int) $client['id']; ?>">
                                    <?php echo htmlspecialchars((string) $clientName, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="zero-carta__dropzone">
                        <input class="zero-carta__file" type="file" name="document" accept=".pdf,.docx" required>
                        <span class="zero-carta__drop-title">Trascina qui il file oppure clicca per selezionarlo</span>
                        <span class="zero-carta__file-name">Solo PDF o DOCX (massimo 10 MB)</span>
                    </label>
                    <p class="zero-carta__status" aria-live="polite"></p>
                    <button class="zero-carta__submit" type="submit">Carica documento</button>
                </form>
            <?php endif; ?>
            </div>
        </section>
    </div>
</div>
