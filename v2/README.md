# Napay POS v2 - primo avvio locale

Yii2, PHP 8.3.36, container indipendente, gateway Caddy condiviso.
URL: **https://pos.localhost**. Chrome risolve `.localhost` su loopback;
per curl puo essere necessario `--resolve pos.localhost:443:127.0.0.1`.
Stessa CA di `napay.local` e `wallet.local`, nessuna nuova porta host.

## Stato reale

Prima dell'implementazione: [SIP-021](../../tips/SIP-021-pos-login-operatori-apertura-esclusiva.md).
Le nuove fasi richiedono SIP documentato e approvato; nessuna implementazione
deriva automaticamente dalla bozza OpenAPI.
Flusso login approvato: email/password nel POS, verifica API Napay e token
soltanto sul server POS. Dettagli API ancora da concordare; login non attivo.

Questa e la **base navigabile di migrazione**, non un POS pronto a incassare.
Tastierino, validazione importi lato server, layout mobile/desktop e protezioni
HTTP attivi. Il pulsante verifica solo l'importo; non crea fatture, QR,
transazioni, saldo o ricevute. Importi EUR in centesimi interi, intervallo della
sola anteprima 0,01-99.999,99: non definisce limiti di pagamento del backend.

**Non implementati:** autenticazione/pairing terminale, API Napay, incassi
BTC/TTS, polling stato, storico, ricevute e installazione/offline PWA.

Decisioni concordate il 2026-11-11: login utente base, POS autorizzati per piu
negozi, apertura esclusiva e sblocco admin/merchant; wallet unico per merchant.
Il pairing tramite SIN/codici non e il flusso scelto.
Vedere [contratto funzionale per l'agente Napay](docs/NAPAY-CONTRACT.md).
Le decisioni documentate non rendono queste funzioni gia disponibili.
Disponibile [bozza OpenAPI](docs/pos-api.openapi.json) per l'agente backend:
7 operazioni proposte, non implementate e non ancora approvate. Login allineato
alla decisione Napay email-only (`a4f4ffe3`). Controlli strutturali ripetibili:
`node tests/api-contract.cjs`, senza test o chiamate al backend operativo.
Non e presente una modalita live: `POS_MODE` diverso da `preview` blocca l'avvio.
Non usare in produzione e non inserire credenziali reali del vecchio POS.

Il codice Yii1 resta intatto, ma non viene montato o caricato dal container v2.
Nessun runtime da `yii-1.1.22`, `packages` o vendor di Napay/poawallet.
Il repo mantiene branch `feature/v2-yii2-php83-docker`.

## Revisione integrazione esistente

L'altro agente ha trasferito Napay al Caddy condiviso: commit Napay `6e7bbead`,
root orchestratore `6c3ab42`. Preservati compose e script Napay/wallet.
POS risultava ancora Yii1 (ultimo commit applicativo `b82071c`, 2021), senza
implementazione v2. TIP-020 assegna migrazione associazione/legacy al backoffice.
Questa fase non crea tabelle POS nel database gestito dall'altro agente.

Rilievo del 2026-11-11: `.env` dell'orchestratore ha permessi 0664, quindi e
leggibile dagli altri utenti locali. Se contiene credenziali va ristretto a
0600 in coordinamento con l'altro agente; non e stato modificato da questa
attivita. Il file env POS e gia 0600 e non ne copia i valori.

Il vecchio `POSIdentity::authenticatePOS()` autentica tramite SIN e contiene
un controllo quote disabilitato. Non viene riutilizzato automaticamente.
Prima degli incassi occorre concordare il protocollo con Napay: autenticazione
operatore e apertura revocabile, scope azienda/negozio/POS, creazione fattura
idempotente, verifiche di stato e autorizzazione su ricevute/storico.
Nessun protocollo finanziario e stato inventato in questa fase.

## Avvio e arresto

Prerequisiti: Docker Compose, OpenSSL, flock e gateway `napay-gateway` gia avviato
con rete interna `napay_local_browser` (launcher della piattaforma esistente).

```sh
cd pos/v2
bash docker/local.sh up
bash docker/local.sh status
bash docker/local.sh stop
```

Il launcher avvia/ferma solo POS. Configurazione privata in
`$HOME/.local/share/napay/pos-v2-local/local.env`, directory 0700/file 0600;
chiave cookie casuale persistita, mai nel repository o nella build.
Il launcher non ricarica o riavvia automaticamente il gateway condiviso.

Nuova rotta: `napay-project/docker/gateway/conf.d/pos.caddy`.
Il Caddy corrente 2.8.4 ha admin API disabilitata; la prima attivazione della
rotta ha richiesto riavvio **esplicitamente autorizzato** dall'utente. Una
configurazione validata e il controllo dei siti esistenti precedono/seguono
il riavvio. Non inviare SIGUSR1: il reload tramite segnale e disponibile solo
da Caddy 2.11. Non modificare la configurazione globale per aggirare il limite.

Questo POS non e ancora incluso in `start-localhost.sh`/`stop-localhost.sh`:
usare il proprio launcher. Nessun volume DB, migrazione o cancellazione dati.
`runtime` persiste su volume POS separato; stop non elimina volumi.

## Sicurezza e dipendenze

DocumentRoot solo `v2/web`; rootfs read-only, nessuna porta pubblicata dal web,
nessuna rete Internet per il backend e nessun DB/API/wallet collegato.
Cookie CSRF host-only, nome isolato, Secure/HttpOnly/SameSite Strict; POST con
CSRF. CSP restrittiva, no cache, no referer, niente frame. Dati variabili
codificati in HTML; errori pubblici generici; log senza query o dump richieste.
Anteprima locale senza login: non rappresenta autenticazione del terminale.

Composer indipendente con lock versionato, niente plugin o script automatici.
La build usa `composer install` dal lock, non `update`. Nessuna dipendenza dev,
Debug o Gii. Il nuovo vincolo Yii2 parte dalla versione 2.0.55 gia adottata
dalla piattaforma; il lock fissa le versioni effettive.

Prima di fissare PHP sono stati consultati annuncio e changelog ufficiali:
[PHP 8.3.36 security release](https://www.php.net/releases/8_3_36.php),
[changelog](https://www.php.net/ChangeLog-8.php#8.3.36).
Non sono state aggiornate immagini PHP o librerie delle altre applicazioni.

## Verifiche

```sh
docker exec pos-v2-local-web-1 php tests/amount.php
docker exec pos-v2-local-web-1 php tests/config.php
PLAYWRIGHT_PACKAGE=/percorso/playwright/package /percorso/node tests/browser.cjs
```

Browser: Chrome (`CHROME_PATH` opzionale); curl verifica CA completa, poi
automazione limita eccezione di fiducia alla chiave pubblica del certificato
POS (SPKI pin), senza bypass TLS globale. Richieste esterne bloccate.
CA esportata in `~/.local/share/napay/pos-v2-local/caddy-root.crt`;
`POS_CA_FILE` permette percorso diverso. CA pubblica, non chiave privata.

18 test importi, 9 configurazione e 24 HTTPS/browser (390/1280px), inclusi CSRF, array/strutture
malformate, XSS, percorsi privati e assenza operazioni finanziarie. Validazione
strict e audit Composer eseguiti; sintassi PHP/JS/shell e Compose controllati.
Non e configurato PHPStan/PHPCS. Nessun test di incasso o pairing dichiarato.
