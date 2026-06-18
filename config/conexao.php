<?php
/*
 * ARQUIVO: config/conexao.php
 * DESCRIÇÃO: Arquivo de configuração e conexão com o banco de dados SQLite.
 *
 * Este é o primeiro arquivo incluído por todos os outros arquivos PHP do sistema.
 * Ele realiza três tarefas fundamentais:
 *   1. Configura o ambiente PHP (fuso horário, erros, cache)
 *   2. Inicia a sessão do usuário (necessária para login/logout)
 *   3. Cria a conexão com o banco de dados SQLite via PDO
 *
 * BANCO DE DADOS: SQLite (arquivo local nexus.sqlite)
 * TECNOLOGIA: PDO (PHP Data Objects) - interface unificada para bancos de dados
 *
 * INCLUÍDO EM: login.php, dashboard_paciente.php, dashboard_psicologa.php
 *              e todos os arquivos da pasta views/
 */

// Define o fuso horário padrão do PHP como horário de Brasília
// Isso garante que funções como date(), strtotime() e datetime() usem o horário correto
date_default_timezone_set('America/Sao_Paulo');

// Inicia a sessão para manter o estado do usuário logado
// A sessão armazena variáveis como $_SESSION['id_paciente'] e $_SESSION['id_psicologa']
// Deve ser chamado antes de qualquer output (HTML, echo, etc.)
session_start();

// Configurações de exibição de erros (útil durante o desenvolvimento)
// Em produção, estas configurações devem ser desativadas por segurança
ini_set('display_errors', 1);          // Exibe erros na tela
ini_set('display_startup_errors', 1);  // Exibe erros de inicialização do PHP
error_reporting(E_ALL);                // Reporta todos os tipos de erros
ini_set('log_errors', 1);              // Salva erros em arquivo de log
ini_set('error_log', __DIR__ . '/../error.log'); // Caminho do arquivo de log

// Headers HTTP para desabilitar o cache do navegador
// Garante que o navegador sempre busque a versão mais recente das páginas
// Importante para páginas de dashboard que exibem dados em tempo real
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');   // Compatibilidade com HTTP/1.0
header('Expires: 0');         // Marca o conteúdo como expirado imediatamente

// ─── CONFIGURAÇÕES DO SQLITE ───────────────────────────────────────────────────

// Define o caminho absoluto do arquivo do banco de dados SQLite
// __DIR__ é o diretório atual (config/), então o arquivo fica em config/nexus.sqlite
define('DB_FILE', __DIR__ . '/nexus.sqlite');

try {
    // DSN (Data Source Name): string que identifica o banco de dados
    // Formato para SQLite: 'sqlite:' + caminho_do_arquivo
    $dsn = 'sqlite:' . DB_FILE;

    // Cria a conexão PDO com o banco de dados SQLite
    // PDO é uma interface que permite usar o mesmo código para diferentes bancos (MySQL, SQLite, etc.)
    $pdo = new PDO($dsn, null, null, [
        // ERRMODE_EXCEPTION: lança exceções em vez de retornar false em caso de erro
        // Isso permite usar try/catch para tratar erros de banco de dados
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        // FETCH_ASSOC: por padrão, fetch() retorna arrays associativos (ex: $row['nome'])
        // em vez de arrays numéricos (ex: $row[0]) - mais legível e seguro
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // No SQLite, emular prepares não é estritamente necessário como no MySQL,
        // mas mantemos a consistência com o original se possível.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Habilitar chaves estrangeiras no SQLite (por padrão vêm desabilitadas)
    // Isso garante integridade referencial: ex: não deixa deletar um paciente
    // que tem consultas associadas
    $pdo->exec('PRAGMA foreign_keys = ON;');

} catch (PDOException $e) {
    // Captura erros de conexão com o banco de dados
    // PDOException é lançada quando ERRMODE_EXCEPTION está ativo
    error_log('Erro de conexão SQLite: ' . $e->getMessage());
    http_response_code(500); // Retorna código HTTP 500 (Internal Server Error)
    
    // Verifica se a requisição veio via AJAX (JavaScript assíncrono)
    // Requisições AJAX enviam o header 'X-Requested-With: XMLHttpRequest'
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // Para AJAX: retorna JSON com mensagem de erro (o JS espera JSON)
        die(json_encode(['erro' => 'Erro interno no servidor. Tente novamente mais tarde.']));
    } else {
        // Para requisições normais (página HTML): exibe mensagem de texto
        die('Erro interno no servidor. Tente novamente mais tarde.');
    }
}
?>
