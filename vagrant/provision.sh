#!/bin/sh

# Install packages
sudo apt-get update
sudo apt-get install -y curl gnupg php zip unzip php-bcmath php-curl php-mbstring php-mysql php-xdebug php-xml php-zip libxml2-utils git nodejs npm
# Shibboleth
curl -O http://pkg.switch.ch/switchaai/SWITCHaai-swdistrib.asc
shasum -a 256 SWITCHaai-swdistrib.asc
apt-key add SWITCHaai-swdistrib.asc
rm -f SWITCHaai-swdistrib.asc
echo 'deb http://pkg.switch.ch/switchaai/debian stretch main' | tee /etc/apt/sources.list.d/SWITCHaai-swdistrib.list > /dev/null
sudo apt-get update
sudo apt-get install -y --install-recommends shibboleth
shib-keygen -f -u _shibd -h staging.remotelabz.com -y 3 -e https://staging.remotelabz.com/shibboleth -o /etc/shibboleth/
(cd /tmp && \
    curl -s -O https://test.federation.renater.fr/exemples/conf_sp2_renater.tar.gz && \
    tar -zxvf conf_sp2_renater.tar.gz)
sudo mv /etc/shibboleth/attribute-map.xml /etc/shibboleth/attribute-map.xml.dist
sudo mv /etc/shibboleth/attribute-policy.xml /etc/shibboleth/attribute-policy.xml.dist
sudo cp /tmp/conf_sp2/attribute-map.xml /etc/shibboleth/attribute-map.xml
sudo cp /tmp/conf_sp2/attribute-policy.xml /etc/shibboleth/attribute-policy.xml
curl -s https://metadata.federation.renater.fr/certs/renater-metadata-signing-cert-2016.pem -o /etc/shibboleth/renater-metadata-signing-cert-2016.pem
sudo cp /var/www/html/remotelabz/vagrant/shibboleth2.xml /etc/shibboleth/
sudo cp /var/www/html/remotelabz/vagrant/shib2.conf /etc/apache2/conf-available/
sudo a2enconf shib2
sudo a2enmod shib2
sudo service shibd start
# Handle users permissions
sudo groupadd -f remotelabz
sudo usermod -aG remotelabz vagrant
sudo usermod -aG remotelabz www-data
echo "www-data     ALL=(ALL) NOPASSWD: /bin/ip" | sudo tee /etc/sudoers.d/www-data
# Composer
if ! [ "$(command -v composer)" ]; then 
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php -r "if (hash_file('sha384', 'composer-setup.php') === '48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"
    sudo mv composer.phar /usr/local/bin/composer
fi
(cd /var/www/html/remotelabz && composer install)
# Folders
mkdir -p /opt/remotelabz/images
chmod -R g+rwx /opt/remotelabz
chgrp -R remotelabz /opt/remotelabz
# Configure apache
sed -i 's/Listen 80/Listen 8000/g' /etc/apache2/ports.conf
sudo ln -fs /var/www/html/remotelabz/vagrant/100-remotelab.conf /etc/apache2/sites-enabled/100-remotelabz.conf
sudo service apache2 reload
ln -fs /var/www/html/remotelabz ./remotelabz