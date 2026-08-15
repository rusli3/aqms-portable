#!/bin/sh
set -eu

while true; do
    php /var/www/html/scheduler/main.php || true
    sleep 300
done
