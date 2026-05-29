# Checklist de ajustes QA - 28/05/2026

Documento de acompanhamento baseado em `docs/plano-ajustes-qa-2026-05-28.md`.

Status:

- `✅` Feito e testado.
- `[ ]` Pendente.

## Resumo executivo

- ✅ Etapa 1 concluída: textos, labels e pequenos ajustes de UI.
- ✅ Etapa 1 validada com testes de funcionalidade, suíte completa PHPUnit e Playwright.
- [ ] Etapa 2 pendente: busca, checkout e estados visuais.
- [ ] Etapa 3 pendente: checkout e dados de passageiro.
- [ ] Etapa 4 pendente: painel operacional essencial.
- [ ] Etapa 5 pendente: e-mails e autenticação.

## Etapa 1 - Textos, labels e pequenos ajustes de UI

Objetivo: corrigir comunicação e elementos visuais sem alterar regras profundas.

### Implementação

- ✅ Trocar texto exibido ao clicar em "Comprar" na busca para "Revalidando preço e disponibilidade".
- ✅ Remover link ou texto "Ver política completa" da tela de política de cancelamento e resumo no checkout.
- ✅ Remover cores das labels `IDA` e `VOLTA` em telas de checkout, pedido, acompanhamento, painel e e-mails.
- ✅ Remover cores das labels `IDA` e `VOLTA` no calendário da busca.
- ✅ Adicionar asteriscos aos campos obrigatórios do checkout.
- ✅ Ajustar status de pagamento para cartão em análise:
  - ✅ Exibir "Pagamento em análise".
  - ✅ Não exibir "Aguardando pagamento" para cartão em análise.
- ✅ Ajustar e-mail de status para cartão em análise.
- ✅ Ajustar acompanhamento do pedido para cartão em análise.
- ✅ Ajustar área do cliente para cartão em análise.
- ✅ Ajustar painel para cartão em análise.
- ✅ Adicionar botão "Ir para o pedido" na tela do ticket do cliente.
- ✅ Adicionar ação "Ir para o pedido" na tela de ticket do painel.

### Testes e validações

- ✅ Teste de funcionalidade para renderização da busca e copy de revalidação.
- ✅ Teste de funcionalidade para labels neutras no calendário da busca.
- ✅ Teste de funcionalidade para política de cancelamento sem "Ver política completa".
- ✅ Teste de funcionalidade para checkout com labels neutras e campos obrigatórios com asterisco.
- ✅ Teste de funcionalidade para fluxo de cartão em análise sem botão de verificação manual de pagamento.
- ✅ Teste de funcionalidade para área do cliente exibindo "Pagamento em análise".
- ✅ Teste de funcionalidade para ticket exibindo botão "Ir para o pedido".
- ✅ Teste Feature/Filament para ação "Ir para o pedido" no painel.
- ✅ Teste de funcionalidade de e-mail com status "Pagamento em análise" e labels `IDA`/`VOLTA` neutras.
- ✅ Playwright validando calendário com labels neutras.
- ✅ Playwright validando checkout com asteriscos e política resumida sem link.

### Evidências da etapa 1

- ✅ Formatação executada:
  - `php -d memory_limit=-1 vendor/bin/pint --dirty`
- ✅ Suíte completa PHPUnit executada:
  - `php -d memory_limit=-1 vendor/bin/phpunit --stop-on-failure`
  - Resultado: `263 tests, 1819 assertions`
- ✅ Playwright executado:
  - `npx playwright test tests/e2e/stage1-ui.spec.ts --project=chromium`
  - Resultado: `2 passed`

## Etapa 2 - Busca, checkout e estados visuais

Objetivo: corrigir navegação e deixar o fluxo visual mais claro.

### Implementação

- [ ] Corrigir bug ao voltar na busca.
- [ ] Adicionar skeleton de carregamento até trazer os primeiros resultados na busca.
- [ ] Adicionar ícones de avião de ida e volta acima do horário no checkout.
- [ ] Ajustar componentes de status para preencher com check as etapas já concluídas.
- [ ] Separar visualmente os estados "Pagamento confirmado" e "Aguardando emissão".
- [ ] Implementar validação ou modal de expiração da pesquisa conforme referência visual.

### Testes e validações

- [ ] Testes de funcionalidade para estados de pedido, stepper, status e expiração.
- [ ] Playwright para voltar da busca e validar consistência de filtros e resultados.
- [ ] Playwright para skeleton antes dos primeiros resultados.
- [ ] Playwright para ícones de ida e volta no checkout.
- [ ] Playwright para stepper com checks em pedido avançado.
- [ ] Playwright para checkout com pesquisa expirada e modal.

## Etapa 3 - Checkout e dados de passageiro

Objetivo: corrigir dados obrigatórios e cenários comuns de passageiro sem abrir dependências externas.

### Implementação

- [ ] Telefone com DDI.
- [ ] Máscara universal de telefone.
- [ ] Aceitar telefones brasileiros antigos com 8 dígitos.
- [ ] Aceitar formatos internacionais de telefone.
- [ ] Passageiro estrangeiro deve informar passaporte no checkout em vez de CPF.
- [ ] Sexo deve ser exibido e tratado como obrigatório no checkout.
- [ ] Nacionalidade deve listar opções reais e não permitir "Outro".
- [ ] Alertar que menores de 16 anos não podem viajar desacompanhados ou sem autorização expressa dos responsáveis legais.
- [ ] Validar regra base de passageiro sozinho: menor de 12 anos não deve seguir sozinho.

### Testes e validações

- [ ] Testes unitários e de funcionalidade para validações de `StoreOrderPassengersRequest`.
- [ ] Teste com passageiro brasileiro e CPF.
- [ ] Teste com passageiro estrangeiro e passaporte.
- [ ] Teste com menor de 12 anos.
- [ ] Teste com menor de 16 anos.
- [ ] Teste com adulto.
- [ ] Testes de persistência de documento ou passaporte no pedido.
- [ ] Playwright para checkout com passageiro brasileiro e CPF.
- [ ] Playwright para checkout com estrangeiro e passaporte.
- [ ] Playwright para checkout com telefone internacional.
- [ ] Playwright para menor desacompanhado com alerta ou bloqueio esperado.

## Etapa 4 - Painel operacional essencial

Objetivo: melhorar operação sem criar módulos novos grandes.

### Implementação

- [ ] Criar área de alertas no painel.
- [ ] Tickets prioritários devem aparecer no topo do painel de atendimento.
- [ ] Mostrar badges no menu para cada interação pendente.
- [ ] O botão de cancelamento deve ficar disponível somente via ticket ou para admin full.
- [ ] Registrar logs de todas as ações feitas no painel com usuário responsável.
- [ ] Implementar validação de LOC na emissão.

### Testes e validações

- [ ] Teste Feature/Filament para área de alertas e estados vazios.
- [ ] Teste Feature/Filament para ordenação de tickets prioritários.
- [ ] Teste Feature/Filament para badges e contadores de pendências.
- [ ] Teste Feature/Filament para permissões de cancelamento por perfil.
- [ ] Teste Feature/Filament para criação de logs com usuário responsável.
- [ ] Teste Feature/Filament para validação de LOC.
- [ ] Playwright acessando painel com usuário admin e emissor.
- [ ] Playwright validando área de alertas.
- [ ] Playwright validando ticket prioritário no topo.
- [ ] Playwright validando badge no menu.
- [ ] Playwright validando ação de cancelamento visível ou invisível por perfil.

## Etapa 5 - E-mails e autenticação

Objetivo: padronizar comunicações transacionais e melhorar acesso do cliente sem senha.

### Implementação

- [ ] Revisar e padronizar todos os e-mails transacionais.
- [ ] Padronizar e-mail de redefinição de senha.
- [ ] Padronizar e-mails de cadastro.
- [ ] Padronizar e-mails de pedido.
- [ ] Padronizar e-mails de pagamento.
- [ ] Padronizar e-mails de atendimento.
- [ ] Padronizar e-mails de pós-venda.
- [ ] Implementar autenticação sem senha com código por e-mail.

### Testes e validações

- [ ] Testes de funcionalidade para envio e conteúdo dos e-mails transacionais.
- [ ] Teste de autenticação sem senha com código válido.
- [ ] Teste de autenticação sem senha com código expirado.
- [ ] Teste de autenticação sem senha com código inválido.
- [ ] Teste de reenvio de código.
- [ ] Teste de rate limit para evitar abuso.
- [ ] Playwright para fluxo de redefinição de senha visualmente padronizado.
- [ ] Playwright para login sem senha usando código fake em ambiente de teste.
- [ ] Playwright para mensagens de erro e sucesso no login sem senha.

## Backlog separado

Itens fora do plano enxuto por exigirem decisão externa, jurídica, feature maior ou integração e automação própria.

### Decisões externas ou jurídicas

- [ ] Enviar política de cancelamento para o jurídico antes de congelar texto final.
- [ ] Verificar taxa de estorno com a AppMax.
- [ ] Definir se o calendário deve mostrar valor direto ou apenas barrinhas coloridas.
- [ ] Validar regras de idade mínima por companhia.

### Features grandes de produto

- [ ] Radar de ofertas.
- [ ] Colocar banners.
- [ ] Criar CMS para inclusão de banners.
- [ ] Ofertar bagagem no checkout.
- [ ] Ofertar seguro no checkout.

### Integrações e automações externas

- [ ] Criar cron para verificar pagamentos a cada X minutos.
- [ ] Alterar botão "Confirmar pagamento" para consultar o gateway de fato.
- [ ] Crawler de comparação de preço.
- [ ] Implementar cron para verificar alteração de passagem.
- [ ] Implementar regra operacional de estorno AppMax depois da validação da taxa.
