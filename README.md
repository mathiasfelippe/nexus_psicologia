# 🧠 Nexus Psicologia

<p align="center">
  <img src="Layout_NexusPsicologia_2026.jpg" alt="Preview do Projeto Nexus Psicologia" width="100%">
</p>

## 💻 Sobre o Projeto

O **Nexus Psicologia** é um sistema dinâmico desenvolvido para otimizar o agendamento e a gestão de consultas clínicas. O objetivo principal é unificar a experiência do paciente e do administrador em uma plataforma fluida, permitindo a marcação de sessões, controlo de horários e visualização de disponibilidade em tempo real.

Este projeto foi consolidado como parte do currículo do curso **Técnico em Desenvolvimento de Sistemas** na Escola **SENAI A. Jacob Lafer**.

---

## ⚙️ Funcionalidades do Sistema

A aplicação foi projetada para resolver problemas reais de gestão clínica através de recursos essenciais:

**Agendamento Inteligente:** Escolha dinâmica de horários disponíveis, evitando conflitos de marcação.
**Painel Administrativo (Dashboard):** Área restrita para controle de sessões, triagem e relatórios básicos.
**Interface Fluida & Assíncrona:** Atualização de dados da agenda instantaneamente sem a necessidade de recarregar a página.
**Design Responsivo:** Adaptabilidade total para telemóveis, tablets e computadores usando a metodologia Mobile First.

---

## 🛠 Stack Técnica

Unificamos as ferramentas utilizadas e os conceitos aplicados em cada camada da aplicação para tornar a leitura mais direta:

### **Back-end & Infraestrutura**
![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white) — Lógica de negócios, processamento de formulários, autenticação de utilizadores e controlo seguro de sessões.
![SQLite](https://img.shields.io/badge/sqlite-%23003B57.svg?style=for-the-badge&logo=sqlite&logoColor=white) — Banco de dados relacional embutido, sem necessidade de servidor externo.
![Git](https://img.shields.io/badge/git-%23F05033.svg?style=for-the-badge&logo=git&logoColor=white) — Gestão de código e controlo de versão através do GitHub Desktop.

### **Front-end & Interatividade**
![JavaScript](https://img.shields.io/badge/javascript-%23323330.svg?style=for-the-badge&logo=javascript&logoColor=%23F7DF1E) — Desenvolvimento com JavaScript Avançado, utilizando requisições assíncronas (Fetch API/AJAX) para atualizar a interface em tempo real.
![HTML5](https://img.shields.io/badge/html5-%23E34F26.svg?style=for-the-badge&logo=html5&logoColor=white) — Estruturação semântica focada em acessibilidade e boas práticas de arquitetura web (SEO).
![CSS3](https://img.shields.io/badge/css3-%231572B6.svg?style=for-the-badge&logo=css3&logoColor=white) — Estilização moderna e layouts flexíveis com Flexbox e CSS Grid.

---

## 📂 Estrutura de Pastas

```bash
NexusPsicologia/
├── api/                    # Endpoints JSON para requisições AJAX
│   ├── consultas_paciente.php
│   ├── consultas_psicologa.php
│   ├── horarios_disponiveis.php
│   └── notificacoes.php
├── assets/                 # Recursos visuais (Logos, ícones e imagens)
├── components/             # Componentes HTML da landing page
├── config/                 # Configurações e lógica de negócio
│   ├── conexao.php         # Conexão PDO com SQLite
│   ├── funcoes.php         # Funções de negócio (CRUD, notificações, etc.)
│   ├── gerar_datas.php     # Geração de datas úteis
│   └── inicializar_datas.php
├── css/                    # Folhas de estilo
│   ├── global.css          # Variáveis CSS, reset, tipografia
│   ├── navbar.css, hero.css, about.css, stats.css
│   ├── specializations.css, cta.css, footer.css
│   ├── login.css           # Tela de autenticação
│   └── dashboards.css      # Dashboard (glassmorphism + dark mode)
├── js/                     # Scripts JavaScript
│   ├── main.js             # Landing page
│   ├── dashboard_paciente.js
│   └── dashboard_psicologa.js   # Dashboard da psicóloga
├── pages/                  # Páginas institucionais e artigos
├── views/                  # Views dos dashboards (incluídas via PHP)
├── uploads/fotos/          # Fotos de perfil dos usuários
├── index.html              # Landing page institucional
├── login.php               # Autenticação (login + cadastro)
├── logout.php              # Encerrar sessão
├── dashboard_paciente.php  # Painel do paciente
├── dashboard_psicologa.php # Painel da psicóloga
├── estrutura_db.sql        # Script SQL completo (SQLite)
└── README.md
```

---

## 🔗 Links Úteis

- **Login:** `/login.php`
- **Admin (psicóloga):** admin@nexus.com / password
