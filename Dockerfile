FROM ubuntu:bionic

ENV REMOTELABZ_PATH=/opt/remotelabz
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && \
    apt-get install -y curl software-properties-common && \
    add-apt-repository -y ppa:ondrej/php

RUN curl -sL https://deb.nodesource.com/setup_12.x | bash -

RUN apt-get update && \
    apt-get install -y apache2 zip unzip gnupg php7.3 php7.3-bcmath php7.3-curl php7.3-gd php7.3-intl php7.3-mbstring php7.3-mysql php7.3-xml php7.3-zip libxml2-utils git nodejs swapspace apt-transport-https exim4 sudo

# Exim
RUN sed -i "s/dc_eximconfig_configtype='local'/dc_eximconfig_configtype='satellite'/g" /etc/exim4/update-exim4.conf.conf && \
    sed -i "s/dc_readhost=''/dc_readhost='staging.remotelabz.univ-reims.fr'/g" /etc/exim4/update-exim4.conf.conf && \
    sed -i "s/dc_smarthost=''/dc_smarthost='smtp.univ-reims.fr'/g" /etc/exim4/update-exim4.conf.conf && \
    sed -i "s/dc_local_interfaces='127.0.0.1 ; ::1'/dc_local_interfaces='127.0.0.1'/g" /etc/exim4/update-exim4.conf.conf

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
RUN curl -sS http://pkg.switch.ch/switchaai/SWITCHaai-swdistrib.asc | apt-key add - && \
    echo "deb http://pkg.switch.ch/switchaai/ubuntu bionic main" | tee /etc/apt/sources.list.d/SWITCHaai-swdistrib.list && \
    apt-get update && \
    apt-get install -y --install-recommends shibboleth

RUN shib-keygen -f -u _shibd -h staging.remotelabz.com -y 3 -e https://staging.remotelabz.com/shibboleth -o /etc/shibboleth/

RUN cd /tmp && \
    git clone https://git.renater.fr/anonscm/git/partage-fede/formation.git && \
    mv /etc/shibboleth/attribute-map.xml /etc/shibboleth/attribute-map.xml.dist && \
    mv /etc/shibboleth/attribute-policy.xml /etc/shibboleth/attribute-policy.xml.dist && \
    cp /tmp/formation/sp/etc/shibboleth/attribute-map.xml /etc/shibboleth/attribute-map.xml && \
    cp /tmp/formation/sp/etc/shibboleth/attribute-policy.xml /etc/shibboleth/attribute-policy.xml && \
    curl https://metadata.federation.renater.fr/certs/renater-metadata-signing-cert-2016.pem -o /etc/shibboleth/renater-metadata-signing-cert-2016.pem

ADD ./config/shibboleth/shibboleth2.xml /etc/shibboleth/shibboleth2.xml
ADD ./config/shibboleth/shib2.conf /etc/apache2/conf-available/
RUN a2enconf shib2 && \
    a2enmod shib && \
    a2enmod ssl

RUN npm install -g configurable-http-proxy

ARG ENVIRONMENT=dev
ARG PORT=80
ARG PORT_SSL=443
ARG WORKER_SERVER=localhost
ARG WORKER_PORT=8080
ARG PROXY_SERVER=localhost
ARG PROXY_PORT=8888
ARG PROXY_API_PORT=8889
ARG DATABASE_SERVER=localhost
ARG DATABASE_USER=symfony
ARG DATABASE_PASSWORD=symfony
ARG DATABASE_NAME=symfony
ARG MAILER_URL="smtp://localhost:25?encryption=&auth_mode="
ARG SERVER_NAME=remotelabz.com

ADD --chown=www-data:www-data . ${REMOTELABZ_PATH}

RUN php ${REMOTELABZ_PATH}/bin/install -e ${ENVIRONMENT} -p ${PORT} --worker-server ${WORKER_SERVER} --worker-port ${WORKER_PORT} --proxy-server ${PROXY_SERVER} --proxy-port ${PROXY_PORT} --proxy-api-port ${PROXY_API_PORT} --database-server ${DATABASE_SERVER} --database-user ${DATABASE_USER} --database-password ${DATABASE_PASSWORD} --database-name ${DATABASE_NAME} --mailer-url ${MAILER_URL} --server-name ${SERVER_NAME} --no-permission

ADD docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR ${REMOTELABZ_PATH}

EXPOSE ${PORT}/tcp
EXPOSE ${PORT_SSL}/tcp
EXPOSE ${PROXY_PORT}/tcp

ENTRYPOINT [ "/usr/local/bin/docker-entrypoint" ]