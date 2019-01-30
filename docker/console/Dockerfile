FROM php:7.2
RUN apt-get update && apt-get install -yqq git zlib1g-dev zip unzip
RUN docker-php-ext-install pdo_mysql zip opcache pcntl bcmath > /dev/null
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet && \
    php -r "unlink('composer-setup.php');"
COPY entrypoint.sh /usr/local/bin/entrypoint
RUN ["chmod", "+x", "/usr/local/bin/entrypoint"]
WORKDIR /app
ENTRYPOINT ["entrypoint"]