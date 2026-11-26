#!/bin/sh
# SIP-021 fase 2: e2e reale POS v2 <-> Napay delle aperture esclusive, stack interamente usa-e-getta:
# MySQL + bootstrap + Napay web (sorgente NAPAY_SOURCE in sola lettura) + POS v2 (questo repo) + gateway TLS proprio.
# Nessuna porta host, rete interna dedicata, CA e credenziali casuali per esecuzione (0700/0600), cleanup del solo progetto.
# Uso: NAPAY_SOURCE=/percorso/napay NAPAY_TEST_DATASET=/percorso/dataset-v2 sh tests/docker/openings-e2e.sh
# NAPAY_E2E_SUITE=payments (SIP-021 fase 3): aggiunge chain Besu usa-e-getta e transfer reali del pagatore di test;
# richiede le immagini locali napay/chain-tools:dev e hyperledger/besu:26.9.0.
set -eu
pos_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
napay_root=$(CDPATH= cd -- "${NAPAY_SOURCE:?Set NAPAY_SOURCE to the Napay checkout under test}" && pwd)
roles=${NAPAY_E2E_DB_ROLES:-legacy}
case "$roles" in legacy|restricted) ;; *) echo 'Unknown E2E database role mode.' >&2; exit 1 ;; esac
suite=${NAPAY_E2E_SUITE:-openings}
case "$suite" in openings|payments) ;; *) echo 'Unknown E2E suite.' >&2; exit 1 ;; esac
if [ "$suite" = payments ]; then
    [ -f "$napay_root/tests/application/tts-pay.mjs" ] || { echo 'NAPAY_SOURCE lacks the TTS payments backend.' >&2; exit 1; }
    docker image inspect napay/chain-tools:dev hyperledger/besu:26.9.0 >/dev/null
fi
if [ "$roles" = restricted ]; then
    [ -f "$napay_root/docker-compose.roles.yml" ] && [ -f "$napay_root/components/db/DatabaseRoleBootstrap.php" ] || exit 1
fi
[ -f "$napay_root/tests/docker/runtime.sh" ] && [ -f "$napay_root/migrations/m261113_150000_create_pos_terminal_openings.php" ] || { echo 'NAPAY_SOURCE lacks the openings backend.' >&2; exit 1; }
export NAPAY_E2E_NAPAY="$napay_root" NAPAY_E2E_POS="$pos_root"
export NAPAY_E2E_DB_ROLES="$roles" NAPAY_E2E_SUITE="$suite"
umask 077
private=$(mktemp -d)
trap 'rm -rf "$private"' EXIT
trap 'exit 1' HUP INT TERM
export NAPAY_E2E_ROLES_SETUP="$private/roles-setup.sh"
cat > "$NAPAY_E2E_ROLES_SETUP" <<'ROLES'
openssl rand -hex 32 > "$work/migrator-password"
openssl rand -hex 32 > "$work/worker-password"
cat >> "$work/test.env" <<ENV
NAPAY_DB_MIGRATOR_USERNAME=napay_migrator
NAPAY_DB_WORKER_USERNAME=napay_worker
NAPAY_DB_MIGRATOR_SECRET_FILE=$work/migrator-password
NAPAY_DB_WORKER_SECRET_FILE=$work/worker-password
ENV
compose() { docker compose -p "$project" --env-file "$work/test.env" -f "$root/docker-compose.yml" -f "$work/override.yml" -f "$root/docker-compose.roles.yml" "$@"; }
ROLES
# Stessa preparazione del runtime Napay (MySQL, bootstrap, web, fixture base); si ferma prima delle suite Napay.
awk -v roles="$roles" '
 /^root=/ { print "root=${NAPAY_E2E_NAPAY:?}"; next }
 /^docker volume create / && roles == "restricted" { print ". \"$NAPAY_E2E_ROLES_SETUP\"" }
 /^compose run --rm --no-deps --entrypoint php bootstrap .*users-access.php$/ { found=1; exit }
 { print }
 END { if (!found) exit 1 }
' "$napay_root/tests/docker/runtime.sh" > "$private/runtime.sh"
cat >> "$private/runtime.sh" <<'E2E'
pos=${NAPAY_E2E_POS:?}
e2e=$work/e2e
mkdir -m 700 "$e2e" "$e2e/gateway" "$e2e/pos-secrets" "$e2e/rogue-secrets" "$e2e/state"
# CA privata del test e certificato del gateway; una seconda CA estranea per il POS "badca".
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj '/CN=napay e2e test CA' -keyout "$e2e/ca.key" -out "$e2e/ca.pem" >/dev/null 2>&1
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj '/CN=napay e2e rogue CA' -keyout "$e2e/rogue.key" -out "$e2e/rogue.pem" >/dev/null 2>&1
openssl req -newkey rsa:2048 -nodes -subj '/CN=napay e2e gateway' -keyout "$e2e/gateway/server.key" -out "$e2e/gateway.csr" >/dev/null 2>&1
printf 'subjectAltName=DNS:napay.local,DNS:pos.local,DNS:pos-badca.local,DNS:pos-noca.local\n' > "$e2e/gateway.ext"
openssl x509 -req -in "$e2e/gateway.csr" -CA "$e2e/ca.pem" -CAkey "$e2e/ca.key" -CAcreateserial -days 1 -extfile "$e2e/gateway.ext" -out "$e2e/gateway/server.pem" >/dev/null 2>&1
rm -f "$e2e/ca.key" "$e2e/rogue.key" "$e2e/gateway.csr"
cp "$e2e/ca.pem" "$e2e/pos-secrets/ca.crt"
cp "$e2e/rogue.pem" "$e2e/rogue-secrets/ca.crt"
# Canary generata dal runtime di test (la copia raw resta nell'env delle fixture, mai nel browser).
sed -n 's/^NAPAY_TEST_POS_CLIENT_KEY=//p' "$work/test.env" > "$e2e/pos-secrets/client-key"
cp "$e2e/pos-secrets/client-key" "$e2e/rogue-secrets/client-key"
openssl rand -hex 24 > "$e2e/password"
cookie_key=$(openssl rand -hex 24)
printf 'POS_MODE=preview\nCOOKIE_VALIDATION_KEY=%s\n' "$cookie_key" > "$e2e/pos.env"
unset cookie_key
cat > "$e2e/Caddyfile" <<'CADDY'
{
	admin off
	auto_https off
	persist_config off
}
https://napay.local:443 {
	tls /certs/server.pem /certs/server.key
	reverse_proxy web:80
}
https://pos.local:443 {
	tls /certs/server.pem /certs/server.key
	reverse_proxy pos:80
}
https://pos-badca.local:443 {
	tls /certs/server.pem /certs/server.key
	reverse_proxy pos-badca:80
}
https://pos-noca.local:443 {
	tls /certs/server.pem /certs/server.key
	reverse_proxy pos-noca:80
}
CADDY
uid=$(id -u); gid=$(id -g)
cat > "$work/e2e.yml" <<YAML
x-pos: &pos
  image: napay/pos-v2:dev
  env_file: $e2e/pos.env
  environment: &pos-env
    NAPAY_API_BASE_URL: https://napay.local/api/pos/v1
    NAPAY_POS_CLIENT_KEY_FILE: /run/pos-secrets/client-key
    NAPAY_CA_FILE: /run/pos-secrets/ca.crt
  read_only: true
  security_opt: [no-new-privileges:true]
  tmpfs: [/tmp, /var/run/apache2, /var/lock/apache2, /run/pos-secrets, /srv/pos/runtime]
  networks: [e2e]
  healthcheck:
    test: [CMD, curl, --fail, --silent, http://127.0.0.1/health]
    interval: 3s
    timeout: 3s
    retries: 20
services:
  web:
    networks: [database, e2e]
  gateway:
    image: caddy:2.8.4-alpine
    user: "$uid:$gid"
    read_only: true
    cap_drop: [ALL]
    # il binario caddy dell'immagine ha la file capability di bind: va lasciata nel bounding set
    cap_add: [NET_BIND_SERVICE]
    security_opt: [no-new-privileges:true]
    sysctls: [net.ipv4.ip_unprivileged_port_start=443]
    environment: {XDG_DATA_HOME: /tmp/data, XDG_CONFIG_HOME: /tmp/config}
    tmpfs: ["/tmp:mode=1777"]
    volumes:
      - $e2e/Caddyfile:/etc/caddy/Caddyfile:ro
      - $e2e/gateway:/certs:ro
    networks:
      e2e:
        aliases: [napay.local, pos.local, pos-badca.local, pos-noca.local]
    healthcheck:
      test: [CMD, nc, -z, 127.0.0.1, "443"]
      interval: 2s
      timeout: 2s
      retries: 20
  pos:
    <<: *pos
    volumes: &pos-src
      - $pos/config:/srv/pos/config:ro
      - $pos/controllers:/srv/pos/controllers:ro
      - $pos/models:/srv/pos/models:ro
      - $pos/components:/srv/pos/components:ro
      - $pos/views:/srv/pos/views:ro
      - $pos/web:/srv/pos/web:ro
      - $pos/messages:/srv/pos/messages:ro
      - $pos/fonts:/srv/pos/fonts:ro
      - $pos/vendor:/srv/pos/vendor:ro
      - $e2e/pos-secrets/client-key:/run/secrets/napay_client_key:ro
      - $e2e/pos-secrets/ca.crt:/run/secrets/napay_ca:ro
  pos-badca:
    <<: *pos
    volumes:
      - $pos/config:/srv/pos/config:ro
      - $pos/controllers:/srv/pos/controllers:ro
      - $pos/models:/srv/pos/models:ro
      - $pos/components:/srv/pos/components:ro
      - $pos/views:/srv/pos/views:ro
      - $pos/web:/srv/pos/web:ro
      - $pos/messages:/srv/pos/messages:ro
      - $pos/fonts:/srv/pos/fonts:ro
      - $pos/vendor:/srv/pos/vendor:ro
      - $e2e/rogue-secrets/client-key:/run/secrets/napay_client_key:ro
      - $e2e/rogue-secrets/ca.crt:/run/secrets/napay_ca:ro
  pos-noca:
    <<: *pos
    environment:
      <<: *pos-env
      NAPAY_CA_FILE: /run/pos-secrets/absent.crt
    volumes: *pos-src
  driver:
    image: napay/pos-v2:dev
    user: "$uid:$gid"
    entrypoint: [php]
    working_dir: /srv/pos
    read_only: true
    cap_drop: [ALL]
    security_opt: [no-new-privileges:true]
    tmpfs: ["/tmp:mode=1777"]
    volumes:
      - $pos/tests:/srv/pos/tests:ro
      - $e2e:/e2e:ro
      - $e2e/state:/state
    networks: [e2e]
    profiles: [driver]
networks:
  e2e:
    internal: true
YAML
compose() {
    if [ "$NAPAY_E2E_DB_ROLES" = restricted ]; then set -- -f "$root/docker-compose.roles.yml" "$@"; fi
    if [ "$NAPAY_E2E_SUITE" = payments ]; then set -- -f "$work/chain.yml" "$@"; fi
    docker compose -p "$project" --env-file "$work/test.env" -f "$root/docker-compose.yml" -f "$work/override.yml" -f "$work/e2e.yml" "$@"
}
if [ "$NAPAY_E2E_SUITE" = payments ]; then
    # SIP-021 fase 3: chain di test sulla rete interna e2e; volume e container rimossi dal cleanup del progetto.
    cat > "$work/chain.yml" <<YAML
services:
  tts-chain-init:
    image: napay/chain-tools:dev
    command: [node, init.mjs]
    environment: {CHAIN_ID: "2118"}
    volumes: [tts_chain:/data]
    networks: [e2e]
  tts-besu:
    image: hyperledger/besu:26.9.0
    user: "1000:1000"
    command: [--data-path=/data/db, --genesis-file=/data/genesis.json, --node-private-key-file=/data/key, --rpc-http-enabled,
      --rpc-http-host=0.0.0.0, --rpc-http-api=ETH,NET,WEB3, --host-allowlist=*, --min-gas-price=0,
      --discovery-enabled=false, --sync-mode=FULL, --sync-min-peers=0]
    volumes: [tts_chain:/data]
    networks: [e2e]
    healthcheck:
      test: [CMD, bash, -c, "exec 3<>/dev/tcp/127.0.0.1/8545"]
      interval: 3s
      retries: 40
  tts-chain-tools:
    image: napay/chain-tools:dev
    environment: {CHAIN_RPC_URL: "http://tts-besu:8545", CHAIN_ID: "2118"}
    volumes: [tts_chain:/data, $root/tests/application/tts-pay.mjs:/tools/tts-pay.mjs:ro]
    networks: [e2e]
volumes:
  tts_chain:
YAML
    compose run --rm --no-deps tts-chain-init >/dev/null
    compose up -d --no-deps --wait --wait-timeout 180 tts-besu
    compose run --rm --no-deps tts-chain-tools node deploy.mjs >/dev/null
    token=$(compose run --rm --no-deps tts-chain-tools node -e 'console.log(JSON.parse(require("fs").readFileSync("/data/tts.json","utf8")).address)')
    printf 'TTS_RPC_URL=http://tts-besu:8545\nTTS_CHAIN_ID=2118\nTTS_TOKEN_ADDRESS=%s\n' "$token" >> "$work/test.env"
fi
compose up -d --no-build --wait --wait-timeout 240 web gateway pos pos-badca pos-noca
if [ "$NAPAY_E2E_DB_ROLES" = restricted ]; then
    compose exec -T web php /var/www/html/docker/bootstrap-check.php
    compose run --rm --no-deps worker-db-check
    compose exec -T web php -r '
    try {
        require "/var/www/html/config/env.php";
        if (env("NAPAY_DB_ROLE") !== "web" || is_file("/run/secrets/mysql_root_password") || is_file("/run/secrets/napay_db_migrator_password")) exit(1);
        $db = new PDO(env_required("DB_DSN"), env_required("DB_USERNAME"), env_required("DB_PASSWORD"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($db->query("SELECT CURRENT_USER()")->fetchColumn() !== "napay_test@%") exit(1);
        foreach (["SELECT COUNT(*) FROM btcpay_merchant_credentials", "DELETE FROM pos_terminal_openings WHERE 1=0", "CREATE TABLE e2e_privilege_escape (id INT)"] as $sql) {
            $denied = false;
            try { $db->exec($sql); } catch (PDOException $e) { $denied = in_array((int) ($e->errorInfo[1] ?? 0), [1142, 1143], true); }
            if (!$denied) exit(1);
        }
        echo "PASS: E2E web uses restricted credentials, no DDL, history deletion or BTCPay secrets.\n";
    } catch (Throwable $e) { fwrite(STDERR,"FAIL: E2E restricted database verification.\n"); exit(1); }
    '
fi
# Fixture Napay da stdin nel web del progetto: la password per esecuzione non passa mai negli argomenti.
wallet=""
if [ "$NAPAY_E2E_SUITE" = payments ]; then wallet="0x$(openssl rand -hex 20)"; fi
{ printf "<?php \$e2ePassword = '%s'; \$e2eWallet = '%s'; ?>" "$(cat "$e2e/password")" "$wallet"; cat "$pos/tests/integration/napay-fixture.php"; } | compose exec -T web php > "$e2e/fixture.json"
if [ "$NAPAY_E2E_SUITE" = openings ]; then
    compose run --rm --no-deps driver tests/integration/openings-e2e.php
    compose logs --no-color web gateway pos pos-badca pos-noca | compose run --rm -T --no-deps driver tests/integration/openings-e2e.php --logs
else
    # Fase 1 dal browser simulato: richiesta creata nel POS. Poi transfer reale con il riferimento letto da Napay.
    compose run --rm --no-deps driver tests/integration/payments-e2e.php create
    compose exec -T web php -r '
        require "/var/www/html/config/env.php";
        $db = new PDO(env_required("DB_DSN"), env_required("DB_USERNAME"), env_required("DB_PASSWORD"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $plan = [];
        foreach (explode(",", trim(stream_get_contents(STDIN))) as $id) {
            $row = $db->query("SELECT to_address, amount_cents, reference FROM pos_tts_payments WHERE public_id=" . $db->quote($id))->fetch(PDO::FETCH_ASSOC);
            if ($row === false) exit(1);
            $plan[] = ["label" => $id, "to" => $row["to_address"], "cents" => (int) $row["amount_cents"], "ref" => $row["reference"]];
        }
        echo json_encode($plan);
    ' < "$e2e/state/pay-ids" \
        | compose run --rm --no-deps -T tts-chain-tools node tts-pay.mjs > "$e2e/state/paid.json"
    compose run --rm --no-deps driver tests/integration/payments-e2e.php verify
    compose logs --no-color web gateway pos | compose run --rm -T --no-deps driver tests/integration/payments-e2e.php --logs
fi
E2E
sh "$private/runtime.sh"
