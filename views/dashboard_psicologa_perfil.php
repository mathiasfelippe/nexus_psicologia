<?php
/*
 * ARQUIVO: views/dashboard_psicologa_perfil.php
 * DESCRIÇÃO: View da aba "Meu Perfil" do dashboard da psicóloga.
 *
 * Este arquivo é incluído pelo dashboard_psicologa.php quando a aba 'perfil'
 * está ativa. Ele exibe e permite editar os dados pessoais da psicóloga,
 * incluindo foto de perfil, nome, CRP, telefone e bio.
 * Também oferece uma seção de segurança para alteração de senha.
 *
 * AÇÕES POST GERADAS:
 *   - atualizar_perfil → Salva nome, CRP, telefone, bio e foto
 *   - alterar_senha    → Atualiza a senha com verificação da atual
 *
 * DEPENDÊNCIAS:
 *   - $pdo: conexão com o banco de dados (herdada do arquivo pai)
 *   - $psicologa: array com dados da psicóloga (herdado do arquivo pai)
 *   - obter_especializacoes(), formatar_data(): funções de config/funcoes.php
 */

// Carrega as especializações (não usadas diretamente nesta view, mas disponíveis)
$especializacoes = obter_especializacoes($pdo);
?>

<!-- ═══════════════════════════════════════════════════════════
     FORMULÁRIO DE EDIÇÃO DE PERFIL
     Permite atualizar foto, nome, CRP, telefone e bio
═══════════════════════════════════════════════════════════ -->
<div class="secao">
    <h2>Meu Perfil</h2>

    <!-- enctype="multipart/form-data" é obrigatório para envio de arquivos (foto) -->
    <form method="POST" enctype="multipart/form-data" class="card-white" style="padding: 32px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <!-- Campo oculto que identifica a ação para o PHP -->
        <input type="hidden" name="acao" value="atualizar_perfil">

        <!-- ── Cabeçalho do Perfil: avatar + nome + email + CRP ── -->
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid #f3f4f6;">
            <div style="position: relative;">
                <!-- Container do avatar: exibe foto ou inicial do nome -->
                <div id="foto-preview" style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 32px; font-weight: 700; flex-shrink: 0; overflow: hidden; cursor: pointer;">
                    <?php if (!empty($psicologa['foto_perfil'])): ?>
                        <!-- Se tiver foto cadastrada, exibe a imagem -->
                        <img src="<?php echo htmlspecialchars($psicologa['foto_perfil']); ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <!-- Caso contrário, exibe a inicial do nome em maiúsculo -->
                        <span><?php echo strtoupper(substr($psicologa['nome'], 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <!-- Input de arquivo oculto: ativado pelo label abaixo (ícone de lápis) -->
                <!-- onchange: chama previewFotoPerfil() para mostrar a nova foto antes de salvar -->
                <input type="file" id="foto_perfil" name="foto_perfil" class="foto-upload-input" accept="image/*" style="display: none;" onchange="previewFotoPerfil(this)">
                <!-- Label visível como botão de edição (ícone de lápis sobre o avatar) -->
                <!-- for="foto_perfil" conecta o label ao input de arquivo -->
                <label for="foto_perfil" style="position: absolute; bottom: 0; right: 0; background: #6366f1; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid white; font-size: 14px;" title="Alterar foto">✎</label>
            </div>
            <div>
                <!-- Nome da psicóloga (grande, em destaque) -->
                <h3 style="font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 4px 0;"><?php echo htmlspecialchars($psicologa['nome']); ?></h3>
                <!-- Email (somente leitura, não editável) -->
                <p style="color: #6b7280; margin: 0; font-size: 14px;"><?php echo htmlspecialchars($psicologa['email']); ?></p>
                <?php if (!empty($psicologa['crp'])): ?>
                    <!-- CRP exibido em roxo se cadastrado -->
                    <p style="color: #6366f1; margin: 4px 0 0 0; font-size: 13px; font-weight: 600;">CRP: <?php echo htmlspecialchars($psicologa['crp']); ?></p>
                <?php endif; ?>
                <p style="color: #9ca3af; margin: 4px 0 0 0; font-size: 12px;">Clique na foto para alterar</p>
            </div>
        </div>

        <!-- ── Linha 1: Nome e CRP (dois campos lado a lado) ── -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group">
                <label for="nome" style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Nome Completo *</label>
                <!-- required: campo obrigatório (validação nativa do HTML5) -->
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($psicologa['nome']); ?>" required style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; box-sizing:border-box;">
            </div>
            <div class="form-group">
                <label for="crp" style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">CRP</label>
                <!-- O operador ?? retorna string vazia se crp for null -->
                <input type="text" id="crp" name="crp" value="<?php echo htmlspecialchars($psicologa['crp'] ?? ''); ?>" placeholder="Ex: 06/12345" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; box-sizing:border-box;">
            </div>
        </div>

        <!-- ── Linha 2: Telefone e Email (dois campos lado a lado) ── -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group">
                <label for="telefone" style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Telefone</label>
                <!-- type="tel" ativa o teclado numérico em dispositivos móveis -->
                <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($psicologa['telefone'] ?? ''); ?>" placeholder="(00) 00000-0000" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; box-sizing:border-box;">
            </div>
            <div class="form-group">
                <label style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Email (não editável)</label>
                <!-- disabled: campo somente leitura, não enviado no formulário -->
                <!-- Fundo cinza e texto acinzentado indicam visualmente que não é editável -->
                <input type="email" value="<?php echo htmlspecialchars($psicologa['email']); ?>" disabled style="width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; background:#f9fafb; color:#9ca3af; box-sizing:border-box;">
            </div>
        </div>

        <!-- ── Biografia Profissional ── -->
        <div class="form-group" style="margin-bottom: 24px;">
            <label for="bio" style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Biografia Profissional</label>
            <!-- resize:vertical permite ao usuário redimensionar apenas verticalmente -->
            <!-- O conteúdo entre as tags é o valor atual da bio -->
            <textarea id="bio" name="bio" rows="5" placeholder="Conte um pouco sobre sua experiência e abordagem terapêutica..." style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; resize:vertical; box-sizing:border-box;"><?php echo htmlspecialchars($psicologa['bio'] ?? ''); ?></textarea>
        </div>

        <!-- Botão de salvar alinhado à direita -->
        <div style="display:flex; justify-content:flex-end;">
            <button type="submit" class="btn btn-primary" style="display:flex; align-items:center; gap:8px;">
                <!-- Ícone de disquete (salvar) em SVG -->
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                Salvar Alterações
            </button>
        </div>
    </form>

    <!-- ── Informações da Conta (somente leitura) ── -->
    <div class="card-white" style="border-radius: 16px; padding: 24px; 
box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 24px;">
        <h3 style="font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 16px;">Informações da Conta</h3>
        <!-- Grid responsivo com colunas mínimas de 200px -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
            <div>
                <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Email</p>
                <p style="font-size: 14px; font-weight: 600; color: #111827; margin: 0;"><?php echo htmlspecialchars($psicologa['email']); ?></p>
            </div>
            <div>
                <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Membro desde</p>
                <!-- formatar_data() converte 'YYYY-MM-DD' para 'dd/mm/YYYY' -->
                <!-- substr(..., 0, 10) extrai apenas a parte da data (sem hora) -->
                <p style="font-size: 14px; font-weight: 600; color: #111827; margin: 0;"><?php echo formatar_data(substr($psicologa['data_criacao'], 0, 10)); ?></p>
            </div>
            <div>
                <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Status</p>
                <!-- Badge verde indicando conta ativa -->
                <span style="display:inline-block; background:#d1fae5; color:#065f46; padding:2px 10px; border-radius:20px; font-size:13px; font-weight:600;">Ativo</span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     SEÇÃO DE SEGURANÇA (alteração de senha)
═══════════════════════════════════════════════════════════ -->
<div class="secao-seguranca">
    <h3>Segurança</h3>
    <!-- Botão que abre o modal de alteração de senha -->
    <button type="button" class="btn btn-outline" onclick="abrirAlterarSenha()">
        <!-- Ícone de cadeado em SVG -->
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        Alterar Senha
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL DE ALTERAÇÃO DE SENHA
     Janela flutuante para trocar a senha da psicóloga
═══════════════════════════════════════════════════════════ -->
<!-- display:none por padrão; exibido por abrirAlterarSenha() -->
<div id="modal-senha" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Alterar Senha</h2>
            <!-- Botão X: chama fecharModal com o ID do modal -->
            <button class="modal-fechar" onclick="fecharModal('modal-senha')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Formulário enviado via POST para acao=alterar_senha no controlador -->
            <form id="form-alterar-senha" method="POST">
                <!-- Campo oculto que identifica a ação para o PHP -->
                <input type="hidden" name="acao" value="alterar_senha">

                <!-- Senha atual: necessária para verificar identidade antes de alterar -->
                <div class="form-group">
                    <label for="senha-atual">Senha Atual *</label>
                    <!-- type="password" oculta os caracteres digitados -->
                    <input type="password" id="senha-atual" name="senha_atual" required>
                </div>

                <!-- Nova senha desejada -->
                <div class="form-group">
                    <label for="nova-senha">Nova Senha *</label>
                    <input type="password" id="nova-senha" name="nova_senha" required>
                </div>

                <!-- Confirmação da nova senha (deve ser idêntica ao campo anterior) -->
                <div class="form-group">
                    <label for="confirmar-nova-senha">Confirmar Nova Senha *</label>
                    <input type="password" id="confirmar-nova-senha" name="confirmar_nova_senha" required>
                </div>

                <!-- btn-bloco: botão de largura total -->
                <button type="submit" class="btn btn-primary btn-bloco">Atualizar Senha</button>
            </form>
        </div>
    </div>
</div>

<style>
/* ── Seção de segurança: separador superior ── */
.secao-seguranca {
    margin-top: 30px;
    padding-top: 24px;
    border-top: 1px solid #e0e0e0;
}

/* ── Modal: overlay de fundo escuro ── */
/* display:none por padrão; flex quando aberto (centraliza o conteúdo) */
.modal {
    display: none;
    position: fixed; /* Fixo na tela, não rola com o conteúdo */
    z-index: 1000;   /* Acima de todos os outros elementos */
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5); /* Overlay semi-transparente */
    align-items: center;
    justify-content: center;
}

/* ── Conteúdo do modal: caixa branca centralizada ── */
.modal-conteudo {
    background-color: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;   /* Não ultrapassa 90% da altura da tela */
    overflow-y: auto;   /* Scroll interno se o conteúdo for muito alto */
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

/* ── Cabeçalho do modal: título + botão X ── */
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
}

.modal-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

/* ── Botão X de fechar o modal ── */
.modal-fechar {
    background: none;
    border: none;
    font-size: 24px;
    color: #999;
    cursor: pointer;
    transition: color 0.3s ease;
}

/* Hover: escurece o X ao passar o mouse */
.modal-fechar:hover {
    color: #333;
}

/* ── Corpo do modal ── */
.modal-body {
    padding: 20px;
}

/* ── Botão de contorno (outline) ── */
.btn-outline {
    background-color: white;
    color: #667eea;
    border: 1px solid #667eea;
}

/* Hover: fundo levemente azulado */
.btn-outline:hover {
    background-color: #f0f4ff;
}

/* ── DARK MODE: Overrides para o tema escuro ── */
body.dark-mode .secao-seguranca {
    border-top-color: rgba(255,255,255,.1);
}
body.dark-mode .modal-conteudo {
    background: rgba(30,30,50,.95);
    border-color: rgba(255,255,255,.1);
}
body.dark-mode .modal-header {
    border-bottom-color: rgba(255,255,255,.08);
}
body.dark-mode .modal-header h2 {
    color: var(--branco);
}
body.dark-mode .modal-fechar {
    color: rgba(255,255,255,.5);
}
body.dark-mode .modal-fechar:hover {
    color: rgba(255,255,255,.8);
}
body.dark-mode .btn-outline {
    background-color: transparent;
    color: var(--azul-sereno);
    border-color: var(--azul-sereno);
}
body.dark-mode .btn-outline:hover {
    background-color: rgba(128,161,212,.15);
}
</style>

<script>
    /*
     * Exibe uma pré-visualização da nova foto de perfil antes de salvar.
     * Chamada pelo evento onchange do input de arquivo.
     *
     * @param {HTMLInputElement} input - O input de arquivo que disparou o evento
     */
    function previewFotoPerfil(input) {
        // Verifica se um arquivo foi selecionado
        if (input.files && input.files[0]) {
            // FileReader lê o arquivo localmente (sem enviar ao servidor)
            const reader = new FileReader();
            // Callback executado quando a leitura termina
            reader.onload = function(e) {
                const preview = document.getElementById('foto-preview');
                // Substitui o conteúdo do avatar pela imagem selecionada
                // e.target.result contém a imagem como Data URL (base64)
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">';
            };
            // Inicia a leitura do arquivo como Data URL (base64)
            reader.readAsDataURL(input.files[0]);
        }
    }

    /*
     * Abre o modal de alteração de senha.
     * Usa display:flex para centralizar o conteúdo.
     */
    function abrirAlterarSenha() {
        document.getElementById('modal-senha').style.display = 'flex';
    }

    /*
     * Fecha um modal pelo seu ID.
     * Função genérica reutilizável para qualquer modal desta view.
     *
     * @param {string} id - O ID do elemento modal a ser fechado
     */
    function fecharModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Fecha o modal de senha ao clicar fora da área de conteúdo (no overlay)
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('modal-senha');
        // event.target === modal: o clique foi no overlay, não no conteúdo
        if (modal && event.target === modal) {
            modal.style.display = 'none';
        }
    });
</script>
