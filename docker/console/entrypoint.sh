#!/bin/bash
set -e

if [[ $1 == *:* || $1 == -* || $1 == "about" || $1 == "help" || $1 == "list" ]]; then
    set -- /app/bin/console "$@"
fi

exec "$@"