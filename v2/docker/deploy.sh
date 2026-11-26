#!/usr/bin/env bash
# SIP-036: dedicated production POS, no host ports or database; rollback changes only the image.
set -euo pipefail
umask 077
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd -P)
fail() { printf 'POS release failed: %s\n' "$1" >&2; exit 1; }
[[ $# -ge 1 && $# -le 2 && ( ${2:-} == '' || ${2:-} == --check ) ]] || fail 'usage: deploy.sh env-file [--check]'
for tool in git realpath stat docker flock; do command -v "$tool" >/dev/null || fail 'required release tool missing'; done
repo=$(git -C "$root" rev-parse --show-toplevel) || fail 'Git checkout required'
common=$(git -C "$root" rev-parse --path-format=absolute --git-common-dir)
main_repo=$(dirname -- "$common")
platform=$(git -C "$main_repo/.." rev-parse --show-toplevel 2>/dev/null || true)
outside() {
    local path=$1 boundary
    for boundary in "$repo" "$main_repo" "$platform"; do
        [[ -z $boundary || ( $path != "$boundary" && $path != "$boundary/"* ) ]] || fail 'private paths must be outside application repositories'
    done
}
physical() {
    local input=$1 lexical resolved
    lexical=$(realpath -ms -- "$input") || fail 'invalid private path'
    resolved=$(realpath -e -- "$input" 2>/dev/null) || fail 'private path does not exist'
    [[ $lexical == "$resolved" ]] || fail 'symlinks are not permitted in private paths'
    outside "$resolved"
    printf '%s' "$resolved"
}
private_dir() {
    [[ -d $1 && $(stat -c '%u' "$1") == "$(id -u)" && $(stat -c '%a' "$1") == 700 ]] || fail 'private directory must be owned by the release user with mode 0700'
}
private_file() {
    [[ -f $1 && $(stat -c '%u' "$1") == "$(id -u)" && $(stat -c '%a' "$1") == 600 ]] || fail 'private file must be owned by the release user with mode 0600'
    private_dir "$(dirname -- "$1")"
}
ENV_FILE=$(physical "$1")
private_file "$ENV_FILE"
[[ $(stat -c '%s' "$ENV_FILE") -le 16384 ]] || fail 'env file too large'
LC_ALL=C grep -aq '[[:cntrl:]]' "$ENV_FILE" && fail 'control characters are not allowed in env files'
declare -A values=()
while IFS= read -r line || [[ -n $line ]]; do
    [[ -z $line || $line == \#* ]] && continue
    [[ $line =~ ^([A-Z][A-Z0-9_]*)=(.*)$ ]] || fail 'env requires plain KEY=value lines'
    name=${BASH_REMATCH[1]} value=${BASH_REMATCH[2]}
    case "$name" in POS_MODE|COOKIE_VALIDATION_KEY|NAPAY_API_BASE_URL|POS_TRUSTED_PROXIES|POS_LABEL|NAPAY_CLIENT_KEY_SOURCE|NAPAY_CA_SOURCE|POS_RUNTIME_DIR|POS_GATEWAY_NETWORK|POS_COMPOSE_PROJECT|POS_DEPLOY_WAIT_SECONDS) ;; *) fail 'unknown production env setting' ;; esac
    [[ ! -v values[$name] ]] || fail 'duplicate env setting'
    [[ $value != *'$'* && $value != *'`'* && $value != *'"'* && $value != *"'"* && $value != *'\'* && $value != *'#'* ]] || fail 'quoted or interpolated env values are not supported'
    values[$name]=$value
done < "$ENV_FILE"
for name in POS_MODE COOKIE_VALIDATION_KEY NAPAY_API_BASE_URL POS_TRUSTED_PROXIES NAPAY_CLIENT_KEY_SOURCE POS_RUNTIME_DIR POS_GATEWAY_NETWORK; do
    [[ -n ${values[$name]:-} ]] || fail 'required production env setting missing'
done
[[ ${values[POS_MODE]} == production ]] || fail 'production mode required'
[[ ${values[COOKIE_VALIDATION_KEY]} =~ ^[A-Za-z0-9_-]{32,256}$ ]] || fail 'invalid cookie validation key'
api=${values[NAPAY_API_BASE_URL]}
[[ $api =~ ^https://([A-Za-z0-9.-]+)(:([1-9][0-9]{0,4}))?/api/pos/v1$ && ${#api} -le 512 ]] || fail 'backend URL must be HTTPS with the exact POS API path'
host=${BASH_REMATCH[1]} port=${BASH_REMATCH[3]:-443}
[[ ${#host} -le 253 && $port -le 65535 ]] || fail 'invalid backend origin'
IFS=. read -ra labels <<< "$host"
[[ $host != .* && $host != *. && $host != *..* ]] || fail 'invalid backend hostname'
for label in "${labels[@]}"; do [[ $label =~ ^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?$ ]] || fail 'invalid backend hostname'; done
proxies=${values[POS_TRUSTED_PROXIES]}
[[ ${#proxies} -le 1024 && $proxies != ,* && $proxies != *, && $proxies != *,,* ]] || fail 'invalid trusted proxies'
IFS=, read -ra networks <<< "$proxies"
[[ ${#networks[@]} -le 16 ]] || fail 'too many trusted proxies'
for cidr in "${networks[@]}"; do
    [[ $cidr =~ ^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})/([1-9]|[12][0-9]|3[0-2])$ ]] || fail 'trusted proxies must be bounded IPv4 CIDRs'
    for i in 1 2 3 4; do octet=${BASH_REMATCH[$i]}; [[ ( ${#octet} == 1 || $octet != 0* ) && $((10#$octet)) -le 255 ]] || fail 'invalid trusted proxy address'; done
done
[[ ${values[POS_GATEWAY_NETWORK]} =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$ ]] || fail 'invalid gateway network'
project=${values[POS_COMPOSE_PROJECT]:-pos-v2-production}
[[ $project =~ ^pos-[a-z0-9][a-z0-9-]{0,47}$ ]] || fail 'invalid production project name'
wait_seconds=${values[POS_DEPLOY_WAIT_SECONDS]:-180}
[[ $wait_seconds =~ ^[1-9][0-9]{1,2}$ && $wait_seconds -le 600 ]] || fail 'health wait must be between 10 and 600 seconds'
label=${values[POS_LABEL]:-Napay POS}
[[ ${#label} -le 128 ]] || fail 'POS label too long'
client=$(physical "${values[NAPAY_CLIENT_KEY_SOURCE]}")
private_file "$client"
[[ $(stat -c '%s' "$client") -le 64 ]] || fail 'invalid client key file'
client_key=$(cat -- "$client")
[[ $client_key =~ ^[A-Za-z0-9_-]{43}$ ]] || fail 'invalid client key format'
unset client_key
runtime=$(physical "${values[POS_RUNTIME_DIR]}")
[[ -d $runtime && $(stat -c '%a' "$runtime") == 700 ]] || fail 'runtime must be an existing private directory'
owner=$(stat -c '%u' "$runtime")
[[ $owner == "$(id -u)" || $owner == 33 ]] || fail 'invalid runtime owner'
private_dir "$(dirname -- "$runtime")"
[[ $ENV_FILE != "$runtime/"* && $client != "$runtime/"* ]] || fail 'runtime must not contain release credentials'
ca=''
if [[ -n ${values[NAPAY_CA_SOURCE]:-} ]]; then
    ca=$(physical "${values[NAPAY_CA_SOURCE]}")
    private_file "$ca"
    [[ $ca != "$runtime/"* ]] || fail 'runtime must not contain certificates'
    command -v openssl >/dev/null || fail 'OpenSSL is required for an additional CA'
    openssl x509 -in "$ca" -noout >/dev/null 2>&1 || fail 'invalid additional CA certificate'
fi
for name in "${!values[@]}"; do export "$name=${values[$name]}"; done
export POS_ENV_FILE="$ENV_FILE" POS_RUNTIME_DIR="$runtime" NAPAY_CLIENT_KEY_SOURCE="$client" NAPAY_CA_SOURCE="$ca"
revision=$(git -C "$root" rev-parse HEAD)
export POS_RELEASE_IMAGE="napay/pos-v2:git-$revision"
compose=(docker compose --project-directory "$root" --env-file "$ENV_FILE" -p "$project" -f "$root/docker/compose.production.yml")
[[ -z $ca ]] || compose+=(-f "$root/docker/compose.production-ca.yml")
"${compose[@]}" config --quiet >/dev/null 2>&1 || fail 'invalid production compose configuration'
if [[ ${2:-} == --check ]]; then
    printf 'POS production check OK; no image built or service started.\n'
    exit 0
fi
# A release tag must describe exactly the tracked application, not an edited worktree.
git -C "$repo" diff --quiet HEAD -- "$root" || fail 'commit application changes before releasing'
[[ -z $(git -C "$repo" ls-files --others --exclude-standard -- "$root") ]] || fail 'untracked application files must be committed before releasing'
[[ ${POS_BUILD_NETWORK:-default} == default || ${POS_BUILD_NETWORK:-default} == none ]] || fail 'invalid build network'
state="$(dirname -- "$runtime")/.pos-release-$project"
if [[ ! -e $state && ! -L $state ]]; then mkdir -m 700 -- "$state"; fi
[[ $(physical "$state") == "$state" ]] || fail 'invalid release state path'
private_dir "$state"
for file in lock build.log release.log rollback.log previous-image; do
    [[ ! -L $state/$file ]] || fail 'symlinked release state refused'
    if [[ -e $state/$file ]]; then private_file "$state/$file"; fi
done
exec 9>"$state/lock"
flock -n 9 || fail 'another POS release is running'
previous=''
container=$("${compose[@]}" ps -q web)
if [[ -n $container ]]; then
    [[ $container != *$'\n'* ]] || fail 'multiple POS containers are not supported'
    previous=$(docker inspect --format '{{.Image}}' "$container")
fi
replacing=0
restore() {
    if [[ -n $previous ]]; then
        if POS_RELEASE_IMAGE="$previous" "${compose[@]}" up -d --no-build --pull never --wait --wait-timeout "$wait_seconds" web >"$state/rollback.log" 2>&1; then
            printf 'Previous POS image restored and healthy.\n' >&2
        else
            printf 'CRITICAL: previous POS image could not be restored; operator intervention required.\n' >&2
        fi
    else
        "${compose[@]}" rm -sf web >"$state/rollback.log" 2>&1 || true
        printf 'No previous POS image; failed first release stopped.\n' >&2
    fi
}
on_exit() {
    code=$?
    trap - EXIT
    if (( code != 0 && replacing == 1 )); then restore; fi
    exit "$code"
}
trap on_exit EXIT
trap 'exit 130' HUP INT TERM
docker build --pull=false --network "${POS_BUILD_NETWORK:-default}" --target production --label "org.opencontainers.image.revision=$revision" \
    -f "$root/docker/Dockerfile" -t "$POS_RELEASE_IMAGE" "$root" >"$state/build.log" 2>&1 || fail 'image build failed; running POS unchanged'
replacing=1
"${compose[@]}" up -d --no-build --pull never --wait --wait-timeout "$wait_seconds" web >"$state/release.log" 2>&1 || fail 'new POS did not become healthy'
replacing=0
printf '%s\n' "$previous" > "$state/previous-image"
printf 'POS production release healthy: %s\n' "$revision"
