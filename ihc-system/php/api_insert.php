<?php
// Set headers to allow JSON communication
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection configuration 
$host     = 'localhost';
$db_name  = 'ihc'; //[cite: 3]
$username = 'root'; //[cite: 3]
$password = ''; //[cite: 3]

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the JSON payload sent by the frontend
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode(["success" => false, "message" => "Invalid JSON payload."]);
        exit;
    }

    // Map the JSON structure from the frontend to your variables
    $client_name   = filter_var($data['client']['fullName'], FILTER_SANITIZE_SPECIAL_CHARS);
    $email         = filter_var($data['client']['email'], FILTER_VALIDATE_EMAIL);
    $cellphone_num = filter_var($data['client']['phone'], FILTER_SANITIZE_SPECIAL_CHARS);
    $total_price   = filter_var($data['contract']['totalPrice'], FILTER_VALIDATE_FLOAT);
    $downpayment   = filter_var($data['contract']['downpayment'], FILTER_VALIDATE_FLOAT);
    $terms_months  = filter_var($data['contract']['terms'], FILTER_VALIDATE_INT);
    $start_date    = filter_var($data['contract']['startDate'], FILTER_SANITIZE_SPECIAL_CHARS);

    // Validate required values
    if (empty($client_name) || !$email || empty($cellphone_num) || 
        $total_price === false || $downpayment === false || !$terms_months || empty($start_date)) {
        echo json_encode(["success" => false, "message" => "Please correctly fill in all fields with valid details."]);
        exit;
    }

    try {
        // Prepare SQL targeting your exact column names[cite: 3]
        $sql = "INSERT INTO contracts (client_name, email, cellphone_number, total_contract_price, downpayment, installment_terms, start_date) 
                VALUES (:client_name, :email, :cellphone_number, :total_price, :downpayment, :terms_months, :start_date)"; //[cite: 3]
                
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters[cite: 3]
        $stmt->bindParam(':client_name', $client_name); //[cite: 3]
        $stmt->bindParam(':email', $email); //[cite: 3]
        $stmt->bindParam(':cellphone_number', $cellphone_num); //[cite: 3]
        $stmt->bindParam(':total_price', $total_price); //[cite: 3]
        $stmt->bindParam(':downpayment', $downpayment); //[cite: 3]
        $stmt->bindParam(':terms_months', $terms_months); //[cite: 3]
        $stmt->bindParam(':start_date', $start_date); //[cite: 3]
        
        if ($stmt->execute()) { //[cite: 3]
            // Send a success response back to the JavaScript
            echo json_encode(["success" => true, "message" => "Contract created successfully!"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>