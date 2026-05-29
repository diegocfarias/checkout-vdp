# Plano enxuto de ajustes QA - 28/05/2026

Este plano consolida os pontos levantados no documento `Testes VOE 28-05-2026.docx`, separando o que pode ser tratado como ajuste direto do que deve virar feature maior, decisao externa ou trabalho dependente de terceiros.

## Premissas

- O plano principal deve priorizar ajustes objetivos, com baixo acoplamento e validacao rapida.
- Features grandes, integracoes externas, decisoes juridicas e mudancas de produto ficam no backlog separado ao final.
- Cada ajuste deve preservar as regras de negocio existentes em `docs/regras-negocio.md`.
- Mudancas no checkout, busca e pos-venda devem ter testes automatizados e validacao visual com Playwright.
- Fluxos administrativos devem ter testes de permissao, status e visibilidade por perfil quando aplicavel.

## Plano principal

### Etapa 1 - Textos, labels e pequenos ajustes de UI

Objetivo: corrigir comunicacao e elementos visuais sem alterar regras profundas.

#### Itens

- Trocar texto exibido ao clicar em "Comprar" na busca:
  - De: mensagem indicando que estamos buscando passagens.
  - Para: "Revalidando preco e disponibilidade".
- Remover link/texto "Ver politica completa" da tela de politica de cancelamento/resumo no checkout.
- Remover cores das labels `IDA` e `VOLTA` em telas e e-mails.
- Adicionar asteriscos aos campos obrigatorios do checkout.
- Ajustar status de pagamento para cartao:
  - Cartao em analise deve exibir "Pagamento em analise".
  - Nao deve exibir "Aguardando pagamento".
- Adicionar botao "Ir para o pedido" na tela do ticket.

#### Testes e validacoes

- Feature tests para renderizacao das telas alteradas.
- Testes de renderizacao de e-mails quando houver labels `IDA` e `VOLTA`.
- Playwright:
  - Busca: clicar em comprar e validar copy de revalidacao.
  - Checkout: validar campos obrigatorios com asterisco.
  - Pedido/ticket: validar botao "Ir para o pedido".
  - Fluxo cartao: validar status "Pagamento em analise".

### Etapa 2 - Busca, checkout e estados visuais

Objetivo: corrigir navegacao e deixar o fluxo visual mais claro.

#### Itens

- Corrigir bug ao voltar na busca.
- Adicionar skeleton de carregamento ate trazer os primeiros resultados na busca.
- Adicionar icones de aviao de ida e volta acima do horario no checkout, conforme referencia visual.
- Ajustar componentes de status para preencher com check as etapas ja concluidas.
- Separar visualmente os estados:
  - "Pagamento confirmado".
  - "Aguardando emissao".
- Implementar validacao/modal de expiracao da pesquisa conforme referencia visual.

#### Testes e validacoes

- Feature tests para estados de pedido, stepper/status e expiracao.
- Playwright:
  - Navegar para busca, selecionar voo, voltar e validar que filtros/resultados continuam consistentes.
  - Validar skeleton antes dos primeiros resultados.
  - Validar icones de ida/volta no checkout.
  - Validar stepper com checks em pedido avancado.
  - Simular checkout/pesquisa expirada e validar modal.

### Etapa 3 - Checkout e dados de passageiro

Objetivo: corrigir dados obrigatorios e cenarios comuns de passageiro sem abrir dependencias externas.

#### Itens

- Telefone com DDI.
- Mascara universal de telefone:
  - Aceitar telefones brasileiros antigos com 8 digitos.
  - Aceitar formatos internacionais.
- Passageiro estrangeiro deve informar passaporte no checkout em vez de CPF.
- Sexo deve ser exibido e tratado como obrigatorio no checkout.
- Nacionalidade deve listar opcoes reais e nao permitir "Outro".
- Alertar que menores de 16 anos nao podem viajar desacompanhados ou sem autorizacao expressa dos responsaveis legais.
- Validar regra base de passageiro sozinho:
  - Menor de 12 anos nao deve seguir sozinho.

#### Testes e validacoes

- Unit/feature tests para validacoes de `StoreOrderPassengersRequest`.
- Testes com passageiro brasileiro, estrangeiro, menor de 12, menor de 16 e adulto.
- Testes de persistencia de documento/passaporte no pedido.
- Playwright:
  - Checkout com passageiro brasileiro e CPF.
  - Checkout com estrangeiro e passaporte.
  - Checkout com telefone internacional.
  - Checkout com menor desacompanhado validando alerta/bloqueio esperado.

### Etapa 4 - Painel operacional essencial

Objetivo: melhorar operacao sem criar modulos novos grandes.

#### Itens

- Criar area de alertas no painel.
- Tickets prioritarios devem aparecer no topo do painel de atendimento.
- Mostrar badges no menu para cada interacao pendente.
- Botao de cancelamento deve ficar disponivel somente:
  - Via ticket.
  - Ou para admin full.
- Registrar logs de todas as acoes feitas no painel com usuario responsavel.
- Implementar validacao de LOC na emissao.

#### Testes e validacoes

- Feature/Filament tests para:
  - Area de alertas e estados vazios.
  - Ordenacao de tickets prioritarios.
  - Badges/contadores de pendencias.
  - Permissoes de cancelamento por perfil.
  - Criacao de logs com usuario responsavel.
  - Validacao de LOC.
- Playwright:
  - Acessar painel com usuario admin e emissor.
  - Validar area de alertas.
  - Validar ticket prioritario no topo.
  - Validar badge no menu.
  - Validar acao de cancelamento visivel/invisivel por perfil.

### Etapa 5 - E-mails e autenticacao

Objetivo: padronizar comunicacoes transacionais e melhorar acesso do cliente sem senha.

#### Itens

- Revisar e padronizar todos os e-mails transacionais.
  - Comecar por redefinicao de senha, que esta fora do padrao.
  - Depois revisar cadastro, pedido, pagamento, atendimento e pos-venda.
- Implementar autenticacao sem senha com codigo por e-mail.

#### Testes e validacoes

- Feature tests para envio e conteudo dos e-mails transacionais.
- Testes de autenticacao sem senha:
  - Codigo valido.
  - Codigo expirado.
  - Codigo invalido.
  - Reenvio de codigo.
  - Rate limit para evitar abuso.
- Playwright:
  - Fluxo de redefinicao de senha visualmente padronizado.
  - Login sem senha usando codigo fake em ambiente de teste.
  - Validar mensagens de erro e sucesso no login sem senha.

## Backlog separado

Os itens abaixo saem do plano enxuto porque sao features grandes, dependem de decisao externa, exigem integracao com terceiros ou abrem uma frente operacional propria.

### Decisoes externas ou juridicas

- Enviar politica de cancelamento para o juridico antes de congelar texto final.
- Verificar taxa de estorno com a AppMax.
- Definir se o calendario deve mostrar valor direto ou apenas barrinhas coloridas.
- Validar regras de idade minima por companhia.

### Features grandes de produto

- Radar de ofertas.
- Colocar banners.
- Criar CMS para inclusao de banners.
- Ofertar bagagem no checkout.
- Ofertar seguro no checkout.

### Integracoes e automacoes externas

- Criar cron para verificar pagamentos a cada X minutos.
- Alterar botao "Confirmar pagamento" para consultar o gateway de fato.
- Crawler de comparacao de preco.
- Implementar cron para verificar alteracao de passagem.
- Implementar regra operacional de estorno AppMax depois da validacao da taxa.

## Ordem recomendada de execucao

1. Etapa 1: textos, labels e pequenos ajustes de UI.
2. Etapa 2: busca, checkout e estados visuais.
3. Etapa 3: checkout e dados de passageiro.
4. Etapa 4: painel operacional essencial.
5. Etapa 5: e-mails e autenticacao.
6. Reavaliar backlog separado e puxar apenas um bloco grande por vez.

## Criterio de pronto por etapa

- Codigo implementado.
- Regras de negocio atualizadas quando houver nova regra.
- Testes automatizados criados ou ajustados.
- Suite relevante passando.
- Validacao visual com Playwright documentada por screenshots ou trace quando aplicavel.
- Nenhum dado tecnico sensivel exposto em telas ou respostas publicas.
