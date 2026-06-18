<?php
/*
 * ARQUIVO: logout.php
 * DESCRIÇÃO: Realiza o logout do usuário (paciente ou psicóloga).
 *
 * Este arquivo é acessado quando o usuário clica no botão "Sair" do dashboard.
 * Ele destrói a sessão PHP (apagando todos os dados do usuário logado)
 * e redireciona para a página inicial do site.
 *
 * SEGURANÇA: Ao destruir a sessão, todas as variáveis como
 * $_SESSION['id_paciente'] e $_SESSION['id_psicologa'] são removidas,
 * impedindo acesso não autorizado às páginas protegidas.
 */

// Inicia a sessão para ter acesso aos dados da sessão atual
// Necessário para poder destruí-la em seguida
session_start();

// Destrói completamente a sessão PHP:
// - Remove todas as variáveis de sessão ($_SESSION)
// - Invalida o ID de sessão no servidor
// - O cookie de sessão no navegador ainda existe, mas não é mais válido
session_destroy();

// Redireciona o usuário para a página inicial do site
// header('Location: ...') envia um cabeçalho HTTP de redirecionamento
header('Location: index.html');

// Para a execução do PHP imediatamente após o redirecionamento
// Garante que nenhum código adicional seja executado
exit;
?>
