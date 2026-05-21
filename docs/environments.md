# Ambientes e branches

Este projeto usa um fluxo GitFlow simples:

- `master`: producao. Deploy do site publico.
- `develop`: desenvolvimento/homologacao. Deploy do ambiente `checkout-dev.voedeprimeira.com`.
- `feature/<descricao>`: trabalho novo, sempre criado a partir de `develop`.
- `hotfix/<descricao>`: correcao urgente criada a partir de `master`, depois mesclada em `master` e `develop`.

## Ambiente dev

O ambiente dev deve ter banco, cache, sessoes, filas e credenciais proprias. Ele nunca deve apontar para o banco de producao.

Use PHP 8.4 no ambiente dev. Hoje uma dependencia do painel (`openspout`, via Filament) nao aceita PHP 8.5, entao Composer falha quando o deploy roda com PHP 8.5.

No Forge, instale/habilite PHP 8.4 na instancia antes do primeiro deploy. Se o comando `php8.4` nao existir no terminal do servidor, o deploy vai falhar antes do Composer.

Checklist de infra:

1. Criar um site separado, por exemplo `checkout-dev.voedeprimeira.com`.
2. Configurar o deploy desse site para a branch `develop`.
3. Criar um banco separado, por exemplo:
   - database: `checkout_vdp_dev`
   - user: `checkout_vdp_dev`
4. Copiar `.env.development.example` para `.env` no servidor dev.
5. Preencher `APP_KEY`, banco, crawlers, gateways e demais chaves com valores de dev/sandbox.
6. Rodar:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=SettingsSeeder --force
php artisan storage:link
```

No Forge, o script de deploy do ambiente dev pode ser:

```bash
PHP_BIN=php8.4 bash deploy/development.sh
```

Se o Forge tiver PHP 8.4 instalado com outro caminho, defina o caminho completo:

```bash
PHP_BIN=/usr/bin/php8.4 bash deploy/development.sh
```

Em deploy com releases do Forge, o script deve rodar dentro do diretorio da release e nao precisa fazer `git pull`; por isso `UPDATE_GIT` fica desligado por padrao.

Se o site estiver em outro diretorio e o deploy for manual, defina `APP_DIR` e ligue `UPDATE_GIT`:

```bash
APP_DIR=/home/forge/checkout-dev.voedeprimeira.com UPDATE_GIT=true PHP_BIN=php8.4 bash deploy/development.sh
```

## Ambiente producao

Producao segue `master`. O script equivalente e:

```bash
PHP_BIN=php8.4 bash deploy/production.sh
```

Se o diretorio de producao for diferente do padrao e o deploy for manual, use:

```bash
APP_DIR=/home/forge/voedeprimeira.com UPDATE_GIT=true PHP_BIN=php8.4 bash deploy/production.sh
```

## Fluxo recomendado

1. Criar feature a partir de `develop`.
2. Abrir PR para `develop`.
3. Validar no ambiente dev.
4. Mesclar `develop` em `master` quando for publicar.
5. Rodar deploy de producao.
