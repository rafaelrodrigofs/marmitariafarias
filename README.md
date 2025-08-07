# 🍽️ Marmitaria Farias

Sistema completo de gestão para marmitaria com integração ao Anotaai, painel administrativo avançado e controle total de pedidos, estoque e clientes.

## ✨ Principais Funcionalidades

### 🛒 Sistema de Pedidos
- **Carrinho Inteligente**: Adicione produtos com acompanhamentos personalizáveis
- **Gestão de Clientes**: Cadastro completo com histórico de pedidos
- **Entrega e Retirada**: Sistema flexível com cálculo automático de taxa de entrega
- **Múltiplos Pagamentos**: Suporte a dinheiro, cartão, Pix e vouchers

### 🤖 Integração Anotaai
- **Extração Automática**: Extensão Chrome que importa pedidos automaticamente
- **Sincronização em Tempo Real**: Webhooks para atualizações instantâneas
- **Backup de Segurança**: Sistema robusto de logs e recuperação

### 📊 Painel Administrativo
- **Dashboard Completo**: Visão geral de vendas, despesas e relatórios
- **Controle de Estoque**: Gestão de produtos, categorias e acompanhamentos
- **Relatórios Avançados**: Análises detalhadas por período, cliente e produto
- **Fluxo de Produção**: Acompanhamento de pedidos em tempo real

### 💰 Gestão Financeira
- **Controle de Despesas**: Registro e categorização de gastos
- **Fechamento de Caixa**: Relatórios diários automatizados
- **Análise de Rentabilidade**: Comparativo de receitas vs despesas

## 🚀 Tecnologias Utilizadas

- **Backend**: PHP 7.4+ com MySQL
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **Integração**: API REST e Webhooks
- **Extensão**: Chrome Extension (Manifest V3)
- **Design**: Interface responsiva e moderna

## 📁 Estrutura do Projeto

```
marmitariafarias/
├── admin/                    # Painel administrativo
│   ├── actions/             # Endpoints da API
│   ├── controllers/         # Lógica de negócio
│   ├── views/              # Interfaces do usuário
│   └── assets/             # CSS, JS e imagens
├── AnotaaiExtractor/        # Extensão Chrome
├── anotaiPedidos/          # Sistema de integração
└── assets/                 # Recursos do site principal
```

## ⚡ Começando

### Pré-requisitos
- Servidor web (Apache/Nginx)
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Chrome (para extensão)

### Instalação
1. Clone o repositório no seu servidor web
2. Configure o banco de dados usando `admin/config/u195662740_pedidos_db.sql`
3. Ajuste as configurações em `admin/config/database.php`
4. Instale a extensão Chrome em `AnotaaiExtractor/`

## 🔧 Configuração

### Banco de Dados
Configure sua conexão no arquivo `admin/config/database.php`:

```php
// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'seu_banco');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

### Integração Anotaai
Configure os webhooks em `anotaiPedidos/anotaiWebhook.php` com suas credenciais da API.

## 📋 Funcionalidades Detalhadas

### Sistema de Acompanhamentos
- **Regras Personalizáveis**: Configure mínimo/máximo de escolhas
- **Preços Diferenciados**: Acompanhamentos com valores específicos
- **Validação Automática**: Sistema que impede combinações inválidas

### Gestão de Clientes
- **Tipos de Cliente**: Pessoa física e funcionários de empresas
- **Múltiplos Endereços**: Cada cliente pode ter vários locais de entrega
- **Histórico Completo**: Rastreamento total de pedidos anteriores

### Relatórios Inteligentes
- **Vendas por Período**: Análise diária, semanal e mensal
- **Produtos Mais Vendidos**: Ranking de performance
- **Clientes Ativos**: Identificação de padrões de consumo

## 🎯 Status dos Pedidos

- **🔍 Em Análise** (0): Pedido recebido, aguardando confirmação
- **👨‍🍳 Em Produção** (1): Sendo preparado na cozinha
- **✅ Pronto** (2): Aguardando entrega ou retirada
- **🎉 Finalizado** (3): Entregue com sucesso
- **❌ Cancelado** (4): Pedido cancelado

## 🔐 Segurança

- Prepared statements em todas as consultas SQL
- Validação de sessão em endpoints críticos
- Sanitização de dados de entrada
- Logs detalhados de atividades

## 📱 Responsividade

Interface totalmente adaptada para:
- 💻 Desktop
- 📱 Tablet
- 📱 Smartphone

## 🤝 Contribuição

Este é um sistema proprietário da Marmitaria Farias. Para sugestões ou melhorias, entre em contato com a equipe de desenvolvimento.

---

<div align="center">
  <strong>🍽️ Feito com ❤️ para a Marmitaria Farias</strong>
</div>
