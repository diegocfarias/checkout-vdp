# Testes e cobertura

Comandos principais:

- `composer test`: roda a suite completa.
- `composer test:unit`: roda apenas testes unitarios.
- `composer test:feature`: roda apenas testes de feature.
- `composer test:coverage`: roda PHPUnit com relatorio texto, HTML e Clover.

O ambiente de teste usa SQLite em memoria via `phpunit.xml`, para evitar dependencia do MySQL local.

Para gerar cobertura, o PHP precisa ter PCOV ou Xdebug instalado. O relatorio HTML fica em
`storage/app/private/coverage/html` e o Clover em `storage/app/private/coverage/clover.xml`.

## Testes E2E com Playwright

- `npm run e2e`: roda a suite E2E headless.
- `npm run e2e:headed`: roda com navegador visivel.
- `npm run e2e:ui`: abre o runner interativo.
- `npm run e2e:report`: abre o ultimo relatorio HTML.

Configuracao e escopo ficam em `tests/e2e/README.md`. Use `.env.e2e` para apontar a suite para o ambiente dev e habilitar credenciais/flags dos fluxos que criam dados.
