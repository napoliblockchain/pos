# POS / Napay: decisioni e contratto funzionale

- Data: 2026-11-11.
- Stato: requisiti utente confermati; protocollo API da concordare con agente Napay.
- Perimetro: login operatore, selezione POS e apertura esclusiva; primo collaudo
  TTS su chain locale, senza denaro reale.
- Nessun endpoint descritto qui e gia implementato. Il POS resta in anteprima.

## Bozza tecnica per l'agente Napay

Specifica proposta: [pos-api.openapi.json](pos-api.openapi.json), formato
[OpenAPI 3.1](https://spec.openapis.org/oas/v3.1.0.html).
Stato `draft-awaiting-backend-review`: **non e un accordo API gia raggiunto**.
Non iniziare un client operativo dando per esistenti queste rotte.

Rilevazione backend del 2026-11-11: Napay commit `a4f4ffe3` usa login solo email;
nessun controller API POS presente. Il vecchio login tramite username non va
riproposto. La password deve essere verificata dal backend con le regole
centralizzate esistenti, senza copiarne hash o replicare la logica nel POS.

Base proposta `/api/pos/v1`:

| Metodo/percorso | Scopo |
| --- | --- |
| `POST /sessions` | Autenticare operatore con email/password. |
| `DELETE /sessions` | Revocare sessione e rilasciare soltanto le sue aperture. |
| `GET /terminals` | Catalogo POS filtrato per assegnazioni esplicite, paginato. |
| `POST /terminals/{terminal_id}/openings` | Acquisire apertura esclusiva con chiave idempotenza. |
| `GET /openings/{opening_id}` | Verificare titolarita e validita, senza acquisire lock. |
| `POST /openings/{opening_id}/heartbeat` | Registrare contatto senza ripristinare aperture revocate. |
| `POST /openings/{opening_id}/release` | Rilascio normale della propria apertura, non sblocco forzato. |

Il primo login propone un canale server-to-server dal POS a Napay: identita
del servizio POS piu sessione operatore opaca, revocabile. Token backend tenuti
solo nella sessione server POS; cookie browser POS separato, HttpOnly/Secure,
rigenerato al login. Password soltanto durante la verifica, mai persistita.
Si tratta di una **proposta**, alternativa a login centralizzato/SSO da
valutare con l'agente Napay, non di una scelta gia implementata.

Il browser non chiama questa API direttamente, non riceve il token backend
e non comunica con URL/host forniti dall'utente. Form login/apertura/logout del
POS restano protetti da CSRF; niente sessioni cookie Napay riutilizzate dal
client server-to-server. TLS con verifica CA/hostname, nessun redirect libero,
timeout e limiti risposte. Routing DNS/rete Docker e CA interna ancora da
concordare: non disattivare verifiche TLS per far funzionare un hostname.

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

Prima dell'integrazione l'agente backend deve confermare o correggere:

1. Login server-to-server o autenticazione centralizzata; identita del servizio,
   rotazione, scadenza sessione e modalita di revoca.
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

| Tema | Regola |
| --- | --- |
| Struttura | Un merchant ha piu negozi; un negozio ha piu POS. |
| Operatori | Un utente base puo essere abilitato a piu negozi. Il solo ruolo base non concede accesso ai POS. |
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

Le modalita tecniche di autenticazione tra `pos.localhost` e `napay.local`
vanno definite con l'agente backend prima del codice: login centralizzato,
trasporto server-to-server, credenziali/sessioni scoped e revocabili.
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
