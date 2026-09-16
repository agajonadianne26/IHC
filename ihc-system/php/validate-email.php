<?php
// 1. Allow cross-origin requests & preflight checks (Fixes CORS network errors)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// 2. Handle the preflight OPTIONS request from JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Read the incoming JSON payload
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$email = isset($data['email']) ? trim($data['email']) : '';

// 4. Validate the email format (Bypassing DNS check for local XAMPP development)
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['valid' => true]);
} else {
    echo json_encode(['valid' => false, 'reason' => 'invalid format']);
}
exit;
?>