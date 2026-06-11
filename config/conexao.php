<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ─── CONFIGURAÇÕES DO MYSQL ───────────────────────────────────────────────────
define('DB_HOST',    '10.116.233.45');
define('DB_PORT',    '3306');
define('DB_NAME',    'nexus');
define('DB_USER',    'familyhub');
define('DB_PASS',    'SUA_SENHA_AQUI');   // ← coloque sua senha aqui
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {
    error_log('Erro de conexão MySQL: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['erro' => 'Erro interno no servidor. Tente novamente mais tarde.']));
}
?>
