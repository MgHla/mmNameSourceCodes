FROM php:8.3.6-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN chown -R www-data:www-data /var/www && \
    chmod -R 755 /var/www
COPY ./src/ /var/www/html/
