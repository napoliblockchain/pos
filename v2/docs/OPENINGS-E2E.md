# E2E aperture POS <-> Napay (SIP-021 fase 2)

`tests/docker/openings-e2e.sh` prova il percorso reale browser -> gateway TLS ->
POS v2 -> client POS -> gateway TLS -> API Napay, senza trasporto simulato.

```sh
NAPAY_SOURCE=/percorso/napay NAPAY_TEST_DATASET=/percorso/dataset-v2 sh tests/docker/openings-e2e.sh
```

- `NAPAY_SOURCE`: checkout Napay con il backend aperture, montato in sola
  lettura; lo stack Napay (MySQL, bootstrap, web, fixture base) è quello di
  `tests/docker/runtime.sh` di quel checkout, fermato prima delle sue suite.
- Stack interamente usa-e-getta (progetto casuale): rete interna dedicata,
  nessuna porta host, gateway Caddy proprio con CA privata generata per
  l'esecuzione; cartelle 0700, file 0600, password e chiave client casuali,
  mai negli argomenti dei processi; `compose down -v` a fine esecuzione.
- Driver PHP/cURL (niente browser automation): due cookie jar = due
  dispositivi. Casi: login reale, catalogo, apertura e cassa, heartbeat,
  seconda apertura bloccata, chiusura e riapertura, logout che libera,
  sblocco forzato dal backoffice Napay con vecchia apertura respinta, POS con
  CA estranea o assente senza accesso, token Napay assente da HTML, header,
  URL, cookie del browser e log.

Limiti: verifica il trasporto end-to-end, non i ruoli DB separati né il
deploy (master). Il container POS usa il proprio entrypoint come root per
copiare i secret a `www-data` (come lo stack locale); gateway e driver girano
con l'uid dell'host, capability rimosse (al gateway resta solo il bind di 443).
Nessun pagamento.
