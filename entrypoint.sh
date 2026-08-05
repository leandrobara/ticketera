#!/bin/bash
set -e

echo "Esperando a que MySQL esté disponible..."
until php artisan db:show > /dev/null 2>&1; do
  sleep 2
done

echo "Corriendo migraciones..."
php artisan migrate --force

echo "Optimizando cache..."
php artisan config:cache
php artisan route:cache

echo "Iniciando Apache..."
exec apache2-foreground
