# Runtime analysis whitelist deploy

Data analisi: 2026-04-18
Repository: `realestate-sync-plugin`
Obiettivo: derivare una whitelist deployabile dal codice realmente eseguito, non dalla whitelist storica.

## 1. Sintesi esecutiva

La whitelist attuale basata sui path `realestate-sync.php`, `admin/`, `includes/`, `config/`, `data/` non e' sufficiente: il file root `batch-continuation.php` e' runtime certo e va aggiunto esplicitamente. Senza quel file il flusso batch/scheduled resta incompleto.

Gli entrypoint runtime reali sono tre:
- `realestate-sync.php`: bootstrap del plugin WordPress.
- `batch-continuation.php`: endpoint PHP diretto richiamato da cron esterno, con `wp-load.php` e prosecuzione dei batch.
- `admin/class-realestate-sync-admin.php` + viste/assets admin: runtime nell'area admin via menu, AJAX e pagina dashboard.

La superficie runtime effettiva e' piu' ampia della sola logica batch: il bootstrap carica sempre quasi tutto `includes/` via `require_once` in `realestate-sync.php:125-162`, inclusi file legacy/deprecati. Finche' quei `require_once` restano nel bootstrap, quei file sono deploy runtime-certi anche se poi i relativi oggetti non vengono usati spesso.

`batch-continuation.php` e' runtime certo. Viene raggiunto da cron server esterno, carica WordPress tramite `wp-load.php`, puo' avviare import schedulato e continua i batch leggendo la queue DB; a fine elaborazione richiama anche `Import_Verifier` e `Email_Report`.

Conclusione operativa: per una whitelist affidabile bisogna includere `batch-continuation.php` oltre ai path gia' previsti, e distinguere dentro `admin/`, `includes/` e `data/` i file attivi da quelli presenti ma non richiamati.

## 2. Mappa runtime del plugin

### 2.1 Entry point e bootstrap

1. `realestate-sync.php`
   - Bootstrap plugin e definizione costanti.
   - Registra hook principali: `wp_loaded`, `plugins_loaded`, `init`, `admin_menu`, `admin_enqueue_scripts`, `wp_dashboard_setup`, `admin_post_realestate_sync_send_test_email`, `wp_ajax_realestate_sync_get_import_status`, `wp_ajax_realestate_sync_test_sample_xml`, `realestate_sync_daily_import`.
   - Riferimenti: `realestate-sync.php:93-117`, `762`.

2. `batch-continuation.php`
   - Endpoint diretto richiamato da cron HTTP esterno.
   - Verifica token, carica WordPress con `require_once(dirname(__FILE__) . '/../../../wp-load.php')`, puo' scaricare XML e chiamare `RealEstate_Sync_Batch_Orchestrator::process_xml_batch(...)`, poi continua i batch con `RealEstate_Sync_Batch_Processor`.
   - Riferimenti: `batch-continuation.php:24-53`, `148-161`, `258-307`.

3. Admin page WordPress
   - `realestate-sync.php` registra il menu.
   - `admin/class-realestate-sync-admin.php::display_admin_page()` include `admin/views/dashboard-modular.php`.
   - Riferimenti: `realestate-sync.php:484-493`, `admin/class-realestate-sync-admin.php:749-755`.

### 2.2 Catena di caricamento certa

Caricati sempre dal bootstrap plugin (`require_once` in `realestate-sync.php:125-162`):

- `includes/class-realestate-sync-logger.php`
- `includes/class-realestate-sync-debug-tracker.php`
- `includes/class-realestate-sync-xml-downloader.php`
- `includes/class-realestate-sync-xml-parser.php`
- `includes/class-realestate-sync-property-mapper.php`
- `includes/class-realestate-sync-image-importer.php`
- `includes/class-realestate-sync-agency-manager.php`
- `includes/class-realestate-sync-agency-parser.php`
- `includes/class-realestate-sync-agency-importer.php`
- `includes/class-realestate-sync-media-deduplicator.php`
- `includes/class-realestate-sync-property-agent-linker.php`
- `includes/class-realestate-sync-wpresidence-api-writer.php`
- `includes/class-realestate-sync-wpresidence-agency-api-writer.php`
- `includes/class-realestate-sync-wp-importer.php`
- `includes/class-realestate-sync-wp-importer-api.php`
- `includes/class-realestate-sync-import-engine.php`
- `includes/class-realestate-sync-cron-manager.php`
- `includes/class-realestate-sync-tracking-manager.php`
- `includes/class-realestate-sync-queue-manager.php`
- `includes/class-realestate-sync-batch-processor.php`
- `includes/class-realestate-sync-batch-orchestrator.php`
- `includes/class-realestate-sync-deletion-manager.php`
- `includes/class-realestate-sync-attachment-cleanup.php`
- `config/default-settings.php`
- `config/field-mapping.php`
- `admin/class-realestate-sync-admin.php` solo in `is_admin()`

### 2.3 Flussi runtime principali

#### A. Bootstrap plugin standard

`plugins_loaded` chiama `realestate_sync_init()` e istanzia `RealEstate_Sync` (`realestate-sync.php:762`). Il costruttore:
- registra hook;
- carica dipendenze;
- su `plugins_loaded` esegue `init_plugin()`;
- crea istanze runtime di `Logger`, `XML_Parser`, `Property_Mapper`, `WP_Importer_API`, `Import_Engine`, `Cron_Manager`, `Tracking_Manager`, `Attachment_Cleanup`, `Admin`.

Riferimenti:
- `realestate-sync.php:75-90`
- `realestate-sync.php:170-204`

#### B. Attivazione e setup DB

Su attivazione:
- `register_activation_hook` -> `set_activation_flag()`
- `wp_loaded` -> `complete_activation()`
- creazione tabelle inline in `realestate-sync.php`
- `Queue_Manager` e `Tracking_Manager` vengono richiamati per creare tabelle aggiuntive.

Riferimenti:
- `realestate-sync.php:93-99`
- `realestate-sync.php:223-266`
- `realestate-sync.php:330-431`

Nota: `includes/migrations/create-import-sessions-table.php` non e' usato da questo flusso; la creazione tabella avviene inline nel bootstrap.

#### C. Import manuale / upload XML / admin AJAX

AJAX admin registrati in `admin/class-realestate-sync-admin.php:36-100`.

Flussi rilevanti:
- import manuale -> `handle_manual_import()` -> `XML_Downloader` + `Batch_Orchestrator` (`admin/class-realestate-sync-admin.php:768-819`)
- upload file XML test/import -> `handle_process_test_file()` / `handle_import_test_file()` con `Import_Engine` o `Batch_Orchestrator` (`admin/class-realestate-sync-admin.php:2196`, `2712`)
- gestione queue, log, schedule, cleanup, duplicate scan, system checks via AJAX.

Superficie file coinvolta:
- `admin/assets/admin.js`
- `admin/assets/admin.css`
- `admin/assets/bootstrap-scope.css`
- `admin/views/dashboard-modular.php`
- partials e widget inclusi dal dashboard attivo

#### D. Import schedulato

Esistono due rami runtime per `realestate_sync_daily_import`:

1. `RealEstate_Sync::run_scheduled_import()` in `realestate-sync.php:686-745`
   - scarica XML
   - usa `Import_Engine::execute_chunked_import()`
   - rappresenta un ramo legacy ancora hookato

2. `RealEstate_Sync_Cron_Manager::execute_daily_import()` in `includes/class-realestate-sync-cron-manager.php:64-143`
   - scarica XML
   - usa `Batch_Orchestrator::process_xml_batch()`
   - logga che la prosecuzione continuera' via `batch-continuation.php`

Poiche' entrambi i callback risultano registrati sullo stesso hook, la whitelist deve coprire entrambi i rami.

#### E. Continuazione batch via endpoint HTTP

`batch-continuation.php`:
- verifica token (`24-44`);
- carica WordPress (`53`);
- puo' avviare import schedulato direttamente con `XML_Downloader` + `Batch_Orchestrator` (`148-161`);
- interroga la queue DB e seleziona la sessione attiva (`184-205`);
- carica `Queue_Manager` e `Batch_Processor` (`258-259`);
- processa il batch successivo (`263-266`);
- a completamento carica `Import_Verifier` e `Email_Report` (`296-307`).

Questo file e' quindi sia entrypoint sia snodo verso altri file runtime non opzionali.

### 2.4 Include indiretti e caricamenti costruiti da path

Include/runtime indiretti verificati:
- `includes/class-realestate-sync-import-engine.php` richiede `class-realestate-sync-istat-lookup.php` (`19`) e `class-realestate-sync-self-healing-manager.php` (`91`).
- `includes/class-realestate-sync-istat-lookup.php` richiede `../data/istat-lookup-tn-bz.php` (`171-182`).
- `includes/class-realestate-sync-property-mapper.php` richiede `class-realestate-sync-agency-manager.php` (`60`).
- `includes/class-realestate-sync-batch-orchestrator.php` richiede dinamicamente `class-realestate-sync-deletion-manager.php` (`208`).
- `admin/views/dashboard-modular.php` include partials e widget attivi (`19-73`).
- `admin/views/widgets/database-tools.php` richiede `partials/allowed-admins.php` (`10`).

Caricamenti dinamici non risolti come include di codice, ma rilevanti per deploy:
- `glob()` su log/temp in `Logger`, `XML_Downloader`, `Image_Importer`; non introducono nuovi file PHP.
- uso di `wp_upload_dir()` per file temporanei/log esterni alla cartella plugin.

### 2.5 Hook, endpoint e superfici runtime trovate

Trovati staticamente:
- WordPress hooks: si'
- AJAX admin (`wp_ajax_*`): si', numerosi
- `admin_post_*`: si', `realestate_sync_send_test_email`
- cron/schedule: si'
- REST route: nessuna `register_rest_route` trovata
- `wp_ajax_nopriv_*`: nessuna trovata
- autoload Composer: nessuno trovato
- template loader front-end dedicato: non trovato

## 3. Classificazione file

## File sicuramente runtime

### Root

- `realestate-sync.php`
- `batch-continuation.php`

### Config e data

- `config/default-settings.php`
- `config/field-mapping.php`
- `data/istat-lookup-tn-bz.php`

### Includes caricati certamente dal bootstrap

- `includes/class-realestate-sync-logger.php`
- `includes/class-realestate-sync-debug-tracker.php`
- `includes/class-realestate-sync-xml-downloader.php`
- `includes/class-realestate-sync-xml-parser.php`
- `includes/class-realestate-sync-property-mapper.php`
- `includes/class-realestate-sync-image-importer.php`
- `includes/class-realestate-sync-agency-manager.php`
- `includes/class-realestate-sync-agency-parser.php`
- `includes/class-realestate-sync-agency-importer.php`
- `includes/class-realestate-sync-media-deduplicator.php`
- `includes/class-realestate-sync-property-agent-linker.php`
- `includes/class-realestate-sync-wpresidence-api-writer.php`
- `includes/class-realestate-sync-wpresidence-agency-api-writer.php`
- `includes/class-realestate-sync-wp-importer.php`
- `includes/class-realestate-sync-wp-importer-api.php`
- `includes/class-realestate-sync-import-engine.php`
- `includes/class-realestate-sync-cron-manager.php`
- `includes/class-realestate-sync-tracking-manager.php`
- `includes/class-realestate-sync-queue-manager.php`
- `includes/class-realestate-sync-batch-processor.php`
- `includes/class-realestate-sync-batch-orchestrator.php`
- `includes/class-realestate-sync-deletion-manager.php`
- `includes/class-realestate-sync-attachment-cleanup.php`

### Includes caricati certamente da flussi secondari runtime

- `includes/class-realestate-sync-istat-lookup.php`
- `includes/class-realestate-sync-self-healing-manager.php`
- `includes/class-realestate-sync-import-verifier.php`
- `includes/class-realestate-sync-email-report.php`

### Admin runtime attivo

- `admin/class-realestate-sync-admin.php`
- `admin/assets/admin.js`
- `admin/assets/admin.css`
- `admin/assets/bootstrap-scope.css`
- `admin/views/dashboard-modular.php`
- `admin/views/partials/header.php`
- `admin/views/partials/navigation.php`
- `admin/views/partials/footer-scripts.php`
- `admin/views/partials/allowed-admins.php`
- `admin/views/widgets/monitor-import.php`
- `admin/views/widgets/import-prossimo.php`
- `admin/views/widgets/import-immediato.php`
- `admin/views/widgets/import-xml.php`
- `admin/views/widgets/config-automatico.php`
- `admin/views/widgets/config-email.php`
- `admin/views/widgets/config-credenziali.php`
- `admin/views/widgets/queue-management.php`
- `admin/views/widgets/cleanup-duplicates.php`
- `admin/views/widgets/database-tools.php`
- `admin/views/widgets/cleanup-properties.php`

## File probabilmente runtime

- `admin/views/widgets/stato-attuale.php`
  - presente ma include commentato in `dashboard-modular.php`.
- `admin/views/widgets/storico-import.php`
  - presente ma include commentato.
- `admin/views/widgets/log-monitoraggio.php`
  - presente ma include commentato.

Questi tre non sono attivi nel codice corrente, ma sembrano appartenere alla dashboard modulare e potrebbero rientrare se i commenti venissero rimossi senza ulteriore wiring.

## File di supporto non runtime

- `data/comuni-istat-full.json`
  - nessun riferimento nel codice runtime.
- `admin/views/settings.php`
  - nessun include/richiamo trovato.
- `admin/views/logs.php`
  - nessun include/richiamo trovato.
- `admin/assets/menu-icon.svg`
  - non usato; il menu usa `dashicons-download`.
- `docs/`
- `logs/`
- `config/` non ha altri file oltre ai due runtime certi.

## File sicuramente non deployabili

- `.claude/`
- `.codex/`
- `.ssh-config/`
- `scripts/`
- `server-snapshot-20260114/`
- `TRASH/`
- `feng-shui-analysis/`
- `.env`
- `.gitignore`
- `diff-batch-continuation.ps.txt`
- `diff-realestate-sync.ps.txt`
- `includes/migrations/create-import-sessions-table.php`
  - script standalone, non richiamato dai flussi attivi.
- `includes/class-realestate-sync-email-notifier.php`
  - nessun riferimento runtime trovato; superseded da `Email_Report`.
- `includes/class-realestate-sync-hook-logger.php`
  - raggiungibile solo dal legacy importer se questo viene riattivato.

## 4. Focus whitelist deploy

### 4.1 Whitelist corrente: validazione

Whitelist corrente implicita:
- `realestate-sync.php`
- `admin/`
- `includes/`
- `config/`
- `data/`

Esito: **incompleta**.

Motivo principale:
- manca `batch-continuation.php`, che e' runtime certo ma sta in root fuori dai path oggi considerati deployabili.

### 4.2 File runtime fuori whitelist attuale

Da riportare esplicitamente:
- `batch-continuation.php`

Non ho trovato altri file PHP runtime fuori da `realestate-sync.php`, `admin/`, `includes/`, `config/`, `data/`.

### 4.3 Directory/path che dovrebbero entrare o uscire

Da aggiungere:
- `batch-continuation.php` come eccezione root deployabile.

Da mantenere:
- `admin/`
- `includes/`
- `config/`
- `data/`
- `realestate-sync.php`

Da escludere o trattare con eccezioni interne:
- dentro `admin/`: escludibili `views/settings.php`, `views/logs.php`, `assets/menu-icon.svg`
- dentro `admin/views/widgets/`: escludibili in stato attuale `stato-attuale.php`, `storico-import.php`, `log-monitoraggio.php`
- dentro `includes/`: escludibili `migrations/create-import-sessions-table.php`, `class-realestate-sync-email-notifier.php`, `class-realestate-sync-hook-logger.php`
- dentro `data/`: escludibile `comuni-istat-full.json`

### 4.4 Whitelist affidabile consigliata

Se si vuole massima sicurezza con whitelist semplice:
- `realestate-sync.php`
- `batch-continuation.php`
- `admin/`
- `includes/`
- `config/`
- `data/`

Se si vuole whitelist piu' stretta ma ancora affidabile:
- includere tutti i file elencati sotto "sicuramente runtime"
- includere facoltativamente i "probabilmente runtime" se si preferisce margine conservativo sull'admin UI
- escludere esplicitamente i file elencati come non runtime/non deployabili

## 5. Focus su batch-continuation.php

Esito: **runtime certo**.

### Come viene raggiunto

Percorso di invocazione:
- cron server esterno richiama HTTP GET su `.../wp-content/plugins/realestate-sync-plugin/batch-continuation.php?token=...`
- riferimento diretto nel commento header del file (`batch-continuation.php:12`)
- `Cron_Manager` lo cita esplicitamente come continuazione del processing (`includes/class-realestate-sync-cron-manager.php:142`)

### Cosa fa nel flusso reale

1. valida token (`24-44`)
2. carica WordPress con `wp-load.php` (`53`)
3. se lo scheduling interno dice che e' ora, scarica XML e chiama `Batch_Orchestrator` (`148-161`)
4. controlla lock e queue DB (`174-205`)
5. carica `Queue_Manager` e `Batch_Processor` (`258-259`)
6. processa il prossimo batch (`263-266`)
7. quando la sessione e' completa:
   - carica `Import_Verifier` (`296`)
   - carica `Email_Report` (`307`)
   - salva snapshot e invia email

### Impatto sulla whitelist

Se `batch-continuation.php` manca in deploy:
- la prosecuzione dei batch si interrompe;
- il cron HTTP esterno fallisce;
- la verifica finale e il report email non partono da questo endpoint;
- il sistema schedulato batch-oriented rimane parzialmente rotto anche se `includes/` e `realestate-sync.php` sono aggiornati.

## 6. Rischi e limiti dell'analisi

1. Coesistenza di rami legacy e batch.
   - Il plugin registra sia `RealEstate_Sync::run_scheduled_import()` sia `Cron_Manager::execute_daily_import()` sullo stesso hook `realestate_sync_daily_import`.
   - Finche' questa coesistenza rimane, la whitelist deve restare conservativa.

2. `require_once` bootstrap molto ampio.
   - Alcuni file deprecati risultano comunque runtime-certi perche' il bootstrap li carica sempre.
   - Esempio: `class-realestate-sync-agency-importer.php`, `class-realestate-sync-wp-importer.php`.

3. Branch condizionali da opzioni/costanti.
   - Il legacy importer e' frenato da `REALESTATE_SYNC_ENABLE_LEGACY_IMPORTER=false`, ma il file e' comunque richiesto.
   - `class-realestate-sync-hook-logger.php` diventerebbe runtime se il legacy importer fosse riattivato.

4. Dipendenze esterne a WordPress/plugin.
   - L'analisi copre i file del repository plugin, non i file core WP o tema WPResidence richiamati via API/hook/meta.

5. Caricamenti non statici di asset esterni.
   - Bootstrap CDN in admin (`jsdelivr`) non influisce sulla whitelist locale ma influisce sul funzionamento reale della UI.

6. Commenti e file dormant.
   - Alcuni widget/admin view sono presenti ma commentati o non referenziati; sono stati classificati come probabili o non runtime in base allo stato attuale del codice, non a intenzioni future.

## Elenco sintetico finale

### File o path deployabili

- `realestate-sync.php`
- `batch-continuation.php`
- `admin/`
- `includes/`
- `config/`
- `data/`

Per whitelist stretta, escludere da questi path i file indicati sotto.

### File o path da escludere

- `docs/`
- `scripts/`
- `TRASH/`
- `server-snapshot-20260114/`
- `.claude/`
- `.codex/`
- `.ssh-config/`
- `feng-shui-analysis/`
- `logs/`
- `.env`
- `.gitignore`
- `diff-batch-continuation.ps.txt`
- `diff-realestate-sync.ps.txt`
- `includes/migrations/create-import-sessions-table.php`
- `includes/class-realestate-sync-email-notifier.php`
- `includes/class-realestate-sync-hook-logger.php`
- `data/comuni-istat-full.json`
- `admin/views/settings.php`
- `admin/views/logs.php`
- `admin/assets/menu-icon.svg`
- `admin/views/widgets/stato-attuale.php`
- `admin/views/widgets/storico-import.php`
- `admin/views/widgets/log-monitoraggio.php`

### Dubbi da validare manualmente

- confermare in produzione se il cron esterno richiama davvero `batch-continuation.php` e non solo WP-Cron;
- verificare se il ramo legacy `RealEstate_Sync::run_scheduled_import()` e' ancora effettivamente attivo in produzione oppure e' solo residuo;
- verificare se i widget admin oggi commentati devono restare esclusi anche dal deploy incrementale operativo;
- verificare se in `wp-config.php` viene mai riattivato il legacy importer, nel qual caso `includes/class-realestate-sync-hook-logger.php` passerebbe da esclusione a file runtime.
