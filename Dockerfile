FROM php:8.3-apache

RUN docker-php-ext-install mysqli

COPY app/ /var/www/html/

RUN chown -R root:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 0750 {} \; \
    && find /var/www/html -type f -exec chmod 0640 {} \;
