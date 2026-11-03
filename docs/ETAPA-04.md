# Etapa 04 — Validação e Confirmação de Pedidos

Implementação restrita à revisão e confirmação temporária. Nenhuma tabela de pedidos, gravação definitiva, baixa de estoque, pagamento, cliente, autenticação ou integração adicional foi criada. As alterações preexistentes da integração Gemini foram preservadas; o serviço de IA não foi modificado por esta etapa.

## Arquitetura e fluxo

A Etapa 03 usa `AIController`, `AIService`, `AIResponseValidator` e `OrderPreviewValidator`. Sua resposta tem `items`, IDs de produto/variação, quantidade, preços locais, estoque disponível, `issues`, `warnings`, observações e prazo. `Product::find()` carrega tanto `products` quanto `product_variants`; não existe um model separado de variações. A validação anterior já agrega estoque de linhas repetidas e continua responsável pela conferência inicial da IA.

1. O Assistente interpreta a mensagem pelo fluxo existente e entrega o resultado à conferência. A revisão também pode começar manualmente, sem IA.
2. `order-review.js` carrega os produtos selecionados e oferece um modal Bootstrap com pesquisa por nome e paginação de dez produtos ativos por página. Produtos e variações usam selects; quantidades usam campos numéricos.
3. O usuário troca produtos/variações, adiciona/remove linhas e edita quantidades, observações e prazo. Os preços não são campos editáveis. A interface calcula em centavos usando `BigInt`.
4. Ao adicionar/corrigir um produto, revalidar ou confirmar, Fetch envia IDs e quantidades a `OrderPreviewController`. `ReviewedOrderValidator` consulta `Product::find()` e usa os preços e estoques atuais, com cache apenas dentro daquela validação.
5. A confirmação bem-sucedida guarda a prévia em sessão e mostra a mensagem solicitada, acompanhada do aviso de que o pedido ainda não foi registrado definitivamente.
6. Editar itens, observações ou prazo invalida a confirmação e exige nova conferência. Uma nova interpretação também invalida a sessão. Recarregar a página recupera somente uma prévia previamente confirmada.

## Contrato e regras

`GET /api/orders/catalog?search=camiseta&page=1` retorna produtos ativos, preços, estoque e variações. `?id=1` recupera um produto ativo para apresentar os itens interpretados ou restaurados. O endpoint reaproveita `Product::paginate()` e `Product::find()`, sem duplicar consultas SQL no controller.

```json
{
  "action": "confirm",
  "revision": 0,
  "items": [{"product_id": 1, "variant_id": 2, "quantity": "3"}],
  "notes": "Embalar separadamente",
  "requested_deadline": "sexta-feira"
}
```

Esse corpo é enviado a `POST /api/orders/validate` com `Content-Type: application/json` e `X-CSRF-Token`. Use a revisão fornecida pela página ou pela última resposta. `validate` confere sem confirmar; `confirm` confere e mantém a prévia; `invalidate` remove a confirmação e dispensa os itens. Ações compartilham um endpoint para evitar rotas redundantes.

A resposta de validação contém `success`, `validated_items`, `errors`, `warnings`, `estimated_total`, `total_units`, `notes`, `requested_deadline`, `confirmed` e `revision`. `errors` identifica campos por caminhos como `items.0.quantity`; cada item também contém seus erros. Erros de domínio retornam 422; CSRF 403, conteúdo incompatível 415, JSON inválido 400, corpo excessivo 413, revisão desatualizada 409 e falhas internas 500, sem detalhes sensíveis.

- De 1 a 100 linhas; quantidade inteira de 1 a 10.000. Não aceita booleanos, arrays, expoentes, floats, zero ou negativos.
- Produtos existentes e ativos; variação obrigatória quando o produto possuir variações, sempre pertencente a ele.
- Duplicatas não são consolidadas. O servidor e a interface somam o consumo da mesma variação, ou do mesmo produto sem variações, e destacam todas as linhas que excedem o estoque.
- Produtos com variações usam o estoque da variação. O preço é único por produto, conforme o schema existente.
- Dinheiro usa strings decimais e centavos inteiros; valores do cliente nunca substituem os do banco. O total parcial exclui linhas inválidas; a contagem de unidades soma quantidades sintaticamente válidas, inclusive linhas com outras pendências.
- Observações: 2.000 caracteres; prazo: 200; corpo: 64 KiB. O prazo relativo é preservado como texto.
- PDO e prepared statements existentes, CSRF, escape PHP e criação de elementos DOM por `textContent`/`Option`. Requisições de confirmação bloqueiam os controles e evitam reenvio concorrente.

## Sessão e concorrência

`confirmed_order_preview` guarda somente IDs, quantidades, preços/subtotais consultados, totais, observações, prazo, `confirmed_at` em UTC e `requires_revalidation: true`. Não guarda catálogo completo nem conteúdo bruto do provedor. A revisão monotônica fica em `order_preview_revision`.

Cada validação/invalidação remove a confirmação anterior e incrementa a revisão; somente uma confirmação válida recria o snapshot. O lock da sessão PHP serializa requisições e a revisão rejeita operações antigas de outras abas. O navegador enfileira invalidações antes da confirmação ou de nova interpretação. O catálogo é recarregado antes de liberar os controles após interpretar.

O snapshot não representa reserva de estoque nem autorização permanente para salvar. A futura Etapa 05 deve reler produtos, preços e estoque no momento da gravação. Não existe código dessa etapa nesta entrega.

## Arquivos criados e modificados

| Arquivo | Papel |
| --- | --- |
| `app/Controllers/OrderPreviewController.php` (novo) | Catálogo de revisão e endpoint de validação/confirmação |
| `app/Validation/ReviewedOrderValidator.php` (novo) | Validação independente da IA, estoque agregado e totais |
| `app/Services/OrderPreviewSession.php` (novo) | Snapshot temporário, invalidação e revisão |
| `public/assets/js/order-review.js` (novo) | Edição, modal, cálculo, Fetch e restauração |
| `tests/orders.php` (novo) | Testes de regras e sessão sem rede/banco |
| `tests/orders_mysql.php` (novo) | Banco isolado, preço atualizado e execução opcional do navegador |
| `tests/orders_browser.cjs` (novo) | Fluxos HTTP e interface, IA simulada |
| `docs/ETAPA-04.md` (novo) | Documentação desta entrega |
| `app/Controllers/AIController.php` | Invalida a confirmação ao interpretar e retorna a revisão |
| `public/assets/js/assistant.js` | Entrega a interpretação ao editor e coordena carregamento |
| `app/Views/ai/index.php` | Conferência, formulário de observações/prazo, modal e snapshot escapado |
| `app/Views/layouts/app.php` | Carrega o editor e atualiza identificação da versão |
| `public/assets/css/app.css` | Larguras mínimas dos controles dentro da tabela responsiva |
| `routes/web.php` | Duas rotas da conferência |
| `composer.json` | Inclui os testes de regras da Etapa 04 |
| `tests/ai_browser.cjs` | Adapta a regressão de interface para a conferência editável |
| `README.md` | Uso, regras, sessão, comandos e limitações |

## Como executar os testes

```powershell
C:\xampp\php\php.exe .tools/composer.phar test
C:\xampp\php\php.exe tests/products_mysql.php
C:\xampp\php\php.exe tests/ai_mysql.php
C:\xampp\php\php.exe tests/orders_mysql.php --browser
```

Alternativa sem Composer: execute `tests/run.php`, `tests/ai.php` e `tests/orders.php` diretamente com PHP. Para testar apenas a integração MySQL desta etapa, execute `tests/orders_mysql.php` sem `--browser`.

O MySQL deve estar iniciado e o usuário configurado deve poder criar/remover bancos. Cada teste cria somente um banco aleatório com prefixo `ai_order_test_` e o remove em `finally`. Não importa seeds no banco de desenvolvimento nem altera `.env`. O teste de navegador exige Node.js, Playwright instalado no projeto ou em `.qa`, e Microsoft Edge. O servidor temporário usa configuração de ambiente própria e é encerrado no final. Capturas locais são gravadas em `.qa/orders-desktop.png` e `.qa/orders-mobile.png` (diretório ignorado pelo Git).

### Roteiro dos 18 cenários

Cadastre uma Camiseta de R$ 39,90 com variações Preta / M (estoque 10) e Branca / G (estoque 8), além de um produto sem variações. Para testes destrutivos de catálogo, prefira o banco isolado automatizado.

| Cenário | Procedimento e resultado esperado | Cobertura automatizada |
| --- | --- | --- |
| 1. Confirmar prévia válida | Adicione 3 Preta / M e confirme: R$ 119,70, sucesso e snapshot recuperável após recarregar | Regras, HTTP e navegador |
| 2. Corrigir produto | Use Trocar/Selecionar produto, pesquise e aplique outro produto; preço muda | Navegador |
| 3. Trocar variação | Selecione Branca / G; estoque passa a 8 | Regras e navegador |
| 4. Alterar quantidade | Altere 3 para 4; subtotal passa a R$ 159,60 | Navegador |
| 5. Remover | Clique Remover; linha, unidades e total são atualizados | Navegador |
| 6. Adicionar manualmente | Use Adicionar produto; escolha produto, variação e quantidade | Navegador |
| 7. Zero | Informe 0; erro no campo e rejeição no servidor | Regras e HTTP |
| 8. Negativo | Informe -1; rejeição | Regras e HTTP |
| 9. Decimal | Informe 1,5/1.5; rejeição, inclusive por JSON direto | Regras e HTTP |
| 10. Produto inexistente | Envie ID inexistente; 422 sem confirmação | Regras e HTTP |
| 11. Variação de outro produto | Envie o ID da variação da camiseta para a caneca; 422 | Regras e HTTP |
| 12. Inativo | Envie ID inativo ou inative antes de revalidar; rejeição | Regras, MySQL e HTTP |
| 13. Estoque insuficiente | Peça 11 Preta / M; erro com estoque disponível | Regras e HTTP |
| 14. Duplicatas | Adicione 6 + 5 Preta / M; ambas as linhas inválidas (total 11 > 10) | Regras, HTTP e navegador |
| 15. Confirmar e editar | Confirme e altere quantidade; sessão perde o snapshot, nova confirmação exigida | Regras, HTTP e navegador |
| 16. Preço atualizado | Altere o preço no cadastro/MySQL antes de confirmar; o novo preço prevalece | Regras e MySQL |
| 17. CSRF | Envie POST sem token válido; 403 | HTTP |
| 18. Lista vazia | Remova tudo; interface não habilita confirmação e POST direto retorna 422 | Regras e HTTP |

Também há testes de preço adulterado no navegador, corpo/JSON malformado, limites Unicode, total monetário máximo, pesquisa de ativos, reenvio concorrente, revisão antiga, erro de rede/servidor com preservação dos campos, conteúdo HTML malicioso, prazo relativo, ausência de novas chamadas IA durante edição e layout em 320/390 px.

### Resultados

Verificados nesta entrega: 73 checks gerais, 105 de IA sem rede, 46 de regras da Etapa 04, 19 de produtos/MySQL, 10 de IA/MySQL, 23 de regressão HTTP/interface IA, 39 de HTTP/interface da Etapa 04 e 8 no runner MySQL com navegador: **323 verificações aprovadas**, sem chamadas reais ao provedor. Sintaxe PHP e JavaScript também verificada. A suíte confirma que produtos e estoques permanecem iguais e que existem somente as duas tabelas do catálogo no banco de teste.

## Limitações e decisões

- Somente a confirmação é mantida em sessão; edições ainda não confirmadas são perdidas ao recarregar. Erros de validação ou comunicação preservam o formulário aberto.
- A sessão segue a duração configurada no PHP; não há persistência definitiva ou autenticação.
- Preço/estoque mostrado na edição é uma prévia. Revalidar/confirmar usa o MySQL atual; recuperar uma sessão não garante disponibilidade.
- Sem rede, o navegador não consegue garantir que uma invalidação chegou ao servidor. A interface exige nova confirmação; reconecte/revalide. A futura Etapa 05 obrigatoriamente revalida o snapshot.
- Outra aba com revisão antiga recebe conflito e deve reabrir a página. Não há mesclagem entre rascunhos.
- A tabela usa rolagem horizontal interna em telas pequenas. Não foram adicionadas bibliotecas de interface além do Bootstrap existente.
- Não há consolidação automática de duplicatas, calendário de entregas ou alteração direta de preços.

Sugestão de commit: `feat(orders): add preview validation and session confirmation`
