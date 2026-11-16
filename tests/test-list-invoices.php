<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo->query('SELECT id, user_id, document_number, document_type, total FROM invoices');
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total invoices in DB: " . count($invoices) . "\n";
foreach ($invoices as $inv) {
    echo "- ID: {$inv['id']}, UserID: {$inv['user_id']}, Doc: {$inv['document_number']}, Type: {$inv['document_type']}, Total: {$inv['total']}\n";
}
