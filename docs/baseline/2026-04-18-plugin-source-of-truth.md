# RealEstate Sync - Plugin Source of Truth

## Scope

Questo documento è la mappa tecnica baseline del plugin `realestate-sync-plugin` ricostruita dal codice reale del branch locale `main` alla data del 2026-04-18.

## Fonte di verità

- Fonte primaria: codice PHP, asset admin, config e data del repository locale
- Fonte esclusa come primaria: documentazione in `docs/`
- Principio operativo: se codice e documentazione storica divergono, prevale il codice

## Cosa copre

- bootstrap del plugin
- superficie admin
- import manuale
- import schedulato
- batch processing e continuazione
- queue e tracking
- parsing XML e mapping
- scrittura dati su WordPress / WPResidence API
- media / immagini
- cleanup / deletion
- logging / debug
- configurazione e lookup data
- differenze tra flusso batch e motore legacy ancora attivo

## Cosa NON copre

- runbook operativo di deploy
- comportamento del server di produzione non verificabile dal repository
- validazione dello schema DB reale già presente sull’istanza
- qualità o correttezza della documentazione storica in `docs/`
- test funzionali end-to-end eseguiti contro WordPress/WPResidence reale

## Come usarlo

- usarlo come punto di partenza quando serve capire da dove parte un flusso
- usarlo per identificare rapidamente file, classi e punti di ingresso da modificare
- usarlo insieme al codice, non al posto del codice
- usarlo come baseline per valutare se i documenti storici in `docs/` sono ancora affidabili

# RealEstate Sync - Technical Flow Map

Fonte: analisi del codice del branch `main` locale al 2026-04-18.  
Vincolo: questa mappa usa il codice come fonte di verità, non `docs/`.

## 1. Bootstrap plugin

- Scopo
  - Caricare classi/configurazione, registrare hook WP, inizializzare singleton/plugin services, creare tabelle in activation.
- Entry point
  - `realestate-sync.php`
  - `realestate_sync_init()`
  - `RealEstate_Sync::__construct()`
  - `RealEstate_Sync::init_plugin()`
- Hook o trigger
  - `add_action('plugins_loaded', 'realestate_sync_init', 0)`
  - `register_activation_hook(__FILE__, [RealEstate_Sync::class, 'set_activation_flag'])`
  - `add_action('wp_loaded', [$this, 'complete_activation'])`
  - `register_deactivation_hook(__FILE__, [RealEstate_Sync::class, 'plugin_deactivate'])`
- Classi principali coinvolte
  - `RealEstate_Sync`
  - `RealEstate_Sync_Logger`
  - `RealEstate_Sync_Debug_Tracker`
  - `RealEstate_Sync_Import_Engine`
  - `RealEstate_Sync_Cron_Manager`
  - `RealEstate_Sync_Tracking_Manager`
  - `RealEstate_Sync_Admin`
- File coinvolti
  - `realestate-sync.php`
  - `includes/class-realestate-sync-*.php` caricati in `load_dependencies()`
  - `config/default-settings.php`
  - `config/field-mapping.php`
- Flusso
  - `plugins_loaded` chiama `realestate_sync_init()`
  - il costruttore registra hook e fa `load_dependencies()`
  - un secondo `plugins_loaded` interno chiama `init_plugin()`
  - `init_plugin()` istanzia logger, parser, mapper, importer API, import engine, cron manager, tracking manager e admin
  - `wp_loaded` completa activation se trova il flag `realestate_sync_needs_activation`
- Output/effetto finale
  - plugin pronto, hook registrati, classi centrali disponibili, tabelle create, opzioni default impostate
- Dove intervenire per modifiche future
  - bootstrap e lifecycle: `realestate-sync.php`
  - dipendenze caricate sempre: `load_dependencies()`
  - istanze runtime condivise: `init_plugin()`

## 2. Admin UI (menu, pagine, AJAX)

- Scopo
  - Esporre dashboard operativa, settings, strumenti queue/cleanup, upload XML, log e varie utility di manutenzione.
- Entry point
  - `RealEstate_Sync::add_admin_menu()`
  - `RealEstate_Sync_Admin::display_admin_page()`
- Hook o trigger
  - `admin_menu`
  - `admin_enqueue_scripts`
  - molti `wp_ajax_realestate_sync_*`
- Classi principali coinvolte
  - `RealEstate_Sync`
  - `RealEstate_Sync_Admin`
  - `RealEstate_Sync_Cron_Manager`
  - `RealEstate_Sync_Logger`
  - `RealEstate_Sync_Tracking_Manager`
- File coinvolti
  - `admin/class-realestate-sync-admin.php`
  - `admin/views/dashboard-modular.php`
  - `admin/views/widgets/*.php`
  - `admin/views/partials/*.php`
  - `admin/assets/admin.js`
  - `admin/assets/admin.css`
- Flusso
  - menu `Import dal Gestionale` apre `display_admin_page()`
  - la pagina include `dashboard-modular.php`
  - il layout monta widget separati per monitor, import, settings e tools
  - `admin.js` aggancia click/form/tab e invoca AJAX su `admin-ajax.php`
  - gli handler in `RealEstate_Sync_Admin` salvano opzioni, avviano import, leggono queue/log, eseguono cleanup
- Output/effetto finale
  - superficie di controllo completa lato admin
- Dove intervenire per modifiche future
  - comportamento UI: `admin/assets/admin.js`
  - wiring AJAX/server side: `admin/class-realestate-sync-admin.php`
  - struttura dashboard: `admin/views/dashboard-modular.php` e widget correlati

## 3. Import manuale

- Scopo
  - Avviare import scaricando il feed XML dal gestionale dal pannello admin.
- Entry point
  - `RealEstate_Sync_Admin::handle_manual_import()`
- Hook o trigger
  - `wp_ajax_realestate_sync_manual_import`
  - bottone `#start-manual-import` in `admin/assets/admin.js`
- Classi principali coinvolte
  - `RealEstate_Sync_Admin`
  - `RealEstate_Sync_XML_Downloader`
  - `RealEstate_Sync_Batch_Orchestrator`
- File coinvolti
  - `admin/class-realestate-sync-admin.php`
  - `admin/assets/admin.js`
  - `includes/class-realestate-sync-xml-downloader.php`
  - `includes/class-realestate-sync-batch-orchestrator.php`
- Flusso
  - UI invia AJAX con `mark_as_test` e `force_update`
  - handler risolve credenziali da opzioni o hardcoded fallback
  - `XML_Downloader::download_xml()` scarica e decomprime
  - `Batch_Orchestrator::process_xml_batch()` crea sessione, filtra, queue, processa il primo batch
  - la UI riceve `session_id`, `total_queued`, `remaining`
- Output/effetto finale
  - import batch avviato; il primo lotto viene processato subito, il resto continua fuori richiesta HTTP
- Dove intervenire per modifiche future
  - avvio import manuale: `handle_manual_import()`
  - acquisizione file XML: `XML_Downloader`
  - logica comune post-download: `Batch_Orchestrator`

## 4. Import schedulato (cron)

- Scopo
  - Avviare import automatici su base temporale.
- Entry point
  - WP cron: `RealEstate_Sync::run_scheduled_import()`
  - cron manager: `RealEstate_Sync_Cron_Manager::execute_daily_import()`
  - cron esterno: `batch-continuation.php`
- Hook o trigger
  - `realestate_sync_daily_import`
  - `wp_schedule_event(...)`
  - chiamata HTTP diretta a `batch-continuation.php?token=...`
- Classi principali coinvolte
  - `RealEstate_Sync`
  - `RealEstate_Sync_Cron_Manager`
  - `RealEstate_Sync_XML_Downloader`
  - `RealEstate_Sync_Batch_Orchestrator`
- File coinvolti
  - `realestate-sync.php`
  - `includes/class-realestate-sync-cron-manager.php`
  - `batch-continuation.php`
- Flusso
  - l’activation registra il cron WP `realestate_sync_daily_import`
  - la UI può riprogrammare la schedule via `Cron_Manager::reschedule_import()`
  - `Cron_Manager::execute_daily_import()` scarica XML e invoca `Batch_Orchestrator`
  - in parallelo esiste `RealEstate_Sync::run_scheduled_import()` che usa ancora `Import_Engine::execute_chunked_import()`
  - `batch-continuation.php` controlla anche se è ora di partire e può avviare direttamente un import schedulato batch senza passare dal cron WP
- Output/effetto finale
  - uno scheduled import viene avviato e poi continuato a batch
- Dove intervenire per modifiche future
  - scheduling UI e reschedule: `admin/class-realestate-sync-admin.php`, `Cron_Manager`
  - cron WP moderno batch: `Cron_Manager::execute_daily_import()`
  - cron legacy interno al plugin: `RealEstate_Sync::run_scheduled_import()`
  - cron esterno robusto: `batch-continuation.php`

## 5. Batch processing

- Scopo
  - Processare feed grandi senza timeout, spezzando il lavoro in coda DB e lotti piccoli.
- Entry point
  - `RealEstate_Sync_Batch_Orchestrator::process_xml_batch()`
  - `RealEstate_Sync_Batch_Processor::process_next_batch()`
- Hook o trigger
  - chiamata da import manuale
  - chiamata da upload XML
  - chiamata da cron manager
  - continuazione da `batch-continuation.php`
- Classi principali coinvolte
  - `RealEstate_Sync_Batch_Orchestrator`
  - `RealEstate_Sync_Batch_Processor`
  - `RealEstate_Sync_Queue_Manager`
  - `RealEstate_Sync_Tracking_Manager`
  - `RealEstate_Sync_Import_Engine`
- File coinvolti
  - `includes/class-realestate-sync-batch-orchestrator.php`
  - `includes/class-realestate-sync-batch-processor.php`
  - `includes/class-realestate-sync-queue-manager.php`
  - `batch-continuation.php`
- Flusso
  - orchestrator crea `session_id`
  - carica XML con `simplexml_load_file()`
  - indicizza agenzie e annunci
  - filtra province abilitate TN/BZ
  - separa gli elementi `deleted=1`
  - esegue cleanup deletion
  - pre-calcola hash via tracking per saltare elementi invariati
  - salva la queue DB e i dati pre-parsati in option `realestate_sync_batch_data_{session}`
  - processa subito il primo batch
  - la continuazione legge la queue e richiama `Batch_Processor`
- Output/effetto finale
  - queue popolata, dati batch persistiti, avanzamento background fino a coda vuota
- Dove intervenire per modifiche future
  - fase index/filter/queue: `Batch_Orchestrator`
  - dimensione batch, timeout, retry, stale recovery: `Batch_Processor`
  - storage queue e query SQL: `Queue_Manager`

## 6. Endpoint `batch-continuation.php`

- Scopo
  - Endpoint HTTP esterno per cron server-side, usato sia per far partire import schedulati sia per continuare i batch pendenti.
- Entry point
  - `batch-continuation.php`
- Hook o trigger
  - chiamata diretta HTTP con token segreto
- Classi principali coinvolte
  - `RealEstate_Sync_XML_Downloader`
  - `RealEstate_Sync_Batch_Orchestrator`
  - `RealEstate_Sync_Batch_Processor`
  - `RealEstate_Sync_Import_Verifier`
  - `RealEstate_Sync_Email_Report`
- File coinvolti
  - `batch-continuation.php`
  - `includes/class-realestate-sync-batch-processor.php`
  - `includes/class-realestate-sync-import-verifier.php`
  - `includes/class-realestate-sync-email-report.php`
- Flusso
  - valida token da costante/env/option/default hardcoded
  - fa bootstrap di WordPress via `wp-load.php`
  - step 1: se la schedule custom dice “ora”, scarica XML e avvia `Batch_Orchestrator`
  - step 2: legge la queue DB per trovare la prima sessione con `pending`
  - imposta transient lock `realestate_sync_processing_lock`
  - costruisce `Batch_Processor` e chiama `process_next_batch()`
  - aggiorna option `realestate_sync_background_import_progress`
  - a coda completa esegue `Import_Verifier`, snapshot/report email e chiude la sessione
- Output/effetto finale
  - batch successivo processato o intera sessione chiusa con verifica e report
- Dove intervenire per modifiche future
  - sicurezza endpoint/token: `batch-continuation.php`
  - policy di selezione sessione e locking: `batch-continuation.php`
  - post-processing finale: `Import_Verifier`, `Email_Report`

## 7. Queue / tracking

- Scopo
  - Tenere stato di elaborazione per sessione e change detection hash-based per property/agency.
- Entry point
  - `RealEstate_Sync_Queue_Manager::*`
  - `RealEstate_Sync_Tracking_Manager::*`
- Hook o trigger
  - accesso diretto da orchestrator, batch processor, admin tools
  - `before_delete_post` per cleanup tracking
- Classi principali coinvolte
  - `RealEstate_Sync_Queue_Manager`
  - `RealEstate_Sync_Tracking_Manager`
- File coinvolti
  - `includes/class-realestate-sync-queue-manager.php`
  - `includes/class-realestate-sync-tracking-manager.php`
  - `realestate-sync.php` activation table creation
- Flusso
  - activation crea tabelle tracking, import sessions e queue
  - orchestrator calcola hash e decide `insert/update/skip`
  - queue salva `session_id`, `item_type`, `item_id`, `status`, retry/error
  - batch processor legge `pending`, marca `processing`, poi `done` o `error`
  - tracking salva hash, `wp_post_id`, snapshot e stato `active/deleted`
- Output/effetto finale
  - persistenza dello stato di import e filtro di modifiche reali
- Dove intervenire per modifiche future
  - change detection: `Tracking_Manager`
  - semantica degli stati queue: `Queue_Manager`
  - strumenti admin queue: `admin/class-realestate-sync-admin.php`

## 8. Parsing XML e mapping

- Scopo
  - Convertire il feed GestionaleImmobiliare in dati interni e poi in payload compatibile con WPResidence.
- Entry point
  - `RealEstate_Sync_XML_Parser::parse_xml_file()`
  - `RealEstate_Sync_XML_Parser::parse_annuncio_xml()`
  - `RealEstate_Sync_Property_Mapper::map_properties()`
  - `RealEstate_Sync_Import_Engine::convert_xml_to_v3_format()`
- Hook o trigger
  - chiamata diretta da `Import_Engine`
  - chiamata diretta da `Batch_Orchestrator` per pre-parsing singolo annuncio
- Classi principali coinvolte
  - `RealEstate_Sync_XML_Parser`
  - `RealEstate_Sync_Property_Mapper`
  - `RealEstate_Sync_ISTAT_Lookup`
  - `RealEstate_Sync_Agency_Parser`
- File coinvolti
  - `includes/class-realestate-sync-xml-parser.php`
  - `includes/class-realestate-sync-property-mapper.php`
  - `includes/class-realestate-sync-import-engine.php`
  - `includes/class-realestate-sync-istat-lookup.php`
  - `data/istat-lookup-tn-bz.php`
  - `config/field-mapping.php`
- Flusso
  - parser estrae `annuncio` dal feed in array normalizzato
  - import engine converte i campi XML in struttura v3 interna
  - se mancano dati geografici usa lookup ISTAT locale
  - mapper genera `post_data`, `meta_fields`, `taxonomies`, `features`, `gallery`, `custom_fields`, `catasto`, `source_data`
- Output/effetto finale
  - proprietà pronte per il writer API
- Dove intervenire per modifiche future
  - parsing feed: `XML_Parser`
  - mapping business e tassonomie/features: `Property_Mapper`
  - fallback geografico: `ISTAT_Lookup` + `data/istat-lookup-tn-bz.php`
  - config descrittiva/non autoritativa: `config/field-mapping.php`

## 9. Scrittura dati (WP / WPResidence API)

- Scopo
  - Creare/aggiornare property e agency in WordPress/WPResidence.
- Entry point
  - property: `RealEstate_Sync_WP_Importer_API::process_property()`
  - property API: `RealEstate_Sync_WPResidence_API_Writer::create_property()` / `update_property()`
  - agency: `RealEstate_Sync_Agency_Manager::import_agencies()`
  - agency API: `RealEstate_Sync_WPResidence_Agency_API_Writer::*`
- Hook o trigger
  - chiamata diretta da `Import_Engine` o `Batch_Processor`
- Classi principali coinvolte
  - `RealEstate_Sync_Import_Engine`
  - `RealEstate_Sync_WP_Importer_API`
  - `RealEstate_Sync_WPResidence_API_Writer`
  - `RealEstate_Sync_Agency_Manager`
  - `RealEstate_Sync_WPResidence_Agency_API_Writer`
- File coinvolti
  - `includes/class-realestate-sync-import-engine.php`
  - `includes/class-realestate-sync-wp-importer-api.php`
  - `includes/class-realestate-sync-wpresidence-api-writer.php`
  - `includes/class-realestate-sync-agency-manager.php`
  - `includes/class-realestate-sync-wpresidence-agency-api-writer.php`
- Flusso
  - `Import_Engine::process_single_property()` calcola hash e decide action
  - mapper produce `mapped_data`
  - `WP_Importer_API` cerca post esistente via `property_import_id`
  - crea termini mancanti
  - `WPResidence_API_Writer` ottiene JWT, formatta body, chiama endpoint REST `/property/add` o `/property/edit/{id}`
  - aggiorna meta tracking locali (`property_import_id`, hash, session, version)
  - `Agency_Manager` fa create/update agency via API e aggiorna tracking agency
- Output/effetto finale
  - post/property e agency allineati nel sito WPResidence
- Dove intervenire per modifiche future
  - logica create/update property: `WP_Importer_API`
  - payload WPResidence e auth JWT: `WPResidence_API_Writer`
  - create/update agency: `Agency_Manager` + agency API writer
  - decisione action e tracking: `Import_Engine`

## 10. Media / immagini

- Scopo
  - Gestire immagini property/agency e cleanup attachment.
- Entry point
  - percorso attivo property: `WPResidence_API_Writer::format_api_body()` con `images`
  - filtro update: `WPResidence_API_Writer::filter_unchanged_gallery_images()`
  - cleanup delete: `RealEstate_Sync_Attachment_Cleanup::cleanup_attachments_on_delete()`
  - classi storiche/utilità: `RealEstate_Sync_Image_Importer`, `RealEstate_Sync_Media_Deduplicator`
- Hook o trigger
  - chiamata diretta durante create/update API
  - `before_delete_post`
- Classi principali coinvolte
  - `RealEstate_Sync_WPResidence_API_Writer`
  - `RealEstate_Sync_Attachment_Cleanup`
  - `RealEstate_Sync_Image_Importer`
  - `RealEstate_Sync_Media_Deduplicator`
- File coinvolti
  - `includes/class-realestate-sync-wpresidence-api-writer.php`
  - `includes/class-realestate-sync-attachment-cleanup.php`
  - `includes/class-realestate-sync-image-importer.php`
  - `includes/class-realestate-sync-media-deduplicator.php`
- Flusso
  - mapper prepara gallery con URL remote
  - API writer converte URL in formato `images[]`, forza HTTPS, filtra immagini già presenti negli update
  - l’API WPResidence gestisce upload/associazione lato destinazione
  - quando un post viene cancellato, `Attachment_Cleanup` rimuove allegati orfani/associati
- Output/effetto finale
  - immagini gallery/featured coerenti sul post; allegati puliti in fase di delete
- Dove intervenire per modifiche future
  - strategia media del flusso attivo: `WPResidence_API_Writer`
  - cleanup attachment: `Attachment_Cleanup`
  - eventuali flussi manuali di download/import immagini: `Image_Importer`

## 11. Cleanup / deletion

- Scopo
  - Rimuovere annunci/agenzie marcati deleted nel feed e fornire tool admin per cleanup di orfani/duplicati.
- Entry point
  - batch: `RealEstate_Sync_Deletion_Manager::handle_deleted_properties()` / `handle_deleted_agencies()`
  - admin: `handle_scan_orphan_posts()`, `handle_cleanup_orphan_posts()`, `handle_scan_duplicates()`, `handle_delete_duplicate_post()`, `handle_delete_all_duplicates()`
- Hook o trigger
  - chiamata diretta da orchestrator
  - AJAX admin
  - `before_delete_post`
- Classi principali coinvolte
  - `RealEstate_Sync_Deletion_Manager`
  - `RealEstate_Sync_Attachment_Cleanup`
  - `RealEstate_Sync_Tracking_Manager`
- File coinvolti
  - `includes/class-realestate-sync-deletion-manager.php`
  - `includes/class-realestate-sync-attachment-cleanup.php`
  - `admin/class-realestate-sync-admin.php`
- Flusso
  - orchestrator separa `deleted=1` dopo il filtro provincia
  - `Deletion_Manager` trova il post tramite meta import id, elimina attachments/featured image e poi il post definitivamente
  - aggiorna tracking a `deleted`
  - i tool admin scansionano orfani e duplicati usando query SQL dirette su posts/postmeta/tracking/queue
- Output/effetto finale
  - contenuti rimossi dal sito e tracking riallineato
- Dove intervenire per modifiche future
  - deletion XML-driven: `Deletion_Manager`
  - cleanup da backend WP: `Attachment_Cleanup`, `Tracking_Manager::cleanup_tracking_on_delete()`
  - strumenti operativi: `admin/class-realestate-sync-admin.php`

## 12. Logging / debug

- Scopo
  - Fornire logging testuale, tracing strutturato per sessione e monitoraggio/verifica post-import.
- Entry point
  - `RealEstate_Sync_Logger::log()`
  - `RealEstate_Sync_Debug_Tracker::start_trace()/log_event()/end_trace()`
  - `RealEstate_Sync_Import_Verifier::verify_session()`
  - `RealEstate_Sync_Email_Report::build_report()/send_email()`
- Hook o trigger
  - logging chiamato direttamente quasi ovunque
  - verifier/report chiamati a fine sessione da `batch-continuation.php`
  - UI admin via AJAX `get_logs`, `download_logs`, `clear_logs`
- Classi principali coinvolte
  - `RealEstate_Sync_Logger`
  - `RealEstate_Sync_Debug_Tracker`
  - `RealEstate_Sync_Import_Verifier`
  - `RealEstate_Sync_Email_Report`
- File coinvolti
  - `includes/class-realestate-sync-logger.php`
  - `includes/class-realestate-sync-debug-tracker.php`
  - `includes/class-realestate-sync-import-verifier.php`
  - `includes/class-realestate-sync-email-report.php`
  - `logs/`
- Flusso
  - bootstrap crea logger singleton
  - orchestrator apre trace e batch processor lo riprende nelle continuazioni
  - a fine batch completo si chiude il trace
  - verifier salva issue della sessione
  - email report costruisce snapshot e invia mail
- Output/effetto finale
  - log giornalieri/import-specifici, trace di sessione, report finale
- Dove intervenire per modifiche future
  - logging file-based: `Logger`
  - tracing strutturato: `Debug_Tracker`
  - qualità post-import: `Import_Verifier`
  - reportistica: `Email_Report`

## 13. Configurazione (`config/`)

- Scopo
  - Definire default options e mapping di riferimento.
- Entry point
  - `load_dependencies()` carica sempre entrambi i file
  - `set_default_options()` include di nuovo `default-settings.php`
- Hook o trigger
  - bootstrap plugin
  - activation
- Classi principali coinvolte
  - `RealEstate_Sync`
  - `RealEstate_Sync_Admin` per alcune viste info
- File coinvolti
  - `config/default-settings.php`
  - `config/field-mapping.php`
- Flusso
  - `default-settings.php` fornisce opzioni iniziali via `add_option`
  - `field-mapping.php` viene letto in admin/info ma il mapping operativo principale è codificato in `Property_Mapper`
- Output/effetto finale
  - opzioni default create
  - tabella informativa di mapping disponibile in admin
- Dove intervenire per modifiche future
  - default options: `config/default-settings.php`
  - mapping runtime reale: `Property_Mapper`, non solo `config/field-mapping.php`

## 14. Data (`data/`)

- Scopo
  - Conservare lookup locali usati a runtime.
- Entry point
  - `RealEstate_Sync_ISTAT_Lookup::load_lookup_table()`
- Hook o trigger
  - chiamata indiretta dal mapping/import quando servono dati geografici
- Classi principali coinvolte
  - `RealEstate_Sync_ISTAT_Lookup`
  - `RealEstate_Sync_Import_Engine`
  - `RealEstate_Sync_Property_Mapper`
- File coinvolti
  - `data/istat-lookup-tn-bz.php`
  - `data/comuni-istat-full.json`
- Flusso
  - `ISTAT_Lookup` carica il lookup PHP in cache statica
  - import engine completa comune/provincia/regione/cap da codice ISTAT
  - `comuni-istat-full.json` è presente nel repo ma non emerge come dipendenza runtime principale dei flussi letti
- Output/effetto finale
  - dati geografici completati anche se il feed è incompleto
- Dove intervenire per modifiche future
  - lookup attivo TN/BZ: `data/istat-lookup-tn-bz.php`
  - servizio di accesso: `includes/class-realestate-sync-istat-lookup.php`

## Entrypoint globali

- `realestate-sync.php`
  - entrypoint WordPress principale
  - registra hook, carica dipendenze, inizializza servizi e menu admin
- `batch-continuation.php`
  - entrypoint HTTP esterno per cron server-side
  - controlla schedule custom e continua i batch leggendo la queue DB
- Superficie admin
  - `admin/class-realestate-sync-admin.php`
  - `admin/views/dashboard-modular.php`
  - `admin/assets/admin.js`
  - tutti gli `wp_ajax_realestate_sync_*`

## Flussi trasversali

- Differenza tra import legacy (`Import_Engine`) e import batch (`Batch_Orchestrator`)
  - `Import_Engine` è il motore storico di import property: parse stream, hash tracking, mapping, writer property
  - `Batch_Orchestrator` è il coordinatore moderno: indicizza XML, filtra province, gestisce deletion, pre-filtra hash, salva queue, processa primo lotto
  - `Batch_Processor` non sostituisce davvero `Import_Engine`: per le property lo richiama via `process_single_property()`
  - quindi il batch è uno strato orchestration/queue attorno al motore property storico
- Coesistenza e impatti
  - il plugin carica sia classi legacy sia batch sempre dal bootstrap
  - `REALESTATE_SYNC_ENABLE_LEGACY_IMPORTER` è `false`, ma il `Import_Engine` resta centrale nel ramo property
  - esistono ancora handler/admin path che usano direttamente `Import_Engine::execute_chunked_import()` per test/import di file locali
  - esiste anche `RealEstate_Sync::run_scheduled_import()` che usa ancora il ramo non-batch
- Relazioni tra cron interno e cron esterno
  - cron interno WP: hook `realestate_sync_daily_import`, gestito da `Cron_Manager`
  - cron esterno: `batch-continuation.php` chiamato dal server ogni minuto
  - il cron esterno è il vero meccanismo robusto di continuazione batch
  - in più l’endpoint esterno può anche far partire import schedulati, quindi in pratica convive con il cron WP e ne duplica parte della responsabilità

## Dipendenze importanti

- Include caricati sempre dal bootstrap
  - quasi tutte le classi `includes/class-realestate-sync-*.php`
  - `config/default-settings.php`
  - `config/field-mapping.php`
  - `admin/class-realestate-sync-admin.php` solo se `is_admin()`
- Include/require non ovvi
  - `includes/class-realestate-sync-import-engine.php` include direttamente `class-realestate-sync-istat-lookup.php`
  - `Import_Engine::__construct()` include runtime `class-realestate-sync-self-healing-manager.php`
  - `Property_Mapper::__construct()` richiede `class-realestate-sync-agency-manager.php`
  - `batch-continuation.php` carica `wp-load.php` e poi solo queue/batch processor/verifier/report dove servono
  - `database-tools.php` richiede `admin/views/partials/allowed-admins.php`
- File caricati solo in flussi specifici
  - `class-realestate-sync-import-verifier.php` solo a fine sessione batch completa
  - `class-realestate-sync-email-report.php` per test email e chiusura batch
  - `wp-admin/includes/upgrade.php` solo in creazione tabelle
  - `wp-admin/includes/image.php`, `media.php`, `file.php` solo in utility media

## Incoerenze e note operative rilevate dal codice

- Il ramo operativo “moderno” è batch-based, ma il motore property finale resta `Import_Engine`.
- Coesistono due entrypoint schedulati reali:
  - `Cron_Manager::execute_daily_import()` usa batch
  - `RealEstate_Sync::run_scheduled_import()` usa import engine diretto
- L’admin ha ancora handler legacy/test che usano `execute_chunked_import()` fuori dal batch.
- `Queue_Manager::create_table()` non mostra colonne `wp_post_id` o `processed_at`, ma altre parti del codice le leggono/scrivono. Questo è un punto da verificare in DB reale.
- `handle_get_progress()` legge il transient del vecchio import engine, non la progress option del batch moderno; utile ma non allineato al flusso batch.
- `config/field-mapping.php` esiste, ma il mapping runtime effettivo è principalmente hardcoded in `Property_Mapper`.

## Task finale

### 1. Entrypoint principali

- `realestate-sync.php`
- `batch-continuation.php`
- `admin/class-realestate-sync-admin.php`
- `admin/views/dashboard-modular.php`
- `admin/assets/admin.js`

### 2. File più critici (top 10)

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

### 3. Aree poco chiare o rischiose da approfondire dopo

- allineamento reale schema queue DB vs codice che usa `processed_at` e `wp_post_id`
- doppio scheduling tra `Cron_Manager`, `RealEstate_Sync::run_scheduled_import()` e `batch-continuation.php`
- livello di utilizzo reale delle classi legacy/test ancora presenti nell’admin
- ruolo effettivo di `Image_Importer` e `Media_Deduplicator` nel flusso attuale API-based
- consistenza tra option storage moderno batch (`realestate_sync_background_import_progress`) e vecchio transient progress (`realestate_sync_import_progress`)
