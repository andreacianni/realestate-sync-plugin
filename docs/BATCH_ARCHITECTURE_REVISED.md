# Batch System Architecture - REVISED

**Data**: 1 Dicembre 2025
**Basato su**: Analisi e visione dell'utente

---

## 🎯 PRINCIPIO FONDAMENTALE

**I tre entry point (A, B, C) differiscono SOLO nell'acquisizione file.**

**Dopo l'acquisizione, tutti usano lo STESSO flusso di batch processing.**

---

## 📊 ARCHITETTURA

### LAYER 1: File Acquisition (DIVERSO per A/B/C)

```php
// ═══════════════════════════════════════════════════════════
// A: Button "Processa File XML" (Manual Upload)
// ═══════════════════════════════════════════════════════════
function handle_process_test_file() {
    // Upload from filesystem
    $uploaded_file = $_FILES['test_xml_file'];
    $xml_file = save_uploaded_file($uploaded_file);

    // ↓ CALL SHARED PROCESSOR
    return process_xml_batch($xml_file, $mark_as_test);
}

// ═══════════════════════════════════════════════════════════
// B: Button "Scarica e Importa Ora" (Manual Download)
// ═══════════════════════════════════════════════════════════
function handle_manual_import() {
    // Download from server
    $downloader = new RealEstate_Sync_XML_Downloader();
    $xml_file = $downloader->download_xml($url, $user, $pass);

    // ↓ CALL SHARED PROCESSOR
    return process_xml_batch($xml_file, $mark_as_test);
}

// ═══════════════════════════════════════════════════════════
// C: Cron "Import Notturno" (Scheduled Download)
// ═══════════════════════════════════════════════════════════
function handle_scheduled_import() {
    // Download from server (via cron)
    $downloader = new RealEstate_Sync_XML_Downloader();
    $xml_file = $downloader->download_xml($url, $user, $pass);

    // ↓ CALL SHARED PROCESSOR
    return process_xml_batch($xml_file, false); // Not test mode
}
```

---

### LAYER 2: Shared Batch Processing (IDENTICO per A/B/C)

```php
/**
 * Shared batch processor - used by ALL entry points
 *
 * @param string $xml_file      Path to XML file
 * @param bool   $mark_as_test  Mark items as test
 * @return array Processing results
 */
function process_xml_batch($xml_file, $mark_as_test = false) {

    // ═════════════════════════════════════════════════════════
    // STEP 1: INDEX & FILTER
    // ═════════════════════════════════════════════════════════
    error_log("[BATCH] STEP 1: Indexing XML and filtering TN/BZ");

    $xml = simplexml_load_file($xml_file);

    // Get enabled provinces from settings
    $settings = get_option('realestate_sync_settings', array());
    $enabled_provinces = $settings['enabled_provinces'] ?? array('TN', 'BZ');

    // Index agencies (with province filter applied by Agency_Parser)
    $agency_parser = new RealEstate_Sync_Agency_Parser();
    $agencies = $agency_parser->extract_agencies_from_xml($xml);

    // Index properties (filter by comune_istat)
    $properties = array();
    foreach ($xml->annuncio as $annuncio) {
        // Skip deleted
        if ((string)$annuncio->deleted === '1') continue;

        // Filter by province (comune_istat)
        $comune_istat = (string)($annuncio->info->comune_istat ?? '');
        $prefix = substr($comune_istat, 0, 3);

        if (($prefix === '022' && in_array('TN', $enabled_provinces)) ||
            ($prefix === '021' && in_array('BZ', $enabled_provinces))) {

            $property_id = (string)$annuncio->info->id;
            if (!empty($property_id)) {
                $properties[] = $property_id;
            }
        }
    }

    error_log("[BATCH] Found: " . count($agencies) . " agencies, " . count($properties) . " properties (TN/BZ only)");

    // ═════════════════════════════════════════════════════════
    // STEP 2: CREATE QUEUE
    // ═════════════════════════════════════════════════════════
    error_log("[BATCH] STEP 2: Creating queue");

    $session_id = 'import_' . uniqid('', true);
    $queue_manager = new RealEstate_Sync_Queue_Manager();

    // Clear any existing queue for this session
    $queue_manager->clear_session_queue($session_id);

    // Add agencies to queue (higher priority)
    foreach ($agencies as $agency) {
        $queue_manager->add_agency($session_id, $agency['id']);
    }

    // Add properties to queue
    foreach ($properties as $property_id) {
        $queue_manager->add_property($session_id, $property_id);
    }

    $total_queued = count($agencies) + count($properties);
    error_log("[BATCH] Queue created: {$total_queued} items");

    // ═════════════════════════════════════════════════════════
    // STEP 3: PROCESS FIRST BATCH (Immediate)
    // ═════════════════════════════════════════════════════════
    error_log("[BATCH] STEP 3: Processing first batch (10 items max)");

    // Save XML file path for batch processor
    update_option('realestate_sync_background_import_progress', array(
        'session_id' => $session_id,
        'xml_file_path' => $xml_file,
        'mark_as_test' => $mark_as_test,
        'start_time' => time(),
        'status' => 'processing'
    ));

    // Process first batch immediately
    $batch_processor = new RealEstate_Sync_Batch_Processor($session_id, $xml_file);
    $first_batch_result = $batch_processor->process_next_batch();

    error_log("[BATCH] First batch complete: {$first_batch_result['processed']} items processed");

    // ═════════════════════════════════════════════════════════
    // STEP 4: SETUP CONTINUATION (Cron)
    // ═════════════════════════════════════════════════════════
    if (!$first_batch_result['complete']) {
        error_log("[BATCH] STEP 4: Setting up cron continuation");
        set_transient('realestate_sync_pending_batch', $session_id, 300);
        error_log("[BATCH] Transient set - cron will continue processing");
    } else {
        error_log("[BATCH] All items processed in first batch - COMPLETE!");
    }

    return array(
        'success' => true,
        'session_id' => $session_id,
        'total_queued' => $total_queued,
        'first_batch_processed' => $first_batch_result['processed'],
        'complete' => $first_batch_result['complete']
    );
}
```

---

## 🔧 IMPLEMENTAZIONE

### File da Creare/Modificare

**1. Nuovo file: `includes/class-realestate-sync-batch-orchestrator.php`**
```php
<?php
/**
 * Batch Orchestrator
 *
 * Shared batch processing logic used by all entry points.
 * Handles: Index → Filter → Queue → Process
 */
class RealEstate_Sync_Batch_Orchestrator {

    public static function process_xml_batch($xml_file, $mark_as_test = false) {
        // Implementation as shown above
    }
}
```

**2. Modifica: `admin/class-realestate-sync-admin.php`**
```php
// Button A
public function handle_process_test_file() {
    // ... upload file ...

    // Call shared processor
    $result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch(
        $temp_file,
        $mark_as_test
    );

    wp_send_json_success($result);
}

// Button B
public function handle_manual_import() {
    // ... download file ...

    // Call shared processor
    $result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch(
        $xml_file,
        $mark_as_test
    );

    wp_send_json_success($result);
}
```

**3. Modifica: `includes/class-realestate-sync-cron-manager.php`**
```php
// Cron C
public function execute_scheduled_import() {
    // ... download file ...

    // Call shared processor
    $result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch(
        $xml_file,
        false // not test mode
    );

    return $result;
}
```

---

## ✅ VANTAGGI

1. **DRY** (Don't Repeat Yourself)
   - Batch logic in UN SOLO posto
   - Modifiche facili e sicure

2. **Testabilità**
   - Test una funzione = test tutto
   - Isolamento facile

3. **Debugging**
   - Un solo flusso da debuggare
   - Log consistency

4. **Manutenibilità**
   - Fix un bug = fix ovunque
   - No duplicazione codice

---

## 🔍 VERIFICHE NECESSARIE

### 1. Table Name Issue (RISOLTO?)

Il Queue_Manager usa:
```php
$this->table_name = $wpdb->prefix . 'realestate_import_queue';
```

Dovrebbe produrre `kre_realestate_import_queue` ✅

**Verifica**: La tabella esiste con questo nome esatto?

### 2. Class Loading

**Verifica**: Tutti i file sono caricati?
- `class-realestate-sync-batch-processor.php`
- `class-realestate-sync-queue-manager.php`
- `class-realestate-sync-agency-parser.php` (protected)

### 3. Queue Operations

**Test**:
```php
$qm = new RealEstate_Sync_Queue_Manager();
$qm->add_agency('test_session', '123');
// Verify in DB: SELECT * FROM kre_realestate_import_queue;
```

---

## 📋 PROSSIMI STEP

1. **Crea Batch_Orchestrator** con funzione shared
2. **Modifica Button A** per usare Orchestrator
3. **Test Button A** con file piccolo
4. **Se funziona** → Modifica Button B
5. **Test Button B** con download
6. **Poi** → Setup Cron C

---

**Questa architettura rispecchia la tua visione?** ☕

Vuoi che implementiamo il Batch_Orchestrator?
