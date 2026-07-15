#!/bin/bash
set -euo pipefail
trap 's=$?; echo "$0: Error on line "$LINENO": $BASH_COMMAND"; exit $s' ERR

cd "$(dirname "$0")"

# docker won't let you copy files from the parent directory of the dockerfile
# one workaround is to use the `context` option in docker compose
cp ../../composer.json web/composer.json
cp ../../composer.lock web/composer.lock
cp ../../package.json web/package.json
cp ../../package-lock.json web/package-lock.json

docker compose down
docker compose build
# docker image prune
