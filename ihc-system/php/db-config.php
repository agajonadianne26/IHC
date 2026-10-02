<?php
/**
 * Shared MySQL connection factory. Environment overrides keep local dev
 * defaults while allowing deployment-specific credentials without code edits:
 *   IHC_DB_DSN, IHC_DB_USER, IHC_DB_PASS
 */
function ihc_pdo(): PDO
{
    $dsn  = getenv('IHC_DB_DSN')  ?: 'mysql:host=127.0.0.1;dbname=ihc;charset=utf8mb4';
    $user = getenv('IHC_DB_USER') ?: 'root';
    $pass = ($v = getenv('IHC_DB_PASS')) !== false ? $v : '';
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
