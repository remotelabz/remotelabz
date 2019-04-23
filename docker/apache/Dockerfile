FROM php:7.2-apache

ENV DEBIAN_FRONTEND noninteractive

RUN echo 'deb http://deb.debian.org/debian stretch-backports main' | tee /etc/apt/sources.list.d/stretch-backports.list

RUN apt-get update > /dev/null && \
    apt-get install -yqq gnupg curl > /dev/null && \
    apt-get -t stretch-backports install -yqq init-system-helpers libxerces-c3.2 > /dev/null

RUN curl --output /etc/apt/trusted.gpg.d/SWITCHaai-swdistrib.asc http://pkg.switch.ch/switchaai/SWITCHaai-swdistrib.asc && \
    # curl -O http://pkg.switch.ch/switchaai/SWITCHaai-swdistrib.asc && \
    # shasum -a 256 SWITCHaai-swdistrib.asc && \
    # apt-key add SWITCHaai-swdistrib.asc && \
    # rm -f SWITCHaai-swdistrib.asc && \
    echo 'deb http://pkg.switch.ch/switchaai/debian stretch main' | tee /etc/apt/sources.list.d/SWITCHaai-swdistrib.list

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

RUN docker-php-ext-install pdo_mysql opcache bcmath > /dev/null && \
    pecl -q install xdebug && \
    docker-php-ext-enable xdebug

RUN mkdir -p /var/www/cache && \
    mkdir -p /var/www/log && \
    chmod -R 777 /var/www/cache && \
    chmod -R 777 /var/www/log

# Configuration

ADD ./000-default.conf /etc/apache2/sites-enabled/
ADD ./shibboleth2.xml /etc/shibboleth/shibboleth2.xml
ADD ./shib2.conf /etc/apache2/conf-available/

RUN a2enconf shib2 && \
    a2enmod shib
RUN sed -i '/exec apache2 -DFOREGROUND "$@"/iservice shibd start' /usr/local/bin/apache2-foreground
RUN sed -i 's/Listen 80/Listen 8000/g' /etc/apache2/ports.conf

# FIXME: Is the use of www-data is necessary when running apache? There is a conflict with the use of the 80 port
# ARG userid=1000

# RUN usermod -u ${userid} www-data
