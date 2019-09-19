FROM ubuntu:bionic

ARG environment=dev
ARG port=80
ARG worker-server=localhost
ARG worker-port=8080
ARG proxy-server=localhost
ARG proxy-port=8888
ARG proxy-api-port=8889
ARG database-server=localhost
ARG database-user=symfony
ARG database-password=symfony
ARG database-name=symfony
ARG mailer-url="smtp://localhost:25?encryption=&auth_mode="
ARG server-name=remotelabz.com

ENV REMOTELABZ_PATH=/opt/remotelabz
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && \
    apt-get install -y apache2 curl gnupg php zip unzip php-bcmath php-curl php-intl php-mbstring php-mysql php-xdebug php-xml php-zip libxml2-utils git nodejs npm swapspace apt-transport-https exim4

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

ADD ./config/shibboleth/shibboleth2.xml /etc/shibboleth/shibboleth2.xml
ADD ./config/shibboleth/shib2.conf /etc/apache2/conf-available/
RUN a2enconf shib2 && \
    a2enmod shib

RUN npm install -g configurable-http-proxy

ADD --chown=www-data:www-data . ${REMOTELABZ_PATH}

RUN echo ${worker-port}
RUN php ${REMOTELABZ_PATH}/bin/install -e ${environment} -p ${port} --worker-server ${worker-server} --worker-port ${worker-port} --proxy-server ${proxy-server} --proxy-port ${proxy-port} --proxy-api-port ${proxy-api-port} --database-server ${database-server} --database-uer ${database-user} --database-password ${database-password} --database-name ${database-name} --mailer-url ${mailer-url} --server-name ${server-name}

# Folders
RUN chmod -R g+rwx /opt/remotelabz

ADD docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR ${REMOTELABZ_PATH}

EXPOSE ${port}/tcp
EXPOSE ${proxy-port}/tcp

ENTRYPOINT [ "/usr/local/bin/docker-entrypoint" ]