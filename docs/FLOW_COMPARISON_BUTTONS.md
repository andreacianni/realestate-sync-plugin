# Confronto Flussi: Button A vs Button B

**Data**: 1 Dicembre 2025, Mattina ☕

---

## 📊 TABELLA COMPARATIVA

| Step | **Button A: "Processa File XML"** | **Button B: "Scarica e Importa Ora"** | Identico? |
|------|-----------------------------------|---------------------------------------|-----------|
| **1. Ottenimento File** | | | |
| | Upload da filesystem locale | Download da GestionaleImmobiliare.it | ❌ NO |
| | → `$_FILES['test_xml_file']` | → `XML_Downloader::download_xml()` | |
| | Salva in temp file | Estrae .tar.gz → XML file | |
| **2. Session ID** | | | |
| | `'test_import_' . uniqid()` | `'import_' . uniqid()` | ⚠️ Diverso prefisso |
| **3. Error Log Markers** | | | |
| | `[REALESTATE-SYNC] BATCH TEST IMPORT` | `[REALESTATE-SYNC] STARTING BATCH IMPORT` | ⚠️ Diverso testo |
| **4. Progress Tracking** | | | |
| | `update_option('realestate_sync_background_import_progress', ...)` | `update_option('realestate_sync_background_import_progress', ...)` | ✅ SI |
| **5. Batch Processor Init** | | | |
| | `new RealEstate_Sync_Batch_Processor($session_id, $temp_file)` | `new RealEstate_Sync_Batch_Processor($session_id, $xml_file)` | ✅ SI |
| **6. Scan & Populate Queue** | | | |
| | `$batch_processor->scan_and_populate_queue($mark_as_test)` | `$batch_processor->scan_and_populate_queue($mark_as_test)` | ✅ SI |
| **7. Process First Batch** | | | |
| | `$batch_processor->process_next_batch()` | `$batch_processor->process_next_batch()` | ✅ SI |
| **8. Set Transient** | | | |
| | `set_transient('realestate_sync_pending_batch', $session_id, 300)` | `set_transient('realestate_sync_pending_batch', $session_id, 300)` | ✅ SI |
| **9. Response** | | | |
| | `wp_send_json_success(...)` | `wp_send_json_success(...)` | ✅ SI |

---

## ✅ CONCLUSIONE

### I DUE FLUSSI SONO IDENTICI dal punto 2 in poi!

**Differenze**:
- ❌ **Step 1**: Come ottengono il file XML (upload vs download)
- ⚠️ **Session ID prefix**: `test_import_` vs `import_` (irrilevante)
- ⚠️ **Log messages**: Testo diverso (irrilevante)

**Identici**:
- ✅ Batch Processor initialization
- ✅ scan_and_populate_queue()
- ✅ process_next_batch()
- ✅ Transient setting
- ✅ Response format

### 🎯 IMPLICAZIONE

**Se Button A (Processa File XML) NON funziona** → problema nel Batch Processor, non in handle_manual_import()

**Se Button B (Scarica e Importa Ora) NON funziona ma A SI** → problema nel download/extract XML

**Se ENTRAMBI non funzionano** → problema in:
1. Batch_Processor class not loaded
2. scan_and_populate_queue() fails
3. process_next_batch() fails

---

## 🔍 VERIFICA DA FARE

### Test Button A (Processa File XML)
```
1. Upload test-property-complete-fixed.xml
2. Check log per [REALESTATE-SYNC] BATCH TEST IMPORT
3. Check DB per queue table populated
4. Check risultati: 2 agencies + 3 properties
```

**Se Button A funziona** → Button B ha problema download/extract
**Se Button A NON funziona** → Batch Processor ha problema

---

## 📋 CODICE SORGENTE

### Button A - Line 1729-1824
```php
public function handle_process_test_file() {
    // 1. Upload file
    $uploaded_file = $_FILES['test_xml_file'];
    $temp_file = wp_upload_dir()['basedir'] . '/realestate-test-' . time() . '.xml';
    file_put_contents($temp_file, $xml_content);

    // 2-9. IDENTICO a Button B
    $session_id = 'test_import_' . uniqid('', true);
    $batch_processor = new RealEstate_Sync_Batch_Processor($session_id, $temp_file);
    $scan_result = $batch_processor->scan_and_populate_queue($mark_as_test);
    $first_batch_result = $batch_processor->process_next_batch();
    set_transient('realestate_sync_pending_batch', $session_id, 300);
    wp_send_json_success(...);
}
```

### Button B - Line 708-788
```php
public function handle_manual_import() {
    // 1. Download file
    $downloader = new RealEstate_Sync_XML_Downloader();
    $xml_file = $downloader->download_xml($settings['xml_url'], ...);

    // 2-9. IDENTICO a Button A
    $session_id = 'import_' . uniqid('', true);
    $batch_processor = new RealEstate_Sync_Batch_Processor($session_id, $xml_file);
    $scan_result = $batch_processor->scan_and_populate_queue($mark_as_test);
    $first_batch_result = $batch_processor->process_next_batch();
    set_transient('realestate_sync_pending_batch', $session_id, 300);
    wp_send_json_success(...);
}
```

---

**Creato**: 1 Dicembre 2025, Mattina con caffè ☕
