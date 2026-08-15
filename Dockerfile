FROM php:8.5.9-apache-bookworm@sha256:68e1de9a82af09f1b0ae70611bd64a8702069ac1e036bdc5a12eb48e1a5cab2b

RUN docker-php-ext-install mysqli \
    && a2enmod headers

COPY app/ /var/www/html/
COPY docker/apache-security.conf /etc/apache2/conf-available/zz-aqms-security.conf
COPY docker/php-security.ini /usr/local/etc/php/conf.d/99-aqms-security.ini
COPY docker/scheduler-loop.sh /usr/local/bin/aqms-scheduler-loop

RUN chown -R root:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 0750 {} \; \
    && find /var/www/html -type f -exec chmod 0640 {} \; \
    && chmod 0755 /usr/local/bin/aqms-scheduler-loop \
    && a2enconf zz-aqms-security
