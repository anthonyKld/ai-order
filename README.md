# AI Order — Assistente Inteligente de Pedidos

Base de um sistema comercial que, em etapas futuras, interpretará pedidos em linguagem natural e apresentará os dados para conferência antes de salvar. Projeto de portfólio com arquitetura MVC simplificada, sem framework PHP completo.

**Esta versão implementa somente a Etapa 01.** O exemplo exibido no dashboard é estático e ilustrativo. Não existem métricas comerciais, processamento de IA, autenticação, cadastro de produtos ou persistência de pedidos.

## Tecnologias e requisitos

- PHP 8.2+ com extensões `pdo` e `pdo_mysql`.
- MySQL preparado via PDO; não precisa estar iniciado para usar esta etapa.
- Composer 2, autoload PSR-4 e `vlucas/phpdotenv` para leitura do ambiente.
- HTML5, CSS3, JavaScript e Bootstrap 5.3.8, com arquivos locais e licença preservada.
- XAMPP/Apache com `mod_rewrite` e `AllowOverride All`, ou servidor embutido do PHP.

O projeto não exige Node.js nem uma etapa de build. Dependências PHP estão fixadas no `composer.lock`.

## Instalação

Coloque o projeto em `C:\xampp\htdocs\ai-order`. No PowerShell, dentro dessa pasta:

```powershell
composer install
Copy-Item .env.example .env
```

Se já houver um `.env`, preserve-o. Preencha somente as configurações necessárias. Não envie `.env`, `vendor/`, logs ou chaves de API para o repositório. `storage/logs` precisa permitir escrita pelo PHP.

Se PHP/Composer não estiverem no PATH, use o PHP do XAMPP e o caminho da sua instalação do Composer. Durante a preparação deste workspace, uma cópia local do Composer foi instalada em `.tools` (ignorada pelo Git):

```powershell
C:\xampp\php\php.exe .tools\composer.phar install
```

Em outro computador, instale o Composer pela [documentação oficial](https://getcomposer.org/download/).

## Configuração do ambiente

| Variável | Uso |
| --- | --- |
| `APP_NAME` | Nome exibido no layout; padrão `AI Order` |
| `APP_ENV` | Identificação do ambiente; padrão `local` |
| `APP_URL` | URL base opcional, incluindo subdiretório; vazia detecta o caminho automaticamente |
| `APP_TIMEZONE` | Fuso horário; padrão `America/Manaus` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE` | Servidor, porta e banco MySQL |
| `DB_USERNAME`, `DB_PASSWORD` | Credenciais locais; valores vazios no exemplo |
| `AI_API_URL`, `AI_API_KEY`, `AI_MODEL` | Reservadas para etapa futura, sem uso nesta versão |

Exemplos de `APP_URL`: `http://localhost/ai-order/public` no XAMPP ou `http://127.0.0.1:8000` no servidor embutido. Links usam o caminho dessa URL e permanecem no domínio atual. Ao alternar servidores, deixe a variável vazia ou ajuste o subdiretório.

Não é necessário criar banco, tabelas ou chave de API para abrir o dashboard. A classe `App\Core\Database` recebe o array de `config/database.php` e só cria a conexão ao chamar `connection()`. Está preparada com `utf8mb4`, exceções PDO e prepared statements nativos. A conexão com um banco real será exercitada nas etapas que precisarem de persistência.

## Executar no XAMPP

1. Inicie **Apache** no painel do XAMPP.
2. Abra **http://localhost/ai-order/public/**.
3. A raiz `http://localhost/ai-order/` redireciona para `public/`.

Os arquivos `.htaccess` bloqueiam acesso HTTP aos diretórios internos e encaminham URLs para o front controller. Não desabilite essas regras ao servir a pasta completa. Para um VirtualHost, configure `DocumentRoot` diretamente para `C:/xampp/htdocs/ai-order/public` e permita as regras do `.htaccess` desse diretório. Outros servidores também devem expor somente `public/`.

## Executar sem Apache

```powershell
composer start
```

Ou, usando o PHP do XAMPP diretamente:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public server.php
```

Abra **http://127.0.0.1:8000/**. `Ctrl+C` encerra o servidor. Use o servidor embutido apenas para desenvolvimento local. Mantenha `server.php` no comando para que as rotas sem extensão funcionem.

## Estrutura

```text
ai-order/
├── app/
│   ├── Controllers/       # Dashboard e páginas futuras
│   ├── Core/              # Router, View e Database
│   ├── Models/            # Reservado; sem modelos de negócio nesta etapa
│   ├── Views/
│   │   ├── dashboard/
│   │   ├── errors/
│   │   ├── layouts/
│   │   └── pages/
│   └── helpers.php        # Escape HTML, URLs, ambiente e ícones
├── config/                # Configuração da aplicação e do banco
├── public/                # Única raiz pública
│   ├── assets/
│   │   ├── css/
│   │   ├── img/
│   │   ├── js/
│   │   └── vendor/bootstrap/
│   ├── .htaccess
│   └── index.php          # Front controller e tratamento de falhas
├── routes/web.php
├── storage/logs/          # Logs privados, ignorados pelo Git
├── tests/run.php
├── vendor/                # Gerado pelo Composer, ignorado pelo Git
├── .env.example
├── .gitignore
├── .htaccess
├── bootstrap.php
├── composer.json
├── composer.lock
├── server.php             # Roteador do servidor local do PHP
└── README.md
```

Fluxo: requisição → `public/index.php` → bootstrap/configuração → Router → Controller → View/layout. O Composer mapeia `App\` para `app/` por PSR-4. `Models/` permanece vazio para evitar entidades e abstrações sem uso.

## Funcionalidades implementadas

- Dashboard de apresentação, módulos planejados e sequência de desenvolvimento.
- Menu lateral com estado ativo e painel recolhível no celular.
- Páginas Produtos, Pedidos e Assistente IA com aviso de implementação futura.
- Roteador GET/POST, query strings, subdiretórios, respostas 404 e 405 com `Allow`.
- Escape de dados dinâmicos, CSP com recursos locais e mensagens públicas genéricas em falhas internas.
- Configuração por ambiente e conexão PDO sob demanda.
- Navegação por teclado, link para pular ao conteúdo e respeito a movimento reduzido.

## Como testar

```powershell
composer validate --strict
composer test
```

Alternativa sem ferramentas no PATH:

```powershell
C:\xampp\php\php.exe .tools\composer.phar validate --strict
C:\xampp\php\php.exe tests\run.php
```

Os testes verificam despacho GET/POST, isolamento entre métodos, query strings, subdiretórios, 404/405, escape HTML, renderização das quatro páginas e rejeição de configuração de DSN insegura. O teste de POST registra uma rota apenas em memória, sem criar funcionalidade de negócio.

Verificação manual:

1. Abra `/` e confirme a apresentação e a marcação de exemplo ilustrativo.
2. Navegue por Produtos, Pedidos e Assistente IA; confira o destaque do menu e os avisos de “Em breve”.
3. Clique em “Conhecer o Assistente IA” e depois “Voltar ao Dashboard”.
4. Abra `/pagina-inexistente`: deve retornar HTTP 404 com navegação de retorno.
5. Envie POST para `/`: deve retornar HTTP 405, sem modificar dados.
6. Em uma janela de 390 px, abra/feche o menu, navegue e confira ausência de rolagem horizontal. Teste também teclado, Tab e Escape.
7. No Apache, tente `/ai-order/.env`, `/ai-order/composer.json` e `/ai-order/app/Core/Database.php`: devem retornar 403 sem expor conteúdo.

## Limitações e próximas etapas

- Sem autenticação ou operações de escrita; o ambiente atual é uma base local de desenvolvimento.
- O roteador usa caminhos estáticos, sem parâmetros dinâmicos ou middleware.
- Não há banco criado, migrations, métricas, integração externa ou interpretação de texto nesta etapa.
- Erros internos retornam mensagem genérica. Logs ficam em `storage/logs/php.log`; erros da aplicação não registram credenciais nem mensagens brutas de conexão.
- Próximas etapas, somente mediante solicitação: **02** produtos; **03** integração IA; **04** validação/confirmação; **05** persistência e gestão de pedidos; **06** finalização do portfólio.

Referências: [Bootstrap](https://getbootstrap.com/docs/5.3/getting-started/download/), [autoload PSR-4 no Composer](https://getcomposer.org/doc/04-schema.md#autoload) e [PHP dotenv](https://github.com/vlucas/phpdotenv).
