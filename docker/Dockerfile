FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite
WORKDIR /var/www/html
COPY . .
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN chown -R www-data:www-data /var/www/html
CMD ["sh", "-c", "php scripts/migrate_entrega2.php && php scripts/create_demo_users.php && apache2-foreground"]
