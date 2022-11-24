#!/bin/bash

apt-get update
apt-get -y upgrade
apt install -y curl gnupg php zip unzip php-bcmath php-curl php-gd php-intl php-mbstring php-mysql php-xml php-zip ntp openvpn libapache2-mod-php7.4 fail2ban
php -r "copy('https://getcomposer.org/download/2.2.6/composer.phar', 'composer.phar');"
cp composer.phar /usr/local/bin/composer
chmod a+x /usr/local/bin/composer
curl -sL https://deb.nodesource.com/setup_14.x | sudo -E bash -
apt-get install -y nodejs
npm install -g yarn
npm install -g configurable-http-proxy
apt-get install -y mysql-server
cat > mysql_secure_sql.sql << EOF
ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY 'RemoteLabz-2022$';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
CREATE USER IF NOT EXISTS 'user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'Mysql-Pa33wrd$';
CREATE DATABASE IF NOT EXISTS remotelabz;
GRANT ALL ON remotelabz.* TO 'user'@'localhost';
FLUSH PRIVILEGES;
EOF

mysql -sfu root < mysql_secure_sql.sql
rm ./mysql_secure_sql.sql

echo "The MySQL is configured with user \"user\" and the password \"Mysql-Pa33wrd$\""
apt-get install -y rabbitmq-server php-amqp
if ! rabbitmqctl list_users | grep -q 'remotelabz-amqp'; then
    rabbitmqctl add_user 'remotelabz-amqp' 'password-amqp'
fi
rabbitmqctl set_permissions -p '/' 'remotelabz-amqp' '.*' '.*' '.*'
service rabbitmq-server restart

#To test if the connexion to the RabbitMQ works fine
#rabbitmqctl authenticate_user 'remotelabz-amqp' "password-amqp"

cd ~
wget -q https://github.com/OpenVPN/easy-rsa/releases/download/v3.0.8/EasyRSA-3.0.8.tgz 
tar -xzf EasyRSA-3.0.8.tgz
ln -s EasyRSA-3.0.8 EasyRSA
cd EasyRSA

cat > vars << EOF
set_var EASYRSA_BATCH           "yes"
set_var EASYRSA_REQ_CN         "RemoteLabz-VPNServer-CA"
set_var EASYRSA_REQ_COUNTRY    "FR"
set_var EASYRSA_REQ_PROVINCE   "Grand-Est"
set_var EASYRSA_REQ_CITY       "Reims"
set_var EASYRSA_REQ_ORG        "RemoteLabz"
set_var EASYRSA_REQ_EMAIL      "contact@remotelabz.com"
set_var EASYRSA_REQ_OU         "RemoteLabz-VPNServer"
set_var EASYRSA_ALGO           "ec"
set_var EASYRSA_DIGEST         "sha512"
set_var EASYRSA_CURVE          secp384r1
#5 ans de validité pour le CA
set_var EASYRSA_CA_EXPIRE      1825
#5 ans de validité pour les certificats
set_var EASYRSA_CERT_EXPIRE    1825
EOF

sed -i "s/RANDFILE/#RANDFILE/g" openssl-easyrsa.cnf

./easyrsa init-pki
echo "🔥 In the documentation, the password used to secure the CA certificate is 'R3mot3!abz-0penVPN-CA2020'"
echo "You can use the same password for the next question"
echo "This password have to be added in you .env file. It is used to sign all users VPN certificate"
./easyrsa build-ca

cp ./vars ./vars-ca

sed -i "s/RemoteLabz-VPNServer-CA/RemoteLabz-VPNServer/g" vars

echo "Generation of the client certificate"
./easyrsa gen-req RemoteLabz-VPNServer nopass
echo "You have to type your CA password use before (R3mot3!abz-0penVPN-CA2020)"
./easyrsa sign-req server RemoteLabz-VPNServer

echo "Copy the certificate file to your openvpn directory"
cp pki/issued/RemoteLabz-VPNServer.crt /etc/openvpn/server
cp pki/private/RemoteLabz-VPNServer.key /etc/openvpn/server
cp pki/ca.crt /etc/openvpn/server
cp pki/private/ca.key /etc/openvpn/server


openvpn --genkey --secret ta.key
cp ta.key /etc/openvpn/server
openssl dhparam -out dh2048.pem 2048
mv dh2048.pem /etc/openvpn/server
chown www-data: /etc/openvpn/server -R

cat > /etc/openvpn/server/server.conf << EOF
port 1194
proto udp
dev tun
ca ca.crt
cert RemoteLabz-VPNServer.crt
key RemoteLabz-VPNServer.key
dh dh2048.pem
cipher AES-256-GCM
tls-auth ta.key 0
server 10.8.0.0 255.255.255.0
keepalive 10 120
persist-key
persist-tun
status /var/log/openvpn/openvpn-status.log
log /var/log/openvpn/openvpn.log
verb 1
mute 20
explicit-exit-notify 1
duplicate-cn
push "route 10.11.0.0 255.255.0.0"
EOF

chown :www-data /etc/openvpn/client
chmod g+w /etc/openvpn/client

systemctl enable openvpn-server@server
service openvpn-server@server start

sysctl -w net.ipv4.ip_forward=1
sed -i 's/net.ipv4.ip_forward = 0/net.ipv4.ip_forward = 1/g' /etc/sysctl.conf
sed -i 's/#net.ipv4.ip_forward =/net.ipv4.ip_forward =/g' /etc/sysctl.conf

# To avoid error message "Too many opened files" and containers don't stop
sysctl -n -w fs.inotify.max_user_instances=512
sysctl -n -w fs.inotify.max_user_watches=16384
echo "fs.inotify.max_user_watches=16384" >> /etc/sysctl.conf
echo "fs.inotify.max_user_instances=512" >> /etc/sysctl.conf


echo "🔥 The root password for your MySQL database is set to RemoteLabz-2022$"
echo "🔥 The user password for the remotelabz MySQL database is set to Mysql-Pa33wrd$"
echo "Your .env.local will be configured with this default password. If you choose to change it, don't forget to modify your .env.local file"
echo "To change it, you can read the documentation online httsp://docs.remotelabz.com"
