FROM php:8.1-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libldap2-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu \
    && docker-php-ext-install ldap mbstring \
    && a2enmod headers rewrite

# Официальный образ по умолчанию AllowOverride None — включаем .htaccess проекта
RUN printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/allow-override.conf \
    && a2enconf allow-override

COPY . /var/www/html/

RUN rm -f /var/www/html/*.bak \
    && chown -R www-data:www-data /var/www/html
