#!/bin/bash
echo "🚀 Deploying DICT Procurement System..."

# Pull latest code
git pull origin main

# Install dependencies
composer install --optimize-autoloader --no-dev

# Clear and cache config
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Storage permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx

echo "✅ Deployment complete!"