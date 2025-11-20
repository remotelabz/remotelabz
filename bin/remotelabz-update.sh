#!/bin/bash
#if  [ -f package-lock.json ]; then
#    rm package-lock.json
#fi;
cd /opt/remotelabz
git fetch
mv /opt/remotelabz/config/packages/messenger.yaml ~/
git restore /opt/remotelabz/config/packages/messenger.yaml
mv /opt/remotelabz/config/packages/dev/web_profiler.yaml ~/
git restore /opt/remotelabz/config/packages/dev/web_profiler.yaml
git pull
mv ~/messenger.yaml /opt/remotelabz/config/packages/messenger.yaml
mv ~/web_profiler.yaml /opt/remotelabz/config/packages/dev/web_profiler.yaml
composer update
yarn
yarn encore prod
php bin/console doctrine:migrations:migrate -n
#npx browserslist@latest --update-db
php bin/console cache:clear
chown remotelabz:www-data * -R
chmod g+w /opt/remotelabz/var -R
chmod g+w /opt/remotelabz/public/uploads -R
chmod g+r config/jwt/private.pem
sed -i '/push "route/d' /etc/openvpn/server/server.conf
NETWORK=`awk -F "=" '/BASE_NETWORK=/{print $2}' .env.local`
NETWORK_MASK=`awk -F "=" '/BASE_NETWORK_NETMASK=/{print $2}' .env.local`
echo "push \"route $NETWORK $NETWORK_MASK\"" | tee -a /etc/openvpn/server/server.conf
systemctl daemon-reload
service remotelabz restart