# Debug System Unificato - Design Document

**Data**: 03 Dicembre 2025
**Versione**: 1.0
**Status**: 🎯 DESIGN PHASE

---

## 🎯 Obiettivo

Implementare un sistema di debug unificato **a livelli "cipolla"** che tracci il flusso dal click/trigger fino alla chiusura, con verbosity configurabile:
- Trace ID univoco per ogni sessione
- Timestamp precisi per ogni operazione
- **Livelli disabilitabili progressivamente** (da TRACE dettagliato a PRODUCTION minimale)
- Meta field tracking (salvataggio e ricerca)
- Query tracking (quali query vengono eseguite)
- API call tracking (request/response)

### 🧅 Sistema a Cipolla (Onion Logging)

```
┌─────────────────────────────────────────┐
│ LEVEL 5: TRACE (massimo dettaglio)     │  ← Debug fase sviluppo
│  ├─ Stack traces                       │
│  ├─ Performance microseconds           │
│  └─ Variable dumps                     │
├─────────────────────────────────────────┤
│ LEVEL 4: DEBUG (dettagli tecnici)      │  ← Debug attivo
│  ├─ Database queries (args + results)  │
│  ├─ Meta operations (save/search)      │
│  └─ Internal state changes             │
├─────────────────────────────────────────┤
│ LEVEL 3: INFO (flusso operazioni)      │  ← Monitoring normale
│  ├─ Import started/completed           │
│  ├─ Agency/Property created/updated    │
│  └─ API calls (endpoint + success)     │
├─────────────────────────────────────────┤
│ LEVEL 2: WARNING (problemi non fatali) │  ← Always on
│  ├─ Missing optional fields            │
│  ├─ Skipped items                      │
│  └─ Degraded performance               │
├─────────────────────────────────────────┤
│ LEVEL 1: ERROR (errori bloccanti)      │  ← Always on
│  ├─ API failures                       │
│  ├─ Import failures                    │
│  └─ Critical errors                    │
├─────────────────────────────────────────┤
│ LEVEL 0: CRITICAL (sistema non usabile)│  ← Always on
│  └─ Fatal errors                       │
└─────────────────────────────────────────┘
```

**Configurazione per fase**:
- **Fase DEBUG** (ora): LEVEL 5 (TRACE) - tutto attivo
- **Fase TEST**: LEVEL 4 (DEBUG) - no stack traces
- **Fase STAGING**: LEVEL 3 (INFO) - no query details
- **PRODUZIONE**: LEVEL 2 (WARNING) - solo warning/error/critical

---

## 📊 Requisiti Funzionali

### 1. Session Tracking
- Ogni import deve avere un **Trace ID univoco**
- Format: `import_{timestamp}_{random}` (es: `import_20251203_abc123`)
- Trace ID deve persistere attraverso:
  - AJAX calls
  - Batch processing
  - Background continuation
  - Cron jobs

### 2. Event Tracking
Tracciare TUTTI gli eventi:
- ✅ Entry point (click button, cron trigger)
- ✅ Download XML
- ✅ Parse XML (agencies + properties count)
- ✅ Queue creation (items inserted)
- ✅ Batch processing start/end
- ✅ Each agency create/update (con API call)
- ✅ Each property create/update (con API call)
- ✅ Meta field operations (save/search)
- ✅ Database queries (WP_Query args + results)
- ✅ API calls (endpoint, body, response)
- ✅ Errors/Warnings/Success
- ✅ Exit point (completion or interruption)

### 3. Structured Logging
Formato log consistente:
```
[TRACE_ID] [TIMESTAMP] [LEVEL] [COMPONENT] Message {context}
```

Esempio:
```
[import_20251203_abc123] [2025-12-03 08:30:15] [INFO] [ORCHESTRATOR] Starting batch import {file: export.xml, size: 314MB}
[import_20251203_abc123] [2025-12-03 08:30:16] [INFO] [PARSER] Found agencies {count: 30}
[import_20251203_abc123] [2025-12-03 08:30:17] [DEBUG] [AGENCY_MANAGER] Finding agency by XML ID {xml_id: "1", meta_key: "agency_xml_id"}
[import_20251203_abc123] [2025-12-03 08:30:17] [DEBUG] [WP_QUERY] Executed query {post_type: "estate_agency", meta_key: "agency_xml_id", meta_value: "1", found: 0}
[import_20251203_abc123] [2025-12-03 08:30:18] [DEBUG] [API_WRITER] Creating agency via API {endpoint: "/wpresidence/v1/agency/add", body: {...}}
[import_20251203_abc123] [2025-12-03 08:30:19] [INFO] [API_WRITER] Agency created {agency_id: 123}
[import_20251203_abc123] [2025-12-03 08:30:19] [DEBUG] [AGENCY_MANAGER] Saving meta {post_id: 123, meta_key: "agency_xml_id", meta_value: "1"}
```

---

## 🏗️ Architettura

### Componenti

```
┌─────────────────────────────────────────────────────────┐
│                    Entry Point                          │
│  (Admin Button / Cron / Server Cron)                   │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ Generate Trace ID
                     ▼
┌─────────────────────────────────────────────────────────┐
│              Debug Tracker (Singleton)                  │
│  - trace_id                                             │
│  - start_time                                           │
│  - events[]                                             │
│  - context{}                                            │
└────────────────────┬────────────────────────────────────┘
                     │
         ┌───────────┴───────────┬──────────────┐
         ▼                       ▼              ▼
┌─────────────────┐  ┌──────────────────┐  ┌──────────────┐
│ Component       │  │ Component        │  │ Component    │
│ (Orchestrator)  │  │ (Agency_Manager) │  │ (API_Writer) │
│                 │  │                  │  │              │
│ log_event()     │  │ log_event()      │  │ log_event()  │
└─────────────────┘  └──────────────────┘  └──────────────┘
         │                       │              │
         └───────────┬───────────┴──────────────┘
                     ▼
┌─────────────────────────────────────────────────────────┐
│                  Log Storage                            │
│  - File: logs/import-{trace_id}.log                    │
│  - Database: wp_realestate_sync_debug_log (optional)   │
└─────────────────────────────────────────────────────────┘
```

---

## 💻 Implementazione

### 1. Debug Tracker Class

**File**: `includes/class-realestate-sync-debug-tracker.php`

```php
<?php
/**
 * Debug Tracker - Unified Debugging System
 *
 * Traces entire import flow from entry point to completion.
 * Provides structured logging with trace IDs, timestamps, and context.
 *
 * @package RealEstateSync
 * @version 1.0.0
 */

class RealEstate_Sync_Debug_Tracker {

    /**
     * Log levels (Onion layers)
     */
    const LEVEL_CRITICAL = 0;  // Fatal errors only
    const LEVEL_ERROR = 1;     // Errors + Critical
    const LEVEL_WARNING = 2;   // Warnings + Error + Critical
    const LEVEL_INFO = 3;      // Info + Warning + Error + Critical
    const LEVEL_DEBUG = 4;     // Debug (queries, meta) + all above
    const LEVEL_TRACE = 5;     // Trace (stack, perf) + all above

    /**
     * Current log level (configured)
     */
    private $log_level = self::LEVEL_INFO;

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Current trace ID
     */
    private $trace_id = null;

    /**
     * Start timestamp
     */
    private $start_time = null;

    /**
     * Events log
     */
    private $events = array();

    /**
     * Context data
     */
    private $context = array();

    /**
     * Log file handle
     */
    private $log_file = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor (singleton)
     */
    private function __construct() {
        // Load log level from configuration
        $this->load_log_level();
    }

    /**
     * Load log level from configuration
     */
    private function load_log_level() {
        // Priority 1: wp-config.php constant
        if (defined('REALESTATE_SYNC_LOG_LEVEL')) {
            $this->log_level = REALESTATE_SYNC_LOG_LEVEL;
            return;
        }

        // Priority 2: Admin settings
        $settings = get_option('realestate_sync_settings', array());
        if (isset($settings['log_level'])) {
            $this->log_level = (int) $settings['log_level'];
            return;
        }

        // Priority 3: Default based on WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log_level = self::LEVEL_DEBUG;  // Debug mode
        } else {
            $this->log_level = self::LEVEL_WARNING;  // Production mode
        }
    }

    /**
     * Set log level programmatically
     *
     * @param int $level Log level constant
     */
    public function set_log_level($level) {
        $this->log_level = $level;
    }

    /**
     * Get current log level
     *
     * @return int
     */
    public function get_log_level() {
        return $this->log_level;
    }

    /**
     * Start new trace session
     *
     * @param string $entry_point Entry point identifier
     * @param array $context Initial context data
     * @return string Trace ID
     */
    public function start_trace($entry_point, $context = array()) {
        // Generate unique trace ID
        $this->trace_id = 'import_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
        $this->start_time = microtime(true);
        $this->context = array_merge(array(
            'entry_point' => $entry_point,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ), $context);

        // Open log file
        $log_dir = plugin_dir_path(dirname(__FILE__)) . 'logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_file_path = $log_dir . '/import-' . $this->trace_id . '.log';
        $this->log_file = fopen($log_file_path, 'a');

        // Log start event
        $this->log_event('START', 'SYSTEM', "Trace started from {$entry_point}", $this->context);

        return $this->trace_id;
    }

    /**
     * Log event (with level filtering)
     *
     * @param string $level Log level (CRITICAL, ERROR, WARNING, INFO, DEBUG, TRACE)
     * @param string $component Component name (ORCHESTRATOR, AGENCY_MANAGER, etc.)
     * @param string $message Log message
     * @param array $data Additional context data
     */
    public function log_event($level, $component, $message, $data = array()) {
        if (!$this->trace_id) {
            // No active trace, skip logging
            return;
        }

        // Convert string level to numeric
        $numeric_level = $this->string_to_level($level);

        // Check if this event should be logged based on current log level
        if ($numeric_level > $this->log_level) {
            // Event level is more verbose than configured level, skip
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $elapsed = microtime(true) - $this->start_time;

        $event = array(
            'trace_id' => $this->trace_id,
            'timestamp' => $timestamp,
            'elapsed' => round($elapsed, 3),
            'level' => $level,
            'component' => $component,
            'message' => $message,
            'data' => $data
        );

        $this->events[] = $event;

        // Format log line
        $log_line = sprintf(
            "[%s] [%s] [+%.3fs] [%s] [%s] %s",
            $this->trace_id,
            $timestamp,
            $elapsed,
            $level,
            $component,
            $message
        );

        // Add context data if present
        if (!empty($data)) {
            $log_line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $log_line .= "\n";

        // Write to file
        if ($this->log_file) {
            fwrite($this->log_file, $log_line);
        }

        // Also log to WordPress debug.log if WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($log_line);
        }
    }

    /**
     * Convert string level to numeric
     *
     * @param string $level String level (CRITICAL, ERROR, WARNING, INFO, DEBUG, TRACE, SUCCESS)
     * @return int Numeric level
     */
    private function string_to_level($level) {
        switch (strtoupper($level)) {
            case 'CRITICAL':
                return self::LEVEL_CRITICAL;
            case 'ERROR':
                return self::LEVEL_ERROR;
            case 'WARNING':
                return self::LEVEL_WARNING;
            case 'SUCCESS':
            case 'INFO':
                return self::LEVEL_INFO;
            case 'DEBUG':
                return self::LEVEL_DEBUG;
            case 'TRACE':
                return self::LEVEL_TRACE;
            case 'START':
            case 'END':
                return self::LEVEL_INFO;  // Always log start/end at INFO level
            default:
                return self::LEVEL_INFO;
        }
    }

    /**
     * Log database query (LEVEL_DEBUG)
     *
     * @param string $component Component executing query
     * @param array $args WP_Query arguments
     * @param array $results Query results
     */
    public function log_query($component, $args, $results) {
        // Only logged at DEBUG level or higher
        $this->log_event('DEBUG', $component, 'Database query executed', array(
            'query_args' => $args,
            'found_posts' => isset($results['found_posts']) ? $results['found_posts'] : 0,
            'post_ids' => isset($results['posts']) ? $results['posts'] : array()
        ));
    }

    /**
     * Log API call (LEVEL_INFO for success/error, LEVEL_DEBUG for details)
     *
     * @param string $component Component making API call
     * @param string $method HTTP method (POST, PUT, GET)
     * @param string $endpoint API endpoint
     * @param array $body Request body
     * @param array $response Response data
     */
    public function log_api_call($component, $method, $endpoint, $body, $response) {
        $success = isset($response['success']) ? $response['success'] : false;

        // Always log API calls at INFO level (summary)
        $this->log_event('INFO', $component, "API {$method} {$endpoint}", array(
            'success' => $success,
            'agency_id' => isset($response['agency_id']) ? $response['agency_id'] : null,
            'property_id' => isset($response['property_id']) ? $response['property_id'] : null
        ));

        // Log full details at DEBUG level
        $this->log_event('DEBUG', $component, "API {$method} {$endpoint} [FULL]", array(
            'request_body' => $body,
            'response' => $response
        ));
    }

    /**
     * Log meta field operation (LEVEL_DEBUG)
     *
     * @param string $component Component performing operation
     * @param string $operation Operation (save, search, delete)
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     * @param mixed $result Operation result
     */
    public function log_meta_operation($component, $operation, $post_id, $meta_key, $meta_value, $result = null) {
        // Only logged at DEBUG level
        $this->log_event('DEBUG', $component, "Meta {$operation}", array(
            'post_id' => $post_id,
            'meta_key' => $meta_key,
            'meta_value' => $meta_value,
            'result' => $result
        ));
    }

    /**
     * End trace session
     *
     * @param string $status Final status (completed, error, interrupted)
     * @param array $summary Summary statistics
     */
    public function end_trace($status, $summary = array()) {
        $elapsed = microtime(true) - $this->start_time;

        $this->log_event('END', 'SYSTEM', "Trace ended with status: {$status}", array_merge(array(
            'total_elapsed' => round($elapsed, 3),
            'total_events' => count($this->events)
        ), $summary));

        // Close log file
        if ($this->log_file) {
            fclose($this->log_file);
            $this->log_file = null;
        }

        // Reset state
        $this->trace_id = null;
        $this->start_time = null;
        $this->events = array();
        $this->context = array();
    }

    /**
     * Get current trace ID
     *
     * @return string|null
     */
    public function get_trace_id() {
        return $this->trace_id;
    }

    /**
     * Get all events
     *
     * @return array
     */
    public function get_events() {
        return $this->events;
    }
}
```

---

### 2. Integration Points

#### A. Entry Points

**File**: `admin/class-realestate-sync-admin.php`

```php
// In handle_manual_import() - Line ~708
public function handle_manual_import() {
    check_ajax_referer('realestate_sync_nonce', 'nonce');

    // START TRACE
    $tracker = RealEstate_Sync_Debug_Tracker::get_instance();
    $trace_id = $tracker->start_trace('MANUAL_IMPORT_BUTTON', array(
        'user_id' => get_current_user_id(),
        'action' => 'handle_manual_import'
    ));

    $tracker->log_event('INFO', 'ADMIN', 'Manual import button clicked');

    try {
        // ... existing code ...

        $tracker->end_trace('completed', $result);

    } catch (Exception $e) {
        $tracker->log_event('ERROR', 'ADMIN', 'Import failed: ' . $e->getMessage());
        $tracker->end_trace('error');
    }
}
```

#### B. Components

**File**: `includes/class-realestate-sync-agency-manager.php`

```php
// In find_agency_by_xml_id()
private function find_agency_by_xml_id($xml_id) {
    $tracker = RealEstate_Sync_Debug_Tracker::get_instance();

    $tracker->log_event('DEBUG', 'AGENCY_MANAGER', 'Finding agency by XML ID', array(
        'xml_id' => $xml_id,
        'meta_key' => 'agency_xml_id'
    ));

    $args = array(
        'post_type' => 'estate_agency',
        'meta_key' => 'agency_xml_id',
        'meta_value' => $xml_id,
        'posts_per_page' => 1,
        'fields' => 'ids'
    );

    $query = new WP_Query($args);

    $tracker->log_query('AGENCY_MANAGER', $args, array(
        'found_posts' => $query->found_posts,
        'posts' => $query->posts
    ));

    if ($query->have_posts()) {
        $agency_id = $query->posts[0];
        $tracker->log_event('INFO', 'AGENCY_MANAGER', 'Agency found', array(
            'xml_id' => $xml_id,
            'wp_id' => $agency_id
        ));
        return $agency_id;
    }

    $tracker->log_event('INFO', 'AGENCY_MANAGER', 'Agency not found', array(
        'xml_id' => $xml_id
    ));

    return false;
}

// In create_agency_via_api()
private function create_agency_via_api($agency_data, $mark_as_test = false) {
    $tracker = RealEstate_Sync_Debug_Tracker::get_instance();

    $tracker->log_event('INFO', 'AGENCY_MANAGER', 'Creating agency via API', array(
        'name' => $agency_data['name'],
        'xml_id' => $agency_data['xml_agency_id']
    ));

    $api_body = $this->api_writer->format_api_body($agency_data);
    $result = $this->api_writer->create_agency($api_body);

    if (!$result['success']) {
        $tracker->log_event('ERROR', 'AGENCY_MANAGER', 'Agency creation failed', array(
            'error' => $result['error']
        ));
        return false;
    }

    $agency_id = $result['agency_id'];

    $tracker->log_event('SUCCESS', 'AGENCY_MANAGER', 'Agency created', array(
        'xml_id' => $agency_data['xml_agency_id'],
        'wp_id' => $agency_id
    ));

    // Log meta save
    $tracker->log_meta_operation('AGENCY_MANAGER', 'save', $agency_id, 'agency_xml_id', $agency_data['xml_agency_id']);
    update_post_meta($agency_id, 'agency_xml_id', $agency_data['xml_agency_id']);

    return $agency_id;
}
```

---

## 📁 Log Output Examples per Level

### LEVEL 5: TRACE (Debug Fase Sviluppo - ORA)

**File**: `logs/import_20251203_083015_abc123.log`

```
[import_20251203_083015_abc123] [2025-12-03 08:30:15] [+0.000s] [START] [SYSTEM] Trace started from MANUAL_IMPORT_BUTTON {"entry_point":"MANUAL_IMPORT_BUTTON","user_id":1}
[import_20251203_083015_abc123] [2025-12-03 08:30:15] [+0.001s] [INFO] [ADMIN] Manual import button clicked
[import_20251203_083015_abc123] [2025-12-03 08:30:16] [+1.234s] [INFO] [ORCHESTRATOR] Starting batch import {"file":"export.xml"}
[import_20251203_083015_abc123] [2025-12-03 08:30:17] [+2.456s] [INFO] [PARSER] Found agencies {"count":30}
[import_20251203_083015_abc123] [2025-12-03 08:30:18] [+3.123s] [DEBUG] [AGENCY_MANAGER] Finding agency by XML ID {"xml_id":"1","meta_key":"agency_xml_id"}
[import_20251203_083015_abc123] [2025-12-03 08:30:18] [+3.234s] [DEBUG] [AGENCY_MANAGER] Database query executed {"query_args":{"post_type":"estate_agency","meta_key":"agency_xml_id","meta_value":"1"},"found_posts":0}
[import_20251203_083015_abc123] [2025-12-03 08:30:18] [+3.235s] [TRACE] [AGENCY_MANAGER] Query performance {"duration_ms":23.4,"sql":"SELECT ID FROM wp_posts..."}
[import_20251203_083015_abc123] [2025-12-03 08:30:18] [+3.236s] [INFO] [AGENCY_MANAGER] Agency not found {"xml_id":"1"}
[import_20251203_083015_abc123] [2025-12-03 08:30:18] [+3.237s] [INFO] [AGENCY_MANAGER] Creating agency via API {"name":"Agenzia Test","xml_id":"1"}
[import_20251203_083015_abc123] [2025-12-03 08:30:19] [+4.123s] [INFO] [API_WRITER] API POST /wpresidence/v1/agency/add {"success":true,"agency_id":123}
[import_20251203_083015_abc123] [2025-12-03 08:30:19] [+4.124s] [DEBUG] [API_WRITER] API POST /wpresidence/v1/agency/add [FULL] {"request_body":{...},"response":{...}}
[import_20251203_083015_abc123] [2025-12-03 08:30:19] [+4.234s] [INFO] [AGENCY_MANAGER] Agency created {"xml_id":"1","wp_id":123}
[import_20251203_083015_abc123] [2025-12-03 08:30:19] [+4.235s] [DEBUG] [AGENCY_MANAGER] Meta save {"post_id":123,"meta_key":"agency_xml_id","meta_value":"1"}
[import_20251203_083015_abc123] [2025-12-03 08:45:32] [+917.123s] [END] [SYSTEM] Trace ended {"total_elapsed":917.123,"agencies_created":30,"properties_created":775}
```

**Righe totali**: ~1500+ righe per import completo

---

### LEVEL 3: INFO (Monitoring Normale - FUTURO)

**File**: `logs/import_20251203_083015_abc123.log`

```
[import_20251203_083015_abc123] [2025-12-03 08:30:15] [+0.000s] [START] [SYSTEM] Trace started from MANUAL_IMPORT_BUTTON
[import_20251203_083015_abc123] [2025-12-03 08:30:16] [+1.234s] [INFO] [ORCHESTRATOR] Starting batch import {"file":"export.xml"}
[import_20251203_083015_abc123] [2025-12-03 08:30:17] [+2.456s] [INFO] [PARSER] Found agencies {"count":30}
[import_20251203_083015_abc123] [2025-12-03 08:30:18] [+3.236s] [INFO] [AGENCY_MANAGER] Agency not found {"xml_id":"1"}
[import_20251203_083015_abc123] [2025-12-03 08:30:18] [+3.237s] [INFO] [AGENCY_MANAGER] Creating agency via API {"name":"Agenzia Test","xml_id":"1"}
[import_20251203_083015_abc123] [2025-12-03 08:30:19] [+4.123s] [INFO] [API_WRITER] API POST /wpresidence/v1/agency/add {"success":true,"agency_id":123}
[import_20251203_083015_abc123] [2025-12-03 08:30:19] [+4.234s] [INFO] [AGENCY_MANAGER] Agency created {"xml_id":"1","wp_id":123}
[... repeat for 29 more agencies ...]
[import_20251203_083015_abc123] [2025-12-03 08:45:32] [+917.123s] [END] [SYSTEM] Trace ended {"agencies_created":30,"properties_created":775}
```

**Righe totali**: ~150 righe per import completo (10x meno)
**Cosa manca**: Query DEBUG, Meta DEBUG, TRACE performance

---

### LEVEL 2: WARNING (Produzione - A REGIME)

**File**: `logs/import_20251203_083015_abc123.log`

```
[import_20251203_083015_abc123] [2025-12-03 08:30:15] [+0.000s] [START] [SYSTEM] Trace started
[import_20251203_083015_abc123] [2025-12-03 08:30:25] [+10.234s] [WARNING] [PARSER] Missing optional field {"field":"logo","agency":"Agenzia Test"}
[import_20251203_083015_abc123] [2025-12-03 08:32:15] [+120.456s] [WARNING] [API_WRITER] Slow API response {"endpoint":"/property/add","duration_ms":5234}
[import_20251203_083015_abc123] [2025-12-03 08:45:32] [+917.123s] [END] [SYSTEM] Trace ended {"agencies_created":30,"properties_created":775}
```

**Righe totali**: ~10-20 righe per import completo (100x meno)
**Cosa manca**: INFO operations, DEBUG details, TRACE
**Cosa c'è**: Solo START/END + warnings

---

## ⚙️ Configurazione Livelli

### Metodo 1: wp-config.php (Priorità 1)

```php
// wp-config.php

// Debug massimo (fase sviluppo - ORA)
define('REALESTATE_SYNC_LOG_LEVEL', 5);  // TRACE

// Debug standard (fase test)
define('REALESTATE_SYNC_LOG_LEVEL', 4);  // DEBUG

// Monitoring (fase staging)
define('REALESTATE_SYNC_LOG_LEVEL', 3);  // INFO

// Produzione (a regime)
define('REALESTATE_SYNC_LOG_LEVEL', 2);  // WARNING
```

### Metodo 2: Admin Settings (Priorità 2)

```php
// Via dashboard WordPress
Settings > RealEstate Sync > Debug Level
[ ] TRACE (5) - Maximum detail
[ ] DEBUG (4) - Technical details
[x] INFO (3) - Operations flow  ← Default
[ ] WARNING (2) - Problems only
[ ] ERROR (1) - Errors only
```

### Metodo 3: Auto-detect (Priorità 3 - Default)

```php
// Se WP_DEBUG = true  → LEVEL_DEBUG (4)
// Se WP_DEBUG = false → LEVEL_WARNING (2)
```

### Cambio Livello Runtime (Temporaneo)

```php
// Nel codice, per debug specifico
$tracker = RealEstate_Sync_Debug_Tracker::get_instance();
$tracker->set_log_level(RealEstate_Sync_Debug_Tracker::LEVEL_TRACE);

// Import con trace completo
// ...

// Ripristina livello normale
$tracker->set_log_level(RealEstate_Sync_Debug_Tracker::LEVEL_INFO);
```

---

## 📊 Comparazione Livelli

| Livello | Fase | Righe Log | Include | Performance |
|---------|------|-----------|---------|-------------|
| **TRACE (5)** | Debug sviluppo | ~2000+ | Tutto + stack + perf | ⚠️ Slow |
| **DEBUG (4)** | Testing | ~800 | Query + Meta + API full | 🔶 Medium |
| **INFO (3)** | Staging | ~150 | Operations + API summary | ✅ Good |
| **WARNING (2)** | **Produzione** | ~20 | Warnings + Errors | ⚡ Fast |
| **ERROR (1)** | Minimal | ~5 | Only errors | ⚡ Fastest |
| **CRITICAL (0)** | Emergency | ~2 | Fatal only | ⚡ Fastest |

**Raccomandazioni**:
- 🔧 **ORA (debug bugs)**: LEVEL 5 (TRACE) o LEVEL 4 (DEBUG)
- 🧪 **Testing fix**: LEVEL 3 (INFO)
- 🚀 **Produzione**: LEVEL 2 (WARNING)

---

## 🎯 Vantaggi Sistema a Cipolla

1. **Scalabilità**: Da 2000+ righe a 20 righe senza modificare codice
2. **Performance**: In produzione, 99% dei log_event() vengono skippati immediatamente
3. **Flessibilità**: Cambio livello senza redeploy
4. **Debug facilitato**: Attivi TRACE solo quando serve
5. **Produzione pulita**: Solo warnings/errors, niente noise
6. **Backward compatible**: Codice esistente continua a funzionare

---

## 🚀 Next Steps

1. ✅ Review design document con sistema a cipolla
2. ⚠️ Implement Debug_Tracker class con livelli
3. ⚠️ Integrate in all components
4. ⚠️ Test with sequential XML imports (LEVEL_TRACE)
5. ⚠️ Analyze logs to find bugs
6. ⚠️ Fix bugs identified
7. ⚠️ Switch to LEVEL_WARNING for production
