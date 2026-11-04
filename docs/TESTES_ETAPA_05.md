# Testes — Etapa 05: Persistência e Gerenciamento de Pedidos

Execução em **04/11/2026**, referência de calendário **America/Manaus**.

A implementação foi validada no ambiente local XAMPP, com **467 verificações aprovadas e zero falhas na execução final das suítes**. A integração foi executada no **MariaDB 10.4.32**, através de PDO MySQL/InnoDB. **Oracle MySQL 8 não está instalado neste ambiente; a verificação nesse motor permanece pendente.** Não se declara validação integral em um motor que não foi executado.

## Ambiente e ferramentas

| Componente | Versão/uso real |
|---|---|
| Sistema | Windows, PowerShell; workspace `C:\xampp\htdocs\ai-order` |
| PHP | 8.2.12, CLI, ZTS, x64; `C:\xampp\php\php.exe` |
| Banco | `SELECT VERSION()` retornou `10.4.32-MariaDB`; InnoDB; PDO MySQL com prepared statements nativos |
| Unitários | Runner de asserções PHP já existente, estendido sem dependências adicionais |
| Integração | Schemas aleatórios `ai_order_test_<12 hex>`, SQL real, triggers de falha e processos PHP/PDO independentes |
| Node.js | 26.13.0 |
| Playwright | 1.64.1, instalação já disponível em `.qa/node_modules` |
| Navegador | Microsoft Edge 150.0.4078.48, headless |
| HTTP | Servidor embutido PHP em porta livre para Etapa 05; processos encerrados ao finalizar |
| IA | Respostas simuladas; nenhuma chamada real ao Gemini nesta entrega |

O runner novo lê `tests/database_config.php` e, opcionalmente, `.env.test`, sem usar `.env`. O schema é sempre criado pelo próprio runner, nunca escolhido pelo usuário. O runner apaga o schema em `finally`, inclusive nas tentativas que falharam. O servidor da Etapa 05 usa diretório temporário exclusivo de sessões, também removido ao finalizar. Capturas de evidência são mantidas intencionalmente em `.qa`.

Os runners legados leem as credenciais de `.env` para criar seus próprios schemas aleatórios; seus cenários não são executados no catálogo de desenvolvimento.

## Comandos executados

```powershell
C:\xampp\php\php.exe .tools/composer.phar test
C:\xampp\php\php.exe tests/products_mysql.php
C:\xampp\php\php.exe tests/ai_mysql.php
C:\xampp\php\php.exe tests/orders_mysql.php --browser
C:\xampp\php\php.exe tests/order_persistence_mysql.php
C:\xampp\php\php.exe tests/order_persistence_mysql.php --browser
C:\xampp\php\php.exe .tools/composer.phar validate --no-check-publish
git diff --check
node --check public/assets/js/order-review.js
node --check public/assets/js/orders.js
node --check tests/order_persistence_browser.cjs
```

Também foram executados `php -l` sobre os **49 arquivos PHP** de `app`, `tests`, `routes`, `config` e `public`. Todos passaram. Composer retornou `./composer.json is valid`; os três arquivos JavaScript passaram na análise sintática; o diff não contém erros de whitespace. Essas verificações auxiliares não entram no total de asserções abaixo.

## Contagem da execução final

As contagens representam **asserções/verificações**, conforme a convenção dos runners existentes, e não métodos PHPUnit. Reexecuções intermediárias não são somadas. As verificações de sucesso de subprocessos de navegador estão incluídas nos respectivos runners de integração, além das asserções internas do navegador.

| Suíte | Executadas | Aprovadas | Reprovadas |
|---|---:|---:|---:|
| `tests/run.php` — estrutura/produtos/segurança | 71 | 71 | 0 |
| `tests/ai.php` — IA sem rede | 105 | 105 | 0 |
| `tests/orders.php` — prévia da Etapa 04 | 46 | 46 | 0 |
| `tests/order_persistence.php` — unitários da Etapa 05 | 19 | 19 | 0 |
| `tests/products_mysql.php` — regressão do catálogo | 19 | 19 | 0 |
| `tests/ai_mysql.php` — regressão IA com banco | 10 | 10 | 0 |
| `tests/orders_mysql.php --browser` — runner da Etapa 04 | 8 | 8 | 0 |
| `tests/ai_browser.cjs` — chamado pelo runner anterior | 23 | 23 | 0 |
| `tests/orders_browser.cjs` — chamado pelo runner anterior | 39 | 39 | 0 |
| `tests/order_persistence_mysql.php --browser` — integração da Etapa 05 | 78 | 78 | 0 |
| `tests/order_persistence_browser.cjs` — chamado pelo runner anterior | 49 | 49 | 0 |
| **Total** | **467** | **467** | **0** |

Por tipo: **241 unitárias/sem banco, 115 nos runners de integração SQL e 111 HTTP/navegador**. A Etapa 05 acrescenta **146 verificações**; as suítes anteriores fornecem **321 verificações de regressão**. O runner novo sem `--browser` executa 77 verificações; com a opção, acrescenta a verificação de saída bem-sucedida do subprocesso.

## Cobertura e evidências

| Teste | Resultado | Observação |
|---|---|---|
| Criar pedido | PASSOU | Produtos/variação reais; 3 unidades a 39,90 + 2 a 24,90 = 169,50; 5 unidades e 2 itens persistidos |
| Número único | PASSOU | Confirmações distintas geram números distintos; índices UNIQUE instalados |
| Preços/subtotais históricos | PASSOU | Alteração posterior de preço, nome de produto e variação não altera o pedido salvo |
| Limite financeiro | PASSOU | 100 linhas × 10.000 × 99.999.999,99 persistem como `99999999990000.00`, sem float/truncamento |
| Validação | PASSOU | Sem itens, produto inexistente/inativo, variação inválida/ausente, IDs inválidos, quantidades zero/negativa/fracionária e prévia não confirmada são rejeitados |
| Baixa de estoque | PASSOU | Produto sem variações baixa `products`; variação baixa `product_variants`; estoque próprio do produto com variações fica zero |
| Itens duplicados | PASSOU | Quantidades somadas para rejeitar excesso, baixar e repor; linhas históricas permanecem separadas |
| Mudanças após confirmar | PASSOU | Preço/nome alterados retornam prévia atualizada sem confirmação; estoque insuficiente/inativação impedem salvar |
| Reconferência no navegador | PASSOU | Preço 12,35 → 20,10 e nome alterado em outra aba; nova confirmação obrigatória; total de 2 unidades passa a 40,20 |
| ROLLBACK na criação | PASSOU | Triggers geram erro SQL ao inserir pedido, segundo item e atualizar estoque; snapshots de todas as tabelas permanecem idênticos |
| ROLLBACK no cancelamento | PASSOU | Falha na reposição após outro item ser atualizado desfaz estoque e status integralmente |
| Idempotência | PASSOU | Repetição com e sem prévia retorna o mesmo ID; um pedido e uma baixa |
| Idempotência concorrente | PASSOU | Dois processos com a mesma chave retornam o mesmo ID; estoque final 1 após compra de 4 sobre estoque 5 |
| Concorrência | PASSOU | Dois processos/conexões PDO observados em `information_schema.innodb_trx` no estado `LOCK WAIT`; estoque 5 e duas compras de 4 resultam em exatamente um sucesso, estoque 1 |
| Cancelamento concorrente | PASSOU | Duas conexões aguardam a barreira, ambas retornam sucesso idempotente; estoque volta a 5, sem segunda reposição |
| Status | PASSOU | Matriz dos 16 pares de estados: quatro transições permitidas, repetições inofensivas e demais mudanças rejeitadas |
| Cancelamento | PASSOU | Pendente/processamento cancelam; reposição de produto e variação; `cancelled_at` preenchido; histórico preservado |
| Estados terminais | PASSOU | Concluído/cancelado não podem mudar para outro status; não há endpoint de exclusão |
| Listagem/pesquisa/filtro | PASSOU | Consultas reais e navegador, pesquisa por número, filtro cancelado, ordenação e mensagem sem resultados |
| Paginação | PASSOU | Páginas SQL de 10, sem IDs sobrepostos; link Próxima funciona no navegador |
| Integridade referencial | PASSOU | FK rejeita item órfão e exclusões de pedido/produto/variação vinculados; CHECK rejeita estoque negativo |
| Fonte de estoque do histórico | PASSOU | Troca de produto vendido sem variações para produto com variações é rejeitada com rollback |
| CSRF e métodos | PASSOU | Salvar e mudar status sem CSRF retornam 403; GET retorna 405; cancelar sem confirmação retorna 422 |
| Corpo HTTP | PASSOU | Tipo incorreto 415, JSON malformado 400, corpo maior que 4 KiB 413, chave inválida 422 |
| Manipulação de preço | PASSOU | Valor enviado na confirmação é ignorado; reenvio com totais/itens adulterados retorna o pedido original |
| Isolamento da chave na sessão | PASSOU | Outra sessão, mesmo com CSRF válido, não consegue reenviar chave alheia |
| Conteúdo malicioso | PASSOU | Escape de nomes/observações/prazo nas views; script nas observações não executa no navegador; pesquisa SQL maliciosa não retorna registros |
| Erro interno seguro | PASSOU | Trigger com mensagem sentinela de credencial causa HTTP 500 genérico, sem sentinela ou SQLSTATE na resposta; retry da mesma confirmação funciona |
| Fluxo completo | PASSOU | Cadastro UI → IA simulada → conferir → confirmar → salvar → número → histórico → detalhes → estoque → processar → cancelar → estoque restaurado |
| Interface | PASSOU | Spinner, botão desabilitado e clique sintético duplicado geram um envio; modal de cancelamento e feedback de sucesso |
| Responsividade | PASSOU | Listagem/detalhes em 390 e 320 px, sem transbordamento da página; tabelas com rolagem horizontal; inspeção visual da captura móvel |
| Etapa 01 | PASSOU | Dashboard, navegação, roteamento e subdiretórios nos testes sem banco e navegador |
| Etapa 02 | PASSOU | Cadastro, listagem, edição, variações e exclusão/integridade nos testes anteriores e novos |
| Etapa 03 | PASSOU | Parsing estruturado, correspondência e validação com mocks; regressão HTTP e navegador |
| Etapa 04 | PASSOU | Edição, quantidades, adicionar/remover, duplicatas, estoque, confirmação temporária e invalidação da sessão |
| Limpeza | PASSOU | Runners imprimiram `Banco descartável removido.`; processos finalizados e sessões temporárias novas removidas |

Trecho da última execução da Etapa 05, preservado em `.qa/etapa05-integration.log`:

```text
PHP 8.2.12 / SQL 10.4.32-MariaDB
OK: 49 verificações HTTP/navegador da Etapa 05, sem API real.
PASS 78: Fluxo HTTP e navegador completo
OK: 78 verificações de integração da Etapa 05.
Banco descartável removido.
```

Capturas locais: `.qa/orders-stage05-mobile.png` e `.qa/orders-stage05-desktop.png`. `.qa` permanece ignorada pelo Git; este relatório e os testes reproduzíveis são versionados.

## Problemas encontrados e correções

1. O catálogo permitia transformar um produto sem variações em produto com variações. Para produtos já vendidos isso inviabilizaria a reposição na fonte correta. O cadastro agora impede essa mudança quando há itens históricos; o teste comprova a rejeição e o rollback.
2. A prévia armazenava IDs/preços, mas não os nomes necessários para detectar alterações comerciais. A confirmação agora guarda os nomes e uma chave aleatória; o salvamento compara os snapshots sob bloqueio e exige reconfirmação quando necessário.
3. A antiga rota de pedidos era um placeholder testado sem banco. A página agora depende de SQL: a cobertura dessa rota foi transferida para integração/navegador, mantendo os demais testes unitários independentes de banco. Por isso `tests/run.php` passa de 73 para 71 verificações.
4. A primeira tentativa do teste de JSON malformado recebeu 422, pois o cliente Playwright normalizou a string como JSON válido. O teste passou a enviar bytes `Buffer`, preservando o corpo inválido; a aplicação então retornou o 400 esperado. A expectativa não foi relaxada.
5. No fixture CLI de falha SQL, uma variável de senha vazia não era propagada como string no Windows. A conversão explícita para string corrigiu o fixture; o teste de 500 e o retry foram executados com sucesso depois disso.
6. A inspeção da primeira captura móvel revelou colunas comprimidas, embora não houvesse overflow da página. As tabelas ganharam largura mínima dentro do contêiner responsivo; a suíte de navegador e a inspeção visual foram repetidas.
7. Ao isolar as sessões do servidor de teste, o parser INI do PHP truncou o caminho curto do Windows no caractere `~`, impedindo persistir o CSRF. O valor de `session.save_path` passou a ser explicitamente delimitado por aspas. A suíte completa foi reexecutada com sessões isoladas.

Não há falhas conhecidas remanescentes nas suítes executadas. As falhas intermediárias acima não são omitidas nem incluídas como resultados aprovados antes da correção.

## Instalação local verificada

A migration aditiva `005_orders.sql` foi aplicada ao banco local configurado no `.env`, **depois** dos testes em schemas descartáveis. A comparação integral de produtos/variações antes e depois confirmou preservação de **1 produto e 6 variações**, sem pedidos de teste no banco local (`orders`: 0). Nenhum teste de falha, concorrência, estoque ou exclusão usou dados reais. Os comandos de instalação reproduzíveis estão no README.

## Testes não executados e limitações

| Teste | Resultado | Motivo/impacto |
|---|---|---|
| Oracle MySQL 8 | NÃO EXECUTADO | O ambiente fornece MariaDB 10.4.32. DDL e PDO são compatíveis com o alvo MySQL 8.0.16+, mas a execução nesse motor continua pendente; usar `.env.test` apontando para uma instância MySQL para validar |
| Gemini real nesta entrega | NÃO EXECUTADO | Mocks foram usados conforme solicitado para evitar créditos; parsing/validação foram cobertos, disponibilidade do provedor não foi revalidada |
| Firefox/Safari e dispositivo físico | NÃO EXECUTADO | Automação disponível com Edge headless; dimensões móveis foram emuladas |

Não há autenticação/autorização nesta etapa. Gerenciar catálogo e pedidos exige controle de acesso antes de publicação em produção. CSRF não fornece autenticação. A chave pode ser reenviada enquanto a sessão correspondente existir; após expiração, o histórico continua disponível. Não há reserva na prévia, edição de itens salvos ou reabertura de pedidos encerrados. O dashboard permanece ilustrativo. Nenhum recurso da Etapa 06 foi implementado.

## Arquivos entregues

Criados:

- `database/migrations/005_orders.sql` — schema aditivo.
- `app/Models/Order.php` — persistência, consultas, transações, locks, status e estoque.
- `app/Controllers/OrderController.php` — HTTP, sessão, CSRF, navegação e tratamento seguro de erros.
- `app/Services/OrderPreviewChanged.php` — retorno de prévia para nova conferência.
- `app/Views/orders/index.php`, `app/Views/orders/show.php` — histórico e detalhes/modal.
- `public/assets/js/orders.js` — proteção de envio dos formulários de status.
- `.env.test.example`, `tests/database_config.php` — configuração separada de teste.
- `tests/order_persistence.php`, `tests/order_persistence_mysql.php`, `tests/order_persistence_browser.cjs` — suítes da etapa.
- `tests/order_worker.php`, `tests/order_browser_fixture.php` — concorrência e injeção de falha em ambiente descartável.
- `docs/TESTES_ETAPA_05.md` — este relatório.

Modificados:

- `app/Services/OrderPreviewSession.php`, `app/Controllers/OrderPreviewController.php` — snapshots e chave de confirmação.
- `app/Models/Product.php` — ordem dos locks e proteção da fonte de estoque de produtos vendidos.
- `app/Views/ai/index.php`, `public/assets/js/order-review.js` — salvar, carregamento, reconferência e redirecionamento.
- `app/Views/layouts/app.php`, `app/Views/dashboard/index.php`, `public/assets/css/app.css` — navegação/etapa e apresentação responsiva.
- `routes/web.php` — rotas reais de pedidos.
- `tests/run.php` — deslocamento da cobertura da listagem para integração.
- `composer.json`, `.gitignore`, `README.md` — comandos, configuração e documentação.

Mensagem de commit sugerida: `feat(orders): persist orders with transactional stock and idempotent cancellation`.
