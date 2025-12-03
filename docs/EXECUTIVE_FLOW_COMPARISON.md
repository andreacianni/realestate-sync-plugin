# Confronto Flussi ESECUTIVI - Livello Metodi

**Data**: 1 Dicembre 2025, Analisi Profonda ☕

---

## 🔬 CHIAMATE METODI ESATTE

### BUTTON A: "Processa File XML"

```
1. handle_process_test_file()                          [admin class, line 1729]
   ├─> $_FILES['test_xml_file']                        [PHP upload]
   ├─> file_put_contents($temp_file, $xml_content)     [Save to temp]
   │
   ├─> new RealEstate_Sync_Batch_Processor($session_id, $temp_file)
   │   └─> __construct()                               [Batch Processor, line 68]
   │       ├─> new RealEstate_Sync_Queue_Manager()     [line 73]
   │       ├─> new RealEstate_Sync_Agency_Parser()     [line 79] ✅ PROTECTED
   │       ├─> new RealEstate_Sync_Agency_Manager()    [line 80] ✅ PROTECTED
   │       ├─> new RealEstate_Sync_Property_Mapper()   [line 81] ✅ PROTECTED
   │       └─> new RealEstate_Sync_WP_Importer_API()   [line 82] ✅ PROTECTED
   │
   ├─> scan_and_populate_queue($mark_as_test)          [Batch Processor, line 91]
   │   ├─> simplexml_load_file($xml_file_path)         [line 103]
   │   ├─> $this->agency_parser->extract_agencies_from_xml($xml) [line 116] ✅
   │   ├─> foreach agencies → add_agency()             [line 171]
   │   └─> foreach properties → add_property()         [line 176]
   │
   ├─> process_next_batch()                            [Batch Processor, line 203]
   │   ├─> $this->queue_manager->get_next_batch()      [line 211]
   │   └─> foreach items:
   │       ├─> process_agency($item)                   [line 241]
   │       │   ├─> simplexml_load_file()               [line 289]
   │       │   ├─> $this->agency_parser->extract_agencies_from_xml($xml) [line 292] ✅
   │       │   └─> $this->agency_manager->import_agencies($agencies, $mark_as_test) [line 314] ✅
   │       │
   │       └─> process_property($item)                 [line 243]
   │           ├─> simplexml_load_file()               [line 333]
   │           ├─> Parse XML to array                  [line 343-389]
   │           ├─> $this->property_mapper->map_property($property_data) [line 391] ✅
   │           └─> $this->wp_importer->process_property($mapped_data) [line 395] ✅
   │
   ├─> set_transient('realestate_sync_pending_batch', ...)  [line 1810]
   └─> wp_send_json_success()                          [line 1818]
```

---

### BUTTON B: "Scarica e Importa Ora"

```
1. handle_manual_import()                              [admin class, line 708]
   ├─> new RealEstate_Sync_XML_Downloader()            [line 733]
   │   └─> download_xml($url, $user, $pass)            [Downloads + extracts .tar.gz]
   │
   ├─> new RealEstate_Sync_Batch_Processor($session_id, $xml_file)
   │   └─> __construct()                               [Batch Processor, line 68]
   │       ├─> new RealEstate_Sync_Queue_Manager()     [line 73]
   │       ├─> new RealEstate_Sync_Agency_Parser()     [line 79] ✅ PROTECTED
   │       ├─> new RealEstate_Sync_Agency_Manager()    [line 80] ✅ PROTECTED
   │       ├─> new RealEstate_Sync_Property_Mapper()   [line 81] ✅ PROTECTED
   │       └─> new RealEstate_Sync_WP_Importer_API()   [line 82] ✅ PROTECTED
   │
   ├─> scan_and_populate_queue($mark_as_test)          [Batch Processor, line 91]
   │   ├─> simplexml_load_file($xml_file_path)         [line 103]
   │   ├─> $this->agency_parser->extract_agencies_from_xml($xml) [line 116] ✅
   │   ├─> foreach agencies → add_agency()             [line 171]
   │   └─> foreach properties → add_property()         [line 176]
   │
   ├─> process_next_batch()                            [Batch Processor, line 203]
   │   ├─> $this->queue_manager->get_next_batch()      [line 211]
   │   └─> foreach items:
   │       ├─> process_agency($item)                   [line 241]
   │       │   ├─> simplexml_load_file()               [line 289]
   │       │   ├─> $this->agency_parser->extract_agencies_from_xml($xml) [line 292] ✅
   │       │   └─> $this->agency_manager->import_agencies($agencies, $mark_as_test) [line 314] ✅
   │       │
   │       └─> process_property($item)                 [line 243]
   │           ├─> simplexml_load_file()               [line 333]
   │           ├─> Parse XML to array                  [line 343-389]
   │           ├─> $this->property_mapper->map_property($property_data) [line 391] ✅
   │           └─> $this->wp_importer->process_property($mapped_data) [line 395] ✅
   │
   ├─> set_transient('realestate_sync_pending_batch', ...)  [line 772]
   └─> wp_send_json_success()                          [line 777]
```

---

## ✅ RISPOSTA DEFINITIVA

### SÌ, I DUE FLUSSI CHIAMANO **ESATTAMENTE** GLI STESSI METODI NELLO STESSO ORDINE!

**Differenza UNICA**:
- ❌ Button A: `file_put_contents()` (upload)
- ❌ Button B: `XML_Downloader::download_xml()` (download)

**Identici dal punto 2 in poi**:
1. ✅ `new RealEstate_Sync_Batch_Processor()`
   - ✅ Istanzia TUTTE le protected classes (Parser, Manager, Mapper, Importer)

2. ✅ `scan_and_populate_queue()`
   - ✅ `simplexml_load_file()`
   - ✅ `agency_parser->extract_agencies_from_xml()`
   - ✅ `add_agency()` per ogni agenzia
   - ✅ `add_property()` per ogni proprietà

3. ✅ `process_next_batch()`
   - ✅ `queue_manager->get_next_batch()`
   - ✅ Per ogni agency:
     - `agency_parser->extract_agencies_from_xml()` (re-parse)
     - `agency_manager->import_agencies()`
   - ✅ Per ogni property:
     - Parse XML to array
     - `property_mapper->map_property()`
     - `wp_importer->process_property()`

4. ✅ `set_transient()`
5. ✅ `wp_send_json_success()`

---

## 🎯 METODI PROTECTED CHIAMATI (IDENTICI!)

### Agency Processing
```php
// SCAN
Agency_Parser::extract_agencies_from_xml($xml)         ✅ Line 116

// PROCESS
Agency_Parser::extract_agencies_from_xml($xml)         ✅ Line 292 (re-scan per singola agency)
Agency_Manager::import_agencies($agencies, $test)      ✅ Line 314
```

### Property Processing
```php
// PROCESS
Property_Mapper::map_property($property_data)          ✅ Line 391
WP_Importer_API::process_property($mapped_data)        ✅ Line 395
```

---

## 🔍 DIFFERENZE ESECUTIVE: ZERO!

| Metodo | Button A | Button B | Identico? |
|--------|----------|----------|-----------|
| `simplexml_load_file()` | ✅ | ✅ | SÌ |
| `agency_parser->extract_agencies_from_xml()` | ✅ | ✅ | SÌ |
| `queue_manager->add_agency()` | ✅ | ✅ | SÌ |
| `queue_manager->add_property()` | ✅ | ✅ | SÌ |
| `queue_manager->get_next_batch()` | ✅ | ✅ | SÌ |
| `agency_manager->import_agencies()` | ✅ | ✅ | SÌ |
| `property_mapper->map_property()` | ✅ | ✅ | SÌ |
| `wp_importer->process_property()` | ✅ | ✅ | SÌ |
| `set_transient()` | ✅ | ✅ | SÌ |

---

## 💡 IMPLICAZIONE CRITICA

**Se Button A non funziona** → Il problema è in:

1. ❌ **Batch_Processor class non caricata**
   ```php
   new RealEstate_Sync_Batch_Processor()  // Fails to instantiate
   ```

2. ❌ **Protected classes non caricate**
   ```php
   new RealEstate_Sync_Agency_Parser()    // Class not found
   new RealEstate_Sync_Agency_Manager()   // Class not found
   // etc...
   ```

3. ❌ **Queue Manager class non caricata**
   ```php
   new RealEstate_Sync_Queue_Manager()    // Class not found
   ```

4. ❌ **Fatal error in metodo protected**
   ```php
   $agency_parser->extract_agencies_from_xml()  // Crashes
   $agency_manager->import_agencies()           // Crashes
   ```

**NON può essere**:
- ✅ Admin class (codice identico)
- ✅ Cache PHP (tu l'hai disattivata)
- ✅ Upload file (problema diverso Button A vs B)

---

## 🔬 TEST DIAGNOSTICO DA FARE

**Script da eseguire sul server:**

```php
<?php
// diagnostic-batch-system.php

require_once('wp-load.php');

echo "=== BATCH SYSTEM DIAGNOSTIC ===\n\n";

// 1. Check classes exist
$classes = [
    'RealEstate_Sync_Batch_Processor',
    'RealEstate_Sync_Queue_Manager',
    'RealEstate_Sync_Agency_Parser',
    'RealEstate_Sync_Agency_Manager',
    'RealEstate_Sync_Property_Mapper',
    'RealEstate_Sync_WP_Importer_API'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ $class: EXISTS\n";
    } else {
        echo "❌ $class: NOT FOUND\n";
    }
}

// 2. Try instantiate Batch Processor
try {
    $bp = new RealEstate_Sync_Batch_Processor('test_123', '/tmp/test.xml');
    echo "\n✅ Batch Processor instantiated successfully\n";
} catch (Exception $e) {
    echo "\n❌ Batch Processor failed: " . $e->getMessage() . "\n";
}

// 3. Check queue table
global $wpdb;
$table = $wpdb->prefix . 'realestate_import_queue';
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if ($exists) {
    echo "✅ Queue table exists\n";
} else {
    echo "❌ Queue table NOT FOUND\n";
}

?>
```

Questo ti dirà ESATTAMENTE qual è il problema!

---

**Creato**: 1 Dicembre 2025, Analisi Profonda
