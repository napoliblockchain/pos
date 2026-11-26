#!/bin/sh
set -eu
umask 077
install -d -o 33 -g 33 -m 0700 /run/pos-secrets
install -o 33 -g 33 -m 0400 /run/secrets/napay_client_key /run/pos-secrets/client-key
if [ -f /run/secrets/napay_ca ]; then
    install -o 33 -g 33 -m 0400 /run/secrets/napay_ca /run/pos-secrets/ca.crt
elif [ "${POS_MODE:-}" != production ] || [ -n "${NAPAY_CA_FILE:-}" ]; then
    exit 1
fi
install -d -o 33 -g 33 -m 0700 /srv/pos/runtime
[ ! -L /srv/pos/runtime/sessions ] || exit 1
install -d -o 33 -g 33 -m 0700 /srv/pos/runtime/sessions
exec docker-php-entrypoint apache2-foreground
