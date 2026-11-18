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

## Prova con ruoli DB separati

```sh
NAPAY_E2E_DB_ROLES=restricted NAPAY_SOURCE=/percorso/napay-integrato \
  NAPAY_TEST_DATASET=/percorso/dataset-v2 sh tests/docker/openings-e2e.sh
```

Richiede anche overlay ruoli/bootstrap B1 nel checkout Napay. Genera secret
migrator/worker privati per esecuzione e applica davvero l'overlay prima
del bootstrap. Verifica readiness web/worker, identita web e divieto di DDL,
DELETE dello storico aperture e lettura credenziali BTCPay; poi esegue gli
stessi 25 casi HTTP/TLS. Nessun privilegio extra assegnato al web per fixture.
Modalita predefinita `legacy` preservata; valori diversi sono rifiutati.
Entrambe le modalita restano isolate dalla sandbox condivisa.

Limiti: nessun deploy o pagamento. Il container POS usa il proprio entrypoint come root per
copiare i secret a `www-data` (come lo stack locale); gateway e driver girano
con l'uid dell'host, capability rimosse (al gateway resta solo il bind di 443).
Nessun pagamento.

## Pagamenti TTS (SIP-021 fase 3)

`NAPAY_E2E_SUITE=payments` aggiunge alla stessa rete interna una chain Besu
usa-e-getta (`napay/chain-tools:dev`, `hyperledger/besu:26.9.0`), configura il
backend con `TTS_*` e imposta il wallet del merchant nella fixture. Il driver
incassa dal tastierino, legge da Napay il riferimento della richiesta, il runner
esegue un transfer reale con il pagatore di test di Napay (`tests/application/tts-pay.mjs`),
poi il driver verifica polling, pagina pagata, ricevuta PDF, annullamento e
assenza di segreti nel browser e nei log.

```sh
NAPAY_E2E_SUITE=payments NAPAY_E2E_DB_ROLES=restricted NAPAY_SOURCE=/percorso/napay-integrato \
NAPAY_TEST_DATASET=/percorso/dataset-v2 sh tests/docker/openings-e2e.sh
```
