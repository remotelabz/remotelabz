FROM php:7.2-apache

RUN apt-get update > /dev/null && \
    apt-get install -yqq libapache2-mod-shib2 > /dev/null && \
    a2enmod shib2

RUN docker-php-ext-install pdo_mysql opcache bcmath > /dev/null && \
    pecl -q install xdebug && \
    docker-php-ext-enable xdebug

ADD ./000-default.conf /etc/apache2/sites-enabled/

RUN mkdir -p /var/www/cache && \
    mkdir -p /var/www/log && \
    chmod -R 777 /var/www/cache && \
    chmod -R 777 /var/www/log

RUN sed -i -e "s/80/8000/g" /etc/apache2/ports.conf

ARG userid

RUN usermod -u ${userid} www-data
