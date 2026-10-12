FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
 && pecl install redis && docker-php-ext-enable redis \
 && printf '<Directory /var/www/html>\n  FallbackResource /index.php\n</Directory>\n' > /etc/apache2/conf-available/fallback.conf \
 && a2enconf fallback
COPY src/ /var/www/html/
