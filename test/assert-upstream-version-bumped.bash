#!/usr/bin/env bash
set -euo pipefail
trap 's=$?; echo "$0: Error on line "$LINENO": $BASH_COMMAND"; exit $s' ERR

if [[ $# -ne 2 ]]; then
    echo "usage: $0 <base-ref> <head-ref>"
    exit 1
fi

base_ref="$1"
head_ref="$2"

if [[ "$base_ref" =~ ^0+$ ]]; then
    base_ref="$(git rev-list --max-parents=0 "$head_ref" | tail -n 1)"
fi

changed_files="$(git diff --name-only "$base_ref" "$head_ref")"

# print out a list of changed files, if nothing was printed then exit
if ! grep -E '^(webroot/css/|webroot/js/)' <<< "$changed_files"; then
    exit 0
fi

version_base="$(git show "$base_ref:deployment/config.base.ini" | grep 'Current upstream version')"
version_head="$(git show "$head_ref:deployment/config.base.ini" | grep 'Current upstream version')"
if [ "$version_base" == "$version_head" ]; then
    echo "webroot/css or webroot/js changed, but deployment/config.base.ini upstream.version was not updated"
    exit 1
fi
