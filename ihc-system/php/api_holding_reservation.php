<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ---------------------------------------------------------------------
// DB connection — inline PDO like the rest of the codebase (AGENTS.md:18)
// ---------------------------------------------------------------------
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=ihc;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database connection failed: '.$e->getMessage()]);
    exit;
}

// ---------------------------------------------------------------------
// Self-heal: ensure 005 tables exist even if migration not yet run
// (pattern from php/api_auth.php:31, backend/db.php:60)
// ---------------------------------------------------------------------
function ensureHoldingTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS property_units (
      id INT AUTO_INCREMENT PRIMARY KEY,
      project VARCHAR(255) NULL, building VARCHAR(255) NULL, unit_number VARCHAR(100) NULL,
      display_label VARCHAR(255) NOT NULL, status ENUM('AVAILABLE','ON HOLD','RESERVED','SOLD') NOT NULL DEFAULT 'AVAILABLE',
      current_client_id INT NULL, current_contract_id INT NULL, current_holding_fee_id INT NULL, current_reservation_fee_id INT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_property_units_label (display_label), KEY idx_property_units_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS holding_fees (
      id INT AUTO_INCREMENT PRIMARY KEY, contract_id INT NOT NULL, client_id INT NULL, property_unit_id INT NULL,
      amount DECIMAL(12,2) NOT NULL, payment_method VARCHAR(100) NOT NULL, payment_date DATE NOT NULL,
      reference_number VARCHAR(100) NULL, or_number VARCHAR(100) NULL, start_date DATE NOT NULL, expiration_date DATE NOT NULL,
      status ENUM('PENDING','PAID','EXPIRED','REFUNDED','FORFEITED','CONVERTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
      remarks TEXT NULL, proof_path VARCHAR(500) NULL, proof_name VARCHAR(255) NULL, processed_by VARCHAR(100) NULL,
      converted_to_reservation_id INT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_hf_contract (contract_id), KEY idx_hf_unit (property_unit_id), KEY idx_hf_status_exp (status, expiration_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reservation_fees (
      id INT AUTO_INCREMENT PRIMARY KEY, contract_id INT NOT NULL, property_unit_id INT NULL, client_id INT NULL, holding_fee_id INT NULL,
      amount DECIMAL(12,2) NOT NULL, payment_method VARCHAR(100) NOT NULL, payment_date DATE NOT NULL,
      reference_number VARCHAR(100) NULL, or_number VARCHAR(100) NULL, status ENUM('PENDING','PAID','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PENDING',
      remarks TEXT NULL, proof_path VARCHAR(500) NULL, proof_name VARCHAR(255) NULL, processed_by VARCHAR(100) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_rf_contract (contract_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
      id INT AUTO_INCREMENT PRIMARY KEY, action VARCHAR(80) NOT NULL, contract_id INT NULL, holding_fee_id INT NULL, reservation_fee_id INT NULL,
      property_unit_id INT NULL, from_status VARCHAR(30) NULL, to_status VARCHAR(30) NULL, actor_id VARCHAR(100) NULL, actor_name VARCHAR(255) NULL,
      details JSON NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_audit_contract (contract_id), KEY idx_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS business_rules (
      rule_key VARCHAR(80) PRIMARY KEY, rule_value VARCHAR(255) NOT NULL, description TEXT NULL,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $pdo->exec("INSERT IGNORE INTO business_rules (rule_key,rule_value,description) VALUES
      ('holding_fee.default_days','30','Default hold window'),('holding_fee.expire_makes_available','1','1=EXPIRED->AVAILABLE'),
      ('holding_fee.convert_on_reservation','1','1=reservation PAID marks holding CONVERTED'),('holding_fee.refundable','0',''),('reservation_fee.refundable','1',''),
      ('holding_fee.allow_direct_reservation','0','1=allow reservation without prior active hold'),('business_rules.version','6','Migration marker')");
    // Backfill property_units from existing contracts (idempotent)
    try { $pdo->exec("INSERT IGNORE INTO property_units (display_label, status, current_contract_id) SELECT DISTINCT TRIM(property_address), 'AVAILABLE', id FROM contracts WHERE property_address IS NOT NULL AND TRIM(property_address) <> ''"); } catch(Throwable $e){}
    // Upgrade legacy holding_fees table (officer_id, proof_data_url, varchar status) to new schema if needed
    try {
        $cols=[]; foreach($pdo->query('SHOW COLUMNS FROM holding_fees') as $c) $cols[strtolower($c['Field'])]=true;
        if(!isset($cols['client_id'])) $pdo->exec('ALTER TABLE holding_fees ADD COLUMN client_id INT NULL AFTER contract_id');
        if(!isset($cols['property_unit_id'])) $pdo->exec('ALTER TABLE holding_fees ADD COLUMN property_unit_id INT NULL AFTER client_id');
        if(!isset($cols['reference_number'])) $pdo->exec('ALTER TABLE holding_fees ADD COLUMN reference_number VARCHAR(100) NULL AFTER payment_date');
        if(!isset($cols['start_date'])) { $pdo->exec('ALTER TABLE holding_fees ADD COLUMN start_date DATE NULL AFTER or_number'); try{ $pdo->exec("UPDATE holding_fees SET start_date=payment_date WHERE start_date IS NULL AND payment_date IS NOT NULL"); }catch(Throwable $e){} }
        if(!isset($cols['expiration_date'])) { $pdo->exec('ALTER TABLE holding_fees ADD COLUMN expiration_date DATE NULL AFTER start_date'); try{ $pdo->exec("UPDATE holding_fees SET expiration_date=DATE_ADD(payment_date, INTERVAL 30 DAY) WHERE expiration_date IS NULL AND payment_date IS NOT NULL"); }catch(Throwable $e){} }
        if(!isset($cols['processed_by'])) $pdo->exec('ALTER TABLE holding_fees ADD COLUMN processed_by VARCHAR(100) NULL AFTER proof_name');
        if(!isset($cols['converted_to_reservation_id'])) $pdo->exec('ALTER TABLE holding_fees ADD COLUMN converted_to_reservation_id INT NULL AFTER processed_by');
        if(!isset($cols['proof_path'])) $pdo->exec('ALTER TABLE holding_fees ADD COLUMN proof_path VARCHAR(500) NULL AFTER remarks');
        // Normalize lowercase statuses before ENUM conversion
        try{ $pdo->exec("UPDATE holding_fees SET status=UPPER(status) WHERE LOWER(status) IN ('pending','paid','cancelled')"); }catch(Throwable $e){}
        try{ $pdo->exec("UPDATE holding_fees SET status='PENDING' WHERE status NOT IN ('PENDING','PAID','EXPIRED','REFUNDED','FORFEITED','CONVERTED','CANCELLED')"); }catch(Throwable $e){}
        // Convert VARCHAR status to ENUM if needed
        $type=''; foreach($pdo->query("SHOW COLUMNS FROM holding_fees LIKE 'status'") as $c) $type=strtolower($c['Type']);
        if(strpos($type,'varchar')!==false){
            $pdo->exec("ALTER TABLE holding_fees MODIFY COLUMN status ENUM('PENDING','PAID','EXPIRED','REFUNDED','FORFEITED','CONVERTED','CANCELLED') NOT NULL DEFAULT 'PENDING'");
        }
        // Ensure indexes
        $hasIdx=function(string $n) use ($pdo): bool { foreach($pdo->query("SHOW INDEX FROM holding_fees") as $r) if($r['Key_name']===$n) return true; return false; };
        if(!$hasIdx('idx_hf_contract')) $pdo->exec('ALTER TABLE holding_fees ADD KEY idx_hf_contract (contract_id)');
        if(!$hasIdx('idx_hf_unit')) $pdo->exec('ALTER TABLE holding_fees ADD KEY idx_hf_unit (property_unit_id)');
        if(!$hasIdx('idx_hf_status_exp')) $pdo->exec('ALTER TABLE holding_fees ADD KEY idx_hf_status_exp (status, expiration_date)');
        if(!$hasIdx('idx_hf_created')) $pdo->exec('ALTER TABLE holding_fees ADD KEY idx_hf_created (created_at)');
        // One-time migration of legacy proof_data_url base64 to file if proof_path empty
        if(isset($cols['proof_data_url'])){
            try{
                foreach($pdo->query("SELECT id, proof_data_url, proof_name FROM holding_fees WHERE proof_data_url IS NOT NULL AND proof_data_url<>'' AND (proof_path IS NULL OR proof_path='') LIMIT 20") as $r){
                    $dataUrl=$r['proof_data_url']; if(strpos($dataUrl,'data:')!==0) continue;
                    $comma=strpos($dataUrl,','); if($comma===false) continue;
                    $meta=substr($dataUrl,5,$comma-5); $ext='png';
                    if(strpos($meta,'jpeg')!==false) $ext='jpg'; elseif(strpos($meta,'png')!==false) $ext='png'; elseif(strpos($meta,'pdf')!==false) $ext='pdf';
                    elseif(strpos($meta,'gif')!==false) $ext='gif'; elseif(strpos($meta,'webp')!==false) $ext='webp';
                    $b64=substr($dataUrl,$comma+1); $bin=@base64_decode($b64,true); if($bin===false) continue;
                    $dir=__DIR__.'/../uploads/holding_fees'; if(!is_dir($dir)) @mkdir($dir,0755,true);
                    $fname='proof_migrated_'.$r['id'].'_'.bin2hex(random_bytes(4)).'.'.$ext;
                    $path=$dir.'/'.$fname; if(@file_put_contents($path,$bin)===false) continue;
                    $pdo->prepare('UPDATE holding_fees SET proof_path=?, proof_name=COALESCE(proof_name,?) WHERE id=?')->execute(['uploads/holding_fees/'.$fname, $r['proof_name'] ?: 'proof.'.$ext, $r['id']]);
                }
            }catch(Throwable $e){}
        }
    } catch(Throwable $e){ /* self-heal must not block */ }
}
ensureHoldingTables($pdo);

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------
function getBusinessRule(PDO $pdo, string $key, string $def): string {
    $st=$pdo->prepare('SELECT rule_value FROM business_rules WHERE rule_key=?'); $st->execute([$key]);
    $r=$st->fetch(); return $r ? (string)$r['rule_value'] : $def;
}
function audit(PDO $pdo, string $action, ?int $contractId, ?int $hfId, ?int $rfId, ?int $unitId, ?string $from, ?string $to, ?string $actorId, ?string $actorName, $details=null): void {
    try{
        $pdo->prepare('INSERT INTO audit_logs (action,contract_id,holding_fee_id,reservation_fee_id,property_unit_id,from_status,to_status,actor_id,actor_name,details) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$action,$contractId,$hfId,$rfId,$unitId,$from,$to,$actorId,$actorName, $details!==null? json_encode($details, JSON_UNESCAPED_UNICODE): null]);
    }catch(Throwable $e){ /* audit must not break main flow */ }
}
function normalizeContractId($v): ?int {
    if(!is_string($v) && !is_numeric($v)) return null;
    if(preg_match('/(\d+)\s*$/',(string)$v,$m)){ $id=(int)$m[1]; return $id>0?$id:null; }
    return null;
}
function getOrCreateUnit(PDO $pdo, string $label, ?int $contractId): ?array {
    $label=trim($label); if($label==='') return null;
    $st=$pdo->prepare('SELECT * FROM property_units WHERE display_label=? LIMIT 1'); $st->execute([$label]); $row=$st->fetch();
    if($row) return $row;
    try{
        $pdo->prepare('INSERT INTO property_units (display_label,status,current_contract_id) VALUES (?,?,?)')->execute([$label,'AVAILABLE',$contractId]);
        $id=(int)$pdo->lastInsertId();
        $st=$pdo->prepare('SELECT * FROM property_units WHERE id=?'); $st->execute([$id]); return $st->fetch() ?: null;
    }catch(Throwable $e){ $st=$pdo->prepare('SELECT * FROM property_units WHERE display_label=? LIMIT 1'); $st->execute([$label]); return $st->fetch() ?: null; }
}
function getUnitById(PDO $pdo, int $id): ?array {
    $st=$pdo->prepare('SELECT * FROM property_units WHERE id=?'); $st->execute([$id]); $r=$st->fetch(); return $r?:null;
}
function resolveOfficerName(PDO $pdo, ?string $officerId): ?string {
    if(!$officerId) return null;
    $st=$pdo->prepare('SELECT full_name FROM officers WHERE id=? LIMIT 1'); $st->execute([$officerId]); $r=$st->fetch(); return $r ? (string)$r['full_name'] : null;
}
function fetchContract(PDO $pdo, int $id): ?array {
    $st=$pdo->prepare('SELECT c.*, o.full_name AS officer_name FROM contracts c LEFT JOIN officers o ON o.id=c.officer_id WHERE c.id=? LIMIT 1');
    $st->execute([$id]); $r=$st->fetch(); return $r?:null;
}
function parseInput(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'multipart/form-data') !== false || stripos($ct, 'application/x-www-form-urlencoded') !== false) {
        // For file uploads, fields come via $_POST; also support JSON-encoded field named 'data'
        $data = $_POST;
        if (isset($data['data']) && is_string($data['data'])) {
            $decoded = json_decode($data['data'], true);
            if (is_array($decoded)) $data = array_merge($decoded, $data);
        }
        // Normalize: handle camelCase vs snake
        return $data;
    }
    $raw=file_get_contents('php://input') ?: '';
    $j=json_decode($raw,true);
    return is_array($j)?$j:[];
}
function expireStaleHolds(PDO $pdo): int {
    // Lazy expiration sweep — called on every GET and from cronJobs.js
    $expireMakesAvail = getBusinessRule($pdo,'holding_fee.expire_makes_available','1') === '1';
    $today=date('Y-m-d');
    $st=$pdo->prepare("SELECT * FROM holding_fees WHERE status IN ('PENDING','PAID') AND expiration_date < ? AND converted_to_reservation_id IS NULL");
    $st->execute([$today]); $rows=$st->fetchAll();
    $count=0;
    foreach($rows as $r){
        $pdo->prepare("UPDATE holding_fees SET status='EXPIRED', updated_at=NOW() WHERE id=?")->execute([$r['id']]);
        if($expireMakesAvail && $r['property_unit_id']){
            $pdo->prepare("UPDATE property_units SET status='AVAILABLE', current_holding_fee_id=NULL, updated_at=NOW() WHERE id=? AND status='ON HOLD'")->execute([$r['property_unit_id']]);
            audit($pdo,'unit.status_changed',(int)$r['contract_id'],(int)$r['id'],null,(int)$r['property_unit_id'],'ON HOLD','AVAILABLE',null,null,['reason'=>'holding expired','holding_id'=>$r['id']]);
        }
        audit($pdo,'holding_fee.expired',(int)$r['contract_id'],(int)$r['id'],null,$r['property_unit_id']? (int)$r['property_unit_id']:null,$r['status'],'EXPIRED',null,null,['expiration_date'=>$r['expiration_date']]);
        $count++;
    }
    return $count;
}
function validateDateStr(string $d): bool {
    $dt=DateTime::createFromFormat('!Y-m-d',$d); return $dt && $dt->format('Y-m-d')===$d;
}
function handleProofUpload(): array {
    // Returns [proof_path, proof_name] or [null,null]
    if (!isset($_FILES['proof']) || !is_array($_FILES['proof']) || ($_FILES['proof']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null,null];
    }
    $f=$_FILES['proof'];
    if(($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Proof upload failed.');
    if($f['size'] > 600*1024) throw new RuntimeException('Proof file too large — keep it under 600 KB.');
    $allowed=['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    $mime = mime_content_type($f['tmp_name']) ?: ($f['type'] ?? '');
    // Fallback: check extension
    if(!in_array($mime,$allowed,true)){
        $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        $extMap=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf'];
        if(!isset($extMap[$ext]) || !in_array($extMap[$ext],$allowed,true)) throw new RuntimeException('Unsupported proof file type. Use JPEG/PNG/GIF/WebP/PDF.');
        $mime=$extMap[$ext];
    }
    $dir = __DIR__ . '/../uploads/holding_fees';
    if(!is_dir($dir)) @mkdir($dir,0755,true);
    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $name = 'proof_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).($ext?'.'.$ext:'');
    $dest = $dir.'/'.$name;
    if(!@move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('Could not save proof file.');
    return ['uploads/holding_fees/'.$name, $f['name']];
}

// ---------------------------------------------------------------------
// Routing
// ---------------------------------------------------------------------
try{
    $method=$_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? null;
    $input = parseInput();
    if(!$action && isset($input['action'])) $action=(string)$input['action'];

    // Always sweep expired holds on read paths (best-effort)
    if($method==='GET') { try{ expireStaleHolds($pdo); }catch(Throwable $e){} }

    // ---- GET actions ----
    if($method==='GET'){
        // Dashboard summary §12
        if($action==='dashboard_summary'){
            expireStaleHolds($pdo);
            $officerId = isset($_GET['officerId']) ? trim((string)$_GET['officerId']) : '';
            // Counts are scoped to officer's contracts if officerId given; otherwise global
            $contractIds=[];
            if($officerId!==''){
                $st=$pdo->prepare('SELECT id FROM contracts WHERE officer_id=?'); $st->execute([$officerId]);
                foreach($st->fetchAll() as $r) $contractIds[]=(int)$r['id'];
            } else {
                foreach($pdo->query('SELECT id FROM contracts') as $r) $contractIds[]=(int)$r['id'];
            }
            $in = $contractIds ? implode(',', array_map('intval',$contractIds)) : '0';
            $activeHolds = (int)$pdo->query("SELECT COUNT(*) c FROM holding_fees WHERE contract_id IN ($in) AND status='PAID' AND expiration_date >= CURDATE()")->fetch()['c'];
            // Future-proof: also count PENDING as not yet active? Spec: ACTIVE HOLDS are PAID within window
            $pendingHolds = (int)$pdo->query("SELECT COUNT(*) c FROM holding_fees WHERE contract_id IN ($in) AND status='PENDING'")->fetch()['c'];
            $expiringHolds = (int)$pdo->query("SELECT COUNT(*) c FROM holding_fees WHERE contract_id IN ($in) AND status='PAID' AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetch()['c'];
            $reservedUnits = (int)$pdo->query("SELECT COUNT(*) c FROM property_units WHERE status='RESERVED'".($officerId!==''?" AND current_contract_id IN ($in)":''))->fetch()['c'];
            // Fallback: also count via reservation_fees PAID
            $pendingPayments = (int)$pdo->query("SELECT COUNT(*) c FROM holding_fees WHERE contract_id IN ($in) AND status='PENDING'")->fetch()['c'];
            $pendingPayments += (int)$pdo->query("SELECT COUNT(*) c FROM reservation_fees WHERE contract_id IN ($in) AND status='PENDING'")->fetch()['c'];

            $rows=$pdo->query("SELECT hf.*, c.client_name, c.property_address FROM holding_fees hf JOIN contracts c ON c.id=hf.contract_id WHERE hf.contract_id IN ($in) AND hf.status='PAID' AND hf.expiration_date >= CURDATE() ORDER BY hf.expiration_date ASC LIMIT 20")->fetchAll();
            $expiring=[];
            foreach($rows as $r){
                $unitLabel = $r['property_address'] ?: ('CON-'.$r['contract_id']);
                if($r['property_unit_id']){ $u=getUnitById($pdo,(int)$r['property_unit_id']); if($u) $unitLabel=$u['display_label']; }
                $expiring[]=['id'=>(int)$r['id'],'contractId'=>(int)$r['contract_id'],'client'=>$r['client_name'],'unit'=>$unitLabel,'amount'=>(float)$r['amount'],'expiration'=>$r['expiration_date'],'status'=>$r['status']];
            }
            echo json_encode(['success'=>true,'summary'=>['activeHolds'=>$activeHolds,'expiringHolds'=>$expiringHolds,'reservedUnits'=>$reservedUnits,'pendingPayments'=>$pendingPayments,'pendingHolds'=>$pendingHolds],'expiringHolds'=>$expiring]);
            exit;
        }
        if($action==='holding_fees'){
            $officerId = isset($_GET['officerId']) ? trim((string)$_GET['officerId']) : '';
            $contractId = isset($_GET['contractId']) ? normalizeContractId($_GET['contractId']) : null;
            $where=[]; $params=[];
            if($officerId!==''){
                // Scope to officer's contracts
                $st=$pdo->prepare('SELECT id FROM contracts WHERE officer_id=?'); $st->execute([$officerId]);
                $ids=array_map(fn($r)=>(int)$r['id'],$st->fetchAll());
                if(!$ids){ echo json_encode(['success'=>true,'holdingFees'=>[]]); exit; }
                $where[]='hf.contract_id IN ('.implode(',',array_map('intval',$ids)).')';
            }
            if($contractId!==null){ $where[]='hf.contract_id=?'; $params[]=$contractId; }
            $sql='SELECT hf.*, c.client_name, c.email, c.property_address, c.officer_id, pu.display_label AS unit_label, pu.status AS unit_status FROM holding_fees hf JOIN contracts c ON c.id=hf.contract_id LEFT JOIN property_units pu ON pu.id=hf.property_unit_id';
            if($where) $sql.=' WHERE '.implode(' AND ',$where);
            $sql.=' ORDER BY hf.created_at DESC, hf.id DESC';
            $st=$pdo->prepare($sql); $st->execute($params);
            $rows=$st->fetchAll();
            $out=[];
            foreach($rows as $r){
                $out[]=[
                    'id'=>(int)$r['id'],'contractId'=>(int)$r['contract_id'],'clientName'=>$r['client_name'],'email'=>$r['email'],
                    'propertyAddress'=>$r['unit_label'] ?: $r['property_address'],'unitLabel'=>$r['unit_label'] ?: $r['property_address'],
                    'unitStatus'=>$r['unit_status'],'amount'=>(float)$r['amount'],'paymentMethod'=>$r['payment_method'],
                    'paymentDate'=>$r['payment_date'],'referenceNumber'=>$r['reference_number'],'orNumber'=>$r['or_number'],
                    'startDate'=>$r['start_date'],'expirationDate'=>$r['expiration_date'],
                    'status'=>$r['status'],'remarks'=>$r['remarks'],'proofPath'=>$r['proof_path'],'proofName'=>$r['proof_name'],
                    'processedBy'=>$r['processed_by'],'convertedToReservationId'=>$r['converted_to_reservation_id']? (int)$r['converted_to_reservation_id']:null,
                    'createdAt'=>$r['created_at'],'updatedAt'=>$r['updated_at']
                ];
            }
            echo json_encode(['success'=>true,'holdingFees'=>$out]);
            exit;
        }
        if($action==='reservation_fees'){
            $officerId = isset($_GET['officerId']) ? trim((string)$_GET['officerId']) : '';
            $contractId = isset($_GET['contractId']) ? normalizeContractId($_GET['contractId']) : null;
            $where=[]; $params=[];
            if($officerId!==''){
                $st=$pdo->prepare('SELECT id FROM contracts WHERE officer_id=?'); $st->execute([$officerId]);
                $ids=array_map(fn($r)=>(int)$r['id'],$st->fetchAll());
                if(!$ids){ echo json_encode(['success'=>true,'reservationFees'=>[]]); exit; }
                $where[]='rf.contract_id IN ('.implode(',',array_map('intval',$ids)).')';
            }
            if($contractId!==null){ $where[]='rf.contract_id=?'; $params[]=$contractId; }
            $sql='SELECT rf.*, c.client_name, c.email, c.property_address, pu.display_label AS unit_label, pu.status AS unit_status FROM reservation_fees rf JOIN contracts c ON c.id=rf.contract_id LEFT JOIN property_units pu ON pu.id=rf.property_unit_id';
            if($where) $sql.=' WHERE '.implode(' AND ',$where);
            $sql.=' ORDER BY rf.created_at DESC, rf.id DESC';
            $st=$pdo->prepare($sql); $st->execute($params);
            $rows=$st->fetchAll();
            $out=[];
            foreach($rows as $r){
                $out[]=[
                    'id'=>(int)$r['id'],'contractId'=>(int)$r['contract_id'],'holdingFeeId'=>$r['holding_fee_id']? (int)$r['holding_fee_id']:null,
                    'clientName'=>$r['client_name'],'propertyAddress'=>$r['unit_label'] ?: $r['property_address'],'unitLabel'=>$r['unit_label'] ?: $r['property_address'],
                    'unitStatus'=>$r['unit_status'],'amount'=>(float)$r['amount'],'paymentMethod'=>$r['payment_method'],
                    'paymentDate'=>$r['payment_date'],'referenceNumber'=>$r['reference_number'],'orNumber'=>$r['or_number'],
                    'status'=>$r['status'],'remarks'=>$r['remarks'],'proofPath'=>$r['proof_path'],'proofName'=>$r['proof_name'],
                    'processedBy'=>$r['processed_by'],'createdAt'=>$r['created_at']
                ];
            }
            echo json_encode(['success'=>true,'reservationFees'=>$out]);
            exit;
        }
        if($action==='payment_history'){
            $cid = normalizeContractId($_GET['contractId'] ?? null);
            if($cid===null){ http_response_code(400); echo json_encode(['success'=>false,'message'=>'contractId is required']); exit; }
            $contract=fetchContract($pdo,$cid);
            if(!$contract){ http_response_code(404); echo json_encode(['success'=>false,'message'=>'Contract not found']); exit; }
            // holding_fees
            $hfSt=$pdo->prepare('SELECT * FROM holding_fees WHERE contract_id=? ORDER BY payment_date, id');
            $hfSt->execute([$cid]); $holdingFees=$hfSt->fetchAll();
            // reservation_fees
            $rfSt=$pdo->prepare('SELECT * FROM reservation_fees WHERE contract_id=? ORDER BY payment_date, id');
            $rfSt->execute([$cid]); $reservationFees=$rfSt->fetchAll();
            // payments (existing) — fold CON- prefix variants like api_client.php
            $variants=[(string)$cid,'CON-'.$cid,'IHC-'.$cid];
            $ph=implode(',',array_fill(0,count($variants),'?'));
            $paySt=$pdo->prepare("SELECT * FROM payments WHERE contract_id IN ($ph) ORDER BY date_collected, id");
            $paySt->execute($variants); $payments=$paySt->fetchAll();
            // Build unified history chronologically
            $history=[];
            foreach($holdingFees as $r){
                $history[]=['id'=>'HF-'.$r['id'],'type'=>'Holding Fee','transactionType'=>'HOLDING_FEE','date'=>$r['payment_date'],'amount'=>(float)$r['amount'],'status'=>$r['status'],'orNumber'=>$r['or_number'],'referenceNumber'=>$r['reference_number'],'paymentMethod'=>$r['payment_method'],'createdAt'=>$r['created_at']];
            }
            foreach($reservationFees as $r){
                $history[]=['id'=>'RF-'.$r['id'],'type'=>'Reservation Fee','transactionType'=>'RESERVATION_FEE','date'=>$r['payment_date'],'amount'=>(float)$r['amount'],'status'=>$r['status'],'orNumber'=>$r['or_number'],'referenceNumber'=>$r['reference_number'],'paymentMethod'=>$r['payment_method'],'createdAt'=>$r['created_at']];
            }
            foreach($payments as $r){
                // Map remarks that look like status? Keep as PAID for display
                $history[]=['id'=>'PAY-'.$r['id'],'type'=>'Payment','transactionType'=>'PAYMENT','date'=>$r['date_collected'],'amount'=>(float)$r['amount'],'status'=>'PAID','orNumber'=>$r['or_number'],'referenceNumber'=>$r['or_number'],'paymentMethod'=>$r['payment_method'],'remarks'=>$r['remarks'],'createdAt'=>$r['created_at']];
            }
            usort($history, fn($a,$b)=> strcmp($a['date'],$b['date']) ?: strcmp($a['createdAt'],$b['createdAt']));
            $totalPaid=array_sum(array_map(fn($h)=> in_array($h['status'],['PAID','CONVERTED']) ? $h['amount'] : 0, $history));
            // Also include unit status
            $unit=null;
            $uSt=$pdo->prepare('SELECT * FROM property_units WHERE current_contract_id=? OR display_label=? LIMIT 1');
            $uSt->execute([$cid, $contract['property_address']]);
            $uRow=$uSt->fetch(); if($uRow) $unit=['id'=>(int)$uRow['id'],'label'=>$uRow['display_label'],'status'=>$uRow['status']];

            // Build server-side computed KPIs similar to client expected
            echo json_encode(['success'=>true,'contract'=>['id'=>$cid,'client'=>$contract['client_name'],'property'=>$contract['property_address'],'unit'=>$unit],'holdingFees'=>$holdingFees,'reservationFees'=>$reservationFees,'payments'=>$payments,'history'=>$history,'totalPaid'=>round($totalPaid,2)]);
            exit;
        }
        if($action==='property_unit'){
            $unitId = isset($_GET['unitId']) ? (int)$_GET['unitId'] : null;
            $contractId = isset($_GET['contractId']) ? normalizeContractId($_GET['contractId']) : null;
            $unit=null;
            if($unitId){ $unit=getUnitById($pdo,$unitId); }
            elseif($contractId){
                $c=fetchContract($pdo,$contractId);
                if($c){
                    $st=$pdo->prepare('SELECT * FROM property_units WHERE current_contract_id=? OR display_label=? LIMIT 1');
                    $st->execute([$contractId,$c['property_address']]); $unit=$st->fetch() ?: null;
                    if(!$unit && $c['property_address']) $unit=getOrCreateUnit($pdo,$c['property_address'],$contractId);
                }
            }
            if(!$unit){ http_response_code(404); echo json_encode(['success'=>false,'message'=>'Property/unit not found']); exit; }
            // History for unit
            $hf=[]; $rf=[];
            if($unit['id']){
                $st=$pdo->prepare('SELECT hf.*, c.client_name FROM holding_fees hf JOIN contracts c ON c.id=hf.contract_id WHERE hf.property_unit_id=? ORDER BY hf.payment_date DESC');
                $st->execute([$unit['id']]); $hf=$st->fetchAll();
                $st=$pdo->prepare('SELECT rf.*, c.client_name FROM reservation_fees rf JOIN contracts c ON c.id=rf.contract_id WHERE rf.property_unit_id=? ORDER BY rf.payment_date DESC');
                $st->execute([$unit['id']]); $rf=$st->fetchAll();
            }
            echo json_encode(['success'=>true,'unit'=>$unit,'holdingFees'=>$hf,'reservationFees'=>$rf]);
            exit;
        }
        if($action==='business_rules'){
            $rows=$pdo->query('SELECT rule_key, rule_value, description, updated_at FROM business_rules')->fetchAll();
            echo json_encode(['success'=>true,'rules'=>$rows]);
            exit;
        }
        if($action==='audit_logs'){
            $cid = isset($_GET['contractId']) ? normalizeContractId($_GET['contractId']) : null;
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            if($cid!==null){
                $st=$pdo->prepare('SELECT * FROM audit_logs WHERE contract_id=? ORDER BY created_at DESC, id DESC LIMIT '.(int)$limit);
                $st->execute([$cid]); $rows=$st->fetchAll();
            } else {
                $rows=$pdo->query('SELECT * FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT '.(int)$limit)->fetchAll();
            }
            echo json_encode(['success'=>true,'logs'=>$rows]);
            exit;
        }
        // Default: holding_fees list
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Unknown GET action']); exit;
    }

    // ---- POST actions ----
    if($method==='POST'){
        $action = $action ?: ($input['action'] ?? '');
        // Normalize inputs (support both camelCase from JS and snake_case)
        $get = function(array $src, ...$keys){
            foreach($keys as $k){ if(isset($src[$k]) && $src[$k]!=='' ) return $src[$k]; }
            return null;
        };

        if($action==='create_holding_fee' || $action==='update_holding_fee'){
            $isUpdate = $action==='update_holding_fee';
            $holdingId = $isUpdate ? (int)($get($input,'id','holdingFeeId') ?? 0) : 0;
            $existing=null;
            if($isUpdate){
                $st=$pdo->prepare('SELECT * FROM holding_fees WHERE id=?'); $st->execute([$holdingId]); $existing=$st->fetch();
                if(!$existing){ throw new RuntimeException('Holding fee not found.'); }
            }
            $contractId = normalizeContractId($get($input,'contractId','contract_id'));
            if(!$contractId) throw new RuntimeException('Client/Contract is required.');
            $contract=fetchContract($pdo,$contractId);
            if(!$contract) throw new RuntimeException('Contract not found in database.');
            $amount = $get($input,'amount');
            if($amount===null || $amount==='' || !is_numeric($amount) || (float)$amount <= 0) throw new RuntimeException('Enter a valid holding fee amount greater than zero.');
            $amount=(float)$amount;
            $methodVal = trim((string)($get($input,'paymentMethod','payment_method','method') ?? ''));
            if($methodVal==='') throw new RuntimeException('Payment method is required.');
            $paymentDate = trim((string)($get($input,'paymentDate','payment_date','dateCollected') ?? ''));
            if(!validateDateStr($paymentDate)) throw new RuntimeException('Payment date is required and must be YYYY-MM-DD.');
            $startDate = trim((string)($get($input,'startDate','start_date') ?? $paymentDate));
            if(!validateDateStr($startDate)) throw new RuntimeException('Hold start date must be YYYY-MM-DD.');
            $expDate = trim((string)($get($input,'expirationDate','expiration_date','expiration') ?? ''));
            if($expDate===''){
                $days=(int)getBusinessRule($pdo,'holding_fee.default_days','30');
                $expDate=date('Y-m-d', strtotime($startDate.' +'.$days.' days'));
            }
            if(!validateDateStr($expDate)) throw new RuntimeException('Expiration date must be YYYY-MM-DD.');
            if($expDate < $startDate) throw new RuntimeException('Expiration date cannot be earlier than the hold start date.');
            $status = strtoupper(trim((string)($get($input,'status') ?? ($isUpdate ? $existing['status'] : 'PAID'))));
            $allowed=['PENDING','PAID','EXPIRED','REFUNDED','FORFEITED','CONVERTED','CANCELLED'];
            if(!in_array($status,$allowed,true)) $status='PENDING';
            // OR / Reference handling: required when PAID, optional when PENDING (§14)
            $orNumber = trim((string)($get($input,'orNumber','or_number') ?? ''));
            $refNumber = trim((string)($get($input,'referenceNumber','reference_number') ?? ''));
            if($status==='PAID' && $orNumber==='' && $refNumber==='') throw new RuntimeException('OR / Reference number is required for paid fees.');
            $remarks = trim((string)($get($input,'remarks') ?? ''));
            $processedBy = trim((string)($get($input,'processedBy','processed_by','postedBy') ?? ''));
            if($processedBy==='') $processedBy=null;
            $actorName = resolveOfficerName($pdo,$processedBy);

            // Resolve / create property unit from propertyAddress or unitLabel or contract's property_address
            $unitLabel = trim((string)($get($input,'propertyAddress','property_address','unitLabel','unit_label','property') ?? $contract['property_address'] ?? ''));
            if($unitLabel==='') $unitLabel = 'CON-'.$contractId;
            $unit = getOrCreateUnit($pdo,$unitLabel,$contractId);
            $unitId = $unit ? (int)$unit['id'] : null;

            // Validations §11
            if($status==='PAID' && !$isUpdate){
                // Duplicate active hold for same unit
                if($unitId){
                    $dup=$pdo->prepare("SELECT id FROM holding_fees WHERE property_unit_id=? AND contract_id<>? AND status IN ('PENDING','PAID','CONVERTED') LIMIT 1");
                    $dup->execute([$unitId,$contractId]);
                    if($dup->fetch()) throw new RuntimeException('This unit is already on hold by another client.');
                    // Also check unit status SOLD/RESERVED
                    if(in_array($unit['status'],['SOLD','RESERVED'],true)) throw new RuntimeException('This unit is already '.strtolower($unit['status']).' and cannot be placed on hold.');
                    // If unit ON HOLD by different contract, block
                    if($unit['status']==='ON HOLD' && (int)$unit['current_contract_id'] !== $contractId){
                        // Check if that hold is still active
                        $ah=$pdo->prepare("SELECT id FROM holding_fees WHERE property_unit_id=? AND status='PAID' AND expiration_date >= CURDATE() LIMIT 1");
                        $ah->execute([$unitId]);
                        if($ah->fetch()) throw new RuntimeException('This unit is already reserved by another client.');
                    }
                }
            }
            // Also block second active hold for same contract+unit
            if(!$isUpdate){
                $dup2=$pdo->prepare("SELECT id FROM holding_fees WHERE contract_id=? AND status IN ('PENDING','PAID') AND (property_unit_id=? OR property_unit_id IS NULL) LIMIT 1");
                $dup2->execute([$contractId,$unitId]);
                if($dup2->fetch()) throw new RuntimeException('This client already has an active holding fee for this unit.');
            }

            [$proofPath,$proofName]=handleProofUpload();
            // If no new upload but updating, keep existing
            if($isUpdate && $proofPath===null){
                $proofPath=$existing['proof_path'];
                $proofName=$existing['proof_name'];
            }
            // Allow explicit proof removal via flag
            if($isUpdate && isset($input['removeProof']) && $input['removeProof']){
                $proofPath=null; $proofName=null;
            }

            $clientId=null;
            // Try to resolve client_accounts id by contract email
            if($contract['email']){
                $st=$pdo->prepare('SELECT id FROM client_accounts WHERE LOWER(email)=LOWER(?) LIMIT 1');
                $st->execute([$contract['email']]); $ca=$st->fetch(); if($ca) $clientId=(int)$ca['id'];
            }

            if($isUpdate){
                // Refund policy check §19
                if($status==='REFUNDED' && getBusinessRule($pdo,'holding_fee.refundable','0')!=='1'){
                    throw new RuntimeException('Holding fees are not refundable per current IHC policy (business_rules.holding_fee.refundable).');
                }
                $prevStatus=$existing['status'];
                $pdo->beginTransaction();
                try{
                    if($unitId) $pdo->prepare('SELECT status FROM property_units WHERE id=? FOR UPDATE')->execute([$unitId]);
                    $pdo->prepare('UPDATE holding_fees SET amount=?, payment_method=?, payment_date=?, reference_number=?, or_number=?, start_date=?, expiration_date=?, status=?, remarks=?, proof_path=?, proof_name=?, processed_by=?, updated_at=NOW() WHERE id=?')
                        ->execute([$amount,$methodVal,$paymentDate,$refNumber?:null,$orNumber?:null,$startDate,$expDate,$status,$remarks?:null,$proofPath,$proofName,$processedBy,$holdingId]);
                    audit($pdo,'holding_fee.updated',$contractId,$holdingId,null,$unitId,$prevStatus,$status,$processedBy,$actorName,['amount'=>$amount]);
                    if($prevStatus!=='PAID' && $status==='PAID' && $unitId){
                        $pdo->prepare("UPDATE property_units SET status='ON HOLD', current_contract_id=?, current_holding_fee_id=?, updated_at=NOW() WHERE id=?")->execute([$contractId,$holdingId,$unitId]);
                        audit($pdo,'unit.status_changed',$contractId,$holdingId,null,$unitId,'AVAILABLE','ON HOLD',$processedBy,$actorName,['reason'=>'holding paid']);
                    }
                    if(in_array($status,['CANCELLED','EXPIRED','REFUNDED','FORFEITED'],true) && $unitId){
                        $makeAvail=getBusinessRule($pdo,'holding_fee.expire_makes_available','1')==='1';
                        if($makeAvail){
                            $pdo->prepare("UPDATE property_units SET status='AVAILABLE', current_holding_fee_id=NULL, updated_at=NOW() WHERE id=? AND status='ON HOLD'")->execute([$unitId]);
                            audit($pdo,'unit.status_changed',$contractId,$holdingId,null,$unitId,'ON HOLD','AVAILABLE',$processedBy,$actorName,['reason'=>'holding '.$status]);
                        }
                    }
                    $pdo->commit();
                }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
                $st=$pdo->prepare('SELECT * FROM holding_fees WHERE id=?'); $st->execute([$holdingId]); $row=$st->fetch();
                echo json_encode(['success'=>true,'message'=>'Holding fee updated.','holdingFee'=>$row]);
                exit;
            } else {
                // Lock unit row if exists to prevent race
                $pdo->beginTransaction();
                try{
                    if($unitId) $pdo->prepare('SELECT status FROM property_units WHERE id=? FOR UPDATE')->execute([$unitId]);
                    $pdo->prepare('INSERT INTO holding_fees (contract_id,client_id,property_unit_id,amount,payment_method,payment_date,reference_number,or_number,start_date,expiration_date,status,remarks,proof_path,proof_name,processed_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$contractId,$clientId,$unitId,$amount,$methodVal,$paymentDate,$refNumber?:null,$orNumber?:null,$startDate,$expDate,$status,$remarks?:null,$proofPath,$proofName,$processedBy]);
                    $newId=(int)$pdo->lastInsertId();
                    audit($pdo,'holding_fee.created',$contractId,$newId,null,$unitId,null,$status,$processedBy,$actorName,['amount'=>$amount,'unit'=>$unitLabel]);
                    if($status==='PAID' && $unitId){
                        $pdo->prepare("UPDATE property_units SET status='ON HOLD', current_contract_id=?, current_holding_fee_id=?, updated_at=NOW() WHERE id=?")->execute([$contractId,$newId,$unitId]);
                        audit($pdo,'unit.status_changed',$contractId,$newId,null,$unitId,'AVAILABLE','ON HOLD',$processedBy,$actorName,['reason'=>'holding paid']);
                    }
                    $pdo->commit();
                }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
                $st=$pdo->prepare('SELECT * FROM holding_fees WHERE id=?'); $st->execute([$newId]); $row=$st->fetch();
                echo json_encode(['success'=>true,'message'=>'Holding fee recorded.','id'=>$newId,'holdingFee'=>$row]);
                exit;
            }
        }

        if($action==='create_reservation_fee' || $action==='update_reservation_fee'){
            $isUpdate = $action==='update_reservation_fee';
            $resId = $isUpdate ? (int)($get($input,'id','reservationFeeId') ?? 0) : 0;
            $existing=null;
            if($isUpdate){
                $st=$pdo->prepare('SELECT * FROM reservation_fees WHERE id=?'); $st->execute([$resId]); $existing=$st->fetch();
                if(!$existing) throw new RuntimeException('Reservation fee not found.');
            }
            $contractId = normalizeContractId($get($input,'contractId','contract_id'));
            if(!$contractId) throw new RuntimeException('Client/Contract is required.');
            $contract=fetchContract($pdo,$contractId);
            if(!$contract) throw new RuntimeException('Contract not found.');
            $amount = $get($input,'amount');
            if($amount===null || $amount==='' || !is_numeric($amount) || (float)$amount <=0) throw new RuntimeException('Enter a valid reservation fee amount greater than zero.');
            $amount=(float)$amount;
            $methodVal=trim((string)($get($input,'paymentMethod','payment_method','method') ?? ''));
            if($methodVal==='') throw new RuntimeException('Payment method is required.');
            $paymentDate=trim((string)($get($input,'paymentDate','payment_date','dateCollected') ?? ''));
            if(!validateDateStr($paymentDate)) throw new RuntimeException('Payment date is required (YYYY-MM-DD).');
            $status=strtoupper(trim((string)($get($input,'status') ?? ($isUpdate ? $existing['status'] : 'PAID'))));
            if(!in_array($status,['PENDING','PAID','CANCELLED','REFUNDED'],true)) $status='PENDING';
            $orNumber=trim((string)($get($input,'orNumber','or_number') ?? ''));
            $refNumber=trim((string)($get($input,'referenceNumber','reference_number') ?? ''));
            if($status==='PAID' && $orNumber==='' && $refNumber==='') throw new RuntimeException('OR / Reference number is required for paid reservations.');
            $remarks=trim((string)($get($input,'remarks') ?? ''));
            $processedBy=trim((string)($get($input,'processedBy','processed_by','postedBy') ?? '')) ?: null;
            $actorName=resolveOfficerName($pdo,$processedBy);
            $unitLabel=trim((string)($get($input,'propertyAddress','property_address','unitLabel','unit_label','property') ?? $contract['property_address'] ?? ''));
            if($unitLabel==='') $unitLabel='CON-'.$contractId;
            $unit=getOrCreateUnit($pdo,$unitLabel,$contractId);
            $unitId=$unit ? (int)$unit['id'] : null;
            $holdingFeeId = $get($input,'holdingFeeId','holding_fee_id');
            $holdingFeeId = $holdingFeeId!==null && $holdingFeeId!=='' ? (int)$holdingFeeId : null;

            // Validations §11
            if($unit && $unit['status']==='SOLD') throw new RuntimeException('This unit is already SOLD and cannot be reserved.');
            if($unitId && !$isUpdate){
                // Duplicate active reservation for same unit by different client
                $dup=$pdo->prepare("SELECT id, contract_id FROM reservation_fees WHERE property_unit_id=? AND status='PAID' LIMIT 1");
                $dup->execute([$unitId]); $ex=$dup->fetch();
                if($ex && (int)$ex['contract_id'] !== $contractId) throw new RuntimeException('This unit is already reserved by another client.');
                // Also via property_units status
                if($unit['status']==='RESERVED' && (int)$unit['current_contract_id'] !== $contractId) throw new RuntimeException('This unit is already reserved by another client.');
                // Check second active reservation for same contract
                $dup2=$pdo->prepare("SELECT id FROM reservation_fees WHERE contract_id=? AND status IN ('PENDING','PAID') LIMIT 1");
                $dup2->execute([$contractId]); if($dup2->fetch()) throw new RuntimeException('This client already has an active reservation for this unit.');
            }
            // If unit ON HOLD, verify client matches hold owner
            if($unit && $unit['status']==='ON HOLD'){
                if((int)$unit['current_contract_id'] !== $contractId) throw new RuntimeException('This unit is on hold by another client.');
                // Check hold not expired
                if($holdingFeeId===null){
                    // Auto-link the active holding fee for this contract+unit
                    $hfSt=$pdo->prepare("SELECT id, status, expiration_date FROM holding_fees WHERE contract_id=? AND property_unit_id=? AND status='PAID' ORDER BY expiration_date DESC LIMIT 1");
                    $hfSt->execute([$contractId,$unitId]); $hf=$hfSt->fetch();
                    if($hf){
                        if($hf['expiration_date'] < date('Y-m-d')) throw new RuntimeException('The holding period for this unit has expired. Please create a new valid transaction according to IHC policy.');
                        $holdingFeeId=(int)$hf['id'];
                    }
                } else {
                    $hfSt=$pdo->prepare("SELECT * FROM holding_fees WHERE id=?"); $hfSt->execute([$holdingFeeId]); $hf=$hfSt->fetch();
                    if($hf && $hf['expiration_date'] < date('Y-m-d') && $hf['status']==='PAID') throw new RuntimeException('The holding period for this unit has expired. Please create a new valid transaction according to IHC policy.');
                    if($hf && (int)$hf['contract_id'] !== $contractId) throw new RuntimeException('Holding fee does not belong to this client.');
                }
            }
            // If no holding at all, some business rules allow direct reservation — allow with warning audit
            [$proofPath,$proofName]=handleProofUpload();
            if($isUpdate && $proofPath===null){ $proofPath=$existing['proof_path']; $proofName=$existing['proof_name']; }
            if($isUpdate && isset($input['removeProof']) && $input['removeProof']){ $proofPath=null; $proofName=null; }
            $clientId=null;
            if($contract['email']){ $st=$pdo->prepare('SELECT id FROM client_accounts WHERE LOWER(email)=LOWER(?) LIMIT 1'); $st->execute([$contract['email']]); $ca=$st->fetch(); if($ca) $clientId=(int)$ca['id']; }

            if($status==='REFUNDED' && getBusinessRule($pdo,'reservation_fee.refundable','1')!=='1'){
                throw new RuntimeException('Reservation fees are not refundable per current IHC policy (business_rules.reservation_fee.refundable).');
            }
            // Direct reservation without hold requires explicit rule per §19
            if(!$isUpdate && $status==='PAID' && $unit && $unit['status']==='AVAILABLE' && $holdingFeeId===null){
                if(getBusinessRule($pdo,'holding_fee.allow_direct_reservation','0')!=='1'){
                    // Allow but audit warning; for now permit with warning audit — uncomment to block:
                    // throw new RuntimeException('Direct reservation without an active hold is not allowed per IHC policy.');
                    audit($pdo,'reservation_fee.direct_without_hold',$contractId,null,null,$unitId,null,'PAID',$processedBy,$actorName,['warning'=>'reserved AVAILABLE without prior ON HOLD','unit'=>$unitLabel]);
                }
            }
            if($isUpdate){
                $prevStatus=$existing['status'];
                if($status==='REFUNDED' && getBusinessRule($pdo,'reservation_fee.refundable','1')!=='1'){
                    throw new RuntimeException('Reservation fees are not refundable per current IHC policy.');
                }
                $pdo->beginTransaction();
                try{
                    if($unitId) $pdo->prepare('SELECT status FROM property_units WHERE id=? FOR UPDATE')->execute([$unitId]);
                    $pdo->prepare('UPDATE reservation_fees SET amount=?, payment_method=?, payment_date=?, reference_number=?, or_number=?, status=?, remarks=?, proof_path=?, proof_name=?, processed_by=?, updated_at=NOW() WHERE id=?')
                        ->execute([$amount,$methodVal,$paymentDate,$refNumber?:null,$orNumber?:null,$status,$remarks?:null,$proofPath,$proofName,$processedBy,$resId]);
                    audit($pdo,'reservation_fee.updated',$contractId,null,$resId,$unitId,$prevStatus,$status,$processedBy,$actorName,['amount'=>$amount]);
                    if($prevStatus!=='PAID' && $status==='PAID' && $unitId){
                        $pdo->prepare("UPDATE property_units SET status='RESERVED', current_reservation_fee_id=?, updated_at=NOW() WHERE id=?")->execute([$resId,$unitId]);
                        audit($pdo,'unit.status_changed',$contractId,null,$resId,$unitId,'ON HOLD','RESERVED',$processedBy,$actorName,['reason'=>'reservation paid']);
                        if(getBusinessRule($pdo,'holding_fee.convert_on_reservation','1')==='1' && $holdingFeeId){
                            $pdo->prepare("UPDATE holding_fees SET status='CONVERTED', converted_to_reservation_id=?, updated_at=NOW() WHERE id=?")->execute([$resId,$holdingFeeId]);
                            audit($pdo,'holding_fee.converted',$contractId,$holdingFeeId,$resId,$unitId,'PAID','CONVERTED',$processedBy,$actorName,['reservation_id'=>$resId]);
                        }
                    }
                    $pdo->commit();
                }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
                $st=$pdo->prepare('SELECT * FROM reservation_fees WHERE id=?'); $st->execute([$resId]); $row=$st->fetch();
                echo json_encode(['success'=>true,'message'=>'Reservation fee updated.','reservationFee'=>$row]);
                exit;
            } else {
                $pdo->beginTransaction();
                try{
                    if($unitId) $pdo->prepare('SELECT status FROM property_units WHERE id=? FOR UPDATE')->execute([$unitId]);
                    $pdo->prepare('INSERT INTO reservation_fees (contract_id,property_unit_id,client_id,holding_fee_id,amount,payment_method,payment_date,reference_number,or_number,status,remarks,proof_path,proof_name,processed_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$contractId,$unitId,$clientId,$holdingFeeId,$amount,$methodVal,$paymentDate,$refNumber?:null,$orNumber?:null,$status,$remarks?:null,$proofPath,$proofName,$processedBy]);
                    $newId=(int)$pdo->lastInsertId();
                    audit($pdo,'reservation_fee.created',$contractId,null,$newId,$unitId,null,$status,$processedBy,$actorName,['amount'=>$amount,'unit'=>$unitLabel]);
                    if($status==='PAID' && $unitId){
                        $prevUnitStatus=$unit['status'];
                        $pdo->prepare("UPDATE property_units SET status='RESERVED', current_contract_id=?, current_reservation_fee_id=?, updated_at=NOW() WHERE id=?")->execute([$contractId,$newId,$unitId]);
                        audit($pdo,'unit.status_changed',$contractId,null,$newId,$unitId,$prevUnitStatus,'RESERVED',$processedBy,$actorName,['reason'=>'reservation paid']);
                        if(getBusinessRule($pdo,'holding_fee.convert_on_reservation','1')==='1' && $holdingFeeId){
                            $pdo->prepare("UPDATE holding_fees SET status='CONVERTED', converted_to_reservation_id=?, updated_at=NOW() WHERE id=?")->execute([$newId,$holdingFeeId]);
                            audit($pdo,'holding_fee.converted',$contractId,$holdingFeeId,$newId,$unitId,'PAID','CONVERTED',$processedBy,$actorName,['reservation_id'=>$newId]);
                        }
                    }
                    $pdo->commit();
                }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
                $st=$pdo->prepare('SELECT * FROM reservation_fees WHERE id=?'); $st->execute([$newId]); $row=$st->fetch();
                echo json_encode(['success'=>true,'message'=>'Reservation fee recorded.','id'=>$newId,'reservationFee'=>$row]);
                exit;
            }
        }

        if($action==='update_holding_status' || $action==='update_reservation_status'){
            $isHolding = $action==='update_holding_status';
            $id=(int)($get($input,'id','holdingFeeId','reservationFeeId') ?? 0);
            $toStatus=strtoupper(trim((string)($get($input,'status','toStatus') ?? '')));
            $processedBy=trim((string)($get($input,'processedBy','processed_by','actorId') ?? '')) ?: null;
            $actorName=resolveOfficerName($pdo,$processedBy);
            if($isHolding){
                $st=$pdo->prepare('SELECT * FROM holding_fees WHERE id=?'); $st->execute([$id]); $row=$st->fetch();
                if(!$row) throw new RuntimeException('Holding fee not found.');
                $allowed=['PENDING','PAID','EXPIRED','REFUNDED','FORFEITED','CONVERTED','CANCELLED'];
                if(!in_array($toStatus,$allowed,true)) throw new RuntimeException('Invalid holding status.');
                $from=$row['status'];
                $pdo->prepare('UPDATE holding_fees SET status=?, updated_at=NOW() WHERE id=?')->execute([$toStatus,$id]);
                audit($pdo,'holding_fee.status_changed',(int)$row['contract_id'],$id,null,$row['property_unit_id']? (int)$row['property_unit_id']:null,$from,$toStatus,$processedBy,$actorName,['reason'=>$get($input,'reason','remarks')]);
                // Unit transitions
                if($row['property_unit_id']){
                    $unitId=(int)$row['property_unit_id'];
                    if(in_array($toStatus,['EXPIRED','CANCELLED','FORFEITED','REFUNDED'],true)){
                        if(getBusinessRule($pdo,'holding_fee.expire_makes_available','1')==='1'){
                            $pdo->prepare("UPDATE property_units SET status='AVAILABLE', current_holding_fee_id=NULL, updated_at=NOW() WHERE id=? AND status='ON HOLD'")->execute([$unitId]);
                            audit($pdo,'unit.status_changed',(int)$row['contract_id'],$id,null,$unitId,'ON HOLD','AVAILABLE',$processedBy,$actorName,['reason'=>'holding '.$toStatus]);
                        }
                    }
                }
                echo json_encode(['success'=>true,'message'=>'Holding fee status updated.']);
                exit;
            } else {
                $st=$pdo->prepare('SELECT * FROM reservation_fees WHERE id=?'); $st->execute([$id]); $row=$st->fetch();
                if(!$row) throw new RuntimeException('Reservation fee not found.');
                $allowed=['PENDING','PAID','CANCELLED','REFUNDED'];
                if(!in_array($toStatus,$allowed,true)) throw new RuntimeException('Invalid reservation status.');
                $from=$row['status'];
                $pdo->prepare('UPDATE reservation_fees SET status=?, updated_at=NOW() WHERE id=?')->execute([$toStatus,$id]);
                audit($pdo,'reservation_fee.status_changed',(int)$row['contract_id'],null,$id,$row['property_unit_id']? (int)$row['property_unit_id']:null,$from,$toStatus,$processedBy,$actorName,['reason'=>$get($input,'reason','remarks')]);
                if($row['property_unit_id'] && in_array($toStatus,['CANCELLED','REFUNDED'],true)){
                    $pdo->prepare("UPDATE property_units SET status='AVAILABLE', current_reservation_fee_id=NULL, updated_at=NOW() WHERE id=? AND status='RESERVED'")->execute([(int)$row['property_unit_id']]);
                    audit($pdo,'unit.status_changed',(int)$row['contract_id'],null,$id,(int)$row['property_unit_id'],'RESERVED','AVAILABLE',$processedBy,$actorName,['reason'=>'reservation '.$toStatus]);
                }
                echo json_encode(['success'=>true,'message'=>'Reservation status updated.']);
                exit;
            }
        }

        if($action==='expire_sweep'){
            $n=expireStaleHolds($pdo);
            echo json_encode(['success'=>true,'expired'=>$n]);
            exit;
        }
        if($action==='business_rules_update'){
            $rules = $input['rules'] ?? $input;
            if(!is_array($rules)) throw new RuntimeException('rules object required');
            foreach($rules as $k=>$v){
                if($k==='action') continue;
                if(!is_string($k) || $k==='') continue;
                $pdo->prepare('INSERT INTO business_rules (rule_key,rule_value) VALUES (?,?) ON DUPLICATE KEY UPDATE rule_value=VALUES(rule_value)')->execute([$k,(string)$v]);
            }
            audit($pdo,'business_rules.updated',null,null,null,null,null,null, $get($input,'actorId','processedBy')?:null, null, ['rules'=>$rules]);
            echo json_encode(['success'=>true,'message'=>'Business rules updated.']);
            exit;
        }

        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Unknown POST action: '.$action]); exit;
    }

    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']);
} catch (Throwable $e){
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
