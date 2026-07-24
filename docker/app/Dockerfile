# ================================================================
# IMAGEN: maindannyob/php-sample-app-web
# Contiene: PHP 7.4 + Apache + la aplicacion (NO la base de datos)
#
# Construir desde la RAIZ del repositorio con:
#   docker build -f docker/app/Dockerfile -t maindannyob/php-sample-app-web:latest .
# ================================================================

# ---- Etapa 1: instalar dependencias con Composer ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# ---- Etapa 2: imagen final PHP + Apache ----
FROM php:7.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite deflate headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Codigo de la aplicacion (contexto = raiz del repo)
COPY . /var/www/html

# Dependencias instaladas en la etapa anterior
COPY --from=vendor /app/vendor /var/www/html/vendor

# config-dev -> config (el Makefile original crea este symlink)
RUN rm -rf /var/www/html/config \
    && ln -s config-dev /var/www/html/config \
    && mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/logs

# VirtualHost equivalente al del README
COPY docker/app/apache-config/000-default.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
