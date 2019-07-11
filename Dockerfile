FROM ubuntu:bionic

ENV REMOTELABZ_PATH=/var/www/html/remotelabz
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && \
    apt-get install -y apache2 curl gnupg php zip unzip php-bcmath php-curl php-intl php-mbstring php-mysql php-xdebug php-xml php-zip libxml2-utils git nodejs npm swapspace apt-transport-https 

# Yarn
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \
    apt-get update && \
    apt-get install -y --no-install-recommends yarn

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet && \
    php -r "unlink('composer-setup.php');"

# Shibboleth
RUN curl -O http://pkg.switch.ch/switchaai/SWITCHaai-swdistrib.asc && \
    shasum -a 256 SWITCHaai-swdistrib.asc && \
    apt-key add SWITCHaai-swdistrib.asc && \
    rm -f SWITCHaai-swdistrib.asc && \
    echo 'deb http://pkg.switch.ch/switchaai/ubuntu bionic main' | tee /etc/apt/sources.list.d/SWITCHaai-swdistrib.list
RUN apt-get update && \
    apt-get install -y --install-recommends shibboleth
    
RUN shib-keygen -f -u _shibd -h staging.remotelabz.com -y 3 -e https://staging.remotelabz.com/shibboleth -o /etc/shibboleth/

RUN cd /tmp && \
    curl -O https://test.federation.renater.fr/exemples/conf_sp2_renater.tar.gz && \
    tar -zxvf conf_sp2_renater.tar.gz && \
    mv /etc/shibboleth/attribute-map.xml /etc/shibboleth/attribute-map.xml.dist && \
    mv /etc/shibboleth/attribute-policy.xml /etc/shibboleth/attribute-policy.xml.dist && \
    cp /tmp/conf_sp2/attribute-map.xml /etc/shibboleth/attribute-map.xml && \
    cp /tmp/conf_sp2/attribute-policy.xml /etc/shibboleth/attribute-policy.xml && \
    curl https://metadata.federation.renater.fr/certs/renater-metadata-signing-cert-2016.pem -o /etc/shibboleth/renater-metadata-signing-cert-2016.pem

RUN npm install -g configurable-http-proxy

RUN mkdir -p ${REMOTELABZ_PATH}

ADD ./vagrant/100-remotelabz.conf /etc/apache2/sites-enabled/
ADD ./vagrant/shibboleth2.xml /etc/shibboleth/shibboleth2.xml
ADD ./vagrant/shib2.conf /etc/apache2/conf-available/
RUN sed -i 's/Listen 80/Listen 8000/g' /etc/apache2/ports.conf
RUN a2enconf shib2 && \
    a2enmod shib

RUN echo "upload_max_filesize=3000M" >> /etc/php/7.2/apache2/conf.d/20-fileinfo.ini && \
    echo "post_max_size=4000M" >> /etc/php/7.2/apache2/conf.d/20-fileinfo.ini

# Folders
RUN mkdir -p /opt/remotelabz/images && \
    chmod -R g+rwx /opt/remotelabz && \
    groupadd -f remotelabz && \
    usermod -aG remotelabz www-data && \
    chgrp -R remotelabz /opt/remotelabz

ADD composer.* ${REMOTELABZ_PATH}/
ADD symfony.lock ${REMOTELABZ_PATH}/
RUN cd ${REMOTELABZ_PATH} && composer install --no-progress --no-scripts --no-suggest

ADD package.json ${REMOTELABZ_PATH}/
ADD yarn.lock ${REMOTELABZ_PATH}/
RUN cd ${REMOTELABZ_PATH} && yarn install

ADD assets/ ${REMOTELABZ_PATH}/assets
ADD webpack.config.js ${REMOTELABZ_PATH}/
RUN mkdir -p ${REMOTELABZ_PATH}/public && \
    cd ${REMOTELABZ_PATH} &&  ls -la && \
    yarn encore dev

ADD . ${REMOTELABZ_PATH}

# Console
RUN php ${REMOTELABZ_PATH}/bin/console assets:install --symlink public --relative

ADD docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint
WORKDIR ${REMOTELABZ_PATH}
EXPOSE 8000/tcp
EXPOSE 8888/tcp
EXPOSE 9000/tcp
ENTRYPOINT [ "/usr/local/bin/docker-entrypoint" ]