#!/usr/bin/env bash
# No network or shared runtime. Overlay source on the cached POS image, leaving its locked vendor intact.
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
args=(docker run --rm --network none --read-only --tmpfs /tmp --tmpfs /srv/pos/runtime --cap-drop ALL --security-opt no-new-privileges:true
    -e POS_MODE=preview -e COOKIE_VALIDATION_KEY=fixture-cookie-validation-key-at-least-32-characters)
for dir in components config controllers models views messages web fonts tests; do args+=(-v "$root/$dir:/srv/pos/$dir:ro"); done
"${args[@]}" --entrypoint sh "${POS_TEST_IMAGE:-napay/pos-v2:dev}" -c '
set -e
for file in config amount login-client logout-client catalog-client opening-client payment-client pwa production; do php "/srv/pos/tests/$file.php"; done
'
if command -v node >/dev/null; then
    node "$root/tests/api-contract.cjs"
    node "$root/tests/local-state.test.cjs"
else
    node_image=${POS_NODE_TEST_IMAGE:-napay/chain-tools:dev}
    docker image inspect "$node_image" >/dev/null
    for file in api-contract.cjs local-state.test.cjs; do
        docker run --rm --network none --read-only --tmpfs /tmp -v "$root:/fixture/pos/v2:ro" --entrypoint node "$node_image" "/fixture/pos/v2/tests/$file"
    done
fi
