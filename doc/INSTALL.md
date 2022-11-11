How to install RemoteLabz
=========================

- [Requirements](#requirements)
- [Installation](#installation)
    - [Ubuntu](#ubuntu)
- [General Informations](#general-informations)
- [FAQ](#faq)
- [Known Bug](#known-bug)

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
sudo chmod 755 /usr/local/bin/composer
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
sudo cp .env.dist .env
# Replace 'mysqlpassword' by your actual password
echo "MYSQL_PASSWORD=mysqlpassword" | sudo tee -a .env

# or edit ENV file directly
sudo nano .env
```

> **Notes:**
> If you are using mysql >= 8.0 don't forget to create user with password plugin
> ```sql
> CREATE USER 'user'@'%' IDENTIFIED WITH mysql_native_password BY 'password';
> ```

Run the `remotelabz-ctl` configuration utility to setup your database :

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
chown -R www-data:www-data config/jwt
```

Don't forget to edit your `.env` :

```bash
# Replace 'yourpassphrase' by your actual passphrase
echo "JWT_PASSPHRASE=yourpassphrase" | sudo tee -a .env
```

### Instances

In order to be able to control instances on [the worker](https://gitlab.remotelabz.com/remotelabz/remotelabz-worker), you need to start **Symfony Messenger** :

```bash
sudo systemctl start remotelabz
```

**Warning :** When consuming messages, a timestamp is used to determine which messages the messenger worker is able to consume. Therefore, each machines needs to be time-synchronized. We recommand you to use a service like `ntp` to keep your machines synchronized.

You will also need to start the proxy service to display VNC console :

```bash
sudo npm install -g configurable-http-proxy
# then start it
configurable-http-proxy
```

### Shibboleth (optional)

Follow [this guide](https://www.switch.ch/aai/guides/sp/installation/?os=ubuntu#2) to install Shibboleth on 18.04.

### RabbitMQ (optional)

To use RabbitMQ instead of Doctrine as messaging backend, you need the **php-amqp** extension :

```bash
sudo apt-get install -y php7.3-amqp
```

Then, modify the `.env` file according to your RabbitMQ configuration :

```bash
# you may change this string for your credentials and server location
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/messages
```

Don't forget to restart the messenger service :

```bash
sudo systemctl restart remotelabz
```

### Jitsi-Meet (optionnal)
In order to authenticate with Jitsi, RemoteLabz use JWT token authentication from Prosody plugin with a shared secret.
To use it, your need to add [those changes](https://github.com/jitsi/lib-jitsi-meet/blob/master/doc/tokens.md) to your Jitsi server.

Then, you need to allow JWT Token coming from RemoteLabz.
Add theses lines on top of your prosody config file (/etc/prosody/conf.d/x.cfg.lua)
```lua
asap_accepted_issuers = {"remotelabz"}
asap_accepted_audiences = {"rl-jitsi-call"}
```

To complete, edit the `.env` file with your Jitsi URI and your shared secret :

```bash
JITSI_CALL_ENABLE=1
JITSI_CALL_URL="jitsiurl.com"
JITSI_CALL_SECRET="changeThisSecret"
```

General informations
====================
## Ports
- TCP 80, 443 : http(s) pages
- TCP 8000 : websocket
- TCP 8080 : Remotelabz-Worker Internal API (remotelabz to remotelabz-worker)
- TCP 3306 : mysql

## Logs
- Logs are located under `/opt/remotelabz/var/log/`

FAQ
====
### How to increase size of disk on LVM virtual machines
1. Shutdown the VM
2. Right click the VM and select Edit Settings
3. Select the hard disk you would like to extend
4. On the right side, make the provisioned size as large as you need it and confirm
5. Power on the VM and connect to it.
6. Identify your disk name with `sudo fdisk -l` for example /dev/sda
7. `sudo fdisk /dev/sda`
8. Enter `p` to print the partition table
9. Enter `n` to add a new partition
10. Enter `p` again to make it a primary partition
11. Enter the number of your new partition
12. Pick the first cylinder which will most like come at the end of the last partition (this is the default value)
13. Enter the amount of space (default is the rest of space available)
14. Enter `w` to save these changes
15. Restart the VM and log in
16. Type `sudo fdisk -l` and check that a new partition is present
17. Find your volume group with `df -h`.
 * Example: `/dev/mapper/ubuntu--vg-root 15G 4.5G ...`
 * Volume group is: `ubuntu-vg`
18. Extend the volume group : `sudo vgextend [volume group] /dev/sdaX`
 * Example: `sudo vgextend ubuntu-vg /dev/sda3`
19. Find the amount of free space available : `sudo vgdisplay [volume group] | grep "Free"`
20. Expand the logical volume : `sudo lvextend -L+[freespace]G /dev/[volgroup]/[volume]`
 * Example: `sudo lvextend -L+64G /dev/ubuntu-vg/root`
21. Expand the ext3 file system in the logical volume : `sudo resize2fs /dev/[volgroup]/[volume]`
 * Example: `sudo resize2fs /dev/ubuntu-vg/root`
22. You can now run the df command to very that you have more space `df -h`

Known Bug
=========

### 500 Internal Server Error on Login page
Wrong permission for config/jwt/
```bash
# Change owner of config/jwt/*
chown -R www-data:www-data config/jwt
``` 

### 500 Internal Server Error on Labs page
Wrong permission in var/cache/prod/
```bash
# Change owner of cache/prod/
chown -R www-data:www-data *
```
