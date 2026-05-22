# Playwright E2E

Suite E2E para automatizar o plano de QA do fluxo principal de compra.

Ela cobre, por padrao, checks seguros do site publico, busca, calendario, filtros e pos-venda sem criar pagamentos. Fluxos que geram pedido, mexem em atendimento ou exigem painel so rodam com flags e credenciais explicitas.

## Instalar

Use Node compativel com o Vite do projeto (`20.19+` ou `22.12+`). Node 21 pode rodar, mas emite aviso de engine.

```bash
npm install
npx playwright install chromium
```

## Configurar ambiente

Copie o exemplo e ajuste a URL/credenciais do ambiente dev:

```bash
cp .env.e2e.example .env.e2e
```

Variaveis principais:

- `E2E_BASE_URL`: URL do ambiente testado.
- `E2E_WORKERS`: quantidade de workers. Use `1` para dev/homologacao com integracoes reais.
- `E2E_RETRIES`: retentativas por teste. Use `1` ou `2` em dev com integracoes externas.
- `E2E_INCLUDE_MOBILE=true`: inclui o projeto mobile. Rode separado se o ambiente tiver throttle baixo.
- `E2E_DEFAULT_DEPARTURE`, `E2E_DEFAULT_ARRIVAL`: rota padrao usada na busca.
- `E2E_ADMIN_EMAIL`, `E2E_ADMIN_PASSWORD`: habilitam testes do painel.
- `E2E_CUSTOMER_EMAIL`, `E2E_CUSTOMER_PASSWORD`: habilitam testes da area do cliente.
- `E2E_CHECKOUT_TOKEN`: habilita teste readonly de checkout existente.
- `E2E_TRACKING_CODE`, `E2E_TRACKING_DOCUMENT`: habilitam tracking de pedido real.
- `E2E_SUPPORT_TICKET_PATH`: habilita teste em um atendimento real, por exemplo `/minha-conta/atendimentos/{uuid}`.
- `E2E_ENABLE_PURCHASE_FLOW=true`: permite criar checkout a partir de busca.
- `E2E_SUBMIT_PAYMENT=true`: permite submeter pagamento Pix no fluxo de compra.
- `E2E_ALLOW_SUPPORT_MUTATIONS=true`: permite criar/interagir com atendimento.

## Rodar

```bash
npm run e2e
npm run e2e:headed
npm run e2e:ui
```

Para rodar tambem no viewport mobile:

```bash
E2E_INCLUDE_MOBILE=true npm run e2e
```

Para subir o Laravel local automaticamente:

```bash
E2E_START_SERVER=true E2E_BASE_URL=http://127.0.0.1:8000 npm run e2e
```

Nesse caso, rode `npm run build` antes ou deixe os assets prontos no ambiente.

## Relatorios

Ao falhar, a suite salva screenshot, video e trace em `test-results/`. O HTML fica em `playwright-report/`.

```bash
npm run e2e:report
```

## Mapa do plano de QA

- `public-search.spec.ts`: home, busca, calendario e resposta sem `source`.
- `search-filters.spec.ts`: filtros separados por ida/volta e bug de conexao visivel em filtro direto.
- `checkout-readonly.spec.ts`: resumo do checkout e formulario de passageiros via token existente.
- `purchase-flow.spec.ts`: busca -> compra -> checkout, protegido por flag.
- `customer-post-sale.spec.ts`: consulta de pedido, area de atendimento e anexos quando houver fixture.
- `admin-panel.spec.ts`: login e areas operacionais principais do painel.
