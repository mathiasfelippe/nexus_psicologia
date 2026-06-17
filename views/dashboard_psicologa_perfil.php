<?php
$especializacoes = obter_especializacoes($pdo);
?>

<div class="secao">
    <h2>Meu Perfil</h2>

    <form method="POST" enctype="multipart/form-data" class="card-white" style="padding: 32px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <input type="hidden" name="acao" value="atualizar_perfil">

        <!-- Cabeçalho do Perfil -->
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid #f3f4f6;">
            <div style="position: relative;">
                <div id="foto-preview" style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 32px; font-weight: 700; flex-shrink: 0; overflow: hidden; cursor: pointer;">
                    <?php if (!empty($psicologa['foto_perfil'])): ?>
                        <img src="<?php echo htmlspecialchars($psicologa['foto_perfil']); ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span><?php echo strtoupper(substr($psicologa['nome'], 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <input type="file" id="foto_perfil" name="foto_perfil" class="foto-upload-input" accept="image/*" style="display: none;" onchange="previewFotoPerfil(this)">
                <label for="foto_perfil" style="position: absolute; bottom: 0; right: 0; background: #6366f1; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid white; font-size: 14px;" title="Alterar foto">✎</label>
            </div>
            <div>
                <h3 style="font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 4px 0;"><?php echo htmlspecialchars($psicologa['nome']); ?></h3>
                <p style="color: #6b7280; margin: 0; font-size: 14px;"><?php echo htmlspecialchars($psicologa['email']); ?></p>
                <?php if (!empty($psicologa['crp'])): ?>
                    <p style="color: #6366f1; margin: 4px 0 0 0; font-size: 13px; font-weight: 600;">CRP: <?php echo htmlspecialchars($psicologa['crp']); ?></p>
                <?php endif; ?>
                <p style="color: #9ca3af; margin: 4px 0 0 0; font-size: 12px;">Clique na foto para alterar</p>
            </div>
        </div>

        <!-- Linha 1: Nome e CRP -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group">
                <label for="nome" style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Nome Completo *</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($psicologa['nome']); ?>" required style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; box-sizing:border-box;">
            </div>
            <div class="form-group">
                <label for="crp" style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">CRP</label>
                <input type="text" id="crp" name="crp" value="<?php echo htmlspecialchars($psicologa['crp'] ?? ''); ?>" placeholder="Ex: 06/12345" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; box-sizing:border-box;">
            </div>
        </div>

        <!-- Linha 2: Telefone e Email -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group">
                <label for="telefone" style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Telefone</label>
                <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($psicologa['telefone'] ?? ''); ?>" placeholder="(00) 00000-0000" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; box-sizing:border-box;">
            </div>
            <div class="form-group">
                <label style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Email (não editável)</label>
                <input type="email" value="<?php echo htmlspecialchars($psicologa['email']); ?>" disabled style="width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; background:#f9fafb; color:#9ca3af; box-sizing:border-box;">
            </div>
        </div>

        <!-- Biografia -->
        <div class="form-group" style="margin-bottom: 24px;">
            <label for="bio" style="display:block; font-weight:600; margin-bottom:6px; color:#374151;">Biografia Profissional</label>
            <textarea id="bio" name="bio" rows="5" placeholder="Conte um pouco sobre sua experiência e abordagem terapêutica..." style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; resize:vertical; box-sizing:border-box;"><?php echo htmlspecialchars($psicologa['bio'] ?? ''); ?></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end;">
            <button type="submit" class="btn btn-primary" style="display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                Salvar Alterações
            </button>
        </div>
    </form>

    <!-- Informações da Conta -->
    <div class="card-white" style="border-radius: 16px; padding: 24px; 
box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 24px;">
        <h3 style="font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 16px;">Informações da Conta</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
            <div>
                <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Email</p>
                <p style="font-size: 14px; font-weight: 600; color: #111827; margin: 0;"><?php echo htmlspecialchars($psicologa['email']); ?></p>
            </div>
            <div>
                <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Membro desde</p>
                <p style="font-size: 14px; font-weight: 600; color: #111827; margin: 0;"><?php echo formatar_data(substr($psicologa['data_criacao'], 0, 10)); ?></p>
            </div>
            <div>
                <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Status</p>
                <span style="display:inline-block; background:#d1fae5; color:#065f46; padding:2px 10px; border-radius:20px; font-size:13px; font-weight:600;">Ativo</span>
            </div>
        </div>
    </div>
</div>

<!-- Seção de Segurança -->
<div class="secao-seguranca">
    <h3>Segurança</h3>
    <button type="button" class="btn btn-outline" onclick="abrirAlterarSenha()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        Alterar Senha
    </button>
</div>

<!-- Modal de Alterar Senha -->
<div id="modal-senha" class="modal">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>Alterar Senha</h2>
            <button class="modal-fechar" onclick="fecharModal('modal-senha')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="form-alterar-senha" method="POST">
                <input type="hidden" name="acao" value="alterar_senha">

                <div class="form-group">
                    <label for="senha-atual">Senha Atual *</label>
                    <input type="password" id="senha-atual" name="senha_atual" required>
                </div>

                <div class="form-group">
                    <label for="nova-senha">Nova Senha *</label>
                    <input type="password" id="nova-senha" name="nova_senha" required>
                </div>

                <div class="form-group">
                    <label for="confirmar-nova-senha">Confirmar Nova Senha *</label>
                    <input type="password" id="confirmar-nova-senha" name="confirmar_nova_senha" required>
                </div>

                <button type="submit" class="btn btn-primary btn-bloco">Atualizar Senha</button>
            </form>
        </div>
    </div>
</div>

<style>
.secao-seguranca {
    margin-top: 30px;
    padding-top: 24px;
    border-top: 1px solid #e0e0e0;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
}

.modal-conteudo {
    background-color: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

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

.modal-fechar {
    background: none;
    border: none;
    font-size: 24px;
    color: #999;
    cursor: pointer;
    transition: color 0.3s ease;
}

.modal-fechar:hover {
    color: #333;
}

.modal-body {
    padding: 20px;
}

.btn-outline {
    background-color: white;
    color: #667eea;
    border: 1px solid #667eea;
}

.btn-outline:hover {
    background-color: #f0f4ff;
}

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
function previewFotoPerfil(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('foto-preview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function abrirAlterarSenha() {
    document.getElementById('modal-senha').style.display = 'flex';
}

function fecharModal(id) {
    document.getElementById(id).style.display = 'none';
}

document.addEventListener('click', function(event) {
    const modal = document.getElementById('modal-senha');
    if (modal && event.target === modal) {
        modal.style.display = 'none';
    }
});
</script>
