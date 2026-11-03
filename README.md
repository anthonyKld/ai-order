# AI Order — Assistente Inteligente de Pedidos

Desenvolvi o AI Order como um projeto de portfólio para transformar pedidos em linguagem natural em uma prévia estruturada para conferência. Utilizei PHP com uma arquitetura MVC simplificada, sem framework completo.

**Nesta versão, implementei as Etapas 01, 02 e 03 — Integração com Inteligência Artificial.** O catálogo persiste produtos, preços, estoque e variações no MySQL. O Assistente IA interpreta mensagens pela Google Gemini e valida os itens no PHP. Mantive o exemplo do dashboard estático e ilustrativo. A prévia não salva pedidos nem altera estoque; autenticação continua fora do escopo.

Registrei as decisões, o fluxo, os arquivos alterados, os testes e um roteiro para minha revisão técnica em **[Etapa 03 — documentação técnica](docs/ETAPA-03.md)**.

## Tecnologias e requisitos

- PHP 8.2+ de 64 bits com extensões `pdo` e `pdo_mysql`; a chamada à Google Gemini também exige `curl`, HTTPS e certificados confiáveis configurados.
- MySQL 8.0.16+ ou MariaDB 10.4+ (XAMPP), com InnoDB e `utf8mb4`. O catálogo exige o banco iniciado; o dashboard continua independente.
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
| `GEMINI_API_KEY` | Chave da Google Gemini, mantida exclusivamente no servidor |
| `GEMINI_MODEL` | Modelo com suporte à generateContent e saída JSON estruturada; obrigatório para interpretar |

Exemplos de `APP_URL`: `http://localhost/ai-order/public` no XAMPP ou `http://127.0.0.1:8000` no servidor embutido. Links usam o caminho dessa URL e permanecem no domínio atual. Ao alternar servidores, deixe a variável vazia ou ajuste o subdiretório.

A chave é necessária somente para interpretar pedidos. O dashboard e o catálogo continuam funcionando sem Google Gemini. Removi as variáveis reservadas `AI_API_URL`, `AI_API_KEY` e `AI_MODEL` do exemplo; se existirem no ambiente antigo, elas não são usadas. A classe `App\Core\Database` reutiliza `config/database.php` e abre a conexão sob demanda, com `utf8mb4`, exceções PDO e prepared statements nativos.

## Banco de dados — Etapa 02

1. Inicie **MySQL** no XAMPP.
2. Crie um banco dedicado, por exemplo `ai_order`, com charset `utf8mb4` e collation `utf8mb4_unicode_ci`.
3. Configure `.env` com as credenciais locais. Na instalação padrão de desenvolvimento do XAMPP, o usuário costuma ser `root` e a senha vazia; use os valores reais da sua instalação:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ai_order
DB_USERNAME=root
DB_PASSWORD=
```

4. No phpMyAdmin, selecione esse banco e importe **`database/migrations/002_products.sql`**. A migration usa `CREATE TABLE IF NOT EXISTS`: não apaga nem substitui registros. Ela não corrige automaticamente uma tabela preexistente com estrutura incompatível.

Alternativa pelo cliente MySQL no PowerShell:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -p
```

No console SQL (ajuste banco/caminhos se necessário):

```sql
CREATE DATABASE IF NOT EXISTS ai_order CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai_order;
SOURCE C:/xampp/htdocs/ai-order/database/migrations/002_products.sql;
```

O arquivo **`database/seeds/products_demo.sql` é opcional e nunca é importado automaticamente**. Ele adiciona Camiseta Básica (R$ 39,90, 33 unidades em três variações), Caneca Personalizada (R$ 24,90, 20 unidades) e Boné Bordado (R$ 49,90, inativo). Cada importação adiciona novos registros; execute apenas quando desejar dados de demonstração.

| Tabela | Estrutura |
| --- | --- |
| `products` | `id` INT UNSIGNED PK auto incremento; `name` VARCHAR(150); `description` TEXT NULL; `price` DECIMAL(10,2); `stock` INT; `active` TINYINT; `created_at`, `updated_at` DATETIME |
| `product_variants` | `id` INT UNSIGNED PK auto incremento; `product_id` INT UNSIGNED FK; `name` VARCHAR(100); `stock` INT; `created_at` DATETIME |

Há índices no nome do produto e em `(active, id)`, unicidade em `(product_id, name)` e chave estrangeira com `ON DELETE RESTRICT`. As constraints `CHECK` impedem valores negativos e status inválido. O índice único segue a collation, rejeitando duplicatas inclusive com diferenças apenas de caixa ou acentos.

**Regra de estoque:** sem variações, usa-se `products.stock`; com variações, esse campo fica em zero e a listagem usa exclusivamente `SUM(product_variants.stock)`. Ao remover a última variação, informe novamente o estoque simples. Não se mantém um total duplicado. Use o módulo para gravar os dados: alterações SQL manuais também precisam respeitar essa regra.

## Executar no XAMPP

1. Inicie **Apache e MySQL** no painel do XAMPP.
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
│   ├── Controllers/       # Dashboard, produtos, assistente e páginas futuras
│   ├── Core/              # Router, View, Database e Session (CSRF/flash)
│   ├── Models/            # Product: consultas e transações
│   ├── Services/          # Google Gemini, erros e limites de requisição
│   ├── Validation/        # Formulário, resposta estruturada e prévia
│   ├── Views/
│   │   ├── ai/            # Formulário e resultado assíncrono
│   │   ├── dashboard/
│   │   ├── errors/
│   │   ├── layouts/
│   │   ├── pages/
│   │   └── products/      # Listagem e formulário compartilhado
│   └── helpers.php        # Escape HTML, URLs, ambiente e ícones
├── config/                # Configuração da aplicação, banco e Google Gemini
├── docs/ETAPA-03.md        # Decisões e roteiro de revisão técnica
├── database/              # Migration versionada e seed opcional
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
├── tests/                 # Testes sem banco e integração MySQL isolada
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

Fluxo: requisição → `public/index.php` → bootstrap/configuração → Router → Controller → validação/Model → View/layout. O Composer mapeia `App\` para `app/` por PSR-4. As rotas continuam estáticas; a edição identifica o produto por `?id=`.

## Funcionalidades implementadas

- Dashboard de apresentação, módulos planejados e sequência de desenvolvimento.
- Menu lateral com estado ativo e painel recolhível no celular.
- Cadastro, edição e exclusão de produtos com descrição, preço, status e estoque.
- Variações adicionadas e removidas com JavaScript nativo; formulário preserva valores após erros.
- Pesquisa por nome, filtro por status e paginação no servidor com 10 produtos por página.
- Tabela responsiva, estado vazio, mensagens de sucesso e modal de confirmação de exclusão.
- Transações para produto/variações, validação no servidor e CSRF em todas as escritas.
- Assistente IA com Fetch, saída estruturada, prévia validada e total parcial quando há pendências.
- Página Pedidos permanece com aviso de implementação futura.
- Roteador GET/POST, query strings, subdiretórios, respostas 404 e 405 com `Allow`.
- Escape de dados dinâmicos, CSP com recursos locais e mensagens públicas genéricas em falhas internas.
- Configuração por ambiente e conexão PDO sob demanda.
- Navegação por teclado, link para pular ao conteúdo e respeito a movimento reduzido.

## Como usar o catálogo

Abra **Produtos** no menu lateral ou `/produtos`. Clique em **Novo produto**, informe o nome, preço (ex.: `39,90` ou `39.90`, sem milhar), estoque e status. Para uma camiseta, adicione `Preta / P` com 10 unidades, `Preta / M` com 15 e `Branca / G` com 8: a listagem exibirá 33 unidades. Todas as variações usam o preço do produto.

Na listagem, pesquise pelo nome e combine com Ativo/Inativo. A paginação preserva esses filtros. **Editar** permite alterar todos os dados; **Excluir** abre um modal e só envia o POST após clicar em **Confirmar exclusão**. Cancelar não altera dados.

| Método | Rota | Operação |
| --- | --- | --- |
| GET | `/produtos` | Listagem (`q`, `status`, `page`) |
| GET | `/produtos/novo` | Formulário de cadastro |
| GET | `/produtos/editar?id=1` | Formulário de edição |
| POST | `/produtos/criar` | Cadastro com CSRF |
| POST | `/produtos/atualizar` | Atualização com ID e CSRF |
| POST | `/produtos/excluir` | Exclusão com ID, `confirm=1` e CSRF |

## Como testar

```powershell
composer validate --strict
composer test
```

Alternativa sem ferramentas no PATH:

```powershell
C:\xampp\php\php.exe .tools\composer.phar validate --strict
C:\xampp\php\php.exe tests\run.php
C:\xampp\php\php.exe tests\ai.php
```

`tests/run.php` não depende do MySQL. Verifica rotas, subdiretórios, escape HTML, formulário, validação de nome/preço/estoque/variações, limites, normalização monetária, CSRF nas três operações e confirmação obrigatória.

Incluí `tests/ai.php` no `composer test`: valida o contrato JSON, o processamento da prévia, as falhas simuladas da API, a soma de itens repetidos, valores monetários e os limites de uso. Esse teste não usa rede, banco nem créditos. A simulação de quantidades por extenso verifica o processamento da resposta normalizada; a qualidade linguística do modelo precisa de teste real.

Após configurar o banco, execute também:

```powershell
C:\xampp\php\php.exe tests/products_mysql.php
C:\xampp\php\php.exe tests/ai_mysql.php
```

Esse teste exige permissão para criar/remover bancos. Cria um banco **aleatório e isolado** com prefixo `ai_order_test_`, aplica a migration, testa CRUD, estoque, IDs, timestamps, pesquisa, filtros, paginação, rollback e proteção de vínculos comerciais simulados; remove somente esse banco ao terminar. Não importa o seed nem altera os produtos de `DB_DATABASE`. Não há dependências de teste adicionais.

O teste `ai_mysql.php` também usa um banco temporário isolado: confere catálogo ativo, limites, consulta após interpretação e ausência de alterações no banco, com a resposta da Google Gemini simulada.

Verificação manual:

1. Abra `/` e confirme a apresentação e a marcação de exemplo ilustrativo.
2. Navegue por Produtos, Pedidos e Assistente IA; confira o destaque do menu e o aviso de “Em breve” somente em Pedidos.
3. Clique em “Conhecer o Assistente IA”, confira o formulário e volte pelo link Dashboard no menu.
4. Abra `/pagina-inexistente`: deve retornar HTTP 404 com navegação de retorno.
5. Envie POST para `/`: deve retornar HTTP 405, sem modificar dados.
6. Em uma janela de 390 px, abra/feche o menu, navegue e confira ausência de rolagem horizontal. Teste também teclado, Tab e Escape.
7. No Apache, tente `/ai-order/.env`, `/ai-order/composer.json` e `/ai-order/app/Core/Database.php`: devem retornar 403 sem expor conteúdo.

Verificação manual da Etapa 02:

1. Cadastre um produto simples com nome, preço `24,90` e estoque `20`; recarregue e confirme os dados persistidos.
2. Tente nome vazio, preço negativo/texto e estoque fracionário/negativo. Mesmo enviando POST diretamente, deve retornar 422 com mensagem clara e preservar o formulário.
3. Cadastre as três variações do exemplo; confirme 33 unidades. Tente nomes duplicados, inclusive `Preta / P` e `preta / p`; o cadastro deve falhar sem gravar parcialmente.
4. Edite nome, descrição, preço, estoque, status e variações; adicione/remova linhas e confirme a persistência. O ID do produto e os IDs das variações mantidas devem permanecer iguais.
5. Clique em Excluir e cancele: o registro deve continuar. Confirme depois: produto e variações devem desaparecer.
6. Pesquise um trecho do nome, combine com status e tente uma pesquisa sem resultados; confira o estado vazio.
7. Cadastre ao menos 11 produtos; confirme 10 na primeira página, os demais na segunda e preservação de pesquisa/status ao navegar.
8. Nas ferramentas do navegador, remova ou altere `_token` antes de enviar cada formulário de escrita: deve retornar 403 sem modificar o banco. GET nas rotas de escrita retorna 405.
9. Digite `<script>alert(1)</script>` no nome/descrição: o conteúdo deve aparecer como texto e não executar.
10. Confira formulário, listagem e modal em 320/390 px e desktop. A tabela pode rolar horizontalmente dentro do painel; a página não deve transbordar. Teste menu, teclado, foco e Escape.

Validei a integração Gemini com PHP 8.2.12, MariaDB 10.4.32 do XAMPP e Microsoft Edge: 73 verificações gerais, 105 de IA sem rede, 10 de IA/MySQL e 23 de HTTP/interface, totalizando 211 verificações aprovadas. O teste real final passou pelo navegador, Apache, MySQL e Gemini 3.5 Flash-Lite: o pedido de 3 camisetas básicas M gerou a prévia sem pendências e total local de R$ 119,70 em cerca de 2 segundos. O Gemini 3.8 Flash retornava erros 503 intermitentes e timeout. Consulte [o diagnóstico Gemini](docs/TESTES-GEMINI.md). A entrega original da Etapa 03 e seus resultados históricos estão em [docs/ETAPA-03.md](docs/ETAPA-03.md).

## Assistente IA — Etapa 03

### Configuração e uso

Mantive a URL da API fixa no back-end (`https://generativelanguage.googleapis.com/v1beta/models/{modelo}:generateContent`). No `.env` local, adicione:

```dotenv
GEMINI_API_KEY=sua_chave_local
GEMINI_MODEL=gemini-3.5-flash-lite
```

O modelo acima é um exemplo compatível com Structured Outputs; a escolha é configurável e depende do acesso da conta. Não envie a chave para o Git nem para o JavaScript. No XAMPP, confira `extension=curl` no `php.ini` e reinicie o Apache se alterar extensões. Em caso de falha de certificado, configure uma cadeia de certificados confiável para o PHP; não desative a verificação TLS.

Com MySQL iniciado e produtos ativos cadastrados, abra **Assistente IA** (`/assistente`), escreva o pedido e clique em **Interpretar pedido**. Recebo o resultado sem recarregar a página.

### Fluxo que implementei

1. O navegador envia `{ "message": "Quero 3 camisetas pretas M" }` para `POST /api/ai/interpret`, com o token CSRF da sessão no cabeçalho `X-CSRF-Token`.
2. O PHP valida JSON, tamanho e limites por sessão; consulta somente os IDs, nomes e variações dos produtos ativos.
3. O `AIService` envia contexto e mensagem para a API Gemini generateContent com JSON Schema. Sem catálogo, interrompo antes da chamada externa.
4. Valido o formato da resposta no PHP e consulto novamente cada produto sugerido. Confiro atividade, correspondência de nomes, vínculo da variação e quantidade.
5. Somo quantidades repetidas, comparo com o estoque e calculo os valores com preços do banco, em centavos inteiros.
6. Exibo os itens, todas as pendências, observações, prazo literal e total estimado apenas dos itens sem pendências.

A chamada usa `systemInstruction`, `contents` e `generationConfig` com `responseMimeType: application/json` e `responseJsonSchema`, conforme a [referência oficial do Gemini](https://ai.google.dev/api/generate-content). O conteúdo continua sendo validado no PHP e conferido com o catálogo local.

### Exemplos para testar

**Gemini integrado:** consulte [os testes e o diagnóstico](docs/TESTES-GEMINI.md). Para uma chamada real optativa com dados fictícios, execute `C:\xampp\php\php.exe tests/gemini_live.php`. A rotina padrão de testes não usa a API. As antigas variáveis `OPENAI_API_KEY` e `OPENAI_MODEL` não são mais utilizadas.

Os exemplos abaixo dependem dos respectivos produtos e variações no catálogo. O seed opcional da Etapa 02 contém Camiseta Básica e Caneca Personalizada.

| Mensagem | Resultado que espero conferir |
| --- | --- |
| `Quero duas camisetas pretas M.` | Quantidade 2 e variação Preta / M |
| `Quero 3 camisetas pretas M e 2 camisetas brancas G para sexta-feira.` | Dois itens e prazo literal |
| `Preciso de 5 canecas.` | Produto simples, quantidade 5 |
| `Quero umas camisetas.` | Quantidade e variação pendentes |
| `Quero 3 camisetas azuis.` | Variação não encontrada quando não cadastrada |
| `Quero 10 camisetas pretas M e mais 10 camisetas pretas M.` | Soma 20; insuficiência se o estoque for 15 |
| `Quero 1 produto inexistente.` | Produto não encontrado, sem preço inventado |

### Limites e custos

Adotei 2.000 caracteres por mensagem, corpo HTTP de até 16.000 bytes, cinco tentativas válidas por minuto por sessão e bloqueio da mesma mensagem por 15 segundos. Catálogo: até 100 produtos e 500 linhas na consulta com variações, com contexto serializado de até 64.000 bytes. Rejeito o excesso sem enviar um catálogo incompleto. A resposta aceita até 50 itens e quantidade de 1 a 10.000 por item.

Limitei a saída a 4.000 tokens, a conexão a 5 segundos e a requisição a 60 segundos (75 segundos no navegador). Para HTTP 500, 502, 503 ou 504, faço no máximo uma nova tentativa após uma pausa curta, dentro dos mesmos 60 segundos totais. Não repito automaticamente erros de cota, configuração ou timeout. Cada nova tentativa pode consumir quota do provedor. Configuração inválida, limite do provedor ou respostas incompletas podem impedir a prévia; falhas locais após o limite por sessão também consomem uma tentativa dessa janela.

Cada chamada real pode consumir créditos. Os limites locais reduzem o uso acidental, mas não representam um teto financeiro global. Antes de testar, confiro o consumo e os limites da conta Google Gemini. O texto digitado e o catálogo mínimo são enviados ao provedor; não registro mensagens completas ou respostas brutas em logs. O tratamento de dados pelo Google depende das condições aplicáveis ao projeto.

**Como ainda não implementei autenticação, este endpoint não deve ser exposto publicamente sem controles adicionais de acesso e consumo.** Uma nova sessão contorna o limite local; CSRF não substitui autenticação nem proteção global contra abuso.

O reconhecimento pode falhar com apelidos, abreviações, erros de digitação e descrições ambíguas. Minha conferência de nomes é conservadora: normaliza caixa, alguns acentos, plurais simples e cores comuns, mas não é uma busca semântica. A IA ainda pode extrair informações incorretamente. Preservo o prazo como texto, sem prometer uma data de entrega.

**A prévia precisa de conferência humana.** Não implementei criação ou gravação de pedidos, baixa de estoque nem confirmação nesta etapa. O estoque pode mudar depois da consulta; em uma etapa futura, será necessário revalidá-lo no momento de salvar, dentro da regra transacional adequada.

## Limitações e próximas etapas

- Sem autenticação: o catálogo é para desenvolvimento local; qualquer visitante com acesso pode gerenciá-lo.
- O roteador usa caminhos estáticos, sem parâmetros dinâmicos ou middleware.
- Até 100 variações por produto, nome de produto com 150 caracteres, variação com 100, descrição com 5.000. Preço máximo de R$ 99.999.999,99 e estoque total máximo de 2.147.483.647.
- Preços são strings decimais no PHP e DECIMAL no banco; não passam por float. Nomes de produtos podem repetir; nomes de variações no mesmo produto não.
- JavaScript é necessário para adicionar/remover linhas e abrir a confirmação; a segurança e a validação são feitas no servidor. Cookies de sessão devem estar habilitados.
- Pesquisa por trecho usa `LIKE` e não full-text; adequada ao MVP. Edições simultâneas usam transações e lock do produto, mas a última gravação válida prevalece.
- IDs de variações existentes são preservados. A exclusão física ocorre em transação: futuras tabelas de pedidos devem usar FKs com `ON DELETE RESTRICT` para produtos e variações. A aplicação já trata o bloqueio com uma mensagem amigável e rollback completo; não há tabelas de pedidos nesta etapa.
- Sem clientes, pedidos, financeiro, uploads ou relatórios. A integração externa desta versão se limita à Google Gemini; o dashboard não mostra métricas reais.
- Erros internos retornam mensagem genérica. Logs ficam em `storage/logs/php.log`; erros da aplicação não registram credenciais nem mensagens brutas de conexão.
- Próximas etapas, somente mediante solicitação: **04** fluxo de validação/confirmação; **05** persistência e gestão de pedidos; **06** finalização do portfólio. A validação técnica da prévia já faz parte da Etapa 03.

## Arquivos da Etapa 02

Criados:

- `app/Controllers/ProductController.php`, `app/Models/Product.php`, `app/Validation/ProductValidator.php` — fluxo MVC, persistência e validação.
- `app/Core/Session.php` — sessão, tokens CSRF e mensagens temporárias.
- `app/Views/products/index.php`, `app/Views/products/form.php` — catálogo e formulário.
- `public/assets/js/products.js` — variações, total de estoque e modal.
- `database/migrations/002_products.sql`, `database/seeds/products_demo.sql` — estrutura e dados opcionais.
- `tests/products_mysql.php` — integração em banco isolado.

Modificados:

- `routes/web.php`, `app/Controllers/PlaceholderController.php` — rotas reais de produtos.
- `app/helpers.php` — formatação de moeda sem float.
- `app/Views/layouts/app.php`, `app/Views/dashboard/index.php` — inclusão do script e indicação da etapa disponível.
- `app/Views/errors/http.php` — mensagens de CSRF, produto ausente e exclusão bloqueada.
- `public/assets/css/app.css` — componentes responsivos do catálogo e ajuste do link de navegação por teclado.
- `tests/run.php` — validação e segurança sem banco.
- `README.md` — instalação, uso, testes e decisões técnicas.

Mensagem de commit sugerida: `feat(products): implement product catalog with stock and variants`.

Referências: [Bootstrap](https://getbootstrap.com/docs/5.3/getting-started/download/), [autoload PSR-4 no Composer](https://getcomposer.org/doc/04-schema.md#autoload) e [PHP dotenv](https://github.com/vlucas/phpdotenv).
