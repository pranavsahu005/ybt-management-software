<?php
/**
 * YBT Management Software — Global Quick Search API
 */

require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['results' => []]);
    exit;
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$businessId = Auth::businessId();
$term = '%' . $query . '%';
$results = [];

// Customers
$customers = Database::fetchAll(
    "SELECT id, name, company_name, email FROM customers WHERE business_id = ? AND (name LIKE ? OR company_name LIKE ? OR email LIKE ?) LIMIT 3",
    [$businessId, $term, $term, $term]
);
foreach ($customers as $c) {
    $results[] = [
        'type' => 'Customer',
        'title' => $c['name'],
        'subtitle' => $c['company_name'] ?: $c['email'],
        'url' => url('customer-view.php?id=' . $c['id'])
    ];
}

// Invoices
$invoices = Database::fetchAll(
    "SELECT id, invoice_number, grand_total, status FROM invoices WHERE business_id = ? AND invoice_number LIKE ? LIMIT 3",
    [$businessId, $term]
);
foreach ($invoices as $i) {
    $results[] = [
        'type' => 'Invoice',
        'title' => $i['invoice_number'],
        'subtitle' => '₹' . number_format($i['grand_total'], 2) . ' (' . ucfirst($i['status']) . ')',
        'url' => url('invoice-view.php?id=' . $i['id'])
    ];
}

// Leads
$leads = Database::fetchAll(
    "SELECT id, title, company_name, deal_value FROM leads WHERE business_id = ? AND (title LIKE ? OR company_name LIKE ?) LIMIT 3",
    [$businessId, $term, $term]
);
foreach ($leads as $l) {
    $results[] = [
        'type' => 'Lead',
        'title' => $l['title'],
        'subtitle' => ($l['company_name'] ? $l['company_name'] . ' • ' : '') . '₹' . number_format($l['deal_value'], 2),
        'url' => url('leads.php')
    ];
}

echo json_encode(['results' => $results]);
