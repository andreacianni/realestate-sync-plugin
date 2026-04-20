# Validated Implementation Plan - `missing_from_feed`

> Tipo documento: OPERATIVO  
> Basato su: Implementation Plan del 2026-04-20  
> Obiettivo: versione validata, pronta per uso in produzione

## 1. Strategia generale di implementazione

### Ordine macro-step

1. Preparare le strutture isolate
- nuova tabella
- nuovo manager queue delete
- nessun impatto sul runtime attuale

2. Aggiungere detection e persistenza in sola modalita' sicura
- detection nel `Batch_Orchestrator`
- persistenza `realestate_delete_queue`
- nessuna delete reale

3. Aggiungere guardrail backend prima del worker
- `dry_run`
- kill switch
- cap base
- nessuna delete reale non controllata

4. Definire e salvare stati e contatori lato backend
- progress state
- contatori delete
- recovery item stantii

5. Esporre stati e contatori in admin/UI
- solo dopo che i dati backend sono stabili

6. Aggiungere worker delete asincrono
- `batch-continuation.php`
- nuovo processor delete
- riuso `Deletion_Manager`

7. Spostare la finalizzazione alla vera fine sessione
- verifier
- report
- email

### Dipendenze tra step

- La tabella e il manager delete queue devono esistere prima della detection
- La detection deve esistere prima dei guardrail runtime
- I guardrail devono esistere prima del worker delete
- Il checkpoint sul filtro provincia deve essere superato prima dello Step 2
- Gli stati backend devono esistere prima della UI admin
- Il worker delete deve esistere prima di cambiare la finalizzazione

### Step sicuri

- creazione tabella
- aggiunta manager dedicato
- detection con sola persistenza
- aggiunta `dry_run`, kill switch e cap senza agganciare ancora la delete reale
- aggiunta stati e log backend senza cambiare ancora la finalizzazione

### Step rischiosi

- ingresso del worker delete in `batch-continuation.php`
- modifica del punto di finalizzazione sessione
- prima attivazione `soft/live`
- interazione con attachments e hook WordPress

### Contratto modalita' operative

#### `dry_run`

- detection: obbligatoria
- persistenza `realestate_delete_queue`: obbligatoria
- aggiornamento stati e contatori backend: obbligatorio
- esposizione monitor/admin/report: obbligatoria
- candidati missing: obbligatori e tracciati
- contatori delete: obbligatori e coerenti
- delete reale: vietata
- chiamata runtime a `Deletion_Manager` per eseguire delete: vietata

Clausola forte:
> Se in modalita' `dry_run` viene eseguita una delete reale, e' un bug bloccante.

#### `soft`

- detection: si'
- persistenza queue: si'
- worker delete: attivo
- delete reale: si'
- cap: obbligatorio
- kill switch: obbligatorio
- 1 item per tick: obbligatorio
- stop su anomalie: si'

Obiettivo:
> rollout controllato

#### `live`

- detection: si'
- persistenza queue: si'
- worker delete: attivo
- delete reale: si'
- cap: configurabile
- kill switch: sempre attivo
- 1 item per tick: invariato

#### Decisione architetturale vincolante

- la modalita' operativa:
  - viene salvata nello stato/sessione backend
  - viene letta e applicata nel worker `batch-continuation.php` / delete processor
- `Deletion_Manager`:
  - non gestisce modalita' `dry_run/soft/live`
  - resta motore di delete reale

### Contratto blocco nuovo import

#### Sorgente di verita'

- `realestate_sync_background_import_progress.status`

#### Stati bloccanti

- un nuovo import non puo' partire se lo stato e':
  - `delete_pending`
  - `delete_processing`
  - `delete_paused`

#### Stati consentiti

- un nuovo import puo' partire solo se lo stato e':
  - `completed`
  - `completed_with_errors`
  - oppure non esiste alcuna sessione attiva valida

#### Ambito del blocco

- il blocco deve essere applicato a:
  - cron / scheduler
  - avvio manuale admin
  - resume / retry
  - qualsiasi entry point backend che avvia import

Clausola:
> Il blocco non puo' essere solo lato UI. Deve essere backend/runtime.

#### Comportamento quando il blocco scatta

- l'import non parte
- viene loggato il motivo
- il monitor mostra chiaramente:
  - sessione ancora in fase delete

#### Recovery stato incoerente

- se:
  - lo stato indica fase delete
  - ma non esiste piu' lavoro reale `import queue + delete queue vuote`
- allora:
  - non resettare automaticamente in modo cieco
  - segnalare come anomalia
  - richiedere verifica o recovery controllata

## 2. Piano file-per-file

### `includes/class-realestate-sync-batch-orchestrator.php`

- Ruolo nel sistema:
  - orchestrazione iniziale import
  - scansione XML
  - filtro provincia
  - creazione import queue
  - gestione legacy `deleted=1`

- Tipo intervento:
  - `DA MODIFICARE`

- Modifiche previste:
  - verificare esplicitamente nel codice reale il punto in cui avviene il filtro provincia
  - aggiungere raccolta `feed_active_property_ids`
  - aggiungere raccolta blacklist `feed_deleted_property_ids`
  - aggiungere punto di detection `missing_from_feed`
  - aggiungere persistenza nella nuova delete queue
  - salvare modalita' operativa backend:
    - `dry_run`
    - `soft`
    - `live`
  - salvare cap base e kill switch nello stato sessione/backend
  - estendere `realestate_sync_background_import_progress` con nuovi stati e contatori minimi delete
  - mantenere invariato il ramo legacy `deleted=1`

- Dipendenze:
  - nuovo `RealEstate_Sync_Delete_Queue_Manager`
  - `RealEstate_Sync_Deletion_Manager`
  - `RealEstate_Sync_Queue_Manager`
  - `realestate_sync_background_import_progress`

- Rischi:
  - alterare il conteggio attuale `total_queued`
  - raccogliere `feed_active_property_ids` prima del filtro provincia
  - introdurre detection fuori filtro provincia
  - generare candidati duplicati con `deleted=1`

- Come testare:
  - avvio import in `dry_run`
  - verificare log/count:
    - attivi feed
    - `deleted=1`
    - missing candidati
  - verificare che import queue attuale continui a popolarsi come prima
  - verificare che modalita', cap e kill switch siano salvati lato backend
  - verificare esplicitamente che la raccolta `feed_active_property_ids` avvenga dopo il filtro provincia

### `batch-continuation.php`

- Ruolo nel sistema:
  - cron endpoint
  - lock globale
  - continuazione batch import
  - finalizzazione sessione

- Tipo intervento:
  - `DA MODIFICARE`

- Modifiche previste:
  - aggiungere controllo sessione in fase delete
  - aggiungere passaggio `import complete -> delete_pending`
  - aggiungere esecuzione worker delete da 1 item per tick
  - rispettare guardrail backend:
    - `dry_run`
    - kill switch
    - cap base
  - aggiungere recovery item delete bloccati in `processing`
  - impedire finalizzazione se delete queue non e' conclusa
  - bloccare avvio nuovo import tramite controllo deterministico su `realestate_sync_background_import_progress.status`
  - trattare come blocco forte gli stati:
    - `delete_pending`
    - `delete_processing`
    - `delete_paused`
  - aggiornare progress option con stati delete

- Dipendenze:
  - nuovo `RealEstate_Sync_Delete_Queue_Manager`
  - nuovo `RealEstate_Sync_Delete_Batch_Processor`
  - `RealEstate_Sync_Import_Verifier`
  - `RealEstate_Sync_Email_Report`

- Rischi:
  - lock non rilasciato
  - finalizzazione troppo presto
  - nuovo import partito mentre la delete precedente e' ancora aperta

- Come testare:
  - simulare sessione con import queue finita e delete queue pending
  - verificare che:
    - non parta verifier
    - non parta email
    - venga processato un solo delete item per tick
  - verificare che in `dry_run` il worker non invochi `Deletion_Manager`
  - verificare che `dry_run` non processi delete reali
  - verificare che il cap blocchi la prosecuzione oltre soglia
  - verificare che un nuovo import schedulato non parta se `realestate_sync_background_import_progress.status` e' in fase delete

### `includes/class-realestate-sync-deletion-manager.php`

- Ruolo nel sistema:
  - hard delete property/agency
  - lookup via meta
  - update tracking stato deleted

- Tipo intervento:
  - `DA MODIFICARE`

- Modifiche previste:
  - aggiungere entry point singolo per delete di un item property
  - formalizzare contratto esplicito del delete singolo:
    - `success`
    - `not_found`
    - `error`
  - definire impatto sulla delete queue:
    - `success` -> item `done`
    - `not_found` -> item `skipped`
    - `error` -> item `error`
  - lasciare invariata la logica core di `wp_delete_post`

- Dipendenze:
  - update tracking inline nello stesso `Deletion_Manager` via `$wpdb`
  - hook `before_delete_post`
  - `Attachment_Cleanup`

- Rischi:
  - alterare il comportamento del delete legacy `deleted=1`
  - cambiare accidentalmente il contratto usato dall'orchestrator

- Come testare:
  - chiamata singola su property esistente
  - chiamata singola su property gia' assente
  - chiamata singola che forza errore
  - verificare mapping esplicito:
    - `success` -> delete queue `done`
    - `not_found` -> delete queue `skipped`
    - `error` -> delete queue `error`
  - verificare risultato coerente senza loop/errori rumorosi

### `realestate-sync.php`

- Ruolo nel sistema:
  - bootstrap plugin
  - creazione tabelle
  - load dependencies

- Tipo intervento:
  - `DA MODIFICARE`

- Modifiche previste:
  - includere il nuovo manager delete queue
  - includere il nuovo delete batch processor
  - creare tabella `realestate_delete_queue` in activation/bootstrap

- Dipendenze:
  - nuovo `includes/class-realestate-sync-delete-queue-manager.php`
  - nuovo `includes/class-realestate-sync-delete-batch-processor.php`

- Rischi:
  - tabella non creata in ambienti gia' attivi
  - caricamento dependency in ordine errato

- Come testare:
  - verificare esistenza tabella
  - verificare che il plugin continui a caricarsi senza fatal

### `includes/class-realestate-sync-import-verifier.php`

- Ruolo nel sistema:
  - verifica post-import

- Tipo intervento:
  - `DA MODIFICARE`

- Modifiche previste:
  - nessuna nuova logica di verifica delete
  - solo adattare il momento in cui viene invocato
  - rendere esplicito che lavora dopo chiusura vera della sessione

- Dipendenze:
  - `batch-continuation.php`

- Rischi:
  - esecuzione troppo presto
  - lettura di sessione non ancora conclusa

- Come testare:
  - verificare che non venga eseguito finche' esiste delete pending o delete processing

### `includes/class-realestate-sync-email-report.php`

- Ruolo nel sistema:
  - report finale
  - snapshot
  - email

- Tipo intervento:
  - `DA MODIFICARE`

- Modifiche previste:
  - aggiungere lettura stats delete queue per `session_id`
  - distinguere:
    - delete legacy `deleted=1`
    - delete `missing_from_feed`
  - aggiornare subject/body solo dopo chiusura reale sessione

- Dipendenze:
  - `batch-continuation.php`
  - nuova delete queue
  - eventuali contatori salvati in progress option

- Rischi:
  - report con numeri incompleti
  - doppio conteggio tra queue import e queue delete

- Come testare:
  - costruzione report con sessione che ha delete pending
  - costruzione report con sessione delete conclusa
  - verificare numeri distinti per canale

### `includes/class-realestate-sync-tracking-manager.php`

- Ruolo nel sistema:
  - tracking properties/agencies
  - cleanup tracking on delete

- Tipo intervento:
  - `ESISTENTE (solo lettura, salvo micro-adattamenti se inevitabili)`

- Modifiche previste:
  - nessuna modifica iniziale
  - da usare come supporto indiretto, non come base detection

- Dipendenze:
  - `Deletion_Manager`
  - hook WordPress

- Rischi:
  - usare il tracking come fonte primaria sarebbe fragile

- Come testare:
  - verificare che il tracking continui a essere pulito quando la delete passa da WP hooks

### `includes/class-realestate-sync-attachment-cleanup.php`

- Ruolo nel sistema:
  - cleanup attachments in `before_delete_post`

- Tipo intervento:
  - `ESISTENTE (solo lettura)`

- Modifiche previste:
  - nessuna nella prima fase

- Dipendenze:
  - `Deletion_Manager`
  - WordPress hooks

- Rischi:
  - doppio cleanup attachment insieme a `Deletion_Manager`

- Come testare:
  - nei primi delete reali verificare che non restino media orfani e che non emergano warning anomali

### `admin/class-realestate-sync-admin.php`

- Ruolo nel sistema:
  - endpoint AJAX admin
  - monitor queue

- Tipo intervento:
  - `DA MODIFICARE`

- Modifiche previste:
  - estendere `handle_get_queue_stats()`
  - includere stato delete della sessione
  - esporre contatori backend gia' salvati:
    - delete pending
    - delete done
    - delete error
    - modalita' `dry_run|soft|live`
    - cap attivo
  - non derivare lato UI stati che non esistono ancora backend

- Dipendenze:
  - delete queue
  - progress option
  - widget monitor/admin JS

- Rischi:
  - UI che mostra sessione chiusa quando invece e' in delete

- Come testare:
  - chiamata AJAX durante `delete_pending` e `delete_processing`
  - verificare coerenza con contatori backend

### `admin/views/widgets/monitor-import.php`

- Ruolo nel sistema:
  - monitor ultimo import

- Tipo intervento:
  - `DA MODIFICARE`

- Modifiche previste:
  - aggiungere campi display fase sessione reale
  - mostrare stato delete
  - mostrare modalita' attiva
  - mostrare cap e contatori base

- Dipendenze:
  - `admin/class-realestate-sync-admin.php`
  - `admin/assets/admin.js`

- Rischi:
  - informazione incompleta all’operatore

- Come testare:
  - refresh widget in sessioni:
    - import_processing
    - delete_pending
    - delete_processing
    - delete_paused

### `admin/assets/admin.js`

- Ruolo nel sistema:
  - refresh widget monitor
  - rendering stato processo

- Tipo intervento:
  - `DA MODIFICARE`

- Modifiche previste:
  - leggere nuovi campi AJAX
  - non ridurre piu' tutto a `ATTIVO/CHIUSO`
  - distinguere chiaramente fasi delete

- Dipendenze:
  - `admin/class-realestate-sync-admin.php`
  - `monitor-import.php`

- Rischi:
  - falso senso di chiusura sessione

- Come testare:
  - refresh manuale widget con diversi stati restituiti dall’AJAX

### `includes/class-realestate-sync-delete-queue-manager.php`

- Ruolo nel sistema:
  - gestione CRUD nuova `realestate_delete_queue`

- Tipo intervento:
  - `DA AGGIUNGERE`

- Modifiche previste:
  - `create_table()`
  - schema allineato concettualmente a pattern e convenzioni di `RealEstate_Sync_Queue_Manager`
  - insert item
  - bulk insert sessione
  - fetch next pending
  - mark processing/done/error/skipped
  - stats per sessione
  - query esistenza pending
  - recovery item `processing` stantii
  - UNIQUE KEY su:
    - `(session_id, property_import_id, reason)`

- Requisito operativo:
  - un item delete in `processing` e' considerato stantio oltre una soglia esplicita
  - soglia iniziale consigliata:
    - `15 minuti`
  - al superamento soglia:
    - registrare evento
    - incrementare tentativo
    - riportare a `pending` oppure a `error` secondo la policy scelta
  - policy consigliata iniziale:
    - primo recovery: `pending`
    - oltre limite tentativi: `error`

- Dipendenze:
  - `realestate-sync.php`
  - `Batch_Orchestrator`
  - `batch-continuation.php`

- Rischi:
  - schema incompleto
  - mancata idempotenza per stessa sessione
  - item bloccati indefinitamente in `processing`

- Come testare:
  - test CRUD base su tabella
  - verifica UNIQUE KEY `(session_id, property_import_id, reason)`
  - simulare item con `updated_at` vecchio oltre soglia e verificare recovery

### `includes/class-realestate-sync-delete-batch-processor.php`

- Ruolo nel sistema:
  - worker asincrono 1 delete item per tick

- Tipo intervento:
  - `DA AGGIUNGERE`

- Modifiche previste:
  - fetch 1 item `pending`
  - mark `processing`
  - invocare `Deletion_Manager`
  - applicare contratto esplicito del delete singolo:
    - `success` -> queue `done`
    - `not_found` -> queue `skipped`
    - `error` -> queue `error`
  - rispettare cap e kill switch
  - restituire stats batch minime

- Dipendenze:
  - `Delete_Queue_Manager`
  - `Deletion_Manager`
  - `batch-continuation.php`

- Rischi:
  - lasciare item in `processing`
  - trattare male il caso `not_found`

- Come testare:
  - sessione con 2-3 item pending
  - un tick = 1 item processato
  - verificare mapping esito -> stato queue

### `docs/develop/2026-04-20-delete-missing-properties-blueprint-v2.md`

- Ruolo nel sistema:
  - source of truth progettuale

- Tipo intervento:
  - `ESISTENTE (solo lettura)`

- Modifiche previste:
  - nessuna, salvo note di allineamento a fine lavori

- Dipendenze:
  - nessuna runtime

- Rischi:
  - divergenza tra piano operativo e blueprint

- Come testare:
  - review finale allineamento doc/codice

## 3. Ordine di implementazione reale

### Step 1

- File coinvolti:
  - `realestate-sync.php`
  - `includes/class-realestate-sync-delete-queue-manager.php`

- Cosa viene fatto:
  - introdurre dependency e tabella nuova
  - aggiungere CRUD minimi delete queue

- Cosa NON deve ancora essere fatto:
  - nessuna detection
  - nessun worker delete

- Output atteso:
  - tabella disponibile e classe caricata

- Come verificare che e' ok:
  - plugin senza fatal
  - tabella esistente
  - test CRUD base riuscito

### Step 2

- File coinvolti:
  - `includes/class-realestate-sync-batch-orchestrator.php`
  - `includes/class-realestate-sync-delete-queue-manager.php`

- Cosa viene fatto:
  - raccogliere `feed_active_property_ids`
  - raccogliere blacklist `deleted=1`
  - costruire candidati missing
  - persisterli in queue

- Cosa NON deve ancora essere fatto:
  - nessuna delete reale
  - nessuna modifica a `batch-continuation.php`

- Output atteso:
  - sessione import crea record nella delete queue

- Come verificare che e' ok:
  - verificare prima nel codice reale dove avviene il filtro provincia
  - verificare che `feed_active_property_ids` venga raccolto solo dopo il filtro provincia
  - contatori coerenti
  - no duplicati
  - import queue invariata

### Step 3

- File coinvolti:
  - `includes/class-realestate-sync-batch-orchestrator.php`
  - `includes/class-realestate-sync-delete-queue-manager.php`

- Cosa viene fatto:
  - introdurre guardrail backend:
    - `dry_run`
    - kill switch
    - cap base
  - salvare modalita' e limiti nel backend/sessione
  - definire comportamento sicuro di default
  - definire il contratto runtime vincolante delle modalita'

- Cosa NON deve ancora essere fatto:
  - nessuna delete reale
  - nessun aggancio del worker in cron

- Output atteso:
  - backend pronto a impedire delete reale non controllata

- Come verificare che e' ok:
  - `dry_run` salvato e attivo
  - kill switch letto correttamente
  - cap disponibile lato backend
  - in `dry_run` nessuna chiamata runtime a `Deletion_Manager`

### Step 4

- File coinvolti:
  - `includes/class-realestate-sync-batch-orchestrator.php`
  - `includes/class-realestate-sync-delete-queue-manager.php`

- Cosa viene fatto:
  - introdurre stati e contatori backend
  - aggiungere requisito e meccanismo di recovery per item `processing` stantii

- Cosa NON deve ancora essere fatto:
  - nessuna UI admin
  - nessun worker delete agganciato al cron

- Output atteso:
  - stato sessione completo lato backend
  - policy stantii definita e testabile

- Come verificare che e' ok:
  - stati presenti nella progress option
  - simulazione item stantio oltre soglia con recovery corretto

### Step 5

- File coinvolti:
  - `admin/class-realestate-sync-admin.php`
  - `admin/views/widgets/monitor-import.php`
  - `admin/assets/admin.js`

- Cosa viene fatto:
  - esporre stati e contatori backend in admin/UI

- Cosa NON deve ancora essere fatto:
  - nessuna delete reale
  - nessuna modifica alla finalizzazione

- Output atteso:
  - monitor leggibile per sessioni con missing queue popolata

- Come verificare che e' ok:
  - UI mostra `delete_pending` senza ambiguita'
  - UI riflette modalita', cap e contatori backend

### Step 6

- File coinvolti:
  - `includes/class-realestate-sync-deletion-manager.php`
  - `includes/class-realestate-sync-delete-batch-processor.php`

- Cosa viene fatto:
  - rendere `Deletion_Manager` riusabile a item singolo
  - formalizzare contratto `success/not_found/error`
  - introdurre worker delete isolato

- Cosa NON deve ancora essere fatto:
  - nessun collegamento a `batch-continuation.php`

- Output atteso:
  - worker delete testabile in isolamento

- Come verificare che e' ok:
  - item esistente -> `success`
  - item assente -> `skipped`
  - item errore -> `error`

### Step 7

- File coinvolti:
  - `batch-continuation.php`
  - `includes/class-realestate-sync-delete-batch-processor.php`
  - `includes/class-realestate-sync-delete-queue-manager.php`

- Cosa viene fatto:
  - agganciare la fase delete al cron endpoint
  - processare 1 item per tick
  - rispettare `dry_run`, kill switch e cap
  - applicare recovery item stantii
  - applicare il blocco backend/runtime dei nuovi import quando la sessione e' in fase delete
  - impedire finalizzazione anticipata

- Cosa NON deve ancora essere fatto:
  - nessun refactor del report oltre il necessario

- Output atteso:
  - cron continua a lavorare anche dopo fine import queue

- Come verificare che e' ok:
  - sessione con delete queue pending non viene marcata completata
  - 1 solo delete item per tick
  - item stantio viene recuperato secondo policy
  - nuovo import schedulato bloccato se `realestate_sync_background_import_progress.status` e' in fase delete
  - nuovo import manuale admin bloccato se `realestate_sync_background_import_progress.status` e' in fase delete

### Step 8

- File coinvolti:
  - `batch-continuation.php`
  - `includes/class-realestate-sync-import-verifier.php`
  - `includes/class-realestate-sync-email-report.php`

- Cosa viene fatto:
  - spostare verifier/report alla vera fine
  - aggiungere stats delete al report

- Cosa NON deve ancora essere fatto:
  - nessuna unificazione `deleted=1`

- Output atteso:
  - report prodotto solo dopo chiusura completa sessione

- Come verificare che e' ok:
  - sessione con delete aperta non genera report finale
  - sessione chiusa genera report con conteggi delete

## 4. Checkpoint di sicurezza (produzione)

### Dopo Step 1

- Quando fermarsi:
  - appena creata la tabella e caricata la dependency

- Cosa verificare prima di andare avanti:
  - nessun fatal plugin
  - tabella realmente presente

- Segnali di errore da NON ignorare:
  - tabella non creata
  - errore in activation/bootstrap

### Dopo Step 2

- Quando fermarsi:
  - appena la detection persiste candidati senza delete reale

- Cosa verificare prima di andare avanti:
  - confermare nel codice reale dove avviene il filtro provincia
  - confermare che `feed_active_property_ids` venga popolato dopo quel filtro
  - candidati missing plausibili
  - nessun overlap con `deleted=1`

- Segnali di errore da NON ignorare:
  - raccolta ID prima del filtro provincia
  - picco anomalo candidati
  - candidati con `property_import_id` vuoto
  - import queue alterata

### Dopo Step 3

- Quando fermarsi:
  - appena i guardrail backend sono attivi

- Cosa verificare prima di andare avanti:
  - `dry_run` sia il default operativo
  - kill switch funzioni
  - cap sia leggibile dal backend
  - in `dry_run` nessuna delete reale sia possibile
  - in `dry_run` nessuna chiamata runtime a `Deletion_Manager` venga effettuata

- Segnali di errore da NON ignorare:
  - delete reale possibile senza guardrail
  - cap non applicabile
  - qualsiasi delete eseguita in `dry_run`

### Dopo Step 4

- Quando fermarsi:
  - appena stato backend e recovery stantii sono definiti

- Cosa verificare prima di andare avanti:
  - item `processing` oltre soglia riconosciuto come stantio
  - recovery coerente:
    - a `pending` entro limite tentativi
    - a `error` oltre limite

- Segnali di errore da NON ignorare:
  - item che restano indefinitamente in `processing`
  - recovery ripetuto senza progressione di tentativi

### Dopo Step 5

- Quando fermarsi:
  - appena il monitor rende visibile lo stato delete

- Cosa verificare prima di andare avanti:
  - l’operatore capisce se la sessione e' aperta o no

- Segnali di errore da NON ignorare:
  - UI che mostra `CHIUSO` con delete queue ancora pending

### Dopo Step 6

- Quando fermarsi:
  - appena il worker delete funziona in isolamento

- Cosa verificare prima di andare avanti:
  - casi `success/not_found/error`
  - mapping corretto verso gli stati queue

- Segnali di errore da NON ignorare:
  - cancellazioni doppie
  - `not_found` non marcato come `skipped`
  - attachments non coerenti

### Dopo Step 7

- Quando fermarsi:
  - appena il cron endpoint gestisce la delete

- Cosa verificare prima di andare avanti:
  - lock rilasciato sempre
  - 1 item per tick
  - recovery stantii attiva
  - blocco backend nuovo import attivo su stati delete

- Segnali di errore da NON ignorare:
  - sessione bloccata in `processing`
  - nuovo import partito durante delete
  - worker che ignora kill switch o cap
  - stato delete attivo ma import partito comunque da entry point backend

### Dopo Step 8

- Quando fermarsi:
  - appena la finalizzazione parte alla vera fine

- Cosa verificare prima di andare avanti:
  - verifier/report non anticipati

- Segnali di errore da NON ignorare:
  - report inviato con delete ancora aperta
  - numeri incoerenti tra queue e report

## 5. Dipendenze critiche

- `Batch_Orchestrator` dipende dalla nuova delete queue per la sola persistenza
- I guardrail backend devono esistere prima del worker delete
- Gli stati backend devono esistere prima dell’admin/UI
- Il blocco nuovo import durante delete dipende dal controllo su `realestate_sync_background_import_progress.status`
- `batch-continuation.php` dipende dall’esistenza del worker delete prima di poter spostare la finalizzazione
- `Email_Report` dipende dal nuovo punto di chiusura sessione
- la gestione stantii dipende da:
  - `updated_at`
  - policy tentativi
  - soglia temporale esplicita
- l’ordine e' fondamentale tra:
  - detection
  - guardrail
  - stati backend
  - UI
  - worker
  - finalizzazione

## 6. Parti ad alto rischio

### Delete effettiva

- Perche' e' rischiosa:
  - elimina post e media in produzione

- Come mitigare:
  - partire da `dry_run`
  - poi `soft`
  - cap basso
  - 1 item per tick
  - kill switch gia' disponibile prima dell’aggancio worker

### Finalizzazione sessione

- Perche' e' rischiosa:
  - oggi e' agganciata alla sola fine import queue

- Come mitigare:
  - non toccarla finche' la fase delete non e' osservabile
  - aggiungere stato esplicito e solo dopo spostare verifier/report

### Lock

- Perche' e' rischiosa:
  - un lock lasciato aperto blocca cron e sessioni

- Come mitigare:
  - `try/finally`
  - recovery stale processing
  - log chiari sui passaggi lock acquire/release

### Media cleanup

- Perche' e' rischiosa:
  - esistono due livelli di cleanup:
    - `Deletion_Manager`
    - `before_delete_post`

- Come mitigare:
  - non rifattorizzare subito
  - trattarlo come comportamento noto accettato nel primo rollout
  - verificare primi delete reali con campione piccolo
  - considerare che i contatori `disk_space_freed` possono risultare sottostimati o incoerenti per doppio tentativo di cleanup

## 7. Cosa NON fare in questa fase

- non unificare subito il canale legacy `deleted=1`
- non rifattorizzare la import queue esistente
- non correggere ora tutti i disallineamenti storici della queue attuale
- non spostare logica nel tracking manager
- non introdurre parallelismo tra import e delete
- non cambiare il comportamento del writer/import engine se non strettamente necessario
- non rifare la UI admin oltre il minimo per osservabilita'
- non ottimizzare ora media cleanup se prima non emerge un problema reale

## 8. Mini test plan per step

### Step 1

- Cosa verificare:
  - tabella e CRUD

- Come verificarlo velocemente:
  - controllo DB + inserimento/lettura manuale

- Cosa deve risultare vero:
  - schema pronto e plugin stabile
  - UNIQUE KEY `(session_id, property_import_id, reason)` presente

### Step 2

- Cosa verificare:
  - detection missing e persistenza queue

- Come verificarlo velocemente:
  - eseguire import `dry_run`
  - confrontare count candidati con campione feed/WP

- Cosa deve risultare vero:
  - `feed_active_property_ids` raccolto dopo il filtro provincia
  - i candidati sono plausibili e deduplicati

### Step 3

- Cosa verificare:
  - guardrail backend

- Come verificarlo velocemente:
  - controllare modalita', kill switch e cap salvati lato backend

- Cosa deve risultare vero:
  - nessuna delete reale possibile senza controllo
  - in `dry_run` la queue viene popolata e i contatori sono presenti
  - in `dry_run` nessuna delete viene eseguita
  - in `dry_run` nessuna chiamata runtime a `Deletion_Manager` viene effettuata

### Step 4

- Cosa verificare:
  - stati backend e recovery stantii

- Come verificarlo velocemente:
  - simulare item `processing` oltre soglia

- Cosa deve risultare vero:
  - l’item non resta bloccato indefinitamente

### Step 5

- Cosa verificare:
  - stato sessione visibile

- Come verificarlo velocemente:
  - refresh widget admin

- Cosa deve risultare vero:
  - non esiste piu' ambiguita' tra import finito e sessione finita davvero

### Step 6

- Cosa verificare:
  - worker delete isolato

- Come verificarlo velocemente:
  - test su item mock/reali controllati

- Cosa deve risultare vero:
  - esito coerente per `success/not_found/error`
  - `not_found` mappato in modo deterministico a `skipped`

### Step 7

- Cosa verificare:
  - cron delete 1 item per tick
  - cap
  - kill switch
  - recovery stantii
  - blocco import durante fase delete

- Come verificarlo velocemente:
  - due esecuzioni cron consecutive
  - tentare avvio import durante `delete_pending`
  - tentare avvio import durante `delete_processing`

- Cosa deve risultare vero:
  - un solo item delete per tick
  - sessione non finalizzata troppo presto
  - item stantio recuperato secondo policy
  - l'import non parte
  - lo stato resta invariato
  - il log del blocco e' presente

### Step 8

- Cosa verificare:
  - finalizzazione post import + delete

- Come verificarlo velocemente:
  - sessione con delete queue non vuota

- Cosa deve risultare vero:
  - verifier/report solo a fine completa

## 9. Output finale sintetico

- Step piu' sicuro da iniziare subito:
  - Step 1, nuova tabella + `Delete_Queue_Manager`

- Step piu' rischioso:
  - Step 7, aggancio del worker delete in `batch-continuation.php`

- Prerequisiti minimi prima di toccare il codice:
  - conferma che il rollout iniziale resta `dry_run`
  - cap iniziale definito per `soft`
  - criterio manuale di validazione candidati missing
  - soglia iniziale per item `processing` stantii confermata
  - policy iniziale `not_found -> skipped` confermata

- Suggerimento ordine reale di lavoro:
  1. Step 1
  2. Step 2
  3. pausa verifica
  4. Step 3
  5. pausa verifica
  6. Step 4
  7. pausa verifica
  8. Step 5
  9. pausa verifica
  10. Step 6
  11. pausa verifica forte
  12. Step 7
  13. pausa verifica forte
  14. Step 8

## Differenze principali rispetto al piano originale

- guardrail backend anticipati prima dell’aggancio del worker delete
- stati e contatori backend anticipati prima della UI admin
- recovery item `processing` stantii resa esplicita come requisito operativo
- contratto del delete singolo formalizzato in `Deletion_Manager`:
  - `success`
  - `not_found`
  - `error`
- checkpoint di verifica aggiornati per includere:
  - stantii
  - cap
  - kill switch
  - mapping esito delete -> stato queue

## Ordine definitivo degli step

1. nuova tabella + `Delete_Queue_Manager`
2. detection + persistenza queue
3. guardrail backend: `dry_run`, kill switch, cap
4. stati e contatori backend + recovery stantii
5. esposizione admin/UI
6. contratto `Deletion_Manager` + worker delete isolato
7. aggancio worker in `batch-continuation.php`
8. finalizzazione post import + delete

## Punto piu' rischioso confermato

- Resta lo Step 7: aggancio del worker delete in `batch-continuation.php`

- contratto operativo vincolante aggiunto per `dry_run`, `soft` e `live`
- blocco nuovo import formalizzato con sorgente di verita' su `realestate_sync_background_import_progress.status`
- `dry_run` definito come modalita' con detection e queue obbligatorie ma delete reale vietata
- verifiche esplicite aggiunte per blocco import durante `delete_pending` e `delete_processing`
- Step 3 e Step 7 aggiornati con comportamento runtime deterministico e checkpoint coerenti
- mini test plan esteso per garantire assenza di delete runtime in `dry_run`
