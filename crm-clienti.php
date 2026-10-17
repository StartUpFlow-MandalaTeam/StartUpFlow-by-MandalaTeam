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
        $stmt = $db->prepare('SELECT * FROM crm_activities WHERE client_id = ?');
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function save($db, $data) {
        $stmt = $db->prepare('INSERT INTO crm_activities (client_id, user_id, type, subject, body, occurred_at, next_action_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['client_id'], $data['user_id'], $data['type'], $data['subject'], $data['body'], $data['occurred_at'] ?: date('Y-m-d H:i:s'), $data['next_action_at'] ?: null]);
    }
}

// Views
function renderIndex($clients) {
    echo '<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Dashboard</a>
        <h1>CRM clienti</h1>
        <p>Questo è il modulo per la gestione dei potenziali clienti.</p>
    </header>
    <div class="service-content">
        <a href="crm-clienti.php?action=create" class="btn btn-primary" data-spa="true">Aggiungi Cliente</a>
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
        echo '<tr>
            <td>' . htmlspecialchars($client->client_type === 'company' ? ($client->company_name ?? '') : (($client->first_name ?? '') . ' ' . ($client->last_name ?? ''))) . '</td>
            <td>' . htmlspecialchars($client->email ?? '') . '</td>
            <td>' . htmlspecialchars($client->crm_status ?? '') . '</td>
            <td>
                <a href="crm-clienti.php?action=show&id=' . $client->id . '" class="btn btn-info" data-spa="true">Visualizza</a>
                <a href="crm-clienti.php?action=edit&id=' . $client->id . '" class="btn btn-warning" data-spa="true">Modifica</a>
            </td>
        </tr>';
    }
    echo '</tbody>
        </table>
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

function renderShow($client, $contacts, $activities) {
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
            <td>' . htmlspecialchars(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) . '</td>
            <td>' . htmlspecialchars($contact->email ?? '') . '</td>
            <td>' . htmlspecialchars($contact->phone ?? '') . '</td>
            <td>' . htmlspecialchars($contact->role ?? '') . '</td>
            <td>' . (($contact->is_primary ?? 0) ? 'Sì' : 'No') . '</td>
        </tr>';
    }
    echo '</tbody>
        </table>

        <h2>Attività</h2>
        <a href="crm-clienti.php?action=create_activity&id=' . $client->id . '" class="btn btn-primary" data-spa="true">Aggiungi Attività</a>
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
        echo '<tr>
            <td>' . htmlspecialchars($activity->type ?? '') . '</td>
            <td>' . htmlspecialchars($activity->subject ?? '') . '</td>
            <td>' . htmlspecialchars($activity->occurred_at ?? '') . '</td>
            <td>' . htmlspecialchars($activity->next_action_at ?? '') . '</td>
        </tr>';
    }
    echo '</tbody>
        </table>

        <form action="crm-clienti.php?action=update_status&id=' . $client->id . '" method="POST">
            <div class="form-group">
                <label>Cambia Stato:</label>
                <select name="crm_status" class="form-control">
                    <option value="lead" ' . ($client->crm_status === 'lead' ? 'selected' : '') . '>Lead</option>
                    <option value="prospect" ' . ($client->crm_status === 'prospect' ? 'selected' : '') . '>Prospect</option>
                    <option value="customer" ' . ($client->crm_status === 'customer' ? 'selected' : '') . '>Customer</option>
                    <option value="lost" ' . ($client->crm_status === 'lost' ? 'selected' : '') . '>Lost</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Aggiorna Stato</button>
        </form>

        <a href="/crm-clienti" class="btn btn-secondary" data-spa="true">Annulla</a>
    </div>
</div>';
}

function renderCreateContact($client) {
    echo '<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Dashboard</a>
        <h1>Aggiungi Contatto</h1>
    </header>
    <div class="service-content">
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
    <div class="service-content">
        <form action="crm-clienti.php?action=store_activity" method="POST">
            <div class="form-group">
                <label>Tipo:</label>
                <select name="type" class="form-control">
                    <option value="call">Chiamata</option>
                    <option value="email">Email</option>
                    <option value="meeting">Riunione</option>
                    <option value="note">Nota</option>
                    <option value="status_change">Cambio Stato</option>
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
        renderShow($client, $contacts, $activities);
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
        renderShow($client, $contacts, $activities);
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
        renderShow($client, $contacts, $activities);
        break;
    default:
        header('Location: /crm-clienti');
        break;
}
?>
