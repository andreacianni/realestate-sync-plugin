# RealEstate Sync - Human Overview

## Scope

Questo documento è una sintesi operativa del plugin costruita dalla baseline tecnica del 2026-04-18. Serve per orientarsi velocemente senza perdere aderenza al codice reale.

## Quando usarlo

- quando devi capire rapidamente da dove parte un flusso
- quando devi decidere quale file aprire per una modifica
- quando devi fare debug senza rileggere subito tutta la mappa tecnica
- quando devi distinguere tra percorso batch moderno e motore property storico

## Come usarlo per sviluppare

- parti dagli entrypoint
- individua il flusso coinvolto
- scendi poi nel file responsabile del livello corretto: orchestration, mapping, writer API, queue o admin
- usa la mappa tecnica completa se serve dettaglio su hook, catene di chiamata o dipendenze indirette

# RealEstate Sync - Plugin Overview

## Entrypoint

- Il plugin ha due strati principali:
  - bootstrap WordPress in `realestate-sync.php`
  - runtime import in due livelli: orchestration batch + motore property storico
- L’entrypoint WordPress è `realestate-sync.php`.
- L’entrypoint esterno per continuazione batch è `batch-continuation.php`.
- La superficie admin vive in `admin/class-realestate-sync-admin.php`, renderizzata da `admin/views/dashboard-modular.php` e pilotata da `admin/assets/admin.js`.

## Flussi

- Il flusso operativo standard oggi parte quasi sempre dal batch:
  - admin manual import
  - upload XML da admin
  - scheduled import via `Cron_Manager`
  - scheduled import via `batch-continuation.php`
- Tutti questi rami convergono in `RealEstate_Sync_Batch_Orchestrator::process_xml_batch()`.

- Cosa fa il batch orchestrator:
  - apre una `session_id`
  - carica XML
  - filtra annunci e agenzie per province abilitate TN/BZ
  - separa gli elementi con `deleted=1`
  - chiama `Deletion_Manager` per le cancellazioni
  - pre-calcola hash con `Tracking_Manager`
  - mette in queue solo ciò che è nuovo o cambiato
  - salva dati pre-parsati in option
  - esegue subito il primo batch

- Il batch processor non reimplementa l’import property.
- Per le property delega a `Import_Engine::process_single_property()`.
- Quindi la coesistenza è reale:
  - `Batch_Orchestrator` governa sessione, filtro, queue e continuazione
  - `Import_Engine` resta il core di mapping/action/create-update delle property

- Per capire l’import property bisogna seguire questa catena:
  - `Batch_Processor::process_property()`
  - `Import_Engine::process_single_property()`
  - `Property_Mapper`
  - `WP_Importer_API::process_property()`
  - `WPResidence_API_Writer::create_property()` / `update_property()`

- Per capire le agency bisogna seguire:
  - `Batch_Processor::process_agency()`
  - `Agency_Manager::import_agencies()`
  - `WPResidence_Agency_API_Writer`

- Il cron ha tre pezzi da non confondere:
  - `Cron_Manager` programma l’hook WP `realestate_sync_daily_import`
  - `Cron_Manager::execute_daily_import()` avvia import batch
  - `batch-continuation.php` continua i batch ogni minuto ed è anche capace di avviare import schedulati da solo
- Esiste anche `RealEstate_Sync::run_scheduled_import()` che usa ancora il vecchio percorso `Import_Engine::execute_chunked_import()`.

## Dove intervenire

- Dove modificare un import:
  - download feed e acquisizione file: `XML_Downloader`
  - filtro TN/BZ, queue, deletion, continuazione: `Batch_Orchestrator`
  - retry, timeout, stale items: `Batch_Processor`
  - mapping business XML -> WPResidence: `Property_Mapper`
  - decisione insert/update/skip: `Import_Engine` + `Tracking_Manager`
  - scrittura property via API: `WP_Importer_API` e `WPResidence_API_Writer`
  - scrittura agency: `Agency_Manager`

- Dove aggiungere feature:
  - nuova action admin o tool operativo: `RealEstate_Sync_Admin` + widget JS
  - nuova regola di mapping: `Property_Mapper`
  - nuova semantica di queue/sessione: `Batch_Orchestrator` / `Batch_Processor` / `Queue_Manager`
  - nuova integrazione API property/agency: writer API dedicati
  - nuova logica geografica: `ISTAT_Lookup` + `data/istat-lookup-tn-bz.php`

## Debug

- Dove debuggare problemi:
  - log file e log AJAX: `Logger`
  - trace di sessione batch: `Debug_Tracker`
  - stato queue e reset manuali: admin tools + `Queue_Manager`
  - verifica finale import: `Import_Verifier`
  - report email fine sessione: `Email_Report`

## Note architetturali

- Punti da tenere a mente:
  - `config/field-mapping.php` non è la fonte primaria del mapping runtime
  - il mapping vero è soprattutto nel codice di `Property_Mapper`
  - il batch è il flusso moderno, ma `Import_Engine` non è morto: è ancora nel cuore del ramo property
  - cron WP e cron esterno convivono, quindi i problemi di scheduling vanno letti su entrambi
  - ci sono handler admin legacy/test che usano ancora `execute_chunked_import()` direttamente

- File da aprire per orientarsi in fretta:
  - `realestate-sync.php`
  - `batch-continuation.php`
  - `admin/class-realestate-sync-admin.php`
  - `includes/class-realestate-sync-batch-orchestrator.php`
  - `includes/class-realestate-sync-batch-processor.php`
  - `includes/class-realestate-sync-import-engine.php`
  - `includes/class-realestate-sync-property-mapper.php`
  - `includes/class-realestate-sync-wp-importer-api.php`
  - `includes/class-realestate-sync-wpresidence-api-writer.php`
  - `includes/class-realestate-sync-tracking-manager.php`
