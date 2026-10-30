# Testes da IA com Gemini — adaptação pendente

## Situação atual

A aplicação usa exclusivamente a Responses API da OpenAI. Gemini ainda não foi implementado nem testado neste projeto. As observações `TODO(Gemini)` marcam os pontos a adaptar; não ativam outro provedor.

Na verificação de 30/10/2026, a configuração local da OpenAI foi corrigida e a chamada real retornou HTTP 429 com `credit_balance_exhausted`. A consulta de modelo retornou HTTP 403 por falta do escopo `api.model.read`; isso não comprova que a chave seja inválida. Não houve interpretação real concluída com sucesso.

Os testes gratuitos executados passaram: 73 verificações gerais, 77 de IA com transporte simulado, 10 de IA/MySQL em banco isolado e 23 de HTTP/interface. Esses 183 resultados verificam o sistema com respostas simuladas, não a qualidade de interpretação de um modelo real.

## Pontos a adaptar

| Local | Observação para a futura integração |
| --- | --- |
| `config/ai.php` e `.env.example` | Definir seleção de provedor e variáveis próprias para chave/modelo Gemini. Guardar valores reais somente no ambiente local. Não reutilizar `OPENAI_API_KEY` para uma chave Google. |
| `app/Services/AIService.php` | Separar o adaptador do provedor; adaptar endpoint, autenticação, instruções, entrada, saída estruturada e leitura da resposta. Mapear limites, erros, bloqueios e respostas incompletas. Preservar timeouts e TLS. |
| `tests/ai.php` | Adicionar fixtures Gemini para sucesso, JSON inválido, saída fora do contrato, bloqueio, truncamento, credenciais inválidas, quota e falha de conexão. Manter os casos OpenAI se esse provedor continuar disponível. |
| `tests/ai_mysql.php` | Adaptar a resposta simulada ao provedor selecionado, preservando o banco temporário e a verificação de preços, estoque e ausência de gravações no catálogo. |
| `tests/ai_browser.cjs` | Preservar o contrato HTTP interno e as simulações de interface. Essas simulações não comprovam conexão nem acesso ao Gemini. |
| `app/Views/ai/index.php` | Atualizar o aviso de envio de dados conforme o provedor efetivamente usado. |
| `README.md` e `docs/ETAPA-03.md` | Atualizar instalação, comandos, limitações e resultados reais somente depois da implementação e execução. |

`AIResponseValidator` e `OrderPreviewValidator` devem continuar validando o contrato interno e consultando preços/estoque locais. A troca de provedor não deve delegar esses valores à IA nem criar pedidos automaticamente.

## Abordagem de testes

1. Manter o teste padrão sem rede e sem necessidade de chave. Adicionar testes do adaptador Gemini com transporte simulado, separados dos testes de regras de negócio.
2. Criar um teste real optativo, executado por comando explícito, usando chave local, modelo disponível na faixa gratuita e dados fictícios. Não adicionar chamadas reais ao `composer test` nem à rotina padrão de CI.
3. Confirmar no Google AI Studio que o projeto está na faixa gratuita e que o modelo possui quota disponível. A disponibilidade e os limites dependem do projeto/modelo; não prometer uso gratuito ilimitado.
4. No teste real, enviar um catálogo pequeno e um pedido inequívoco. Verificar resposta válida, produto, quantidade e passagem pelo validador local. Usar os exemplos do README para avaliar também ambiguidades e informações ausentes, sem exigir redação idêntica a um fixture.
5. Conferir totais com os preços do banco e confirmar que nenhum pedido foi salvo e nenhum estoque foi alterado. Registrar apenas resultado, modelo e erros sanitizados, sem chave ou conteúdo sensível.
6. Interromper diante de indisponibilidade ou quota esgotada. Não fazer fallback automático para a OpenAI paga nem repetição ilimitada. Diferenciar falha de acesso/quota de regressão do código.

## Referências para a implementação

- [Faixa gratuita e cobrança do Gemini](https://ai.google.dev/gemini-api/docs/billing): conferir elegibilidade e limites antes do teste real.
- [Preços e uso de dados por faixa](https://ai.google.dev/gemini-api/docs/pricing): usar dados fictícios nos testes gratuitos.
- [Documentação da API Gemini](https://ai.google.dev/gemini-api/docs): confirmar o protocolo e a saída estruturada do modelo escolhido durante a implementação.

Este plano registra uma mudança futura. A integração atual permanece OpenAI e os testes simulados continuam sendo o caminho disponível sem créditos ou novas credenciais.
