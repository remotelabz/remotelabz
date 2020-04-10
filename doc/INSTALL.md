How to install RemoteLabz
=========================

- [Requirements](#requirements)
- [Installation](#installation)
    - [Ubuntu](#ubuntu)

Requirements
============

You will need the following software installed in order to run RemoteLabz.
- PHP >= 7.3
- Apache
- [Composer](https://getcomposer.org/download/)
- [Node.js](https://nodejs.org/en/download/package-manager/)
- [Yarn](https://yarnpkg.com/en/docs/install#debian-stable)
- [configurable-http-proxy](https://github.com/jupyterhub/configurable-http-proxy#install)
- An SQL database (not necessarily on the same server)

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
sudo apt-get update && apt-get upgrade
sudo apt-get install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt install -y curl gnupg php7.3 zip unzip php-bcmath php-curl php-gd php-intl php-mbstring php-mysql php-xml php-zip
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

### Install RemoteLabz

While you're in RemoteLabz root directory :

```bash
sudo bin/install
```

Then, you should modify the `.env` file according to your environment, including SQL database variables with `MYSQL_SERVER`, `MYSQL_USER`, `MYSQL_PASSWORD` and `MYSQL_DATABASE`.

```bash
# Replace 'mysqlpassword' by your actual password
echo "MYSQL_PASSWORD=mysqlpassword" | sudo tee -a .env

# or edit ENV file directly
sudo nano .env
```

Finally, run the `remotelabz-ctl` configuration utility to setup your database :

```bash
sudo remotelabz-ctl reconfigure database
```

With the loaded fixtures, default credentials are :
- Username : `root@localhost`
- Password : `admin`

You may change those values by using the web interface.

#### Generate API keys

In order for the app to work correctly, you must create a key pair for JWT. You can find detailed configuration in [the LexikJWTAuthenticationBundle doc](https://github.com/lexik/LexikJWTAuthenticationBundle/blob/master/Resources/doc/index.md#generate-the-ssh-keys).

At the root of your RemoteLabz folder:

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Don't forget to edit your `.env` :

```bash
###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=yourpassphrase
###< lexik/jwt-authentication-bundle ###
```