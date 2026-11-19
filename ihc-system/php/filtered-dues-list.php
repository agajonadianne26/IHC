<?php
// php/filtered-dues-list.php
require_once 'db.php'; // Siguruhing tama ang path ng db connection mo

$type = isset($_GET['type']) ? $_GET['type'] : 'has_payment';
$title = ($type === 'reserved') ? 'Clients with Last Reserved Date' : 'Clients with Last Payment Date';

// Query para makuha ang mga impormasyon ayon sa Excel structure
// Palitan ang table/column names batay sa aktwal na database schema ng ihc-system
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> — Imperial Homes</title>
    <link rel="stylesheet" href="../css/dashboard.css"> <!-- I-adjust ang CSS path kung kinakailangan -->
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .main-container { padding: 30px; margin-left: 260px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h2 { color: #0f172a; margin-bottom: 20px; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        th { background: #f8fafc; color: #64748b; font-weight: 600; }
        .back-btn { display: inline-block; margin-bottom: 20px; font-size: 13px; color: #2563eb; text-decoration: none; font-weight: 600; }
        .back-btn:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="main-container">
        <a href="../html/clerk-dashboard.html" class="back-btn">&larr; Back to Dashboard</a>
        
        <div class="card">
            <h2><?php echo $title; ?></h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Due Date</th>
                        <th>Client Name</th>
                        <th>Property</th>
                        <th>Installment</th>
                        <th>Amount Due</th>
                        <th>Status</th>
                        <th>Last Payment / Reserved Date</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dito ilalagay ang dynamic rows mula sa database -->
                    <tr>
                        <td>Nov 10, 2026</td>
                        <td><strong>ALEXIS ZACHARY FLORES</strong><br><small style="color: #64748b;">CON-884 • C0380</small></td>
                        <td>Block 17 Lot 14, Phase 2</td>
                        <td>Installment #1</td>
                        <td>₱10,000.00</td>
                        <td><span style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Overdue</span></td>
                        <td><strong><?php echo ($type === 'reserved') ? 'Nov 05, 2026 (Reserved)' : 'Nov 08, 2026 (Paid)'; ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>