#!/usr/bin/env bash
# SIP-036: actual production image, external private fixture network and image rollback. No shared stack/ports.
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
repo=$(git -C "$root" rev-parse --show-toplevel)
revision=$(git -C "$root" rev-parse HEAD)
for image in php:8.3.36-apache composer:2.10.3 caddy:2.8.4-alpine; do docker image inspect "$image" >/dev/null; done
umask 077
work=$(mktemp -d)
run=$(openssl rand -hex 5)
project="pos-test-$run"
network="$project-edge"
backend="$project-backend"
source_dir="$work/source"
mkdir -m 700 "$source_dir" "$work/private" "$work/private/runtime"
images=()
cleanup() {
    code=$?; trap - EXIT
    if (( code != 0 )); then
        debug=$(mktemp -d /tmp/pos-release-diagnostics.XXXXXXXX)
        for file in "$work"/*.log "$work/private/.pos-release-$project"/*.log; do
            [[ ! -f $file ]] || cp -- "$file" "$debug/$(basename "$file")"
        done
        printf 'Private fixture diagnostics: %s\n' "$debug" >&2
    fi
    docker ps -aq --filter "label=com.docker.compose.project=$project" | while read -r id; do docker rm -f "$id" >/dev/null 2>&1 || true; done
    docker rm -f "$backend" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
    for image in "${images[@]}"; do docker image rm "$image" >/dev/null 2>&1 || true; done
    if [[ -d $work/private/runtime ]]; then
        docker run --rm --network none --read-only -v "$work/private/runtime:/fixture" --entrypoint sh php:8.3.36-apache \
            -c 'find /fixture -mindepth 1 -delete; chown "$1:$2" /fixture' sh "$(id -u)" "$(id -g)" >/dev/null 2>&1 || true
    fi
    rm -rf "$work"
    exit "$code"
}
trap cleanup EXIT
trap 'exit 1' HUP INT TERM
# Archive committed source only; the private fixture has its own Git history and release tags.
git -C "$repo" archive "$revision" v2 | tar -x -C "$source_dir"
# The test stays offline: reuse cached vendor only after exact lock metadata verification.
docker image inspect napay/pos-v2:dev >/dev/null
docker run --rm -i --network none --read-only -v "$source_dir/v2/composer.lock:/fixture.lock:ro" --entrypoint php napay/pos-v2:dev <<'PHP'
<?php
$lock = json_decode(file_get_contents('/fixture.lock'), true, 512, JSON_THROW_ON_ERROR);
$installed = json_decode(file_get_contents('/srv/pos/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
$fingerprints = static function (array $packages): array {
    $result = [];
    foreach ($packages as $package) {
        $result[$package['name']] = [$package['version'], $package['source']['reference'] ?? null, $package['dist']['reference'] ?? null];
    }
    ksort($result);
    return $result;
};
if ($fingerprints($lock['packages']) !== $fingerprints($installed['packages'])) {
    fwrite(STDERR, "Cached dependencies do not match composer.lock.\n");
    exit(1);
}
PHP
# Only the disposable fixture replaces dependency acquisition; application stages are unchanged.
{
    printf '%s\n' 'FROM napay/pos-v2:dev AS dependencies' 'WORKDIR /app' 'RUN cp -a /srv/pos/vendor /app/vendor'
    sed -n '/^FROM php:/,$p' "$source_dir/v2/docker/Dockerfile"
} > "$work/Dockerfile.offline"
mv "$work/Dockerfile.offline" "$source_dir/v2/docker/Dockerfile"
git -C "$source_dir" init -q
git -C "$source_dir" add v2
git -C "$source_dir" -c user.name=Fixture -c user.email=fixture@example.invalid commit -qm 'production fixture'
script="$source_dir/v2/docker/deploy.sh"
key=$(openssl rand -base64 32 | tr '+/' '-_' | tr -d '=')
printf '%s\n' "$key" > "$work/private/client-key"
cookie=$(openssl rand -hex 32)
cat > "$work/private/pos.env" <<ENV
POS_MODE=production
COOKIE_VALIDATION_KEY=$cookie
NAPAY_API_BASE_URL=https://napay.production.invalid/api/pos/v1
POS_TRUSTED_PROXIES=10.250.0.0/24
NAPAY_CLIENT_KEY_SOURCE=$work/private/client-key
POS_RUNTIME_DIR=$work/private/runtime
POS_GATEWAY_NETWORK=$network
POS_COMPOSE_PROJECT=$project
POS_DEPLOY_WAIT_SECONDS=15
ENV
checks=0
ok() { printf 'PASS: %s\n' "$1"; checks=$((checks+1)); }
reject() {
    local env_file=$1
    if bash "$script" "$env_file" --check >"$work/check.log" 2>&1; then echo 'FAIL: unsafe production env accepted' >&2; exit 1; fi
    if grep -Fq -- "$key" "$work/check.log" || grep -Fq -- "$cookie" "$work/check.log"; then echo 'FAIL: secret in release output' >&2; exit 1; fi
    ok 'unsafe production input refused without secret output'
}
base="$work/private/pos.env"
reject "$work/private/missing.env"
cp "$base" "$work/private/wide.env"; chmod 644 "$work/private/wide.env"; reject "$work/private/wide.env"
cp "$base" "$work/private/foreign.env"
foreign=33; [[ $(id -u) != 33 ]] || foreign=0
docker run --rm --network none --read-only -v "$work/private/foreign.env:/fixture" --entrypoint chown php:8.3.36-apache "$foreign" /fixture
reject "$work/private/foreign.env"
ln -s "$base" "$work/private/link.env"; reject "$work/private/link.env"
ln -s "$work/private" "$work/linked-parent"; reject "$work/linked-parent/pos.env"
cp "$base" "$source_dir/v2/inside.env"; reject "$source_dir/v2/inside.env"; rm "$source_dir/v2/inside.env"
for replacement in 'POS_MODE=preview' 'NAPAY_API_BASE_URL=http://napay.production.invalid/api/pos/v1' 'NAPAY_API_BASE_URL=https://user:pass@napay.production.invalid/api/pos/v1' 'POS_TRUSTED_PROXIES=0.0.0.0/0' 'POS_TRUSTED_PROXIES=999.0.0.0/24' 'POS_GATEWAY_NETWORK=bad/name' 'POS_COMPOSE_PROJECT=wallet' 'POS_DEPLOY_WAIT_SECONDS=0' 'COOKIE_VALIDATION_KEY=short'; do
    name=${replacement%%=*}
    grep -v "^$name=" "$base" > "$work/private/bad.env"; printf '%s\n' "$replacement" >> "$work/private/bad.env"; reject "$work/private/bad.env"
done
grep -v '^COOKIE_VALIDATION_KEY=' "$base" > "$work/private/bad.env"; reject "$work/private/bad.env"
cp "$base" "$work/private/bad.env"; printf 'POS_MODE=production\n' >> "$work/private/bad.env"; reject "$work/private/bad.env"
cp "$base" "$work/private/bad.env"; printf 'POS_LABEL=$(touch /tmp/forbidden)\n' >> "$work/private/bad.env"; reject "$work/private/bad.env"
cp "$base" "$work/private/bad.env"; printf 'POS_RELEASE_IMAGE=untrusted:dev\n' >> "$work/private/bad.env"; reject "$work/private/bad.env"
ln -s "$work/private/client-key" "$work/private/key-link"
sed "s@^NAPAY_CLIENT_KEY_SOURCE=.*@NAPAY_CLIENT_KEY_SOURCE=$work/private/key-link@" "$base" > "$work/private/bad.env"; reject "$work/private/bad.env"
printf 'short\n' > "$work/private/short-key"
sed "s@^NAPAY_CLIENT_KEY_SOURCE=.*@NAPAY_CLIENT_KEY_SOURCE=$work/private/short-key@" "$base" > "$work/private/bad.env"; reject "$work/private/bad.env"
chmod 755 "$work/private/runtime"; reject "$base"; chmod 700 "$work/private/runtime"
# A real compose check must not create a container, image or release-state directory.
bash "$script" "$base" --check > "$work/check.log" 2>&1
[[ -z $(docker ps -aq --filter "label=com.docker.compose.project=$project") && ! -e "$work/private/.pos-release-$project" ]]
ok 'valid --check starts no service and writes no release state'
# Additional CA fixture, locally generated; no Internet or public CA service.
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj /CN=POS-Fixture-CA -keyout "$work/ca.key" -out "$work/private/ca.crt" >/dev/null 2>&1
openssl req -newkey rsa:2048 -nodes -subj /CN=napay.production.invalid -keyout "$work/server.key" -out "$work/server.csr" >/dev/null 2>&1
printf 'subjectAltName=DNS:napay.production.invalid\nbasicConstraints=CA:FALSE\nkeyUsage=digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\n' > "$work/server.ext"
openssl x509 -req -in "$work/server.csr" -CA "$work/private/ca.crt" -CAkey "$work/ca.key" -CAcreateserial -days 1 -extfile "$work/server.ext" -out "$work/server.crt" >/dev/null 2>&1
cp "$base" "$work/private/ca.env"; printf 'NAPAY_CA_SOURCE=%s/private/ca.crt\n' "$work" >> "$work/private/ca.env"
bash "$script" "$work/private/ca.env" --check > "$work/check.log" 2>&1; ok 'optional additional CA accepted by --check'
printf 'not a cert\n' > "$work/private/bad.crt"
sed "s@^NAPAY_CA_SOURCE=.*@NAPAY_CA_SOURCE=$work/private/bad.crt@" "$work/private/ca.env" > "$work/private/bad.env"; reject "$work/private/bad.env"
docker network create --internal "$network" >/dev/null
cat > "$work/Caddyfile" <<'CADDY'
{
 admin off
 auto_https disable_redirects
}
https://napay.production.invalid {
 tls /fixture/server.crt /fixture/server.key
 header Content-Type application/json
 respond `{"error":{"code":"invalid_credentials"}}` 401
}
CADDY
docker run -d --name "$backend" --network "$network" --network-alias napay.production.invalid --read-only --tmpfs /data --tmpfs /config \
    -v "$work/Caddyfile:/etc/caddy/Caddyfile:ro" -v "$work/server.crt:/fixture/server.crt:ro" -v "$work/server.key:/fixture/server.key:ro" caddy:2.8.4-alpine >/dev/null
export POS_BUILD_NETWORK=none
old_commit=$(git -C "$source_dir" rev-parse HEAD)
old_image="napay/pos-v2:git-$old_commit"; images+=("$old_image")
bash "$script" "$work/private/ca.env" > "$work/deploy.log" 2>&1
container=$(docker ps -q --filter "label=com.docker.compose.project=$project" --filter label=com.docker.compose.service=web)
[[ $(docker inspect -f '{{.State.Health.Status}}' "$container") == healthy ]]
old_id=$(docker inspect -f '{{.Image}}' "$container")
[[ $(docker inspect -f '{{.HostConfig.ReadonlyRootfs}}' "$container") == true && $(docker inspect -f '{{json .HostConfig.PortBindings}}' "$container") == '{}' ]]
docker exec "$container" sh -c 'test ! -e /srv/pos/tests && test -f /run/pos-secrets/client-key'
ok 'production image healthy, no tests, no host ports, read-only root'
[[ $(docker exec "$container" curl -s http://127.0.0.1/health) == '{"status":"ok","mode":"production","payments":"tts-btc"}' ]]
[[ $(docker exec "$container" curl -s -o /dev/null -w '%{http_code}' 'http://127.0.0.1/payment/request?id=fixture') == 404 ]]
! docker exec "$container" curl -s http://127.0.0.1/site/login | grep -q 'Solo chain di test'
ok 'real HTTP health reports production and test request is404'
docker exec -i --user 33:33 -e POS_TLS_TEST=isolated-fixture -e POS_TLS_EXPECT=invalid_credentials "$container" php < "$root/tests/production-tls.php"
ok 'additional CA verifies actual TLS backend from production container'
# No CAINFO: a disposable probe uses its system CA bundle, then the original untrusted bundle.
docker run --rm -i --network "$network" --read-only --tmpfs /tmp -e POS_MODE=production -e POS_TLS_TEST=isolated-fixture -e POS_TLS_EXPECT=invalid_credentials \
    -e NAPAY_API_BASE_URL=https://napay.production.invalid/api/pos/v1 -e NAPAY_POS_CLIENT_KEY_FILE=/fixture/client-key \
    -v "$work/private/client-key:/fixture/client-key:ro" -v "$work/private/ca.crt:/etc/ssl/certs/ca-certificates.crt:ro" --entrypoint php "$old_image" < "$root/tests/production-tls.php"
docker run --rm -i --network "$network" --read-only --tmpfs /tmp -e POS_MODE=production -e POS_TLS_TEST=isolated-fixture -e POS_TLS_EXPECT=backend_unavailable \
    -e NAPAY_API_BASE_URL=https://napay.production.invalid/api/pos/v1 -e NAPAY_POS_CLIENT_KEY_FILE=/fixture/client-key \
    -v "$work/private/client-key:/fixture/client-key:ro" --entrypoint php "$old_image" < "$root/tests/production-tls.php"
ok 'system CA path succeeds only with a trusted certificate; untrusted TLS refused'
# A second commit whose app cannot boot. The deployment must restore the first image, preserving runtime.
printf '<?php throw new RuntimeException("deliberate fixture failure");\n' > "$source_dir/v2/config/web.php"
git -C "$source_dir" add v2/config/web.php
git -C "$source_dir" -c user.name=Fixture -c user.email=fixture@example.invalid commit -qm 'failing production fixture'
new_commit=$(git -C "$source_dir" rev-parse HEAD); images+=("napay/pos-v2:git-$new_commit")
# Put a marker into the mounted runtime through the running image (no backend request/session).
docker exec --user 33:33 "$container" sh -c 'printf keep > /srv/pos/runtime/survivor'
if bash "$script" "$work/private/ca.env" > "$work/failed.log" 2>&1; then echo 'FAIL: unhealthy production release reported success' >&2; exit 1; fi
container=$(docker ps -q --filter "label=com.docker.compose.project=$project" --filter label=com.docker.compose.service=web)
[[ $(docker inspect -f '{{.Image}}' "$container") == "$old_id" && $(docker inspect -f '{{.State.Health.Status}}' "$container") == healthy ]]
[[ $(docker exec --user 33:33 "$container" cat /srv/pos/runtime/survivor) == keep ]]
! grep -Fq -- "$key" "$work/failed.log"; ! grep -Fq -- "$cookie" "$work/failed.log"
ok 'failed new image restores exact prior image healthy, runtime kept, output sanitized'
printf 'PASS %s production deploy checks\n' "$checks"
