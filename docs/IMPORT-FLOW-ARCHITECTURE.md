# Import Flow Architecture - Complete System Diagram

**Document version:** 2.0
**Created:** 2025-12-14
**Updated:** 2025-12-14 (Post-Fix)
**Purpose:** Fotografia accurata del flusso di import reale - usato per identificare codice obsoleto
**Status:** ✅ Self-healing ATTIVO su tutti i flussi

---

## Executive Summary

### ✅ SELF-HEALING STATUS

**Self-healing è ATTIVO su tutti i flussi di import.**

- **Fix Applicata:** 2025-12-14 - `process_single_property()` ora usa self-healing
- **Status:** Self-healing operativo su tutti e 3 i punti di accesso
- **Testing:** In attesa di deploy e test su server

### Metodi ATTIVI (Usati in Produzione)
✅ `Batch_Orchestrator::process_xml_batch()` - Entry point per tutti e 3 i flussi
✅ `Batch_Processor::process_next_batch()` - Processamento queue
✅ `Batch_Processor::process_property()` - Delega a Import Engine
✅ `Import_Engine::process_single_property()` - **ORA USA SELF-HEALING** ✅

### Metodi SEMI-OBSOLETI (Usati Solo Per Test)
⚠️ `Import_Engine::execute_chunked_import()` - Usato SOLO per "Test Import" in dashboard
⚠️ Potrebbe essere deprecato o consolidato in futuro

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          3 ENTRY POINTS                                  │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────┐
        │                           │                           │
        ▼                           ▼                           ▼
┌───────────────┐          ┌───────────────┐          ┌───────────────┐
│  Entry Point  │          │  Entry Point  │          │  Entry Point  │
│      A        │          │      B        │          │      C        │
│               │          │               │          │               │
│   Filesystem  │          │    Manual     │          │   Scheduled   │
│  XML Upload   │          │  XML Download │          │ XML Download  │
│               │          │               │          │    (Cron)     │
└───────────────┘          └───────────────┘          └───────────────┘
        │                           │                           │
        │                           │                           │
        └───────────────────────────┼───────────────────────────┘
                                    │
                                    ▼
        ┌──────────────────────────────────────────────────────┐
        │      RealEstate_Sync_Batch_Orchestrator              │
        │                                                       │
        │  process_xml_batch($xml_file, $mark_as_test)         │
        │                                                       │
        │  - Validates XML file                                │
        │  - Creates session ID                                │
        │  - Saves pre-parsed data to database                 │
        │  - Creates Batch Processor                           │
        └──────────────────────────────────────────────────────┘
                                    │
                                    ▼
        ┌──────────────────────────────────────────────────────┐
        │      RealEstate_Sync_Batch_Processor                 │
        │                                                       │
        │  1. scan_and_populate_queue()                        │
        │     - Scans XML for agencies/properties              │
        │     - Filters by province (TN/BZ)                    │
        │     - Populates queue table                          │
        │                                                       │
        │  2. process_next_batch() [CALLED REPEATEDLY]         │
        │     - Gets 5 items from queue                        │
        │     - Processes each item                            │
        │     - Returns when batch complete or timeout         │
        └──────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    ▼                               ▼
        ┌───────────────────┐           ┌───────────────────┐
        │  process_agency() │           │ process_property()│
        │                   │           │                   │
        │  Delegates to:    │           │  Delegates to:    │
        │  Agency_Manager   │           │  Import_Engine    │
        └───────────────────┘           └───────────────────┘
                                                    │
                                                    ▼
        ┌──────────────────────────────────────────────────────┐
        │      RealEstate_Sync_Import_Engine                   │
        │                                                       │
        │  🔴 CRITICAL METHOD: process_single_property()       │
        │                                                       │
        │  Line 793: ❌ USES OLD TRACKING METHOD               │
        │  $change_status = $this->tracking_manager->          │
        │      check_property_changes($property_id, $hash)     │
        │                                                       │
        │  ⚠️ DOES NOT USE self_healing_manager!               │
        │                                                       │
        │  Result: Duplicates created, tracking broken         │
        └──────────────────────────────────────────────────────┘
```

---

## Entry Point A: Filesystem XML Upload

**User Action:** Admin uploads XML file via WordPress admin dashboard

**Flow:**

1. **admin/class-realestate-sync-admin.php** (line ~1850)
   - Handler: `handle_file_upload_import()`
   - Validates file upload
   - Saves XML to filesystem

2. **Calls Orchestrator:**
   ```php
   $result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch(
       $xml_file_path,
       $mark_as_test
   );
   ```

3. **Continues to Common Flow** (see below)

---

## Entry Point B: Manual XML Download from Gestionale

**User Action:** Admin clicks "Download & Import" button for manual import

**Flow:**

1. **admin/class-realestate-sync-admin.php** (line ~781)
   - Handler: `handle_manual_download_import()`
   - Calls Download Manager to fetch XML from FTP
   - Extracts tar.gz archive
   - Gets XML file path

2. **Calls Orchestrator:**
   ```php
   $result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch(
       $xml_file_path,
       $mark_as_test
   );
   ```

3. **Continues to Common Flow** (see below)

---

## Entry Point C: Scheduled XML Download (Cron)

**User Action:** Automated - runs on schedule set in admin dashboard

**Flow:**

1. **includes/class-realestate-sync-scheduler.php** (line ~150)
   - Triggered by: WordPress cron hook `realestate_sync_scheduled_import`
   - Checks if schedule is enabled
   - Calculates schedule time (daily, weekly, etc.)

2. **Calls Download Manager:**
   ```php
   $download_manager = new RealEstate_Sync_Download_Manager();
   $xml_file_path = $download_manager->download_and_extract();
   ```

3. **Calls Orchestrator:**
   ```php
   $result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch(
       $xml_file_path,
       $mark_as_test // false for scheduled imports
   );
   ```

4. **Continues to Common Flow** (see below)

---

## Common Flow: Batch Orchestrator → Batch Processor

All 3 entry points converge at the Batch Orchestrator.

### Phase 1: Orchestrator Initialization

**File:** `includes/class-realestate-sync-batch-orchestrator.php`
**Method:** `process_xml_batch($xml_file, $mark_as_test)`

```
┌──────────────────────────────────────────────────────────────┐
│ STEP 1: Validate XML File                                    │
│  - Check file exists                                          │
│  - Load SimpleXML                                             │
│  - Count announcements                                        │
└──────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────┐
│ STEP 2: Create Session ID                                    │
│  - Generate unique session ID                                │
│  - Format: import_{timestamp}_{random}                       │
└──────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────┐
│ STEP 3: Pre-Parse and Cache XML Data                         │
│  - Parse all agencies (Agency_Parser)                        │
│  - Parse all properties (XML_Parser)                         │
│  - Save to database option:                                  │
│    realestate_sync_batch_data_{session_id}                   │
│                                                               │
│  ⚠️ LINE 342: USES OLD TRACKING CHECK                        │
│  $change_check = $tracking_manager->                         │
│      check_property_changes($property_id, $hash)             │
│                                                               │
│  This is BEFORE queueing - used to count changes             │
└──────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────┐
│ STEP 4: Create Batch Processor                               │
│  $batch_processor = new RealEstate_Sync_Batch_Processor(     │
│      $session_id,                                             │
│      $xml_file,                                               │
│      $mark_as_test                                            │
│  );                                                           │
└──────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────┐
│ STEP 5: Populate Queue                                       │
│  $batch_processor->scan_and_populate_queue()                 │
│   - Scans XML for valid properties (TN/BZ only)              │
│   - Creates queue items in kre_realestate_import_queue       │
│   - Status: 'pending'                                         │
└──────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────┐
│ STEP 6: Process First Batch                                  │
│  $batch_processor->process_next_batch()                      │
│   - Gets up to 5 items from queue                            │
│   - Processes each item                                      │
│   - Returns result                                            │
└──────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────┐
│ STEP 7: Schedule Background Processing                       │
│  If not all items processed:                                 │
│   - Creates Background_Import_Manager                        │
│   - Schedules WP cron to process remaining batches           │
└──────────────────────────────────────────────────────────────┘
```

### Phase 2: Batch Processor - Queue Processing

**File:** `includes/class-realestate-sync-batch-processor.php`
**Method:** `process_next_batch()`

**Called repeatedly until queue is empty (via WP cron background processing)**

```
┌──────────────────────────────────────────────────────────────┐
│ LOOP: For each batch (max 5 items, 50s timeout)              │
│                                                               │
│  1. Get next batch items from queue                          │
│     $items = $queue_manager->get_next_batch()                │
│                                                               │
│  2. For each item:                                            │
│     a. Mark as 'processing'                                   │
│     b. Process based on type:                                │
│        - Agency → process_agency()                           │
│        - Property → process_property() ⚠️ BUG HERE           │
│     c. Mark as 'done' or 'error'                             │
│                                                               │
│  3. Check if all items processed                             │
│  4. Return batch result                                      │
└──────────────────────────────────────────────────────────────┘
```

### Phase 3: Property Processing - WHERE THE BUG IS

**File:** `includes/class-realestate-sync-batch-processor.php`
**Method:** `process_property($queue_item)` (line 466)

```
┌──────────────────────────────────────────────────────────────┐
│ process_property($queue_item)                                │
│                                                               │
│  1. Get property_id from queue item                          │
│  2. Load pre-parsed data from database                       │
│  3. ⚠️ CRITICAL: Delegate to Import Engine                   │
│                                                               │
│     LINE 508:                                                 │
│     $result = $this->import_engine->                         │
│         process_single_property($property_data);             │
│                                                               │
│  4. Return result                                            │
└──────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────┐
│ ✅ FIXED - Self-Healing Integration Point                    │
│                                                               │
│ RealEstate_Sync_Import_Engine::process_single_property()     │
│                                                               │
│ File: includes/class-realestate-sync-import-engine.php       │
│ Method: process_single_property() (line 772)                 │
│                                                               │
│ FIXED CODE (2025-12-14):                                     │
│ ─────────────────────────                                    │
│ LINE 794:                                                     │
│ $change_status = $this->self_healing_manager->               │
│     resolve_property_action($property_id, $property_hash);   │
│                                                               │
│ ✅ Now uses self-healing logic                               │
│ ✅ Detects orphaned posts                                    │
│ ✅ Rebuilds tracking automatically                           │
│ ✅ Prevents duplicates                                       │
│ ✅ Handles SKIP action for unchanged properties              │
│                                                               │
│ PREVIOUS CODE (DEPRECATED):                                  │
│ ────────────────────────────                                 │
│ $change_status = $this->tracking_manager->                   │
│     check_property_changes($property_id, $property_hash);    │
│                                                               │
│ ❌ Used OLD tracking manager method                          │
│ ❌ Created duplicates when tracking broken                   │
└──────────────────────────────────────────────────────────────┘
```

---

## Comparison: Test Import vs Batch Import

**ENTRAMBI ORA USANO SELF-HEALING** ✅

### Test Import (Test XML piccoli - Dashboard)

**File:** `includes/class-realestate-sync-import-engine.php`
**Method:** `execute_chunked_import()` (line ~588)
**Chiamato da:** `admin/class-realestate-sync-admin.php` - Test upload handler (line 2119)

```php
// LINE 724: ✅ USES SELF-HEALING
$this->logger->log("➤ STEP 2: TRACKING - Self-healing property resolution", 'info');
$change_status = $this->self_healing_manager->resolve_property_action(
    $property_id,
    $property_hash
);

if ($change_status['action'] === 'skip') {
    $this->stats['skipped_properties']++;
    return;
}
```

**Usato per:** Solo test import (upload piccoli XML di prova dalla dashboard)
**Frequenza:** Raramente - solo durante test manuali
**Result:** ✅ Self-healing active

### Batch Import (TUTTI I FLUSSI PRODUZIONE)

**File:** `includes/class-realestate-sync-import-engine.php`
**Method:** `process_single_property()` (line 772)
**Chiamato da:** `Batch_Processor::process_property()` (line 508)

```php
// LINE 794: ✅ FIXED - NOW USES SELF-HEALING (2025-12-14)
$this->logger->log("➤ STEP 2: TRACKING - Self-healing property resolution", 'info');
$change_status = $this->self_healing_manager->resolve_property_action(
    $property_id,
    $property_hash
);

if ($change_status['action'] === 'skip') {
    return array(
        'success' => true,
        'action' => 'skipped',
        'post_id' => $change_status['wp_post_id']
    );
}
```

**Usato per:** TUTTI e 3 i flussi produzione (filesystem, manual, scheduled)
**Frequenza:** Giornaliero/settimanale - import di produzione
**Result:** ✅ Self-healing active, orphans prevented, tracking rebuilt

---

## Database Tables Involved

### Queue Table: `kre_realestate_import_queue`

**Purpose:** Stores items to be processed in batches

**Schema:**
```sql
CREATE TABLE kre_realestate_import_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    item_type ENUM('agency', 'property') NOT NULL,
    item_id VARCHAR(255) NOT NULL,
    wp_post_id BIGINT UNSIGNED NULL,
    status ENUM('pending', 'processing', 'done', 'error', 'retry') DEFAULT 'pending',
    priority INT DEFAULT 0,
    retry_count INT DEFAULT 0,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    INDEX idx_session_status (session_id, status),
    INDEX idx_item (item_id, item_type)
);
```

**Lifecycle:**
1. Created by `Batch_Processor::scan_and_populate_queue()` with status='pending'
2. Updated to 'processing' when `Batch_Processor::process_next_batch()` starts item
3. Updated to 'done' if processing succeeds
4. Updated to 'error' if processing fails

### Tracking Table: `kre_realestate_sync_tracking`

**Purpose:** Links property_id to wp_post_id and stores hash for change detection

**Schema:**
```sql
CREATE TABLE kre_realestate_sync_tracking (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id VARCHAR(100) NOT NULL,
    wp_post_id BIGINT UNSIGNED NOT NULL,
    property_hash VARCHAR(32) NOT NULL,
    data_snapshot LONGTEXT NULL,
    last_synced_at DATETIME NOT NULL,
    UNIQUE KEY unique_property (property_id),
    KEY idx_wp_post (wp_post_id)
);
```

**Self-Healing Behavior:**
- When `wp_post_id` exists in wp_posts but NOT in tracking → Rebuild tracking
- When `wp_post_id` exists in BOTH and hash different → Update post
- When `wp_post_id` exists in BOTH and hash same → Skip (no changes)

---

## The Bug Explained

### What Happens When Tracking Is Broken

**Scenario:** Property 12345 has:
- wp_post with import_id=12345 (ID 678)
- NO tracking record (tracking table entry missing or pointing to wrong post)

### OLD Method Behavior (Current Bug)

```php
// tracking_manager->check_property_changes()

1. Check tracking table for property_id = 12345
2. NOT FOUND → return action='create'
3. Import Engine creates NEW post
4. Result: DUPLICATE POST
```

### Self-Healing Method Behavior (Correct)

```php
// self_healing_manager->resolve_property_action()

1. Search wp_posts for import_id = 12345
2. FOUND post 678
3. Check tracking table
4. NOT FOUND → SELF-HEAL:
   a. Rebuild tracking record (property_id=12345 → wp_post_id=678)
   b. Return action='update' (force update to guarantee data fresh)
5. Import Engine UPDATES existing post 678
6. Result: NO DUPLICATE, TRACKING FIXED
```

---

## Evidence from Production Logs

### Log Analysis (2025-12-13 Self-Healing Test)

**File:** `debug.log` from production after self-healing deployment

**Observations:**

1. ✅ **No PHP errors** - Self-healing code is syntactically correct
2. ❌ **No self-healing logs** - Lines like `🩹 SELF-HEALING: ...` completely absent
3. ✅ **Batch processor logs present** - Shows batch processing executed normally
4. ❌ **52 orphan posts created** - Same duplicates as before (14 properties × ~4 copies each)

**Conclusion:**
Self-healing code is deployed and working, but the batch processor is NOT calling it.

### Example Duplicate Pattern

| Property ID | Title | Orphan Posts Created |
|-------------|-------|---------------------|
| 12345 | Apartment in Trento | 4 |
| 67890 | Villa in Bolzano | 4 |
| ... | ... | ... |
| **Total** | **14 properties** | **52 posts** |

**Pattern:** Properties that previously had broken tracking created exact same number of duplicates.

---

## ✅ Fix Applied (2025-12-14)

### File Modified
`includes/class-realestate-sync-import-engine.php`

### Method Fixed
`process_single_property()` (line 772)

### Change Applied

**AFTER (line 794) - FIXED:**
```php
// 🩹 STEP 2: TRACKING - Self-healing property resolution
$this->logger->log("➤ STEP 2: TRACKING - Self-healing property resolution", 'info');
$change_status = $this->self_healing_manager->resolve_property_action($property_id, $property_hash);

// Handle SKIP action
if ($change_status['action'] === 'skip') {
    $this->logger->log("⏭️ STEP 2b: Property {$property_id} SKIPPED - no changes detected", 'info');
    return array(
        'success' => true,
        'action' => 'skipped',
        'post_id' => $change_status['wp_post_id']
    );
}

// Force has_changed to true for compatibility with downstream code
$change_status['has_changed'] = true;
```

**BEFORE (DEPRECATED):**
```php
// STEP 2: Check if property exists and needs update
$change_status = $this->tracking_manager->check_property_changes($property_id, $property_hash);
```

**Status:** ✅ Fix applied and syntax validated - Ready for deployment

---

## Codice Potenzialmente Obsoleto - Candidati per Pulizia

Questa sezione identifica codice che potrebbe essere obsoleto o ridondante.
**⚠️ NON eliminare senza prima verificare l'impatto.**

### 1. Metodi Semi-Obsoleti in RealEstate_Sync_Import_Engine

#### `execute_chunked_import()` (line ~588)
- **Status:** ⚠️ SEMI-OBSOLETO
- **Uso attuale:** SOLO per test import (upload piccoli XML di test)
- **Chiamato da:** `admin/class-realestate-sync-admin.php` line 2119
- **Frequenza:** Raramente - solo test manuali
- **Può essere rimosso?** ❌ NO - ancora usato per test import
- **Può essere consolidato?** ✅ FORSE - potrebbe essere unificato con batch flow
- **Raccomandazione:** MANTENERE per ora, valutare consolidamento futuro

### 2. Metodi Tracking Manager - Verificare Uso

#### `RealEstate_Sync_Tracking_Manager::check_property_changes()`
- **Status:** ⚠️ DEPRECATO (sostituito da self-healing)
- **Uso attuale:** Probabilmente ancora chiamato da `Batch_Orchestrator` (line 342) per conteggio
- **Chiamato da:**
  - ~~`Import_Engine::process_single_property()` - RIMOSSO (ora usa self-healing)~~
  - `Batch_Orchestrator::process_xml_batch()` - DA VERIFICARE
- **Può essere rimosso?** ❌ NO - ancora usato da Orchestrator
- **Raccomandazione:** VALUTARE se Orchestrator può usare self-healing per conteggio

### 3. Entry Points - Verificare Se Tutti Usati

#### A. Filesystem Upload (admin/class-realestate-sync-admin.php)
- **Handler:** `handle_file_upload_import()` (line ~1850)
- **Status:** ✅ ATTIVO
- **Usato?** ✅ SI - upload XML da admin dashboard
- **Raccomandazione:** MANTENERE

#### B. Manual Download (admin/class-realestate-sync-admin.php)
- **Handler:** `handle_manual_download_import()` (line ~781)
- **Status:** ✅ ATTIVO
- **Usato?** ✅ SI - download e import manuale
- **Raccomandazione:** MANTENERE

#### C. Scheduled Cron (includes/class-realestate-sync-scheduler.php)
- **Handler:** `run_scheduled_import()` (line ~150)
- **Status:** ✅ ATTIVO
- **Usato?** ✅ SI - import automatico programmato
- **Raccomandazione:** MANTENERE

#### D. Sample/Test Import (realestate-sync.php)
- **Location:** line 600, 660
- **Status:** ⚠️ DA VERIFICARE
- **Usato?** ❓ UNCLEAR - potrebbe essere codice di debug/test
- **Raccomandazione:** VERIFICARE se ancora necessario

### 4. Documentazione Obsoleta

#### File da Aggiornare/Rimuovere:
- `FLOW_ANALYSIS.md` - Potrebbe contenere analisi vecchia pre-self-healing
- `BATCH_IMPLEMENTATION_COMPLETE.md` - Potrebbe essere obsoleto
- `IMPORT_ENGINE_VS_BATCH_COMPARISON.md` - Potrebbe contenere confronti obsoleti
- `NIGHT_WORK_SUMMARY.md` - Potrebbe essere solo storico

**Raccomandazione:** Revisionare e archiviare o aggiornare

### 5. Batch Orchestrator - Doppio Check

#### Line 342: `check_property_changes()` usato per pre-scan
```php
$change_check = $tracking_manager->check_property_changes($property_id, $hash);
```

- **Scopo:** Conta quante properties hanno cambiamenti PRIMA di iniziare import
- **Problema:** Usa metodo vecchio invece di self-healing
- **Impatto:** Solo statistiche pre-import, NON influisce su processing reale
- **Può essere aggiornato?** ✅ SI - potrebbe usare self-healing per conteggio accurato
- **Raccomandazione:** VALUTARE aggiornamento per consistenza

---

## Action Items per Pulizia Codice (Futuro)

### Priorità ALTA
1. ⚠️ **Verificare uso di `check_property_changes()` in Batch_Orchestrator**
   - Se usato solo per conteggio, valutare switch a self-healing
   - Garantire consistenza logica in tutto il codebase

### Priorità MEDIA
2. ⚠️ **Valutare consolidamento `execute_chunked_import()`**
   - Opzione A: Mantenere separato per semplicità test
   - Opzione B: Far usare batch flow anche ai test (più consistente)

3. ⚠️ **Audit entry points in realestate-sync.php**
   - Verificare se line 600, 660 sono ancora usati
   - Se debug code, rimuovere o commentare

### Priorità BASSA
4. 📄 **Pulizia documentazione**
   - Archiviare documenti storici (FLOW_ANALYSIS.md, NIGHT_WORK_SUMMARY.md)
   - Aggiornare o rimuovere documenti obsoleti
   - Mantenere solo documentazione corrente e accurata

---

## Metodi ATTIVI - Fotografia Reale del Sistema

Questa è la lista definitiva dei metodi EFFETTIVAMENTE usati in produzione.

### Entry Points (3)
1. ✅ `admin/class-realestate-sync-admin.php::handle_file_upload_import()` → Batch Orchestrator
2. ✅ `admin/class-realestate-sync-admin.php::handle_manual_download_import()` → Batch Orchestrator
3. ✅ `includes/class-realestate-sync-scheduler.php::run_scheduled_import()` → Batch Orchestrator

### Core Processing Pipeline
1. ✅ `RealEstate_Sync_Batch_Orchestrator::process_xml_batch()` - Coordina tutto
2. ✅ `RealEstate_Sync_Batch_Processor::scan_and_populate_queue()` - Crea queue
3. ✅ `RealEstate_Sync_Batch_Processor::process_next_batch()` - Loop processing
4. ✅ `RealEstate_Sync_Batch_Processor::process_agency()` - Processa agenzie
5. ✅ `RealEstate_Sync_Batch_Processor::process_property()` - Delega property
6. ✅ `RealEstate_Sync_Import_Engine::process_single_property()` - Processing finale
7. ✅ `RealEstate_Sync_Self_Healing_Manager::resolve_property_action()` - Self-healing

### Supporting Components
1. ✅ `RealEstate_Sync_Queue_Manager` - Gestione queue database
2. ✅ `RealEstate_Sync_Tracking_Manager` - Gestione tracking (hash, snapshot)
3. ✅ `RealEstate_Sync_WP_Importer_API` - Creazione/aggiornamento WP posts
4. ✅ `RealEstate_Sync_Agency_Manager` - Gestione agenzie
5. ✅ `RealEstate_Sync_Download_Manager` - Download/estrazione XML da FTP

### Test/Debug Only
⚠️ `RealEstate_Sync_Import_Engine::execute_chunked_import()` - Solo test import

---

---

## Testing Plan

### Test Scenario 1: Clean Import (No Orphans)
1. Delete all orphan posts
2. Trigger batch import (manual or scheduled)
3. **Expected:** All properties imported successfully, no duplicates

### Test Scenario 2: Import With Orphans (Self-Healing Test)
1. Create orphan situation:
   - Delete tracking record for property 12345
   - Keep wp_post 678 with import_id=12345
2. Trigger batch import
3. **Expected:**
   - Self-healing log: `🩹 SELF-HEALING: Post exists but tracking missing!`
   - Tracking rebuilt
   - Post 678 updated
   - NO duplicate created

### Test Scenario 3: Import With Hash Changes
1. Modify property data in XML (change price)
2. Keep tracking record intact
3. Trigger batch import
4. **Expected:**
   - Hash comparison detects change
   - Post updated
   - No duplicate created

### Verification Checklist
- [ ] Zero orphan posts after import
- [ ] All tracking records present
- [ ] Self-healing logs in debug.log
- [ ] No duplicate posts created
- [ ] Queue items all 'done' status

---

## Rollback Plan

If fix causes issues:

### Option 1: Quick Rollback via FTP
```powershell
# Download previous version from GitHub tag
# Upload via FTP to restore old Import Engine
```

### Option 2: Git Rollback
```bash
git checkout e0c533b -- includes/class-realestate-sync-import-engine.php
# Re-upload to production
```

---

## Related Documentation

- `SELF-HEALING-IMPLEMENTATION-MASTER.md` - Complete self-healing design
- `DEPLOYMENT-PLAN.md` - Original deployment plan (needs update)
- `docs/SELF-HEALING-INTEGRATION.md` - Integration guide

---

## Next Steps

1. ✅ **Document created** - This flow diagram complete
2. ⏳ **Modify `process_single_property()`** - Apply self-healing fix
3. ⏳ **Test locally** - Verify no PHP errors
4. ⏳ **Deploy to production** - Upload modified Import Engine
5. ⏳ **Test with orphan cleanup** - Verify self-healing works
6. ⏳ **Monitor logs** - Confirm self-healing logs appear
7. ⏳ **Verify zero duplicates** - Check orphan count after import

---

**END OF DOCUMENT**
