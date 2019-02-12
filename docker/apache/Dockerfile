FROM php:7.2-apache

RUN apt-get update > /dev/null && \
    apt-get install -yqq gnupg curl > /dev/null

RUN curl -O http://pkg.switch.ch/switchaai/SWITCHaai-swdistrib.asc && \
    shasum -a 256 SWITCHaai-swdistrib.asc && \
    apt-key add SWITCHaai-swdistrib.asc && \
    rm -f SWITCHaai-swdistrib.asc && \
    echo 'deb http://pkg.switch.ch/switchaai/debian stretch main' | tee /etc/apt/sources.list.d/SWITCHaai-swdistrib.list > /dev/null

RUN apt-get update > /dev/null && \
    apt-get install -yqq --install-recommends shibboleth > /dev/null
    
RUN shib-keygen -f -u _shibd -h staging.remotelabz.com -y 3 -e https://staging.remotelabz.com/shibboleth -o /etc/shibboleth/

RUN cd /tmp && \
    curl -O https://test.federation.renater.fr/exemples/conf_sp2_renater.tar.gz && \
    tar -zxvf conf_sp2_renater.tar.gz && \
    mv /etc/shibboleth/attribute-map.xml /etc/shibboleth/attribute-map.xml.dist && \
    mv /etc/shibboleth/attribute-policy.xml /etc/shibboleth/attribute-policy.xml.dist && \
    cp /tmp/conf_sp2/attribute-map.xml /etc/shibboleth/attribute-map.xml && \
    cp /tmp/conf_sp2/attribute-policy.xml /etc/shibboleth/attribute-policy.xml && \
    curl https://metadata.federation.renater.fr/certs/renater-metadata-signing-cert-2016.pem -o /etc/shibboleth/renater-metadata-signing-cert-2016.pem

RUN echo "" >> /etc/apache2/conf-available/shib2.conf && \
    echo "<Location /Shibboleth.sso>" >> /etc/apache2/conf-available/shib2.conf && \
    echo "  SetHandler shib" >> /etc/apache2/conf-available/shib2.conf && \
    echo "</Location>" >> /etc/apache2/conf-available/shib2.conf && \
    a2enconf shib2 && \
    a2enmod shib2

RUN docker-php-ext-install pdo_mysql opcache bcmath > /dev/null && \
    pecl -q install xdebug && \
    docker-php-ext-enable xdebug

ADD ./000-default.conf /etc/apache2/sites-enabled/
ADD ./shibboleth2.xml /etc/shibboleth/shibboleth2.xml

RUN sed -i '/exec apache2 -DFOREGROUND "$@"/iservice shibd start' /usr/local/bin/apache2-foreground
RUN sed -i 's/Listen 80/Listen 8000/g' /etc/apache2/ports.conf

RUN mkdir -p /var/www/cache && \
    mkdir -p /var/www/log && \
    chmod -R 777 /var/www/cache && \
    chmod -R 777 /var/www/log

# FIXME: Is the use of www-data is necessary when running apache? There is a conflict with the use of the 80 port
# ARG userid=1000

# RUN usermod -u ${userid} www-data
