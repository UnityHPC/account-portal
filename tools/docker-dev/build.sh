#!/bin/bash
set -e
cd "$(dirname "$0")"
docker compose down
docker compose build
# docker image prune
