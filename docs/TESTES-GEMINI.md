# Integração e testes com Gemini

## Implementação em 31/10/2026

O assistente usa Google Gemini pelo endpoint `POST https://generativelanguage.googleapis.com/v1beta/models/{modelo}:generateContent`, com autenticação no cabeçalho `X-goog-api-key`. A chave fica exclusivamente no `.env` local, ignorado pelo Git. O navegador recebe apenas o resultado validado.

```dotenv
GEMINI_API_KEY=sua_chave_local
GEMINI_MODEL=gemini-3.5-flash-lite
```

O projeto agora utiliza somente Gemini. As variáveis antigas `OPENAI_API_KEY` e `OPENAI_MODEL` não são utilizadas e não há fallback automático para outro provedor ou modelo. Nome e número do projeto Google não precisam ser enviados no `generateContent`; a autenticação usa a chave.

O serviço envia as regras em `systemInstruction`, o catálogo mínimo e a mensagem em `contents` e solicita JSON com `generationConfig.responseMimeType` e `responseJsonSchema`. O modelo é configurável. Mantém saída de até 4.000 tokens, conexão de até 5 segundos, requisição de até 60 segundos (75 segundos no navegador), limite de resposta de 1 MiB e verificação TLS.

O adaptador exige um candidato finalizado com `STOP`, concatena suas partes de texto e ignora partes marcadas como `thought`. Bloqueios do prompt ou do candidato geram recusa; truncamentos e outras finalizações não são aceitos. Erros de credenciais/modelo, quota, indisponibilidade e conexão são apresentados sem expor chave ou corpo bruto do provedor.

`AIResponseValidator` valida o JSON e `OrderPreviewValidator` confere os itens com o cadastro. Preços, estoque e totais continuam sendo calculados localmente. A interpretação não salva pedidos nem modifica estoque.

## Testes automáticos executados

```powershell
C:\xampp\php\php.exe tests/run.php
C:\xampp\php\php.exe tests/ai.php
C:\xampp\php\php.exe tests/ai_mysql.php
$env:AI_ORDER_TEST_URL = 'http://localhost/ai-order/public'
node tests/ai_browser.cjs
```

Resultados: 73 verificações gerais, 105 de IA, 10 de IA/MySQL em banco temporário e 23 de HTTP/interface: **211 verificações aprovadas**. O teste de interface requer Playwright instalado no ambiente de QA e Edge; não adiciona dependências à aplicação. Os testes automáticos simulam o provedor e não fazem chamadas externas.

Os casos Gemini verificam payload, contrato, separação das instruções e mensagem, JSON inválido, campos incompatíveis, ausência/multiplicidade de candidatos, bloqueio do prompt, recusas, truncamento, partes de pensamento, texto em múltiplas partes, modelo inválido, erros HTTP, conexão e timeout. Os testes de negócio e banco preservam a conferência de preços, estoque e ausência de gravações no catálogo.

## Teste real optativo e diagnóstico

```powershell
C:\xampp\php\php.exe tests/gemini_live.php
```

Esse comando realiza uma chamada real com catálogo fictício de uma caneca e pedido de duas unidades. Exige interpretação válida, quantidade 2, ausência de pendências e total local de R$ 49,80. Não acessa o banco e não integra o `composer test`. A execução pode consumir a quota/créditos do projeto.

Na verificação de 31/10/2026, a chave fornecida no cabeçalho do exemplo permitiu consultar a lista de modelos. As chamadas de geração com `gemini-flash-latest` retornaram HTTP 503 `UNAVAILABLE`, com indicação de alta demanda. O teste de `gemini-2.5-flash` retornou HTTP 404: o Google informou que esse modelo não está disponível para novos usuários e indicou `gemini-3.8-flash`. Uma tentativa com o modelo indicado também retornou HTTP 503 por alta demanda.

**Teste real concluído com sucesso após os ajustes descritos abaixo.** O `.env` agora fixa `gemini-3.5-flash-lite`, validado pelo navegador com o catálogo real. O funcionamento da geração depende da disponibilidade do Google; execute o teste optativo novamente quando o serviço estiver disponível. Há no máximo uma nova tentativa para falhas HTTP temporárias; não há troca automática de modelo.

Apache e MySQL locais foram iniciados para verificar catálogo e interface. Nenhum dado de demonstração foi importado no catálogo da aplicação.

## Referências

- [API generateContent: payload, autenticação e respostas](https://ai.google.dev/api/generate-content)
- [Saída estruturada do Gemini](https://ai.google.dev/gemini-api/docs/structured-output)

## Ajustes após o diagnóstico de timeout

A espera foi ampliada de 30 para 60 segundos no PHP e de 45 para 75 segundos no navegador. O PHP local permite 120 segundos de execução. O parâmetro candidateCount foi removido por incompatibilidade documentada com Gemini 3. A nova tentativa limitada está descrita abaixo.

Os erros agora distinguem requisição inválida (400), autenticação (401), permissão do projeto (403), modelo não encontrado (404), cota (429), indisponibilidade (5xx) e timeout de transporte. O log de erros registra somente modelo, status HTTP, código cURL e duração, sem chave ou conteúdo do pedido.

Resultado anterior pelo terminal: o comando tests/gemini_live.php passou com gemini-3.8-flash. A resposta real interpretou duas canecas personalizadas, passou pelos validadores e produziu total local de R$ 49,80, sem gravações no banco. Após as alterações, também passaram as 89 verificações de IA e 23 de HTTP/interface. Esse teste pelo terminal exercitou somente o serviço PHP e os validadores; a validação final pela interface está registrada abaixo. Como modelo e tempo de espera foram ajustados juntos e a disponibilidade do provedor pode variar, o sucesso não permite atribuir a causa exclusivamente ao timeout.

## Falhas intermitentes pela interface

O pedido real "Quero 3 camisetas básicas no tamanho M" foi reproduzido pelo Edge, usando a página do Apache, CSRF real, catálogo MySQL e chamada externa sem mocks. Antes do ajuste, retornou HTTP 503 em cerca de 2 segundos. Os logs das tentativas do usuário também mostraram HTTP 503 em aproximadamente 1,3 a 1,6 segundos, não timeout.

O serviço agora permite no máximo duas chamadas (uma inicial e uma nova tentativa), somente para HTTP 500/502/503/504. Aguarda de 1 a 1,25 segundo entre elas. As duas chamadas e a pausa compartilham o orçamento total de 60 segundos; se restarem menos de 6 segundos após a primeira resposta, encerra sem repetir. Não repete cota (429), configuração/permissão (4xx), respostas inválidas nem timeout, pois nesses casos repetir pode ser inútil ou duplicar uma geração já processada. O log identifica a tentativa. Essa proteção reduz falhas transitórias, mas não garante disponibilidade do Google e pode consumir uma segunda requisição da quota.

Os testes simulados incluem recuperação 503→200, limite de duas chamadas, preservação do payload e ausência de repetição em erros não elegíveis. Resultado: 105 verificações de IA e 23 de HTTP/interface aprovadas após o ajuste.

Teste real optativo pela interface (requer Edge e Playwright já presentes no ambiente de QA):

```powershell
node tests/gemini_browser_live.cjs
```

O comando envia o pedido da captura pelo botão da interface, verifica HTTP 200 e a renderização da prévia e salva uma captura local em `.qa/gemini-live.png`. É separado da rotina sem rede. Uma pendência de cor/tamanho no pedido é um resultado comercial válido; erros da API fazem o teste falhar.

Referência: [estratégia de novas tentativas recomendada pelo Google](https://ai.google.dev/gemini-api/docs/troubleshooting#retry-strategy).

## Resultado final pela interface

O Gemini 3.8 Flash continuou retornando 503 mesmo com uma nova tentativa; em outra execução, a primeira chamada excedeu 60 segundos. Reduzir seu nível de raciocínio para LOW também não resolveu os erros 503. O sucesso isolado pelo terminal não comprovava disponibilidade contínua para o usuário.

Foi então testado `gemini-3.5-flash-lite`, modelo documentado pelo Google para novos projetos e com faixa gratuita. Com esse modelo, o teste real pelo navegador retornou **HTTP 200 em 1,981 segundo** e renderizou:

- Pedido: `Quero 3 camisetas básicas no tamanho M`.
- Produto do catálogo real: Camiseta Básica; variação M; quantidade 3.
- Preço unitário local: R$ 39,90; total local: **R$ 119,70**.
- Item identificado, sem pendências.
- Captura da interface: `.qa/gemini-live.png` (arquivo local de QA, ignorado pelo Git).

A configuração local, o padrão do PHP e o `.env.example` agora usam `gemini-3.5-flash-lite`. A mudança foi explícita após o teste; não há fallback automático entre modelos. Não foi alterado o faturamento do projeto. O parâmetro experimental LOW do 3.8 foi retirado, pois não faz parte da solução final. Permanecem o limite total de 60 segundos, uma única nova tentativa para HTTP temporário e a conferência local de produtos, preços e estoque.

Referências: [modelos Gemini disponíveis](https://ai.google.dev/gemini-api/docs/models) e [faixa gratuita do Gemini 3.5 Flash-Lite](https://ai.google.dev/gemini-api/docs/pricing#gemini-3.5-flash-lite).
