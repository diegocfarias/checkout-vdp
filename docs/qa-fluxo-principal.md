# Plano de QA - fluxo principal de compra

Este documento orienta a validacao do ambiente de desenvolvimento/homologacao do Voe de Primeira com foco no fluxo principal de compra, incluindo site publico, checkout, pos-venda, atendimento, emissao e painel administrativo.

O objetivo e reduzir custo e tempo de QA sem perder cobertura dos caminhos que mais impactam receita, operacao e cliente final.

## 1. Escopo

### Dentro do escopo

- Busca de voos na home e tela de resultados.
- Calendario com barras de preco por nivel.
- Resultados vindos dos provedores ativos, incluindo voos em milhas e convencionais.
- Filtros de ida e volta: companhia, paradas e horarios.
- Exibicao de bagagens onde houver detalhe de voo.
- Selecionar voo e criar pedido.
- Checkout com passageiros, pagador, cupom, indicacao, wallet, Pix e cartao.
- Revalidacao de preco antes de criar o pedido e antes de pagar.
- Pagamento aprovado, pendente, recusado/cancelado e expirado.
- Pos-venda: acompanhamento do pedido, area do cliente, pedidos, passageiros salvos, perfil, solicitacoes de alteracao e atendimento.
- Painel: pedidos, emissao, atendimento, clientes, cupons, indicacoes, usuarios, configuracoes e dashboard financeiro.
- Permissoes basicas por perfil: admin, emissor e atendente.
- Fluxo operacional completo: pedido pago -> fila de emissao -> assumir -> emitir -> pedido completo.

### Fora do escopo, salvo se houver tempo

- Carga pesada e stress test.
- Auditoria de seguranca profunda.
- Validacao fiscal/contabil completa.
- Testes exaustivos de todos os navegadores antigos.
- Testes de todos os gateways com cartoes reais. Usar sandbox ou confirmacao manual quando necessario.

## 2. Ambientes e acessos

Validar somente no ambiente dev/homologacao.

- Site publico: informar URL do ambiente dev.
- Painel: `/admin`.
- Cliente: `/login`, `/registro`, `/minha-conta`.
- Acompanhamento de pedido: `/pedido`.

Perfis necessarios:

- Admin: acesso total ao painel.
- Emissor: acesso a fila de emissoes, minhas emissoes e detalhes permitidos.
- Atendente: acesso aos tickets de atendimento permitidos.
- Cliente comum: cria compra, acompanha pedidos e abre tickets.
- Cliente afiliado: testa indicacao, wallet e saldo.

Dados sugeridos:

- Usar e-mails com prefixo de QA, por exemplo `qa+cliente01@dominio.com`.
- Usar CPFs validos de teste, nao documentos reais.
- Usar telefones ficticios.
- Usar cartoes/gateways apenas em sandbox. Se nao houver sandbox funcional, usar confirmacao manual de pagamento pelo painel para seguir o fluxo de emissao.

## 3. Severidade e evidencia

Classificacao sugerida:

- P0: bloqueia compra, pagamento, emissao ou exposicao indevida de dados.
- P1: erro relevante no fluxo principal, calculo de preco, desconto, taxa, permissao ou pos-venda.
- P2: falha visual, texto confuso, problema de usabilidade ou comportamento secundario.
- P3: melhoria.

Para cada bug, registrar:

- Ambiente, URL, usuario/perfil e horario.
- Passos para reproduzir.
- Resultado esperado e resultado obtido.
- Evidencia: print, video curto, payload/response quando aplicavel.
- Codigo do pedido, rota, datas, cia, metodo de pagamento e cupom usado.

## 4. Fluxo publico de compra

### 4.1 Home e acesso a busca

Orientacao:

- A home deve permitir iniciar a busca principal de voos.
- A home deve focar no formulario de busca e nos caminhos principais do cliente.

Validar:

- Home carrega sem erro em desktop e mobile.
- Formulario de busca fica visivel e utilizavel.
- Origem, destino, datas, passageiros e cabine podem ser preenchidos.
- Texto de Pix aparece como "Desconto no Pix", sem textos antigos, se exibido na tela.
- Layout nao quebra em desktop e mobile.

### 4.2 Formulario de busca

Orientacao:

- O usuario informa origem, destino, ida/volta ou somente ida, datas, adultos, criancas, bebes e cabine.
- Adultos sao obrigatorios; bebes nao podem exceder adultos.
- Origem e destino devem ser diferentes.

Validar:

- Busca por cidade/aeroporto encontra opcoes esperadas.
- Nao permite origem igual ao destino.
- Nao permite data passada.
- Em ida e volta, volta nao pode ser antes da ida.
- Contadores respeitam limites e validacoes.
- Cabine economica/executiva e enviada corretamente.
- Mensagens de validacao sao claras.

### 4.3 Calendario com niveis de preco

Orientacao:

- Apos origem e destino preenchidos, o calendario consulta niveis de preco.
- Deve exibir apenas barras coloridas, sem valor numerico.
- Cores: verde barato, amarelo medio, vermelho caro.
- A consulta inicial deve trazer uma janela maior de datas e, ao avancar no calendario, carregar novos periodos.
- A feature pode ser ligada/desligada no painel em Configuracoes > Busca de Voos > Precos no calendario.

Validar:

- Barras aparecem apos preencher origem e destino.
- Nao aparece valor em reais no dia.
- Ao trocar origem/destino, dados antigos somem e novos sao buscados.
- Ao avancar meses, novas datas recebem barras sem piscar preco antigo.
- Ao selecionar data, barras nao ficam piscando nem alternando com logica antiga.
- Quando desligado no painel, nenhuma barra aparece.
- A resposta da API interna de calendario nao expoe campo `source`.
- Falha da API de calendario nao bloqueia a busca de voos.

### 4.4 Resultados de busca

Orientacao:

- Os provedores sao configurados por cia no painel.
- Pode haver VDP, LATAM Crawler, BDS Crawler e BDS convencional/Patria.
- Resultados devem incluir opcoes em milhas e convencionais quando o provedor retornar.
- Voos iguais nao devem aparecer duplicados de forma indevida.
- Cards agrupam combinacoes de ida e volta e mostram preco total por adulto, taxas, desconto Pix quando aplicavel e opcoes de voos.

Validar:

- Busca retorna cards ou mensagem amigavel quando nao houver resultado.
- Resultados aparecem progressivamente por provedor, sem travar a pagina.
- GOL, Azul, LATAM e voos convencionais aparecem quando configurados e retornados pela integracao.
- A rota testada na BDS com mais resultados nao perde voos convencionais no site.
- Card mostra cia, numero do voo, horarios, aeroportos, duracao, direto/conexao e data correta.
- Ida e volta aparecem no card certo.
- Tags tecnicas antigas, como `bds_crawler` e `milhas`, nao aparecem para o usuario.
- Preco total soma passagem + taxa.
- Taxa de embarque aparece no resumo quando existir.
- Desconto Pix e economia aparecem corretamente.
- Bagagens aparecem nos cards quando a integracao retorna dados.
- Detalhes de conexao abrem e exibem segmentos, espera e aeroportos.
- Estado de loading e falha parcial de provedor sao aceitaveis.

### 4.5 Filtros de resultados

Orientacao:

- Filtros de companhia e paradas sao separados por ida e volta.
- Periodo de horario tambem e separado por ida e volta.

Validar:

- Companhia ida filtra somente voos de ida.
- Companhia volta filtra somente voos de volta.
- Paradas ida "Direto" nao deixa passar voo de ida com conexao.
- Paradas volta "Direto" nao deixa passar voo de volta com conexao.
- Filtro "Conexao" mostra somente trechos com conexao no respectivo sentido.
- Combinacao de filtros nao exibe card que descumpra algum filtro ativo.
- Contador de filtros ativos funciona no desktop e mobile.
- Limpar filtros restaura resultados.
- Filtros funcionam depois que novos provedores carregam resultados.

### 4.6 Selecao de voo e revalidacao

Orientacao:

- Ao selecionar voos, o sistema revalida disponibilidade e preco.
- Se o preco mudou, deve mostrar tela/modal de alteracao antes de continuar.
- Se o voo nao estiver mais disponivel, deve orientar o cliente a escolher outra opcao.

Validar:

- Ida simples cria pedido com um trecho.
- Ida e volta exige selecao de ida e volta.
- Selecao sem volta em busca ida e volta mostra erro.
- Preco zerado ou invalido nao permite avancar.
- Revalidacao mantem dados corretos de provedor, tipo de preco, taxa, bagagem e conexoes.
- Tela de preco alterado mostra valor antigo, novo, diferenca e permite confirmar ou voltar.
- Pedido criado tem token unico e codigo de rastreio.

## 5. Checkout

### 5.1 Resumo do pedido

Orientacao:

- O resumo mostra voos selecionados, valor por passageiro, taxa, total, desconto Pix/cupom quando aplicavel e validade do pedido.

Validar:

- Link `/r/{token}` abre enquanto pedido esta pendente e nao expirado.
- Link expirado, cancelado ou inexistente mostra pagina de pedido indisponivel.
- Voos, horarios, aeroportos, datas, conexoes e bagagens estao corretos.
- Total por adulto/crianca ignora bebe quando aplicavel.
- Taxa sempre soma ao valor da passagem.
- Descontos sao calculados sobre valor da passagem, sem incluir taxa.
- Pix nao acumula com cupom.
- Wallet nao deve ser usado junto com cupom quando a regra bloquear.

### 5.2 Passageiros

Orientacao:

- O checkout coleta dados dos passageiros e do pagador.
- Para rotas Mercosul/Brasil, CPF e requerido para brasileiros.
- Para rotas fora do Mercosul ou estrangeiros, passaporte e validade podem ser requeridos.
- Cliente logado pode salvar passageiros.

Validar:

- Quantidade de formularios bate com adultos + criancas + bebes.
- CPF invalido e CPF duplicado no mesmo pedido sao barrados.
- Data de nascimento futura e barrada.
- Passaporte duplicado no mesmo pedido e barrado.
- Validade de passaporte no passado e barrada.
- Campos obrigatorios mudam corretamente por nacionalidade e rota.
- Passageiro salvo aparece na area do cliente.
- Cliente logado consegue reutilizar passageiro salvo.
- Pagador exige nome, e-mail e CPF validos.

### 5.3 Cupom e indicacao

Orientacao:

- Cupom pode ser percentual ou fixo, com limite de uso, validade e restricao por cliente.
- Indicacao usa codigo de afiliado e pode gerar desconto ao indicado e credito ao afiliado.
- Desconto e aplicado apenas sobre passagem, nao sobre taxa.
- Cupom nao acumula com Pix.

Validar:

- Cupom valido aplica desconto correto.
- Cupom percentual respeita desconto maximo.
- Cupom fixo nao deixa total negativo.
- Cupom expirado, inativo ou fora do periodo e recusado.
- Cupom com limite esgotado e recusado.
- Cupom restrito a cliente/documento nao funciona para outro cliente.
- Cupom aplicado no preview e persistido no pedido pago.
- Uso do cupom incrementa contador.
- Indicacao valida aplica desconto e cria registro de indicacao.
- Mesmo CPF do afiliado nao deve conseguir usar a propria indicacao.
- Pix nao acumula com cupom; se houver cupom, total Pix nao aplica desconto Pix.
- Configuracao de indicacao cumulativa com Pix e respeitada quando aplicavel.

### 5.4 Wallet

Orientacao:

- Wallet vem do sistema de indicacoes.
- Cliente afiliado pode usar saldo disponivel em compra futura.

Validar:

- Saldo disponivel aparece para cliente logado e afiliado.
- Cliente sem saldo nao ve/aplica valor indevido.
- Uso de wallet reduz o total corretamente.
- Wallet nao reduz taxa se a regra atual for limitar desconto ao valor de passagem.
- Se wallet quitar 100% do pedido, pagamento externo nao e criado e pedido vai para emissao.
- Ao cancelar pedido, saldo usado deve ser estornado.
- Historico de wallet aparece na area de indicacoes.

### 5.5 Pix

Orientacao:

- Pix pode usar AbacatePay, AppMax ou C6 Bank conforme configuracao.
- Desconto Pix incide sobre passagem, nao sobre taxa.
- Pix nao acumula com cupom.

Validar:

- Se Pix esta habilitado, opcao aparece no checkout.
- Se Pix esta desabilitado, opcao nao aparece e validacao backend rejeita.
- Total Pix considera taxa integral.
- QR Code/copia e cola ou redirect de pagamento aparece conforme gateway.
- Pedido muda para `awaiting_payment` apos gerar pagamento.
- Callback/webhook aprovado muda para `awaiting_emission`.
- Pagamento expirado muda pagamento para expirado e pedido para cancelado.
- Falha do gateway mostra tela de aguardando pagamento sem quebrar o checkout.

### 5.6 Cartao de credito

Orientacao:

- Cartao usa gateway configurado no painel.
- Parcelas e juros sao configurados por gateway.

Validar:

- Se cartao esta habilitado, opcao aparece.
- Se cartao esta desabilitado, opcao nao aparece e backend rejeita.
- Parcelas respeitam limite do gateway ativo.
- Juros por parcela alteram total corretamente.
- Endereco de cobranca e requerido para cartao.
- Cartao aprovado muda pedido para `awaiting_emission`.
- Cartao recusado/cancelado muda pedido para cancelado ou mantem estado esperado conforme gateway.
- Erros de gateway sao tratados com mensagem/tela amigavel.

## 6. Pos-venda do cliente

### 6.1 Acompanhamento de pedido

Orientacao:

- Cliente pode acessar o pedido por codigo de rastreio + CPF.
- Apos pagamento aprovado, o cliente e redirecionado para acompanhamento.

Validar:

- `/pedido` exige codigo e CPF.
- Codigo/CPF corretos exibem pedido.
- CPF errado nao exibe pedido.
- Token valido na URL permite acesso quando previsto.
- Status exibidos acompanham mudancas: pendente, aguardando pagamento, aguardando emissao, emitido, cancelado.
- Historico de status aparece em ordem.
- LOC aparece quando pedido e emitido.
- Voos, passageiros e pagamentos estao corretos.

### 6.2 Cadastro, login e area do cliente

Orientacao:

- Cliente pode se cadastrar, logar, completar cadastro, recuperar senha e usar login Google quando configurado.

Validar:

- Registro cria cliente e permite login.
- Login com senha incorreta falha.
- Logout funciona.
- Recuperacao de senha envia fluxo correto quando e-mail esta configurado.
- Cliente com cadastro incompleto e direcionado para completar cadastro.
- Dashboard mostra pedidos recentes.
- Lista de pedidos mostra apenas pedidos do cliente logado.
- Detalhe de pedido de outro cliente retorna 404/negado.
- Perfil permite alterar nome e telefone.
- CPF/e-mail sensiveis usam solicitacao de alteracao, nao alteracao direta pelo cliente.

### 6.3 Passageiros salvos

Validar:

- Passageiro salvo no checkout aparece em `/minha-conta/passageiros`.
- Remover passageiro remove apenas do cliente logado.
- Cliente nao consegue remover passageiro de outro cliente.

### 6.4 Solicitacoes de alteracao

Validar:

- Cliente solicita alteracao de e-mail.
- Cliente solicita alteracao de CPF.
- Nao permite duas solicitacoes pendentes para o mesmo campo.
- Solicitacao aparece no painel para admin.
- Aprovacao altera dado do cliente e registra auditoria.
- Rejeicao exige motivo e nao altera dado.

### 6.5 Atendimento e anexos

Orientacao:

- Cliente pode abrir ticket vinculado ou nao a pedido.
- Cliente e atendente podem anexar arquivos.
- Notas internas do painel nao aparecem para o cliente.
- Anexos podem ser visualizados ou baixados conforme tipo.

Validar:

- Cliente abre ticket com assunto e mensagem.
- Cliente pode vincular ticket a um pedido proprio.
- Cliente nao consegue vincular pedido de outro cliente.
- Anexos aceitos: jpg, jpeg, png, webp, pdf, doc, docx, xls, xlsx, csv, txt, zip.
- Limite: ate 5 arquivos, ate 10 MB por arquivo.
- Arquivo invalido ou grande demais e rejeitado.
- Cliente ve e baixa anexos proprios e anexos publicos do atendente.
- Cliente nao ve nota interna nem anexo interno.
- Atendente/admin responde ticket e cliente ve resposta.
- Ticket fechado nao permite resposta do cliente.
- Visualizacao inline funciona para imagens e PDF; outros arquivos baixam.
- Permissao: cliente nao acessa anexo de outro cliente/ticket.

## 7. Painel administrativo

### 7.1 Login e permissoes

Validar:

- Admin acessa todas as areas.
- Emissor acessa somente fluxo operacional permitido.
- Atendente acessa atendimento permitido.
- Usuario inativo nao deve operar fluxos.
- Menus restritos nao aparecem para perfis sem acesso.
- Acesso direto por URL restrita retorna bloqueio/403.

### 7.2 Configuracoes

Orientacao:

- Configuracoes controlam provedores, calendario, precificacao, fallback de taxa, gateways, Pix, cartao, indicacoes e emissao.

Validar:

- Salvar configuracoes persiste valores.
- Provedores por cia alteram resultados da busca.
- BDS Patria liga/desliga convencionais conforme regra.
- Mix de companhias liga/desliga combinacoes de cias diferentes.
- Precos no calendario liga/desliga barras no calendario.
- Valor do milheiro altera preco de voos com milhas.
- Percentual altera preco de voos convencionais quando aplicavel.
- Fallback de taxa e usado quando integracao retorna taxa vazia/zero.
- Gateway Pix/cartao liga/desliga opcoes no checkout.
- Desconto Pix altera total somente sobre passagem.
- Parcelas e juros por gateway refletem no checkout.
- Tempo de expiracao de pedido e Pix e respeitado.
- Valor por emissao e congelado ao concluir emissao.

### 7.3 Pedidos

Validar:

- Lista exibe pedidos recentes, codigo, rota, passageiro, status, valor e data.
- Filtro por status funciona.
- Busca por codigo/passageiro funciona.
- Detalhe mostra pedido, cliente, cupom, voos, bagagens, conexoes, passageiros, pagamentos e historico.
- Confirmar pagamento manual muda para `awaiting_emission` e cria emissao.
- Marcar como emitido exige LOC por trecho e muda para `completed`.
- Cancelar pedido muda para `cancelled`.
- Estornar chama gateway quando houver pagamento e muda estado conforme resultado.
- Valores no painel batem com checkout e dashboard financeiro.

### 7.4 Emissao

Orientacao:

- Pedido pago gera item na Fila de Emissoes.
- Emissor assume uma emissao, preenche LOC, taxa paga e custo do milheiro por voo.
- Concluir emissao completa o pedido.

Validar:

- Pagamento aprovado cria `OrderEmission` pendente.
- Fila mostra codigo, rota, passageiro, data, milhas, status e tempo na fila.
- Emissor consegue assumir emissao pendente.
- Dois emissores nao conseguem assumir a mesma emissao ao mesmo tempo.
- Emissor consegue devolver emissao para fila.
- Admin consegue reatribuir emissao.
- Detalhe de emissao mostra card para emissao, passageiros, documentos, bagagens e dados para copiar.
- Concluir exige LOC, taxa paga e custo do milheiro por trecho.
- Ao concluir, pedido muda para `completed`, LOC fica visivel ao cliente e dashboard financeiro passa a computar custos.
- Historico de logs da emissao registra criado, assumido, devolvido, reatribuido e concluido.
- Minhas Emissoes mostra apenas emissoes do emissor logado.
- Dashboard Emissoes mostra pendentes, atribuidas, concluidas e tempo medio coerentes.

### 7.5 Atendimento no painel

Validar:

- Dashboard Atendimento lista tickets relevantes.
- Tickets mostram assunto, cliente, pedido, atendente, status, prioridade e ultima resposta.
- Atendente pode pegar ticket sem responsavel.
- Admin pode atribuir ticket a atendente.
- Admin altera prioridade.
- Responder com mensagem publica envia resposta visivel ao cliente.
- Responder com nota interna nao aparece para cliente e nao deve enviar e-mail externo.
- Anexo publico aparece para cliente; anexo interno nao aparece.
- Resolver e fechar alteram status e timestamps.
- Atendente sem permissao nao acessa ticket atribuido a outro quando regra bloquear.

### 7.6 Clientes, indicacoes e wallet

Validar:

- Clientes listam nome, e-mail, CPF, status, Google, afiliado e pedidos.
- Detalhe do cliente mostra pedidos, solicitacoes, auditoria e dados de afiliado.
- Editar dados sensiveis pelo admin altera e gera auditoria.
- Tornar cliente afiliado gera codigo unico.
- Codigo de afiliado nao conflita com cupom existente.
- Configurar percentuais especificos por afiliado funciona.
- Pedido com indicacao cria registro em Indicacoes.
- Credito fica pendente/disponivel conforme configuracao.
- Wallet usado e creditos aparecem no historico do afiliado.
- Cancelamento reverte credito de indicacao e estorna wallet quando aplicavel.

### 7.7 Cupons

Validar:

- Criar cupom percentual.
- Criar cupom fixo.
- Cupom com desconto maximo.
- Cupom com limite de usos.
- Cupom com data de inicio e fim.
- Cupom restrito a cliente.
- Cupom usado nao pode ser editado/excluido se a regra bloquear.
- Listagem, filtros e visualizacao funcionam.

### 7.8 Usuarios, emissores e atendentes

Validar:

- Admin cria usuario admin, emissor e atendente.
- Senha e obrigatoria na criacao e opcional na edicao.
- E-mail unico e validado.
- Emissor pode receber Pushover User Key.
- Usuario inativo nao aparece nos fluxos operacionais.
- Nao permite excluir o proprio usuario quando regra bloquear.
- Recursos especificos de Emissores e Atendentes criam usuarios com papel correto.

### 7.9 Dashboard financeiro

Orientacao:

- Considera pedidos pagos e calcula GMV, receita capturada, descontos, wallet, custos, margem, Pix, cartao, taxas pagas e custo de milhas.

Validar:

- Filtro de data altera totais e graficos.
- Filtros de metodo, gateway, status, cia, cupom, emissor, dispositivo, origem e destino funcionam.
- GMV inclui wallet usado.
- Receita capturada considera pagamentos externos.
- Custo de milhas usa custo do milheiro informado na emissao.
- Taxas pagas usam taxa paga na emissao.
- Custo de emissao usa valor configurado ao concluir.
- Margem = GMV - custos.
- Tabela de pedidos pagos bate com totalizadores.
- Resetar filtros volta ao periodo inicial.

## 8. Integracoes e webhooks

Validar com sandbox quando possivel:

- BDS Crawler: busca por GOL, Azul, LATAM e convencional/Patria.
- BDS Crawler: taxa final usada no checkout inclui as taxas esperadas.
- LATAM Crawler: busca LATAM e taxa/preco corretos.
- VDP API: se configurada, responde ou falha sem bloquear demais provedores.
- Calendario 123: retorna niveis de preco e falha sem quebrar busca.
- AppMax/AbacatePay/C6: criar pagamento, consultar status, processar webhook, expirar/cancelar/estornar.
- E-mail: confirmacao de ticket, resposta de ticket e status de pedido.
- Pushover: notificacao de nova emissao para emissores ativos com chave configurada.

## 9. Plano de execucao sugerido

### Rodada 1 - Smoke P0

Tempo alvo: 2 a 4 horas.

Casos:

- QA-001: abrir home, buscar ida e volta, ver resultados.
- QA-002: filtrar resultados por cia/paradas ida e volta.
- QA-003: selecionar voo, criar pedido e abrir checkout.
- QA-004: preencher passageiros e gerar Pix.
- QA-005: confirmar pagamento manual no painel.
- QA-006: verificar pedido na fila de emissao.
- QA-007: emissor assume e conclui emissao.
- QA-008: cliente acompanha pedido emitido e ve LOC.
- QA-009: cliente abre ticket com anexo; atendente responde; cliente visualiza/baixa anexo.
- QA-010: admin confere pedido e dashboard financeiro.

### Rodada 2 - Compra completa P1

Tempo alvo: 1 dia.

Casos:

- QA-011: compra somente ida.
- QA-012: compra ida e volta.
- QA-013: compra com adulto + crianca + bebe.
- QA-014: rota nacional/Mercosul com CPF.
- QA-015: rota internacional exigindo passaporte.
- QA-016: voo direto.
- QA-017: voo com conexao.
- QA-018: voos em milhas.
- QA-019: voos convencionais BDS.
- QA-020: bagagens aparecem em resultados, checkout, painel e emissao.

### Rodada 3 - Descontos e pagamentos P1

Tempo alvo: meio dia a 1 dia.

Casos:

- QA-021: Pix sem cupom.
- QA-022: Pix com cupom, sem acumular desconto Pix.
- QA-023: Cartao com cupom.
- QA-024: Cupom percentual com limite.
- QA-025: Cupom fixo.
- QA-026: Cupom restrito por cliente.
- QA-027: Indicacao valida.
- QA-028: Wallet parcial.
- QA-029: Wallet quitando pedido.
- QA-030: Cancelamento estorna wallet/indicacao.

### Rodada 4 - Painel operacional P1

Tempo alvo: 1 dia.

Casos:

- QA-031: admin cria emissor, atendente e usuario.
- QA-032: emissor nao acessa areas de admin.
- QA-033: atendente nao acessa areas financeiras/pedidos restritas.
- QA-034: fila de emissao com assumir, devolver, reatribuir e concluir.
- QA-035: atendimento com status, prioridade, notas internas e anexos.
- QA-036: solicitacao de alteracao aprovada/rejeitada.
- QA-037: configuracoes de provedores refletem na busca.
- QA-038: configuracoes de Pix/cartao refletem no checkout.
- QA-039: dashboard financeiro com filtros e valores coerentes.

### Rodada 5 - Regressao visual e responsiva P2

Tempo alvo: meio dia.

Casos:

- QA-041: home desktop/mobile.
- QA-042: calendario desktop/mobile.
- QA-043: resultados desktop/mobile, incluindo filtros.
- QA-044: checkout desktop/mobile.
- QA-045: area do cliente desktop/mobile.
- QA-046: painel em tela desktop comum.
- QA-047: mensagens de erro/empty states.
- QA-048: textos longos nao quebram cards, botoes ou tabelas.

## 10. Checklist final de aceite

Considerar a versao aprovada para producao quando:

- E possivel concluir uma compra completa ate pedido emitido.
- Resultados de busca incluem voos esperados dos provedores ativos.
- Valores de passagem, taxa, Pix, cupom, indicacao e wallet estao corretos.
- Pedido pago entra na fila de emissao automaticamente.
- Emissao concluida mostra LOC para cliente.
- Atendimento com anexos funciona cliente <-> painel.
- Permissoes basicas por perfil estao corretas.
- Dashboard financeiro calcula GMV, receita, custos e margem de forma coerente.
- Nenhum P0 aberto.
- P1 abertos possuem decisao explicita de correcao antes de producao ou aceite de risco.
