#!/bin/sh

# Environment variables
export REMOTELABZ_PATH=/var/www/html/remotelabz
export DEBIAN_FRONTEND=noninteractive
# Install packages
apt-get update
apt-get install -y curl gnupg php zip unzip php-bcmath php-curl php-intl php-mbstring php-mysql php-xdebug php-xml php-zip libxml2-utils git nodejs npm swapspace mysql-server exim4
# Move files
mv /home/vagrant/remotelabz /var/www/html
cp "${REMOTELABZ_PATH}"/.env.dist "${REMOTELABZ_PATH}"/.env
. "${REMOTELABZ_PATH}"/.env
# Redirections
echo "127.0.0.1       ${MYSQL_SERVER}" >> /etc/hosts
# MySQL
mysql -u root -e "CREATE USER '${MYSQL_USER}'@'localhost' IDENTIFIED BY '${MYSQL_PASSWORD}'"
mysql -u root -e "CREATE DATABASE ${MYSQL_DATABASE}"
mysql -u root -e "GRANT ALL PRIVILEGES ON * . * TO '${MYSQL_USER}'@'localhost'"
mysql -u root -e "FLUSH PRIVILEGES"
# Shibboleth
curl -s -O http://pkg.switch.ch/switchaai/SWITCHaai-swdistrib.asc
shasum -a 256 SWITCHaai-swdistrib.asc
apt-key add SWITCHaai-swdistrib.asc
rm -f SWITCHaai-swdistrib.asc
echo 'deb http://pkg.switch.ch/switchaai/ubuntu bionic main' | tee /etc/apt/sources.list.d/SWITCHaai-swdistrib.list > /dev/null
apt-get update
apt-get install -y --install-recommends shibboleth
shib-keygen -f -u _shibd -h staging.remotelabz.com -y 3 -e https://staging.remotelabz.com/shibboleth -o /etc/shibboleth/
curl -s https://test.federation.renater.fr/exemples/conf_sp2_renater.tar.gz -o /tmp/conf_sp2_renater.tar.gz
tar -zxvf /tmp/conf_sp2_renater.tar.gz -C /tmp
mv /etc/shibboleth/attribute-map.xml /etc/shibboleth/attribute-map.xml.dist
mv /etc/shibboleth/attribute-policy.xml /etc/shibboleth/attribute-policy.xml.dist
cp /tmp/conf_sp2/attribute-map.xml /etc/shibboleth/attribute-map.xml
cp /tmp/conf_sp2/attribute-policy.xml /etc/shibboleth/attribute-policy.xml
rm /tmp/conf_sp2_renater.tar.gz
rm -rf /tmp/conf_sp2
curl -s https://metadata.federation.renater.fr/certs/renater-metadata-signing-cert-2016.pem -o /etc/shibboleth/renater-metadata-signing-cert-2016.pem
cp "${REMOTELABZ_PATH}"/vagrant/shibboleth2.xml /etc/shibboleth/
cp "${REMOTELABZ_PATH}"/vagrant/shib2.conf /etc/apache2/conf-available/
a2enconf shib2
a2enmod shib
service shibd start
# Yarn install
curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | sudo apt-key add -
echo "deb https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list
apt-get update
apt-get install --no-install-recommends yarn
# Handle users permissions
groupadd -f remotelabz
usermod -aG remotelabz vagrant
usermod -aG remotelabz www-data
# Composer
if ! [ "$(command -v composer)" ]; then 
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"
    mv composer.phar /usr/local/bin/composer
fi
(cd "${REMOTELABZ_PATH}" && composer install --no-progress --no-suggest)
# Yarn
# On Windows 10, you need to enable symlink creation rights :
# https://github.com/yarnpkg/yarn/issues/4908#issuecomment-462285339
#(cd "${REMOTELABZ_PATH}" && yarn install --no-bin-links)
(cd "${REMOTELABZ_PATH}" && yarn install)
#(cd "${REMOTELABZ_PATH}" && yarn add encore dev --no-bin-links)
(cd "${REMOTELABZ_PATH}" && yarn encore dev)
# Console
php "${REMOTELABZ_PATH}"/bin/console doctrine:migrations:migrate -n
php "${REMOTELABZ_PATH}"/bin/console doctrine:fixtures:load -n
php "${REMOTELABZ_PATH}"/bin/console assets:install --symlink public --relative
# configurable-http-proxy
npm install -g configurable-http-proxy
configurable-http-proxy --ssl-key /etc/ssl/private/remotelabz.com_private_key.key --ssl-cert /etc/ssl/certs/remotelabz.com_ssl_certificate.cer --port 8080 --api-port 8889 &
# Folders
mkdir -p /opt/remotelabz/images
chmod -R g+rwx /opt/remotelabz
chgrp -R remotelabz /opt/remotelabz
# Configure apache
sed -i 's/Listen 80$/Listen 8000/g' /etc/apache2/ports.conf
ln -fs "${REMOTELABZ_PATH}"/vagrant/100-remotelabz.conf /etc/apache2/sites-enabled/100-remotelabz.conf
service apache2 reload
ln -fs "${REMOTELABZ_PATH}" ./remotelabz
