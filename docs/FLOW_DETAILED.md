# FLOW DETTAGLIATO - RealEstate Sync Plugin

**Data Creazione**: 03 Dicembre 2025
**Ora**: 06:05:42
**Versione Plugin**: 1.5.0 (Batch System)

Documentazione completa di TUTTI i flussi di import con riferimenti file:linea esatti.

**Scopo**: Mappare ogni singola chiamata di metodo per capire esattamente cosa succede durante l'import.

---

## 📋 INDICE

1. [Manual Import Flow](#1-manual-import-flow)
2. [Server Cron Continuation Flow](#2-server-cron-continuation-flow)
3. [Property Processing Deep Dive](#3-property-processing-deep-dive)
4. [Agency Processing Deep Dive](#4-agency-processing-deep-dive)
5. [Hash Checking e Skip Logic](#5-hash-checking-e-skip-logic)
6. [Database Operations](#6-database-operations)

---

## 1. MANUAL IMPORT FLOW

### Entry Point: User Click "Scarica e Importa Ora"

```
FRONTEND (admin/views/dashboard.php)
├─ jQuery AJAX call
├─ Action: 'realestate_sync_manual_import'
├─ Data: {nonce, mark_as_test}
└─ POST to: admin-ajax.php
```

### AJAX Handler

**File**: `admin/class-realestate-sync-admin.php`

```php
Line 708: public function handle_manual_import()
  │
  ├─ Line 709: check_ajax_referer('realestate_sync_nonce', 'nonce')
  ├─ Line 711: if (!current_user_can('manage_options')) → Unauthorized
  │
  ├─ Line 717-721: Get settings (hardcoded temporaneo)
  │   ├─ xml_url
  │   ├─ username
  │   └─ password
  │
  ├─ Line 726: $mark_as_test = $_POST['mark_as_test']
  │
  ├─ STEP 1: DOWNLOAD XML
  │   │
  │   ├─ Line 733: new RealEstate_Sync_XML_Downloader()
  │   └─ Line 734: $xml_file = $downloader->download_xml($url, $user, $pass)
  │       │
  │       └─ includes/class-realestate-sync-xml-downloader.php
  │           ├─ Line 45: public function download_xml($url, $username, $password)
  │           ├─ Line 60: Download .tar.gz via wp_remote_get()
  │           ├─ Line 85: Save to temp: /tmp/realestate_*.tar.gz
  │           ├─ Line 95: Extract via PharData (tar.gz)
  │           └─ Line 120: Return path: /tmp/realestate_*.xml
  │
  ├─ STEP 2: CALL BATCH ORCHESTRATOR
  │   │
  │   └─ Line 743: RealEstate_Sync_Batch_Orchestrator::process_xml_batch($xml_file, $mark_as_test)
  │       │
  │       └─ [Vai a: Batch Orchestrator Flow](#batch-orchestrator-flow)
  │
  └─ STEP 3: RETURN JSON RESPONSE
      │
      └─ Line 751-760: wp_send_json_success()
          ├─ session_id
          ├─ total_queued
          ├─ agencies_queued
          ├─ properties_queued
          ├─ first_batch_processed
          └─ complete (true/false)
```

---

### Batch Orchestrator Flow

**File**: `includes/class-realestate-sync-batch-orchestrator.php`

```php
Line 35: public static function process_xml_batch($xml_file, $mark_as_test)
  │
  ├─ Line 38: $session_id = 'import_' . uniqid('', true)
  │
  ├─ ═══════════════════════════════════════════════════════════
  ├─ STEP 1: INDEX & FILTER (TN/BZ only)
  ├─ ═══════════════════════════════════════════════════════════
  │   │
  │   ├─ Line 52: $xml = simplexml_load_file($xml_file)
  │   ├─ Line 62: $settings = get_option('realestate_sync_settings')
  │   ├─ Line 63: $enabled_provinces = $settings['enabled_provinces'] ?? ['TN', 'BZ']
  │   │
  │   ├─ AGENCIES EXTRACTION
  │   │   │
  │   │   ├─ Line 68: $agency_parser = new RealEstate_Sync_Agency_Parser()
  │   │   └─ Line 69: $agencies = $agency_parser->extract_agencies_from_xml($xml)
  │   │       │
  │   │       └─ includes/class-realestate-sync-agency-parser.php
  │   │           │
  │   │           ├─ Line 59: public function extract_agencies_from_xml($xml)
  │   │           ├─ Line 67: foreach ($xml->annuncio as $annuncio)
  │   │           │   │
  │   │           │   ├─ Line 70: Skip if deleted === '1'
  │   │           │   │
  │   │           │   ├─ BUGFIX 30-Nov-2025: Province filtering
  │   │           │   ├─ Line 76: $comune_istat = (string)$annuncio->info->comune_istat
  │   │           │   ├─ Line 77: $prefix = substr($comune_istat, 0, 3)
  │   │           │   ├─ Line 84-91: Skip if NOT 021xxx/022xxx
  │   │           │   │
  │   │           │   └─ Line 95-109: Extract agency data
  │   │           │       ├─ id
  │   │           │       ├─ ragione_sociale
  │   │           │       ├─ indirizzo, telefono, email
  │   │           │       ├─ url, logo
  │   │           │       └─ Store in $unique_agencies[$id]
  │   │           │
  │   │           └─ Line 146: return array_values($unique_agencies)
  │   │
  │   ├─ PROPERTIES FILTERING
  │   │   │
  │   │   ├─ Line 78: foreach ($xml->annuncio as $annuncio)
  │   │   │   │
  │   │   │   ├─ Line 81: Skip if deleted === '1' → $deleted_count++
  │   │   │   │
  │   │   │   ├─ Line 87: $comune_istat = (string)$annuncio->info->comune_istat
  │   │   │   ├─ Line 88: $prefix = substr($comune_istat, 0, 3)
  │   │   │   │
  │   │   │   ├─ Line 93: $is_tn = ($prefix === '022' && in_array('TN', $enabled))
  │   │   │   ├─ Line 94: $is_bz = ($prefix === '021' && in_array('BZ', $enabled))
  │   │   │   │
  │   │   │   ├─ Line 96: if ($is_tn || $is_bz)
  │   │   │   │   ├─ Line 97: $property_id = (string)$annuncio->info->id
  │   │   │   │   └─ Line 99: $properties[] = $property_id
  │   │   │   │
  │   │   │   └─ Line 102: else → $skipped_count++
  │   │   │
  │   │   └─ Result: Array of property IDs (TN/BZ only)
  │   │
  │   └─ Line 106-108: Log results
  │       ├─ Properties found (TN/BZ): 781
  │       ├─ Properties skipped (other): 27,844
  │       └─ Deleted items: ~100
  │
  ├─ ═══════════════════════════════════════════════════════════
  ├─ STEP 2: CREATE QUEUE
  ├─ ═══════════════════════════════════════════════════════════
  │   │
  │   ├─ Line 115: $queue_manager = new RealEstate_Sync_Queue_Manager()
  │   ├─ Line 118: $queue_manager->clear_session_queue($session_id)
  │   │
  │   ├─ Line 122-125: foreach ($agencies as $agency)
  │   │   └─ $queue_manager->add_agency($session_id, $agency['id'])
  │   │       │
  │   │       └─ includes/class-realestate-sync-queue-manager.php
  │   │           ├─ Line 75: public function add_agency($session_id, $agency_id)
  │   │           └─ Line 76: INSERT INTO queue (session_id, item_type='agency', item_id, status='pending')
  │   │
  │   ├─ Line 129-132: foreach ($properties as $property_id)
  │   │   └─ $queue_manager->add_property($session_id, $property_id)
  │   │       │
  │   │       └─ includes/class-realestate-sync-queue-manager.php
  │   │           ├─ Line 88: public function add_property($session_id, $property_id)
  │   │           └─ Line 89: INSERT INTO queue (session_id, item_type='property', item_id, status='pending')
  │   │
  │   └─ Line 136: Log: "Queue created: 30 agencies + 781 properties = 811 total"
  │
  ├─ ═══════════════════════════════════════════════════════════
  ├─ STEP 3: PROCESS FIRST BATCH (Immediate)
  ├─ ═══════════════════════════════════════════════════════════
  │   │
  │   ├─ Line 144-151: update_option('realestate_sync_background_import_progress')
  │   │   ├─ session_id
  │   │   ├─ xml_file_path
  │   │   ├─ mark_as_test
  │   │   ├─ start_time
  │   │   ├─ status: 'processing'
  │   │   └─ total_items
  │   │
  │   ├─ Line 154: $batch_processor = new RealEstate_Sync_Batch_Processor($session_id, $xml_file, $mark_as_test)
  │   │   │
  │   │   └─ includes/class-realestate-sync-batch-processor.php
  │   │       ├─ Line 87: public function __construct($session_id, $xml_file, $mark_as_test)
  │   │       ├─ Line 99: $this->session_id = $session_id
  │   │       ├─ Line 100: $this->xml_file_path = $xml_file
  │   │       ├─ Line 110: Initialize Import_Engine (for property processing)
  │   │       ├─ Line 113-114: Initialize Agency_Parser + Agency_Manager
  │   │       └─ Line 117: Initialize XML_Parser (GOLDEN)
  │   │
  │   └─ Line 155: $first_batch_result = $batch_processor->process_next_batch()
  │       │
  │       └─ [Vai a: Batch Processor Flow](#batch-processor-flow)
  │
  ├─ ═══════════════════════════════════════════════════════════
  ├─ STEP 4: SETUP CONTINUATION (if not complete)
  ├─ ═══════════════════════════════════════════════════════════
  │   │
  │   ├─ Line 166: if (!$first_batch_result['complete'])
  │   │   │
  │   │   ├─ Line 171: set_transient('realestate_sync_pending_batch', $session_id, 300)
  │   │   │   └─ TTL: 5 minutes (300 seconds)
  │   │   │
  │   │   └─ Line 173: Log: "Transient set - cron will continue processing"
  │   │
  │   └─ Line 175-186: else (complete)
  │       └─ update_option() → status: 'completed'
  │
  └─ Line 194-205: return results
      ├─ success: true
      ├─ session_id
      ├─ total_queued: 811
      ├─ agencies_queued: 30
      ├─ properties_queued: 781
      ├─ first_batch_processed: 10
      ├─ agencies_processed: 10 (or fewer)
      ├─ properties_processed: 0 (or more)
      ├─ complete: false
      └─ remaining: 801
```

---

### Batch Processor Flow

**File**: `includes/class-realestate-sync-batch-processor.php`

```php
Line 237: public function process_next_batch()
  │
  ├─ Line 240: $start_time = time()
  ├─ Line 241-244: Initialize counters (processed, errors, agencies, properties)
  │
  ├─ Line 247: $items = $this->queue_manager->get_next_batch($session_id, ITEMS_PER_BATCH)
  │   │
  │   └─ includes/class-realestate-sync-queue-manager.php
  │       ├─ Line 119: public function get_next_batch($session_id, $limit = 10)
  │       └─ Line 120-129: SELECT * FROM queue WHERE session_id=? AND status='pending' LIMIT ?
  │
  ├─ Line 264: foreach ($items as $item)
  │   │
  │   ├─ Line 266-268: Check timeout (50 seconds)
  │   │
  │   ├─ Line 272: $this->queue_manager->mark_processing($item->id)
  │   │   │
  │   │   └─ includes/class-realestate-sync-queue-manager.php
  │   │       ├─ Line 167: public function mark_processing($id)
  │   │       └─ Line 168: UPDATE queue SET status='processing', updated_at=NOW() WHERE id=?
  │   │
  │   ├─ Line 278-284: Process based on type
  │   │   │
  │   │   ├─ if (item_type === 'agency')
  │   │   │   │
  │   │   │   ├─ Line 279: $result = $this->process_agency($item)
  │   │   │   │   │
  │   │   │   │   └─ [Vai a: Agency Processing](#agency-processing)
  │   │   │   │
  │   │   │   └─ Line 280: $agencies_processed++
  │   │   │
  │   │   └─ else (property)
  │   │       │
  │   │       ├─ Line 282: $result = $this->process_property($item)
  │   │       │   │
  │   │       │   └─ [Vai a: Property Processing](#property-processing)
  │   │       │
  │   │       └─ Line 283: $properties_processed++
  │   │
  │   ├─ Line 287: $this->queue_manager->mark_done($item->id)
  │   │   │
  │   │   └─ includes/class-realestate-sync-queue-manager.php
  │   │       ├─ Line 179: public function mark_done($id)
  │   │       └─ Line 180: UPDATE queue SET status='completed', updated_at=NOW() WHERE id=?
  │   │
  │   ├─ Line 288: $processed++
  │   │
  │   └─ Line 292-298: catch (Exception $e)
  │       ├─ Line 294: $this->queue_manager->mark_error($item->id, $e->getMessage())
  │       │   │
  │       │   └─ includes/class-realestate-sync-queue-manager.php
  │       │       ├─ Line 192: public function mark_error($id, $error)
  │       │       ├─ Line 193: retry_count++
  │       │       ├─ Line 194: if (retry_count < MAX_RETRIES) → status='pending' (for retry)
  │       │       └─ Line 197: else → status='failed', error_message=$error
  │       │
  │       └─ Line 295: $errors++
  │
  ├─ Line 302: $is_complete = $this->queue_manager->is_session_complete($session_id)
  │   │
  │   └─ includes/class-realestate-sync-queue-manager.php
  │       ├─ Line 206: public function is_session_complete($session_id)
  │       └─ Line 207: SELECT COUNT(*) FROM queue WHERE session_id=? AND status IN ('pending','processing')
  │           └─ Returns: true if count=0, false otherwise
  │
  ├─ Line 303: $stats = $this->queue_manager->get_session_stats($session_id)
  │   │
  │   └─ includes/class-realestate-sync-queue-manager.php
  │       ├─ Line 214: public function get_session_stats($session_id)
  │       └─ Line 215: SELECT status, COUNT(*) FROM queue WHERE session_id=? GROUP BY status
  │
  └─ Line 307-315: return results
      ├─ success: true
      ├─ complete: bool
      ├─ processed: 10
      ├─ errors: 0
      ├─ agencies_processed: 10
      ├─ properties_processed: 0
      └─ stats: {pending, completed, failed counts}
```

---

## 2. SERVER CRON CONTINUATION FLOW

### Entry Point: Server Cron (ogni minuto)

```bash
* * * * * wget -q -O - "https://trentinoimmobiliare.it/wp-content/plugins/realestate-sync-plugin/batch-continuation.php?token=TrentinoImmo2025Secret!" >/dev/null 2>&1
```

**File**: `batch-continuation.php`

```php
Line 1: <?php
  │
  ├─ Line 17-20: Security Check
  │   └─ if ($_GET['token'] !== 'TrentinoImmo2025Secret!') → die('Forbidden')
  │
  ├─ Line 27-28: Load WordPress
  │   ├─ define('WP_USE_THEMES', false)
  │   └─ require_once('../../../wp-load.php')
  │
  ├─ Line 30-33: Check for pending batch
  │   └─ $pending_session = get_transient('realestate_sync_pending_batch')
  │       │
  │       ├─ if (!$pending_session)
  │       │   └─ echo "OK - No pending batch\n" → exit
  │       │
  │       └─ else → continue processing
  │
  ├─ Line 38-40: Get progress metadata
  │   ├─ $progress = get_option('realestate_sync_background_import_progress')
  │   ├─ $xml_file_path = $progress['xml_file_path']
  │   └─ $mark_as_test = $progress['mark_as_test']
  │
  ├─ Line 45: delete_transient('realestate_sync_pending_batch')
  │   └─ Prevent concurrent runs (safety mechanism)
  │
  ├─ Line 70-73: Process next batch
  │   ├─ $batch_processor = new RealEstate_Sync_Batch_Processor($session_id, $xml_file, $mark_as_test)
  │   └─ $result = $batch_processor->process_next_batch()
  │       │
  │       └─ [Usa stesso flusso: Batch Processor Flow](#batch-processor-flow)
  │
  ├─ Line 80-81: Update progress
  │   ├─ $progress['processed_items'] += $result['processed']
  │   └─ update_option('realestate_sync_background_import_progress', $progress)
  │
  ├─ Line 84-95: Check if more work needed
  │   │
  │   ├─ if (!$result['complete'])
  │   │   │
  │   │   ├─ Line 85: set_transient('realestate_sync_pending_batch', $session_id, 300)
  │   │   └─ Line 88: echo "OK - Batch processed, more pending\n"
  │   │
  │   └─ else (complete)
  │       │
  │       ├─ Line 91: $progress['status'] = 'completed'
  │       ├─ Line 92: update_option(...)
  │       └─ Line 93: echo "OK - All batches complete!\n"
  │
  └─ Line 105: http_response_code(200) → exit
```

---

## 3. PROPERTY PROCESSING DEEP DIVE

### Entry: Batch_Processor::process_property()

**File**: `includes/class-realestate-sync-batch-processor.php`

```php
Line 372: private function process_property($queue_item)
  │
  ├─ Line 373: $property_id = $queue_item->item_id
  │
  ├─ Line 378: $xml = simplexml_load_file($this->xml_file_path)
  │
  ├─ Line 382-397: Find property in XML
  │   │
  │   ├─ foreach ($xml->annuncio as $annuncio)
  │   │   │
  │   │   ├─ Line 384: $current_id = (string)$annuncio->info->id
  │   │   │
  │   │   └─ Line 386: if ($current_id === $property_id)
  │   │       │
  │   │       ├─ Line 389: $property_data = $this->xml_parser->parse_annuncio_xml($annuncio->asXML())
  │   │       │   │
  │   │       │   └─ includes/class-realestate-sync-xml-parser.php
  │   │       │       ├─ Line 250: public function parse_annuncio_xml($xml_string)
  │   │       │       ├─ Line 256: $xml = simplexml_load_string($xml_string)
  │   │       │       ├─ Line 260-450: Extract ALL fields
  │   │       │       │   ├─ id, titolo, descrizione
  │   │       │       │   ├─ prezzo, superficie, locali
  │   │       │       │   ├─ comune, provincia, regione, comune_istat
  │   │       │       │   ├─ categoria, contratto
  │   │       │       │   ├─ caratteristiche (array)
  │   │       │       │   ├─ immagini (array)
  │   │       │       │   └─ agenzia (nested object)
  │   │       │       │
  │   │       │       └─ Line 480: return $property_array
  │   │       │
  │   │       └─ Line 395: break
  │   │
  │   └─ Line 399: if (!$property_data) → throw Exception
  │
  └─ Line 412: $result = $this->import_engine->process_single_property($property_data)
      │
      └─ includes/class-realestate-sync-import-engine.php
          │
          ├─ Line 676: public function process_single_property($property_data)
          │
          ├─ STEP 1: Convert to v3 format
          │   │
          │   └─ Line 686: $converted = $this->convert_xml_to_v3_format($property_data)
          │       │
          │       └─ Line 925: private function convert_xml_to_v3_format($data)
          │           ├─ Line 935-1020: Map fields to v3 structure
          │           ├─ Line 980: Extract agency_data separately
          │           └─ Line 1090: return $converted_data
          │
          ├─ STEP 2: Map properties (PROTECTED Property_Mapper)
          │   │
          │   └─ Line 690: $mapped = $this->map_properties([$converted])
          │       │
          │       └─ Line 840: private function map_properties($properties)
          │           │
          │           ├─ foreach ($properties as $property)
          │           │   │
          │           │   └─ Line 851: $mapped = $this->property_mapper->map_property($property)
          │           │       │
          │           │       └─ includes/class-realestate-sync-property-mapper.php (PROTECTED v3.3)
          │           │           │
          │           │           ├─ Line 140: public function map_property($property_data)
          │           │           │
          │           │           ├─ Line 150-200: Basic fields
          │           │           │   ├─ property_title, property_description
          │           │           │   ├─ property_price, property_size
          │           │           │   ├─ property_rooms, property_bedrooms, property_bathrooms
          │           │           │   ├─ property_address, property_city, property_area
          │           │           │   └─ property_zip, property_country
          │           │           │
          │           │           ├─ Line 210-300: Categories & Taxonomies (80+ mappings)
          │           │           │   ├─ property_category (28 types)
          │           │           │   ├─ property_action_category (vendita/affitto)
          │           │           │   ├─ amenities (33+ checkboxes)
          │           │           │   └─ property_features (48+ details)
          │           │           │
          │           │           ├─ Line 310-380: Energy & Advanced
          │           │           │   ├─ energy_class (A4-G)
          │           │           │   ├─ maintenance_status
          │           │           │   ├─ position_type
          │           │           │   └─ micro_categories
          │           │           │
          │           │           ├─ Line 400-450: Gallery
          │           │           │   ├─ property_images (array of URLs)
          │           │           │   └─ property_images_meta (array with alt text, titles)
          │           │           │
          │           │           └─ Line 520: return $mapped_property
          │           │
          │           └─ Line 870: return $mapped_properties
          │
          ├─ STEP 3: Call WP Importer (GOLDEN API-based)
          │   │
          │   └─ Line 701: $result = $this->call_wp_importer($mapped[0], $property_data['id'])
          │       │
          │       └─ Line 1120: private function call_wp_importer($mapped_data, $property_id)
          │           │
          │           ├─ Line 1130: if (using API importer)
          │           │   │
          │           │   └─ $result = $this->wp_importer->process_property($mapped_data)
          │           │       │
          │           │       └─ includes/class-realestate-sync-wp-importer-api.php (PROTECTED v1.4)
          │           │           │
          │           │           ├─ Line 85: public function process_property($mapped_data)
          │           │           │
          │           │           ├─ STEP A: Check tracking (hash-based duplicate detection)
          │           │           │   │
          │           │           │   └─ Line 95: $change_status = $this->tracking_manager->check_property_changes($property_id, $new_hash)
          │           │           │       │
          │           │           │       └─ [Vai a: Hash Checking](#hash-checking)
          │           │           │
          │           │           ├─ STEP B: Skip if no changes (hash identical)
          │           │           │   │
          │           │           │   └─ Line 100-105: if (!$change_status['has_changed']) → return skip
          │           │           │
          │           │           ├─ STEP C: Process property by action
          │           │           │   │
          │           │           │   └─ Line 110: $result = $this->process_property_by_action($mapped_data, $change_status)
          │           │           │       │
          │           │           │       └─ Line 200: private function process_property_by_action($data, $change_status)
          │           │           │           │
          │           │           │           ├─ if ($change_status['action'] === 'insert')
          │           │           │           │   │
          │           │           │           │   └─ Line 210: $post_id = $this->create_property_via_api($data)
          │           │           │           │       │
          │           │           │           │       └─ Line 300: private function create_property_via_api($data)
          │           │           │           │           ├─ Line 310: Format data for WPResidence API
          │           │           │           │           ├─ Line 350: POST /wp-json/wp/v2/estate_property
          │           │           │           │           └─ Line 380: return $property_id
          │           │           │           │
          │           │           │           └─ else if ($change_status['action'] === 'update')
          │           │           │               │
          │           │           │               └─ Line 250: $post_id = $this->update_property_via_api($wp_post_id, $data)
          │           │           │                   │
          │           │           │                   └─ Line 450: private function update_property_via_api($post_id, $data)
          │           │           │                       ├─ Line 460: Format data for API
          │           │           │                       ├─ Line 500: POST /wp-json/wp/v2/estate_property/:id
          │           │           │                       └─ Line 530: return $post_id
          │           │           │
          │           │           ├─ STEP D: Setup gallery (4 gallery systems)
          │           │           │   │
          │           │           │   └─ Line 120: $this->setup_gallery_system($post_id, $mapped_data['property_images'])
          │           │           │       │
          │           │           │       └─ Line 600: private function setup_gallery_system($post_id, $images)
          │           │           │           ├─ Line 610: Download images via Media_Deduplicator
          │           │           │           ├─ Line 650: Setup gallery meta (4 formats)
          │           │           │           │   ├─ Format 1: property_images (array of attachment IDs)
          │           │           │           │   ├─ Format 2: _thumbnail_id (featured image)
          │           │           │           │   ├─ Format 3: wpresidence_images (serialized array)
          │           │           │           │   └─ Format 4: _gallery (comma-separated IDs)
          │           │           │           └─ Line 720: return $attachment_ids
          │           │           │
          │           │           ├─ STEP E: Link to agency
          │           │           │   │
          │           │           │   └─ Line 130: $this->link_to_agency($post_id, $data['agency_id'])
          │           │           │       │
          │           │           │       └─ Line 800: private function link_to_agency($post_id, $agency_id)
          │           │           │           ├─ Line 810: Lookup agency by XML ID
          │           │           │           ├─ Line 820: update_post_meta($post_id, 'property_agent', $wp_agency_id)
          │           │           │           └─ Line 830: return $wp_agency_id
          │           │           │
          │           │           ├─ STEP F: Update tracking database
          │           │           │   │
          │           │           │   └─ Line 140: $this->tracking_manager->update_tracking($property_id, $post_id, $new_hash)
          │           │           │       │
          │           │           │       └─ includes/class-realestate-sync-tracking-manager.php
          │           │           │           ├─ Line 150: public function update_tracking($property_id, $wp_post_id, $hash)
          │           │           │           └─ Line 151: INSERT/UPDATE kre_realestate_sync_tracking
          │           │           │               ├─ property_id (XML ID)
          │           │           │               ├─ wp_post_id (WordPress ID)
          │           │           │               ├─ property_hash (MD5)
          │           │           │               ├─ last_import_date
          │           │           │               └─ status: 'active'
          │           │           │
          │           │           └─ Line 180: return result
          │           │               ├─ success: true
          │           │               ├─ post_id
          │           │               ├─ action: 'created|updated'
          │           │               └─ gallery_setup: true
          │           │
          │           └─ Line 1140: else (legacy importer)
          │               └─ (not used - deprecated)
          │
          ├─ STEP 4: Mark as test (if requested)
          │   │
          │   └─ Line 710: if ($mark_as_test)
          │       └─ update_post_meta($post_id, '_test_import', 1)
          │
          ├─ STEP 5: Store agency data for PHASE 2 linking
          │   │
          │   └─ Line 720: Store agency XML ID in property meta
          │       └─ update_post_meta($post_id, 'property_xml_agency_id', $agency_id)
          │
          └─ Line 750: return result
              ├─ success: true
              ├─ post_id
              ├─ action: 'created|updated|skipped'
              └─ message
```

---

## 4. AGENCY PROCESSING DEEP DIVE

### Entry: Batch_Processor::process_agency()

**File**: `includes/class-realestate-sync-batch-processor.php`

```php
Line 327: private function process_agency($queue_item)
  │
  ├─ Line 328: $agency_id = $queue_item->item_id
  │
  ├─ Line 331: $xml = simplexml_load_file($this->xml_file_path)
  │
  ├─ Line 334: $all_agencies = $this->agency_parser->extract_agencies_from_xml($xml)
  │   │
  │   └─ includes/class-realestate-sync-agency-parser.php (PROTECTED v1.3.1)
  │       │
  │       └─ [Già visto in Orchestrator - STEP 1](#batch-orchestrator-flow)
  │
  ├─ Line 338-343: Find specific agency in array
  │   │
  │   └─ foreach ($all_agencies as $agency)
  │       └─ if ($agency['id'] === $agency_id) → found
  │
  ├─ Line 345: if (!$agency_data) → throw Exception
  │
  ├─ Line 350-351: Get mark_as_test flag from progress option
  │
  └─ Line 356: $import_results = $this->agency_manager->import_agencies([$agency_data], $mark_as_test)
      │
      └─ includes/class-realestate-sync-agency-manager.php (PROTECTED v1.0)
          │
          ├─ Line 71: public function import_agencies($agencies, $mark_as_test)
          │
          ├─ Line 88: foreach ($agencies as $agency_data)
          │   │
          │   ├─ Line 91: $converted_data = $this->convert_parser_data_to_manager_format($agency_data)
          │   │   │
          │   │   └─ Line 138: private function convert_parser_data_to_manager_format($parser_data)
          │   │       ├─ Line 140-152: Map fields
          │   │       │   ├─ name (ragione_sociale)
          │   │       │   ├─ xml_agency_id (id)
          │   │       │   ├─ address, phone, email
          │   │       │   ├─ website, logo_url
          │   │       │   ├─ contact_person, vat_number
          │   │       │   └─ province, city, mobile
          │   │       │
          │   │       └─ Line 153: return converted_array
          │   │
          │   ├─ Line 94: $existing_id = $this->find_agency_by_xml_id($converted_data['xml_agency_id'])
          │   │   │
          │   │   └─ Line 185: private function find_agency_by_xml_id($xml_id)
          │   │       ├─ Line 187: WP_Query for post_type='estate_agency' (FIXED)
          │   │       ├─ Line 188: meta_key='agency_xml_id', meta_value=$xml_id
          │   │       ├─ Line 196: if (have_posts()) → return post ID
          │   │       └─ Line 200: else → return false
          │   │
          │   ├─ IF exists (Line 96-104):
          │   │   │
          │   │   └─ Line 98: $result = $this->update_agency_via_api($existing_id, $converted_data, $mark_as_test)
          │   │       │
          │   │       └─ Line 250: private function update_agency_via_api($agency_id, $agency_data, $mark_as_test)
          │   │           ├─ Line 252: Format for API via API_Writer
          │   │           ├─ Line 255: $result = $this->api_writer->update_agency($agency_id, $api_body)
          │   │           │   │
          │   │           │   └─ includes/class-realestate-sync-wpresidence-agency-api-writer.php
          │   │           │       ├─ Line 120: public function update_agency($agency_id, $api_body)
          │   │           │       ├─ Line 295: POST /wpresidence/v1/agency/add (crea estate_agency)
          │   │           │       └─ Line 160: return result
          │   │           │
          │   │           ├─ Line 264: update_post_meta($agency_id, 'agency_xml_id', $xml_id)
          │   │           └─ Line 270: return true
          │   │
          │   └─ ELSE new agency (Line 106-113):
          │       │
          │       └─ Line 107: $new_id = $this->create_agency_via_api($converted_data, $mark_as_test)
          │           │
          │           └─ Line 210: private function create_agency_via_api($agency_data, $mark_as_test)
          │               │
          │               ├─ Line 212: Format for API via API_Writer
          │               │
          │               ├─ Line 215: $result = $this->api_writer->create_agency($api_body)
          │               │   │
          │               │   └─ includes/class-realestate-sync-wpresidence-agency-api-writer.php
          │               │       ├─ Line 50: public function create_agency($api_body)
          │               │       ├─ Line 60: POST /wp-json/wp/v2/estate_agent
          │               │       ├─ Line 90: Download logo if available
          │               │       │   ├─ wp_remote_get($logo_url)
          │               │       │   ├─ wp_upload_bits()
          │               │       │   ├─ wp_insert_attachment()
          │               │       │   └─ update_post_meta($agency_id, 'thumbnail_id', $attachment_id)
          │               │       │
          │               │       └─ Line 110: return result (agency_id)
          │               │
          │               ├─ Line 227: update_post_meta($agency_id, 'agency_xml_id', $xml_id)
          │               │   └─ CRITICAL: Used for property→agency linking
          │               │
          │               ├─ Line 235: if ($mark_as_test)
          │               │   └─ update_post_meta($agency_id, '_test_import', 1)
          │               │
          │               └─ Line 239: return $agency_id
          │
          └─ Line 127: return import_stats
              ├─ total: 1
              ├─ imported: 1 (or 0 if updated)
              ├─ updated: 0 (or 1 if exists)
              ├─ skipped: 0
              ├─ errors: 0
              └─ with_logo: 1 (if logo downloaded)
```

---

## 5. HASH CHECKING E SKIP LOGIC

### Hash Generation e Comparison

**File**: `includes/class-realestate-sync-tracking-manager.php`

```php
Line 100: public function check_property_changes($property_id, $new_hash)
  │
  ├─ Line 105: $existing = $this->get_tracking_record($property_id)
  │   │
  │   └─ Line 50: private function get_tracking_record($property_id)
  │       ├─ Line 55: SELECT * FROM kre_realestate_sync_tracking WHERE property_id=?
  │       └─ Line 70: return $record (or null if not found)
  │
  ├─ IF NOT exists (Line 110-116):
  │   │
  │   └─ return [
  │       'has_changed' => true,
  │       'action' => 'insert',
  │       'reason' => 'new_property',
  │       'wp_post_id' => null
  │   ]
  │
  ├─ IF hash DIFFERENT (Line 120-128):
  │   │
  │   └─ return [
  │       'has_changed' => true,
  │       'action' => 'update',
  │       'reason' => 'data_changed',
  │       'wp_post_id' => $existing['wp_post_id'],
  │       'old_hash' => $existing['property_hash'],
  │       'new_hash' => $new_hash
  │   ]
  │
  └─ ELSE hash IDENTICAL (Line 130-137):
      │
      └─ return [
          'has_changed' => false,
          'action' => 'skip',
          'reason' => 'no_changes',
          'wp_post_id' => $existing['wp_post_id'],
          'hash' => $existing['property_hash']
      ]
```

### Hash Generation

**File**: `includes/class-realestate-sync-wp-importer-api.php`

```php
Line 90: private function generate_property_hash($mapped_data)
  │
  ├─ Line 95: Serialize important fields only (not images, not meta)
  │   │
  │   ├─ property_title
  │   ├─ property_description
  │   ├─ property_price
  │   ├─ property_size
  │   ├─ property_rooms
  │   ├─ property_address
  │   ├─ property_category
  │   └─ caratteristiche (features array)
  │
  ├─ Line 120: $serialized = serialize($hash_data)
  │
  └─ Line 125: return md5($serialized)
```

### Skip Mode Logic

**File**: `includes/class-realestate-sync-import-engine.php`

```php
Line 723: // OPTIONAL SKIP MODE: Skip only if explicitly enabled AND no changes
  │
  ├─ Line 724: $skip_unchanged_mode = get_option('realestate_sync_skip_unchanged_mode', false)
  │
  ├─ Line 726: if ($skip_unchanged_mode && !$change_status['has_changed'])
  │   │
  │   ├─ Line 727: $this->stats['skipped_properties']++
  │   ├─ Line 728: Log: "Property SKIPPED - unchanged (skip mode enabled)"
  │   └─ Line 729: return (early exit)
  │
  └─ ELSE: Continue processing
      │
      └─ Note: Even if skip_mode=false, properties with identical hash
              are NOT processed in process_property_by_action()
              because action='skip' has NO handler
```

**IMPORTANTE**: Anche con `skip_unchanged_mode=false`, le proprietà con hash identico NON vengono processate perché `process_property_by_action()` ha solo handler per 'insert' e 'update', NON per 'skip'.

---

## 6. DATABASE OPERATIONS

### Queue Table Operations

**Tabella**: `{$wpdb->prefix}realestate_import_queue`

**CREATE** (Batch_Orchestrator):
```php
Line 123: add_agency($session_id, $agency_id)
  └─ INSERT INTO queue (session_id, item_type='agency', item_id, status='pending', created_at)

Line 130: add_property($session_id, $property_id)
  └─ INSERT INTO queue (session_id, item_type='property', item_id, status='pending', created_at)
```

**READ** (Batch_Processor):
```php
Line 247: get_next_batch($session_id, $limit)
  └─ SELECT * FROM queue
     WHERE session_id=? AND status='pending'
     ORDER BY id ASC
     LIMIT ?
```

**UPDATE** (Batch_Processor):
```php
Line 272: mark_processing($id)
  └─ UPDATE queue SET status='processing', updated_at=NOW() WHERE id=?

Line 287: mark_done($id)
  └─ UPDATE queue SET status='completed', updated_at=NOW() WHERE id=?

Line 294: mark_error($id, $error)
  └─ UPDATE queue
     SET retry_count=retry_count+1,
         status=(CASE WHEN retry_count+1 < 3 THEN 'pending' ELSE 'failed' END),
         error_message=?,
         updated_at=NOW()
     WHERE id=?
```

**CHECK** (Batch_Processor):
```php
Line 302: is_session_complete($session_id)
  └─ SELECT COUNT(*) FROM queue
     WHERE session_id=? AND status IN ('pending','processing')
  └─ Returns: true if count=0, false otherwise

Line 303: get_session_stats($session_id)
  └─ SELECT status, COUNT(*) as count
     FROM queue
     WHERE session_id=?
     GROUP BY status
```

**DELETE** (Batch_Orchestrator):
```php
Line 118: clear_session_queue($session_id)
  └─ DELETE FROM queue WHERE session_id=?
```

---

### Tracking Table Operations

**Tabella**: `{$wpdb->prefix}realestate_sync_tracking`

**CHECK** (WP_Importer_API):
```php
Line 95: check_property_changes($property_id, $new_hash)
  └─ SELECT * FROM tracking WHERE property_id=?
  └─ Compare hash, return change_status array
```

**UPDATE** (WP_Importer_API):
```php
Line 140: update_tracking($property_id, $wp_post_id, $hash)
  └─ INSERT INTO tracking (property_id, wp_post_id, property_hash, last_import_date, status)
     ON DUPLICATE KEY UPDATE
         wp_post_id=VALUES(wp_post_id),
         property_hash=VALUES(property_hash),
         last_import_date=NOW(),
         status='active',
         updated_at=NOW()
```

**READ** (Admin AJAX):
```php
get_import_statistics()
  └─ SELECT
       COUNT(*) as total_tracked,
       COUNT(CASE WHEN wp_post_id IS NULL THEN 1 END) as orphans,
       COUNT(CASE WHEN status='active' THEN 1 END) as active
     FROM tracking
```

---

## SUMMARY - Flow Completo in Sintesi

```
USER CLICK
  ↓
Admin::handle_manual_import() [admin/class-realestate-sync-admin.php:708]
  ↓
XML_Downloader::download_xml() [includes/class-realestate-sync-xml-downloader.php:45]
  ↓
Batch_Orchestrator::process_xml_batch() [includes/class-realestate-sync-batch-orchestrator.php:35]
  ├─ STEP 1: Index & Filter (TN/BZ)
  │   ├─ Agency_Parser::extract_agencies_from_xml() [includes/class-realestate-sync-agency-parser.php:59]
  │   └─ Filter properties by comune_istat (021xxx/022xxx)
  ├─ STEP 2: Create Queue
  │   ├─ Queue_Manager::add_agency() × 30
  │   └─ Queue_Manager::add_property() × 781
  ├─ STEP 3: Process First Batch
  │   └─ Batch_Processor::process_next_batch() [includes/class-realestate-sync-batch-processor.php:237]
  │       ├─ FOR each item (max 10):
  │       │   ├─ IF agency:
  │       │   │   └─ process_agency() → Agency_Manager::import_agencies() → API create/update
  │       │   └─ ELSE property:
  │       │       └─ process_property()
  │       │           ├─ XML_Parser::parse_annuncio_xml() [GOLDEN parsing]
  │       │           └─ Import_Engine::process_single_property()
  │       │               ├─ convert_xml_to_v3_format()
  │       │               ├─ Property_Mapper::map_property() [PROTECTED v3.3 - 80+ fields]
  │       │               └─ WP_Importer_API::process_property() [PROTECTED v1.4]
  │       │                   ├─ check_property_changes() → hash comparison
  │       │                   ├─ IF changed:
  │       │                   │   ├─ create_property_via_api() OR update_property_via_api()
  │       │                   │   ├─ setup_gallery_system() (4 gallery formats)
  │       │                   │   ├─ link_to_agency()
  │       │                   │   └─ update_tracking() → save hash
  │       │                   └─ ELSE: skip (hash identical)
  │       └─ mark_done() OR mark_error()
  └─ STEP 4: Setup Continuation
      └─ set_transient('realestate_sync_pending_batch', $session_id, 300)
  ↓
SERVER CRON (ogni minuto)
  ↓
batch-continuation.php [Line 1]
  ├─ Check transient [Line 30]
  ├─ IF pending batch exists:
  │   ├─ Delete transient (prevent concurrent) [Line 45]
  │   ├─ Batch_Processor::process_next_batch() [Line 73]
  │   │   └─ [Stesso flow di STEP 3]
  │   └─ IF not complete: re-set transient [Line 85]
  └─ ELSE: echo "No pending batch" [Line 32]
```

---

## METODI PROTETTI - NON MODIFICARE

### 🛡️ GOLDEN CODE (Verified Working 30-Nov-2025)

1. **Property_Mapper::map_property()** [includes/class-realestate-sync-property-mapper.php:140]
   - 80+ field mappings (categories, amenities, energy class, etc.)
   - Version: 3.3
   - DO NOT MODIFY

2. **Agency_Parser::extract_agencies_from_xml()** [includes/class-realestate-sync-agency-parser.php:59]
   - With province filtering bug fix applied
   - Version: 1.3.1
   - ALLOW BUG FIXES ONLY

3. **WP_Importer_API::process_property()** [includes/class-realestate-sync-wp-importer-api.php:85]
   - API-based property creation
   - Hash-based duplicate detection
   - 4-gallery-system compatibility
   - Version: 1.4
   - DO NOT MODIFY

4. **Agency_Manager::import_agencies()** [includes/class-realestate-sync-agency-manager.php:71]
   - API-based agency creation with logos
   - Version: 1.0
   - DO NOT MODIFY

5. **XML_Parser::parse_annuncio_xml()** [includes/class-realestate-sync-xml-parser.php:250]
   - Streaming parser with XMLReader
   - GOLDEN parsing logic
   - DO NOT MODIFY

---

## FILE IMPORTANTI CON LINE NUMBERS

| File | Lines | Key Methods |
|------|-------|-------------|
| admin/class-realestate-sync-admin.php | 2500+ | Line 708: handle_manual_import() |
| includes/class-realestate-sync-batch-orchestrator.php | 208 | Line 35: process_xml_batch() |
| includes/class-realestate-sync-batch-processor.php | 500+ | Line 237: process_next_batch()<br>Line 327: process_agency()<br>Line 372: process_property() |
| includes/class-realestate-sync-queue-manager.php | 250+ | Line 75: add_agency()<br>Line 88: add_property()<br>Line 119: get_next_batch() |
| includes/class-realestate-sync-agency-parser.php | 200+ | Line 59: extract_agencies_from_xml() |
| includes/class-realestate-sync-property-mapper.php | 600+ | Line 140: map_property() [PROTECTED] |
| includes/class-realestate-sync-wp-importer-api.php | 900+ | Line 85: process_property() [PROTECTED] |
| includes/class-realestate-sync-agency-manager.php | 873 | Line 71: import_agencies() [PROTECTED] |
| includes/class-realestate-sync-tracking-manager.php | 300+ | Line 100: check_property_changes() |
| batch-continuation.php | 115 | Line 1: Server cron endpoint |

---

**Data Creazione**: 03-Dic-2025
**Versione Plugin**: 1.5.0 (Batch System)
**Autore**: Claude Code Analysis
