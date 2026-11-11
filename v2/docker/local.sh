#!/usr/bin/env bash
set -euo pipefail
umask 077
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
project=$(CDPATH= cd -- "$root/../.." && pwd)
# Read only host path settings; never source/evaluate the private orchestrator env.
setting() {
    local name=$1
    local value=${!name:-}
    if [[ -z $value && -f $project/.env ]]; then
        value=$(sed -n "s/^$name=//p" "$project/.env" | tail -n 1 | tr -d "\"'")
    fi
    printf '%s' "$value"
}
private_directory() {
    local path=$1 mode
    [[ $path == /* && $path != *$'\n'* && ! -L $path ]] || { echo 'Private paths must be absolute and not symlinked.' >&2; exit 1; }
    path=$(realpath -m -- "$path")
    [[ $path != "$project" && $path != "$project/"* ]] || { echo 'Private paths must stay outside application repositories.' >&2; exit 1; }
    mkdir -p "$path"
    [[ $(stat -c '%u' "$path") == "$(id -u)" ]] || exit 1
    mode=$(stat -c '%a' "$path")
    (( (8#$mode & 077) == 0 )) || { echo 'Private directory requires owner-only permissions.' >&2; exit 1; }
}
state=$(setting POS_LOCAL_STATE)
backend_state=$(setting NAPAY_LOCAL_STATE)
data=$(setting POS_LOCAL_DATA)
[[ -n $state && -n $backend_state && -n $data ]] || { echo 'Set POS_LOCAL_STATE, NAPAY_LOCAL_STATE and POS_LOCAL_DATA in the orchestrator env or process.' >&2; exit 1; }
private_directory "$state"
private_directory "$backend_state"
private_directory "$data"
action=${1:-status}
[[ $# -le 1 && $action =~ ^(up|status|stop)$ ]] || { echo 'Usage: bash docker/local.sh {up|status|stop}' >&2; exit 1; }
[[ ! -L $state ]] || exit 1
mkdir -p "$state"
[[ $(stat -c '%u' "$state") == "$(id -u)" ]] || exit 1
chmod 700 "$state"
for name in control.lock .gitignore; do
    [[ ! -L $state/$name ]] || exit 1
    if [[ -e $state/$name ]]; then
        [[ -f $state/$name && $(stat -c '%u' "$state/$name") == "$(id -u)" ]] || exit 1
        mode=$(stat -c '%a' "$state/$name")
        (( (8#$mode & 077) == 0 )) || exit 1
    fi
done
if [[ ! -e $state/.gitignore ]]; then printf '*\n!.gitignore\n' > "$state/.gitignore"; fi
exec 9>"$state/control.lock"
flock -n 9 || { echo 'Another POS operation is in progress.' >&2; exit 1; }
if [[ $action == up && ! -e $state/local.env ]]; then
    printf 'COOKIE_VALIDATION_KEY=%s\nPOS_MODE=preview\nPOS_LABEL=Napay POS\n' "$(openssl rand -hex 32)" > "$state/local.env"
fi
[[ -f $state/local.env && ! -L $state/local.env ]] || { echo 'Run up first.' >&2; exit 1; }
[[ $(stat -c '%u' "$state/local.env") == "$(id -u)" ]] || exit 1
mode=$(stat -c '%a' "$state/local.env")
(( (8#$mode & 077) == 0 )) || { echo 'Private env requires owner-only permissions.' >&2; exit 1; }
export POS_ENV_FILE="$state/local.env"
export NAPAY_CLIENT_KEY_SOURCE="${NAPAY_CLIENT_KEY_SOURCE:-$backend_state/pos-client-key}"
export NAPAY_CA_SOURCE="${NAPAY_CA_SOURCE:-$backend_state/caddy-root.crt}"
export POS_RUNTIME_DIR="$data/runtime"
compose=(docker compose -p pos-v2-local --project-directory "$root" -f "$root/docker-compose.yml")
case "$action" in
    up)
        [[ $(docker inspect napay-gateway --format '{{.State.Running}}') == true ]] || { echo 'Start the shared gateway first.' >&2; exit 1; }
        docker network inspect pos_local_browser >/dev/null
        [[ -f $NAPAY_CLIENT_KEY_SOURCE && ! -L $NAPAY_CLIENT_KEY_SOURCE && -f $NAPAY_CA_SOURCE && ! -L $NAPAY_CA_SOURCE ]] || exit 1
        secret_mode=$(stat -c '%a' "$NAPAY_CLIENT_KEY_SOURCE")
        (( (8#$secret_mode & 077) == 0 )) || { echo 'Client key requires owner-only permissions.' >&2; exit 1; }
        [[ $(stat -c '%u' "$NAPAY_CLIENT_KEY_SOURCE") == "$(id -u)" && ! -L $POS_RUNTIME_DIR ]] || exit 1
        if [[ ! -e $POS_RUNTIME_DIR ]]; then mkdir -m 700 "$POS_RUNTIME_DIR"; fi
        "${compose[@]}" config --quiet
        "${compose[@]}" up -d --build --wait --wait-timeout 180
        echo 'POS preview: https://pos.localhost (shared gateway must load conf.d/pos.caddy).'
        ;;
    status) "${compose[@]}" ps ;;
    stop) "${compose[@]}" stop ;;
esac
