cd /home/forge/checkout-dev.voedeprimeira.com

git pull origin master

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart

echo "Deploy concluído com sucesso."
