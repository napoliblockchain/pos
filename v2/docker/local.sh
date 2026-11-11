#!/usr/bin/env bash
set -euo pipefail
umask 077
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
state="$HOME/.local/share/napay/pos-v2-local"
action=${1:-status}
[[ $# -le 1 && $action =~ ^(up|status|stop)$ ]] || { echo 'Usage: bash docker/local.sh {up|status|stop}' >&2; exit 1; }
[[ ! -L $state ]] || exit 1
mkdir -p "$state"
[[ $(stat -c '%u' "$state") == "$(id -u)" ]] || exit 1
chmod 700 "$state"
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
compose=(docker compose -p pos-v2-local --project-directory "$root" -f "$root/docker-compose.yml")
case "$action" in
    up)
        [[ $(docker inspect napay-gateway --format '{{.State.Running}}') == true ]] || { echo 'Start the shared gateway first.' >&2; exit 1; }
        docker network inspect napay_local_browser >/dev/null
        "${compose[@]}" config --quiet
        "${compose[@]}" up -d --build --wait --wait-timeout 180
        echo 'POS preview: https://pos.localhost (shared gateway must load conf.d/pos.caddy).'
        ;;
    status) "${compose[@]}" ps ;;
    stop) "${compose[@]}" stop ;;
esac
