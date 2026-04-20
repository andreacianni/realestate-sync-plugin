# Blueprint V2 Operativa - Delete `missing_from_feed`

> Tipo documento: OPERATIVO  
> Base: codice reale del repository al 2026-04-20  
> Scopo: documento direttamente usabile per sviluppo step-by-step

## 1. Mappa del sistema attuale

### 1.1 Flusso reale oggi

1. Avvio import
- `includes/class-realestate-sync-batch-orchestrator.php`
- `RealEstate_Sync_Batch_Orchestrator::process_xml_batch()`
- Carica XML, applica filtro province TN/BZ, separa:
  - agenzie attive
  - immobili attivi
  - immobili `deleted=1`
  - agenzie `deleted=1`

2. Delete legacy immediata
- `includes/class-realestate-sync-batch-orchestrator.php`
- STEP `1b`
- `deleted=1` viene gestito subito, in modo sincrono, tramite `RealEstate_Sync_Deletion_Manager`
- Non passa da queue separata

3. Creazione import queue
- `includes/class-realestate-sync-queue-manager.php`
- Tabella: `{$wpdb->prefix}realestate_import_queue`
- In queue entrano solo agenzie e immobili attivi che risultano changed oppure forzati

4. Persistenza dati batch
- `Batch_Orchestrator` salva su option `realestate_sync_batch_data_{session_id}`
- Contiene:
  - `agencies`
  - `properties`

5. Primo batch immediato
- `includes/class-realestate-sync-batch-processor.php`
- `RealEstate_Sync_Batch_Processor::process_next_batch()`
- Worker reale: `ITEMS_PER_BATCH = 2`

6. Continuazione asincrona
- `batch-continuation.php`
- Cron server-side ogni minuto
- Cerca sessione con item `pending` nella import queue
- Applica lock globale `realestate_sync_processing_lock`
- Processa batch successivi fino a esaurimento `pending`

7. Finalizzazione attuale
- `batch-continuation.php`
- Quando `process_next_batch()` ritorna `complete=true`:
  - aggiorna `realestate_sync_background_import_progress`
  - esegue `RealEstate_Sync_Import_Verifier::verify_session($session_id)`
  - costruisce report con `RealEstate_Sync_Email_Report`
  - invia email

### 1.2 Risoluzione `property_import_id -> wp_post_id`

#### ESISTENTE
- Meta primaria post property: `property_import_id`
- Scritta da:
  - `includes/class-realestate-sync-property-mapper.php`
  - `includes/class-realestate-sync-wp-importer-api.php`
- Lookup post esistente via meta:
  - `RealEstate_Sync_WP_Importer_API::find_existing_property_strict()`
  - `RealEstate_Sync_Self_Healing_Manager::find_post_by_import_id_strict()`
  - `RealEstate_Sync_Deletion_Manager::find_post_by_property_id()`

#### Implicazione
- La chiave logica reale per detection e delete e' `property_import_id`
- Il tracking DB aiuta, ma non e' la fonte primaria piu' affidabile

### 1.3 Lock e progress state oggi

#### ESISTENTE
- Lock globale di tick:
  - transient `realestate_sync_processing_lock`
  - usato in `batch-continuation.php`
- Lock per singolo immobile:
  - transient `realestate_sync_import_lock_{property_import_id}`
  - usato in `RealEstate_Sync_WP_Importer_API::process_property()`
- Progress option globale:
  - `realestate_sync_background_import_progress`
  - non per-sessione multipla

#### Punto critico
- L'architettura attuale assume di fatto una sola sessione attiva per volta

### 1.4 Punti critici gia' esistenti

#### ESISTENTE
- `deleted=1` e' sincrono e anticipato rispetto al resto del batch
- `batch-continuation.php` considera concluso il lavoro quando non ci sono piu' `pending` nella import queue
- verifier e report partono subito dopo la fine import queue
- `Deletion_Manager` cancella attachments esplicitamente
- `Attachment_Cleanup` cancella attachments anche via hook `before_delete_post`
- `Tracking_Manager::cleanup_tracking_on_delete()` rimuove tracking in `before_delete_post`

#### Rischi gia' presenti
- doppio cleanup media durante delete hard
- stato finale troppo anticipato per introdurre una seconda fase di delete
- option progress globale fragile se si tentano piu' sessioni
- disallineamento schema/uso sulla queue attuale:
  - il codice usa campi come `wp_post_id` e `processed_at`
  - lo schema creato in `Queue_Manager::create_table()` non li dichiara

## 2. Punti di integrazione reali

### 2.1 Detection missing nel feed

#### DA MODIFICARE
- File: `includes/class-realestate-sync-batch-orchestrator.php`
- Classe: `RealEstate_Sync_Batch_Orchestrator`
- Metodo: `process_xml_batch()`
- Quando:
  - durante la scansione XML, dopo il filtro provincia
  - prima della creazione della import queue
- Perche' qui:
  - e' l'unico punto in cui il feed completo e' gia' in memoria
  - il filtro TN/BZ e' gia' implementato qui
  - evita una seconda lettura XML

### 2.2 Persistenza delete queue

#### DA AGGIUNGERE
- File nuovo suggerito: `includes/class-realestate-sync-delete-queue-manager.php`
- Classe nuova: `RealEstate_Sync_Delete_Queue_Manager`
- Quando:
  - nella stessa sessione dell'orchestrator, subito dopo detection missing
- Perche' qui:
  - il delete deve restare separato e asincrono
  - la import queue attuale e' orientata a `agency/property`, non a delete

### 2.3 Trigger fase delete

#### DA MODIFICARE
- File: `batch-continuation.php`
- Area:
  - ramo dopo `process_next_batch()`
  - ramo `ALL BATCHES COMPLETE`
- Quando:
  - quando la import queue della sessione non ha piu' item `pending`
- Perche' qui:
  - e' gia' il punto di passaggio tra processing e finalizzazione
  - consente di inserire una fase `delete_pending/delete_processing` senza creare un secondo endpoint

### 2.4 Worker delete

#### DA AGGIUNGERE
- File nuovo suggerito: `includes/class-realestate-sync-delete-batch-processor.php`
- Classe nuova: `RealEstate_Sync_Delete_Batch_Processor`
- Quando:
  - richiamato da `batch-continuation.php` dopo fine import queue
- Perche' qui:
  - deve restare 1 item per tick
  - deve riusare il lock globale gia' esistente
  - tiene separata la responsabilita' dal `Batch_Processor` attuale

### 2.5 Hard delete effettiva

#### DA MODIFICARE
- File: `includes/class-realestate-sync-deletion-manager.php`
- Classe: `RealEstate_Sync_Deletion_Manager`
- Metodi:
  - `handle_deleted_properties()`
  - `delete_property()`
- Quando:
  - riuso dal nuovo worker delete
- Perche' qui:
  - contiene gia' il lookup via `property_import_id`
  - contiene gia' l'hard delete del post
  - va esteso con entry point singolo riusabile per queue item

### 2.6 Finalizzazione, verifier, email

#### DA MODIFICARE
- File: `batch-continuation.php`
- File: `includes/class-realestate-sync-import-verifier.php`
- File: `includes/class-realestate-sync-email-report.php`
- Quando:
  - solo dopo import queue + delete queue completate
- Perche' qui:
  - oggi la finalizzazione parte troppo presto
  - il report deve includere i numeri delete per canale

### 2.7 Creazione tabella

#### DA MODIFICARE
- File: `realestate-sync.php`
- Metodo: `RealEstate_Sync::create_database_tables()`
- Quando:
  - activation/bootstrap
- Perche' qui:
  - e' il punto gia' usato per queue e tracking tables

## 3. Modello dati

### 3.1 Come sono rappresentati oggi gli immobili

#### ESISTENTE
- Post type: `estate_property`
- Meta chiave:
  - `property_import_id`
  - `property_import_session`
  - `property_last_sync`
- Tracking table:
  - `{$wpdb->prefix}realestate_sync_tracking`
  - PK logica: `property_id`
  - stato: `active|inactive|deleted|error`

### 3.2 Come viene usato `property_import_id`

#### ESISTENTE
- Identifica univocamente il post plugin-managed
- Serve per:
  - self-healing
  - duplicate resolution
  - update/import idempotente
  - delete legacy

#### Decisione V2
- La detection `missing_from_feed` confronta:
  - set feed attivo corrente
  - set post WordPress con meta `property_import_id`
- Non usa tracking come fonte primaria di inventario

### 3.3 Nuova delete queue proposta

#### DA AGGIUNGERE
- Tabella: `{$wpdb->prefix}realestate_delete_queue`

#### Schema proposto
- `id` bigint unsigned PK auto_increment
- `session_id` varchar(100) not null
- `property_import_id` varchar(100) not null
- `wp_post_id` bigint unsigned null
- `reason` varchar(50) not null
- `status` varchar(20) not null default `pending`
- `attempts` int not null default `0`
- `last_error` text null
- `source_channel` varchar(30) not null
- `created_at` datetime default current_timestamp
- `updated_at` datetime default current_timestamp on update current_timestamp
- `processed_at` datetime null

#### Indici minimi
- key `session_id`
- key `status`
- key `reason`
- unique key `(session_id, property_import_id, reason)`

#### Valori iniziali
- `reason`:
  - `missing_from_feed`
- `source_channel`:
  - `missing_from_feed`

#### Stati queue
- `pending`
- `processing`
- `done`
- `error`
- `skipped`

#### Nota compatibilita'
- `skipped` e' utile per no-op sicure:
  - post gia' assente
  - item invalidato manualmente
- Se si vuole minimizzare ancora di piu', puo' essere collassato in `done`

### 3.4 Compatibilita' con WP e codice esistente

#### Compatibile
- usa chiave gia' presente sui post: `property_import_id`
- usa `wp_post_id` solo come cache operativa, non come fonte primaria
- segue pattern session-based gia' esistente

#### Ambiguita' da validare
- se la delete queue deve includere da subito anche il canale legacy `deleted=1`
- raccomandazione V2:
  - no nel primo step
  - mantenere `deleted=1` separato per ridurre refactor

## 4. Flusso target dettagliato

### 4.1 Step 1 - scansione feed

#### ESISTENTE
- L'orchestrator scansiona tutto il feed
- Applica filtro TN/BZ
- Separa attivi e `deleted=1`

#### DA MODIFICARE
- Durante la scansione costruire anche:
  - `feed_active_property_ids`
  - `feed_deleted_property_ids`

### 4.2 Step 2 - raccolta ID

#### DA MODIFICARE
- Salvare `property_import_id` attivi in un set in-memory deduplicato
- Escludere record fuori provincia prima di aggiungerli al set

### 4.3 Step 3 - gestione `deleted=1`

#### ESISTENTE
- Delete legacy immediata in orchestrator

#### Decisione V2
- Lasciare invariato nel primo rollout
- Usare il set `feed_deleted_property_ids` come blacklist per i missing

### 4.4 Step 4 - costruzione missing

#### DA AGGIUNGERE
- Query WP per ottenere i post plugin-managed:
  - `estate_property`
  - meta `property_import_id`
  - esclusi `trash`, `auto-draft`, `inherit`
- Candidati missing:
  - presenti in WP
  - assenti in `feed_active_property_ids`
  - non inclusi in `feed_deleted_property_ids`

#### Dove
- `Batch_Orchestrator::process_xml_batch()`

### 4.5 Step 5 - persistenza queue

#### DA AGGIUNGERE
- Persistire i candidati missing in `realestate_delete_queue`
- Salvare `wp_post_id` se gia' noto dal lookup
- Non cancellare ancora nulla

### 4.6 Step 6 - trigger fine import

#### DA MODIFICARE
- In `batch-continuation.php`, quando import queue finisce:
  - se delete queue sessione ha `pending`, passare a fase delete
  - se no, finalizzare come oggi

### 4.7 Step 7 - fase delete

#### DA AGGIUNGERE
- Processare 1 queue item per tick
- Ordine:
  1. acquisire lock globale
  2. prendere 1 item `pending`
  3. marcare `processing`
  4. eseguire delete via `Deletion_Manager`
  5. marcare `done|error|skipped`
  6. aggiornare progress option
  7. rilasciare lock

### 4.8 Step 8 - finalizzazione

#### DA MODIFICARE
- Finalizzare solo quando:
  - import queue: nessun `pending`
  - delete queue: nessun `pending` e nessun `processing`
- Poi:
  - verifier
  - report
  - email
  - status finale sessione

## 5. Macchina a stati reale

### 5.1 Stati esistenti

#### ESISTENTE
- Queue import item:
  - `pending`
  - `processing`
  - `done`
  - `error`
- Progress option:
  - oggi usa soprattutto `processing` / `completed`

### 5.2 Stati nuovi necessari

#### DA MODIFICARE
- `import_processing`
- `delete_pending`
- `delete_processing`
- `delete_paused`
- `completed`
- `completed_with_errors`

### 5.3 Transizioni valide

1. `import_processing -> delete_pending`
- import queue finita
- delete queue con item pending

2. `import_processing -> completed`
- import queue finita
- delete queue vuota

3. `delete_pending -> delete_processing`
- primo tick delete parte

4. `delete_processing -> delete_paused`
- raggiunto cap o kill switch

5. `delete_processing -> completed`
- delete queue finita senza errori residui rilevanti

6. `delete_processing -> completed_with_errors`
- delete queue finita ma restano item `error`

### 5.4 Problemi nello stato attuale

#### ESISTENTE
- il monitor admin riduce di fatto tutto a `ATTIVO/CHIUSO`
- `handle_get_queue_stats()` legge solo la import queue
- la progress option e' globale, non storicizzata per piu' sessioni

## 6. Failure modes e rischi reali

### 6.1 Race condition tra import e delete
- Causa:
  - `batch-continuation.php` oggi non conosce una fase delete distinta
- Impatto:
  - finalizzazione anticipata o overlap con nuovo import schedulato
- Mitigazione proposta:
  - introdurre stati delete espliciti
  - impedire nuovi import se esiste sessione in `delete_pending|delete_processing`

### 6.2 Doppie delete
- Causa:
  - stesso record in `deleted=1` e in `missing_from_feed`
- Impatto:
  - log rumorosi, doppio passaggio, potenziali side effect hook
- Mitigazione proposta:
  - blacklist `feed_deleted_property_ids` prima di costruire/persistire i missing

### 6.3 Mismatch ID
- Causa:
  - dati storici senza tracking coerente
  - duplicati post con stesso `property_import_id`
- Impatto:
  - delete del post sbagliato o mancata delete
- Mitigazione proposta:
  - usare lookup diretto su meta `property_import_id`
  - ordinamento deterministico `ORDER BY p.ID ASC`
  - loggare collisioni/multipli come anomalia da review

### 6.4 Lock non rilasciati
- Causa:
  - exception nel worker
- Impatto:
  - stallo del cron
- Mitigazione proposta:
  - mantenere `try/finally` sul lock globale
  - recovery di item `processing` stantii anche nella delete queue

### 6.5 Timeout
- Causa:
  - delete di post con molte immagini
- Impatto:
  - item bloccati in `processing`
- Mitigazione proposta:
  - 1 delete per tick
  - stale reset su `processing`
  - cap per sessione/tick

### 6.6 Effetti su media
- Causa:
  - `Deletion_Manager` cancella attachments
  - `Attachment_Cleanup` cancella attachments via hook
- Impatto:
  - doppio tentativo delete attachment
- Mitigazione proposta:
  - non duplicare nuova logica media
  - validare se lasciare cleanup solo a `Deletion_Manager` oppure solo agli hook in un passo successivo
  - nel rollout V2 non refactorare, ma tracciare come rischio noto

### 6.7 Hook WordPress
- Causa:
  - `before_delete_post` modifica tracking e media
- Impatto:
  - side effect non immediatamente visibili nel report
- Mitigazione proposta:
  - mantenere delete via API WP standard (`wp_delete_post`, `wp_delete_attachment`)
  - usare cap basso iniziale

### 6.8 Stato/report incoerente
- Causa:
  - report attuale legge solo import queue/verifier
- Impatto:
  - numeri finali incompleti
- Mitigazione proposta:
  - spostare report dopo completamento delete queue
  - aggiungere stats dedicate per canale delete

## 7. Guardrail produzione

### 7.1 Dry run

#### DA AGGIUNGERE
- option/setting consigliata:
  - `realestate_sync_missing_delete_mode = dry_run|soft|live`
- In `dry_run`:
  - detection e persistenza queue si'
  - esecuzione delete no'

### 7.2 Soft activation

#### DA AGGIUNGERE
- modalita' `soft`
- delete reale abilitata
- cap basso obbligatorio

### 7.3 Cap

#### DA AGGIUNGERE
- opzione consigliata:
  - `realestate_sync_missing_delete_cap_per_session`
- comportamento:
  - fermare la fase delete al raggiungimento del cap
  - lasciare sessione in `delete_paused`

### 7.4 Stop conditions

#### DA AGGIUNGERE
- stop se:
  - candidati missing > soglia massima
  - percentuale missing anomala rispetto ai post importati
  - errori delete oltre soglia

### 7.5 Kill switch

#### DA AGGIUNGERE
- opzione booleana:
  - `realestate_sync_missing_delete_enabled`
- se `false`:
  - detection puo' continuare
  - worker delete non parte

### 7.6 Compatibilita' con codice attuale

#### Compatibile
- le guardie possono vivere in `batch-continuation.php` e nell'orchestrator
- non richiedono refactor del writer/import engine

## 8. Piano di sviluppo a step

### Step 1
- Implementare manager e tabella `realestate_delete_queue`
- File coinvolti:
  - `realestate-sync.php`
  - nuovo `includes/class-realestate-sync-delete-queue-manager.php`
- Rischio: basso
- Verifica:
  - tabella creata
  - CRUD base per sessione/stato

### Step 2
- Estendere orchestrator per costruire `feed_active_property_ids` e blacklist `deleted=1`
- File coinvolti:
  - `includes/class-realestate-sync-batch-orchestrator.php`
- Rischio: medio
- Verifica:
  - counts coerenti
  - nessun impatto su queue import attuale

### Step 3
- Implementare detection missing e persistenza queue in `dry_run`
- File coinvolti:
  - orchestrator
  - delete queue manager
- Rischio: medio
- Verifica:
  - campione `property_import_id -> wp_post_id`
  - nessuna delete reale

### Step 4
- Estendere progress state con fasi delete
- File coinvolti:
  - orchestrator
  - `batch-continuation.php`
  - eventuale UI admin
- Rischio: medio
- Verifica:
  - transizioni di stato corrette

### Step 5
- Implementare worker delete 1 item per tick
- File coinvolti:
  - nuovo delete batch processor
  - `batch-continuation.php`
  - `includes/class-realestate-sync-deletion-manager.php`
- Rischio: alto
- Verifica:
  - 1 delete per tick
  - lock rilasciato sempre

### Step 6
- Aggiungere guardrail `dry_run|soft|live`, cap, kill switch
- File coinvolti:
  - orchestrator
  - batch continuation
  - eventuali settings/admin
- Rischio: medio
- Verifica:
  - pause e stop coerenti

### Step 7
- Spostare verifier/report alla vera fine sessione
- File coinvolti:
  - `batch-continuation.php`
  - `includes/class-realestate-sync-import-verifier.php`
  - `includes/class-realestate-sync-email-report.php`
- Rischio: medio
- Verifica:
  - report include delete stats
  - nessuna finalizzazione anticipata

### Step 8
- Adeguare monitor/admin per fase delete
- File coinvolti:
  - `admin/class-realestate-sync-admin.php`
  - `admin/views/widgets/monitor-import.php`
  - `admin/assets/admin.js`
- Rischio: basso
- Verifica:
  - monitor mostra `delete_pending|delete_processing|delete_paused`

## 9. Criteri di accettazione

### 9.1 Detection
- ogni candidato missing ha `property_import_id`
- nessun candidato fuori provincia entra nel set feed
- nessun record in `deleted=1` entra anche nei missing

### 9.2 Queue
- ogni `(session_id, property_import_id, reason)` e' unico
- la persistenza e' idempotente nella stessa sessione

### 9.3 Worker delete
- processa esattamente 1 item per tick
- tratta post gia' assente come no-op non bloccante
- non lascia item in `processing` indefinito

### 9.4 Finalizzazione
- verifier e report non partono prima della fine delete queue
- `completed` significa davvero import + delete concluse

### 9.5 Manual checks
- campione di almeno 20 missing in dry run
- verifica manuale di mapping `property_import_id -> wp_post_id`
- verifica manuale di attachments su primi delete reali

## 10. Sezione finale sintetica

### 10.1 Livello di confidenza
- Alto sulla collocazione architetturale:
  - detection in orchestrator
  - trigger/worker in `batch-continuation.php`
  - hard delete via `Deletion_Manager`
- Medio su due punti operativi:
  - gestione doppio cleanup attachments
  - adeguamento UI/admin allo stato delete

### 10.2 Punti incerti da validare
- Se mantenere `deleted=1` legacy sincrono anche nel rollout iniziale
- Se introdurre stato `skipped` in delete queue o collassarlo in `done`
- Se il monitor admin deve leggere anche la delete queue o limitarsi alla progress option

### 10.3 Rischi principali prima della prima attivazione
- volume missing piu' alto del previsto
- mapping storici incoerenti su alcuni `property_import_id`
- side effect media/hook su delete hard
- avvio di nuovo import mentre la sessione precedente e' ancora in fase delete

## Sintesi finale

### Pronto per essere sviluppato subito
- schema target della delete queue
- punto di detection nel `Batch_Orchestrator`
- punto di trigger in `batch-continuation.php`
- riuso di `Deletion_Manager` come motore delete
- macchina a stati minima
- piano a step con rollout `dry_run -> soft -> live`

### Da chiarire prima
- scelta definitiva sul mantenimento del ramo legacy `deleted=1` nel primo rollout
- policy iniziale di cap e soglie stop
- trattamento del doppio cleanup attachments come rischio accettato o come fix da anticipare
