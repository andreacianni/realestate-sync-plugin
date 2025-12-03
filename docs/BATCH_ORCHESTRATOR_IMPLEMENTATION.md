# Batch Orchestrator Implementation

**Data**: 1 Dicembre 2025
**Status**: ✅ Implementato
**Basato su**: BATCH_ARCHITECTURE_REVISED.md (visione utente)

---

## 🎯 PRINCIPIO ARCHITETTURALE

**I tre entry point (A, B, C) differiscono SOLO nell'acquisizione file.**

**Dopo l'acquisizione, tutti usano lo STESSO flusso di batch processing.**

---

## 📦 FILE CREATI/MODIFICATI

### 1. NUOVO: `includes/class-realestate-sync-batch-orchestrator.php`

**Classe**: `RealEstate_Sync_Batch_Orchestrator`

**Metodo principale**:
```php
public static function process_xml_batch($xml_file, $mark_as_test = false)
```

**Workflow completo**:
```
1. Index & Filter (TN/BZ only)
   ├─> Load XML
   ├─> Get enabled provinces from settings
   ├─> Extract agencies (filtered by Agency_Parser)
   └─> Extract properties (filter by comune_istat: 021xxx=BZ, 022xxx=TN)

2. Create Queue
   ├─> Generate session_id
   ├─> Clear existing queue for session
   ├─> Add agencies to queue (higher priority)
   └─> Add properties to queue

3. Process First Batch (Immediate)
   ├─> Save progress metadata
   ├─> Create Batch_Processor instance
   └─> Process up to 10 items immediately

4. Setup Continuation (Cron)
   ├─> If incomplete: set transient
   └─> If complete: update progress to 'completed'
```

**Return value**:
```php
array(
    'success' => true,
    'session_id' => string,
    'total_queued' => int,
    'agencies_queued' => int,
    'properties_queued' => int,
    'first_batch_processed' => int,
    'agencies_processed' => int,
    'properties_processed' => int,
    'complete' => bool,
    'remaining' => int
)
```

---

### 2. MODIFICATO: `realestate-sync.php` (Main Plugin File)

**Sezione**: `load_dependencies()` (linee 138-141)

**Aggiunto**:
```php
// Batch System classes
require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-queue-manager.php';
require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-batch-processor.php';
require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-batch-orchestrator.php';
```

**Scopo**: Carica le classi del batch system all'avvio del plugin

---

### 3. MODIFICATO: `admin/class-realestate-sync-admin.php`

#### Button A: `handle_process_test_file()` (linee 1756-1794)

**PRIMA** (Import Engine):
```php
$import_engine = new RealEstate_Sync_Import_Engine();
$import_engine->configure($settings);
$results = $import_engine->execute_chunked_import($temp_file, array(
    'mark_as_test' => $mark_as_test
));
```

**DOPO** (Batch Orchestrator):
```php
$result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch($temp_file, $mark_as_test);

if (!$result['success']) {
    throw new Exception('Batch processing failed: ' . ($result['error'] ?? 'Unknown error'));
}
```

---

#### Button B: `handle_manual_import()` (linee 740-760)

**PRIMA** (Direct Batch_Processor):
```php
$batch_processor = new RealEstate_Sync_Batch_Processor($session_id, $xml_file);
$scan_result = $batch_processor->scan_and_populate_queue($mark_as_test);
$first_batch_result = $batch_processor->process_next_batch();
set_transient('realestate_sync_pending_batch', $session_id, 300);
```

**DOPO** (Batch Orchestrator):
```php
$result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch($xml_file, $mark_as_test);

if (!$result['success']) {
    throw new Exception('Batch processing failed: ' . ($result['error'] ?? 'Unknown error'));
}
```

---

## 🔄 FLUSSO ESECUTIVO UNIFICATO

### Entry Point A: Button "Processa File XML"

```
1. User uploads XML file
   ↓
2. handle_process_test_file()
   ├─> Read uploaded file
   ├─> Save to temp location
   │
   └─> ✅ CALL ORCHESTRATOR
       Batch_Orchestrator::process_xml_batch($temp_file, $mark_as_test)
       │
       └─> [SHARED WORKFLOW] ←───────┐
                                     │
```

### Entry Point B: Button "Scarica e Importa Ora"

```
1. User clicks button
   ↓
2. handle_manual_import()
   ├─> Download XML from server
   ├─> Extract .tar.gz archive
   │
   └─> ✅ CALL ORCHESTRATOR
       Batch_Orchestrator::process_xml_batch($xml_file, $mark_as_test)
       │
       └─> [SHARED WORKFLOW] ←───────┤
                                     │
```

### Entry Point C: Cron "Import Notturno" (TODO)

```
1. Server cron triggers
   ↓
2. handle_scheduled_import()
   ├─> Download XML from server
   ├─> Extract .tar.gz archive
   │
   └─> ✅ CALL ORCHESTRATOR
       Batch_Orchestrator::process_xml_batch($xml_file, false)
       │
       └─> [SHARED WORKFLOW] ←───────┘
```

---

## 📋 SHARED WORKFLOW DETTAGLIATO

```
[SHARED WORKFLOW]
│
├─> STEP 1: Index & Filter
│   ├─> simplexml_load_file($xml_file)
│   ├─> Get enabled_provinces from settings
│   ├─> Agency_Parser::extract_agencies_from_xml($xml)
│   │   └─> (already filters by comune_istat internally)
│   └─> foreach $xml->annuncio:
│       ├─> Skip if deleted === '1'
│       ├─> Check comune_istat (021xxx=BZ, 022xxx=TN)
│       └─> Collect property IDs
│
├─> STEP 2: Create Queue
│   ├─> $session_id = 'import_' . uniqid()
│   ├─> Queue_Manager::clear_session_queue($session_id)
│   ├─> foreach agency: Queue_Manager::add_agency()
│   └─> foreach property: Queue_Manager::add_property()
│
├─> STEP 3: Process First Batch
│   ├─> update_option('realestate_sync_background_import_progress', ...)
│   ├─> new Batch_Processor($session_id, $xml_file)
│   └─> Batch_Processor::process_next_batch()
│       └─> Process max 10 items immediately
│
└─> STEP 4: Setup Continuation
    ├─> if (!complete):
    │   └─> set_transient('realestate_sync_pending_batch', $session_id, 300)
    └─> if (complete):
        └─> update_option(..., status='completed')
```

---

## ✅ VANTAGGI IMPLEMENTAZIONE

### 1. DRY (Don't Repeat Yourself)
- **Prima**: 3 implementazioni diverse (2 attuali + 1 futura cron)
- **Dopo**: 1 sola implementazione condivisa
- **Risultato**: Fix un bug = fix ovunque

### 2. Manutenibilità
- Cambio al workflow: modifica 1 file
- Test: testa 1 funzione = testa tutto
- Debug: 1 solo flusso da analizzare

### 3. Consistenza
- Stesso comportamento per tutti gli entry point
- Stesso logging ([BATCH-ORCHESTRATOR] prefix)
- Stesse metriche di ritorno

### 4. Scalabilità
- Aggiungere entry point futuro = 2 righe di codice
- Esempio: webhook, API endpoint, CLI command

---

## 🔍 LOG MARKERS

Il Batch_Orchestrator usa prefix consistente:

```
[BATCH-ORCHESTRATOR] ========================================
[BATCH-ORCHESTRATOR] Starting batch import: import_692ccc892d484
[BATCH-ORCHESTRATOR] XML file: /tmp/realestate-test-1701449247.xml
[BATCH-ORCHESTRATOR] Mark as test: YES
[BATCH-ORCHESTRATOR] ========================================
[BATCH-ORCHESTRATOR] STEP 1: Indexing XML and filtering TN/BZ
[BATCH-ORCHESTRATOR] Enabled provinces: TN, BZ
[BATCH-ORCHESTRATOR] Agencies found: 5
[BATCH-ORCHESTRATOR] Properties found (TN/BZ): 45
[BATCH-ORCHESTRATOR] Properties skipped (other provinces): 0
[BATCH-ORCHESTRATOR] Deleted items skipped: 2
[BATCH-ORCHESTRATOR] STEP 2: Creating queue
[BATCH-ORCHESTRATOR] Queue created: 5 agencies + 45 properties = 50 total items
[BATCH-ORCHESTRATOR] STEP 3: Processing first batch (immediate)
[BATCH-ORCHESTRATOR] First batch complete:
[BATCH-ORCHESTRATOR] - Processed: 10
[BATCH-ORCHESTRATOR] - Agencies: 5
[BATCH-ORCHESTRATOR] - Properties: 5
[BATCH-ORCHESTRATOR] - Complete: NO
[BATCH-ORCHESTRATOR] STEP 4: Setting up cron continuation
[BATCH-ORCHESTRATOR] Transient set - cron will continue processing
[BATCH-ORCHESTRATOR] Remaining items: 40
[BATCH-ORCHESTRATOR] ========================================
[BATCH-ORCHESTRATOR] Batch orchestration complete
[BATCH-ORCHESTRATOR] ========================================
```

---

## 🧪 TESTING PLAN

### Test 1: Button A con file piccolo (5 agenzie, 10 proprietà)

**Aspettativa**:
- ✅ Tutte processate nel primo batch
- ✅ `complete: true`
- ✅ No transient set
- ✅ Agenzie + proprietà create

**Comando**:
```
Upload file piccolo → Click "Processa File XML"
```

**Verifica**:
```
- Check database: agenzie create = 5
- Check database: proprietà create = 10
- Check debug.log: [BATCH-ORCHESTRATOR] markers
- Check response: complete = true
```

---

### Test 2: Button B con download full (50+ agenzie, 800+ proprietà)

**Aspettativa**:
- ✅ Primo batch: 10 items processati
- ✅ `complete: false`
- ✅ Transient set per cron
- ✅ Cron continua processing

**Comando**:
```
Click "Scarica e Importa Ora"
```

**Verifica**:
```
- Check database: 10 items created
- Check transient: realestate_sync_pending_batch exists
- Wait 1 minute for cron
- Check database: more items created
- Repeat until complete
```

---

## 🚀 PROSSIMI STEP

1. ✅ **FATTO**: Implementato Batch_Orchestrator
2. ✅ **FATTO**: Modificato Button A
3. ✅ **FATTO**: Modificato Button B
4. ⏳ **TODO**: Test Button A con file piccolo
5. ⏳ **TODO**: Test Button B con download
6. ⏳ **TODO**: Implementa Cron C usando orchestrator

---

## 📝 DIFFERENZE DA IMPORT ENGINE

| Aspetto | Import Engine | Batch Orchestrator |
|---------|--------------|-------------------|
| **Execution** | Synchronous (tutto in 1 request) | Asynchronous (batch + cron) |
| **Timeout** | Soggetto a max_execution_time | 50s per batch, nessun timeout totale |
| **Scalability** | Max ~100 items | Illimitato (via queue) |
| **Queue** | No | Sì (database-backed) |
| **Resumable** | No | Sì (via transient + cron) |
| **Monitoring** | No | Sì (via progress option) |

---

**Creato**: 1 Dicembre 2025
**Implementazione**: Batch Orchestrator v1.0
**Basato su**: Visione architetturale utente (BATCH_ARCHITECTURE_REVISED.md)
