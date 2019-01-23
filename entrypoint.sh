#!/bin/bash
set -e

cd /app
composer install
yarn install
yarn encore dev
chmod 777 -R vendor/
chmod 777 -R node_modules/
chmod 777 -R public/build/
/app/bin/console make:migration
/app/bin/console doctrine:migrations:migrate -n
/app/bin/console doctrine:fixtures:load -n
/app/bin/console server:run $SERVER_HOST:$SERVER_PORT
