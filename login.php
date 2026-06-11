<?php
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$erro = '';
$sucesso = '';
$modo = isset($_GET['modo']) ? $_GET['modo'] : 'login'; // login ou cadastro

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] === 'login') {
        // Processar login
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $tipo_usuario = $_POST['tipo_usuario'] ?? 'paciente';

        if (empty($email) || empty($senha)) {
            $erro = 'Email e senha são obrigatórios.';
        } else {
            try {
                if ($tipo_usuario === 'psicologa') {
                    // Login da psicóloga
                    $stmt = $pdo->prepare("SELECT * FROM psicologa WHERE email = ?");
                    $stmt->execute([$email]);
                    $usuario = $stmt->fetch();

                    if ($usuario && password_verify($senha, $usuario['senha'])) {
                        $_SESSION['id_psicologa'] = $usuario['id_psicologa'];
                        $_SESSION['nome_psicologa'] = $usuario['nome'];
                        $_SESSION['email_psicologa'] = $usuario['email'];
                        header('Location: dashboard_psicologa.php');
                        exit;
                    } else {
                        $erro = 'Email ou senha incorretos.';
                    }
                } else {
                    // Login do paciente
                    $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE email = ?");
                    $stmt->execute([$email]);
                    $usuario = $stmt->fetch();

                    if ($usuario && password_verify($senha, $usuario['senha'])) {
                        $_SESSION['id_paciente'] = $usuario['id'];
                        $_SESSION['nome_paciente'] = $usuario['nome'];
                        $_SESSION['email_paciente'] = $usuario['email'];
                        header('Location: dashboard_paciente.php');
                        exit;
                    } else {
                        $erro = 'Email ou senha incorretos.';
                    }
                }
            } catch (PDOException $e) {
                $erro = 'Erro ao processar login: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['acao']) && $_POST['acao'] === 'cadastro') {
        // Processar cadastro
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');
        $data_nascimento = trim($_POST['data_nascimento'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');

        if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
            $erro = 'Nome, email e senha são obrigatórios.';
        } elseif ($senha !== $confirmar_senha) {
            $erro = 'As senhas não correspondem.';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha deve ter no mínimo 6 caracteres.';
        } else {
            try {
                // Verificar se o email já existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pacientes WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $erro = 'Este email já está cadastrado.';
                } else {
                    // Inserir novo paciente
                    $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        INSERT INTO pacientes (nome, email, senha, data_nascimento, telefone)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$nome, $email, $senha_hash, $data_nascimento, $telefone]);

                    $sucesso = 'Cadastro realizado com sucesso! Faça login para continuar.';
                    $modo = 'login';
                }
            } catch (PDOException $e) {
                $erro = 'Erro ao cadastrar: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Nexus Psicologia</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <a href="index.html" class="logo-link">
                    <img src="assets/img/logo.png" alt="Nexus Logo" class="login-logo">
                </a>
                <h1>Nexus Psicologia</h1>
            </div>

            <?php if ($erro): ?>
                <div class="alerta alerta-erro">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <?php if ($sucesso): ?>
                <div class="alerta alerta-sucesso">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>

            <div class="abas">
                <button class="aba-btn <?php echo $modo === 'login' ? 'ativa' : ''; ?>" onclick="mudarModo('login')">
                    Login
                </button>
                <button class="aba-btn <?php echo $modo === 'cadastro' ? 'ativa' : ''; ?>" onclick="mudarModo('cadastro')">
                    Cadastro
                </button>
            </div>

            <!-- Formulário de Login -->
            <form id="form-login" class="formulario <?php echo $modo === 'login' ? 'ativo' : ''; ?>" method="POST">
                <input type="hidden" name="acao" value="login">

                <div class="tipo-usuario">
                    <label>
                        <input type="radio" name="tipo_usuario" value="paciente" checked>
                        <span>Sou Paciente</span>
                    </label>
                    <label>
                        <input type="radio" name="tipo_usuario" value="psicologa">
                        <span>Sou Psicóloga</span>
                    </label>
                </div>

                <div class="form-group">
                    <label for="email-login">Email</label>
                    <input type="email" id="email-login" name="email" required>
                </div>

                <div class="form-group">
                    <label for="senha-login">Senha</label>
                    <input type="password" id="senha-login" name="senha" required>
                </div>

                <button type="submit" class="btn btn-primary btn-bloco">Entrar</button>
            </form>

            <!-- Formulário de Cadastro -->
            <form id="form-cadastro" class="formulario <?php echo $modo === 'cadastro' ? 'ativo' : ''; ?>" method="POST">
                <input type="hidden" name="acao" value="cadastro">

                <div class="form-group">
                    <label for="nome-cadastro">Nome Completo</label>
                    <input type="text" id="nome-cadastro" name="nome" required>
                </div>

                <div class="form-group">
                    <label for="email-cadastro">Email</label>
                    <input type="email" id="email-cadastro" name="email" required>
                </div>

                <div class="form-group">
                    <label for="data-nascimento">Data de Nascimento</label>
                    <input type="date" id="data-nascimento" name="data_nascimento">
                </div>

                <div class="form-group">
                    <label for="telefone-cadastro">Telefone</label>
                    <input type="tel" id="telefone-cadastro" name="telefone">
                </div>

                <div class="form-group">
                    <label for="senha-cadastro">Senha</label>
                    <input type="password" id="senha-cadastro" name="senha" required>
                </div>

                <div class="form-group">
                    <label for="confirmar-senha">Confirmar Senha</label>
                    <input type="password" id="confirmar-senha" name="confirmar_senha" required>
                </div>

                <button type="submit" class="btn btn-primary btn-bloco">Cadastrar</button>
            </form>

            <div class="login-footer">
                <p>Voltar para <a href="index.html">página inicial</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/login.js"></script>
</body>
</html>
