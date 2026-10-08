#!/bin/sh

set -eu

backend_directory=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)

cd "$backend_directory"

APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
DB_PASSWORD='staging-contract-password' \
APP_BIND_ADDRESS='0.0.0.0' \
APP_PORT='18080' \
APP_DOMAIN='staging.example.com' \
ACME_EMAIL='ops@example.com' \
docker compose \
    -f compose.yaml \
    -f compose.staging.yaml \
    --env-file /dev/null \
    config \
    --format json \
    | jq -e '
        (.services.web | has("ports") | not)
        and ([.services.caddy.ports[].published] | sort == ["443", "443", "80"])
    ' >/dev/null
