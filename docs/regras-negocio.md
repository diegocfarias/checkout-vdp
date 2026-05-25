# Regras de negocio

Este documento registra as regras esperadas para os fluxos principais. Os testes de especificacao devem usar estes exemplos como fonte de verdade, mesmo que alguma implementacao atual precise ser ajustada.

## Precificacao do checkout

### Componentes do preco

- `valor da passagem`: soma de `money_price` de cada voo por passageiro pagante.
- `taxa`: soma de `tax` de cada voo por passageiro pagante.
- `total bruto`: `valor da passagem + taxa`.
- Passageiros pagantes: adultos + criancas.
- Bebes/infants nao entram no calculo de valor da passagem nem de taxa, salvo regra futura explicita.

### Base de desconto

- Cupom, indicacao e Pix sempre usam apenas o `valor da passagem` como base de desconto.
- Taxas nunca recebem desconto de cupom, indicacao ou Pix.
- Um desconto nunca pode reduzir o valor da passagem abaixo de zero.
- Mesmo com desconto de 100% na passagem, a taxa continua sendo cobrada.

### Cupom

- Codigo de cupom deve ser normalizado sem espacos e em maiusculas.
- Cupom so pode ser aplicado se estiver ativo, dentro do periodo de validade e abaixo do limite de uso.
- Cupom restrito a clientes deve validar o CPF do pagador ou, quando houver cliente logado, o CPF da conta.
- Previa de cupom no checkout nao deve persistir desconto no pedido nem incrementar uso do cupom.
- Uso do cupom deve ser incrementado apenas quando o pedido segue para pagamento com o cupom aplicado.
- Cupom percentual aplica `percentual * valor da passagem`.
- Cupom fixo aplica o valor fixo limitado ao valor da passagem.
- `max_discount`, quando preenchido, limita o desconto percentual.
- Cupom nao acumula com desconto Pix.
- Ao usar cupom no Pix, o total deve ser: `valor da passagem - desconto do cupom + taxa`.

### Indicacao

- Codigo de indicacao deve funcionar com ou sem prefixo `IND-`.
- Indicacao so pode ser aplicada quando o programa estiver ativo e o codigo pertencer a um afiliado valido.
- Cliente nao pode usar o proprio codigo de indicacao.
- Indicacao usa a mesma base de desconto do cupom: somente `valor da passagem`.
- Indicacao pode ou nao acumular com Pix conforme `referral_cumulative_with_pix`.
- O credito gerado para o afiliado tambem usa somente `valor da passagem` como base.
- Credito de indicacao deve ser liberado uma unica vez no ledger da wallet.
- Credito pendente so deve ser liberado quando o pedido indicado estiver pago/em emissao ou concluido.
- Pedido indicado cancelado deve reverter o credito de indicacao pendente ou disponivel.

### Pix

- Pix aplica desconto somente quando o metodo de pagamento e Pix e existe percentual configurado.
- Pix sem cupom aplica `pix_discount_pct * valor da passagem`.
- Pix com cupom nao aplica desconto Pix.
- Pix com indicacao so acumula se `referral_cumulative_with_pix` estiver ativo.
- Exemplo: passagem R$ 1.000,00, taxa R$ 100,00, Pix 3%.
  - desconto Pix: R$ 30,00.
  - total Pix: R$ 1.070,00.

### Wallet

- Wallet e credito financeiro, nao desconto comercial.
- Wallet pode abater passagem e taxa.
- Wallet nao acumula com cupom no fluxo atual.
- Cancelamento/estorno de pedido que usou Wallet deve devolver o saldo usado uma unica vez.
- Quando Wallet e Pix sao usados juntos, o credito da Wallet reduz primeiro o total a pagar; o Pix incide apenas sobre a parte restante da passagem que ainda sera paga.
- Exemplo: passagem R$ 1.000,00, taxa R$ 100,00, Wallet R$ 200,00, Pix 3%.
  - total bruto: R$ 1.100,00.
  - restante apos Wallet: R$ 900,00.
  - desconto Pix sobre R$ 900,00: R$ 27,00.
  - pagamento Pix restante: R$ 873,00.
  - custo total do cliente: R$ 1.073,00, sendo R$ 200,00 em Wallet e R$ 873,00 no Pix.

### Cartao de credito

- Cartao nao recebe desconto Pix.
- Juros de parcelamento incidem depois de cupom/indicacao/wallet.
- Exemplo: passagem R$ 1.000,00, taxa R$ 100,00, cupom R$ 100,00, juros 10%.
  - total antes dos juros: R$ 1.000,00.
  - juros: R$ 100,00.
  - total no cartao: R$ 1.100,00.

## Voos e integracoes

- BDS `PATRIA` representa voo convencional/consolidadora.
- Quando qualquer provedor BDS estiver ativo, a busca deve consultar todos os slots diretos relevantes da BDS: `GOL`, `AZUL`, `LATAM`, `INTERLINE` e `TAP`, alem do `PATRIA`.
- BDS `INTERLINE` deve ser exibido pela companhia aparente quando `CompanhiaAparente` vier preenchida.
- Voos `INTERLINE` com `CompanhiaAparente=AZUL` devem entrar no site como Azul.
- Para `INTERLINE`, quando o melhor valor da BDS for monetario de emissao, o checkout deve usar o valor monetario informado e nao recalcular pelo campo bruto de milhas.
- Taxa final padrao da BDS deve ser `MelhorValor.TotalTaxaEmbarque + TaxaServicoMilha`.
- Quando um voo em milhas da BDS usar precificacao por margem percentual sobre o total da API, a taxa usada nessa precificacao deve ser somente `MelhorValor.TotalTaxaEmbarque`; `TaxaServicoMilha` nao deve entrar no preco final nem na taxa congelada do pedido.
- Quando uma integracao nao retornar taxa preenchida, deve ser aplicada a taxa interna configurada como percentual do valor base da passagem.
- Resultados em milhas e convencionais devem ser exibidos quando a integracao retornar preco valido.
- Deduplicacao ou agrupamento de resultados nao pode descartar voos convencionais ou voos em dinheiro apenas por nao terem milhas.
- O mesmo voo nao deve aparecer como card duplicado para o cliente; opcoes equivalentes devem ser agrupadas quando fizer sentido.
- Informacoes de bagagem retornadas pelas integracoes devem ser preservadas no pedido e exibidas onde houver detalhe de voo.
- Filtros de companhia, paradas e horario devem ser independentes por sentido: ida filtra somente ida e volta filtra somente volta.
- Filtro `Direto` deve esconder opcoes de voo com conexao naquele sentido, mesmo quando o card tambem tiver uma opcao direta.
- Filtro `Conexao` deve esconder opcoes diretas naquele sentido, mesmo quando o card tambem tiver uma opcao com conexao.
- A opcao de voo selecionada no card sempre deve respeitar os filtros ativos.
- Dados tecnicos de origem do provedor nao devem aparecer como tags visiveis para o cliente.
- Busca ida e volta deve exigir data de volta no backend.
- Criacao de pedido via API/bot deve rejeitar origem igual ao destino, cabine invalida e quantidade de bebes maior que a quantidade de adultos.
- Calendario de precos deve exibir apenas niveis visuais de preco (`barato`, `medio`, `caro`), sem expor o preco numerico ao cliente.
- Resposta da API de precos do calendario nao deve expor fonte, fornecedor ou metadados tecnicos da integracao externa.
- Quando precos no calendario estiverem desligados, o endpoint deve retornar niveis vazios sem chamar a integracao externa.

## Precificacao de voos da busca

- A precificacao operacional fica em menu proprio no painel: `Precificacao`.
- Voos em milhas podem ser precificados por milheiro ou por margem percentual sobre o total retornado pela API.
- A precificacao por milheiro usa: `(milhas / 1000) * valor do milheiro + taxa`.
- A precificacao percentual para voos em milhas usa o total da API como base: `(price_money + taxa aplicavel) * (1 + margem / 100)`.
- Para BDS em milhas, a `taxa aplicavel` dessa regra e a taxa normal da companhia, sem `TaxaServicoMilha`.
- Para manter taxa separada no checkout, quando a regra de milhas por percentual for usada, o `money_price` congelado no pedido deve ser `total precificado - taxa`.
- A ordem de prioridade de voos em milhas deve ser configuravel. Metodos sem dados suficientes ou desativados devem ser pulados ate encontrar uma regra aplicavel.
- Voos convencionais devem ter regra separada de percentual. Essa regra aplica margem sobre `price_money` e soma a taxa sem desconto.
- A opcao de milheiro nao deve ser removida.
- Toda alteracao de precificacao deve gerar historico com snapshot das configuracoes aplicadas.
- Deve ser possivel restaurar uma configuracao antiga de precificacao; a restauracao tambem deve criar novo registro no historico.
- Alteracoes ou restauracoes de precificacao devem invalidar cache de busca de voos via `pricing_version`.

## Painel e configuracoes

- Apenas administradores podem acessar configuracoes, cupons, usuarios e recursos administrativos financeiros.
- Emissores e atendentes podem acessar somente as areas operacionais compativeis com seus papeis.
- Criacao de usuario no painel deve salvar senha com hash e respeitar o papel escolhido.
- Alteracoes de precificacao, taxa fallback, desconto Pix, mix de companhias, fornecedores ativos por cia ou BDS Patria devem invalidar o cache de busca de voos.
- Calendario de precos deve ter apenas controle liga/desliga no painel.
- Cupom nunca acumula com desconto Pix no checkout.
- O painel de cupons nao deve oferecer configuracao de acumulacao de cupom com Pix.
- Cupom ja utilizado nao pode ser editado nem excluido pelo painel.

## Pedido

- O pedido deve congelar o valor confirmado no checkout.
- Link de checkout so deve abrir pedido pendente e nao expirado.
- Link de checkout expirado, cancelado ou inexistente deve exibir estado de indisponibilidade e nao permitir pagamento.
- Pedido criado pela busca ou pelo bot deve receber prazo de expiracao conforme configuracao `order_expiration_minutes`.
- Antes de enviar para pagamento, se houver busca original, o sistema deve revalidar o voo e informar alteracao de preco antes de continuar.
- Valor enviado ao gateway deve ser calculado no backend a partir do pedido congelado, descontos, wallet, Pix e juros; o frontend nao e fonte de verdade para total a pagar.
- Pedido pago deve ir para emissao.
- Pedido pago por Wallet integral deve ser marcado como pago sem criar checkout externo.
- Checkout deve aceitar somente formas de pagamento ativas nas configuracoes; metodo desativado nao pode cair para gateway default do `.env`.
- Confirmacao de pagamento deve ser idempotente: callback, webhook ou acao manual repetida nao pode criar mais de uma emissao para o mesmo pedido.
- Pagamento recusado, cancelado ou expirado deve cancelar pedido ainda aguardando pagamento.
- Pagamento Pix antigo expirado nao deve cancelar o pedido se existir outro pagamento ativo no mesmo pedido.
- Um pedido ja concluido nao deve voltar para cancelado por evento tardio de cancelamento/expiracao nem voltar para aguardando emissao por evento tardio de pagamento.

## Cancelamento e reembolso

- Pedido sem pagamento confirmado pode ser cancelado sem multa, sem consulta a fornecedor e sem criar estorno externo.
- Se pedido sem pagamento confirmado tiver usado Wallet pre-autorizada ou debitada, o saldo deve ser liberado/devolvido uma unica vez.
- Cancelamento sem custo deve ser permitido quando solicitado em ate 24 horas da compra/pagamento confirmado e o primeiro embarque estiver a 7 dias ou mais.
- Cancelamento sem custo deve devolver 100% do valor efetivamente pago pelo cliente, incluindo taxa, respeitando o mesmo meio de pagamento quando possivel.
- Cancelamento voluntario fora da janela de 24 horas/7 dias nao gera reembolso ao cliente.
- Cancelamento voluntario fora da janela sem custo pode ser registrado para acompanhamento operacional, mas deve comunicar claramente que nao havera devolucao de valores.
- Pedido pago e ainda nao emitido, quando fora da janela sem custo, pode ser encerrado operacionalmente sem reembolso ao cliente.
- Pedido em emissao ou emitido so pode ser cancelado apos consulta operacional ao fornecedor/companhia e registro do retorno recebido.
- Fora da janela sem custo, passagem, taxas, servicos internos e valores pagos por Wallet/gateway nao devem ser devolvidos em cancelamento voluntario.
- Cancelamento involuntario por cancelamento de voo, alteracao relevante de horario, pretericao, interrupcao de servico, erro de emissao ou indisponibilidade nao causada pelo cliente deve ser tratado como excecao e analisado separadamente pela equipe.
- Alteracao relevante de horario deve considerar, no minimo, os limites regulatorios: mais de 30 minutos em voo nacional ou mais de 1 hora em voo internacional.
- Se o cliente aceitar reacomodacao, remarcacao ou credito em vez de reembolso, o aceite deve ser registrado com valor, validade, fornecedor e condicoes.
- No-show sem solicitacao previa dentro da janela sem custo nao gera reembolso pela politica de cancelamento voluntario.
- Cancelamento parcial por passageiro, trecho ou sentido so deve ser permitido se a companhia/fornecedor permitir e se o valor liquido puder ser calculado de forma auditavel.
- Reembolso nunca pode exceder o valor efetivamente pago pelo cliente.
- Desconto Pix, cupom, indicacao ou promocao nao viram credito em dinheiro no cancelamento.
- Cupom usado em pedido cancelado por falha operacional da VDP ou cancelamento involuntario pode ser reativado manualmente; em cancelamento voluntario, nao deve ser reativado automaticamente.
- Credito de indicacao vinculado a pedido cancelado deve ser revertido quando estiver pendente ou estornado quando ja tiver sido liberado.
- Valor pago com Wallet deve voltar para Wallet somente quando o cancelamento for reembolsavel, respeitando estorno unico e proporcional em reembolso parcial.
- Reembolso de pagamento misto deve preservar a origem dos valores: gateway externo, Wallet, taxa, passagem, multa, taxa interna e valor liquido devolvido.
- Pedido concluido nao deve mudar diretamente para cancelado por webhook tardio; cancelamento pos-conclusao deve ocorrer por fluxo formal de cancelamento/estorno com logs proprios.
- Toda solicitacao de cancelamento deve registrar status operacional, motivo, canal, solicitante, data da solicitacao, data limite do voo, regra aplicada, anexos, logs internos e historico de comunicacao com o cliente.
- Status minimos da solicitacao: `requested`, `quoted`, `accepted`, `processing`, `refunded`, `rejected` e `cancelled`.
- Cliente deve conseguir abrir solicitacao de cancelamento no detalhe do pedido, informando motivo, detalhes opcionais e anexos.
- Solicitacao de cancelamento dentro das regras prioritarias deve virar ticket de atendimento com prioridade `urgent` e destaque no painel.
- Pedido com solicitacao de cancelamento aberta nao deve criar outra solicitacao aberta duplicada para o mesmo cliente e pedido.
- Prazos de reembolso devem aparecer somente quando o cancelamento for reembolsavel; quando o valor estiver sob controle da VDP, o estorno aprovado deve ser processado em ate 7 dias corridos.
- O cliente deve visualizar no pos-venda o resumo do cancelamento, valores aprovados, meio de devolucao, prazo estimado e motivo quando houver rejeicao.

## Emissao

- Ao confirmar pagamento, o pedido deve criar uma unica emissao pendente.
- Emissao pendente fica visivel para emissores ativos na fila.
- Emissao atribuida fica visivel apenas para o emissor responsavel e para admin.
- Emissor nao pode acessar detalhe de emissao atribuida a outro emissor.
- Assumir emissao deve gravar emissor, data de atribuicao e log operacional.
- Devolver emissao deve remover emissor, voltar status para pendente e notificar emissores.
- Reatribuir emissao deve manter log com emissor anterior e novo emissor.
- Para concluir emissao, cada voo do pedido deve ter LOC, taxa paga e custo do milheiro informados.
- LOC deve ser salvo em maiusculo no voo e o pedido deve guardar os LOCs unicos em lista.
- Taxa paga na emissao e informacao operacional por voo; nao deve alterar a taxa cobrada do cliente.
- Custos operacionais de emissao, taxa paga e milheiro nao devem aparecer para o cliente.
- Ao concluir emissao, pedido deve ir para `completed`, emissao deve ir para `completed` e deve ser criado log de conclusao.
- Emissao manual deve registrar a origem usada para emitir: BDS, milheiro/fornecedor, companhia, Travellink ou outro.
- Quando o voo selecionado veio da BDS, o painel do emissor deve mostrar o custo estimado para emitir diretamente na BDS, preservando esse custo a partir do retorno original da busca.
- Integracao Travellink deve ficar configuravel no painel, com chaves, ambiente, busca, emissao manual, emissao automatica e modo teste.
- A busca Travellink entra como fonte propria; voos retornados por ela devem ser marcados internamente com `source_provider = travellink`.
- Emissao Travellink so pode ser usada quando todos os voos selecionados no pedido vieram da Travellink.
- Pedido com voos BDS, VDP ou LATAM Crawler nao pode ser emitido pela Travellink por equivalencia aproximada de voo.
- A acao manual de emissao Travellink deve ficar disponivel na fila de emissoes para pedidos elegiveis.
- Quando `travellink_auto_emission_enabled` estiver ativo, o gatilho deve ser o pedido pago/aprovado entrando em `awaiting_emission`; nao deve exigir acao manual.
- Enquanto `travellink_dry_run` estiver ativo, nenhuma chamada externa de emissao real deve ser executada; o sistema deve apenas validar elegibilidade e registrar log operacional.
- Emissao real Travellink deve seguir a sequencia `Tarifar`, `Reservar`, `IniciarEmissao` e `Emitir`, salvando LOC retornado em maiusculo e bilhetes no log operacional quando retornados.
- Emissao Travellink nao usa custo de milheiro; custo operacional deve considerar taxa paga/fallback da taxa do voo e valor de emissao configurado.
- Os identificadores tecnicos necessarios para tarifar/reservar/emitir na Travellink devem ser armazenados no pedido, mas nao devem aparecer em telas do cliente.

## Indicadores financeiros

- Dashboards financeiros devem considerar apenas pedidos pagos, usando pagamentos com status `paid` ou `paid_at` do pedido como fallback quando nao houver pagamento pago registrado.
- `GMV` deve representar receita total liquida do pedido, somando pagamentos externos capturados e credito Wallet usado.
- `Receita capturada` deve considerar apenas valores pagos por gateways externos, sem incluir Wallet.
- `Margem bruta` deve ser `GMV - custo total`.
- `Custo total` deve somar custo de milhas, taxa paga na emissao e custo operacional de emissao.
- Quando `paid_boarding_tax` estiver preenchida no voo, ela deve ser usada como custo de taxa; caso contrario, usa-se a taxa do voo como fallback.
- Ticket medio deve ser calculado por pedido pago: `GMV / quantidade de pedidos`.
- Filtros financeiros devem afetar totais, graficos e tabela de pedidos de forma consistente.

## Passageiros e documentos

- A quantidade de passageiros enviada no checkout deve ser exatamente adultos + criancas + bebes.
- Passageiro brasileiro deve informar CPF valido.
- Em rotas Mercosul/Brasil, passageiro brasileiro pode seguir sem passaporte.
- Em rotas fora do Mercosul, todo passageiro deve informar passaporte e validade futura, inclusive brasileiro.
- Passageiro estrangeiro deve informar passaporte e validade futura.
- CPF nao pode repetir entre passageiros brasileiros do mesmo pedido.
- Passaporte nao pode repetir entre passageiros do mesmo pedido.
- Data de nascimento deve ser valida e anterior ao dia atual.
- Cliente logado pode salvar passageiro; brasileiro deve ser identificado por CPF e estrangeiro por passaporte.

## Pos-venda do cliente

- A pagina publica de acompanhamento do pedido so pode ser acessada apos validacao por codigo do pedido + documento do passageiro ou por token valido enviado ao cliente.
- Links de acompanhamento enviados por e-mail ou WhatsApp devem conter token valido para abrir o pedido diretamente.
- Codigo do pedido deve ser tratado sem diferenca de maiusculas/minusculas e com espacos removidos.
- Documento informado no acompanhamento deve ser comparado sem mascara.
- Token invalido nao pode liberar a sessao de acompanhamento.
- Pedido concluido deve exibir LOC por voo emitido no acompanhamento e na area logada do cliente.
- Historico do pedido deve exibir os eventos operacionais gravados para o cliente acompanhar a evolucao do status.
- Cliente logado so pode listar e abrir pedidos vinculados ao proprio `customer_id`.
- Detalhe do pedido na area logada deve mostrar voos, bagagens, passageiros, documento/passaporte e dados de emissao disponiveis.
- Cliente pode alterar diretamente apenas nome e telefone no perfil.
- Alteracao de e-mail e documento deve ser feita por solicitacao para analise no painel.
- Solicitacao de alteracao de e-mail deve ter e-mail valido e nao usado por outro cliente.
- Solicitacao de alteracao de documento deve ter CPF valido em formato com ou sem mascara e deve ser salva normalizada.
- Cliente pode ter apenas uma solicitacao pendente por campo.
- Cliente nao pode remover passageiro salvo de outra conta.
- Area de indicacoes deve ficar disponivel apenas para clientes afiliados.

## Cadastro e autenticacao do cliente

- Checkout pode criar cliente pendente automaticamente para vincular pedido ao e-mail do pagador.
- Cliente pendente criado no checkout pode concluir cadastro usando o mesmo e-mail, sem criar duplicidade.
- Cadastro concluido deve ativar o cliente, salvar senha com hash, normalizar CPF e autenticar a sessao do cliente.
- Cadastro nao pode reutilizar e-mail de cliente ativo.
- Login com senha valida deve ativar cliente pendente que ja possui senha.
- Cliente pendente sem senha deve ser direcionado para criacao/redefinicao de senha.
- Redefinicao de senha bem-sucedida deve ativar o cliente e autenticar a sessao.
- Login via Google deve vincular conta existente pelo e-mail antes de criar novo cliente.
- Novo login via Google sem cliente existente deve exigir complemento de cadastro com CPF e telefone.

## Seguranca e privacidade

- API de criacao de pedido usada pelo bot deve exigir chave de API valida.
- Tokens de checkout e acompanhamento nao devem ser previsiveis.
- Dados internos de fornecedor, fonte, custo, milheiro e taxa paga nao devem aparecer em telas do cliente nem em endpoints publicos que nao precisem deles para concluir o fluxo.
- Cliente so pode acessar dados proprios por sessao autenticada, validacao de documento do pedido ou token valido.

## Atendimento e anexos

- Cliente pode abrir atendimento vinculado ao proprio pedido ou sem pedido vinculado.
- Cliente nao pode abrir, visualizar ou responder atendimento de outro cliente.
- Listagem de atendimentos da area do cliente deve exibir apenas tickets do proprio cliente.
- Cliente e equipe podem enviar anexos no atendimento.
- Cada envio aceita ate 5 arquivos de ate 10 MB cada.
- Tipos aceitos: `jpg`, `jpeg`, `png`, `webp`, `pdf`, `doc`, `docx`, `xls`, `xlsx`, `csv`, `txt` e `zip`.
- Imagens e PDF podem abrir em visualizacao inline.
- Outros tipos aceitos devem ser disponibilizados como download.
- Anexos internos nunca aparecem para o cliente e tambem nao podem ser acessados por URL direta.
- Notas internas do painel nunca aparecem para o cliente e nao enviam email externo.
- Resposta publica da equipe aparece para o cliente e envia notificacao externa.
- Cliente nao pode responder atendimento fechado.
- Resposta do cliente em atendimento `awaiting_customer` deve reabrir o status para `in_progress`.
