<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ─── CONFIGURAÇÕES DO SQLITE ───────────────────────────────────────────────────
// O caminho do banco de dados SQLite
define('DB_FILE', __DIR__ . '/nexus.sqlite');

try {
    // DSN para SQLite
    $dsn = 'sqlite:' . DB_FILE;

    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // No SQLite, emular prepares não é estritamente necessário como no MySQL,
        // mas mantemos a consistência com o original se possível.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Habilitar chaves estrangeiras no SQLite (por padrão vêm desabilitadas)
    $pdo->exec('PRAGMA foreign_keys = ON;');

} catch (PDOException $e) {
    error_log('Erro de conexão SQLite: ' . $e->getMessage());
    http_response_code(500);
    
    // Se for uma requisição AJAX/API, retorna JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        die(json_encode(['erro' => 'Erro interno no servidor. Tente novamente mais tarde.']));
    } else {
        die('Erro interno no servidor. Tente novamente mais tarde.');
    }
}
?>
