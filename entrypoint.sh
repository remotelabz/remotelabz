#!/bin/bash
set -e

cd /app
gosu composer install
gosu yarn install
gosu yarn encore dev
# chmod 777 -R vendor/
# chmod 777 -R node_modules/
/app/bin/console make:migration
/app/bin/console doctrine:migrations:migrate -n
/app/bin/console doctrine:fixtures:load -n
/app/bin/console server:run $SERVER_HOST:$SERVER_PORT
