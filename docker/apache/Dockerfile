FROM php:7.2-apache

RUN apt-get update && \
    apt-get install -yqq libapache2-mod-shib2 && \
    a2enmod shib2

# Required packages
RUN apt-get -yqq update > /dev/null && \
    apt-get -yqq install git gnupg zlib1g-dev apt-transport-https ca-certificates unzip > /dev/null

# PHP ext
RUN docker-php-ext-install pdo_mysql zip opcache pcntl bcmath > /dev/null && \
    pecl -q install xdebug && \
    docker-php-ext-enable xdebug

ADD ./000-default.conf /etc/apache2/sites-enabled/