#!/usr/bin/env bash
# Serverda ishlatish uchun: .env.production faylini yaratadi/to'ldiradi.
# Ishlatish: cd /var/www/halalscanner/backend && ./deploy-env-setup.sh
set -euo pipefail

ENV_FILE=".env.production"
DOMAIN="scaner.yulduzcha.net"
APP_PORT="18086"

if [ ! -f "$ENV_FILE" ]; then
    cp .env.docker.example "$ENV_FILE"
    chmod 600 "$ENV_FILE"
fi

set_kv() {
    local key="$1" value="$2"
    if grep -q "^${key}=" "$ENV_FILE"; then
        sed -i.bak "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
        rm -f "${ENV_FILE}.bak"
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    fi
}

get_kv() {
    local key="$1"
    grep "^${key}=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2-
}

# APP_KEY va DB_PASSWORD: faqat bo'sh bo'lsa generatsiya qilinadi (qayta ishga tushirilsa eski qiymat saqlanadi)
if [ -z "$(get_kv APP_KEY)" ]; then
    set_kv APP_KEY "base64:$(openssl rand -base64 32 | tr -d '\n')"
    echo "APP_KEY generatsiya qilindi."
fi

if [ -z "$(get_kv DB_PASSWORD)" ]; then
    set_kv DB_PASSWORD "$(openssl rand -hex 24)"
    echo "DB_PASSWORD generatsiya qilindi."
fi

set_kv APP_ENV "production"
set_kv APP_DEBUG "false"
set_kv APP_URL "https://${DOMAIN}"
set_kv APP_BIND_ADDRESS "127.0.0.1"
set_kv APP_PORT "$APP_PORT"
set_kv APP_DOMAIN "$DOMAIN"

if [ -z "$(get_kv GEMINI_API_KEY)" ]; then
    read -r -s -p "GEMINI_API_KEY kiriting (ko'rinmaydi): " gemini_key
    echo
    set_kv GEMINI_API_KEY "$gemini_key"
fi

chmod 600 "$ENV_FILE"
echo "Tayyor: $ENV_FILE to'ldirildi."
echo "Tekshirish uchun: docker compose -f compose.yaml -f compose.prod.yml --env-file $ENV_FILE config --quiet"
