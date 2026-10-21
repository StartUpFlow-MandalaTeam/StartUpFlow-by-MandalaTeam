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
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || isset($_GET['action']) 
          || ($_SERVER['REQUEST_METHOD'] === 'POST');
if (!$isAjax) {
    header('Location: /');
    exit;
}
global $pdo;
$userId = currentUserId();
if ($userId === null) {
    http_response_code(401);
    exit;
}
$username = $_SESSION['username'] ?? 'Utente';

$additionalCss = '<link rel="stylesheet" href="/assets/css/crm-clienti.css">';


// Database connection
$db = $pdo;

// Helpers: errori, CSRF, accesso al cliente
function abortWith(int $status, string $message): void {
    http_response_code($status);
    echo '<div class="service-page"><p>' . htmlspecialchars($message) . '</p></div>';
    exit;
}

function requireWritePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        abortWith(405, 'Metodo non consentito');
    }
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        abortWith(403, 'Richiesta non autorizzata');
    }
}

function requireClient(PDO $db, int $clientId, int $userId) {
    $client = Client::find($db, $clientId, $userId);
    if (!$client) {
        abortWith(404, 'Cliente non trovato');
    }
    return $client;
}

// Esegue $fn in transazione; se una transazione è già aperta la riusa.
function dbTransaction(PDO $db, callable $fn) {
    if ($db->inTransaction()) {
        return $fn();
    }
    $db->beginTransaction();
    try {
        $result = $fn();
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

// MODIFICA: helper per la campanella di notifica della prossima azione.
// Restituisce stringa vuota se il cliente non ha una prossima azione.
function renderNextActionBell($client): string {
    if (empty($client->next_action_at)) {
        return '';
    }
    $ts  = strtotime($client->next_action_at);
    $now = time();
    if ($ts < $now) {
        $state = 'overdue'; $status = 'Scaduta';
    } elseif ($ts - $now <= 86400) {
        $state = 'soon';    $status = 'Entro 24 ore';
    } else {
        $state = 'ok';      $status = 'Programmata';
    }
 
    $types = ['call' => 'Chiamata', 'email' => 'Email', 'meeting' => 'Riunione', 'note' => 'Nota'];
    $type  = $types[$client->na_type ?? ''] ?? (string) ($client->na_type ?? '');
 
    $name = $client->client_type === 'company'
        ? ($client->company_name ?? '')
        : trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
 
    $title = 'Prossima azione — ' . $name;
    $text  = $status . ': ' . date('d-m-Y H:i', $ts)
           . ' — Da fare dopo: ' . $type
           . (!empty($client->na_subject) ? ' · ' . $client->na_subject : '');
 
    $flags = JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP;
    return ' <button type="button" class="next-action-bell next-action-bell--' . $state . '"'
         . ' title="' . htmlspecialchars($status . ': ' . date('d-m-Y H:i', $ts), ENT_QUOTES) . '"'
         . ' aria-label="Prossima azione"'
         . ' onclick="showStatusPopup(' . htmlspecialchars(json_encode($title, $flags), ENT_QUOTES)
         . ', ' . htmlspecialchars(json_encode($text, $flags), ENT_QUOTES) . ')">🔔</button>';
}


// UNICO punto del codice che scrive crm_status, converted_at e lost_reason.
class ClientService {
    // Passaggi manuali ammessi.'customer' non è mai raggiungibile a mano:
    // ci si arriva solo con markAsCustomer(), alla conferma di un ordine.
    private const MANUAL_TRANSITIONS = [
        'lead'     => ['prospect', 'lost'],
        'prospect' => ['lead', 'lost'],
        'lost'     => ['lead', 'prospect'],
    ];

    // Promozione a customer. Idempotente: se il cliente è già customer non tocca nulla.
    public static function markAsCustomer(PDO $db, int $clientId, int $userId, int $orderId, string $orderNumber): bool {
        return dbTransaction($db, function () use ($db, $clientId, $userId, $orderId, $orderNumber): bool {
            $oldStatus = self::lockStatus($db, $clientId, $userId);
            if (!in_array($oldStatus, ['lead', 'prospect', 'lost'], true)) {
                return false;
            }
            $upd = $db->prepare("UPDATE clients SET crm_status = 'customer', converted_at = NOW(), lost_reason = NULL WHERE id = ? AND user_id = ?");
            $upd->execute([$clientId, $userId]);
            self::logChange($db, $clientId, $userId, $orderId, 'Promosso a customer', 'Da ' . $oldStatus . ' a customer (ordine ' . $orderNumber . ')');
            return true;
        });
    }

    // Cambio di stato manuale (mai verso o da 'customer').
    public static function changeStatus(PDO $db, int $clientId, int $userId, string $newStatus, ?string $lostReason = null): void {
        dbTransaction($db, function () use ($db, $clientId, $userId, $newStatus, $lostReason): void {
            $oldStatus = self::lockStatus($db, $clientId, $userId);
            if ($oldStatus === $newStatus) {
                return;
            }
            if (!in_array($newStatus, self::MANUAL_TRANSITIONS[$oldStatus] ?? [], true)) {
                throw new DomainException('Passaggio di stato non consentito: ' . $oldStatus . ' → ' . $newStatus);
            }
            $reason = null;
            if ($newStatus === 'lost') {
                $reason = trim((string) $lostReason);
                if ($reason === '' || strlen($reason) > 255) {
                    throw new DomainException('Indica il motivo della perdita (massimo 255 caratteri).');
                }
            }
            $upd = $db->prepare('UPDATE clients SET crm_status = ?, lost_reason = ? WHERE id = ? AND user_id = ?');
            $upd->execute([$newStatus, $reason, $clientId, $userId]);
            self::logChange($db, $clientId, $userId, null, 'Cambio stato', 'Da ' . $oldStatus . ' a ' . $newStatus . ($reason !== null ? ' (motivo: ' . $reason . ')' : ''));
        });
    }

    private static function lockStatus(PDO $db, int $clientId, int $userId): string {
        $sel = $db->prepare('SELECT crm_status FROM clients WHERE id = ? AND user_id = ? FOR UPDATE');
        $sel->execute([$clientId, $userId]);
        $status = $sel->fetchColumn();
        if ($status === false) {
            throw new DomainException('Cliente non trovato');
        }
        return (string) $status;
    }

    private static function logChange(PDO $db, int $clientId, int $userId, ?int $orderId, string $subject, string $body): void {
        $stmt = $db->prepare("INSERT INTO crm_activities (client_id, order_id, user_id, type, subject, body, occurred_at) VALUES (?, ?, ?, 'status_change', ?, ?, NOW())");
        $stmt->execute([$clientId, $orderId, $userId, $subject, $body]);
    }
}

// Models
class Client {
        public static function all($db, int $userId) {
        $stmt = $db->prepare("
            SELECT c.*,
                   na.type           AS na_type,
                   na.subject        AS na_subject,
                   na.next_action_at AS next_action_at
              FROM clients c
              LEFT JOIN crm_activities na
                     ON na.id = (SELECT a.id
                                   FROM crm_activities a
                                  WHERE a.client_id = c.id
                                    AND a.type != 'status_change'
                                  ORDER BY a.occurred_at DESC, a.id DESC
                                  LIMIT 1)
                    AND na.next_action_at IS NOT NULL
             WHERE c.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }


    public static function find($db, int $id, int $userId) {
        $stmt = $db->prepare('SELECT * FROM clients WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public static function save($db, $data) {
        $fields = ['company_name', 'first_name', 'last_name', 'vat_number', 'cf', 'address', 'city', 'state', 'postal_code', 'country', 'email', 'phone', 'pec', 'sdi_code', 'source', 'notes'];
        foreach ($fields as $field) {
            if (isset($data[$field]) && trim((string)$data[$field]) === '') {
                $data[$field] = null;
            }
        }
        $data['client_type'] = $data['client_type'] ?? 'individual';

        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $db->prepare('UPDATE clients SET client_type = ?, company_name = ?, first_name = ?, last_name = ?, vat_number = ?, cf = ?, address = ?, city = ?, state = ?, postal_code = ?, country = ?, email = ?, phone = ?, pec = ?, sdi_code = ?, source = ?, notes = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([
                $data['client_type'], $data['company_name'] ?? null, $data['first_name'] ?? null, 
                $data['last_name'] ?? null, $data['vat_number'] ?? null, $data['cf'] ?? null, $data['address'] ?? null, 
                $data['city'] ?? null, $data['state'] ?? null, $data['postal_code'] ?? null, $data['country'] ?? null, 
                $data['email'] ?? null, $data['phone'] ?? null, $data['pec'] ?? null, $data['sdi_code'] ?? null, 
                $data['source'] ?? null, $data['notes'] ?? null, $data['id'], $data['user_id']
            ]);
        } else {
            $stmt = $db->prepare('INSERT INTO clients (user_id, client_type, company_name, first_name, last_name, vat_number, cf, address, city, state, postal_code, country, email, phone, pec, sdi_code, source, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $data['user_id'], $data['client_type'], $data['company_name'] ?? null, $data['first_name'] ?? null, 
                $data['last_name'] ?? null, $data['vat_number'] ?? null, $data['cf'] ?? null, $data['address'] ?? null, 
                $data['city'] ?? null, $data['state'] ?? null, $data['postal_code'] ?? null, $data['country'] ?? null, 
                $data['email'] ?? null, $data['phone'] ?? null, $data['pec'] ?? null, $data['sdi_code'] ?? null, 
                $data['source'] ?? null, $data['notes'] ?? null
            ]);
        }
    }
}

class Contact {
    public static function where($db, $client_id) {
        $stmt = $db->prepare('SELECT * FROM client_contacts WHERE client_id = ?');
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function save($db, $data) {
        $stmt = $db->prepare('INSERT INTO client_contacts (client_id, first_name, last_name, role, email, phone, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['client_id'], $data['first_name'], $data['last_name'], $data['role'], $data['email'], $data['phone'], $data['is_primary'] ?? 0]);
    }
}

class Activity {
    public static function where($db, $client_id) {
        $stmt = $db->prepare('SELECT * FROM crm_activities WHERE client_id = ? AND type != "status_change" ORDER BY occurred_at DESC, id DESC');
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function save($db, $data) {
        $stmt = $db->prepare('INSERT INTO crm_activities (client_id, user_id, type, subject, body, occurred_at, next_action_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['client_id'], $data['user_id'], $data['type'], $data['subject'], $data['body'], $data['occurred_at'] ?: date('Y-m-d H:i:s'), $data['next_action_at'] ?: null]);
    }
}

class Order {
    private const MAX_ITEMS = 50;
    private const MAX_AMOUNT = 99999999.99;
    // Aliquote IVA ammesse nelle righe (0 per chi è in regime forfettario).
    private const VAT_RATES = ['0', '4', '5', '10', '22'];

    // Ordini del cliente, ciascuno con le proprie righe in $order->items.
    public static function where($db, $client_id, int $userId) {
        $stmt = $db->prepare('SELECT * FROM orders WHERE client_id = ? AND user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$client_id, $userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_OBJ);
        if (!$orders) {
            return $orders;
        }
        $ids = array_map(static fn($o) => (int) $o->id, $orders);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id IN ($in) ORDER BY order_id, position, id");
        $itemsStmt->execute($ids);
        $byOrder = [];
        foreach ($itemsStmt->fetchAll(PDO::FETCH_OBJ) as $item) {
            $byOrder[(int) $item->order_id][] = $item;
        }
        foreach ($orders as $order) {
            $order->items = $byOrder[(int) $order->id] ?? [];
        }
        return $orders;
    }

    // Valida e calcola le righe arrivate dal modale. Il server ricalcola sempre tutto:
    // i totali mostrati dal browser sono solo un'anteprima.
    // Per riga: imponibile = quantità x prezzo; IVA = imponibile x aliquota; totale riga = imponibile + IVA.
    public static function parseItems(array $post): array {
        $fields = ['item_description', 'item_quantity', 'item_unit_price', 'item_vat_rate'];
        $cols = [];
        foreach ($fields as $field) {
            $value = $post[$field] ?? [];
            if (!is_array($value)) {
                throw new DomainException('Righe dell\'ordine non valide.');
            }
            $cols[$field] = array_values($value);
        }
        $count = count($cols['item_description']);
        foreach ($cols as $value) {
            if (count($value) !== $count) {
                throw new DomainException('Righe dell\'ordine incomplete.');
            }
        }
        if ($count > self::MAX_ITEMS) {
            throw new DomainException('Massimo ' . self::MAX_ITEMS . ' righe per ordine.');
        }

        $items = [];
        $subtotal = 0.0;
        $tax = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $row = [];
            foreach ($fields as $field) {
                if (!is_scalar($cols[$field][$i])) {
                    throw new DomainException('Righe dell\'ordine non valide.');
                }
                $row[$field] = trim((string) $cols[$field][$i]);
            }
            if ($row['item_description'] === '' && $row['item_unit_price'] === '') {
                continue; // riga lasciata vuota nel modale
            }
            $n = $i + 1;
            $length = function_exists('mb_strlen') ? mb_strlen($row['item_description'], 'UTF-8') : strlen($row['item_description']);
            if ($row['item_description'] === '' || $length > 500) {
                throw new DomainException("Riga $n: descrizione obbligatoria (massimo 500 caratteri).");
            }
            if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $row['item_quantity']) || (float) $row['item_quantity'] <= 0) {
                throw new DomainException("Riga $n: quantità non valida (maggiore di zero, massimo 2 decimali).");
            }
            if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $row['item_unit_price'])) {
                throw new DomainException("Riga $n: prezzo unitario non valido (massimo 2 decimali).");
            }
            if (!in_array($row['item_vat_rate'], self::VAT_RATES, true)) {
                throw new DomainException("Riga $n: aliquota IVA non valida.");
            }
            $quantity = round((float) $row['item_quantity'], 2);
            $unitPrice = round((float) $row['item_unit_price'], 2);
            $rate = (int) $row['item_vat_rate'];
            $taxable = round($quantity * $unitPrice, 2);
            $taxAmount = round($taxable * $rate / 100, 2);
            $lineTotal = round($taxable + $taxAmount, 2);
            if ($lineTotal > self::MAX_AMOUNT) {
                throw new DomainException("Riga $n: importo oltre il limite consentito.");
            }
            $subtotal = round($subtotal + $taxable, 2);
            $tax = round($tax + $taxAmount, 2);
            $items[] = [
                'description' => $row['item_description'],
                'quantity'    => number_format($quantity, 2, '.', ''),
                'unit_price'  => number_format($unitPrice, 2, '.', ''),
                'vat_rate'    => number_format($rate, 2, '.', ''),
                'line_total'  => number_format($lineTotal, 2, '.', ''),
            ];
        }
        if (!$items) {
            throw new DomainException('Aggiungi almeno una riga all\'ordine.');
        }
        $total = round($subtotal + $tax, 2);
        if ($total > self::MAX_AMOUNT) {
            throw new DomainException('Il totale dell\'ordine supera il limite consentito.');
        }
        return ['items' => $items, 'subtotal' => $subtotal, 'tax' => $tax, 'total' => $total];
    }

    // Scrive le righe dell'ordine. I valori arrivano già validati e ricalcolati dal server
    // (parseItems): qui si persiste solo, senza ricalcolare nulla.
    public static function insertItems(PDO $db, int $orderId, array $items): void {
        $stmt = $db->prepare('INSERT INTO order_items (order_id, position, description, quantity, unit_price, vat_rate, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $position = 1;
        foreach ($items as $item) {
            $stmt->execute([
                $orderId,
                $position++,
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['vat_rate'],
                $item['line_total'],
            ]);
        }
    }

    // Sostituisce integralmente le righe di un ordine: usato in modifica, dove il totale
    // viene ricalcolato e le righe precedenti non sono più valide.
    public static function replaceItems(PDO $db, int $orderId, array $items): void {
        $del = $db->prepare('DELETE FROM order_items WHERE order_id = ?');
        $del->execute([$orderId]);
        self::insertItems($db, $orderId, $items);
    }

    // Crea l'ordine (già confermato) con le sue righe e promuove il cliente, tutto in un'unica transazione:
    // o passano ordine + promozione + riga di storico, o non passa niente.
    public static function save($db, $data): int {
        $userId = (int) $data['user_id'];
        $clientId = (int) $data['client_id'];

        $orderNumber = trim((string) ($data['order_number'] ?? ''));
        if ($orderNumber === '') {
            $orderNumber = 'ORD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
        }
        if (strlen($orderNumber) > 50) {
            throw new DomainException('Il numero ordine supera i 50 caratteri.');
        }
        // Le note dell'ordine diventeranno la causale della fattura.
        $notes = trim((string) ($data['notes'] ?? ''));
        if (strlen($notes) > 2000) {
            throw new DomainException('Le note superano i 2000 caratteri.');
        }
        $notes = $notes !== '' ? $notes : null;

        $currency = (string) ($data['currency'] ?? 'EUR');
        if (!in_array($currency, ['EUR', 'USD', 'GBP'], true)) {
            $currency = 'EUR';
        }

        // Il totale è sempre calcolato dalle righe: l'eventuale 'total' del POST viene ignorato.
        $parsed = self::parseItems($data);
        $total = number_format($parsed['total'], 2, '.', '');

        try {
            return dbTransaction($db, function () use ($db, $data, $userId, $clientId, $orderNumber, $total, $currency, $notes, $parsed): int {
                if (!empty($data['id'])) {
                    // Modifica: solo bozze. Non cambia stato né confirmed_at e non promuove il cliente.
                    $orderId = (int) $data['id'];
                    $cur = $db->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ? AND client_id = ? FOR UPDATE');
                    $cur->execute([$orderId, $userId, $clientId]);
                    $status = $cur->fetchColumn();
                    if ($status === false) {
                        throw new DomainException('Ordine non trovato.');
                    }
                    if ($status !== 'draft') {
                        throw new DomainException('Solo le bozze sono modificabili.');
                    }
                    $stmt = $db->prepare('UPDATE orders SET order_number = ?, total = ?, currency = ?, notes = ? WHERE id = ? AND user_id = ?');
                    $stmt->execute([$orderNumber, $total, $currency, $notes, $orderId, $userId]);
                    // Il totale è ricalcolato sulle righe nuove: le vecchie vanno sostituite.
                    self::replaceItems($db, $orderId, $parsed['items']);
                    return $orderId;
                }

                $stmt = $db->prepare('INSERT INTO orders (user_id, client_id, order_number, total, currency, status, payment_status, notes, confirmed_at) VALUES (?, ?, ?, ?, ?, \'confirmed\', \'unpaid\', ?, NOW())');
                $stmt->execute([$userId, $clientId, $orderNumber, $total, $currency, $notes]);
                $orderId = (int) $db->lastInsertId();
                self::insertItems($db, $orderId, $parsed['items']);

                // Promozione: unico punto di scrittura dello stato = ClientService
                ClientService::markAsCustomer($db, $clientId, $userId, $orderId, $orderNumber);
                return $orderId;
            });
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Esiste già un ordine con questo numero.');
            }
            throw $e;
        }
    }
}

// Views
// Renderizza la lista dei clienti in tabella (desktop) o card (mobile).
function renderIndex($clients) {
    echo '<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Dashboard</a>
        <h1>Customer Relationship Management (CRM)</h1>
        <div class="crm-legend">
            <span class="legend-label">Legenda (clicca per info):</span>
            <span class="status-pill status-lead" onclick="showStatusPopup(\'Lead\', \'Contatto iniziale o potenziale cliente appena acquisito con interesse generico.\')">Lead</span>
            <span class="status-pill status-prospect" onclick="showStatusPopup(\'Prospect\', \'Cliente potenziale qualificato con cui è attiva una trattativa commerciale.\')">Prospect</span>
            <span class="status-pill status-customer" onclick="showStatusPopup(\'Customer\', \'Cliente acquisito ed effettivo, la cui conversione avviene alla conferma di un ordine.\')">Customer</span>
            <span class="status-pill status-lost" onclick="showStatusPopup(\'Lost\', \'Trattativa persa o potenziale cliente che ha rifiutato ogni offerta o interrotto i contatti.\')">Lost</span>
        </div>
    </header>
    <div class="service-content">
        <a href="crm-clienti.php?action=create" class="btn btn-primary" data-spa="true">Aggiungi Cliente</a>
        <div class="desktop-table-view">
            <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Stato</th>
                        <th>Pipeline Age</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>';
    foreach ($clients as $client) {
        $cName = htmlspecialchars($client->client_type === 'company' ? ($client->company_name ?? '') : (($client->first_name ?? '') . ' ' . ($client->last_name ?? '')));
        $cEmail = htmlspecialchars($client->email ?? '');
        $cStatus = htmlspecialchars($client->crm_status ?? '');
        $cCreatedAt = $client->created_at ?? '';
        $cConvertedAt = $client->converted_at ?? '';
        // Pipeline Age: numero di giorni di calendario dalla creazione del cliente (ora) a now()
        // Per customer: differenza tra created_at e converted_at
        $pipelineAge = '';
        if (!empty($cCreatedAt)) {
            $created = new DateTime($cCreatedAt);
            $today = ($client->crm_status === 'customer' && !empty($cConvertedAt)) ? new DateTime($cConvertedAt) : new DateTime();
            $interval = $created->diff($today);
            $pipelineAge = $interval->days . ' gg.';
        }
        echo '<tr>
            <td>' . $cName . renderNextActionBell($client) . '</td>
            <td>' . $cEmail . '</td>
            <td>' . $cStatus . '</td>
            <td>' . $pipelineAge . '</td>
            <td>
                <a href="crm-clienti.php?action=show&id=' . $client->id . '" class="btn btn-info" data-spa="true">Visualizza</a>
                <a href="crm-clienti.php?action=edit&id=' . $client->id . '" class="btn btn-warning" data-spa="true">Modifica</a>
            </td>
        </tr>';
    }
    echo '</tbody>
            </table>
            </div>
        </div>

        <div class="mobile-cards-view">';
    foreach ($clients as $client) {
        $cName = htmlspecialchars($client->client_type === 'company' ? ($client->company_name ?? '') : (($client->first_name ?? '') . ' ' . ($client->last_name ?? '')));
        $cEmail = htmlspecialchars($client->email ?? '');
        $cStatus = htmlspecialchars($client->crm_status ?? '');
        $cCreatedAt = $client->created_at ?? '';
        $cConvertedAt = $client->converted_at ?? '';
        // Pipeline Age: giorni a partire dalla creazione del cliente (ora - created_at, diviso per giorno intero)
        // Per customer: differenza tra created_at e converted_at
        $pipelineAge = '';
        if (!empty($cCreatedAt)) {
            $created = new DateTime($cCreatedAt);
            $today = ($client->crm_status === 'customer' && !empty($cConvertedAt)) ? new DateTime($cConvertedAt) : new DateTime();
            $interval = $created->diff($today);
            $pipelineAge = $interval->days . ' gg.';
        }
        echo '<div class="mobile-card">
            <div class="mobile-card-row"><span class="mobile-card-label">Nome:</span><span class="mobile-card-value">' . $cName . renderNextActionBell($client) . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Email:</span><span class="mobile-card-value">' . $cEmail . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Stato:</span><span class="mobile-card-value">' . $cStatus . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Pipeline Age:</span><span class="mobile-card-value">' . $pipelineAge . '</span></div>
            <div class="mobile-card-row" style="margin-top:0.75rem; justify-content:flex-end; gap:0.5rem; border-bottom:none;">
                <a href="crm-clienti.php?action=show&id=' . $client->id . '" class="btn btn-info" data-spa="true">Visualizza</a>
                <a href="crm-clienti.php?action=edit&id=' . $client->id . '" class="btn btn-warning" data-spa="true">Modifica</a>
            </div>
        </div>';
    }
    echo '</div>
    </div>
</div>';
}

// Renderizza il form di creazione di un nuovo cliente.
function renderCreate() {
    echo '<div class="service-page">
    <header class="service-header">
        <nav aria-label="breadcrumb" class="crm-breadcrumb">
            <span class="crm-breadcrumb__item">
                <a href="/" data-spa="true" class="crm-breadcrumb__link">Dashboard</a>
            </span>
            <span class="crm-breadcrumb__separator" aria-hidden="true">/</span>
            <span class="crm-breadcrumb__item">
                <a href="crm-clienti.php" data-spa="true" class="crm-breadcrumb__link">CRM</a>
            </span>
        </nav>
        <h1>Aggiungi Cliente</h1>
    </header>
    <div class="service-content">
        <form action="crm-clienti.php?action=store" method="POST">
            ' . csrfInput() . '
            <div class="form-group">
                <label>Tipo Cliente:</label>
                <select name="client_type" class="form-control">
                    <option value="individual">Persona Fisica</option>
                    <option value="company">Azienda</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nome Azienda:</label>
                <input type="text" name="company_name" class="form-control">
            </div>
            <div class="form-group">
                <label>Nome:</label>
                <input type="text" name="first_name" class="form-control">
            </div>
            <div class="form-group">
                <label>Cognome:</label>
                <input type="text" name="last_name" class="form-control">
            </div>
            <div class="form-group">
                <label>P.IVA:</label>
                <input type="text" name="vat_number" class="form-control">
            </div>
            <div class="form-group">
                <label>CF:</label>
                <input type="text" name="cf" class="form-control">
            </div>
            <div class="form-group">
                <label>Indirizzo:</label>
                <input type="text" name="address" class="form-control">
            </div>
            <div class="form-group">
                <label>Città:</label>
                <input type="text" name="city" class="form-control">
            </div>
            <div class="form-group">
                <label>Provincia:</label>
                <input type="text" name="state" class="form-control">
            </div>
            <div class="form-group">
                <label>CAP:</label>
                <input type="text" name="postal_code" class="form-control">
            </div>
            <div class="form-group">
                <label>Paese:</label>
                <input type="text" name="country" class="form-control">
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label>Telefono:</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="form-group">
                <label>PEC:</label>
                <input type="email" name="pec" class="form-control">
            </div>
            <div class="form-group">
                <label>SDI Code:</label>
                <input type="text" name="sdi_code" class="form-control">
            </div>
            <div class="form-group">
                <label>Fonte:</label>
                <input type="text" name="source" class="form-control">
            </div>
            <div class="form-group">
                <label>Note:</label>
                <textarea name="notes" class="form-control"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Salva</button>
            <a href="/crm-clienti" class="btn btn-secondary" data-spa="true">Annulla</a>
        </form>
    </div>
</div>';
}

// Renderizza il form di modifica di un cliente esistente.
function renderEdit($client) {
    echo '<div class="service-page">
    <header class="service-header">
        <nav aria-label="breadcrumb" class="crm-breadcrumb">
            <span class="crm-breadcrumb__item">
                <a href="/" data-spa="true" class="crm-breadcrumb__link">Dashboard</a>
            </span>
            <span class="crm-breadcrumb__separator" aria-hidden="true">/</span>
            <span class="crm-breadcrumb__item">
                <a href="crm-clienti.php" data-spa="true" class="crm-breadcrumb__link">CRM</a>
            </span>
        </nav>
        <h1>Modifica Cliente</h1>
    </header>
    <div class="service-content">
        <form action="crm-clienti.php?action=update&id=' . $client->id . '" method="POST">
            ' . csrfInput() . '
            <div class="form-group">
                <label>Tipo Cliente:</label>
                <select name="client_type" class="form-control">
                    <option value="individual" ' . ($client->client_type === 'individual' ? 'selected' : '') . '>Persona Fisica</option>
                    <option value="company" ' . ($client->client_type === 'company' ? 'selected' : '') . '>Azienda</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nome Azienda:</label>
                <input type="text" name="company_name" class="form-control" value="' . htmlspecialchars($client->company_name ?? '') . '">
            </div>
            <div class="form-group">
                <label>Nome:</label>
                <input type="text" name="first_name" class="form-control" value="' . htmlspecialchars($client->first_name ?? '') . '">
            </div>
            <div class="form-group">
                <label>Cognome:</label>
                <input type="text" name="last_name" class="form-control" value="' . htmlspecialchars($client->last_name ?? '') . '">
            </div>
            <div class="form-group">
                <label>P.IVA:</label>
                <input type="text" name="vat_number" class="form-control" value="' . htmlspecialchars($client->vat_number ?? '') . '">
            </div>
            <div class="form-group">
                <label>CF:</label>
                <input type="text" name="cf" class="form-control" value="' . htmlspecialchars($client->cf ?? '') . '">
            </div>
            <div class="form-group">
                <label>Indirizzo:</label>
                <input type="text" name="address" class="form-control" value="' . htmlspecialchars($client->address ?? '') . '">
            </div>
            <div class="form-group">
                <label>Città:</label>
                <input type="text" name="city" class="form-control" value="' . htmlspecialchars($client->city ?? '') . '">
            </div>
            <div class="form-group">
                <label>Stato:</label>
                <input type="text" name="state" class="form-control" value="' . htmlspecialchars($client->state ?? '') . '">
            </div>
            <div class="form-group">
                <label>CAP:</label>
                <input type="text" name="postal_code" class="form-control" value="' . htmlspecialchars($client->postal_code ?? '') . '">
            </div>
            <div class="form-group">
                <label>Paese:</label>
                <input type="text" name="country" class="form-control" value="' . htmlspecialchars($client->country ?? '') . '">
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" class="form-control" value="' . htmlspecialchars($client->email ?? '') . '">
            </div>
            <div class="form-group">
                <label>Telefono:</label>
                <input type="text" name="phone" class="form-control" value="' . htmlspecialchars($client->phone ?? '') . '">
            </div>
            <div class="form-group">
                <label>PEC:</label>
                <input type="email" name="pec" class="form-control" value="' . htmlspecialchars($client->pec ?? '') . '">
            </div>
            <div class="form-group">
                <label>SDI Code:</label>
                <input type="text" name="sdi_code" class="form-control" value="' . htmlspecialchars($client->sdi_code ?? '') . '">
            </div>
            <div class="form-group">
                <label>Stato CRM:</label>
                <p>' . htmlspecialchars($client->crm_status ?? '') . ' <small>(si modifica dalla scheda cliente)</small></p>
            </div>
            <div class="form-group">
                <label>Fonte:</label>
                <input type="text" name="source" class="form-control" value="' . htmlspecialchars($client->source ?? '') . '">
            </div>
            <div class="form-group">
                <label>Note:</label>
                <textarea name="notes" class="form-control">' . htmlspecialchars($client->notes ?? '') . '</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Aggiorna</button>
            <a href="/crm-clienti" class="btn btn-secondary" data-spa="true">Annulla</a>
        </form>
    </div>
</div>';
}

// Funzione per renderizzare il dettaglio di un cliente.
function renderShow($client, $contacts, $activities, $orders) {
    echo '<div class="service-page">
    <header class="service-header">
        <nav aria-label="breadcrumb" class="crm-breadcrumb">
            <span class="crm-breadcrumb__item">
                <a href="/" data-spa="true" class="crm-breadcrumb__link">Dashboard</a>
            </span>
            <span class="crm-breadcrumb__separator" aria-hidden="true">/</span>
            <span class="crm-breadcrumb__item">
                <a href="crm-clienti.php" data-spa="true" class="crm-breadcrumb__link">CRM</a>
            </span>
        </nav>
        <h1>Dettagli Cliente</h1>
    </header>
    <div class="service-content">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">' . htmlspecialchars($client->client_type === 'company' ? ($client->company_name ?? '') : (($client->first_name ?? '') . ' ' . ($client->last_name ?? ''))) . '</h5>
                <p class="card-text"><strong>Email:</strong> ' . htmlspecialchars($client->email ?? '') . '</p>
                <p class="card-text"><strong>Telefono:</strong> ' . htmlspecialchars($client->phone ?? '') . '</p>
                <p class="card-text"><strong>Indirizzo:</strong> ' . htmlspecialchars($client->address ?? '') . '</p>
                <p class="card-text"><strong>Città:</strong> ' . htmlspecialchars($client->city ?? '') . '</p>
                <p class="card-text"><strong>Stato:</strong> ' . htmlspecialchars($client->state ?? '') . '</p>
                <p class="card-text"><strong>CAP:</strong> ' . htmlspecialchars($client->postal_code ?? '') . '</p>
                <p class="card-text"><strong>Paese:</strong> ' . htmlspecialchars($client->country ?? '') . '</p>
                <p class="card-text"><strong>P.IVA:</strong> ' . htmlspecialchars($client->vat_number ?? '') . '</p>
                <p class="card-text"><strong>CF:</strong> ' . htmlspecialchars($client->cf ?? '') . '</p>
                <p class="card-text"><strong>PEC:</strong> ' . htmlspecialchars($client->pec ?? '') . '</p>
                <p class="card-text"><strong>SDI Code:</strong> ' . htmlspecialchars($client->sdi_code ?? '') . '</p>
                <p class="card-text"><strong>Stato CRM:</strong> ' . htmlspecialchars($client->crm_status ?? '') . '</p>
                ' . (($client->crm_status ?? '') === 'lost' && !empty($client->lost_reason) ? '<p class="card-text"><strong>Motivo:</strong> ' . htmlspecialchars($client->lost_reason) . '</p>' : '') . '
                <p class="card-text"><strong>Fonte:</strong> ' . htmlspecialchars($client->source ?? '') . '</p>
                <p class="card-text"><strong>Note:</strong> ' . htmlspecialchars($client->notes ?? '') . '</p>
            </div>
        </div>

        <h2>Contatti</h2>
        <a href="crm-clienti.php?action=create_contact&id=' . $client->id . '" class="btn btn-primary" data-spa="true">Aggiungi Contatto</a>
        <div class="desktop-table-view">
            <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Telefono</th>
                        <th>Ruolo</th>
                        <th>Primario</th>
                    </tr>
                </thead>
                <tbody>';
    foreach ($contacts as $contact) {
        echo '<tr>
            <td data-label="Nome">' . htmlspecialchars(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) . '</td>
            <td data-label="Email">' . htmlspecialchars($contact->email ?? '') . '</td>
            <td data-label="Telefono">' . htmlspecialchars($contact->phone ?? '') . '</td>
            <td data-label="Ruolo">' . htmlspecialchars($contact->role ?? '') . '</td>
            <td data-label="Primario">' . (($contact->is_primary ?? 0) ? 'Sì' : 'No') . '</td>
        </tr>';
    }
    echo '</tbody>
            </table>
            </div>
        </div>

        <div class="mobile-cards-view">';
    foreach ($contacts as $contact) {
        $cContactName = htmlspecialchars(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
        $cEmail = htmlspecialchars($contact->email ?? '');
        $cPhone = htmlspecialchars($contact->phone ?? '');
        $cRole = htmlspecialchars($contact->role ?? '');
        $cPrimary = (($contact->is_primary ?? 0) ? 'Sì' : 'No');
        echo '<div class="mobile-card">
            <div class="mobile-card-row"><span class="mobile-card-label">Nome:</span><span class="mobile-card-value">' . $cContactName . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Email:</span><span class="mobile-card-value">' . $cEmail . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Telefono:</span><span class="mobile-card-value">' . $cPhone . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Ruolo:</span><span class="mobile-card-value">' . $cRole . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Primario:</span><span class="mobile-card-value">' . $cPrimary . '</span></div>
        </div>';
    }
    echo '</div>

        <h2>Attività</h2>
        <a href="crm-clienti.php?action=create_activity&id=' . $client->id . '" class="btn btn-primary" data-spa="true">Aggiungi Attività</a>
        <div class="desktop-table-view">
            <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Oggetto</th>
                        <th>Data</th>
                        <th>Prossima Azione</th>
                    </tr>
                </thead>
                <tbody>';
    foreach ($activities as $activity) {
        $occurredFormatted = !empty($activity->occurred_at) ? date('d-m-Y / H:i', strtotime($activity->occurred_at)) : '';
        $nextActionFormatted = !empty($activity->next_action_at) ? date('d-m-Y / H:i', strtotime($activity->next_action_at)) : '';
        echo '<tr>
            <td data-label="Tipo">' . htmlspecialchars($activity->type ?? '') . '</td>
            <td data-label="Oggetto">' . htmlspecialchars($activity->subject ?? '') . '</td>
            <td data-label="Data">' . htmlspecialchars($occurredFormatted) . '</td>
            <td data-label="Prossima Azione">' . htmlspecialchars($nextActionFormatted) . '</td>
        </tr>';
    }
    echo '</tbody>
            </table>
            </div>
        </div>

        <div class="mobile-cards-view">';
    foreach ($activities as $activity) {
        $aType = htmlspecialchars($activity->type ?? '');
        $aSubject = htmlspecialchars($activity->subject ?? '');
        $aOccurred = !empty($activity->occurred_at) ? htmlspecialchars(date('d-m-Y H:i', strtotime($activity->occurred_at))) : '';
        $aNextAction = !empty($activity->next_action_at) ? htmlspecialchars(date('d-m-Y H:i', strtotime($activity->next_action_at))) : '';
        echo '<div class="mobile-card">
            <div class="mobile-card-row"><span class="mobile-card-label">Tipo:</span><span class="mobile-card-value">' . $aType . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Oggetto:</span><span class="mobile-card-value">' . $aSubject . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Data:</span><span class="mobile-card-value">' . $aOccurred . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Prossima Azione:</span><span class="mobile-card-value">' . $aNextAction . '</span></div>
        </div>';
    }
    echo '</div>

        <h2>Ordini</h2>
        <button type="button" class="btn btn-primary" data-order-modal-open data-client-id="' . $client->id . '">Crea Ordine</button>
        <div class="desktop-table-view">
            <div class="table-container orders-table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>N. Ordine</th>
                        <th>Totale</th>
                        <th>Valuta</th>
                        <th>Stato</th>
                        <th>Pagamento</th>
                    </tr>
                </thead>
                <tbody>';
    foreach ($orders as $order) {
        echo '<tr>
            <td data-label="N. Ordine">' . htmlspecialchars($order->order_number ?? '') . '</td>
            <td data-label="Totale">' . htmlspecialchars(number_format((float)($order->total ?? 0), 2, ',', '.')) . '</td>
            <td data-label="Valuta">' . htmlspecialchars($order->currency ?? 'EUR') . '</td>
            <td data-label="Stato">' . htmlspecialchars($order->status ?? '') . '</td>
            <td data-label="Pagamento">' . htmlspecialchars($order->payment_status ?? '') . '</td>
        </tr>';
        $orderLines = '';
        foreach (($order->items ?? []) as $item) {
            $orderLines .= '<li>' . htmlspecialchars((string)($item->description ?? ''))
                . ' — ' . htmlspecialchars(number_format((float)($item->quantity ?? 0), 2, ',', '.'))
                . ' × ' . htmlspecialchars(number_format((float)($item->unit_price ?? 0), 2, ',', '.'))
                . ' (IVA ' . htmlspecialchars(number_format((float)($item->vat_rate ?? 0), 0))
                . '%) = ' . htmlspecialchars(number_format((float)($item->line_total ?? 0), 2, ',', '.')) . '</li>';
        }
        if ($orderLines !== '') {
            echo '<tr class="order-lines"><td colspan="5"><ul>' . $orderLines . '</ul></td></tr>';
        }
    }
    echo '</tbody>
            </table>
            </div>
        </div>

        <div class="mobile-cards-view">';
    foreach ($orders as $order) {
        $oNumber = htmlspecialchars($order->order_number ?? '');
        $oTotal = htmlspecialchars(number_format((float)($order->total ?? 0), 2, ',', '.'));
        $oCurrency = htmlspecialchars($order->currency ?? 'EUR');
        $oStatus = htmlspecialchars($order->status ?? '');
        $oPayment = htmlspecialchars($order->payment_status ?? '');
        echo '<div class="mobile-card">
            <div class="mobile-card-row"><span class="mobile-card-label">N. Ordine:</span><span class="mobile-card-value">' . $oNumber . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Totale:</span><span class="mobile-card-value">' . $oTotal . ' ' . $oCurrency . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Stato:</span><span class="mobile-card-value">' . $oStatus . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Pagamento:</span><span class="mobile-card-value">' . $oPayment . '</span></div>';
        $oLines = '';
        foreach (($order->items ?? []) as $item) {
            $oLines .= '<li>' . htmlspecialchars((string)($item->description ?? ''))
                . ' — ' . htmlspecialchars(number_format((float)($item->quantity ?? 0), 2, ',', '.'))
                . ' × ' . htmlspecialchars(number_format((float)($item->unit_price ?? 0), 2, ',', '.'))
                . ' (IVA ' . htmlspecialchars(number_format((float)($item->vat_rate ?? 0), 0))
                . '%) = ' . htmlspecialchars(number_format((float)($item->line_total ?? 0), 2, ',', '.')) . '</li>';
        }
        if ($oLines !== '') {
            echo '<ul class="order-lines__list">' . $oLines . '</ul>';
        }
        echo '</div>';
    }
    echo '</div>

        <h2>Stato CRM</h2>';
    if (($client->crm_status ?? '') === 'customer') {
        echo '<p>CUSTOMER (promozione automatica, alla conferma di un ordine).</p>';
    } else {
        $statusLabels = ['lead' => 'Lead', 'prospect' => 'Prospect', 'lost' => 'Lost'];
        echo '<form action="crm-clienti.php?action=update_status&id=' . $client->id . '" method="POST">' . csrfInput() . '
            <div class="form-group">
                <select name="crm_status" class="form-control" id="crmStatusSelect">';
        foreach ($statusLabels as $value => $label) {
            echo '<option value="' . $value . '"' . ($client->crm_status === $value ? ' selected' : '') . '>' . $label . '</option>';
        }
        echo '</select>
            </div>
            <div class="form-group" id="lostReasonGroup" style="' . (($client->crm_status ?? '') === 'lost' ? '' : 'display:none;') . '">
                <label>Motivo (obbligatorio se Lost):</label>
                <input type="text" name="lost_reason" class="form-control" maxlength="255" value="' . htmlspecialchars($client->lost_reason ?? '') . '">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Aggiorna Stato</button>
                <a href="/crm-clienti" class="btn btn-secondary" data-spa="true">Annulla</a>
            </div>
        </form>
        <script>
        document.addEventListener("DOMContentLoaded", () => {
            const select = document.getElementById("crmStatusSelect");
            const group = document.getElementById("lostReasonGroup");
            if (select && group) {
                select.addEventListener("change", () => {
                    if (select.value === "lost") {
                        group.style.display = "block";
                    } else {
                        group.style.display = "none";
                    }
                });
            }
        });
        </script>';
    }
    echo '
    </div>
</div>';
}

// Funzione per renderizzare il form di inserimento di un nuovo contatto per un cliente esistente.
function renderCreateContact($client) {
    echo '<div class="service-page">
    <header class="service-header">
        <nav aria-label="breadcrumb" class="crm-breadcrumb">
            <span class="crm-breadcrumb__item">
                <a href="/" data-spa="true" class="crm-breadcrumb__link">Dashboard</a>
            </span>
            <span class="crm-breadcrumb__separator" aria-hidden="true">/</span>
            <span class="crm-breadcrumb__item">
                <a href="crm-clienti.php" data-spa="true" class="crm-breadcrumb__link">CRM</a>
            </span>
            <span class="crm-breadcrumb__separator" aria-hidden="true">/</span>
            <span class="crm-breadcrumb__item">
                <a href="crm-clienti.php?action=show&id=' . $client->id . '" data-spa="true" class="crm-breadcrumb__link">Dettagli Cliente</a>
            </span>
        </nav>
        <h1>Aggiungi Contatto</h1>
    </header>
    <div class="service-content service-content--narrow">
        <form action="crm-clienti.php?action=store_contact" method="POST">
            ' . csrfInput() . '
            <div class="form-group">
                <label>Nome:</label>
                <input type="text" name="first_name" class="form-control">
            </div>
            <div class="form-group">
                <label>Cognome:</label>
                <input type="text" name="last_name" class="form-control">
            </div>
            <div class="form-group">
                <label>Ruolo:</label>
                <input type="text" name="role" class="form-control">
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label>Telefono:</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="form-group form-check">
                <input type="checkbox" name="is_primary" value="1" class="form-check-input">
                <label class="form-check-label">Primario</label>
            </div>
            <input type="hidden" name="client_id" value="' . $client->id . '">
            <button type="submit" class="btn btn-primary">Salva</button>
        </form>
    </div>
</div>';
}

// Funzione per renderizzare il form di inserimento di una nuova attività per un cliente esistente.
function renderCreateActivity($client) {
    echo '<div class="service-page">
    <header class="service-header">
        <nav aria-label="breadcrumb" class="crm-breadcrumb">
            <span class="crm-breadcrumb__item">
                <a href="/" data-spa="true" class="crm-breadcrumb__link">Dashboard</a>
            </span>
            <span class="crm-breadcrumb__separator" aria-hidden="true">/</span>
            <span class="crm-breadcrumb__item">
                <a href="crm-clienti.php" data-spa="true" class="crm-breadcrumb__link">CRM</a>
            </span>
            <span class="crm-breadcrumb__separator" aria-hidden="true">/</span>
            <span class="crm-breadcrumb__item">
                <a href="crm-clienti.php?action=show&id=' . $client->id . '" data-spa="true" class="crm-breadcrumb__link">Dettagli Cliente</a>
            </span>
        </nav>
        <h1>Aggiungi Attività</h1>
    </header>
    <div class="service-content service-content--narrow">
        <form action="crm-clienti.php?action=store_activity" method="POST">
            ' . csrfInput() . '
            <div class="form-group">
                <label>Tipo:</label>
                <select name="type" class="form-control">
                    <option value="call">Chiamata</option>
                    <option value="email">Email</option>
                    <option value="meeting">Riunione</option>
                    <option value="note">Nota</option>
                </select>
            </div>
            <div class="form-group">
                <label>Oggetto:</label>
                <input type="text" name="subject" class="form-control">
            </div>
            <div class="form-group">
                <label>Descrizione:</label>
                <textarea name="body" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Attività svolta il:</label>
                <input type="datetime-local" name="occurred_at" class="form-control">
            </div>
            <div class="form-group">
                <label>Prossima Azione:</label>
                <input type="datetime-local" name="next_action_at" class="form-control">
            </div>
            <input type="hidden" name="client_id" value="' . $client->id . '">
            <button type="submit" class="btn btn-primary">Salva</button>
        </form>
    </div>
</div>';
}

// Main logic
$action = $_GET['action'] ?? 'index';
$id = (int) ($_GET['id'] ?? 0);

switch ($action) {
    case 'index':
        $clients = Client::all($db, $userId);
        renderIndex($clients);
        break;
    case 'create':
        renderCreate();
        break;
    case 'store':
        requireWritePost();
        $data = $_POST;
        unset($data['id']);
        $data['user_id'] = $userId;
        Client::save($db, $data);
        $clients = Client::all($db, $userId);
        renderIndex($clients);
        break;
    case 'show':
        $client = requireClient($db, $id, $userId);
        $contacts = Contact::where($db, $client->id);
        $activities = Activity::where($db, $client->id);
        $orders = Order::where($db, $client->id, $userId);
        renderShow($client, $contacts, $activities, $orders);
        break;
    case 'edit':
        $client = requireClient($db, $id, $userId);
        renderEdit($client);
        break;
    case 'update':
        requireWritePost();
        requireClient($db, $id, $userId);
        $data = $_POST;
        $data['id'] = $id;
        $data['user_id'] = $userId;
        Client::save($db, $data);
        $clients = Client::all($db, $userId);
        renderIndex($clients);
        break;
    case 'update_status':
        requireWritePost();
        $client = requireClient($db, $id, $userId);
        try {
            ClientService::changeStatus($db, (int) $client->id, $userId, (string) ($_POST['crm_status'] ?? ''), $_POST['lost_reason'] ?? null);
        } catch (DomainException $e) {
            abortWith(422, $e->getMessage());
        }
        $clients = Client::all($db, $userId);
        renderIndex($clients);
        break;
    case 'create_contact':
        $client = requireClient($db, $id, $userId);
        renderCreateContact($client);
        break;
    case 'store_contact':
        requireWritePost();
        $client = requireClient($db, (int) ($_POST['client_id'] ?? 0), $userId);
        $data = $_POST;
        $data['client_id'] = (int) $client->id;
        Contact::save($db, $data);
        $contacts = Contact::where($db, $client->id);
        $activities = Activity::where($db, $client->id);
        $orders = Order::where($db, $client->id, $userId);
        renderShow($client, $contacts, $activities, $orders);
        break;
    case 'create_activity':
        $client = requireClient($db, $id, $userId);
        renderCreateActivity($client);
        break;
    case 'store_activity':
        requireWritePost();
        $client = requireClient($db, (int) ($_POST['client_id'] ?? 0), $userId);
        $data = $_POST;
        $data['client_id'] = (int) $client->id;
        $data['user_id'] = $userId;
        Activity::save($db, $data);
        $contacts = Contact::where($db, $client->id);
        $activities = Activity::where($db, $client->id);
        $orders = Order::where($db, $client->id, $userId);
        renderShow($client, $contacts, $activities, $orders);
        break;
    case 'store_order':
        requireWritePost();
        $client = requireClient($db, (int) ($_POST['client_id'] ?? 0), $userId);
        $data = $_POST;
        $data['client_id'] = (int) $client->id;
        $data['user_id'] = $userId;
        try {
            Order::save($db, $data);
        } catch (DomainException $e) {
            abortWith(422, $e->getMessage());
        }
        $contacts = Contact::where($db, $client->id);
        $activities = Activity::where($db, $client->id);
        $orders = Order::where($db, $client->id, $userId);
        renderShow($client, $contacts, $activities, $orders);
        break;
    default:
        header('Location: /crm-clienti');
        break;
}
?>
