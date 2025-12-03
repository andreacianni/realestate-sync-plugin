# DATABASE SCHEMA - RealEstate Sync Plugin

**Data Creazione**: 03 Dicembre 2025
**Ora**: 06:14:20
**Versione Plugin**: 1.5.0 (Batch System)

Documentazione completa dello schema database utilizzato dal plugin.

**Scopo**: Documentare TUTTE le tabelle, colonne, indici, relazioni e query utilizzate dal sistema.

---

## 📋 INDICE

1. [Overview Database](#1-overview-database)
2. [Tabelle Custom](#2-tabelle-custom)
3. [WordPress Tables Usage](#3-wordpress-tables-usage)
4. [Relazioni e Foreign Keys](#4-relazioni-e-foreign-keys)
5. [Indici e Performance](#5-indici-e-performance)
6. [Query Patterns](#6-query-patterns)
7. [Data Migration](#7-data-migration)
8. [Maintenance Operations](#8-maintenance-operations)

---

## 1. OVERVIEW DATABASE

### 1.1 Database Utilizzato

**Engine**: MySQL 5.7+ / MariaDB 10.3+
**Charset**: utf8mb4
**Collation**: utf8mb4_unicode_ci

**Database Name**: Configurato in `wp-config.php`
**Table Prefix**: `{$wpdb->prefix}` (default: `wp_`)

---

### 1.2 Tabelle Utilizzate

**Custom Tables (2)**:
1. `{prefix}realestate_import_queue` - Batch processing queue
2. `{prefix}realestate_sync_tracking` - Import tracking & duplicate detection

**WordPress Core Tables (5)**:
3. `{prefix}posts` - Properties (`estate_property`) & Agencies (`estate_agency`) Custom Post Types
4. `{prefix}postmeta` - Property/agency metadata
5. `{prefix}term_taxonomy` + `{prefix}terms` - Categories, amenities
6. `{prefix}term_relationships` - Post → taxonomy relationships
7. `{prefix}options` - Plugin settings

---

## 2. TABELLE CUSTOM

### 2.1 Queue Table

**Nome**: `{prefix}realestate_import_queue`

#### Schema DDL

```sql
CREATE TABLE IF NOT EXISTS `wp_realestate_import_queue` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(100) NOT NULL COMMENT 'Unique import session identifier (e.g., import_692f6243d4d257)',
    `item_type` VARCHAR(20) NOT NULL COMMENT 'Type of item: agency or property',
    `item_id` VARCHAR(100) NOT NULL COMMENT 'Agency ID or Property ID from XML',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, completed, or failed',
    `retry_count` INT(11) NOT NULL DEFAULT 0 COMMENT 'Number of retry attempts (max 3)',
    `error_message` TEXT DEFAULT NULL COMMENT 'Error details if status=failed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When item was added to queue',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last status change',

    PRIMARY KEY (`id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_status` (`status`),
    KEY `idx_type` (`item_type`),
    KEY `idx_session_status` (`session_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Batch processing queue for import items';
```

#### Colonne Dettagliate

| Colonna | Tipo | Default | Null | Descrizione |
|---------|------|---------|------|-------------|
| `id` | BIGINT(20) UNSIGNED | AUTO_INCREMENT | NO | Primary key, sequential ID |
| `session_id` | VARCHAR(100) | - | NO | Formato: `import_{uniqid}` (es: `import_692f6243d4d257.37604049`) |
| `item_type` | VARCHAR(20) | - | NO | Valori: `'agency'`, `'property'` |
| `item_id` | VARCHAR(100) | - | NO | ID dall'XML (es: `'5631'` per agency, `'P123456'` per property) |
| `status` | VARCHAR(20) | `'pending'` | NO | Stati: `'pending'`, `'processing'`, `'completed'`, `'failed'` |
| `retry_count` | INT(11) | `0` | NO | Incrementato ad ogni errore, max 3 retry |
| `error_message` | TEXT | NULL | YES | Messaggio di errore se `status='failed'` |
| `created_at` | TIMESTAMP | CURRENT_TIMESTAMP | NO | Timestamp creazione item |
| `updated_at` | TIMESTAMP | CURRENT_TIMESTAMP ON UPDATE | NO | Auto-update su ogni modifica |

#### Indici

| Nome | Tipo | Colonne | Scopo |
|------|------|---------|-------|
| PRIMARY | PRIMARY KEY | `id` | Identificazione univoca |
| `idx_session` | INDEX | `session_id` | Filter by session |
| `idx_status` | INDEX | `status` | Find pending/failed |
| `idx_type` | INDEX | `item_type` | Filter agencies/properties |
| `idx_session_status` | INDEX | `session_id, status` | **Main query** (get pending by session) |

#### Stati Possibili

```
pending     → Item waiting to be processed
              ↓
processing  → Item currently being processed (locked)
              ↓
              ├─→ completed (success)
              └─→ failed (error, retry_count >= 3)
                  └─→ pending (error, retry_count < 3 - will retry)
```

#### Lifecycle Example

```sql
-- 1. Creation (Batch_Orchestrator)
INSERT INTO queue (session_id, item_type, item_id, status)
VALUES ('import_692f...', 'agency', '5631', 'pending');

-- 2. Lock for processing (Batch_Processor)
UPDATE queue
SET status = 'processing', updated_at = NOW()
WHERE id = 1;

-- 3a. Success (Batch_Processor)
UPDATE queue
SET status = 'completed', updated_at = NOW()
WHERE id = 1;

-- 3b. Error - retry (Batch_Processor)
UPDATE queue
SET retry_count = retry_count + 1,
    status = CASE WHEN retry_count + 1 < 3 THEN 'pending' ELSE 'failed' END,
    error_message = 'Agency not found in XML',
    updated_at = NOW()
WHERE id = 1;

-- 4. Cleanup (after import complete)
DELETE FROM queue
WHERE session_id = 'import_692f...';
```

#### Statistiche Query

```sql
-- Get session statistics
SELECT
    item_type,
    status,
    COUNT(*) as count
FROM wp_realestate_import_queue
WHERE session_id = 'import_692f6243d4d257.37604049'
GROUP BY item_type, status
ORDER BY item_type, status;

-- Result example:
-- item_type | status      | count
-- ----------|-------------|-------
-- agency    | completed   | 30
-- property  | pending     | 639
-- property  | processing  | 1
-- property  | completed   | 141
```

#### Implementazione

**File**: `includes/class-realestate-sync-queue-manager.php`

**Metodi**:
- `create_table()` - Creates table on plugin activation
- `add_agency()` - INSERT agency item
- `add_property()` - INSERT property item
- `get_next_batch()` - SELECT pending items
- `mark_processing()` - UPDATE to processing
- `mark_done()` - UPDATE to completed
- `mark_error()` - UPDATE with retry logic
- `is_session_complete()` - COUNT pending/processing
- `get_session_stats()` - GROUP BY stats

---

### 2.2 Tracking Table

**Nome**: `{prefix}realestate_sync_tracking`

#### Schema DDL

```sql
CREATE TABLE IF NOT EXISTS `wp_realestate_sync_tracking` (
    `id` MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT,
    `property_id` VARCHAR(50) NOT NULL COMMENT 'Property ID from XML (unique)',
    `property_hash` VARCHAR(32) NOT NULL COMMENT 'MD5 hash of property data for change detection',
    `wp_post_id` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'WordPress post ID (NULL if not created yet)',
    `last_import_date` DATETIME DEFAULT NULL COMMENT 'Last import timestamp',
    `import_count` INT(11) NOT NULL DEFAULT 0 COMMENT 'Number of times imported',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, inactive, deleted, error',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'First import date',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update',

    PRIMARY KEY (`id`),
    UNIQUE KEY `property_id` (`property_id`),
    KEY `idx_hash` (`property_hash`),
    KEY `idx_wp_post` (`wp_post_id`),
    KEY `idx_status` (`status`),
    KEY `idx_import_date` (`last_import_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Import tracking and duplicate detection';
```

#### Colonne Dettagliate

| Colonna | Tipo | Default | Null | Descrizione |
|---------|------|---------|------|-------------|
| `id` | MEDIUMINT(9) UNSIGNED | AUTO_INCREMENT | NO | Primary key |
| `property_id` | VARCHAR(50) | - | NO | **UNIQUE** - ID dall'XML (es: `'P123456'`) |
| `property_hash` | VARCHAR(32) | - | NO | MD5 hash dei campi principali |
| `wp_post_id` | BIGINT(20) UNSIGNED | NULL | YES | WordPress post ID (foreign key virtuale) |
| `last_import_date` | DATETIME | NULL | YES | Ultimo import timestamp |
| `import_count` | INT(11) | `0` | NO | Contatore import (per statistiche) |
| `status` | VARCHAR(20) | `'active'` | NO | Stati: `'active'`, `'inactive'`, `'deleted'`, `'error'` |
| `created_at` | DATETIME | CURRENT_TIMESTAMP | NO | Prima import |
| `updated_at` | DATETIME | CURRENT_TIMESTAMP ON UPDATE | NO | Ultimo aggiornamento |

#### Indici

| Nome | Tipo | Colonne | Scopo |
|------|------|---------|-------|
| PRIMARY | PRIMARY KEY | `id` | Identificazione univoca |
| `property_id` | UNIQUE KEY | `property_id` | **Main lookup** (duplicate check) |
| `idx_hash` | INDEX | `property_hash` | Hash comparison |
| `idx_wp_post` | INDEX | `wp_post_id` | Reverse lookup (WP → XML) |
| `idx_status` | INDEX | `status` | Filter by status |
| `idx_import_date` | INDEX | `last_import_date` | Sort by date, cleanup old |

#### Hash Generation Logic

```php
// File: includes/class-realestate-sync-wp-importer-api.php
private function generate_property_hash($mapped_data) {
    // Include only fields that affect property identity
    $hash_data = [
        'title' => $mapped_data['property_title'],
        'description' => $mapped_data['property_description'],
        'price' => $mapped_data['property_price'],
        'size' => $mapped_data['property_size'],
        'rooms' => $mapped_data['property_rooms'],
        'address' => $mapped_data['property_address'],
        'category' => $mapped_data['property_category'],
        'features' => $mapped_data['property_features']
        // Exclude: images, meta, timestamps
    ];

    return md5(serialize($hash_data));
}
```

**Note**: Images NON fanno parte dell'hash perché cambiano frequentemente (URL updates, re-uploads) senza modificare il contenuto della proprietà.

#### Duplicate Detection Flow

```sql
-- 1. Check if property exists and has changed
SELECT property_id, property_hash, wp_post_id
FROM wp_realestate_sync_tracking
WHERE property_id = 'P123456';

-- Case 1: NOT FOUND → action='insert'
-- Case 2: FOUND, hash DIFFERENT → action='update'
-- Case 3: FOUND, hash IDENTICAL → action='skip'

-- 2. On import success, upsert tracking
INSERT INTO wp_realestate_sync_tracking
    (property_id, property_hash, wp_post_id, last_import_date, import_count, status)
VALUES
    ('P123456', 'abc123...', 12345, NOW(), 1, 'active')
ON DUPLICATE KEY UPDATE
    property_hash = VALUES(property_hash),
    wp_post_id = VALUES(wp_post_id),
    last_import_date = NOW(),
    import_count = import_count + 1,
    status = 'active',
    updated_at = NOW();
```

#### Tracking Orphans

```sql
-- Find tracking records without corresponding WordPress post
SELECT t.*
FROM wp_realestate_sync_tracking t
LEFT JOIN wp_posts p ON t.wp_post_id = p.ID
WHERE t.wp_post_id IS NOT NULL
  AND p.ID IS NULL;

-- Result: "Orphans" (tracking exists but post deleted)

-- Cleanup orphans
DELETE FROM wp_realestate_sync_tracking
WHERE wp_post_id NOT IN (
    SELECT ID FROM wp_posts WHERE post_type = 'estate_property'
);
```

#### Implementazione

**File**: `includes/class-realestate-sync-tracking-manager.php`

**Metodi**:
- `create_table()` - Creates table on activation
- `check_property_changes($property_id, $new_hash)` - Duplicate detection
- `update_tracking($property_id, $wp_post_id, $hash)` - Upsert record
- `get_tracking_record($property_id)` - SELECT by property_id
- `get_import_statistics()` - Aggregate stats

---

## 3. WORDPRESS TABLES USAGE

### 3.1 Posts Table

**Nome**: `{prefix}posts`

**Post Types Utilizzati**:
- `estate_property` - Proprietà immobiliari
- `estate_agency` - Agenzie immobiliari

#### Query Pattern: Insert Property

```sql
-- Via WPResidence REST API:
-- POST /wp-json/wp/v2/estate_property

-- Equivalent direct SQL:
INSERT INTO wp_posts (
    post_author,
    post_date,
    post_date_gmt,
    post_content,
    post_title,
    post_excerpt,
    post_status,
    post_type,
    post_name
) VALUES (
    1,                           -- Admin user
    NOW(),
    UTC_TIMESTAMP(),
    'Property description...',
    'Villa con piscina in vendita a Trento',
    '',
    'publish',
    'estate_property',
    'villa-con-piscina-trento'
);

-- Returns: $post_id (e.g., 12345)
```

#### Query Pattern: Insert Agency

```sql
-- Via WPResidence REST API:
-- POST /wp-json/wp/v2/estate_agent

-- Equivalent direct SQL:
INSERT INTO wp_posts (
    post_author,
    post_date,
    post_date_gmt,
    post_content,
    post_title,
    post_status,
    post_type,
    post_name
) VALUES (
    1,
    NOW(),
    UTC_TIMESTAMP(),
    '',  -- Agencies usually have empty content
    'Abilio S.p.A.',
    'publish',
    'estate_agency',
    'abilio-spa'
);
```

#### Index Usage

| Index | Columns | Purpose |
|-------|---------|---------|
| PRIMARY | `ID` | Unique post identifier |
| `type_status_date` | `post_type, post_status, post_date, ID` | Filter published properties |
| `post_name` | `post_name` | Slug lookup |
| `post_author` | `post_author` | Filter by author |

---

### 3.2 Postmeta Table

**Nome**: `{prefix}postmeta`

**Property Meta Fields** (80+ totali):

#### Core Fields

| Meta Key | Type | Example | Description |
|----------|------|---------|-------------|
| `property_title` | VARCHAR | "Villa con piscina" | Title (stored anche in post_title) |
| `property_description` | TEXT | "Bellissima villa..." | Description (stored anche in post_content) |
| `property_price` | DECIMAL | "450000" | Price in euros |
| `property_size` | INT | "250" | Size in m² |
| `property_rooms` | INT | "5" | Total rooms |
| `property_bedrooms` | INT | "3" | Bedrooms |
| `property_bathrooms` | INT | "2" | Bathrooms |

#### Location Fields

| Meta Key | Type | Example | Description |
|----------|------|---------|-------------|
| `property_address` | VARCHAR | "Via Roma, 12" | Street address |
| `property_city` | VARCHAR | "Trento" | City name |
| `property_area` | VARCHAR | "Centro Storico" | Area/neighborhood |
| `property_zip` | VARCHAR | "38100" | ZIP code |
| `property_country` | VARCHAR | "Italia" | Country |
| `property_latitude` | FLOAT | "46.0664" | GPS latitude |
| `property_longitude` | FLOAT | "11.1257" | GPS longitude |
| `property_comune_istat` | VARCHAR | "022205" | ISTAT code (TN/BZ filtering) |

#### Advanced Fields

| Meta Key | Type | Example | Description |
|----------|------|---------|-------------|
| `property_agent` | BIGINT | "14235" | Agency WordPress ID (foreign key) |
| `property_xml_agency_id` | VARCHAR | "5631" | Agency XML ID (for linking) |
| `property_energy_class` | VARCHAR | "A2" | Energy class (A4-G) |
| `property_maintenance_status` | VARCHAR | "Buono" | Maintenance status |
| `property_position_type` | VARCHAR | "Centrale" | Position type |
| `_test_import` | BOOLEAN | "1" | Test import flag |

#### Gallery Fields

| Meta Key | Type | Example | Description |
|----------|------|---------|-------------|
| `_thumbnail_id` | BIGINT | "67890" | Featured image attachment ID |
| `property_images` | SERIALIZED | `a:5:{i:0;i:123;...}` | Gallery attachment IDs (array) |
| `wpresidence_images` | SERIALIZED | `a:5:{...}` | WPResidence gallery format |
| `_gallery` | VARCHAR | "123,456,789" | Comma-separated IDs |

#### Agency Meta Fields

| Meta Key | Type | Example | Description |
|----------|------|---------|-------------|
| `agency_xml_id` | VARCHAR | "5631" | **CRITICAL** - XML ID for property linking |
| `agency_email` | VARCHAR | "info@abilio.it" | Contact email |
| `agency_phone` | VARCHAR | "+39 0461 123456" | Phone number |
| `agency_mobile` | VARCHAR | "+39 335 1234567" | Mobile number |
| `agency_address` | TEXT | "Via Roma 12, Trento" | Office address |
| `agency_website` | VARCHAR | "https://abilio.it" | Website URL |
| `agency_logo` | BIGINT | "45678" | Logo attachment ID |
| `_test_import` | BOOLEAN | "1" | Test import flag |

#### Query Pattern: Property Meta

```sql
-- Insert all meta for property
-- Via wp_update_post_meta() in loop:

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
VALUES
    (12345, 'property_title', 'Villa con piscina'),
    (12345, 'property_description', 'Bellissima villa...'),
    (12345, 'property_price', '450000'),
    (12345, 'property_size', '250'),
    (12345, 'property_rooms', '5'),
    (12345, 'property_address', 'Via Roma, 12'),
    (12345, 'property_city', 'Trento'),
    (12345, 'property_agent', '14235'),
    (12345, 'property_xml_agency_id', '5631'),
    (12345, '_test_import', '1')
ON DUPLICATE KEY UPDATE
    meta_value = VALUES(meta_value);
```

#### Index Usage

| Index | Columns | Purpose |
|-------|---------|---------|
| PRIMARY | `meta_id` | Unique identifier |
| `post_id` | `post_id` | **Most common** - get all meta for post |
| `meta_key` | `meta_key` | Find posts by meta key |
| `meta_value` | `meta_value(191)` | Search by value (limited) |

---

### 3.3 Taxonomies Tables

**Tables**: `{prefix}terms`, `{prefix}term_taxonomy`, `{prefix}term_relationships`

**Taxonomies Utilizzate**:

#### Property Taxonomies

| Taxonomy | Slug | Terms Count | Description |
|----------|------|-------------|-------------|
| `property_category` | `property_category` | 28 | Casa, Villa, Appartamento, ... |
| `property_action_category` | `property_action_category` | 2 | Vendita, Affitto |
| `property_amenities` | `property_amenities` | 33+ | Piscina, Giardino, Balcone, ... |
| `property_features` | `property_features` | 48+ | Terrazza, Ascensore, Cantina, ... |
| `property_city` | `property_city` | ~100 | Trento, Bolzano, Rovereto, ... |
| `property_area` | `property_area` | ~50 | Centro Storico, Periferia, ... |
| `property_county` | `property_county` | 2 | Trentino, Alto Adige |

#### Property Categories (28 types)

```sql
-- Sample categories
INSERT INTO wp_terms (name, slug) VALUES
    ('Casa singola', 'casa-singola'),
    ('Bifamiliare', 'bifamiliare'),
    ('Villa', 'villa'),
    ('Appartamento', 'appartamento'),
    ('Attico/Mansarda', 'attico-mansarda'),
    ('Rustico/Casale', 'rustico-casale'),
    ('Terreni', 'terreni'),
    ('Garage/Box auto', 'garage-box-auto');

-- Link terms to taxonomy
INSERT INTO wp_term_taxonomy (term_id, taxonomy, description) VALUES
    (1, 'property_category', ''),
    (2, 'property_category', ''),
    ...;

-- Link property to categories
INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES
    (12345, 3, 0);  -- Property 12345 → Villa
```

#### Query Pattern: Set Property Categories

```sql
-- Via wp_set_object_terms():
-- wp_set_object_terms(12345, ['villa', 'vendita'], 'property_category');

-- Equivalent SQL:
-- 1. Delete existing relationships
DELETE FROM wp_term_relationships
WHERE object_id = 12345
  AND term_taxonomy_id IN (
      SELECT term_taxonomy_id
      FROM wp_term_taxonomy
      WHERE taxonomy = 'property_category'
  );

-- 2. Insert new relationships
INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order)
SELECT 12345, tt.term_taxonomy_id, 0
FROM wp_term_taxonomy tt
JOIN wp_terms t ON tt.term_id = t.term_id
WHERE tt.taxonomy = 'property_category'
  AND t.slug IN ('villa', 'vendita');

-- 3. Update term counts
UPDATE wp_term_taxonomy
SET count = (
    SELECT COUNT(*) FROM wp_term_relationships
    WHERE term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id
)
WHERE taxonomy = 'property_category';
```

---

### 3.4 Options Table

**Nome**: `{prefix}options`

**Plugin Options Utilizzate**:

| Option Name | Type | Description |
|-------------|------|-------------|
| `realestate_sync_settings` | JSON | Plugin configuration |
| `realestate_sync_background_import_progress` | JSON | Current import progress |
| `realestate_sync_last_import` | TIMESTAMP | Last import date |
| `realestate_sync_last_success` | TIMESTAMP | Last successful import |
| `realestate_sync_total_properties` | INT | Total properties imported |
| `realestate_sync_use_api_importer` | BOOLEAN | Use API importer (golden) |
| `realestate_sync_skip_unchanged_mode` | BOOLEAN | Skip unchanged properties |
| `_transient_realestate_sync_pending_batch` | STRING | Current batch session_id (TTL: 5min) |
| `_transient_timeout_realestate_sync_pending_batch` | TIMESTAMP | Transient expiry time |

#### Settings JSON Structure

```json
{
    "xml_url": "https://www.gestionaleimmobiliare.it/export/xml/...",
    "username": "trentinoimmobiliare_it",
    "password": "dget6g52",
    "chunk_size": 25,
    "sleep_seconds": 1,
    "enabled_provinces": ["TN", "BZ"],
    "enable_automation": true,
    "schedule_time": "02:00",
    "mark_as_test": false
}
```

#### Background Import Progress Structure

```json
{
    "session_id": "import_692f6243d4d257.37604049",
    "xml_file_path": "/tmp/realestate_692f6243.xml",
    "mark_as_test": false,
    "start_time": 1701558227,
    "status": "processing",
    "total_items": 811,
    "processed_items": 145,
    "last_batch_time": 1701558350
}
```

---

## 4. RELAZIONI E FOREIGN KEYS

### 4.1 Entity Relationship Diagram

```
┌─────────────────────────┐
│  wp_posts (agencies)    │
│  post_type='estate_agency│
│  ┌──────────────────┐   │
│  │ ID (PK)          │   │
│  │ post_title       │   │
│  │ post_status      │   │
│  └──────────────────┘   │
└─────────────┬───────────┘
              │
              │ 1
              │
              │ N
              │
┌─────────────▼───────────┐
│  wp_postmeta            │
│  ┌──────────────────┐   │
│  │ meta_id (PK)     │   │
│  │ post_id (FK)     │───┼──→ references wp_posts.ID
│  │ meta_key         │   │
│  │ meta_value       │   │    (agency_xml_id = '5631')
│  └──────────────────┘   │
└─────────────┬───────────┘
              │
              │
              │ referenced by
              │ property_agent meta
              │
┌─────────────▼───────────┐
│  wp_posts (properties)  │
│  post_type='estate_property'
│  ┌──────────────────┐   │
│  │ ID (PK)          │   │
│  │ post_title       │   │
│  │ post_content     │   │
│  └──────────────────┘   │
└─────────────┬───────────┘
              │
              │ 1
              │
              │ N
              │
┌─────────────▼───────────┐
│  wp_postmeta            │
│  ┌──────────────────┐   │
│  │ meta_id (PK)     │   │
│  │ post_id (FK)     │───┼──→ references wp_posts.ID
│  │ meta_key         │   │
│  │ meta_value       │   │
│  │                  │   │    (property_agent = agency.ID)
│  │                  │   │    (property_xml_agency_id = '5631')
│  └──────────────────┘   │
└─────────────────────────┘

┌─────────────────────────┐
│  wp_realestate_sync_tracking
│  ┌──────────────────┐   │
│  │ id (PK)          │   │
│  │ property_id (UK) │   │    (XML property ID)
│  │ property_hash    │   │
│  │ wp_post_id (FK)  │───┼──→ references wp_posts.ID
│  └──────────────────┘   │    (property WordPress ID)
└─────────────────────────┘

┌─────────────────────────┐
│  wp_realestate_import_queue
│  ┌──────────────────┐   │
│  │ id (PK)          │   │
│  │ session_id       │   │
│  │ item_type        │   │    ('agency' or 'property')
│  │ item_id          │   │    (XML ID - no FK constraint)
│  │ status           │   │
│  └──────────────────┘   │
└─────────────────────────┘
```

### 4.2 Foreign Keys (Virtual)

**Note**: WordPress non usa FOREIGN KEY constraints fisiche, ma le relazioni sono mantenute via applicazione.

**Virtual Foreign Keys**:

1. **Property → Agency**:
   - `wp_postmeta.meta_value` (where `meta_key='property_agent'`)
   - → references `wp_posts.ID` (where `post_type='estate_agency'`)

2. **Property → Tracking**:
   - `wp_realestate_sync_tracking.wp_post_id`
   - → references `wp_posts.ID` (where `post_type='estate_property'`)

3. **Property → Gallery Images**:
   - `wp_postmeta.meta_value` (where `meta_key='_thumbnail_id'`)
   - → references `wp_posts.ID` (where `post_type='attachment'`)

---

## 5. INDICI E PERFORMANCE

### 5.1 Critical Indexes

**Queue Table**:
```sql
-- Most important: session + status lookup
ALTER TABLE wp_realestate_import_queue
ADD INDEX idx_session_status (session_id, status);

-- Query using this index:
SELECT * FROM queue
WHERE session_id = 'import_692f...' AND status = 'pending'
LIMIT 10;
```

**Tracking Table**:
```sql
-- Most important: property_id lookup (duplicate check)
ALTER TABLE wp_realestate_sync_tracking
ADD UNIQUE KEY property_id (property_id);

-- Query using this index:
SELECT * FROM tracking WHERE property_id = 'P123456';
```

**Postmeta Table** (WordPress core):
```sql
-- Property agent lookup
SELECT meta_value FROM wp_postmeta
WHERE post_id = 12345 AND meta_key = 'property_agent';
-- Uses: post_id index

-- Agency by XML ID lookup
SELECT post_id FROM wp_postmeta
WHERE meta_key = 'agency_xml_id' AND meta_value = '5631';
-- Uses: meta_key index (then scans meta_value)
```

### 5.2 Query Optimization Tips

**Use LIMIT sempre**:
```sql
-- BAD (scansione completa)
SELECT * FROM queue WHERE status = 'pending';

-- GOOD (limita risultati)
SELECT * FROM queue WHERE status = 'pending' LIMIT 10;
```

**Use Indexes correttamente**:
```sql
-- BAD (full table scan)
SELECT * FROM tracking WHERE property_hash LIKE '%abc%';

-- GOOD (uses property_id unique index)
SELECT * FROM tracking WHERE property_id = 'P123456';
```

**Evita SELECT \***:
```sql
-- BAD (fetch all columns)
SELECT * FROM queue WHERE session_id = 'import_692f...';

-- GOOD (fetch only needed)
SELECT id, item_type, item_id, status FROM queue
WHERE session_id = 'import_692f...';
```

---

## 6. QUERY PATTERNS

### 6.1 Batch Processing Queries

```sql
-- Get next batch of pending items
SELECT id, item_type, item_id, retry_count
FROM wp_realestate_import_queue
WHERE session_id = ?
  AND status = 'pending'
ORDER BY id ASC
LIMIT 10;

-- Mark item as processing
UPDATE wp_realestate_import_queue
SET status = 'processing', updated_at = NOW()
WHERE id = ?;

-- Mark item as completed
UPDATE wp_realestate_import_queue
SET status = 'completed', updated_at = NOW()
WHERE id = ?;

-- Mark item as failed (with retry)
UPDATE wp_realestate_import_queue
SET retry_count = retry_count + 1,
    status = CASE WHEN retry_count + 1 < 3 THEN 'pending' ELSE 'failed' END,
    error_message = ?,
    updated_at = NOW()
WHERE id = ?;

-- Check if session complete
SELECT COUNT(*) as pending_count
FROM wp_realestate_import_queue
WHERE session_id = ?
  AND status IN ('pending', 'processing');
-- Returns: 0 if complete, >0 if not

-- Get session statistics
SELECT item_type, status, COUNT(*) as count
FROM wp_realestate_import_queue
WHERE session_id = ?
GROUP BY item_type, status
ORDER BY item_type, status;
```

### 6.2 Tracking Queries

```sql
-- Check if property exists and has changed
SELECT property_id, property_hash, wp_post_id
FROM wp_realestate_sync_tracking
WHERE property_id = ?;

-- Upsert tracking record
INSERT INTO wp_realestate_sync_tracking
    (property_id, property_hash, wp_post_id, last_import_date, import_count, status)
VALUES (?, ?, ?, NOW(), 1, 'active')
ON DUPLICATE KEY UPDATE
    property_hash = VALUES(property_hash),
    wp_post_id = VALUES(wp_post_id),
    last_import_date = NOW(),
    import_count = import_count + 1,
    status = 'active',
    updated_at = NOW();

-- Get import statistics
SELECT
    COUNT(*) as total_tracked,
    COUNT(CASE WHEN wp_post_id IS NULL THEN 1 END) as orphans,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
    COUNT(CASE WHEN last_import_date > NOW() - INTERVAL 7 DAY THEN 1 END) as recent
FROM wp_realestate_sync_tracking;

-- Find orphaned tracking records
SELECT t.*
FROM wp_realestate_sync_tracking t
LEFT JOIN wp_posts p ON t.wp_post_id = p.ID
WHERE t.wp_post_id IS NOT NULL AND p.ID IS NULL;
```

### 6.3 Property Queries

```sql
-- Get property with agency
SELECT
    p.ID,
    p.post_title,
    pm_price.meta_value as price,
    pm_agent.meta_value as agent_id,
    a.post_title as agency_name
FROM wp_posts p
LEFT JOIN wp_postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = 'property_price'
LEFT JOIN wp_postmeta pm_agent ON p.ID = pm_agent.post_id AND pm_agent.meta_key = 'property_agent'
LEFT JOIN wp_posts a ON pm_agent.meta_value = a.ID
WHERE p.post_type = 'estate_property'
  AND p.post_status = 'publish'
ORDER BY p.post_date DESC
LIMIT 10;

-- Count properties by category
SELECT
    t.name as category,
    COUNT(*) as count
FROM wp_posts p
JOIN wp_term_relationships tr ON p.ID = tr.object_id
JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
JOIN wp_terms t ON tt.term_id = t.term_id
WHERE p.post_type = 'estate_property'
  AND p.post_status = 'publish'
  AND tt.taxonomy = 'property_category'
GROUP BY t.name
ORDER BY count DESC;

-- Count test imports
SELECT COUNT(*) as test_count
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'estate_property'
  AND pm.meta_key = '_test_import'
  AND pm.meta_value = '1';
```

---

## 7. DATA MIGRATION

### 7.1 Initial Plugin Activation

```sql
-- 1. Create queue table
CREATE TABLE IF NOT EXISTS wp_realestate_import_queue (...);

-- 2. Create tracking table
CREATE TABLE IF NOT EXISTS wp_realestate_sync_tracking (...);

-- 3. Set default options
INSERT INTO wp_options (option_name, option_value, autoload)
VALUES
    ('realestate_sync_settings', '{"enabled_provinces":["TN","BZ"]}', 'yes'),
    ('realestate_sync_use_api_importer', '1', 'yes')
ON DUPLICATE KEY UPDATE option_value = VALUES(option_value);

-- 4. Schedule cron
-- Via wp_schedule_event() - not SQL
```

### 7.2 Version Migrations

**Future migrations** (quando necessario):

```sql
-- Add new column to tracking
ALTER TABLE wp_realestate_sync_tracking
ADD COLUMN province VARCHAR(10) DEFAULT NULL AFTER status,
ADD INDEX idx_province (province);

-- Add new column to queue
ALTER TABLE wp_realestate_import_queue
ADD COLUMN priority INT DEFAULT 0 AFTER status,
ADD INDEX idx_priority (priority);

-- Update existing data
UPDATE wp_realestate_sync_tracking t
JOIN wp_postmeta pm ON t.wp_post_id = pm.post_id
SET t.province = pm.meta_value
WHERE pm.meta_key = 'property_comune_istat'
  AND SUBSTRING(pm.meta_value, 1, 3) IN ('021', '022');
```

---

## 8. MAINTENANCE OPERATIONS

### 8.1 Cleanup Old Sessions

```sql
-- Delete completed sessions older than 7 days
DELETE FROM wp_realestate_import_queue
WHERE status = 'completed'
  AND updated_at < NOW() - INTERVAL 7 DAY;

-- Archive failed items for review
CREATE TABLE IF NOT EXISTS wp_realestate_import_queue_archive
SELECT * FROM wp_realestate_import_queue
WHERE status = 'failed'
  AND updated_at < NOW() - INTERVAL 30 DAY;

DELETE FROM wp_realestate_import_queue
WHERE status = 'failed'
  AND updated_at < NOW() - INTERVAL 30 DAY;
```

### 8.2 Clean Orphaned Records

```sql
-- Remove tracking for deleted properties
DELETE FROM wp_realestate_sync_tracking
WHERE wp_post_id IS NOT NULL
  AND wp_post_id NOT IN (
      SELECT ID FROM wp_posts WHERE post_type = 'estate_property'
  );

-- Remove postmeta for deleted posts
DELETE pm FROM wp_postmeta pm
LEFT JOIN wp_posts p ON pm.post_id = p.ID
WHERE p.ID IS NULL;

-- Remove term relationships for deleted posts
DELETE tr FROM wp_term_relationships tr
LEFT JOIN wp_posts p ON tr.object_id = p.ID
WHERE p.ID IS NULL;
```

### 8.3 Optimize Tables

```sql
-- Rebuild indexes and reclaim space
OPTIMIZE TABLE wp_realestate_import_queue;
OPTIMIZE TABLE wp_realestate_sync_tracking;
OPTIMIZE TABLE wp_posts;
OPTIMIZE TABLE wp_postmeta;
OPTIMIZE TABLE wp_term_relationships;

-- Analyze tables for query optimizer
ANALYZE TABLE wp_realestate_import_queue;
ANALYZE TABLE wp_realestate_sync_tracking;
```

### 8.4 Backup Before Import

```bash
# mysqldump backup
mysqldump -u user -p database_name \
  wp_posts \
  wp_postmeta \
  wp_term_relationships \
  wp_realestate_import_queue \
  wp_realestate_sync_tracking \
  > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore if needed
mysql -u user -p database_name < backup_20251203_220000.sql
```

---

**Data Creazione**: 03-Dic-2025
**Versione Plugin**: 1.5.0 (Batch System)
**Autore**: Claude Code Analysis
