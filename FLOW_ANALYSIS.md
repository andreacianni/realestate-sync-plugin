# 🔍 ANALISI COMPLETA DEI FLUSSI DI IMPORT

**Data**: 2025-12-02
**Obiettivo**: Identificare TUTTI i flussi attivi, duplicati e conflitti prima di procedere con fix

---

## 📊 ENTRY POINTS IDENTIFICATI

### 1️⃣ AJAX: Manual Import (Pulsante "Scarica e Importa Ora")

**Trigger**: Click su pulsante dashboard
**AJAX Action**: `wp_ajax_realestate_sync_manual_import`

**⚠️ DUPLICATO! Registrato in DUE file:**

#### A. Admin Class (GIUSTO - usa Batch System)
- **File**: `admin/class-realestate-sync-admin.php:37`
- **Handler**: `handle_manual_import()` (riga 708)
- **Sistema**: ✅ **Batch Orchestrator**
- **Flusso**:
  ```
  handle_manual_import()
  → Download XML
  → Batch_Orchestrator::process_xml_batch()
    → Index & Filter (TN/BZ only)
    → Create Queue
    → Batch_Processor::process_next_batch()
      → Import_Engine::process_single_property()
    → Setup cron continuation
  ```

#### B. Main Plugin File (SBAGLIATO - usa vecchio sistema)
- **File**: `realestate-sync.php:106`
- **Handler**: `handle_manual_import()` (riga 535)
- **Sistema**: ❌ **execute_chunked_import() (VECCHIO)**
- **Flusso**:
  ```
  handle_manual_import()
  → Download XML
  → Import_Engine::execute_chunked_import()
    → Streaming Parser
    → handle_single_property() (con province filtering)
    → Processa TUTTE le proprietà nel file
  ```

**🚨 PROBLEMA**: Quale viene eseguito? Dipende dall'ordine di caricamento!
**RISULTATO**: Il secondo handler SOVRASCRIVE o viene eseguito DOPO il primo

---

### 2️⃣ WP CRON: Scheduled Daily Import

**Trigger**: WordPress cron (daily)
**Hook**: `realestate_sync_daily_import`

**Registrazione**:
- **File**: `realestate-sync.php:112`
- **Handler**: `run_scheduled_import()` (riga 647)
- **Sistema**: ❌ **execute_chunked_import() (VECCHIO)**

**Flusso**:
```
run_scheduled_import()
→ Download XML
→ Import_Engine::execute_chunked_import()
  → Streaming Parser
  → handle_single_property()
  → Processa TUTTE le proprietà
```

---

### 3️⃣ Server CRON: Batch Continuation

**Trigger**: Server cron (ogni minuto)
**File**: `batch-continuation.php`

**Flusso**:
```
batch-continuation.php
→ Check transient 'realestate_sync_pending_batch'
→ If found:
  → Batch_Processor::process_next_batch()
    → Process 10 items
    → Import_Engine::process_single_property()
  → Reset transient if not complete
```

**Sistema**: ✅ **Batch System**

---

### 4️⃣ AJAX: Test File Upload

**Trigger**: Upload file XML da dashboard
**AJAX Action**: `wp_ajax_realestate_sync_import_test_file`

**Registrazione**:
- **File**: `admin/class-realestate-sync-admin.php:52`
- **Handler**: `handle_import_test_file()` (riga ~1230)
- **Sistema**: ❌ **execute_chunked_import() (VECCHIO)**

**Flusso**:
```
handle_import_test_file()
→ Upload file to temp
→ Import_Engine::execute_chunked_import()
  → Streaming Parser
  → handle_single_property()
```

---

### 5️⃣ AJAX: Test Sample XML

**Trigger**: Test con sample XML
**AJAX Action**: `wp_ajax_realestate_sync_test_sample_xml`

**Registrazione**:
- **File**: `realestate-sync.php:109`
- **Handler**: `handle_test_sample_xml()` (riga 632)
- **Sistema**: ❌ **execute_chunked_import() (VECCHIO)**

---

## 🚨 DUPLICATI TROVATI

### AJAX Actions Duplicati:

| Action Name | File 1 | File 2 | Conflitto |
|-------------|--------|--------|-----------|
| `wp_ajax_realestate_sync_manual_import` | admin/class:37 | realestate-sync.php:106 | ❌ **CRITICO** |
| `wp_ajax_realestate_sync_clear_logs` | admin/class:43 | realestate-sync.php:108 | ⚠️ Minore |

---

## 🔄 SISTEMI DI IMPORT ATTIVI

### ✅ BATCH SYSTEM (Nuovo - Corretto)

**Componenti**:
```
Batch_Orchestrator
  ├─ Index & Filter XML (filtra PRIMA)
  ├─ Queue_Manager (crea coda in DB)
  └─ Batch_Processor
       └─ Import_Engine::process_single_property()
```

**Caratteristiche**:
- ✅ Filtra PRIMA (solo 781 properties TN/BZ)
- ✅ Usa queue nel database
- ✅ Processa in batch da 10 items
- ✅ Timeout 50 secondi per batch
- ✅ Continua via cron (batch-continuation.php)
- ✅ NON re-filtra durante processing

**Usato da**:
- ✅ Manual Import (Admin Class handler)
- ✅ Batch Continuation (server cron)

---

### ❌ CHUNKED IMPORT (Vecchio - Da Deprecare)

**Componenti**:
```
Import_Engine::execute_chunked_import()
  ├─ Streaming_Parser (processa TUTTO il file)
  └─ handle_single_property()
       └─ Province filtering (filtra DURANTE processing)
```

**Caratteristiche**:
- ❌ Processa TUTTE le proprietà (28,625)
- ❌ Filtra DURANTE processing (10,341 skipped)
- ❌ NO queue nel database
- ❌ Processing diretto (no batch)
- ❌ NO continuation automatica
- ❌ Crea session log file

**Usato da**:
- ❌ Manual Import (Main file handler - SBAGLIATO!)
- ❌ Scheduled Daily Import (cron)
- ❌ Test File Upload
- ❌ Test Sample XML

---

## 📝 LOGGING

### Batch System Logging:
```
[BATCH-ORCHESTRATOR] → debug.log
[BATCH-PROCESSOR] → debug.log
[BATCH-CONTINUATION] → debug.log
Import_Engine::process_single_property() → ???
```

### Chunked Import Logging:
```
Import_Engine::execute_chunked_import()
  → Creates session log file: import-{timestamp}-{session_id}.log
  → All STEP 1, STEP 2, etc. messages
```

**🚨 PROBLEMA**: Log sparsi in file diversi!

---

## 🎯 PROBLEMI IDENTIFICATI

### 1. CRITICO: Duplicated AJAX Handler
- `wp_ajax_realestate_sync_manual_import` registrato 2 volte
- Handler sbagliato (vecchio sistema) probabilmente eseguito
- **Causa**: Import del 2 dicembre ha usato vecchio sistema

### 2. ALTO: Sistema Vecchio Ancora Attivo
- 4 entry points usano ancora `execute_chunked_import()`
- Scheduled import usa vecchio sistema
- Test file usa vecchio sistema

### 3. MEDIO: Log Non Unificati
- Batch system logga in debug.log
- Chunked system crea file separati
- Difficile tracciare import completo

### 4. MEDIO: Configurazioni Hardcoded
- Credenziali hardcoded in 3+ posti
- Chunk size hardcoded
- Difficile cambiare configurazione

### 5. BASSO: Cleanup Non Chiaro
- Queue viene svuotata? Quando?
- Session files vengono cancellati? Quando?
- Transient management non documentato

---

## 📈 STATISTICHE

**Entry Points Totali**: 5
**AJAX Actions Totali**: 20+
**AJAX Duplicati**: 2

**Sistemi Attivi**: 2
- Batch System: 2 entry points (40%)
- Chunked Import: 4 entry points (60%)

**🚨 Il 60% degli import usa ancora il vecchio sistema!**

---

## ✅ PROSSIMI PASSI

1. **Fix Immediato**: Rimuovere duplicato AJAX manual_import
2. **Migrazione**: Convertire tutti gli entry point al Batch System
3. **Logging**: Unificare tutti i log in session file
4. **Cleanup**: Documentare e implementare cleanup automatico
5. **Testing**: Verificare ogni flusso dopo migrazione

---

**Fine Analisi**
