<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db-config.php';

try {
    $pdo = ihc_pdo();

    // Per-officer remittance totals: who posted what, how much, when.
    $sql = "SELECT
                COALESCE(NULLIF(p.posted_by, ''), '—') AS officer_id,
                COALESCE(o.full_name, NULLIF(p.posted_by, ''), 'Unassigned') AS officer_name,
                COUNT(*) AS receipt_count,
                COALESCE(SUM(p.amount), 0) AS total_collected,
                MAX(p.date_collected) AS last_collection_date
            FROM payments p
            LEFT JOIN officers o ON o.id = p.posted_by
            GROUP BY COALESCE(NULLIF(p.posted_by, ''), '—'), COALESCE(o.full_name, NULLIF(p.posted_by, ''), 'Unassigned')
            ORDER BY total_collected DESC";
    $rows = [];
    foreach ($pdo->query($sql) as $r) {
        $rows[] = [
            'officerId' => $r['officer_id'],
            'officerName' => $r['officer_name'],
            'receiptCount' => (int)$r['receipt_count'],
            'totalCollected' => round((float)$r['total_collected'], 2),
            'lastCollectionDate' => $r['last_collection_date'],
        ];
    }

    echo json_encode(['success' => true, 'rows' => $rows]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
