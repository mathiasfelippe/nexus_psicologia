<?php
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

$erro = '';
$sucesso = '';
$modo = isset($_GET['modo']) ? $_GET['modo'] : 'login';

function processarUploadFoto($arquivo) {
    $diretorio = __DIR__ . '/uploads/fotos/';
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0777, true);
    }
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extensao, $extensoes_permitidas)) {
        return ['erro' => 'Formato de imagem nao permitido. Use: JPG, PNG, GIF ou WEBP.'];
    }
    if ($arquivo['size'] > 5 * 1024 * 1024) {
        return ['erro' => 'A imagem deve ter no maximo 5MB.'];
    }
    $nome_arquivo = uniqid() . '.' . $extensao;
    $caminho = $diretorio . $nome_arquivo;
    if (move_uploaded_file($arquivo['tmp_name'], $caminho)) {
        return ['sucesso' => 'uploads/fotos/' . $nome_arquivo];
    }
    return ['erro' => 'Erro ao fazer upload da imagem.'];
}

function validarTelefone($telefone) {
    $numeros = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($numeros) < 10 || strlen($numeros) > 11) return false;
    $ddd = substr($numeros, 0, 2);
    if ($ddd < 11 || $ddd > 99) return false;
    return true;
}

function validarDataNascimento($data) {
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    if (!$data_obj || $data_obj->format('Y-m-d') !== $data) return false;
    $hoje = new DateTime();
    $idade = $hoje->diff($data_obj)->y;
    if ($idade < 13 || $data_obj > $hoje) return false;
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] === 'login') {
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $tipo_usuario = $_POST['tipo_usuario'] ?? 'paciente';

        if (empty($email) || empty($senha)) {
            $erro = 'Email e senha sao obrigatorios.';
        } else {
            try {
                if ($tipo_usuario === 'psicologa') {
                    // Limpar session de paciente se existir
                    unset($_SESSION['id_paciente'], $_SESSION['nome_paciente'], $_SESSION['email_paciente']);
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
                    // Limpar session de psicóloga se existir
                    unset($_SESSION['id_psicologa'], $_SESSION['nome_psicologa'], $_SESSION['email_psicologa']);
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
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');
        $data_nascimento = trim($_POST['data_nascimento'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $foto_perfil = null;

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
            if (!empty($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                $resultado_upload = processarUploadFoto($_FILES['foto_perfil']);
                if (isset($resultado_upload['erro'])) {
                    $erro = $resultado_upload['erro'];
                } else {
                    $foto_perfil = $resultado_upload['sucesso'];
                }
            }
            if (empty($erro)) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pacientes WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetchColumn() > 0) {
                        $erro = 'Este email ja esta cadastrado.';
                    } else {
                        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
                        if ($foto_perfil) {
                            $stmt = $pdo->prepare("INSERT INTO pacientes (nome, email, senha, data_nascimento, telefone, foto_perfil) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$nome, $email, $senha_hash, $data_nascimento, $telefone, $foto_perfil]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO pacientes (nome, email, senha, data_nascimento, telefone) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$nome, $email, $senha_hash, $data_nascimento, $telefone]);
                        }
                        $sucesso = 'Cadastro realizado com sucesso! Faca login para continuar.';
                        $modo = 'login';
                    }
                } catch (PDOException $e) {
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
    <link rel="icon" href="assets/simbologo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&family=Lora:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <!-- Background animado -->
    <div class="login-scene">
        <div class="mesh-bg"></div>
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="blob blob-4"></div>
        <canvas id="particles-canvas"></canvas>
    </div>

    <!-- Card central -->
    <div class="login-container">
        <div class="login-card" id="loginCard">

            <!-- Header -->
            <div class="login-header">
                <div class="login-logo">
                    <img src="assets/simbologo.png" alt="Nexus">
                </div>
                <h1 id="form-title">Bem-vindo(a) de volta</h1>
                <p class="subtitle" id="form-subtitle">Entre na sua conta para continuar</p>
            </div>

            <!-- Alertas -->
            <?php if ($erro): ?>
                <div class="alerta alerta-erro">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($erro); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($sucesso): ?>
                <div class="alerta alerta-sucesso">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span><?php echo htmlspecialchars($sucesso); ?></span>
                </div>
            <?php endif; ?>

            <!-- Abas -->
            <div class="login-tabs">
                <button class="tab-btn <?php echo $modo === 'login' ? 'ativa' : ''; ?>" onclick="mudarModo('login')" id="tab-login">Entrar</button>
                <button class="tab-btn <?php echo $modo === 'cadastro' ? 'ativa' : ''; ?>" onclick="mudarModo('cadastro')" id="tab-cadastro">Criar Conta</button>
            </div>

            <!-- FORM LOGIN -->
            <form id="form-login" class="formulario <?php echo $modo === 'login' ? 'ativo' : ''; ?>" method="POST">
                <input type="hidden" name="acao" value="login">

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

                <div class="form-group">
                    <label for="email-login">Email</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="text" id="email-login" name="email" placeholder="seu@email.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="senha-login">Senha</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="senha-login" name="senha" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;" required>
                        <button type="button" class="toggle-senha" onclick="toggleSenha('senha-login')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="lembrar-me">
                        <input type="checkbox" name="lembrar">
                        <span>Lembrar-me</span>
                    </label>
                    <a href="#" class="link-esqueci">Esqueci minha senha</a>
                </div>

                <button type="submit" class="btn-login">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <span>Entrar</span>
                </button>
            </form>

            <!-- FORM CADASTRO -->
            <form id="form-cadastro" class="formulario <?php echo $modo === 'cadastro' ? 'ativo' : ''; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="cadastro">

                <div class="foto-upload-container">
                    <div class="foto-preview-wrapper" onclick="document.getElementById('foto_perfil').click()">
                        <div class="foto-placeholder" id="foto-placeholder">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                            <span>Foto</span>
                        </div>
                    </div>
                    <input type="file" id="foto_perfil" name="foto_perfil" class="foto-upload-input" accept="image/*" onchange="previewFoto(this)">
                    <span class="foto-upload-label" onclick="document.getElementById('foto_perfil').click()">Escolher foto de perfil</span>
                </div>

                <div class="form-group">
                    <label for="nome-cadastro">Nome Completo</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <input type="text" id="nome-cadastro" name="nome" placeholder="Seu nome completo" required>
                    </div>
                </div>

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

                <div class="form-group">
                    <label for="data-nascimento">Data de Nascimento</label>
                    <div class="input-wrapper">
                        <input type="date" id="data-nascimento" name="data_nascimento" max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefone-cadastro">Telefone</label>
                    <div class="input-wrapper">
                        <input type="tel" id="telefone-cadastro" name="telefone" placeholder="(00) 00000-0000" maxlength="15">
                    </div>
                </div>

                <div class="form-group">
                    <label for="senha-cadastro">Senha</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="senha-cadastro" name="senha" placeholder="Minimo 6 caracteres" required minlength="6">
                        <button type="button" class="toggle-senha" onclick="toggleSenha('senha-cadastro')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

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

                <button type="submit" class="btn-login">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    <span>Criar Conta</span>
                </button>
            </form>

            <!-- Footer -->
            <div class="login-footer">
                <p>Voltar para <a href="index.html">pagina inicial</a></p>
            </div>
        </div>
    </div>

    <!-- Frase inferior -->
    <div class="login-bottom-quote">
        <p>"O primeiro passo para o bem-estar e dar-se permissao para cuidar de voce."</p>
    </div>

    <script>
    /* ═══════════════════════════════════════════════════════════
       PARTÍCULAS ANIMADAS
    ═══════════════════════════════════════════════════════════ */
    (function() {
        var canvas = document.getElementById('particles-canvas');
        var ctx = canvas.getContext('2d');
        var particles = [];
        var numParticles = 35;
        var mouse = { x: null, y: null };
        var lastMove = 0;

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resize();
        window.addEventListener('resize', resize);

        document.addEventListener('mousemove', function(e) {
            var now = Date.now();
            if (now - lastMove > 50) {
                mouse.x = e.clientX;
                mouse.y = e.clientY;
                lastMove = now;
            }
        });

        function Particle() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.size = Math.random() * 2.5 + 0.5;
            this.speedX = (Math.random() - 0.5) * 0.4;
            this.speedY = (Math.random() - 0.5) * 0.4;
            this.opacity = Math.random() * 0.4 + 0.1;
            var colors = ['128,161,212', '117,201,200', '192,185,221', '72,72,72'];
            this.color = colors[Math.floor(Math.random() * colors.length)];
        }

        Particle.prototype.update = function() {
            this.x += this.speedX;
            this.y += this.speedY;

            if (mouse.x !== null) {
                var dx = mouse.x - this.x;
                var dy = mouse.y - this.y;
                var dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 100) {
                    var force = (100 - dist) / 100;
                    this.x -= dx * force * 0.005;
                    this.y -= dy * force * 0.005;
                }
            }

            if (this.x < 0) this.x = canvas.width;
            if (this.x > canvas.width) this.x = 0;
            if (this.y < 0) this.y = canvas.height;
            if (this.y > canvas.height) this.y = 0;
        };

        Particle.prototype.draw = function() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(' + this.color + ',' + this.opacity + ')';
            ctx.fill();
        };

        for (var i = 0; i < numParticles; i++) {
            particles.push(new Particle());
        }

        function connectParticles() {
            for (var a = 0; a < particles.length; a++) {
                for (var b = a + 1; b < particles.length; b++) {
                    var dx = particles[a].x - particles[b].x;
                    var dy = particles[a].y - particles[b].y;
                    var dist = dx * dx + dy * dy;
                    if (dist < 8000) {
                        var opacity = (1 - dist / 8000) * 0.1;
                        ctx.beginPath();
                        ctx.strokeStyle = 'rgba(192,185,221,' + opacity + ')';
                        ctx.lineWidth = 0.4;
                        ctx.moveTo(particles[a].x, particles[a].y);
                        ctx.lineTo(particles[b].x, particles[b].y);
                        ctx.stroke();
                    }
                }
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (var i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
            }
            connectParticles();
            requestAnimationFrame(animate);
        }
        animate();
    })();

    /* ═══════════════════════════════════════════════════════════
       3D TILT SUTIL NO CARD
    ═══════════════════════════════════════════════════════════ */
    (function() {
        var card = document.getElementById('loginCard');
        var maxTilt = 2.5;
        var maxShift = 1;
        var rafId = null;
        var targetRotateX = 0;
        var targetRotateY = 0;

        card.addEventListener('mousemove', function(e) {
            var rect = card.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            var centerX = rect.width / 2;
            var centerY = rect.height / 2;
            targetRotateX = ((y - centerY) / centerY) * -maxTilt;
            targetRotateY = ((x - centerX) / centerX) * maxTilt;

            if (!rafId) {
                rafId = requestAnimationFrame(function() {
                    var shiftX = ((x - centerX) / centerX) * maxShift;
                    var shiftY = ((y - centerY) / centerY) * maxShift;
                    card.style.transform = 'perspective(1200px) rotateX(' + targetRotateX + 'deg) rotateY(' + targetRotateY + 'deg) translate(' + shiftX + 'px, ' + shiftY + 'px)';
                    rafId = null;
                });
            }
        });

        card.addEventListener('mouseleave', function() {
            card.style.transform = 'perspective(1200px) rotateX(0deg) rotateY(0deg) translate(0, 0)';
            card.style.transition = 'transform 0.5s cubic-bezier(.4,0,.2,1)';
        });

        card.addEventListener('mouseenter', function() {
            card.style.transition = 'transform 0.15s ease-out';
        });
    })();

    /* ═══════════════════════════════════════════════════════════
       RIPPLE NOS BOTÕES
    ═══════════════════════════════════════════════════════════ */
    document.querySelectorAll('.btn-login').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            var ripple = document.createElement('span');
            ripple.classList.add('ripple');
            var rect = btn.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 600);
        });
    });

    /* ═══════════════════════════════════════════════════════════
       MUDAR MODO (LOGIN / CADASTRO)
    ═══════════════════════════════════════════════════════════ */
    function mudarModo(modo) {
        var formLogin = document.getElementById('form-login');
        var formCadastro = document.getElementById('form-cadastro');
        var tabs = document.querySelectorAll('.tab-btn');
        var title = document.getElementById('form-title');
        var subtitle = document.getElementById('form-subtitle');

        var formAtual = modo === 'login' ? formCadastro : formLogin;
        var formNovo = modo === 'login' ? formLogin : formCadastro;

        formAtual.classList.add('saindo');
        setTimeout(function() {
            formAtual.classList.remove('ativo', 'saindo');
            formNovo.classList.add('ativo');

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
            window.history.replaceState({}, '', '?modo=' + modo);
        }, 250);
    }

    /* ═══════════════════════════════════════════════════════════
       TOGGLE SENHA
    ═══════════════════════════════════════════════════════════ */
    function toggleSenha(inputId) {
        var input = document.getElementById(inputId);
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    /* ═══════════════════════════════════════════════════════════
       PREVIEW FOTO
    ═══════════════════════════════════════════════════════════ */
    function previewFoto(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var wrapper = document.querySelector('.foto-preview-wrapper');
                wrapper.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       MÁSCARA TELEFONE
    ═══════════════════════════════════════════════════════════ */
    document.getElementById('telefone-cadastro')?.addEventListener('input', function(e) {
        var valor = e.target.value.replace(/\D/g, '');
        if (valor.length > 11) valor = valor.substring(0, 11);
        if (valor.length > 7) {
            valor = '(' + valor.substring(0, 2) + ') ' + valor.substring(2, 7) + '-' + valor.substring(7);
        } else if (valor.length > 2) {
            valor = '(' + valor.substring(0, 2) + ') ' + valor.substring(2);
        }
        e.target.value = valor;
    });

    /* ═══════════════════════════════════════════════════════════
       VALIDAÇÃO CADASTRO
    ═══════════════════════════════════════════════════════════ */
    document.getElementById('form-cadastro').addEventListener('submit', function(e) {
        var senha = document.getElementById('senha-cadastro').value;
        var confirmar = document.getElementById('confirmar-senha').value;
        var telefone = document.getElementById('telefone-cadastro').value;
        var dataNascimento = document.getElementById('data-nascimento').value;

        if (senha !== confirmar) {
            e.preventDefault();
            alert('As senhas nao correspondem!');
            return false;
        }
        if (senha.length < 6) {
            e.preventDefault();
            alert('A senha deve ter no minimo 6 caracteres!');
            return false;
        }
        if (telefone && telefone.replace(/\D/g, '').length < 10) {
            e.preventDefault();
            alert('Telefone invalido! Use o formato (XX) XXXXX-XXXX');
            return false;
        }
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