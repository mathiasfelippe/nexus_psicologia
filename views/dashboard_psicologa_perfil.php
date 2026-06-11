<?php
$especializacoes = obter_especializacoes($pdo);
?>

<div class="secao">
    <h2>Meu Perfil</h2>

    <form method="POST" class="formulario-perfil" style="background: white; padding: 32px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <input type="hidden" name="acao" value="atualizar_perfil">

        <div class="perfil-header" style="display: flex; align-items: center; gap: 24px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid #f3f4f6;">
            <div class="perfil-avatar">
                <div class="avatar-grande" style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 32px; font-weight: 700; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);"><?php echo strtoupper(substr($psicologa['nome'], 0, 1)); ?></div>
            </div>
            <div class="perfil-basico">
                <h3 style="font-size: 24px; font-weight: 700; color: #111827; margin: 0;"><?php echo htmlspecialchars($psicologa['nome']); ?></h3>
                <p style="color: #6b7280; margin: 4px 0 0 0;"><?php echo htmlspecialchars($psicologa['email']); ?></p>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="nome">Nome Completo *</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($psicologa['nome']); ?>" required>
            </div>

            <div class="form-group">
                <label for="crp">CRP</label>
                <input type="text" id="crp" name="crp" value="<?php echo htmlspecialchars($psicologa['crp'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email (não editável)</label>
                <input type="email" id="email" value="<?php echo htmlspecialchars($psicologa['email']); ?>" disabled>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="telefone">Telefone</label>
                <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($psicologa['telefone'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="bio">Biografia Profissional</label>
            <textarea id="bio" name="bio" rows="5" placeholder="Conte um pouco sobre sua experiência e abordagem terapêutica..."><?php echo htmlspecialchars($psicologa['bio'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            Salvar Alterações
        </button>
    </form>

    <div class="info-box">
        <h3>Informações da Conta</h3>
        <ul>
            <li><strong>Email:</strong> <?php echo htmlspecialchars($psicologa['email']); ?></li>
            <li><strong>Membro desde:</strong> <?php echo formatar_data(substr($psicologa['data_criacao'], 0, 10)); ?></li>
            <li><strong>Status:</strong> Ativo</li>
        </ul>
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
</style>

<script>
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
