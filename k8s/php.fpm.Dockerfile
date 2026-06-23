FROM firehub:latest

ENV SUPERVISOR_PHP_USER=root
ENV WWWUSER=www-data

WORKDIR /var/www/html

COPY ./k8s/8.4/www.conf /etc/php/8.4/fpm/pool.d/www.conf
COPY ./k8s/8.4/php.ini /etc/php/8.4/fpm/php.ini
COPY ./k8s/8.4/php.ini /etc/php/8.4/cli/php.ini
RUN mkdir -p /run/php

# Install pgsql and redis for php 8.4
RUN apt-get update && apt-get install -y php8.4-pgsql php8.4-redis && apt-get clean

# Install nginx
RUN apt-get update && apt-get install -y nginx && apt-get clean
COPY ./k8s/8.4/nginx.conf /etc/nginx/sites-available/default

# Install supervisor
RUN apt-get update && apt-get install -y supervisor && apt-get clean
COPY ./k8s/8.4/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Add source code
ADD . /var/www/html
RUN rm -f bootstrap/cache/* && chmod -R 777 storage bootstrap/cache
RUN rm /var/www/html/.env
RUN rm -f /var/www/html/.env.*
RUN rm -f /var/www/html/.env.*.deploy

RUN composer install --ignore-platform-reqs --no-interaction --no-scripts --optimize-autoloader

RUN npm install

# Chmod
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R u+rwX /var/www/html

# Expose port 80 of the nginx
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-n"]
