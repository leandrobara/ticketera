#!/bin/bash
set -e

echo "Esperando a que MySQL esté disponible..."
max_retries=30
count=0
until php artisan db:show > /dev/null 2>&1; do
  count=$((count+1))
  if [ $count -ge $max_retries ]; then
    echo "ERROR: No se pudo conectar a la base de datos después de $max_retries intentos."
    exit 1
  fi
  sleep 2
done

echo "Corriendo migraciones..."
php artisan migrate --force

echo "Optimizando cache..."
php artisan config:cache
php artisan route:cache

echo "Iniciando Apache..."
exec apache2-foreground
