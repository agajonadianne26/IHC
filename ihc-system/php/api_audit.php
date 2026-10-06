<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db-config.php';

try {
    $pdo = ihc_pdo();

    $limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));
    $rows = [];
    foreach ($pdo->query(
        "SELECT a.id, a.action, a.contract_id, a.actor_id, a.actor_name, a.from_status, a.to_status,
                a.details, a.created_at, c.client_name
         FROM audit_logs a
         LEFT JOIN contracts c ON c.id = a.contract_id
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT " . (int)$limit
    ) as $r) {
        $rows[] = [
            'id' => (int)$r['id'],
            'action' => $r['action'],
            'contractId' => $r['contract_id'] !== null ? (int)$r['contract_id'] : null,
            'clientName' => $r['client_name'],
            'actorId' => $r['actor_id'],
            'actorName' => $r['actor_name'],
            'fromStatus' => $r['from_status'],
            'toStatus' => $r['to_status'],
            'details' => $r['details'] !== null ? json_decode((string)$r['details'], true) : null,
            'createdAt' => $r['created_at'],
        ];
    }
    echo json_encode(['success' => true, 'rows' => $rows]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
