FROM php:7.2

COPY . /app

RUN mkdir -p /app/vendor && \
    mkdir -p /app/var && \
    mkdir -p /.composer && \
    chown 33:33 -R /.composer && \
    chown 33:33 -R /app/var && \
    chown 33:33 -R /app/vendor && \
    chown 33:33 /app/.env && \
    apt-get update -yqq && \
    apt-get install -yqq git gnupg zlib1g-dev apt-transport-https ca-certificates unzip && \
    docker-php-ext-install pdo_mysql zip opcache && \
    pecl install xdebug
