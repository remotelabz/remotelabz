#!/bin/bash

ENV_FILE=".env.local"

if [ -f /opt/remotelabz/${ENV_FILE} ]; then
     source /opt/remotelabz/${ENV_FILE}
else
     echo "Error: Environment file .env.local not found in /opt/remotelabz/${ENV_FILE}. Please check this file exists and try again."
     exit 1
fi

apt-get update
apt-get -y upgrade
apt install -y openssl

if [ ! -d /home/${SUDO_USER}/EasyRSA-3.0.8 ]; then
echo "🔥 Download of EasyRSA .."
wget -q https://github.com/OpenVPN/easy-rsa/releases/download/v3.0.8/EasyRSA-3.0.8.tgz 
tar -xzf EasyRSA-3.0.8.tgz
fi;

if [ ! -L /home/${SUDO_USER}/EasyRSA ]; then 
echo "🔥 Link creation to EasyRSA .."
ln -s EasyRSA-3.0.8 EasyRSA
fi;

cd /home/${SUDO_USER}/EasyRSA
echo "🔥 Copy /home/${SUDO_USER}/remotelabz/config/apache/cert.cnf"
cp /home/${SUDO_USER}/remotelabz/config/apache/cert.cnf .

sed -i "s/commonName = 192.168.11.131/commonName = ${PUBLIC_ADDRESS}/g" cert.cnf
sed -i "s/IP.1 = 192.168.11.131/IP.1 = ${PUBLIC_ADDRESS}/g" cert.cnf
sed -i "s/ServerName remotelabz.com/ServerName ${PUBLIC_ADDRESS}/g" /etc/apache2/sites-available/100-remotelabz.conf

echo "🔥 Configuration of your certificate with IP or hostname ${PUBLIC_ADDRESS} .."
openssl req -x509 -nodes -days 365 -sha512 -newkey rsa:2048 -keyout RemoteLabz-WebServer.key -out RemoteLabz-WebServer.crt -config /home/${SUDO_USER}/EasyRSA/cert.cnf
cp /home/${SUDO_USER}/EasyRSA/RemoteLabz-WebServer.crt /etc/apache2/
cp /home/${SUDO_USER}/EasyRSA/RemoteLabz-WebServer.key /etc/apache2/
echo "OK"

echo "Activation of SSL module in Apache 2"

a2enmod ssl
a2ensite 200-remotelabz-ssl.conf

echo "🔥 Enable WSS and self-sign in .env.local of RemoteLabz .."
sed -i "s/REMOTELABZ_PROXY_USE_WSS=0/REMOTELABZ_PROXY_USE_WSS=1/g" /opt/remotelabz/.env.local
echo "OK ✔️"

echo "Is your certificate self-signed  ?"
select yn in "Yes" "No"; do
    case $yn in
        Yes ) sed -i "s/REMOTELABZ_PROXY_SSL_CERT_SELFSIGNED=0/REMOTELABZ_PROXY_SSL_CERT_SELFSIGNED=1/g" /opt/remotelabz/.env.local
        echo "Self-signed OK ✔️";
        break;;
        No ) break;;
    esac
done

echo "🔥 Secure Apache configuration .."
sed -i "s/ServerTokens OS/ServerTokens Prod/g" /etc/apache2/conf-enabled/security.conf
sed -i "s/ServerSignature On/ServerSignature Off/g" /etc/apache2/conf-enabled/security.conf

service apache2 restart
service remotelabz-proxy restart

echo "🔥 On the worker, you have to copy the certificate to the directory /opt/remotelabz-worker/config/certs/"
echo "-----------------------------------------------------"
echo "cd ~/EasyRSA"
echo "source /opt/remotelabz/.env.local"
echo "scp ~/EasyRSA/RemoteLabz-WebServer.crt user@${WORKER_SERVER}:~"
echo "sudo scp ~/EasyRSA/RemoteLabz-WebServer.key user@${WORKER_SERVER}:~"
echo "On the worker"
echo "sudo mv RemoteLabz-WebServer.* /opt/remotelabz-worker/config/certs/"
echo "sed -i \"s/REMOTELABZ_PROXY_USE_WSS=0/REMOTELABZ_PROXY_USE_WSS=1/g\" /opt/remotelabz/.env.local"
echo "-----------------------------------------------------"
echo "🔥 You have to change the parameter of REMOTELABZ_PROXY_USE_WSS to 1 in file /opt/remotelabz-worker/.env.local"
echo "🔥 Don't forget to restart your remotelabz-worker service"
