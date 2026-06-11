<div class="secao">
    <h2>Notificações</h2>

    <?php if (count($notificacoes) > 0): ?>
        <div class="notificacoes-lista">
            <?php foreach ($notificacoes as $notif): ?>
                <div class="notificacao-item <?php echo $notif['lida'] ? 'lida' : 'nao-lida'; ?> <?php echo $notif['tipo']; ?>">
                    <div class="notificacao-icone">
                        <?php
                        $icone = '';
                        switch ($notif['tipo']) {
                            case 'nova_consulta':
                                $icone = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
                                break;
                            case 'cancelamento':
                                $icone = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
                                break;
                            case 'pagamento':
                                $icone = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>';
                                break;
                            default:
                                $icone = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>';
                        }
                        echo $icone;
                        ?>
                    </div>
                    <div class="notificacao-conteudo">
                        <h4><?php echo htmlspecialchars(formatar_tipo_notificacao($notif['tipo'])); ?></h4>
                        <p><?php echo htmlspecialchars($notif['mensagem']); ?></p>
                        <span class="notificacao-data">
                            <?php
                            $data = new DateTime($notif['data_criacao']);
                            $agora = new DateTime();
                            $diff = $agora->diff($data);

                            if ($diff->days == 0) {
                                if ($diff->h == 0) {
                                    echo $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '') . ' atrás';
                                } else {
                                    echo $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
                                }
                            } elseif ($diff->days == 1) {
                                echo 'Ontem';
                            } else {
                                echo $data->format('d/m/Y');
                            }
                            ?>
                        </span>
                    </div>
                    <?php if (!$notif['lida']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="acao" value="marcar_lida">
                            <input type="hidden" name="id_notificacao" value="<?php echo $notif['id_notificacao']; ?>">
                            <button type="submit" class="btn btn-pequeno" style="padding: 4px 8px; font-size: 10px;">Marcar como lida</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="vazio-container">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <p>Você não tem notificações no momento.</p>
        </div>
    <?php endif; ?>
</div>
