FROM composer:1.5 AS composer
COPY src/ /app
COPY composer.json /app
COPY composer.lock /app
RUN composer install

FROM php:7.2-alpine
COPY --chown=www-data --from=composer /app/vendor /var/www/html/vendor
COPY --chown=www-data src /var/www/html/src
COPY --chown=www-data miner.php /var/www/html/

CMD ["php", "-f", "/var/www/html/miner.php"]
