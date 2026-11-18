# POS / Napay: decisioni e contratto funzionale

Fonte normativa: [SIP-021](../../../sip/SIP-021-pos-login-operatori-apertura-esclusiva.md).
Questo documento e allegato tecnico; non sostituisce SIP e approvazione della
fase prima del codice. Requisiti confermati non implicano protocollo API approvato.

- Data: 2026-11-12.
- Stato: sessioni e catalogo autorizzato implementati secondo SIP-021; aperture da concordare.
- Perimetro: login operatore, selezione POS e apertura esclusiva; primo collaudo
  TTS su chain locale, senza denaro reale.
- `POST /sessions`, `DELETE /sessions`, `GET /terminals` disponibili. Nessun incasso o apertura.

## Bozza tecnica per l'agente Napay

Specifica proposta: [pos-api.openapi.json](pos-api.openapi.json), formato
[OpenAPI 3.1](https://spec.openapis.org/oas/v3.1.0.html).
Sessioni/catalogo allineati al backend; dal 2026-11-13 anche le quattro operazioni
di apertura sono implementate secondo `sip/SIP-021/APERTURE-CONTRATTO.md`.

Backend del 2026-11-11: Napay commit `b79ae7ab` implementa il login API solo email.
Il vecchio login tramite username non va
riproposto. La password deve essere verificata dal backend con le regole
centralizzate esistenti, senza copiarne hash o replicare la logica nel POS.

Base `/api/pos/v1` (solo prime tre righe implementate):

| Metodo/percorso | Scopo |
| --- | --- |
| `POST /sessions` | Autenticare operatore con email/password. |
| `DELETE /sessions` | Revocare solo il token del client chiamante; nessuna apertura esiste ancora. |
| `GET /terminals` | Catalogo POS filtrato per assegnazioni esplicite, paginato. |
| `POST /terminals/{terminal_id}/openings` | Acquisire apertura esclusiva con chiave idempotenza. |
| `GET /openings/{opening_id}` | Verificare titolarita e validita, senza acquisire lock. |
| `POST /openings/{opening_id}/heartbeat` | Registrare contatto senza ripristinare aperture revocate. |
| `POST /openings/{opening_id}/release` | Rilascio normale della propria apertura, non sblocco forzato. |

Flusso approvato dall'utente: login nel POS e verifica email/password via API
Napay server-to-server, non redirect/SSO. Token backend tenuti solo nella
sessione server POS; cookie browser POS separato, HttpOnly/Secure e rigenerato
al login. Password soltanto durante la verifica, mai persistita o registrata.
Login: header `X-POS-Client-Key` (43 caratteri base64url), facoltativo
`X-POS-Operator-IP` validato e derivato dalla richiesta tramite proxy fidato.
201 restituisce solo `access_token`, `token_type=Bearer`, `expires_at` UTC e
`operator.display_name`; nessun `operator.id`. Token `napos_` seguito da 43
caratteri base64url, scadenza assoluta default 12 ore, nessun refresh.
Errori JSON: 400 `validation_failed`, 401 `unauthenticated` o `invalid_credentials`,
429 `rate_limited` con `Retry-After` e 503 `backend_unavailable`. Altri metodi:
405 sui metodi diversi da POST/DELETE, senza assumere lo stesso formato JSON del framework.

Login POS riservato a operatore dedicato: account base attivo, identita R1
coerente, profilo POS abilitato e merchant PG qualificato. Soci, admin e gestori
non ricevono token, nemmeno con password corretta. Profilo assente/disabilitato
usa lo stesso 401 `invalid_credentials`. Nessun grant richiesto per il login:
un operatore abilitato senza negozi assegnati riceve catalogo vuoto.
Token legacy non idoneo o profilo disabilitato: 401 `invalid_token` sul catalogo.
Fonte: [LOGIN-R1-D3](../../../sip/SIP-021/LOGIN-R1-D3.md). Il client esistente
gestisce questi errori senza cambiare formato wire o conservare credenziali.

Il browser non chiama questa API direttamente, non riceve il token backend
e non comunica con URL/host forniti dall'utente. Form login/apertura/logout del
POS restano protetti da CSRF; niente sessioni cookie Napay riutilizzate dal
client server-to-server. TLS con verifica CA/hostname, nessun redirect libero,
timeout 5 secondi e risposta massima 16 KiB. Rete dedicata `pos_local_browser`,
gateway con alias `napay.local`, CA interna montata come secret e TLS sempre
verificato. Nessun redirect, proxy o accesso HTTP diretto al web Napay.

Il client POS verifica schema e scadenza prima di custodire il token nella
sessione server, rigenerando l'ID browser. Non mostra credenziali o errori
upstream. Logout `DELETE /sessions`: chiave client e bearer, corpo vuoto;
204 vuoto per revoca/replay/token ignoto o di altro client, senza modificare
sessioni altrui. Bearer malformato: 401 `invalid_token`; corpo presente: 400;
limite separato 60/min per client: 429; errore backend: 503. POS elimina la
sessione locale solo dopo 204 verificato; errore mantiene custodia e consente retry.
La pagina account rilegge il catalogo a ogni visita; non certifica disponibilita
o apertura e ogni futura API protetta dovra riverificare grant/sessione.
Percorsi privati e test reali descritti nel [README](../README.md).

Catalogo: [contratto C2/C3](../../../sip/SIP-021/C2-CONTRATTO.md). Solo account
base attivi, profilo/grant/terminale abilitati sullo stesso merchant, scope SQL
prima di keyset/paginazione. Nessun bypass per ruolo, lista vuota senza grant.
Cursor cifrato con autenticazione, legato a sessione/client/limit; JSON massimo
16 KiB, limite righe 1..100 (default 50). Disponibilita solo `unknown`.
Budget client 120/min e sessione 30/min; 400 `validation_failed`, 401
`unauthenticated`/`invalid_token`, 429 `rate_limited`, 503 `backend_unavailable`.
Altri metodi 405. Invalidazione remota elimina custodia POS; guasto non mostra
una lista in cache. Nessuna apertura acquisita da questo GET.

Acquisizione nuova occupata: `409 terminal_in_use`. Replay della stessa chiave
idempotente e stesso payload: ritorno dell'apertura originale; chiave riusata
con payload diverso: `409 idempotency_conflict`. Una nuova istanza/tab richiede
nuova acquisizione e non puo assumere l'apertura solo perche usa lo stesso utente.
Errori login generici, nessuna distinzione pubblica account inesistente,
disabilitato o email ambigua. Errori e risposte `no-store`; nessun segreto nei log.

Lo sblocco forzato appartiene al backoffice Napay, con autorizzazione manager
e CSRF propri: non e esposto come parametro `force` dell'API operatore.
Ogni sblocco cambia generazione/fencing e rende inutilizzabile l'apertura
precedente. Il formato wire della funzione manager resta all'agente Napay.

Per le successive API l'agente backend deve confermare o correggere:

1. Uso della sessione nelle successive API e rilascio futuro delle aperture
   durante logout (revoca del token gia implementata secondo SIP).
2. URL, identificativi pubblici, JSON, paginazione ed errori proposti.
3. Relazione utente-negozio e lock unico persistente/atomico, con fencing.
4. Chiusura normale/logout, recupero dopo refresh e gestione crash senza
   sblocco automatico non autorizzato.
5. Sblocco dal backoffice e test di concorrenza/revoca su database reale di test.

Controllo locale: `node tests/api-contract.cjs` dalla directory `pos/v2`.
Verifica JSON, struttura attesa, security requirement, nomi operazioni e
riferimenti locali. **Non e un validatore completo OpenAPI**, non verifica
implementazione backend, autenticazione, autorizzazioni o concorrenza.
Non introduce librerie o endpoint e non richiede credenziali operative.

## Decisioni confermate

Fonte trasversale: [SIP-023](../../../sip/SIP-023-profili-soci-merchant-operatori.md).
R1 applicata a login e sessioni/catalogo; iscrizioni PF/PG, autoattivazione
merchant e inviti restano passaggi separati, non impliciti in questi endpoint.

| Tema | Regola |
| --- | --- |
| Struttura | Un merchant ha piu negozi; un negozio ha piu POS. |
| Operatori | Solo account base attivi, assegnabili a piu negozi del proprio merchant. Il ruolo base da solo non concede accesso. |
| Societa merchant | Solo PG socia puo diventare merchant; il gestore amministra ma non usa i POS. |
| Attivazione merchant | PG prima iscritta come socio, poi autoattiva merchant senza approvazione admin. Lo stesso account diventa unico gestore, non operatore POS. |
| Gestore merchant | Un account gestore dedicato a un solo merchant; crea solo i propri account operatore POS. Merchant diversi restano separati. |
| Soci e operatori | L'invito non iscrive all'associazione. Stessa persona socio/operatore: account separati con email differenti; nessun riuso dell'account socio. |
| Merchant operatore | Un solo merchant per operatore; grant espliciti solo verso negozi di quel merchant. Nessuna abilitazione implicita a tutti i suoi negozi. |
| Primo catalogo | Disponibilita "Non verificata" (`availability: "unknown"`), senza aperture abilitate prima dei lock. |
| Accesso | Login utente Napay, poi scelta tra i POS autorizzati. Niente login tramite SIN o codice terminale. |
| Etichetta | Selezione per `descrizione negozio - nome POS`, senza esporre SIN o codici tecnici. |
| Esclusivita | Un solo dispositivo/apertura attiva per POS. Seconda apertura bloccata, anche se l'utente e lo stesso. |
| Sblocco forzato | Solo admin oppure merchant proprietario del POS. Mai un merchant di un'altra azienda. |
| Destinazione TTS | Wallet unico del merchant; non scelto o modificato dal client POS. |
| Evoluzioni | Wallet per negozio oggetto di futuro SIP separato, non parte di questa fase. |

La proposta precedente di codice/QR monouso per il pairing e superata dal
login operatore richiesto dall'utente. SIN BTCPay rimane un riferimento
tecnico lato backend, non una credenziale e non un prerequisito per TTS.

## Confini applicativi

Napay resta autorita per account, stato utente, abilitazioni utente-negozio,
merchant/negozi/POS, lock di apertura, revoche e wallet destinatario.
Non copiare database utenti, hash password o chiavi wallet nel POS.

Il POS presenta login, catalogo autorizzato e stato del terminale. Le sole
abilitazioni grafiche non sono autorizzazione: il backend verifica utente,
negozio e merchant a ogni operazione protetta, anche conoscendo un ID valido.
Nessuna concessione implicita a tutti i negozi di un merchant.

Il flusso scelto tra `pos.localhost` e `napay.local` e server-to-server, con
credenziali verificate da Napay e token solo sul server POS. Autenticazione del
servizio e formato della sessione login definiti nel SIP; scope per risorsa e
rilascio aperture da completare col backend; logout token implementato. Nessun redirect al login centrale previsto.
Non condividere automaticamente cookie tra host, non salvare password/token
in URL o localStorage, non esporre token backend al browser senza un design
esplicito. Nessuna API pubblica o CORS permissiva per aggirare il problema.

## Operazioni richieste al backend

Questi sono nomi funzionali, non URL o payload definitivi.

| Operazione | Contratto |
| --- | --- |
| Autenticare operatore | Account Napay attivo, controllo tentativi/rate limit, errori generici; sessione POS revocabile. |
| Elencare POS | Solo negozi esplicitamente assegnati; ID interno opaco, etichetta negozio/POS, stato disponibilita. Nessun SIN, segreto o chiave wallet. |
| Aprire POS | Acquisizione atomica sul backend; ricontrollare grant e stato POS. Se occupato: conflitto, nessuna sostituzione della sessione esistente. |
| Verificare apertura | Sessione utente valida, grant ancora presente, apertura attiva e appartenente all'istanza richiedente. |
| Chiudere POS | Proposta tecnica: il titolare puo rilasciare la propria apertura con logout/chiusura esplicita; non puo rilasciare quella di altri. |
| Sbloccare forzatamente | Admin o merchant proprietario, POST/CSRF o equivalente protezione del canale; invalidare immediatamente l'apertura precedente. |
| Creare richiesta TTS | Fase successiva: apertura valida, importo e rete/token verificati dal backend, destinazione wallet merchant risolta dal backend, idempotenza. |

Catalogo filtrato prima di conteggi e paginazione. L'API non deve accettare
`merchant_id`, `store_id`, indirizzo wallet o SIN come prova di autorizzazione.
Un eventuale `pos_id` seleziona una risorsa: non ne concede l'accesso.

## Lock e revoca

L'esclusivita deve essere persistita lato backend e applicata atomicamente:
due richieste contemporanee non possono entrambe acquisire lo stesso POS.
Un controllo del solo browser, un flag in sessione locale o localStorage non
sono sufficienti. Prevedere identificativo di apertura e versione/generazione
per rifiutare operazioni provenienti da un'apertura revocata (fencing).

Lo sblocco forzato invalida l'apertura precedente prima di consentirne una
nuova; il vecchio dispositivo non deve continuare a creare richieste TTS.
Revoca grant, disabilitazione utente/POS e logout devono essere considerate
nelle verifiche, non solo al primo login.

Proposte da validare tecnicamente: heartbeat per segnalare dispositivo non
raggiungibile e ripresa dopo refresh. Nessuno sblocco automatico per timeout
viene autorizzato da questa decisione: un heartbeat scaduto non concede a
un secondo dispositivo il diritto di espellere il primo. Crash e rete persa
devono poter essere risolti da merchant/admin senza riavviare l'applicazione.

Audit senza credenziali: operatore, POS/negozio/merchant, azione, esito e
manager che effettua lo sblocco. Non registrare password, token o SIN.

## Casi di accettazione

1. Utente assegnato a due negozi vede i POS di entrambi, non quelli di altri negozi.
2. Utente base senza assegnazioni non vede e non apre alcun POS.
3. ID di POS altrui, anche valido, non permette apertura, storico o ricevute.
4. Due aperture simultanee dello stesso POS: una sola ha successo.
5. Seconda apertura dallo stesso utente non espelle automaticamente la prima.
6. Operatore non puo forzare lo sblocco; merchant puo farlo solo sui propri POS.
7. Dopo sblocco/revoca, la vecchia apertura non puo creare ulteriori richieste.
8. Catalogo ed errori non espongono SIN, chiavi, token o wallet modificabili dal client.
9. Richieste TTS di POS diversi dello stesso merchant usano il suo unico wallet.
10. Primo test su chain locale; nessun pagamento reale durante sviluppo.

## Passi piccoli e responsabilita

1. Agente Napay: concordare modello abilitazioni, autenticazione, URL/payload,
   lock persistente e semantica revoche. Nessuna migration implicita da questo documento.
2. Agente POS: collegare login e selettore al contratto approvato, inizialmente
   senza incassi; test grant, sessioni e tentativi di doppia apertura.
3. Integrare apertura esclusiva e sblocco manager, con test concorrenza reali
   sul backend, non con mock che dichiarino provata l'esclusivita.
4. Solo dopo: richiesta TTS idempotente, QR, stato e ricevuta su chain locale.

Un piccolo commit per ciascuno step verificato. Nessuna modifica al backend
Napay da parte dell'agente POS mentre l'altro agente lo sta sviluppando.
