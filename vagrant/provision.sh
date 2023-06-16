#!/bin/sh

# Environment variables
export REMOTELABZ_PATH=/opt/remotelabz
export DEBIAN_FRONTEND=noninteractive
# Install packages
apt-get update
apt-get install -y curl gnupg php zip unzip php-bcmath php-curl php-intl php-mbstring php-mysql php-xdebug php-xml php-zip libxml2-utils git nodejs npm swapspace mysql-server exim4
. "${REMOTELABZ_PATH}"/.env
# Redirections
echo "127.0.0.1       ${MYSQL_SERVER}" >> /etc/hosts
# MySQL
mysql -u root -e "CREATE USER '${MYSQL_USER}'@'localhost' IDENTIFIED BY '${MYSQL_PASSWORD}'"
mysql -u root -e "CREATE DATABASE ${MYSQL_DATABASE}"
mysql -u root -e "GRANT ALL PRIVILEGES ON * . * TO '${MYSQL_USER}'@'localhost'"
mysql -u root -e "FLUSH PRIVILEGES"
# Yarn install
curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | sudo apt-key add -
echo "deb https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list
apt-get update
apt-get install --no-install-recommends yarn
# Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
mv composer.phar /usr/local/bin/composer
# configurable-http-proxy
npm install -g configurable-http-proxy

"${REMOTELABZ_PATH}"/bin/install --environment dev --database-server localhost --database-user "${MYSQL_USER}" --database-password "${MYSQL_PASSWORD}" --database-name "${MYSQL_DATABASE}"
"${REMOTELABZ_PATH}"/bin/remotelabz-ctl reconfigure database
"${REMOTELABZ_PATH}"/bin/remotelabz-ctl service start