# POS V2 - Production Release

SIP-036 part B. This prepares a production POS; it does not authorize a VPS
deployment or certify a payment backend. Gateway and Napay remain managed by
the orchestrator. Never use `docker/local.sh` to deploy production.

## Private Configuration

Use `docker/production.env.example` as the list of settings. Keep the actual
env file and client key outside application repositories, owned by the release
user, mode0600, in directories mode0700. No symlink in any path component.
Only plain KEY=value is supported: no quotes, expansion, duplicate/unknown
settings or inline comments. Do not source the env file in a shell.

`POS_MODE=production` is required. `NAPAY_API_BASE_URL` must use HTTPS with
the exact path `/api/pos/v1`, no credentials/query/fragment. `POS_TRUSTED_PROXIES`
is a comma-separated list of at most16 gateway IPv4 CIDRs, prefix1..32;
the gateway must actually be on that subnet. Never trust every address.

`NAPAY_CLIENT_KEY_SOURCE` points to the43-character client key registered in
Napay; it becomes a Docker secret, copied only into private tmpfs for Apache.
No backend bearer token goes into the browser. `NAPAY_CA_SOURCE` is optional;
without it PHP/cURL uses the image's system CA store. With it, a valid PEM
certificate is mounted as an extra Docker secret. TLS verification is never
disabled. Preview retains its local backend/CA defaults.

Create `POS_RUNTIME_DIR` once as an empty private directory, under a private
directory owned by the release user. Apache will own the runtime (uid33);
both the initial release-user ownership and uid33 are accepted on later
releases. Do not place configuration/secrets in this directory. Session and
runtime data survive container replacement. Back them up separately.

## Check And Release

```sh
bash v2/docker/deploy.sh /absolute/private/pos.env --check
bash v2/docker/deploy.sh /absolute/private/pos.env
```

`--check` validates paths, permissions, env, key, optional CA and compose.
It does not build, create directories, inspect networks or start containers.
A valid check does not prove that Napay is reachable or that operators have
grants. The production gateway network must already exist for the release.

A release requires a clean, committed `v2/` checkout. The image is built
from `docker/Dockerfile`, target `production`, tagged `napay/pos-v2:git-<commit>`;
tests are absent from this target. There are no published host ports, no
source bind mounts, no DB, and the root filesystem is read-only. The external
gateway network is `POS_GATEWAY_NETWORK`; the service alias is `pos-v2-web`.
`POS_COMPOSE_PROJECT` defaults to `pos-v2-production` and must start with `pos-`.
Use a stable project and runtime path on every release. Never use a sandbox
project or gateway network for these production commands.

Private `.pos-release-<project>` state/logs live next to runtime. Deployments
are serialized by a lock there. Output contains fixed messages and commit
IDs, never env values or log contents. `POS_DEPLOY_WAIT_SECONDS` is10..600,
default180. `POS_BUILD_NETWORK=none` can be exported for offline cached builds;
otherwise build uses Docker's default build network. No image or tool is
automatically pulled by tests; production builds need cached/downloadable
locked dependencies as usual. No PHP or dependency version changed here.

If replacement fails its healthcheck, the script restarts the previous
container's exact image ID and waits for its healthcheck. On a failed first
installation it removes only the failed service. A build failure leaves the
running service unchanged. A failed rollback is reported as CRITICAL, never
as success. Rollback restores the image, NOT edited env, rotated keys, gateway,
Napay, session contents or databases. Keep configuration compatible or restore
it explicitly; no automatic credential rollback.

## Gateway Handoff

Connect only the gateway and POS to the production network. Route the actual
POS domain to `pos-v2-web:80` with HTTPS and automatic public certificates.
Napay must resolve from that network at the configured HTTPS origin and allow
the POS subnet for its POS API. Those changes belong to the master; this
release script never creates or restarts the shared gateway or Napay.

`/health` exposes only status/mode/payment-family labels. It checks app boot,
not backend grants or a financial transaction. Production has no test-chain
copy button and `payment/request` returns404. Test on the actual target with
an authorized operator before enabling real collections.
