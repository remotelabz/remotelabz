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
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt install -y curl gnupg php7.3 zip unzip php7.3-bcmath php7.3-curl php7.3-gd php7.3-intl php7.3-mbstring php7.3-mysql php7.3-xml php7.3-zip
```

- Composer
```bash
sudo cp composer.phar /usr/local/bin/composer
```

- Node.js
```bash
curl -sL https://deb.nodesource.com/setup_12.x | sudo -E bash -
sudo apt-get install -y nodejs
```

- Yarn
```bash
sudo npm install -g yarn
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

Run the `remotelabz-ctl` configuration utility to setup your database :

```bash
sudo remotelabz-ctl reconfigure database
```

With the loaded fixtures, default credentials are :
- Username : `root@localhost`
- Password : `admin`

You may change those values by using the web interface.

### Instances

In order to be able to control instances on [the worker](https://gitlab.remotelabz.com/crestic/remotelabz-worker), you need to start **Symfony Messenger** :

```bash
sudo php bin/console messenger:consume front
```

**Warning :** When consuming messages, a timestamp is used to determine which messages the messenger worker is able to consume. Therefore, each machines needs to be time-synchronized. We recommand you to use a service like `ntp` to keep your machines synchronized.

You will also need to start the proxy service to display VNC console :

```bash
sudo npm install -g configurable-http-proxy
# then start it
configurable-http-proxy
```

#### Generate API keys

In order for the app to work correctly, you must create a key pair for JWT. You can find detailed configuration in [the LexikJWTAuthenticationBundle doc](https://github.com/lexik/LexikJWTAuthenticationBundle/blob/master/Resources/doc/index.md#generate-the-ssh-keys).

At the root of your RemoteLabz folder:

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
chown -R www-data:www-data config/jwt
```

Don't forget to edit your `.env` :

```bash
# Replace 'yourpassphrase' by your actual passphrase
echo "JWT_PASSPHRASE=yourpassphrase" | sudo tee -a .env
```

### Shibboleth (optional)

Follow [this guide](https://www.switch.ch/aai/guides/sp/installation/?os=ubuntu#2) to install Shibboleth on 18.04.