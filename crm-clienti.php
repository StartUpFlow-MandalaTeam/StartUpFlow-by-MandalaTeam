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
$userId = currentUserId() ?? 1;
$username = $_SESSION['username'] ?? 'Utente';

$additionalCss = '<link rel="stylesheet" href="/assets/css/crm-clienti.css">';
echo $additionalCss;

// Database connection
$db = $pdo;

// Models
class Client {
    public static function all($db) {
        $stmt = $db->query('SELECT * FROM clients');
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function find($db, $id) {
        $stmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public static function save($db, $data) {
        $fields = ['company_name', 'first_name', 'last_name', 'vat_number', 'cf', 'address', 'city', 'state', 'postal_code', 'country', 'email', 'phone', 'pec', 'sdi_code', 'source', 'notes'];
        foreach ($fields as $field) {
            if (isset($data[$field]) && trim((string)$data[$field]) === '') {
                $data[$field] = null;
            }
        }
        $data['crm_status'] = $data['crm_status'] ?? 'lead';
        $data['client_type'] = $data['client_type'] ?? 'individual';

        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $db->prepare('UPDATE clients SET user_id = ?, client_type = ?, company_name = ?, first_name = ?, last_name = ?, vat_number = ?, cf = ?, address = ?, city = ?, state = ?, postal_code = ?, country = ?, email = ?, phone = ?, pec = ?, sdi_code = ?, crm_status = ?, source = ?, notes = ? WHERE id = ?');
            $stmt->execute([
                $data['user_id'], $data['client_type'], $data['company_name'] ?? null, $data['first_name'] ?? null, 
                $data['last_name'] ?? null, $data['vat_number'] ?? null, $data['cf'] ?? null, $data['address'] ?? null, 
                $data['city'] ?? null, $data['state'] ?? null, $data['postal_code'] ?? null, $data['country'] ?? null, 
                $data['email'] ?? null, $data['phone'] ?? null, $data['pec'] ?? null, $data['sdi_code'] ?? null, 
                $data['crm_status'], $data['source'] ?? null, $data['notes'] ?? null, $data['id']
            ]);
        } else {
            $stmt = $db->prepare('INSERT INTO clients (user_id, client_type, company_name, first_name, last_name, vat_number, cf, address, city, state, postal_code, country, email, phone, pec, sdi_code, crm_status, source, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $data['user_id'], $data['client_type'], $data['company_name'] ?? null, $data['first_name'] ?? null, 
                $data['last_name'] ?? null, $data['vat_number'] ?? null, $data['cf'] ?? null, $data['address'] ?? null, 
                $data['city'] ?? null, $data['state'] ?? null, $data['postal_code'] ?? null, $data['country'] ?? null, 
                $data['email'] ?? null, $data['phone'] ?? null, $data['pec'] ?? null, $data['sdi_code'] ?? null, 
                $data['crm_status'], $data['source'] ?? null, $data['notes'] ?? null
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
        $stmt = $db->prepare('SELECT * FROM crm_activities WHERE client_id = ? AND type != "status_change"');
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function save($db, $data) {
        $stmt = $db->prepare('INSERT INTO crm_activities (client_id, user_id, type, subject, body, occurred_at, next_action_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['client_id'], $data['user_id'], $data['type'], $data['subject'], $data['body'], $data['occurred_at'] ?: date('Y-m-d H:i:s'), $data['next_action_at'] ?: null]);
    }
}

class Order {
    public static function where($db, $client_id) {
        $stmt = $db->prepare('SELECT * FROM orders WHERE client_id = ? ORDER BY created_at DESC');
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function save($db, $data) {
        $orderNumber = trim($data['order_number'] ?? '') !== '' ? $data['order_number'] : 'ORD-' . time();
        $total = isset($data['total']) && is_numeric($data['total']) ? (float)$data['total'] : 0.00;
        $currency = $data['currency'] ?? 'EUR';
        $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;
        $taxRegime = $data['tax_regime'] ?? 'ordinario';
        $userId = $data['user_id'];
        $clientId = $data['client_id'];

        $notes = "Regime: {$taxRegime}" . ($dueDate ? ", Scadenza: {$dueDate}" : "");

        $orderId = null;
        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $db->prepare('UPDATE orders SET user_id = ?, client_id = ?, order_number = ?, total = ?, currency = ?, status = ?, notes = ? WHERE id = ?');
            $stmt->execute([
                $userId, $clientId, $orderNumber, $total, $currency, 'confirmed', $notes, $data['id']
            ]);
            $orderId = $data['id'];
        } else {
            $stmt = $db->prepare('INSERT INTO orders (user_id, client_id, order_number, total, currency, status, payment_status, notes, confirmed_at) VALUES (?, ?, ?, ?, ?, \'confirmed\', \'unpaid\', ?, NOW())');
            $stmt->execute([
                $userId, $clientId, $orderNumber, $total, $currency, $notes
            ]);
            $orderId = $db->lastInsertId();
        }

        // Promuovi il cliente a customer se non lo è già e logga l'attività
        $clientStmt = $db->prepare('SELECT crm_status FROM clients WHERE id = ?');
        $clientStmt->execute([$clientId]);
        $clientObj = $clientStmt->fetch(PDO::FETCH_OBJ);

        if ($clientObj && in_array($clientObj->crm_status, ['lead', 'prospect', 'lost'])) {
            $oldStatus = $clientObj->crm_status;
            $upd = $db->prepare('UPDATE clients SET crm_status = \'customer\', converted_at = NOW(), lost_reason = NULL WHERE id = ? AND crm_status IN (\'lead\',\'prospect\',\'lost\')');
            $upd->execute([$clientId]);
            if ($upd->rowCount() > 0) {
                $actStmt = $db->prepare('INSERT INTO crm_activities (client_id, order_id, user_id, type, subject, body, occurred_at) VALUES (?, ?, ?, \'status_change\', \'Promosso a customer\', ?, NOW())');
                $actStmt->execute([
                    $clientId,
                    $orderId,
                    $userId,
                    'Da ' . $oldStatus . ' a customer (ordine ' . $orderNumber . ')'
                ]);
            }
        }
    }
}

// Views
function renderIndex($clients) {
    echo '<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Dashboard</a>
        <h1>CRM clienti</h1>
        <div class="crm-legend">
            <span class="legend-label">Legenda (clicca per info):</span>
            <span class="status-pill status-lead" onclick="showStatusPopup(\'Lead\', \'Contatto iniziale o potenziale cliente appena acquisito con interesse generico.\')">Lead</span>
            <span class="status-pill status-prospect" onclick="showStatusPopup(\'Prospect\', \'Cliente potenziale qualificato con cui è attiva una trattativa commerciale.\')">Prospect</span>
            <span class="status-pill status-customer" onclick="showStatusPopup(\'Customer\', \'Cliente acquisito ed effettivo, la cui conversione avviene alla conferma di un ordine.\')">Customer</span>
            <span class="status-pill status-lost" onclick="showStatusPopup(\'Lost\', \'Trattativa persa o potenziale cliente che ha rifiutato ogni offerta o interrotto i contatti.\')">Lost</span>
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
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>';
    foreach ($clients as $client) {
        $cName = htmlspecialchars($client->client_type === 'company' ? ($client->company_name ?? '') : (($client->first_name ?? '') . ' ' . ($client->last_name ?? '')));
        $cEmail = htmlspecialchars($client->email ?? '');
        $cStatus = htmlspecialchars($client->crm_status ?? '');
        echo '<tr>
            <td>' . $cName . '</td>
            <td>' . $cEmail . '</td>
            <td>' . $cStatus . '</td>
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
        echo '<div class="mobile-card">
            <div class="mobile-card-row"><span class="mobile-card-label">Nome:</span><span class="mobile-card-value">' . $cName . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Email:</span><span class="mobile-card-value">' . $cEmail . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Stato:</span><span class="mobile-card-value">' . $cStatus . '</span></div>
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

function renderCreate() {
    echo '<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Dashboard</a>
        <h1>Aggiungi Cliente</h1>
    </header>
    <div class="service-content">
        <form action="crm-clienti.php?action=store" method="POST">
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
                <label>Stato CRM:</label>
                <select name="crm_status" class="form-control">
                    <option value="lead">Lead</option>
                    <option value="prospect">Prospect</option>
                    <option value="customer">Customer</option>
                    <option value="lost">Lost</option>
                </select>
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

function renderEdit($client) {
    echo '<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Dashboard</a>
        <h1>Modifica Cliente</h1>
    </header>
    <div class="service-content">
        <form action="crm-clienti.php?action=update&id=' . $client->id . '" method="POST">
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
                <select name="crm_status" class="form-control">
                    <option value="lead" ' . ($client->crm_status === 'lead' ? 'selected' : '') . '>Lead</option>
                    <option value="prospect" ' . ($client->crm_status === 'prospect' ? 'selected' : '') . '>Prospect</option>
                    <option value="customer" ' . ($client->crm_status === 'customer' ? 'selected' : '') . '>Customer</option>
                    <option value="lost" ' . ($client->crm_status === 'lost' ? 'selected' : '') . '>Lost</option>
                </select>
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

function renderShow($client, $contacts, $activities, $orders) {
    echo '<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Dashboard</a>
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
                        <th>Scadenza</th>
                        <th>Stato</th>
                        <th>Pagamento</th>
                    </tr>
                </thead>
                <tbody>';
    foreach ($orders as $order) {
        $notes = $order->notes ?? '';
        $dueDateFormatted = '';
        if (preg_match('/Scadenza:\s*([^\,]+)/', $notes, $m)) {
            $rawDate = trim($m[1]);
            $dueDateFormatted = !empty($rawDate) ? date('d-m-Y', strtotime($rawDate)) : '';
        }
        echo '<tr>
            <td data-label="N. Ordine">' . htmlspecialchars($order->order_number ?? '') . '</td>
            <td data-label="Totale">' . htmlspecialchars(number_format((float)($order->total ?? 0), 2, ',', '.')) . '</td>
            <td data-label="Valuta">' . htmlspecialchars($order->currency ?? 'EUR') . '</td>
            <td data-label="Scadenza">' . htmlspecialchars($dueDateFormatted) . '</td>
            <td data-label="Stato">' . htmlspecialchars($order->status ?? '') . '</td>
            <td data-label="Pagamento">' . htmlspecialchars($order->payment_status ?? '') . '</td>
        </tr>';
    }
    echo '</tbody>
            </table>
            </div>
        </div>

        <div class="mobile-cards-view">';
    foreach ($orders as $order) {
        $notes = $order->notes ?? '';
        $oDueDate = '';
        if (preg_match('/Scadenza:\s*([^\,]+)/', $notes, $m)) {
            $rawDate = trim($m[1]);
            $oDueDate = !empty($rawDate) ? htmlspecialchars(date('d-m-Y', strtotime($rawDate))) : '';
        }
        $oNumber = htmlspecialchars($order->order_number ?? '');
        $oTotal = htmlspecialchars(number_format((float)($order->total ?? 0), 2, ',', '.'));
        $oCurrency = htmlspecialchars($order->currency ?? 'EUR');
        $oStatus = htmlspecialchars($order->status ?? '');
        $oPayment = htmlspecialchars($order->payment_status ?? '');
        echo '<div class="mobile-card">
            <div class="mobile-card-row"><span class="mobile-card-label">N. Ordine:</span><span class="mobile-card-value">' . $oNumber . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Totale:</span><span class="mobile-card-value">' . $oTotal . ' ' . $oCurrency . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Scadenza:</span><span class="mobile-card-value">' . $oDueDate . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Stato:</span><span class="mobile-card-value">' . $oStatus . '</span></div>
            <div class="mobile-card-row"><span class="mobile-card-label">Pagamento:</span><span class="mobile-card-value">' . $oPayment . '</span></div>
        </div>';
    }
    echo '</div>

        <h2>Stato CRM</h2>
        <form action="crm-clienti.php?action=update_status&id=' . $client->id . '" method="POST">
            <div class="form-group">
                <select name="crm_status" class="form-control">
                    <option value="lead" ' . ($client->crm_status === 'lead' ? 'selected' : '') . '>Lead</option>
                    <option value="prospect" ' . ($client->crm_status === 'prospect' ? 'selected' : '') . '>Prospect</option>
                    <option value="customer" ' . ($client->crm_status === 'customer' ? 'selected' : '') . '>Customer</option>
                    <option value="lost" ' . ($client->crm_status === 'lost' ? 'selected' : '') . '>Lost</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Aggiorna Stato</button>
                <a href="/crm-clienti" class="btn btn-secondary" data-spa="true">Annulla</a>
            </div>
        </form>
    </div>
</div>';
}

function renderCreateContact($client) {
    echo '<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Dashboard</a>
        <h1>Aggiungi Contatto</h1>
    </header>
    <div class="service-content service-content--narrow">
        <form action="crm-clienti.php?action=store_contact" method="POST">
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

function renderCreateActivity($client) {
    echo '<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Dashboard</a>
        <h1>Aggiungi Attività</h1>
    </header>
    <div class="service-content service-content--narrow">
        <form action="crm-clienti.php?action=store_activity" method="POST">
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
                <label>Data:</label>
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
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'index':
        $clients = Client::all($db);
        renderIndex($clients);
        break;
    case 'create':
        renderCreate();
        break;
    case 'store':
        $data = $_POST;
        $data['user_id'] = $userId;
        Client::save($db, $data);
        $clients = Client::all($db);
        renderIndex($clients);
        break;
    case 'show':
        $client = Client::find($db, $id);
        $contacts = Contact::where($db, $client->id);
        $activities = Activity::where($db, $client->id);
        $orders = Order::where($db, $client->id);
        renderShow($client, $contacts, $activities, $orders);
        break;
    case 'edit':
        $client = Client::find($db, $id);
        renderEdit($client);
        break;
    case 'update':
        $data = $_POST;
        $data['id'] = $id;
        $data['user_id'] = $userId;
        Client::save($db, $data);
        $clients = Client::all($db);
        renderIndex($clients);
        break;
    case 'update_status':
        $client = Client::find($db, $id);
        $client->crm_status = $_POST['crm_status'];
        if ($client->crm_status === 'customer') {
            $client->converted_at = date('Y-m-d H:i:s');
        }
        Client::save($db, (array)$client);

        // Registra il cambio di stato in crm_activities
        $activityData = [
            'client_id' => $client->id,
            'user_id' => $userId,
            'type' => 'status_change',
            'subject' => 'Cambio stato',
            'body' => 'Stato cambiato da ' . $client->crm_status . ' a ' . $_POST['crm_status'],
            'occurred_at' => date('Y-m-d H:i:s'),
            'next_action_at' => null
        ];
        Activity::save($db, $activityData);

        $clients = Client::all($db);
        renderIndex($clients);
        break;
    case 'create_contact':
        $client = Client::find($db, $id);
        renderCreateContact($client);
        break;
    case 'store_contact':
        $data = $_POST;
        Contact::save($db, $data);
        $client = Client::find($db, $data['client_id']);
        $contacts = Contact::where($db, $client->id);
        $activities = Activity::where($db, $client->id);
        $orders = Order::where($db, $client->id);
        renderShow($client, $contacts, $activities, $orders);
        break;
    case 'create_activity':
        $client = Client::find($db, $id);
        renderCreateActivity($client);
        break;
    case 'store_activity':
        $data = $_POST;
        $data['user_id'] = $userId;
        Activity::save($db, $data);
        $client = Client::find($db, $data['client_id']);
        $contacts = Contact::where($db, $client->id);
        $activities = Activity::where($db, $client->id);
        $orders = Order::where($db, $client->id);
        renderShow($client, $contacts, $activities, $orders);
        break;
    case 'store_order':
        $data = $_POST;
        $data['user_id'] = $userId;
        Order::save($db, $data);
        $client = Client::find($db, $data['client_id']);
        $contacts = Contact::where($db, $client->id);
        $activities = Activity::where($db, $client->id);
        $orders = Order::where($db, $client->id);
        renderShow($client, $contacts, $activities, $orders);
        break;
    default:
        header('Location: /crm-clienti');
        break;
}
?>
