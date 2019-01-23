#!/bin/bash
set -e

cd /app
composer install
yarn install
yarn encore dev
/app/bin/console make:migration
/app/bin/console doctrine:migrations:migrate -n
/app/bin/console doctrine:fixtures:load -n
/app/bin/console server:run $SERVER_HOST:$SERVER_PORT
