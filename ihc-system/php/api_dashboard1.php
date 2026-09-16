<?php
header('Content-Type: application/json');
require_once '../db.php'; // Ensure this connects properly to the 'ihc' database

$officerId = isset($_GET['officerId']) ? (int)$_GET['officerId'] : 0;

if (!$officerId) {
    echo json_encode(['success' => false, 'message' => 'Invalid Officer ID']);
    exit;
}

// NOTE: If you haven't added 'officer_id' to your database yet, this query will fail.
// You must add it in phpMyAdmin, or change this to "SELECT * FROM contracts"
$stmt = $pdo->prepare("SELECT * FROM contracts WHERE officer_id = ?");
$stmt->execute([$officerId]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC); // FETCH_ASSOC keeps the array clean

// Map the database columns to the keys expected by the frontend
$formattedClients = [];
foreach ($clients as $c) {
    $formattedClients[] = [
        'accountCode' => 'CON-' . $c['id'],          // Using the primary key 'id'
        'name'        => $c['client_name'],          // Matches your DB
        'email'       => $c['email'],                // Matches your DB
        'phone'       => $c['cellphone_number'],     // FIXED: matches your DB
        'nextDueDate' => $c['start_date'],           // FIXED: mapped to your DB
        'nextAmount'  => $c['downpayment'],          // FIXED: mapped to your DB
        'nextStatus'  => 'Pending Payment'
    ];
}

echo json_encode(['success' => true, 'clients' => $formattedClients]);
?>