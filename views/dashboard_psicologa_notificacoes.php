<div class="secao">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin-bottom: 0;">Notificacoes</h2>
        <div class="notificacoes-header-acoes" style="display: flex; gap: 8px;">
            <button class="btn btn-pequeno btn-secondary" onclick="notif.marcarTodasLidas()" id="btn-marcar-todas" style="display: none;">
                Marcar todas como lidas
            </button>
            <button class="btn btn-pequeno btn-secondary" onclick="notif.recarregar(true)" title="Atualizar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                Atualizar
            </button>
        </div>
    </div>

    <div id="notificacoes-container">
        <div class="vazio-container">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <p>Carregando notificacoes...</p>
        </div>
    </div>
</div>

<script>
var notif = {
    container: null,
    btnMarcarTodas: null,

    init: function() {
        this.container = document.getElementById('notificacoes-container');
        this.btnMarcarTodas = document.getElementById('btn-marcar-todas');
        this.recarregar();
    },

    recarregar: function(forcar) {
        var self = this;
        self.container.innerHTML = '<div class="vazio-container"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><p>Carregando...</p></div>';
        fetch('api/notificacoes.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                self.renderizar(data.notificacoes, data.nao_lidas);
                if (forcar) self.atualizarBadge(data.nao_lidas);
            })
            .catch(function() {
                self.container.innerHTML = '<div class="vazio-container"><p>Erro ao carregar notificacoes.</p></div>';
            });
    },

    renderizar: function(lista, naoLidas) {
        var self = this;
        if (!lista || lista.length === 0) {
            this.container.innerHTML = '<div class="vazio-container"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><p>Voce nao tem notificacoes no momento.</p></div>';
            this.btnMarcarTodas.style.display = 'none';
            return;
        }

        this.btnMarcarTodas.style.display = naoLidas > 0 ? 'inline-flex' : 'none';

        var html = '<div class="notificacoes-lista">';
        for (var i = 0; i < lista.length; i++) {
            var n = lista[i];
            var lidaClass = n.lida == 1 ? 'lida' : 'nao-lida';
            var icone = self.getIcone(n.tipo);
            var tipoLabel = self.getTipoLabel(n.tipo);
            var tempo = self.formatarTempo(n.data_criacao);

            html += '<div class="notificacao-item ' + lidaClass + ' ' + n.tipo + '" data-id="' + n.id_notificacao + '">';
            html += '<div class="notificacao-icone">' + icone + '</div>';
            html += '<div class="notificacao-conteudo">';
            html += '<h4>' + self.esc(tipoLabel) + '</h4>';
            html += '<p>' + self.esc(n.mensagem) + '</p>';
            html += '<span class="notificacao-data">' + tempo + '</span>';
            html += '</div>';
            html += '<div class="notificacao-acoes" style="display: flex; gap: 4px; flex-shrink: 0; align-items: center;">';
            if (n.lida == 0) {
                html += '<button class="btn btn-pequeno" style="padding: 4px 8px; font-size: 10px;" onclick="notif.marcarLida(' + n.id_notificacao + ', this)">Ler</button>';
            }
            html += '<button class="btn btn-pequeno" style="padding: 4px 8px; font-size: 10px; background: transparent; border: 1px solid var(--danger); color: var(--danger);" onclick="notif.excluir(' + n.id_notificacao + ', this)">';
            html += '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
            html += '</button>';
            html += '</div>';
            html += '</div>';
        }
        html += '</div>';
        this.container.innerHTML = html;
    },

    marcarLida: function(id, btn) {
        var self = this;
        var formData = new FormData();
        formData.append('acao', 'marcar_lida');
        formData.append('id_notificacao', id);

        fetch('api/notificacoes.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.sucesso) {
                    var item = document.querySelector('.notificacao-item[data-id="' + id + '"]');
                    if (item) {
                        item.classList.remove('nao-lida');
                        item.classList.add('lida');
                        if (btn) btn.remove();
                    }
                    self.atualizarBadge(self._getBadgeValue() - 1);
                }
            })
            .catch(function(err) { console.error('Erro:', err); });
    },

    marcarTodasLidas: function() {
        var self = this;
        var formData = new FormData();
        formData.append('acao', 'marcar_todas_lidas');

        fetch('api/notificacoes.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.sucesso) {
                    document.querySelectorAll('.notificacao-item.nao-lida').forEach(function(item) {
                        item.classList.remove('nao-lida');
                        item.classList.add('lida');
                        var btn = item.querySelector('button[onclick*="marcarLida"]');
                        if (btn) btn.remove();
                    });
                    self.btnMarcarTodas.style.display = 'none';
                    self._atualizarBadgeNum(0);
                }
            })
            .catch(function(err) { console.error('Erro:', err); });
    },

    excluir: function(id, btn) {
        if (!confirm('Tem certeza que deseja excluir esta notificacao?')) return;
        var self = this;
        var formData = new FormData();
        formData.append('acao', 'excluir');
        formData.append('id_notificacao', id);

        fetch('api/notificacoes.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.sucesso) {
                    var item = document.querySelector('.notificacao-item[data-id="' + id + '"]');
                    if (item) {
                        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(30px)';
                        setTimeout(function() { item.remove(); self.verificarVazio(); }, 300);
                    }
                    self.atualizarBadge(self._getBadgeValue() - (item && item.classList.contains('nao-lida') ? 1 : 0));
                }
            })
            .catch(function(err) { console.error('Erro:', err); });
    },

    verificarVazio: function() {
        var items = this.container.querySelectorAll('.notificacao-item');
        if (items.length === 0) {
            this.container.innerHTML = '<div class="vazio-container"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><p>Voce nao tem notificacoes no momento.</p></div>';
            this.btnMarcarTodas.style.display = 'none';
        }
    },

    _getBadgeValue: function() {
        var badge = document.querySelector('.badge');
        return badge ? parseInt(badge.textContent, 10) || 0 : 0;
    },

    atualizarBadge: function(total) {
        this._atualizarBadgeNum(Math.max(0, total));
    },

    _atualizarBadgeNum: function(total) {
        var badges = document.querySelectorAll('.badge');
        badges.forEach(function(b) {
            b.textContent = total;
            b.style.display = total > 0 ? 'inline-flex' : 'none';
        });
    },

    getIcone: function(tipo) {
        var map = {
            'agendamento': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
            'nova_consulta': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>',
            'confirmacao': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            'cancelamento': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
            'reagendamento': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
            'pagamento': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>',
            'pagamento_aprovado': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line><path d="M9 12l2 2 4-4"></path></svg>',
            'pagamento_recusado': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>',
            'consulta_concluida': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            'lembrete': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>',
            'comentario_psicologa': '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>'
        };
        return map[tipo] || '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
    },

    getTipoLabel: function(tipo) {
        var map = {
            'nova_consulta': 'Nova Consulta',
            'agendamento': 'Consulta Agendada',
            'confirmacao': 'Consulta Confirmada',
            'cancelamento': 'Consulta Cancelada',
            'reagendamento': 'Consulta Reagendada',
            'pagamento': 'Pagamento Confirmado',
            'pagamento_aprovado': 'Pagamento Aprovado',
            'pagamento_recusado': 'Pagamento Recusado',
            'consulta_concluida': 'Consulta Concluida',
            'alteracao_data': 'Alteracao de Data',
            'alteracao_horario': 'Alteracao de Horario',
            'lembrete': 'Lembrete',
            'comentario_psicologa': 'Recado da Psicologa'
        };
        return map[tipo] || 'Notificacao';
    },

    formatarTempo: function(dataCriacao) {
        if (!dataCriacao) return '';
        var partes = dataCriacao.split(/[\s:-]/);
        var data = new Date(partes[0], partes[1]-1, partes[2], partes[3]||0, partes[4]||0, partes[5]||0);
        var agora = new Date();
        var diffMs = agora - data;
        var diffMin = Math.floor(diffMs / 60000);
        var diffHoras = Math.floor(diffMs / 3600000);
        var diffDias = Math.floor(diffMs / 86400000);

        if (diffMin < 1) return 'Agora mesmo';
        if (diffMin < 60) return diffMin + ' min' + (diffMin > 1 ? 's' : '') + ' atras';
        if (diffHoras < 24) return diffHoras + ' hora' + (diffHoras > 1 ? 's' : '') + ' atras';
        if (diffDias === 1) return 'Ontem as ' + data.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
        return data.toLocaleDateString('pt-BR') + ' as ' + data.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
    },

    esc: function(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', function() { notif.init(); });
</script>
