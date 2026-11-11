FROM composer:2.10.3 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-plugins --prefer-dist --no-interaction --optimize-autoloader
FROM php:8.3.36-apache
RUN a2enmod rewrite \
    && mkdir -p /srv/pos/runtime \
    && chown www-data:www-data /srv/pos/runtime \
    && chmod 700 /srv/pos/runtime
WORKDIR /srv/pos
COPY --from=dependencies /app/vendor ./vendor
COPY config ./config
COPY controllers ./controllers
COPY models ./models
COPY views ./views
COPY web ./web
COPY tests ./tests
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
