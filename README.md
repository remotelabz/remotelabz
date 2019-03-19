RemoteLabz v2
=============

This project is the 2nd version of [RemoteLabz](remotelabz.univ-reims.fr), and was originally written in Symfony 2.8. Its being updated to Symfony 4 in branch **symfony4**.

# Install

You can deploy the project locally for development or testing purposes.

## Requirements
- Docker CE (>17.06)
- docker-compose

## Steps
1. Clone the repository
2. Go to repository root directory
3. Change used branch to use Symfony 4 (master is not up-to-date atm)
    ```bash
    git checkout symfony4
    ```
4. Create and modify as your needs the `.env` file
    ```bash
    cp .env.dist .env
5. Start services
    ```bash
    docker-compose up -d --build
    ```
6. Dependencies and plugins via *compose* and *yarn* will be installed automatically. Wait a moment for them to be installed. You may check their status by using the following commands :
    ```bash
    docker-compose logs composer
    ```
    and
    ```bash
    docker-compose logs yarn
    ```
7. Webpackize assets
    ```bash
    docker-compose run --rm yarn encore dev
    ```
8. Execute the migrations on database
    ```bash
    docker-compose run --rm console doctrine:migrations:migrate -n
    ```
9. Load basic data via fixtures
    ```bash
    docker-compose run --rm console doctrine:fixtures:load -n
    ```
10. Don't forget to symlink assets (otherwise XHR will not work)
    ```bash
    docker-compose run --rm console assets:install --symlink public --relative
    ```

You can now access the website via http://localhost/login.

To stop services, run the following command :
```bash
docker-compose stop # stop all services
docker-compose rm # if you want to delete containers as well
```

# Use

## Source code reloading

As using *docker bind mounts*, the modifications you will make on source code will not need any container restart. You may then modify a file, save it and check your changes *on-the-fly* by refreshing your browser.

## Data persistence

Assets and packages installed by composer and yarn, as well as database itself are stored into *docker volumes*. It means that even if you stop, restart or delete containers, data will persist.

See [docker command-line reference](https://docs.docker.com/engine/reference/commandline/cli/) to know how to manage and delete volumes.

**Warning :** the `.env` file, because it is loaded by docker while starting **mysql** service, is the only source file that require a restart (for now, only if you modify **mysql** related variables).

## Console

The console provided by Symfony (`bin/console`) is usable by the `console` service. If you need to run a command, just run the classical way using `docker-compose`.
```bash
# adding --rm flag instruct docker to remove temporary container that will be created.
# it is not required, and those containers will be deleted when you will run
# docker-compose rm

docker-compose run --rm console [options] <command> [args...]
```

For example :
```bash
# this command is equal to : bin/console -v make:entity MyClass
docker-compose run --rm console -v make:entity MyClass
```

You also can run any other command you need :
```bash
docker-compose run --rm console ls -la /app
```

## Other services

You can use the same method to run `composer` or `yarn` if you need to add packages to the project.

```bash
 # you may replace `require` by any composer command
docker-compose run --rm composer require <package>

# same for yarn
docker-compose run --rm yarn add <package>
```

The same logic is applying to other services. Otherwise, if you need to run a command on a running container (like `apache` or `mysql`), use the `exec` instruction instead.
```bash
# notice that you don't need to specify --rm here
docker-compose exec apache <command>
```

## Run tests

To run the project test suite, you can use the following command :

```bash
docker-compose run --rm console phpunit --configuration /app/phpunit.xml.dist
```