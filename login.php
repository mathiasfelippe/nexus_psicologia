<?php
/*
 * ARQUIVO: login.php
 * DESCRIÇÃO: Página de login e cadastro do sistema Nexus Psicologia.
 *
 * Este arquivo combina backend PHP e frontend HTML/JS em um único arquivo.
 * Ele é responsável por:
 *   1. Processar o formulário de LOGIN (verificar email e senha no banco)
 *   2. Processar o formulário de CADASTRO (criar novo paciente no banco)
 *   3. Renderizar a interface visual com animações (partículas, tilt 3D, ripple)
 *
 * FLUXO GERAL:
 *   - O PHP roda primeiro (no servidor), processa o POST e define variáveis
 *   - Em seguida, o HTML é gerado e enviado ao navegador do usuário
 *   - O JavaScript roda no navegador para animações e validações client-side
 */

// Carrega o arquivo de conexão com o banco de dados (cria a variável $pdo)
require_once 'config/conexao.php';

// Carrega funções auxiliares do sistema (autenticação, consultas, etc.)
require_once 'config/funcoes.php';

// Variáveis de feedback para o usuário: erros e mensagens de sucesso
$erro = '';
$sucesso = '';

// Determina o modo inicial da página: 'login' ou 'cadastro'
// Lê da URL (?modo=cadastro) ou usa 'login' como padrão
$modo = isset($_GET['modo']) ? $_GET['modo'] : 'login';

/*
 * FUNÇÃO: processarUploadFoto
 * Processa o upload da foto de perfil do novo paciente.
 * Valida extensão e tamanho do arquivo, salva no servidor e retorna o caminho.
 *
 * @param array $arquivo - Array $_FILES['foto_perfil'] com dados do arquivo enviado
 * @return array - ['sucesso' => 'caminho/do/arquivo'] ou ['erro' => 'mensagem']
 */
function processarUploadFoto($arquivo) {
    // Define o diretório de destino das fotos de perfil
    $diretorio = __DIR__ . '/uploads/fotos/';

    // Cria o diretório se ele não existir (0777 = permissões totais, true = recursivo)
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0777, true);
    }

    // Extrai a extensão do arquivo enviado (ex: 'jpg', 'png')
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

    // Lista de extensões permitidas para foto de perfil
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Rejeita se a extensão não for permitida
    if (!in_array($extensao, $extensoes_permitidas)) {
        return ['erro' => 'Formato de imagem nao permitido. Use: JPG, PNG, GIF ou WEBP.'];
    }

    // Rejeita se o arquivo for maior que 5MB (5 * 1024 * 1024 bytes)
    if ($arquivo['size'] > 5 * 1024 * 1024) {
        return ['erro' => 'A imagem deve ter no maximo 5MB.'];
    }

    // Gera um nome único para o arquivo (evita sobrescrever arquivos existentes)
    $nome_arquivo = uniqid() . '.' . $extensao;
    $caminho = $diretorio . $nome_arquivo;

    // Move o arquivo do diretório temporário para o destino final
    if (move_uploaded_file($arquivo['tmp_name'], $caminho)) {
        // Retorna o caminho relativo para salvar no banco de dados
        return ['sucesso' => 'uploads/fotos/' . $nome_arquivo];
    }

    return ['erro' => 'Erro ao fazer upload da imagem.'];
}

/*
 * FUNÇÃO: validarTelefone
 * Valida se um número de telefone brasileiro é válido.
 * Aceita formatos com ou sem máscara (ex: "(11) 99999-9999" ou "11999999999").
 *
 * @param string $telefone - Número de telefone a validar
 * @return bool - true se válido, false se inválido
 */
function validarTelefone($telefone) {
    // Remove tudo que não for dígito numérico
    $numeros = preg_replace('/[^0-9]/', '', $telefone);

    // Telefone deve ter 10 (fixo) ou 11 (celular) dígitos
    if (strlen($numeros) < 10 || strlen($numeros) > 11) return false;

    // Extrai o DDD (primeiros 2 dígitos) e valida o intervalo
    $ddd = substr($numeros, 0, 2);
    if ($ddd < 11 || $ddd > 99) return false;

    return true;
}

/*
 * FUNÇÃO: validarDataNascimento
 * Valida se uma data de nascimento é válida e se o usuário tem pelo menos 13 anos.
 *
 * @param string $data - Data no formato 'Y-m-d' (ex: '2000-05-15')
 * @return bool - true se válida, false se inválida
 */
function validarDataNascimento($data) {
    // Tenta criar um objeto DateTime a partir da string recebida
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);

    // Verifica se a data é válida e se o formato está correto
    if (!$data_obj || $data_obj->format('Y-m-d') !== $data) return false;

    $hoje = new DateTime();

    // Calcula a idade em anos completos
    $idade = $hoje->diff($data_obj)->y;

    // Rejeita menores de 13 anos ou datas no futuro
    if ($idade < 13 || $data_obj > $hoje) return false;

    return true;
}

/*
 * PROCESSAMENTO DO FORMULÁRIO (POST)
 * Só executa quando o formulário é enviado (método POST).
 * Verifica qual ação foi enviada: 'login' ou 'cadastro'.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ─── AÇÃO: LOGIN ─── */
    if (isset($_POST['acao']) && $_POST['acao'] === 'login') {
        // Lê e limpa os dados enviados pelo formulário
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $tipo_usuario = $_POST['tipo_usuario'] ?? 'paciente';

        // Validação básica: campos obrigatórios
        if (empty($email) || empty($senha)) {
            $erro = 'Email e senha sao obrigatorios.';
        } else {
            try {
                if ($tipo_usuario === 'psicologa') {
                    // Limpar session de paciente se existir (evita conflito de sessões)
                    unset($_SESSION['id_paciente'], $_SESSION['nome_paciente'], $_SESSION['email_paciente']);

                    // Busca a psicóloga pelo email no banco de dados
                    // Usa prepared statement para evitar SQL Injection
                    $stmt = $pdo->prepare("SELECT * FROM psicologa WHERE email = ?");
                    // Executa a consulta no banco de dados
                    $stmt->execute([$email]);
                    $usuario = $stmt->fetch();

                    // Verifica se encontrou o usuário e se a senha está correta
                    // password_verify compara a senha digitada com o hash armazenado
                    if ($usuario && password_verify($senha, $usuario['senha'])) {
                        // Salva os dados da psicóloga na sessão PHP
                        $_SESSION['id_psicologa'] = $usuario['id_psicologa'];
                        $_SESSION['nome_psicologa'] = $usuario['nome'];
                        $_SESSION['email_psicologa'] = $usuario['email'];

                        // Redireciona para o dashboard da psicóloga
                        header('Location: dashboard_psicologa.php');
                        exit; // Para a execução do PHP após o redirecionamento
                    } else {
                        $erro = 'Email ou senha incorretos.';
                    }
                } else {
                    // Limpar session de psicóloga se existir (evita conflito de sessões)
                    unset($_SESSION['id_psicologa'], $_SESSION['nome_psicologa'], $_SESSION['email_psicologa']);

                    // Busca o paciente pelo email no banco de dados
                    // Usa prepared statement para evitar SQL Injection
                    $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE email = ?");
                    // Executa a consulta no banco de dados
                    $stmt->execute([$email]);
                    $usuario = $stmt->fetch();

                    // Verifica se encontrou o usuário e se a senha está correta
                    if ($usuario && password_verify($senha, $usuario['senha'])) {
                        // Salva os dados do paciente na sessão PHP
                        $_SESSION['id_paciente'] = $usuario['id'];
                        $_SESSION['nome_paciente'] = $usuario['nome'];
                        $_SESSION['email_paciente'] = $usuario['email'];

                        // Redireciona para o dashboard do paciente
                        header('Location: dashboard_paciente.php');
                        exit;
                    } else {
                        $erro = 'Email ou senha incorretos.';
                    }
                }
            } catch (PDOException $e) {
                // Captura erros de banco de dados e exibe mensagem amigável
                $erro = 'Erro ao processar login: ' . $e->getMessage();
            }
        }

    /* ─── AÇÃO: CADASTRO ─── */
    } elseif (isset($_POST['acao']) && $_POST['acao'] === 'cadastro') {
        // Lê e limpa todos os campos do formulário de cadastro
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');
        $data_nascimento = trim($_POST['data_nascimento'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $foto_perfil = null; // Será preenchido se o usuário enviar uma foto

        // Validações em cascata: a primeira que falhar define o erro
        if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
            $erro = 'Nome, email e senha sao obrigatorios.';
        } elseif ($senha !== $confirmar_senha) {
            $erro = 'As senhas nao correspondem.';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha deve ter no minimo 6 caracteres.';
        } elseif (!empty($telefone) && !validarTelefone($telefone)) {
            $erro = 'Telefone invalido. Use o formato (XX) XXXXX-XXXX.';
        } elseif (!empty($data_nascimento) && !validarDataNascimento($data_nascimento)) {
            $erro = 'Data de nascimento invalida. O usuario deve ter pelo menos 13 anos.';
        } else {
            // Processa o upload da foto de perfil (se enviada)
            if (!empty($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                $resultado_upload = processarUploadFoto($_FILES['foto_perfil']);
                if (isset($resultado_upload['erro'])) {
                    $erro = $resultado_upload['erro'];
                } else {
                    $foto_perfil = $resultado_upload['sucesso'];
                }
            }

            // Só prossegue com o cadastro se não houve erro no upload
            if (empty($erro)) {
                try {
                    // Verifica se o email já está cadastrado no banco
                    // Usa prepared statement para evitar SQL Injection
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pacientes WHERE email = ?");
                    // Executa a consulta no banco de dados
                    $stmt->execute([$email]);
                    if ($stmt->fetchColumn() > 0) {
                        $erro = 'Este email ja esta cadastrado.';
                    } else {
                        // Gera o hash seguro da senha (BCRYPT é o algoritmo mais seguro disponível)
                        // NUNCA salve senhas em texto puro no banco de dados
                        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);

                        // Insere o novo paciente no banco (com ou sem foto de perfil)
                        if ($foto_perfil) {
                            // Cadastro com foto: inclui o campo foto_perfil
                            // Usa prepared statement para evitar SQL Injection
                            $stmt = $pdo->prepare("INSERT INTO pacientes (nome, email, senha, data_nascimento, telefone, foto_perfil) VALUES (?, ?, ?, ?, ?, ?)");
                            // Executa a consulta no banco de dados
                            $stmt->execute([$nome, $email, $senha_hash, $data_nascimento, $telefone, $foto_perfil]);
                        } else {
                            // Cadastro sem foto: omite o campo foto_perfil
                            // Usa prepared statement para evitar SQL Injection
                            $stmt = $pdo->prepare("INSERT INTO pacientes (nome, email, senha, data_nascimento, telefone) VALUES (?, ?, ?, ?, ?)");
                            // Executa a consulta no banco de dados
                            $stmt->execute([$nome, $email, $senha_hash, $data_nascimento, $telefone]);
                        }

                        // Cadastro bem-sucedido: exibe mensagem e volta para o modo login
                        $sucesso = 'Cadastro realizado com sucesso! Faca login para continuar.';
                        $modo = 'login'; // Muda para a aba de login automaticamente
                    }
                } catch (PDOException $e) {
                    // Captura erros de banco de dados
                    $erro = 'Erro ao cadastrar: ' . $e->getMessage();
                }
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
    <title>Nexus Psicologia - Login</title>
    <!-- Ícone da aba do navegador -->
    <link rel="icon" href="assets/simbologo.png">
    <!-- Pré-conexão com o Google Fonts para carregamento mais rápido -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Fontes: Comfortaa (texto) e Lora (títulos) -->
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&family=Lora:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <!-- Estilos da página de login -->
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <!-- ═══════════════════════════════════════════════════════════
         BACKGROUND ANIMADO
         Camadas de fundo: gradiente mesh + blobs flutuantes + partículas canvas
    ═══════════════════════════════════════════════════════════════ -->
    <div class="login-scene">
        <!-- Gradiente de fundo com textura mesh -->
        <div class="mesh-bg"></div>
        <!-- Blobs: formas orgânicas animadas que flutuam no fundo -->
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="blob blob-4"></div>
        <!-- Canvas para as partículas animadas (desenhadas via JavaScript) -->
        <canvas id="particles-canvas"></canvas>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         CARD CENTRAL DE LOGIN/CADASTRO
    ═══════════════════════════════════════════════════════════════ -->
    <div class="login-container">
        <!-- O id="loginCard" é usado pelo JS para o efeito de tilt 3D -->
        <div class="login-card" id="loginCard">

            <!-- Cabeçalho: logo + título + subtítulo -->
            <div class="login-header">
                <div class="login-logo">
                    <img src="assets/simbologo.png" alt="Nexus">
                </div>
                <!-- O texto deste título é alterado pelo JS ao trocar de aba -->
                <h1 id="form-title">Bem-vindo(a) de volta</h1>
                <p class="subtitle" id="form-subtitle">Entre na sua conta para continuar</p>
            </div>

            <!-- ─── ALERTAS DE FEEDBACK ─── -->
            <!-- Exibe mensagem de erro se $erro não estiver vazio -->
            <?php if ($erro): ?>
                <div class="alerta alerta-erro">
                    <!-- Ícone SVG de alerta (círculo com exclamação) -->
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <!-- htmlspecialchars previne XSS (injeção de HTML malicioso) -->
                    <span><?php echo htmlspecialchars($erro); ?></span>
                </div>
            <?php endif; ?>

            <!-- Exibe mensagem de sucesso se $sucesso não estiver vazio -->
            <?php if ($sucesso): ?>
                <div class="alerta alerta-sucesso">
                    <!-- Ícone SVG de check (confirmação) -->
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span><?php echo htmlspecialchars($sucesso); ?></span>
                </div>
            <?php endif; ?>

            <!-- ─── ABAS: ENTRAR / CRIAR CONTA ─── -->
            <!-- PHP define qual aba está ativa com base na variável $modo -->
            <div class="login-tabs">
                <button class="tab-btn <?php echo $modo === 'login' ? 'ativa' : ''; ?>" onclick="mudarModo('login')" id="tab-login">Entrar</button>
                <button class="tab-btn <?php echo $modo === 'cadastro' ? 'ativa' : ''; ?>" onclick="mudarModo('cadastro')" id="tab-cadastro">Criar Conta</button>
            </div>

            <!-- ═══════════════════════════════════════════════════
                 FORMULÁRIO DE LOGIN
                 Classe 'ativo' é adicionada pelo PHP se $modo === 'login'
            ═══════════════════════════════════════════════════════ -->
            <form id="form-login" class="formulario <?php echo $modo === 'login' ? 'ativo' : ''; ?>" method="POST">
                <!-- Campo oculto que informa ao PHP qual ação processar -->
                <input type="hidden" name="acao" value="login">

                <!-- Seleção do tipo de usuário: Paciente ou Psicóloga -->
                <div class="tipo-usuario">
                    <label class="tipo-option">
                        <input type="radio" name="tipo_usuario" value="paciente" checked>
                        <span class="tipo-check"></span>
                        <span class="tipo-texto">Paciente</span>
                    </label>
                    <label class="tipo-option">
                        <input type="radio" name="tipo_usuario" value="psicologa">
                        <span class="tipo-check"></span>
                        <span class="tipo-texto">Psicologa</span>
                    </label>
                </div>

                <!-- Campo de email -->
                <div class="form-group">
                    <label for="email-login">Email</label>
                    <div class="input-wrapper">
                        <!-- Ícone SVG de envelope -->
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="text" id="email-login" name="email" placeholder="seu@email.com" required>
                    </div>
                </div>

                <!-- Campo de senha com botão para mostrar/ocultar -->
                <div class="form-group">
                    <label for="senha-login">Senha</label>
                    <div class="input-wrapper">
                        <!-- Ícone SVG de cadeado -->
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <!-- Senha oculta por padrão (type="password") -->
                        <input type="password" id="senha-login" name="senha" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;" required>
                        <!-- Botão que chama toggleSenha() para mostrar/ocultar a senha -->
                        <button type="button" class="toggle-senha" onclick="toggleSenha('senha-login')">
                            <!-- Ícone SVG de olho -->
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Opções adicionais: "Lembrar-me" e "Esqueci minha senha" -->
                <div class="form-options">
                    <label class="lembrar-me">
                        <input type="checkbox" name="lembrar">
                        <span>Lembrar-me</span>
                    </label>
                    <a href="#" class="link-esqueci">Esqueci minha senha</a>
                </div>

                <!-- Botão de envio do formulário de login -->
                <button type="submit" class="btn-login">
                    <!-- Ícone SVG de seta de entrada -->
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <span>Entrar</span>
                </button>
            </form>

            <!-- ═══════════════════════════════════════════════════
                 FORMULÁRIO DE CADASTRO
                 enctype="multipart/form-data" é necessário para upload de arquivo
            ═══════════════════════════════════════════════════════ -->
            <form id="form-cadastro" class="formulario <?php echo $modo === 'cadastro' ? 'ativo' : ''; ?>" method="POST" enctype="multipart/form-data">
                <!-- Campo oculto que informa ao PHP qual ação processar -->
                <input type="hidden" name="acao" value="cadastro">

                <!-- Upload de foto de perfil com preview visual -->
                <div class="foto-upload-container">
                    <!-- Clique no wrapper abre o input de arquivo oculto -->
                    <div class="foto-preview-wrapper" onclick="document.getElementById('foto_perfil').click()">
                        <!-- Placeholder exibido antes de escolher uma foto -->
                        <div class="foto-placeholder" id="foto-placeholder">
                            <!-- Ícone SVG de câmera -->
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                            <span>Foto</span>
                        </div>
                    </div>
                    <!-- Input de arquivo oculto (ativado pelo clique no wrapper acima) -->
                    <!-- onchange chama previewFoto() para mostrar a imagem escolhida -->
                    <input type="file" id="foto_perfil" name="foto_perfil" class="foto-upload-input" accept="image/*" onchange="previewFoto(this)">
                    <span class="foto-upload-label" onclick="document.getElementById('foto_perfil').click()">Escolher foto de perfil</span>
                </div>

                <!-- Campo de nome completo -->
                <div class="form-group">
                    <label for="nome-cadastro">Nome Completo</label>
                    <div class="input-wrapper">
                        <!-- Ícone SVG de pessoa -->
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <input type="text" id="nome-cadastro" name="nome" placeholder="Seu nome completo" required>
                    </div>
                </div>

                <!-- Campo de email -->
                <div class="form-group">
                    <label for="email-cadastro">Email</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="email" id="email-cadastro" name="email" placeholder="seu@email.com" required>
                    </div>
                </div>

                <!-- Campo de data de nascimento -->
                <!-- max= define a data máxima como 13 anos atrás (calculado pelo PHP) -->
                <div class="form-group">
                    <label for="data-nascimento">Data de Nascimento</label>
                    <div class="input-wrapper">
                        <input type="date" id="data-nascimento" name="data_nascimento" max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>">
                    </div>
                </div>

                <!-- Campo de telefone (com máscara aplicada pelo JS) -->
                <div class="form-group">
                    <label for="telefone-cadastro">Telefone</label>
                    <div class="input-wrapper">
                        <input type="tel" id="telefone-cadastro" name="telefone" placeholder="(00) 00000-0000" maxlength="15">
                    </div>
                </div>

                <!-- Campo de senha -->
                <div class="form-group">
                    <label for="senha-cadastro">Senha</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <!-- minlength="6" é validação HTML5 (o PHP também valida) -->
                        <input type="password" id="senha-cadastro" name="senha" placeholder="Minimo 6 caracteres" required minlength="6">
                        <button type="button" class="toggle-senha" onclick="toggleSenha('senha-cadastro')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Campo de confirmação de senha -->
                <div class="form-group">
                    <label for="confirmar-senha">Confirmar Senha</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="confirmar-senha" name="confirmar_senha" placeholder="Repita a senha" required>
                        <button type="button" class="toggle-senha" onclick="toggleSenha('confirmar-senha')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Botão de envio do formulário de cadastro -->
                <button type="submit" class="btn-login">
                    <!-- Ícone SVG de adicionar usuário -->
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    <span>Criar Conta</span>
                </button>
            </form>

            <!-- Rodapé do card: link para voltar à página inicial -->
            <div class="login-footer">
                <p>Voltar para <a href="index.html">pagina inicial</a></p>
            </div>
        </div>
    </div>

    <!-- Frase motivacional exibida abaixo do card -->
    <div class="login-bottom-quote">
        <p>"O primeiro passo para o bem-estar e dar-se permissao para cuidar de voce."</p>
    </div>

    <script>
    /* ═══════════════════════════════════════════════════════════
       PARTÍCULAS ANIMADAS
       Cria e anima pontos coloridos no canvas de fundo.
       As partículas se repelem levemente do cursor do mouse.
    ═══════════════════════════════════════════════════════════ */
    (function() {
        // Referências ao canvas e ao contexto 2D de desenho
        var canvas = document.getElementById('particles-canvas');
        var ctx = canvas.getContext('2d');
        var particles = [];       // Array que armazena todas as partículas
        var numParticles = 35;    // Número total de partículas na tela
        var mouse = { x: null, y: null }; // Posição atual do mouse
        var lastMove = 0;         // Timestamp do último evento de mouse (throttle)

        // Ajusta o tamanho do canvas para cobrir toda a janela
        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resize();
        // Reajusta o canvas quando a janela é redimensionada
        window.addEventListener('resize', resize);

        // Rastreia a posição do mouse com throttle de 50ms para performance
        document.addEventListener('mousemove', function(e) {
            var now = Date.now();
            if (now - lastMove > 50) { // Só atualiza a cada 50ms
                mouse.x = e.clientX;
                mouse.y = e.clientY;
                lastMove = now;
            }
        });

        /*
         * CONSTRUTOR: Particle
         * Cria uma partícula com posição, tamanho, velocidade e cor aleatórios.
         */
        function Particle() {
            this.x = Math.random() * canvas.width;   // Posição X aleatória
            this.y = Math.random() * canvas.height;  // Posição Y aleatória
            this.size = Math.random() * 2.5 + 0.5;  // Tamanho entre 0.5 e 3px
            this.speedX = (Math.random() - 0.5) * 0.4; // Velocidade X: -0.2 a +0.2
            this.speedY = (Math.random() - 0.5) * 0.4; // Velocidade Y: -0.2 a +0.2
            this.opacity = Math.random() * 0.4 + 0.1;  // Opacidade: 0.1 a 0.5
            // Cores da paleta Nexus em formato RGB
            var colors = ['128,161,212', '117,201,200', '192,185,221', '72,72,72'];
            this.color = colors[Math.floor(Math.random() * colors.length)];
        }

        /*
         * MÉTODO: update
         * Atualiza a posição da partícula a cada frame.
         * Aplica repulsão do mouse e faz a partícula "teletransportar"
         * para o lado oposto quando sai da tela.
         */
        Particle.prototype.update = function() {
            // Move a partícula pela velocidade definida
            this.x += this.speedX;
            this.y += this.speedY;

            // Repulsão do mouse: se o mouse estiver próximo (< 100px), afasta a partícula
            if (mouse.x !== null) {
                var dx = mouse.x - this.x;
                var dy = mouse.y - this.y;
                var dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 100) {
                    var force = (100 - dist) / 100; // Força inversamente proporcional à distância
                    this.x -= dx * force * 0.005;   // Afasta na direção X
                    this.y -= dy * force * 0.005;   // Afasta na direção Y
                }
            }

            // Wrap-around: quando sai da tela, reaparece no lado oposto
            if (this.x < 0) this.x = canvas.width;
            if (this.x > canvas.width) this.x = 0;
            if (this.y < 0) this.y = canvas.height;
            if (this.y > canvas.height) this.y = 0;
        };

        /*
         * MÉTODO: draw
         * Desenha a partícula como um círculo no canvas.
         */
        Particle.prototype.draw = function() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2); // Círculo completo
            ctx.fillStyle = 'rgba(' + this.color + ',' + this.opacity + ')';
            ctx.fill();
        };

        // Cria as 35 partículas iniciais
        for (var i = 0; i < numParticles; i++) {
            particles.push(new Particle());
        }

        /*
         * FUNÇÃO: connectParticles
         * Desenha linhas finas entre partículas próximas (< ~89px de distância).
         * A opacidade da linha diminui conforme a distância aumenta.
         */
        function connectParticles() {
            for (var a = 0; a < particles.length; a++) {
                for (var b = a + 1; b < particles.length; b++) {
                    var dx = particles[a].x - particles[b].x;
                    var dy = particles[a].y - particles[b].y;
                    var dist = dx * dx + dy * dy; // Distância ao quadrado (mais rápido que sqrt)
                    if (dist < 8000) { // 8000 ≈ 89px ao quadrado
                        var opacity = (1 - dist / 8000) * 0.1; // Opacidade máxima de 10%
                        ctx.beginPath();
                        ctx.strokeStyle = 'rgba(192,185,221,' + opacity + ')'; // Lavanda
                        ctx.lineWidth = 0.4;
                        ctx.moveTo(particles[a].x, particles[a].y);
                        ctx.lineTo(particles[b].x, particles[b].y);
                        ctx.stroke();
                    }
                }
            }
        }

        /*
         * FUNÇÃO: animate
         * Loop de animação principal. Chamada 60 vezes por segundo pelo navegador.
         * Limpa o canvas, redesenha todas as partículas e as conexões.
         */
        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height); // Limpa o canvas
            for (var i = 0; i < particles.length; i++) {
                particles[i].update(); // Atualiza posição
                particles[i].draw();   // Desenha
            }
            connectParticles(); // Desenha as linhas de conexão
            requestAnimationFrame(animate); // Agenda o próximo frame
        }
        animate(); // Inicia o loop de animação
    })();

    /* ═══════════════════════════════════════════════════════════
       3D TILT SUTIL NO CARD
       Aplica uma leve rotação 3D no card de login conforme
       o mouse se move sobre ele, criando efeito de profundidade.
    ═══════════════════════════════════════════════════════════ */
    (function() {
        var card = document.getElementById('loginCard');
        var maxTilt = 2.5;   // Rotação máxima em graus
        var maxShift = 1;    // Deslocamento máximo em pixels
        var rafId = null;    // ID do requestAnimationFrame (para evitar múltiplos frames)
        var targetRotateX = 0;
        var targetRotateY = 0;

        // Calcula a rotação com base na posição do mouse dentro do card
        card.addEventListener('mousemove', function(e) {
            var rect = card.getBoundingClientRect();
            var x = e.clientX - rect.left;   // Posição X relativa ao card
            var y = e.clientY - rect.top;    // Posição Y relativa ao card
            var centerX = rect.width / 2;
            var centerY = rect.height / 2;

            // Calcula os ângulos de rotação (valores entre -maxTilt e +maxTilt)
            targetRotateX = ((y - centerY) / centerY) * -maxTilt;
            targetRotateY = ((x - centerX) / centerX) * maxTilt;

            // Usa requestAnimationFrame para sincronizar com o refresh da tela
            if (!rafId) {
                rafId = requestAnimationFrame(function() {
                    var shiftX = ((x - centerX) / centerX) * maxShift;
                    var shiftY = ((y - centerY) / centerY) * maxShift;
                    // Aplica a transformação 3D no card
                    card.style.transform = 'perspective(1200px) rotateX(' + targetRotateX + 'deg) rotateY(' + targetRotateY + 'deg) translate(' + shiftX + 'px, ' + shiftY + 'px)';
                    rafId = null;
                });
            }
        });

        // Quando o mouse sai do card, volta para a posição neutra suavemente
        card.addEventListener('mouseleave', function() {
            card.style.transform = 'perspective(1200px) rotateX(0deg) rotateY(0deg) translate(0, 0)';
            card.style.transition = 'transform 0.5s cubic-bezier(.4,0,.2,1)';
        });

        // Quando o mouse entra no card, usa transição mais rápida para responsividade
        card.addEventListener('mouseenter', function() {
            card.style.transition = 'transform 0.15s ease-out';
        });
    })();

    /* ═══════════════════════════════════════════════════════════
       RIPPLE NOS BOTÕES
       Cria um efeito de "onda" que se expande a partir do ponto
       de clique nos botões de login/cadastro.
    ═══════════════════════════════════════════════════════════ */
    document.querySelectorAll('.btn-login').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            // Cria um elemento <span> que será a "onda"
            var ripple = document.createElement('span');
            ripple.classList.add('ripple');
            var rect = btn.getBoundingClientRect();
            // O tamanho da onda é o maior lado do botão (para cobrir tudo)
            var size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            // Centraliza a onda no ponto de clique
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(ripple);
            // Remove o elemento após a animação terminar (600ms)
            setTimeout(function() { ripple.remove(); }, 600);
        });
    });

    /* ═══════════════════════════════════════════════════════════
       MUDAR MODO (LOGIN / CADASTRO)
       Alterna entre os formulários de login e cadastro com
       animação de saída e entrada.
    ═══════════════════════════════════════════════════════════ */
    function mudarModo(modo) {
        var formLogin = document.getElementById('form-login');
        var formCadastro = document.getElementById('form-cadastro');
        var tabs = document.querySelectorAll('.tab-btn');
        var title = document.getElementById('form-title');
        var subtitle = document.getElementById('form-subtitle');

        // Determina qual formulário está ativo e qual deve ser exibido
        var formAtual = modo === 'login' ? formCadastro : formLogin;
        var formNovo = modo === 'login' ? formLogin : formCadastro;

        // Adiciona classe de saída para animar o formulário atual
        formAtual.classList.add('saindo');
        setTimeout(function() {
            // Após a animação de saída (250ms), troca os formulários
            formAtual.classList.remove('ativo', 'saindo');
            formNovo.classList.add('ativo');

            // Atualiza o título, subtítulo e abas conforme o modo
            if (modo === 'login') {
                tabs[0].classList.add('ativa');
                tabs[1].classList.remove('ativa');
                title.textContent = 'Bem-vindo(a) de volta';
                subtitle.textContent = 'Entre na sua conta para continuar';
            } else {
                tabs[0].classList.remove('ativa');
                tabs[1].classList.add('ativa');
                title.textContent = 'Crie sua conta';
                subtitle.textContent = 'Preencha os dados para comecar';
            }
            // Atualiza a URL sem recarregar a página (para o botão "voltar" funcionar)
            window.history.replaceState({}, '', '?modo=' + modo);
        }, 250);
    }

    /* ═══════════════════════════════════════════════════════════
       TOGGLE SENHA
       Alterna a visibilidade do campo de senha entre
       texto visível e pontos (•••••).
    ═══════════════════════════════════════════════════════════ */
    function toggleSenha(inputId) {
        var input = document.getElementById(inputId);
        // Se o tipo for 'password' (oculto), muda para 'text' (visível) e vice-versa
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    /* ═══════════════════════════════════════════════════════════
       PREVIEW FOTO
       Exibe uma prévia da foto de perfil escolhida pelo usuário
       antes de enviar o formulário.
    ═══════════════════════════════════════════════════════════ */
    function previewFoto(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader(); // API do navegador para ler arquivos locais
            reader.onload = function(e) {
                // Substitui o placeholder pela imagem escolhida
                var wrapper = document.querySelector('.foto-preview-wrapper');
                wrapper.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            };
            reader.readAsDataURL(input.files[0]); // Lê o arquivo como URL base64
        }
    }

    /* ═══════════════════════════════════════════════════════════
       MÁSCARA TELEFONE
       Formata automaticamente o campo de telefone enquanto o
       usuário digita: (XX) XXXXX-XXXX
    ═══════════════════════════════════════════════════════════ */
    document.getElementById('telefone-cadastro')?.addEventListener('input', function(e) {
        // Remove tudo que não for número
        var valor = e.target.value.replace(/\D/g, '');
        // Limita a 11 dígitos (DDD + 9 dígitos do celular)
        if (valor.length > 11) valor = valor.substring(0, 11);
        // Aplica a máscara progressivamente conforme o usuário digita
        if (valor.length > 7) {
            // Formato completo: (XX) XXXXX-XXXX
            valor = '(' + valor.substring(0, 2) + ') ' + valor.substring(2, 7) + '-' + valor.substring(7);
        } else if (valor.length > 2) {
            // Formato parcial: (XX) XXXXX
            valor = '(' + valor.substring(0, 2) + ') ' + valor.substring(2);
        }
        e.target.value = valor;
    });

    /* ═══════════════════════════════════════════════════════════
       VALIDAÇÃO CADASTRO (CLIENT-SIDE)
       Valida o formulário antes de enviar ao servidor.
       Isso é uma camada extra de segurança/UX; o PHP também valida.
    ═══════════════════════════════════════════════════════════ */
    document.getElementById('form-cadastro').addEventListener('submit', function(e) {
        var senha = document.getElementById('senha-cadastro').value;
        var confirmar = document.getElementById('confirmar-senha').value;
        var telefone = document.getElementById('telefone-cadastro').value;
        var dataNascimento = document.getElementById('data-nascimento').value;

        // Verifica se as senhas coincidem
        if (senha !== confirmar) {
            e.preventDefault(); // Cancela o envio do formulário
            alert('As senhas nao correspondem!');
            return false;
        }
        // Verifica o tamanho mínimo da senha
        if (senha.length < 6) {
            e.preventDefault();
            alert('A senha deve ter no minimo 6 caracteres!');
            return false;
        }
        // Verifica se o telefone tem pelo menos 10 dígitos (se preenchido)
        if (telefone && telefone.replace(/\D/g, '').length < 10) {
            e.preventDefault();
            alert('Telefone invalido! Use o formato (XX) XXXXX-XXXX');
            return false;
        }
        // Verifica a idade mínima de 13 anos (se a data foi preenchida)
        if (dataNascimento) {
            var hoje = new Date();
            var nascimento = new Date(dataNascimento);
            var idade = hoje.getFullYear() - nascimento.getFullYear();
            if (idade < 13 || nascimento > hoje) {
                e.preventDefault();
                alert('Data de nascimento invalida. Voce deve ter pelo menos 13 anos.');
                return false;
            }
        }
    });
    </script>
</body>
</html>
