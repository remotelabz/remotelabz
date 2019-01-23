FROM php:7.2

RUN mkdir -p /app/vendor && \
    mkdir -p /app/var && \
    mkdir -p /.composer && \
    chown 33:33 -R /.composer && \
    chown 33:33 -R /app/var && \
    chown 33:33 -R /app/vendor

RUN apt-get -yqq update > /dev/null && \
    apt-get -yqq install git gnupg zlib1g-dev apt-transport-https ca-certificates unzip > /dev/null
RUN docker-php-ext-install pdo_mysql zip opcache pcntl bcmath > /dev/null && \
    pecl -q install xdebug

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet && \
    php -r "unlink('composer-setup.php');"

# Yarn
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \
    curl -sL https://deb.nodesource.com/setup_11.x | bash > /dev/null && \
    apt-get -yqq install nodejs > /dev/null && \
    apt-get -yqq update > /dev/null && \
    apt-get -yqq install yarn > /dev/null

ENV SERVER_HOST 0.0.0.0
ENV SERVER_PORT 8000

WORKDIR /app

COPY entrypoint.sh /usr/local/bin/

RUN chown 33:33 /usr/local/bin/entrypoint.sh

ENTRYPOINT [ "/app/bin/console" ]