FROM php:7.2
RUN docker-php-ext-install pdo_mysql opcache pcntl bcmath > /dev/null
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet && \
    php -r "unlink('composer-setup.php');"
RUN apt-get update && apt-get install -yqq git