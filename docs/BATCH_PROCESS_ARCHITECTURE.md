# Batch Process Architecture v1.5.0

## Overview

This document describes the intended architecture for the batch import system that processes 805+ real estate items (agencies + properties) without timeout issues, working identically for both manual dashboard triggers and automatic nightly cron execution.

## System Requirements

- **Environment**: WordPress with `DISABLE_WP_CRON=true`
- **Server Cron**: Runs every minute calling batch continuation endpoint
- **No User Interaction**: Must work completely autonomous for nighttime operation
- **Comprehensive Logging**: Every process and subprocess boundary must log start (>>>) and end (<<<)
- **Infallible Execution**: First batch must execute immediately, no shutdown hook dependency

## Architecture Components

### 1. Background Import Manager
**File**: `includes/class-realestate-sync-background-import-manager.php`
**Responsibility**: Orchestrates import sessions, initiates batch processing

**Key Methods**:
- `initiate_background_import($xml_file_path, $mark_as_test = false)`: Entry point for import
- Creates session ID and logs session start
- Scans XML and populates queue
- Executes first batch IMMEDIATELY (no shutdown hook)
- Sets transient for continuation if needed

**Critical Flow**:
```php
// 1. Create session
$session_id = 'import_' . time();

// 2. Scan XML and populate queue
$batch_processor = new RealEstate_Sync_Batch_Processor($session_id, $xml_file_path);
$scan_result = $batch_processor->scan_and_populate_queue($mark_as_test);

// 3. Process first batch IMMEDIATELY
$first_batch_result = $batch_processor->process_next_batch();

// 4. If not complete, set transient for cron continuation
if (!$first_batch_result['complete']) {
    set_transient('realestate_sync_pending_batch', $session_id, 300);
}
```

### 2. Batch Processor
**File**: `includes/class-realestate-sync-batch-processor.php`
**Responsibility**: Processes queue in chunks with timeout protection

**Key Properties**:
- `$session_id`: Unique session identifier
- `$xml_file_path`: Path to XML file being processed
- `$batch_size`: Items per batch (default 10)
- `$timeout_seconds`: Max execution time per batch (50 seconds)

**Key Methods**:

#### `scan_and_populate_queue($mark_as_test)`
Scans XML, filters valid items, populates database queue

**Flow**:
1. Load XML file
2. Extract agencies from `<software><agenzia>` section
3. Filter properties by valid province codes (TN, BZ, VR)
4. Extract property IDs from `<immobili><annuncio>` elements
5. Clear existing queue for session
6. Insert agencies into queue (type='agency')
7. Insert properties into queue (type='property')
8. Return count of queued items

**CRITICAL**: Must correctly extract property IDs from XML structure

#### `process_next_batch()`
Retrieves pending items from queue and processes them

**Flow**:
1. Check timeout (50 seconds max)
2. Get next `$batch_size` items with status='pending'
3. For each item:
   - Mark as 'processing'
   - Call `process_agency()` or `process_property()`
   - Mark as 'done' on success, 'error' on failure
   - Increment retry counter on error
4. Return stats and completion status
5. Log comprehensive statistics

**Timeout Protection**:
```php
$start_time = time();
$timeout = 50;

foreach ($items as $item) {
    if ((time() - $start_time) > $timeout) {
        error_log("[BATCH] Timeout reached, stopping batch");
        break;
    }
    // Process item...
}
```

### 3. Queue Manager
**File**: `includes/class-realestate-sync-queue-manager.php`
**Responsibility**: Database operations for queue table

**Database Table**: `{prefix}_realestate_import_queue`

**Schema**:
```sql
CREATE TABLE {prefix}_realestate_import_queue (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id varchar(100) NOT NULL,
    item_type varchar(20) NOT NULL,      -- 'agency' or 'property'
    item_id varchar(100) NOT NULL,       -- Agency ID or Property ID
    status varchar(20) NOT NULL,         -- 'pending', 'processing', 'done', 'error', 'retry'
    retry_count int(11) DEFAULT 0,
    error_message text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY session_id (session_id),
    KEY status (status)
)
```

**Key Methods**:
- `clear_session_queue($session_id)`: Remove all queue items for session
- `add_item($session_id, $item_type, $item_id)`: Insert queue item
- `get_next_batch($session_id, $batch_size)`: Get pending items
- `update_item_status($id, $status, $error_msg)`: Update item state
- `get_session_stats($session_id)`: Count items by status

### 4. Cron Continuation Endpoint
**File**: `batch-continuation.php` (plugin root)
**Responsibility**: Server cron entry point for batch processing

**Security**: Requires secret token in URL query parameter
**Token**: `TrentinoImmo2025Secret!`

**Flow**:
```php
// 1. Validate token
if (!isset($_GET['token']) || $_GET['token'] !== 'TrentinoImmo2025Secret!') {
    http_response_code(403);
    die('Forbidden');
}

// 2. Check for pending batch
$pending_session = get_transient('realestate_sync_pending_batch');
if (!$pending_session) {
    echo "OK - No pending batch\n";
    exit;
}

// 3. Get XML file path from session progress
$progress = get_option('realestate_sync_background_import_progress', array());
$xml_file_path = $progress['xml_file_path'] ?? '';

// 4. Delete transient to prevent concurrent execution
delete_transient('realestate_sync_pending_batch');

// 5. Process batch
$batch_processor = new RealEstate_Sync_Batch_Processor($pending_session, $xml_file_path);
$result = $batch_processor->process_next_batch();

// 6. Set transient for continuation if not complete
if (!$result['complete']) {
    set_transient('realestate_sync_pending_batch', $pending_session, 300);
    echo "OK - Batch processed, more pending\n";
} else {
    echo "OK - All batches complete!\n";
}
```

**Server Cron Command**:
```bash
* * * * * wget -q -O - "https://trentinoimmobiliare.it/wp-content/plugins/realestate-sync-plugin/batch-continuation.php?token=TrentinoImmo2025Secret!" >/dev/null 2>&1
```

## Import Methods (GOLDEN PATH)

### Agency Import
**CORRECT METHOD**: Use `RealEstate_Sync_Agency_Manager::import_agencies()`

**File**: `includes/class-realestate-sync-agency-manager.php`
**Features**:
- Creates post_type='estate_agency' (NOT 'estate_agent')
- Fetches agency logo via GestionaleImmobiliare.it API
- Downloads and attaches logo as featured image
- Sets custom fields (phone, email, address, etc.)
- Handles updates for existing agencies
- Returns comprehensive import statistics

**Usage in Batch Processor**:
```php
private function process_agency($queue_item) {
    $agency_id = $queue_item->item_id;

    // Load XML and find agency
    $xml = simplexml_load_file($this->xml_file_path);
    foreach ($xml->software->agenzia as $agenzia) {
        if ((string)$agenzia['codice'] === $agency_id) {
            $agency_data = $this->xml_to_array($agenzia);

            // Initialize Agency Manager
            if (!isset($this->agency_manager)) {
                $this->agency_manager = new RealEstate_Sync_Agency_Manager();
            }

            // Get mark_as_test flag from session
            $progress = get_option('realestate_sync_background_import_progress', array());
            $mark_as_test = $progress['mark_as_test'] ?? false;

            // Import agency with logo via API
            $agencies_array = array($agency_data);
            $import_results = $this->agency_manager->import_agencies($agencies_array, $mark_as_test);

            // Extract result
            $result = array(
                'success' => ($import_results['imported'] + $import_results['updated'] + $import_results['skipped']) > 0,
                'action' => $import_results['imported'] > 0 ? 'created' : ($import_results['updated'] > 0 ? 'updated' : 'skipped'),
                'agent_id' => $import_results['agent_ids'][0] ?? null
            );

            return $result;
        }
    }
}
```

**WRONG METHOD (DO NOT USE)**:
- `RealEstate_Sync_Agency_Importer::import_single_agency()` - Creates agents without logos

### Property Import
**CORRECT METHOD**: Use `RealEstate_Sync_WP_Importer_API::process_property()`

**File**: `includes/class-realestate-sync-wp-importer-api.php`
**Features**:
- Creates post_type='estate_property'
- Links property to agency via custom field
- Sets property metadata (price, rooms, size, etc.)
- Handles property images
- Sets taxonomies (property type, location, features)
- API-based import matching agency system

**Usage in Batch Processor**:
```php
private function process_property($queue_item) {
    $property_id = $queue_item->item_id;

    // Load XML and find property
    $xml = simplexml_load_file($this->xml_file_path);
    foreach ($xml->immobili->annuncio as $annuncio) {
        if ((string)$annuncio->id === $property_id) {

            // Initialize WP Importer API
            if (!isset($this->wp_importer)) {
                $this->wp_importer = new RealEstate_Sync_WP_Importer_API($this->logger);
            }

            // Map XML data to property format
            $mapped_data = $this->map_property_data($annuncio);

            // Process property via API
            $result = $this->wp_importer->process_property($mapped_data);

            return $result;
        }
    }
}
```

**WRONG METHOD (DO NOT USE)**:
- `RealEstate_Sync_Import_Engine::handle_single_property()` - Old method, not API-based

## XML Structure and ID Extraction

### Agency IDs
**Location**: `<software><agenzia codice="AGENCY_ID">`
**Extraction**: `(string)$agenzia['codice']`

### Property IDs
**Location**: TBD - Need to verify actual XML structure
**Expected**: `<immobili><annuncio><id>PROPERTY_ID</id>`
**Current Issue**: Properties have empty IDs, structure may be different

**CRITICAL**: Must verify actual XML structure to correctly extract property IDs

## Logging Strategy

### Log Prefixes
- `[REALESTATE-SYNC]`: Background Import Manager
- `[BATCH-PROCESSOR]`: Batch Processor
- `[QUEUE-MANAGER]`: Queue Manager
- `[BATCH-CONTINUATION]`: Cron endpoint
- `[SCAN]`: XML scanning operations
- `[AGENCY-MANAGER]`: Agency import
- `[WP-IMPORTER-API]`: Property import

### Subprocess Boundaries
Every process entry and exit must be logged:
```php
// Process start
error_log("[PREFIX] >>> Starting operation...");

// Process operations...

// Process end
error_log("[PREFIX] <<< Operation complete: stats");
```

### Statistics Logging
Log comprehensive stats after each batch:
```php
error_log("[BATCH] Stats: Processed={$processed}, Success={$success}, Error={$error}, Remaining={$remaining}");
```

## Error Handling and Retry Logic

### Retry Strategy
- Max retries: 3 per item
- On error: Mark status='error', increment retry_count
- Items with retry_count < 3 can be retried
- Items with retry_count >= 3 remain in 'error' state

### Error States
- `pending`: Item ready for processing
- `processing`: Item currently being processed
- `done`: Item successfully processed
- `error`: Item failed, may be retried if retry_count < 3
- `retry`: Item marked for retry (optional status)

### Transient Management
- Transient key: `realestate_sync_pending_batch`
- Transient value: `$session_id`
- Expiration: 300 seconds (5 minutes)
- Deleted before each batch to prevent concurrent execution
- Reset after each incomplete batch

## Session Progress Tracking

### Progress Option
**Key**: `realestate_sync_background_import_progress`

**Data Structure**:
```php
array(
    'session_id' => 'import_1234567890',
    'xml_file_path' => '/path/to/xml/file.xml',
    'mark_as_test' => false,
    'total_items' => 805,
    'processed_items' => 45,
    'start_time' => 1234567890,
    'last_batch_time' => 1234567920,
    'status' => 'processing' // or 'completed', 'error'
)
```

## Complete Process Flow

### Manual Dashboard Trigger
1. User clicks "Scarica e Importa Ora"
2. AJAX calls `admin-ajax.php?action=realestate_sync_download_and_import`
3. Background Import Manager downloads XML
4. Background Import Manager initiates import
5. Batch Processor scans XML and populates queue
6. First batch processes IMMEDIATELY (10 items)
7. If incomplete, transient set for continuation
8. User receives response with session ID and progress URL
9. Server cron picks up continuation every minute

### Automatic Nightly Cron
1. Server cron triggers download at configured time
2. Downloads XML file
3. Initiates background import (same as manual)
4. First batch processes immediately
5. Continuation batches process every minute via server cron

### Cron Continuation Loop
1. Server cron runs every minute
2. Calls `batch-continuation.php?token=...`
3. Checks for pending batch transient
4. If found: process next batch, reset transient if incomplete
5. If not found: exit silently
6. Repeats until all batches complete

## Known Issues and Solutions

### Issue 1: Properties with Empty IDs
**Problem**: Queue items have empty `item_id` for properties
**Root Cause**: XML structure different than expected
**Solution**: Verify actual XML structure and correct ID extraction

### Issue 2: Shutdown Hook Not Executing
**Problem**: First batch didn't execute via shutdown hook
**Solution**: Execute first batch directly after queue population

### Issue 3: Wrong Import Methods
**Problem**: Using Agency_Importer and Import_Engine instead of golden path
**Solution**: Use Agency_Manager and WP_Importer_API

### Issue 4: Concurrent Execution
**Problem**: Multiple cron calls could process same batch
**Solution**: Delete transient before processing, acts as lock

## Testing Checklist

Before deployment, verify:
- [ ] Manual import processes all items
- [ ] Agencies created with logos (post_type='estate_agency')
- [ ] Properties created and linked to agencies
- [ ] Cron continuation works autonomously
- [ ] Logs show comprehensive process tracking
- [ ] Queue items marked 'done' on success
- [ ] Errors logged with retry mechanism
- [ ] Transient cleared on completion
- [ ] Progress tracking accurate
- [ ] No timeout issues with 805+ items
- [ ] System works identically for manual and cron triggers

## File Checklist

Files that must work together:
- [ ] `includes/class-realestate-sync-background-import-manager.php`
- [ ] `includes/class-realestate-sync-batch-processor.php`
- [ ] `includes/class-realestate-sync-queue-manager.php`
- [ ] `includes/class-realestate-sync-agency-manager.php`
- [ ] `includes/class-realestate-sync-wp-importer-api.php`
- [ ] `batch-continuation.php`
- [ ] Server cron configuration

## Next Steps for Implementation

1. Verify working code in git commits (find golden path)
2. Document current implementation (what worked, what didn't)
3. Clean implementation using correct methods
4. Test with small dataset (7 agencies, 3 properties)
5. Test with full dataset (30 agencies, 775 properties)
6. Deploy to production
7. Monitor first nighttime cron execution
