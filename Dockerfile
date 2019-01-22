FROM php:7.2

COPY . /app

WORKDIR /app

RUN mkdir -p /app/vendor && \
    mkdir -p /app/var && \
    mkdir -p /.composer && \
    chown 33:33 -R /.composer && \
    chown 33:33 -R /app/var && \
    chown 33:33 -R /app/vendor && \
    chown 33:33 /app/.env && \
    apt-get update -yqq && \
    apt-get install -yqq git gnupg zlib1g-dev apt-transport-https ca-certificates unzip && \
    docker-php-ext-install pdo_mysql zip opcache pcntl && \
    pecl install xdebug && \
    curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \
    curl -sL https://deb.nodesource.com/setup_11.x | bash && \
    apt-get install -yqq nodejs && \
    apt-get update -yqq && \
    apt-get install -yqq yarn && \
    yarn install && \
    yarn encore dev
