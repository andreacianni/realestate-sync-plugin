# ARCHITETTURA - RealEstate Sync Plugin

**Data Creazione**: 03 Dicembre 2025
**Ora**: 06:11:10
**Versione Plugin**: 1.5.0 (Batch System)

Documentazione completa dell'architettura del plugin per import immobili da XML.

**Scopo**: Comprendere la struttura completa del sistema, le responsabilità di ogni componente e come interagiscono tra loro.

---

## 📋 INDICE

1. [Overview Sistema](#1-overview-sistema)
2. [Architettura a Livelli](#2-architettura-a-livelli)
3. [Pattern Architetturali](#3-pattern-architetturali)
4. [Componenti Principali](#4-componenti-principali)
5. [Flusso Dati](#5-flusso-dati)
6. [Database Design](#6-database-design)
7. [Security Architecture](#7-security-architecture)
8. [Performance & Scalability](#8-performance--scalability)
9. [Extension Points](#9-extension-points)

---

## 1. OVERVIEW SISTEMA

### 1.1 Descrizione Generale

**RealEstate Sync Plugin** è un sistema professionale di import dati immobiliari per WordPress + WPResidence.

**Funzionalità Principali**:
- ✅ Download automatico file XML tar.gz
- ✅ Parsing XML con 28,625+ proprietà
- ✅ Filtraggio province (TN/BZ only - 781 proprietà)
- ✅ Import batch ottimizzato (queue-based)
- ✅ Creazione agenzie con logo via API
- ✅ Creazione proprietà con gallery (4 systems)
- ✅ Duplicate detection (hash-based)
- ✅ Automatic continuation via server cron
- ✅ Admin dashboard (4 tabs)
- ✅ Comprehensive logging

**Versione Corrente**: 1.5.0 (Batch System)
**Data Release**: 30-Nov-2025
**WordPress**: 5.8+
**PHP**: 7.4+
**Theme**: WPResidence 3.x

---

### 1.2 Architettura ad Alto Livello

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER INTERFACE                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  Dashboard   │  │   Settings   │  │     Logs     │          │
│  │   (4 tabs)   │  │   Manager    │  │    Viewer    │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
           │                    │                    │
           │ AJAX               │ WP Options         │ Read Files
           ├────────────────────┼────────────────────┘
           ↓                    ↓
┌─────────────────────────────────────────────────────────────────┐
│                      APPLICATION LAYER                           │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                  ENTRY POINTS (3)                         │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐   │  │
│  │  │   Manual     │  │   WP Cron    │  │ Server Cron  │   │  │
│  │  │   Import     │  │   (Daily)    │  │(Continuation)│   │  │
│  │  └──────────────┘  └──────────────┘  └──────────────┘   │  │
│  └──────────────────────────────────────────────────────────┘  │
│           │                    │                    │            │
│           └────────────────────┼────────────────────┘            │
│                                ↓                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │           BATCH ORCHESTRATOR (Coordinator)                │  │
│  │   • Index & Filter (TN/BZ)                               │  │
│  │   • Create Queue                                          │  │
│  │   • Process First Batch                                   │  │
│  │   • Setup Continuation                                    │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                ↓                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │             BATCH PROCESSOR (Executor)                    │  │
│  │   • Get pending items (max 10)                           │  │
│  │   • Process each (agency/property)                        │  │
│  │   • Mark done/failed                                      │  │
│  │   • Check completion                                      │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
           │                                    │
           │ Agencies                           │ Properties
           ↓                                    ↓
┌─────────────────────────────────────────────────────────────────┐
│                       CORE SERVICES                              │
│  ┌──────────────────────┐       ┌──────────────────────┐       │
│  │   Agency Pipeline    │       │   Property Pipeline   │       │
│  │  ┌────────────────┐  │       │  ┌────────────────┐  │       │
│  │  │ Agency Parser  │  │       │  │  XML Parser    │  │       │
│  │  │  (extract)     │  │       │  │  (GOLDEN)      │  │       │
│  │  └────────────────┘  │       │  └────────────────┘  │       │
│  │          ↓            │       │          ↓            │       │
│  │  ┌────────────────┐  │       │  ┌────────────────┐  │       │
│  │  │ Agency Manager │  │       │  │ Property Mapper│  │       │
│  │  │  (PROTECTED)   │  │       │  │  (PROTECTED)   │  │       │
│  │  │  • Create/Upd  │  │       │  │  • 80+ fields  │  │       │
│  │  │  • Logo dwn    │  │       │  │  • Categories  │  │       │
│  │  └────────────────┘  │       │  └────────────────┘  │       │
│  │          ↓            │       │          ↓            │       │
│  │  ┌────────────────┐  │       │  ┌────────────────┐  │       │
│  │  │ Agency API     │  │       │  │  WP Importer   │  │       │
│  │  │   Writer       │  │       │  │     API        │  │       │
│  │  │                │  │       │  │  (GOLDEN)      │  │       │
│  │  │  POST /agent   │  │       │  │  • Hash check  │  │       │
│  │  └────────────────┘  │       │  │  • Create/Upd  │  │       │
│  └──────────────────────┘       │  │  • Gallery     │  │       │
│                                  │  │  • Linking     │  │       │
│                                  │  └────────────────┘  │       │
│                                  └──────────────────────┘       │
└─────────────────────────────────────────────────────────────────┘
           │                                    │
           └────────────────┬───────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│                     PERSISTENCE LAYER                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │    Queue     │  │   Tracking   │  │  WP Options  │          │
│  │    Table     │  │    Table     │  │   (Settings) │          │
│  │   (MySQL)    │  │   (MySQL)    │  │    (MySQL)   │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│         │                  │                  │                  │
│         └──────────────────┴──────────────────┘                  │
│                             │                                    │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              WordPress Database (MySQL)                   │  │
│  │   • kre_posts (properties, agencies)                     │  │
│  │   • kre_postmeta (all meta fields)                       │  │
│  │   • kre_terms, kre_term_taxonomy (categories)           │  │
│  │   • kre_term_relationships (property → categories)       │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. ARCHITETTURA A LIVELLI

### 2.1 Presentation Layer (UI)

**Responsabilità**: Interfaccia utente e interazione

**Componenti**:
- **Admin Dashboard** (`admin/views/dashboard.php`)
  - Tab 1: Dashboard (import controls)
  - Tab 2: Info (field mapping)
  - Tab 3: Tools (cleanup, testing)
  - Tab 4: Logs (viewer)
- **AJAX Handlers** (`admin/class-realestate-sync-admin.php`)
  - 20+ AJAX endpoints
  - Nonce verification
  - Capability checks
- **Settings UI** (WP Admin → Settings)
  - XML URL, credentials
  - Province selection
  - Automation toggles

**Tecnologie**: HTML, CSS, JavaScript, jQuery, WordPress Admin API

---

### 2.2 Application Layer (Business Logic)

**Responsabilità**: Orchestrazione workflow, business rules

**Componenti**:
- **Batch Orchestrator** (`class-realestate-sync-batch-orchestrator.php`)
  - Coordina intero workflow
  - Index, Filter, Queue, Process
  - Static methods (utility class)
- **Batch Processor** (`class-realestate-sync-batch-processor.php`)
  - Esegue queue items
  - Timeout management
  - Error handling con retry
- **Import Engine** (`class-realestate-sync-import-engine.php`)
  - Legacy streaming import
  - Conversione formati
  - Stateful processing

**Pattern**: Command Pattern, Strategy Pattern, Observer Pattern

---

### 2.3 Service Layer (Core Services)

**Responsabilità**: Servizi riutilizzabili, logica dominio

**Agency Services**:
- **Agency Parser** (`class-realestate-sync-agency-parser.php`) [PROTECTED]
  - Extract agencies da XML
  - Filtraggio province
  - Deduplicazione
- **Agency Manager** (`class-realestate-sync-agency-manager.php`) [PROTECTED]
  - Create/Update agencies
  - Logo download
  - API integration
- **Agency API Writer** (`class-realestate-sync-wpresidence-agency-api-writer.php`)
  - REST API calls (POST /wpresidence/v1/agency/add → crea `estate_agency`)
  - Response handling

**Property Services**:
- **XML Parser** (`class-realestate-sync-xml-parser.php`) [GOLDEN]
  - Streaming parse con XMLReader
  - Memory efficient
  - 450+ fields extracted
- **Property Mapper** (`class-realestate-sync-property-mapper.php`) [PROTECTED v3.3]
  - XML → WPResidence mapping
  - 80+ field transformations
  - Categories, amenities, energy class
- **WP Importer API** (`class-realestate-sync-wp-importer-api.php`) [PROTECTED v1.4]
  - Create/Update properties via API
  - Hash-based duplicate detection
  - Gallery system (4 formats)
  - Agency linking

**Shared Services**:
- **Logger** (`class-realestate-sync-logger.php`)
  - File-based logging
  - Rotation (5MB, 10 files, 30 days)
  - 4 levels (ERROR, WARNING, INFO, DEBUG)
- **Tracking Manager** (`class-realestate-sync-tracking-manager.php`)
  - Import history
  - Hash comparison
  - Change detection
- **Queue Manager** (`class-realestate-sync-queue-manager.php`)
  - Database queue operations
  - Status management
  - Statistics

---

### 2.4 Data Access Layer (Persistence)

**Responsabilità**: Database operations, data persistence

**Componenti**:
- **Queue Manager** - CRUD operations su queue table
- **Tracking Manager** - CRUD operations su tracking table
- **WordPress DB API** - wpdb wrapper per sicurezza

**Database Tables**:
- `{prefix}realestate_import_queue` - Batch queue
- `{prefix}realestate_sync_tracking` - Import tracking
- `{prefix}posts` - Properties, agencies (CPT)
- `{prefix}postmeta` - Property fields
- `{prefix}options` - Plugin settings

**Pattern**: Repository Pattern, Active Record

---

## 3. PATTERN ARCHITETTURALI

### 3.1 Singleton Pattern

**Utilizzato da**:
- `RealEstate_Sync` (main plugin class)
- `RealEstate_Sync_Logger`

**Scopo**: Single instance globale

**Esempio**:
```php
class RealEstate_Sync_Logger {
    private static $instance = null;

    private function __construct() {
        // Private constructor
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

---

### 3.2 Dependency Injection

**Utilizzato da**:
- `RealEstate_Sync::init_plugin()` - Dependency container
- `Batch_Processor::__construct()` - Inject dependencies
- `Import_Engine::__construct()` - Constructor injection

**Scopo**: Loose coupling, testability

**Esempio**:
```php
class RealEstate_Sync_Batch_Processor {
    private $queue_manager;
    private $agency_manager;
    private $import_engine;

    public function __construct($session_id, $xml_file, $mark_as_test) {
        // Dependency injection
        $this->queue_manager = new RealEstate_Sync_Queue_Manager();
        $this->agency_manager = new RealEstate_Sync_Agency_Manager();
        $this->import_engine = new RealEstate_Sync_Import_Engine(...);
    }
}
```

---

### 3.3 Wrapper/Adapter Pattern

**Utilizzato da**:
- `Batch_Processor` - Wraps PROTECTED classes (non modifica, chiama solo)

**Scopo**: Use PROTECTED code without modification

**Esempio**:
```php
class RealEstate_Sync_Batch_Processor {
    // ✅ WRAPPER: Calls PROTECTED methods without modifying them
    private function process_property($queue_item) {
        // 1. Extract from XML (wrapper call)
        $property_data = $this->xml_parser->parse_annuncio_xml($xml);

        // 2. Delegate to PROTECTED Import_Engine
        $result = $this->import_engine->process_single_property($property_data);

        return $result; // Just wraps, doesn't modify
    }
}
```

---

### 3.4 Strategy Pattern

**Utilizzato da**:
- `Import_Engine` - Seleziona importer (API vs Legacy)
- `WP_Importer_API` - Action strategy (insert vs update)

**Scopo**: Intercambiabile algorithm selection

**Esempio**:
```php
class RealEstate_Sync_Import_Engine {
    public function __construct($property_mapper, $wp_importer, $logger) {
        // Strategy injection
        $this->property_mapper = $property_mapper;
        $this->wp_importer = $wp_importer; // Can be API or Legacy
        $this->logger = $logger;
    }

    private function call_wp_importer($mapped_data, $property_id) {
        // Uses injected strategy
        return $this->wp_importer->process_property($mapped_data);
    }
}
```

---

### 3.5 Observer Pattern

**Utilizzato da**:
- Logger - Observers log to multiple destinations
- Callback system in XML_Parser

**Scopo**: Event-driven notifications

**Esempio**:
```php
class RealEstate_Sync_XML_Parser {
    private $property_callback;
    private $chunk_callback;

    public function set_property_callback($callback) {
        $this->property_callback = $callback;
    }

    public function parse_properties($xml_file) {
        // ... parsing ...

        // Notify observer
        if ($this->property_callback) {
            call_user_func($this->property_callback, $property_data);
        }
    }
}
```

---

### 3.6 Queue Pattern

**Utilizzato da**:
- Batch System - Database-backed queue

**Scopo**: Async processing, retry logic, job persistence

**Caratteristiche**:
- Persistent (MySQL table)
- Priority (agencies before properties)
- Status tracking (pending, processing, completed, failed)
- Retry mechanism (max 3 retries)
- Timeout handling (50 seconds/batch)

---

## 4. COMPONENTI PRINCIPALI

### 4.1 Batch Orchestrator

**File**: `includes/class-realestate-sync-batch-orchestrator.php`
**Tipo**: Static utility class
**Responsabilità**: Coordinate complete batch workflow

**Metodi Pubblici**:
- `process_xml_batch($xml_file, $mark_as_test)` - Entry point

**Workflow**:
1. Index & Filter (TN/BZ only)
2. Create queue (agencies + properties)
3. Process first batch (immediate)
4. Setup continuation (transient)

**Dependencies**: Queue_Manager, Agency_Parser, Batch_Processor

---

### 4.2 Batch Processor

**File**: `includes/class-realestate-sync-batch-processor.php`
**Tipo**: Stateful class (session-based)
**Responsabilità**: Execute queue items in batches

**Metodi Pubblici**:
- `process_next_batch()` - Process up to 10 items
- `is_complete()` - Check if session complete
- `get_final_summary()` - Get final statistics

**Metodi Privati**:
- `process_agency($queue_item)` - Process single agency
- `process_property($queue_item)` - Process single property

**Configuration**:
- `ITEMS_PER_BATCH = 10` - Batch size
- `BATCH_TIMEOUT = 50` - Timeout seconds

**Dependencies**: Queue_Manager, Agency_Manager, Agency_Parser, Import_Engine, XML_Parser

---

### 4.3 Agency Manager

**File**: `includes/class-realestate-sync-agency-manager.php`
**Tipo**: Service class
**Status**: 🛡️ PROTECTED v1.0
**Responsabilità**: Agency creation/update via API

**Metodi Pubblici**:
- `import_agencies($agencies, $mark_as_test)` - Import array of agencies
- `lookup_agency_by_xml_id($xml_id)` - Find agency for property linking

**Workflow**:
1. Check if exists (by XML ID)
2. If exists → update via API
3. If new → create via API + download logo
4. Store `agency_xml_id` meta (for property linking)

**Dependencies**: Agency_API_Writer, Logger

---

### 4.4 Property Mapper

**File**: `includes/class-realestate-sync-property-mapper.php`
**Tipo**: Transformer class
**Status**: 🛡️ PROTECTED v3.3
**Responsabilità**: XML → WPResidence field mapping

**Metodi Pubblici**:
- `map_property($property_data)` - Map single property

**Mappings** (80+ totali):
- **Basic**: title, description, price, size, rooms
- **Location**: address, city, province, zip, coordinates
- **Categories**: 28 property types
- **Amenities**: 33+ checkboxes
- **Features**: 48+ property details
- **Energy**: 14 energy classes (A4-G)
- **Advanced**: maintenance status, position, micro-categories

**Dependencies**: Logger

---

### 4.5 WP Importer API

**File**: `includes/class-realestate-sync-wp-importer-api.php`
**Tipo**: Service class
**Status**: 🛡️ PROTECTED v1.4 (GOLDEN)
**Responsabilità**: Create/update properties via WPResidence API

**Metodi Pubblici**:
- `process_property($mapped_data)` - Main entry point

**Workflow**:
1. Check duplicate (hash comparison)
2. If hash identical → skip
3. If changed/new → create or update via API
4. Setup gallery (4 gallery systems)
5. Link to agency
6. Update tracking database

**Dependencies**: WPResidence_API_Writer, Tracking_Manager, Media_Deduplicator, Logger

---

### 4.6 Queue Manager

**File**: `includes/class-realestate-sync-queue-manager.php`
**Tipo**: Data access class
**Responsabilità**: Queue database operations

**Metodi Pubblici**:
- `create_table()` - Create queue table
- `add_agency($session_id, $agency_id)` - Add agency to queue
- `add_property($session_id, $property_id)` - Add property to queue
- `get_next_batch($session_id, $limit)` - Get pending items
- `mark_processing($id)` - Mark as processing
- `mark_done($id)` - Mark as completed
- `mark_error($id, $error)` - Mark as failed (with retry logic)
- `is_session_complete($session_id)` - Check completion
- `get_session_stats($session_id)` - Get statistics

**Database**: `{prefix}realestate_import_queue`

---

### 4.7 Tracking Manager

**File**: `includes/class-realestate-sync-tracking-manager.php`
**Tipo**: Data access + business logic class
**Responsabilità**: Import tracking, duplicate detection

**Metodi Pubblici**:
- `check_property_changes($property_id, $new_hash)` - Compare hash
- `update_tracking($property_id, $wp_post_id, $hash)` - Update tracking
- `get_tracking_record($property_id)` - Get existing record
- `get_import_statistics()` - Get stats

**Hash Logic**:
```php
Returns:
- 'insert' → New property
- 'update' → Hash changed
- 'skip' → Hash identical
```

**Database**: `{prefix}realestate_sync_tracking`

---

## 5. FLUSSO DATI

### 5.1 Data Flow: Manual Import

```
1. User Click "Scarica e Importa Ora"
   ↓
2. AJAX Request → Admin::handle_manual_import()
   ↓
3. Download XML:
   - URL: https://gestionaleimmobiliare.it/.../export_gi_full_merge_multilevel.xml.tar.gz
   - Download .tar.gz (150MB)
   - Extract XML (500MB)
   - Save to /tmp/realestate_*.xml
   ↓
4. Batch Orchestrator::process_xml_batch()
   ↓
5. STEP 1: Index & Filter
   - Load XML via SimpleXML (500MB in memory)
   - Extract agencies via Agency_Parser (filters by province)
     → Result: 30 agencies (TN/BZ)
   - Filter properties by comune_istat (021xxx=BZ, 022xxx=TN)
     → Input: 28,625 properties
     → Filtered: 781 properties (TN/BZ)
     → Skipped: 27,844 properties (other provinces)
   ↓
6. STEP 2: Create Queue
   - INSERT 30 agencies (item_type='agency', status='pending')
   - INSERT 781 properties (item_type='property', status='pending')
   - Total: 811 queue items
   ↓
7. STEP 3: Process First Batch
   - Get 10 pending items (agencies first - priority)
   - FOR each item:
     IF agency:
       - Extract agency from XML
       - Call Agency_Manager::import_agencies()
         → Check if exists (by agency_xml_id)
         → If new: POST /wpresidence/v1/agency/add (crea estate_agency)
         → If exists: POST /wpresidence/v1/agency/update/:id (aggiorna estate_agency)
         → Download logo if available
         → Store agency_xml_id meta
       - Mark queue item as 'completed'

     IF property:
       - Find <annuncio> in XML by ID
       - Parse via XML_Parser::parse_annuncio_xml()
         → Extract 450+ fields from XML
       - Convert to v3 format via Import_Engine
       - Map via Property_Mapper::map_property() [PROTECTED]
         → Transform 80+ fields
       - Import via WP_Importer_API::process_property() [PROTECTED]
         → Check hash (duplicate detection)
         → If changed: POST /wp-json/wp/v2/estate_property
         → Download images, setup gallery
         → Link to agency
         → Update tracking database
       - Mark queue item as 'completed'

   - Result: 10 items processed (usually 10 agencies)
   ↓
8. STEP 4: Setup Continuation
   - set_transient('realestate_sync_pending_batch', $session_id, 300)
   - Server cron will continue every minute
   ↓
9. Return JSON to frontend:
   {
     session_id: 'import_692f6243d4d257',
     total_queued: 811,
     agencies_queued: 30,
     properties_queued: 781,
     first_batch_processed: 10,
     complete: false,
     remaining: 801
   }
```

### 5.2 Data Flow: Server Cron Continuation

```
1. Cron runs (every minute)
   ↓
2. GET batch-continuation.php?token=TrentinoImmo2025Secret!
   ↓
3. Check transient 'realestate_sync_pending_batch'
   - If not exists → echo "No pending batch" → exit
   - If exists → continue
   ↓
4. Get session info from transient
   - session_id: 'import_692f6243d4d257'
   - xml_file_path: '/tmp/realestate_*.xml'
   - mark_as_test: false
   ↓
5. Delete transient (prevent concurrent runs)
   ↓
6. Create Batch_Processor($session_id, $xml_file, $mark_as_test)
   ↓
7. Call process_next_batch()
   - Get next 10 pending items
   - Process each (same as STEP 3 above)
   - Mark as completed/failed
   ↓
8. Check if complete:
   - Query: SELECT COUNT(*) FROM queue WHERE session_id=? AND status IN ('pending','processing')
   - If count > 0 → not complete
   - If count = 0 → complete
   ↓
9a. IF NOT COMPLETE:
   - set_transient('realestate_sync_pending_batch', $session_id, 300)
   - echo "OK - More pending"
   - Cron will run again in 1 minute
   ↓
9b. IF COMPLETE:
   - update_option('...progress', ['status' => 'completed'])
   - echo "OK - All complete!"
   - No transient → cron stops
```

---

## 6. DATABASE DESIGN

### 6.1 Queue Table

**Nome**: `{prefix}realestate_import_queue`

**Schema**:
```sql
CREATE TABLE wp_realestate_import_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    item_type VARCHAR(20) NOT NULL,     -- 'agency' | 'property'
    item_id VARCHAR(100) NOT NULL,      -- Agency ID or Property ID from XML
    status VARCHAR(20) DEFAULT 'pending', -- 'pending|processing|completed|failed'
    retry_count INT DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_session (session_id),
    INDEX idx_status (status),
    INDEX idx_type (item_type),
    INDEX idx_session_status (session_id, status)
);
```

**Indexes**:
- `session_id` - Filter by import session
- `status` - Find pending/failed items
- `item_type` - Separate agencies/properties
- `session_id + status` - Composite for main query

**Usage**:
- **Writers**: Batch_Orchestrator (insert), Batch_Processor (update status)
- **Readers**: Batch_Processor (get pending), Admin (statistics)

---

### 6.2 Tracking Table

**Nome**: `{prefix}realestate_sync_tracking`

**Schema**:
```sql
CREATE TABLE wp_realestate_sync_tracking (
    id MEDIUMINT(9) AUTO_INCREMENT PRIMARY KEY,
    property_id VARCHAR(50) NOT NULL UNIQUE,  -- XML property ID
    property_hash VARCHAR(32) NOT NULL,        -- MD5 hash for change detection
    wp_post_id BIGINT(20) DEFAULT NULL,       -- WordPress post ID
    last_import_date DATETIME DEFAULT NULL,
    import_count INT(11) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',       -- 'active|inactive|deleted|error'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY property_id (property_id),
    INDEX idx_hash (property_hash),
    INDEX idx_wp_post (wp_post_id),
    INDEX idx_status (status)
);
```

**Indexes**:
- `property_id` - UNIQUE, primary lookup
- `property_hash` - Hash comparison
- `wp_post_id` - Reverse lookup (WordPress → XML)
- `status` - Filter active/inactive

**Usage**:
- **Writers**: WP_Importer_API (upsert on import)
- **Readers**: WP_Importer_API (duplicate check), Admin (statistics)

---

### 6.3 WordPress Tables Integration

**Custom Post Types**:
- `estate_property` - Proprietà immobiliari
- `estate_agency` - Agenzie immobiliari

**Postmeta Fields** (sample - 80+ totali):
```
# Property Meta
- property_title, property_description
- property_price, property_size
- property_rooms, property_bedrooms, property_bathrooms
- property_address, property_city, property_area
- property_zip, property_country
- property_latitude, property_longitude
- property_agent (link to agency WP ID)
- property_xml_agency_id (XML agency ID for linking)
- _test_import (flag for test data)

# Agency Meta
- agency_xml_id (XML agency ID - for property linking)
- agency_email, agency_phone, agency_mobile
- agency_address, agency_website
- agency_logo (attachment ID)
- _test_import
```

**Taxonomies**:
- `property_category` - Tipo immobile (casa, villa, appartamento, ...)
- `property_action_category` - Vendita/Affitto
- `property_amenities` - Caratteristiche (piscina, giardino, ...)
- `property_features` - Dettagli (balcone, terrazza, ...)
- `property_city`, `property_area`, `property_county` - Location

---

## 7. SECURITY ARCHITECTURE

### 7.1 Authentication & Authorization

**AJAX Endpoints**:
```php
// Nonce verification
check_ajax_referer('realestate_sync_nonce', 'nonce');

// Capability check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}
```

**Server Cron**:
```php
// Token-based authentication
if ($_GET['token'] !== 'TrentinoImmo2025Secret!') {
    http_response_code(403);
    die('Forbidden');
}

// TODO: Move to environment variable
$valid_token = getenv('REALESTATE_SYNC_CRON_TOKEN');
```

---

### 7.2 Input Validation

**XML URL Validation**:
```php
// Validate URL format
if (!filter_var($xml_url, FILTER_VALIDATE_URL)) {
    throw new Exception('Invalid XML URL');
}
```

**Property/Agency ID Validation**:
```php
// Sanitize IDs before queue insertion
$property_id = sanitize_text_field($property_id);
$agency_id = sanitize_text_field($agency_id);
```

---

### 7.3 SQL Injection Prevention

**All queries use wpdb->prepare()**:
```php
// Queue Manager
$wpdb->prepare(
    "SELECT * FROM {$this->table} WHERE session_id = %s AND status = %s LIMIT %d",
    $session_id,
    'pending',
    $limit
);

// Tracking Manager
$wpdb->prepare(
    "INSERT INTO {$this->table} (property_id, wp_post_id, property_hash) VALUES (%s, %d, %s)",
    $property_id,
    $wp_post_id,
    $hash
);
```

---

### 7.4 File Security

**Upload Directory**:
- XML files stored in `/tmp/` (auto-cleanup)
- No direct web access
- Cleanup after import

**Logo Downloads**:
- Validated URLs only
- WordPress `wp_upload_bits()` (secure upload)
- Proper mime-type checking

---

## 8. PERFORMANCE & SCALABILITY

### 8.1 Memory Management

**Streaming Parser**:
```php
// XMLReader streaming (no full XML load for properties)
$reader = new XMLReader();
$reader->open($xml_file);

while ($reader->read()) {
    if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'annuncio') {
        // Process one at a time
        $property = simplexml_load_string($reader->readOuterXml());
        process_property($property);
        unset($property); // Free memory
    }
}
```

**Memory Limits**:
- Warning at 256MB usage
- Process max 10 items per batch
- Cleanup after each item

---

### 8.2 Batch Processing

**Configuration**:
- Batch size: 10 items
- Timeout: 50 seconds/batch
- Sleep between batches: 1 second (configurable)

**Benefits**:
- Prevents timeouts (10 items << 28,625 items)
- Allows progress tracking
- Automatic continuation via cron
- Easy to resume on crash

**Calculation**:
```
Total items: 811 (30 agencies + 781 properties)
Batch size: 10
Batches needed: 82 batches
Cron interval: 1 minute
Total time: ~82 minutes (1.4 hours)

With property images (slower):
~2-3 hours total
```

---

### 8.3 Database Optimization

**Indexes**:
- All foreign keys indexed
- Composite indexes for common queries
- UNIQUE constraints where applicable

**Query Optimization**:
- Limit results (pagination)
- Select only needed columns
- Use prepared statements (caching)

---

### 8.4 Caching Strategy

**Agency Cache**:
```php
// Agency_Manager - Session cache
private $agency_cache = array();

public function find_agency_by_xml_id($xml_id) {
    $cache_key = md5($xml_id);
    if (isset($this->agency_cache[$cache_key])) {
        return $this->agency_cache[$cache_key]; // Hit
    }

    // Query DB
    $agency_id = $this->query_database($xml_id);
    $this->agency_cache[$cache_key] = $agency_id; // Store

    return $agency_id;
}
```

**Transient Cache**:
```php
// Batch continuation (5 minute TTL)
set_transient('realestate_sync_pending_batch', $session_id, 300);
```

---

## 9. EXTENSION POINTS

### 9.1 Hooks & Filters (Future)

**Potential WordPress Hooks**:
```php
// Before import
do_action('realestate_sync_before_import', $xml_file, $session_id);

// After property created
do_action('realestate_sync_property_created', $property_id, $wp_post_id);

// Import complete
do_action('realestate_sync_import_complete', $session_id, $stats);

// Property mapping filter
$mapped_data = apply_filters('realestate_sync_map_property', $mapped_data, $xml_data);
```

---

### 9.2 Plugin Integrations

**Current**:
- Rapid AddOn plugin (addon-integration/)

**Future**:
- Email notifications (send report on completion)
- Slack/Discord webhooks (progress notifications)
- Google Analytics (track import events)
- Backup plugins (auto-backup before import)

---

### 9.3 API Endpoints (Future)

**REST API** (potential):
```php
// Trigger import
POST /wp-json/realestate-sync/v1/import
Body: { xml_url, mark_as_test }

// Get import status
GET /wp-json/realestate-sync/v1/status/:session_id

// Get statistics
GET /wp-json/realestate-sync/v1/stats
```

---

## 10. DEPLOYMENT ARCHITECTURE

### 10.1 Production Environment

```
┌─────────────────────────────────────────────┐
│         WordPress Server (LAMP)              │
│                                              │
│  ┌────────────────────────────────────────┐ │
│  │  Apache/Nginx (Web Server)              │ │
│  │  - PHP 7.4+                             │ │
│  │  - WordPress 5.8+                       │ │
│  │  - WPResidence Theme                    │ │
│  │  - RealEstate Sync Plugin               │ │
│  └────────────────────────────────────────┘ │
│                    ↓                         │
│  ┌────────────────────────────────────────┐ │
│  │  MySQL Database                         │ │
│  │  - wp_posts (properties, agencies)      │ │
│  │  - wp_postmeta (fields)                 │ │
│  │  - wp_realestate_import_queue           │ │
│  │  - wp_realestate_sync_tracking          │ │
│  └────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
           ↑                    ↑
           │                    │
    ┌──────┴──────┐      ┌──────┴──────┐
    │  FTP Upload │      │ Server Cron  │
    │  (manual)   │      │ (every min)  │
    └─────────────┘      └──────────────┘
           ↑                    ↑
           │                    │
    ┌──────┴──────────────────┴──────┐
    │  gestionaleimmobiliare.it      │
    │  XML Export Source             │
    │  - export_gi_full_merge...xml  │
    │  - Agency logos                │
    └────────────────────────────────┘
```

### 10.2 Server Requirements

**Minimum**:
- PHP 7.4+ (PHP 8.0+ recommended)
- MySQL 5.7+ (MySQL 8.0+ recommended)
- Memory: 512MB (1GB recommended)
- Disk: 2GB free space
- Cron capability (for automatic continuation)

**PHP Extensions**:
- SimpleXML
- XMLReader
- cURL
- GD or Imagick (image processing)
- JSON
- mbstring

**WordPress**:
- Version 5.8+
- WPResidence Theme 3.x
- Permalink structure enabled

---

**Data Creazione**: 03-Dic-2025
**Versione Plugin**: 1.5.0 (Batch System)
**Autore**: Claude Code Analysis
