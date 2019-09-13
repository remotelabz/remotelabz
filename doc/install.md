How to install RemoteLabz
=========================

- [Requirements](#requirements)
- [Installation](#installation)
    - [Ubuntu](#ubuntu)

Requirements
============

You will need the following software installed in order to run RemoteLabz.
- PHP >= 7.2
- Apache
- [Composer](https://getcomposer.org/download/)
- [Node.js](https://nodejs.org/en/download/package-manager/)
- [Yarn](https://yarnpkg.com/en/docs/install#debian-stable)
- [configurable-http-proxy](https://github.com/jupyterhub/configurable-http-proxy#install)

Walkthrough for some OS explains the steps to follow to install those software.

Installation
============

Ubuntu
--------------

> **Notes:**
> - This section has only been tested with **Ubuntu 18.04 (LTS)**.
> - The first steps explains how to install [requirements](#requirements). You may skip these steps if those software are already present on your system and go to [Install RemoteLabz](#install_remotelabz).

### Install requirements

- PHP
```bash
# default php version from 18.04 is 7.2
sudo apt install -y curl gnupg php zip unzip php-bcmath php-curl php-intl php-mbstring php-mysql php-xml php-zip
```
- Composer
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
```
- Node.js
```bash
sudo apt install -y nodejs npm
```
- Yarn
```bash
curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | sudo apt-key add -
echo "deb https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list
sudo apt-get update
sudo apt-get install --no-install-recommends yarn
```
- configurable-http-proxy
```bash
sudo npm install -g configurable-http-proxy
```

### Install RemoteLabz