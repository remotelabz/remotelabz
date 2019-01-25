FROM php:7.2

RUN mkdir -p /app/vendor && \
    mkdir -p /app/public/build && \
    mkdir -p /app/var && \
    mkdir -p /.composer && \
    chmod 777 -R /.composer && \
    chmod 777 -R /app/var && \
    chmod 777 -R /app/public/build && \
    chmod 777 -R /app/vendor

WORKDIR /app

# Required packages
RUN apt-get -yqq update > /dev/null && \
    apt-get -yqq install git gnupg zlib1g-dev apt-transport-https ca-certificates unzip > /dev/null

# PHP ext
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

ADD composer.json /app/
RUN composer install --no-progress --no-scripts --no-suggest

ADD assets/ /app/assets/
ADD package.json /app/
ADD webpack.config.js /app/
RUN yarn install

ADD assets/ /app/assets/
RUN yarn encore dev

ADD . /app

ENTRYPOINT [ "/app/bin/console" ]
CMD [ "server:start", "0.0.0.0:8000" ]