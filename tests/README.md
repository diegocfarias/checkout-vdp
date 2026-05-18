# Testes e cobertura

Comandos principais:

- `composer test`: roda a suite completa.
- `composer test:unit`: roda apenas testes unitarios.
- `composer test:feature`: roda apenas testes de feature.
- `composer test:coverage`: roda PHPUnit com relatorio texto, HTML e Clover.

O ambiente de teste usa SQLite em memoria via `phpunit.xml`, para evitar dependencia do MySQL local.

Para gerar cobertura, o PHP precisa ter PCOV ou Xdebug instalado. O relatorio HTML fica em
`storage/app/private/coverage/html` e o Clover em `storage/app/private/coverage/clover.xml`.
