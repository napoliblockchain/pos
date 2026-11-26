FROM composer:2.10.3 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-plugins --prefer-dist --no-interaction --optimize-autoloader
FROM php:8.3.36-apache AS application
RUN a2enmod rewrite \
    && mkdir -p /srv/pos/runtime \
    && chown www-data:www-data /srv/pos/runtime \
    && chmod 700 /srv/pos/runtime
RUN printf 'zend.exception_ignore_args=On\ndisplay_errors=Off\nsession.use_only_cookies=1\n' > /usr/local/etc/php/conf.d/pos-security.ini
WORKDIR /srv/pos
COPY --from=dependencies /app/vendor ./vendor
COPY config ./config
COPY controllers ./controllers
COPY models ./models
COPY components ./components
COPY views ./views
COPY messages ./messages
COPY fonts ./fonts
COPY web ./web
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/pos-entrypoint
RUN chmod 755 /usr/local/bin/pos-entrypoint
ENTRYPOINT ["pos-entrypoint"]
FROM application AS production

# Default target stays compatible with the local sandbox and its in-container tests.
FROM application AS preview
COPY tests ./tests
