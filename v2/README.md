# Napay POS v2 - primo avvio locale

Yii2, PHP 8.3.36, container indipendente, gateway Caddy condiviso.
Modalita produzione e rilascio VPS: [PRODUCTION.md](docs/PRODUCTION.md)
(SIP-036). La sandbox continua a usare `POS_MODE=preview` e `docker/local.sh`.
URL: **https://pos.localhost**. Chrome risolve `.localhost` su loopback;
per curl puo essere necessario `--resolve pos.localhost:443:127.0.0.1`.
Stessa CA di `napay.local` e `wallet.local`, nessuna nuova porta host.

## Stato reale

Prima dell'implementazione: [SIP-021](../../sip/SIP-021-pos-login-operatori-apertura-esclusiva.md).
Le nuove fasi richiedono SIP documentato e approvato; nessuna implementazione
deriva automaticamente dalla bozza OpenAPI.
Flusso login approvato: email/password nel POS, verifica API Napay e token
soltanto sul server POS. Login reale attivo tramite `POST /api/pos/v1/sessions`;
nessuna copia del database utenti o degli hash password.

Questa e la **base navigabile di migrazione**, non un POS per incassi reali.
Tastierino, validazione importi lato server, layout mobile/desktop e protezioni
HTTP attivi. **Incasso TTS (SIP-021 fase 3, solo chain di test):** il pulsante
"Incassa TTS" crea la richiesta in Napay dall'apertura corrente; la pagina mostra
QR (formato del wallet, con riferimento), importo, scadenza e wallet del merchant
scelto da Napay; il browser interroga lo stato ogni 3 s tramite il server POS;
a pagamento confermato la ricevuta e un PDF A7 (tc-lib-pdf). "Storico incassi"
mostra gli ultimi 50 incassi del terminale negli ultimi 30 giorni (anche di
aperture precedenti), con ricevuta per quelli pagati. Importi in centesimi
interi, da 0,01 a 1000,00 TTS per richiesta (stesso limite del backend).
Contratto: [PAGAMENTO-TTS](../../sip/SIP-021/PAGAMENTO-TTS-CONTRATTO.md) e
[porting dal POS legacy](../../sip/SIP-021/POS-CLIENT-PAGAMENTO.md).

**Apertura esclusiva (SIP-021 fase 2, 2026-11-13):** dal catalogo l'operatore
apre un POS; Napay garantisce una sola apertura per terminale (409 se occupato,
anche dallo stesso utente da un altro dispositivo). Apertura custodita solo
nella sessione server POS, verificata a ogni caricamento della cassa, heartbeat
ogni 30 s (solo informativo), chiusura esplicita e al logout; lo sblocco forzato
è del responsabile nel backoffice Napay. Contratto:
[APERTURE-CONTRATTO](../../sip/SIP-021/APERTURE-CONTRATTO.md).

**Mobile-first e PWA (requisito):** interfaccia a una colonna per terminalino o
smartphone, manifest installabile, service worker che tiene in cache solo il
guscio statico e mostra `offline.html` senza rete (mai pagine con dati operatore).

**BTC (SIP-033):** incasso, stato, storico, totali e ricevuta implementati;
la disponibilita del metodo e decisa da Napay per ciascuna apertura.

Decisioni concordate il 2026-11-11: login utente base, POS autorizzati per piu
negozi, apertura esclusiva e sblocco admin/merchant; wallet unico per merchant.
Il pairing tramite SIN/codici non e il flusso scelto.
Vedere [contratto funzionale per l'agente Napay](docs/NAPAY-CONTRACT.md).
Le decisioni documentate non rendono queste funzioni gia disponibili.
Disponibile [bozza OpenAPI](docs/pos-api.openapi.json) per l'agente backend:
7 operazioni: login, logout e catalogo implementati sul contratto SIP-021;
altre 4 ancora proposte, non implementate. Controlli strutturali ripetibili:
`node tests/api-contract.cjs`, senza test o chiamate al backend operativo.
Sono ammessi solo `POS_MODE=preview` e `POS_MODE=production`.
Per la VPS usare esclusivamente la composizione e lo script di produzione;
per il collaudo locale usare soltanto account e chain della sandbox.

Accesso da **https://pos.localhost/site/login**. Dopo il login compare soltanto
il nome operatore e il catalogo autorizzato, non un terminale aperto. Solo
account base attivi, profilo e grant abilitati per negozi dello stesso merchant;
nessun grant implicito per ruolo. Lista vuota reale se non assegnato. Token Napay custodito nella sessione
PHP sul server, ID rigenerato al login, scadenza assoluta ricevuta dal backend
(default 12 ore). Logout chiama `DELETE /sessions` con chiave client e bearer
solo server-to-server: dopo 204 elimina la sessione locale. In caso di errore
mantiene la sessione e propone retry, senza dichiarare una revoca non confermata.
La revoca e idempotente e limitata al token di quel client. Le future operazioni protette
dovranno riverificare grant/revoche sul backend; la pagina account non li certifica.

Il catalogo viene riletto da Napay a ogni visita/pagina, senza cache dei grant.
Mostra `descrizione negozio - nome POS` e "Non verificata", senza comandi di
apertura. Cursor cifrato legato a sessione/client/limit; nessun token backend
nel link. Default 50 righe, massimo 100, ma il budget JSON 16 KiB puo produrre
pagine piu corte. Errori temporanei non riutilizzano elenchi precedenti;
sessione remota non valida elimina la custodia locale e richiede nuovo login.
Contratto: [C2/C3](../../sip/SIP-021/C2-CONTRATTO.md).

Il codice Yii1 resta intatto, ma non viene montato o caricato dal container v2.
Nessun runtime da `yii-1.1.22`, `packages` o vendor di Napay/poawallet.
Il repo mantiene branch `feature/v2-yii2-php83-docker`.

## Revisione integrazione esistente

L'altro agente ha trasferito Napay al Caddy condiviso: commit Napay `6e7bbead`,
root orchestratore `6c3ab42`. Preservati compose e script Napay/wallet.
POS risultava ancora Yii1 (ultimo commit applicativo `b82071c`, 2021), senza
implementazione v2. SIP-020 assegna migrazione associazione/legacy al backoffice.
Questa fase non crea tabelle POS nel database gestito dall'altro agente.

Il file `.env` privato dell'orchestratore e stato ristretto a 0600 durante
lo spostamento dei percorsi. Il POS ne legge solo i nomi di percorso sotto
indicati, senza eseguirne il contenuto o copiarne le credenziali.

Il vecchio `POSIdentity::authenticatePOS()` autentica tramite SIN e contiene
un controllo quote disabilitato. Non viene riutilizzato automaticamente.
Prima degli incassi occorre concordare il protocollo con Napay: autenticazione
operatore e apertura revocabile, scope azienda/negozio/POS, creazione fattura
idempotente, verifiche di stato e autorizzazione su ricevute/storico.
Nessun protocollo finanziario e stato inventato in questa fase.

## Avvio e arresto

Prerequisiti: Docker Compose, OpenSSL, flock e gateway `napay-gateway` gia avviato
con rete interna `pos_local_browser` (subnet 10.251.0.0/24, creata dal launcher
della piattaforma). Avviare prima lo stack condiviso con
`bash /var/www/blockchain-napoli/napay-project/start-localhost.sh`.

```sh
cd pos/v2
bash docker/local.sh up
bash docker/local.sh status
bash docker/local.sh stop
```

Il launcher avvia/ferma solo POS. Percorsi letti dalle variabili di processo,
oppure dalle sole righe corrispondenti del `.env` privato dell'orchestratore:

```dotenv
POS_LOCAL_STATE=/var/www/documents/progetti/napoliblockchain/pos/ambiente-locale
NAPAY_LOCAL_STATE=/var/www/documents/progetti/napoliblockchain/napay/ambiente-locale
POS_LOCAL_DATA=/var/www/blockchain-napoli/pos-local-data
```

Nessun percorso personale e fissato nel codice; nessun fallback alla vecchia
cartella HOME. Percorsi assoluti, fuori dai repository applicativi, proprietario
corrente e directory senza permessi gruppo/altri; symlink diretti rifiutati.
`$POS_LOCAL_STATE` contiene `local.env`, `control.lock` e la copia storica pubblica
`caddy-root.crt`: directory 0700/file 0600, `.gitignore` locale esclude tutti i
contenuti tranne se stesso (anche `documents` e un repository). La chiave cookie
preesistente e stata conservata, non rigenerata. Un nuovo avvio crea la chiave
solo se `local.env` non esiste.

Sorgenti secret: `NAPAY_CLIENT_KEY_SOURCE=$NAPAY_LOCAL_STATE/pos-client-key` e
`NAPAY_CA_SOURCE=$NAPAY_LOCAL_STATE/caddy-root.crt`, sovrascrivibili tramite
variabili di processo. Montate read-only, copiate in tmpfs dal solo entrypoint
e leggibili dal worker UID 33 con modo 0400. Nessuna chiave nella build o nei log.
I test browser leggono la CA dallo stesso `NAPAY_LOCAL_STATE`, non dalla copia
storica POS. `login-browser.cjs` usa li anche `browser-password` e `pos-client-key`.
Il launcher non ricarica o riavvia automaticamente il gateway condiviso.

Nuova rotta: `napay-project/docker/gateway/conf.d/pos.caddy`.
Il Caddy corrente 2.8.4 ha admin API disabilitata; la prima attivazione della
rotta ha richiesto riavvio **esplicitamente autorizzato** dall'utente. Una
configurazione validata e il controllo dei siti esistenti precedono/seguono
il riavvio. Non inviare SIGUSR1: il reload tramite segnale e disponibile solo
da Caddy 2.11. Non modificare la configurazione globale per aggirare il limite.

Questo POS non e ancora incluso in `start-localhost.sh`/`stop-localhost.sh`:
usare il proprio launcher. Nessun volume DB, migrazione o cancellazione dati.
`runtime` persiste tramite bind mount in `$POS_LOCAL_DATA/runtime`, fuori dai
repository; directory/sessioni private del worker UID 33. Il precedente volume
`pos-v2-local_runtime` e stato copiato a POS fermo e conservato come salvaguardia.
Stop, build e ricreazione del container non eliminano i dati esterni.

## Sicurezza e dipendenze

DocumentRoot solo `v2/web`; rootfs read-only, nessuna porta pubblicata dal web,
nessuna rete Internet per il backend e nessun DB/wallet collegato. Solo rete POS:
API Napay via gateway `napay.local`, TLS verificato con CA e hostname, timeout
5 secondi, risposta massima 16 KiB, redirect e proxy disabilitati. Il POS non e
sulla rete web Napay e non raggiunge direttamente il backend HTTP.
Cookie CSRF host-only, nome isolato, Secure/HttpOnly/SameSite Strict; POST con
CSRF. CSP restrittiva, no cache, no referer, niente frame. Dati variabili
codificati in HTML; errori pubblici generici; log senza query o dump richieste.
Anteprima importi resta anonima; login operatore separato non rappresenta
autorizzazione/apertura del terminale. Nessun token backend in cookie, URL o HTML.

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
docker exec pos-v2-local-web-1 php tests/login-client.php
docker exec pos-v2-local-web-1 php tests/logout-client.php
docker exec pos-v2-local-web-1 php tests/catalog-client.php
docker exec --user 33 pos-v2-local-web-1 php tests/transport.php
node tests/api-contract.cjs
node tests/local-state.test.cjs
PLAYWRIGHT_PACKAGE=/percorso/playwright/package /percorso/node tests/browser.cjs
PLAYWRIGHT_PACKAGE=/percorso/playwright/package /percorso/node tests/login-browser.cjs
```

Browser: Chrome (`CHROME_PATH` opzionale); curl verifica CA completa, poi
automazione limita eccezione di fiducia alla chiave pubblica del certificato
POS (SPKI pin), senza bypass TLS globale. Richieste esterne bloccate.
CA in `$NAPAY_LOCAL_STATE/caddy-root.crt`;
`POS_CA_FILE` permette percorso diverso. CA pubblica, non chiave privata.

18 test importi, 13 configurazione, 37 client/sessioni con trasporto simulato,
2 trasporto TLS reale, 7 percorsi privati e 24 HTTPS/browser (390/1280px), inclusi CSRF, array/strutture
malformate, XSS, percorsi privati e assenza operazioni finanziarie. Validazione
strict e audit Composer eseguiti; sintassi PHP/JS/shell e Compose controllati.
Ulteriori 24 test browser eseguono login/logout reale POS/Napay sulla fixture locale,
rigenerazione sessione, CSRF logout e assenza segreti nel browser. Altri 18
controlli simulati verificano il client/controller logout, inclusa custodia
locale mantenuta dopo errore e retry confermato. Nessun denaro reale.
Backend: suite `napay/tests/application/pos-sessions.php` include `pos-logout.php`:
62 controlli login e 32 logout HTTP/DB isolati (revoca, replay, client diversi,
concorrenza, cambio password, scadenza, account disabilitato, DB indisponibile,
rate limit e assenza segreti). Nessuna modifica al DB operativo per questi test.
I 37 test simulati e i controlli strutturali OpenAPI non provano autorizzazioni
backend o lock. Non e configurato PHPStan/PHPCS. Nessun test di incasso o apertura esclusiva dichiarato.

Catalogo: 37 controlli client/vista/controller con trasporto simulato. API reale
popolata verificata solo su MySQL usa-e-getta con `napay/tests/docker/pos-terminals.sh`;
la sandbox browser conserva il proprio catalogo reale, anche vuoto. Nessun seed
di grant o terminali inserito nella sandbox per far apparire riuscita la demo.
