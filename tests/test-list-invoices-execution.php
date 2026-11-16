<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

$_SESSION['user_id'] = 1;

$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    include __DIR__ . '/../invoice/list-invoices.php';
    $output = ob_get_clean();
    $data = json_decode($output, true);
    echo "Success: " . (isset($data['success']) && $data['success'] ? 'YES' : 'NO') . "\n";
    if (isset($data['invoices'])) {
        echo "Invoices returned for user 1: " . count($data['invoices']) . "\n";
        foreach ($data['invoices'] as $inv) {
            if (str_starts_with($inv['document_number'], 'INT-')) {
                echo "Found INT invoice: {$inv['document_number']} (Type: {$inv['document_type']}, XML available: " . ($inv['xml_available'] ? 'YES' : 'NO') . ")\n";
            }
        }
    } else {
        echo "Response: " . $output . "\n";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}
