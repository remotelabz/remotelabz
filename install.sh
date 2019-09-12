#!/bin/bash

set -e

export INSTALL_LOG="/var/log/remotelabz/install.log"

function debug() {
    echo "[$(date -u)] $1" >> "${INSTALL_LOG}" 2>&1
}

function warning() {
    echo -ne "\e[33m" >> "${INSTALL_LOG}" 2>&1
    echo -n "[$(date -u)] WARN: $1" >> "${INSTALL_LOG}" 2>&1
    echo -e "\e[39m" >> "${INSTALL_LOG}" 2>&1
}

function error() {
    echo -ne "\e[31m" >> "${INSTALL_LOG}" 2>&1
    echo -n "[$(date -u)] ERROR: $1" >> "${INSTALL_LOG}" 2>&1
    echo -e "\e[39m" >> "${INSTALL_LOG}" 2>&1
}

function quit_on_error() {
  echo "Error ❌"
  echo "Please check logs in ${INSTALL_LOG} to see what went wrong. Exiting..."
  exit 1
}

trap 'quit_on_error' ERR

debug "Starting RemoteLabz installation"

# Check for root
if [ "$(whoami)" != "root" ]; then
    error "Installation aborted, root is required!"
    echo "ERROR: This script must be executed as root! We need to hack some things on your computer. 😎"
    exit 1
fi
# Check requirements
function check_requirements() {
  if ! [ $(command -v composer) ]; then
    error "Composer is not installed! Please add composer"
  fi
}


while getopts "p:s:" opt; do
  case $opt in
    p)
      export REMOTELABZ_PORT="$OPTARG"
      ;;
    s)
      export SERVER_NAME="$OPTARG"
      ;;
    \?)
      echo "Invalid option: -$OPTARG" >&2
      exit 1
      ;;
    :)
      echo "Option -$OPTARG requires an argument." >&2
      exit 1
      ;;
  esac
done

# Environment variables
export REMOTELABZ_PATH=/opt/remotelabz
if [ -z "$REMOTELABZ_PORT" ]; then
    export REMOTELABZ_PORT=8080
fi
if [ -z "$SERVER_NAME" ]; then
    export SERVER_NAME=remotelabz.com
fi
# ----------------------------------
export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1
SCRIPT=$(readlink -f "$0")
SCRIPT_DIR=$(dirname "$SCRIPT")
HAS_MOVED=0

# Groups
if [ $(getent passwd remotelabz > /dev/null) ]; then
  useradd remotelabz
fi
if [ $(getent group remotelabz > /dev/null) ]; then
  groupadd remotelabz
fi
usermod -aG www-data remotelabz

mkdir -p /var/log/remotelabz
# Install packages
echo -n "📦 Installing required packages... "
debug "Running apt-get to grab required packages..."
apt-get update >> "${INSTALL_LOG}" 2>&1
apt-get install -y curl gnupg php zip unzip php-bcmath php-curl php-intl php-mbstring php-mysql php-xdebug php-xml php-zip libxml2-utils git nodejs npm swapspace mysql-server exim4 >> "${INSTALL_LOG}" 2>&1
echo "OK ✔️"

# Copy self-directory into destination
echo -n "📁 Copying files to ${REMOTELABZ_PATH}... "
if [ "${SCRIPT_DIR}" != "${REMOTELABZ_PATH}" ]; then
  cp -Rf "${SCRIPT_DIR}" "${REMOTELABZ_PATH}"
  cd "${REMOTELABZ_PATH}"
  HAS_MOVED=1
  echo "OK ✔️"
else
  echo "Files are already in the right location. Skipping... ✔️"
fi

# Composer
echo -n "🤵 Installing Composer... "
if ! [ $(command -v composer) ]; then
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" >> "${INSTALL_LOG}" 2>&1
    php composer-setup.php >> "${INSTALL_LOG}" 2>&1
    php -r "unlink('composer-setup.php');" >> "${INSTALL_LOG}" 2>&1
    mv composer.phar /usr/local/bin/composer >> "${INSTALL_LOG}" 2>&1
    echo "OK ✔️"
else
  echo "Composer is already installed! Skipping... ✔️"
  warning "Not installing Composer because it is done already."
fi

echo -n "🎶 Downloading Composer packages... "
(cd "${REMOTELABZ_PATH}" && composer install --no-progress --no-suggest) >> "${INSTALL_LOG}" 2>&1
chown -R remotelabz:www-data "${REMOTELABZ_PATH}"/vendor
echo "OK ✔️"

echo -n "🔥 Warming cache... "
php "${REMOTELABZ_PATH}"/bin/console cache:warm >> "${INSTALL_LOG}" 2>&1
chown -R remotelabz:www-data "${REMOTELABZ_PATH}"/var
echo "OK ✔️"

chown -R remotelabz:www-data /opt/remotelabz

# Configure apache
echo -n "🌎 Configuring Apache with port ${REMOTELABZ_PORT}... "
if grep -Fxq "Listen ${REMOTELABZ_PORT}" /etc/apache2/ports.conf; then
  echo "Port ${REMOTELABZ_PORT} is already configured in apache2." >> "${INSTALL_LOG}" 2>&1
else
  echo "Listen ${REMOTELABZ_PORT}" >> /etc/apache2/ports.conf
fi
cp -f "${REMOTELABZ_PATH}"/config/apache/100-remotelabz.conf /etc/apache2/sites-available/100-remotelabz-worker.conf
sed -i "s/<VirtualHost *:80>$/<VirtualHost *:${REMOTELABZ_PORT}>/g" /etc/apache2/sites-available/100-remotelabz.conf
sed -i "s/ServerName remotelabz.com/ServerName ${SERVER_NAME}/g" /etc/apache2/sites-available/100-remotelabz.conf
ln -fs /etc/apache2/sites-available/100-remotelabz.conf /etc/apache2/sites-enabled/100-remotelabz.conf
apache2ctl restart >> "${INSTALL_LOG}" 2>&1
echo "OK ✔️"

echo "Done!"
echo "RemoteLabz is installed! 🔥"
if [ $HAS_MOVED -eq 1 ]; then
  echo "You may now remove this folder. ♻️"
fi
echo "Thank you for using our software. ❤️"
debug "Success"
exit 0
