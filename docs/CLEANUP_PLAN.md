# PIANO DI CLEANUP - Codice Deprecato da Eliminare

**Data Creazione**: 03 Dicembre 2025
**Ora**: 06:07:51
**Versione Plugin**: 1.5.0 (Batch System)

Documento completo di tutto il codice vecchio/duplicato/inutilizzato da rimuovere dal plugin.

**Scopo**: Identificare esattamente cosa eliminare e perché, per mantenere il codebase pulito e maintainable.

---

## 📋 INDICE

1. [File Completi da Eliminare](#1-file-completi-da-eliminare)
2. [Metodi Duplicati/Commentati](#2-metodi-duplicaticommentati)
3. [AJAX Handlers Disabilitati](#3-ajax-handlers-disabilitati)
4. [Classi Deprecate](#4-classi-deprecate)
5. [File di Backup](#5-file-di-backup)
6. [Debug Code Temporaneo](#6-debug-code-temporaneo)
7. [TODO e FIXME Comments](#7-todo-e-fixme-comments)
8. [Riepilogo Azioni](#8-riepilogo-azioni)

---

## 1. FILE COMPLETI DA ELIMINARE

### 1.1 File di Backup (Sicuri da Eliminare)

| File | Path | Motivo | Azione |
|------|------|--------|--------|
| `dashboard-backup.php` | `admin/views/dashboard-backup.php` | Backup vecchia dashboard | ✅ ELIMINA |
| `dashboard-main-backup.php` | `admin/views/dashboard-main-backup.php` | Backup dashboard principale | ✅ ELIMINA |
| `property-mapper backup 18-8 18e50.php` | `includes/class-realestate-sync-property-mapper backup 18-8 18e50.php` | Backup Property Mapper | ✅ ELIMINA |
| `property-mapper-v1-backup.php` | `includes/class-realestate-sync-property-mapper-v1-backup.php` | Versione 1.0 Property Mapper | ✅ ELIMINA |
| `property-mapper-v3.1-backup.php` | `includes/class-realestate-sync-property-mapper-v3.1-backup.php` | Versione 3.1 Property Mapper | ✅ ELIMINA |
| `class-realestate-sync-admin.php.backup.19-08-2025-02h05` | `backups/class-realestate-sync-admin.php.backup.19-08-2025-02h05` | Backup Admin class | ✅ ELIMINA |

**Totale Files**: 6 files di backup non utilizzati

**Azione**:
```bash
# Elimina tutti i backup
rm admin/views/dashboard-backup.php
rm admin/views/dashboard-main-backup.php
rm includes/class-realestate-sync-property-mapper\ backup\ 18-8\ 18e50.php
rm includes/class-realestate-sync-property-mapper-v1-backup.php
rm includes/class-realestate-sync-property-mapper-v3.1-backup.php
rm -rf backups/
```

---

### 1.2 Classi Deprecate (Da Valutare)

| File | Classe | Status | Motivo | Azione |
|------|--------|--------|--------|--------|
| `class-realestate-sync-agency-importer.php` | `RealEstate_Sync_Agency_Importer` | 🚫 DEPRECATED | Replaced by Agency_Manager (API-based) | ⚠️ MARCA @deprecated |
| `class-realestate-sync-wp-importer.php` | `RealEstate_Sync_WP_Importer` | ⚠️ LEGACY | Replaced by WP_Importer_API (golden) | ⚠️ MARCA @deprecated |
| `class-realestate-sync-github-updater.php` | `RealEstate_Sync_GitHub_Updater` | ❌ DISABLED | Using external Git Updater plugin | ✅ ELIMINA |

**Dettagli**:

#### A. `class-realestate-sync-agency-importer.php`

**Motivo Deprecazione**:
- Usa metodi diretti invece di API
- Non scarica logo agenzia
- Sostituito da `Agency_Manager` che usa WPResidence API

**Utilizzato da**: NESSUNO (verificato)
- Batch system usa `Agency_Manager`
- Chunked import usa `Agency_Manager`

**Azione**:
1. Aggiungere `@deprecated` in class docblock
2. Aggiungere notice log quando viene istanziato
3. NON eliminare ancora (safety - mantenere 1-2 release)

**Codice da aggiungere**:
```php
/**
 * RealEstate Sync Agency Importer
 *
 * @deprecated 2.0.0 Use RealEstate_Sync_Agency_Manager instead
 * @see RealEstate_Sync_Agency_Manager
 *
 * This class is deprecated and will be removed in version 3.0.0
 * Use Agency_Manager which provides:
 * - API-based agency creation (proper WPResidence integration)
 * - Logo download functionality
 * - Better error handling
 */
class RealEstate_Sync_Agency_Importer {

    public function __construct() {
        // Log deprecation warning
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[DEPRECATION] RealEstate_Sync_Agency_Importer is deprecated. Use RealEstate_Sync_Agency_Manager instead.');
        }
    }
```

#### B. `class-realestate-sync-wp-importer.php`

**Motivo Deprecazione**:
- Usa metodi diretti meta fields
- Non usa WPResidence API
- 1700+ lines vs 300 lines (API version)
- Sostituito da `WP_Importer_API` (GOLDEN)

**Utilizzato da**:
- Import_Engine (configurabile - usa API se `use_api_importer=true`)
- Default: usa WP_Importer_API

**Azione**:
1. Aggiungere `@deprecated` in class docblock
2. Aggiungere notice log quando viene istanziato
3. Rimuovere in versione 3.0.0

**Codice da aggiungere**:
```php
/**
 * RealEstate Sync WP Importer (Legacy)
 *
 * @deprecated 2.0.0 Use RealEstate_Sync_WP_Importer_API instead
 * @see RealEstate_Sync_WP_Importer_API
 *
 * This class is deprecated and will be removed in version 3.0.0
 * Use WP_Importer_API which provides:
 * - WPResidence REST API integration
 * - 60% less code (300 vs 1700 lines)
 * - Better gallery handling (4 systems compatibility)
 * - Proper error handling
 */
class RealEstate_Sync_WP_Importer {

    public function __construct($logger) {
        $this->logger = $logger;

        // Log deprecation warning
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[DEPRECATION] RealEstate_Sync_WP_Importer is deprecated. Use RealEstate_Sync_WP_Importer_API instead.');
        }
    }
```

#### C. `class-realestate-sync-github-updater.php`

**Motivo Eliminazione**:
- Completamente disabilitato
- Usa plugin esterno "Git Updater"
- Non viene istanziato da nessuna parte

**Utilizzato da**: NESSUNO

**Azione**: ✅ **ELIMINA IMMEDIATAMENTE**

```bash
rm includes/class-realestate-sync-github-updater.php
```

---

## 2. METODI DUPLICATI/COMMENTATI

### 2.1 Metodi Commentati in `realestate-sync.php`

**File**: `realestate-sync.php`

#### Metodo: `handle_manual_import()` (Lines 534-593)

**Status**: ❌ COMMENTATO (2025-12-02)

**Codice Attuale**:
```php
Line 534-593:
/**
 * ❌ DISABLED 2025-12-02: Duplicate handler for manual import
 *
 * This handler was conflicting with the Admin class handler.
 * The Admin class version (admin/class-realestate-sync-admin.php:708)
 * uses the Batch Orchestrator system (correct approach).
 *
 * This version used execute_chunked_import() which:
 * - Processes ALL 28,625 properties instead of filtering first
 * - Skips 10,341+ properties during processing (wasteful)
 * - No queue system, no automatic continuation
 *
 * MIGRATION: All manual imports now use Admin class handler → Batch Orchestrator
 *
 * @deprecated Use admin/class-realestate-sync-admin.php::handle_manual_import() instead
 */
/*
public function handle_manual_import() {
    check_ajax_referer('realestate_sync_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    try {
        // ... 50+ lines of code ...
    } catch (Exception $e) {
        // ... error handling ...
    }
}
*/
```

**Azione**: ✅ **ELIMINA INTERO BLOCCO** (Lines 534-593)

Il commento con `@deprecated` è sufficiente nella history git. Non serve mantenere codice commentato.

---

#### Metodo: `clear_logs()` (Lines ~600-650)

**Status**: Possibilmente duplicato

**Azione**: ⚠️ **VERIFICA prima di eliminare**

Controllare se esiste in Admin class. Se sì, eliminare da realestate-sync.php.

---

### 2.2 Metodi Deprecati ma Ancora Attivi

**File**: `realestate-sync.php`

#### Metodo: `run_scheduled_import()` (Line 664)

**Status**: ⚠️ **USATO ma usa sistema VECCHIO**

**Problema**:
```php
Line 664: public function run_scheduled_import() {
    // ... download XML ...

    // ❌ BAD: Uses OLD chunked import
    $result = $this->instances['import_engine']->execute_chunked_import($xml_file);

    // Should use: Batch_Orchestrator::process_xml_batch()
}
```

**Azione**: 🔧 **MODIFICARE per usare Batch System**

**Codice Corretto**:
```php
public function run_scheduled_import() {
    error_log('[CRON] Starting scheduled import');

    try {
        // Get settings
        $settings = get_option('realestate_sync_settings', array());
        $xml_url = $settings['xml_url'];
        $username = $settings['username'];
        $password = $settings['password'];

        // Download XML
        $downloader = new RealEstate_Sync_XML_Downloader();
        $xml_file = $downloader->download_xml($xml_url, $username, $password);

        if (!$xml_file) {
            error_log('[CRON] Failed to download XML');
            return;
        }

        // ✅ NEW: Use Batch Orchestrator (same as manual import)
        $result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch($xml_file, false);

        if ($result['success']) {
            error_log('[CRON] Batch import started: ' . $result['total_queued'] . ' items queued');
        } else {
            error_log('[CRON] Batch import failed: ' . $result['error']);
        }

    } catch (Exception $e) {
        error_log('[CRON] Scheduled import failed: ' . $e->getMessage());
    }
}
```

---

## 3. AJAX HANDLERS DISABILITATI

### 3.1 Handlers Commentati in `realestate-sync.php`

**File**: `realestate-sync.php` (Lines 106-110)

**Codice Attuale**:
```php
Line 106-110:
// ❌ DISABLED 2025-12-02: Duplicate AJAX handler - using Admin class handler with Batch Orchestrator instead
// add_action('wp_ajax_realestate_sync_manual_import', [$this, 'handle_manual_import']);

// ❌ DISABLED 2025-12-02: Duplicate handler - using Admin class version
// add_action('wp_ajax_realestate_sync_clear_logs', [$this, 'clear_logs']);
```

**Azione**: ✅ **ELIMINA LINEE COMMENTATE** (Lines 106-110)

Motivo: I commenti sono nella git history. Non serve mantenerli nel codice.

**Sostituzione**:
```php
// AJAX handlers are registered in Admin class
// See: admin/class-realestate-sync-admin.php lines 37-72
```

---

## 4. CLASSI DEPRECATE

Vedi [Sezione 1.2](#12-classi-deprecate-da-valutare)

**Summary**:
- `RealEstate_Sync_Agency_Importer` → @deprecated, sostituito da Agency_Manager
- `RealEstate_Sync_WP_Importer` → @deprecated, sostituito da WP_Importer_API
- `RealEstate_Sync_GitHub_Updater` → ELIMINA (non usato)

---

## 5. FILE DI BACKUP

Vedi [Sezione 1.1](#11-file-di-backup-sicuri-da-eliminare)

**Summary**: 6 files di backup da eliminare immediatamente.

---

## 6. DEBUG CODE TEMPORANEO

### 6.1 Pagina Debug Database (TEMPORANEO)

**File**: `realestate-sync.php` (Line 440-448)

**Codice**:
```php
Line 440-448:
// 🔧 DEBUG: Pagina debug metafields (TEMPORANEO)
add_management_page(
    __('RealEstate Debug DB', 'realestate-sync'),
    __('Debug DB', 'realestate-sync'),
    'manage_options',
    'realestate-sync-debug-db',
    [$this->instances['admin'], 'display_debug_metafields_page']
);
```

**Azione**: ⚠️ **RIMUOVERE prima di PRODUZIONE**

Questa è una pagina debug utile per sviluppo, ma non dovrebbe essere in produzione.

**Opzione 1: Rimuovi completamente**
```php
// Debug page removed - use phpMyAdmin or WP CLI for DB inspection
```

**Opzione 2: Solo se WP_DEBUG**
```php
// Debug page (only available when WP_DEBUG is enabled)
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_management_page(
        __('RealEstate Debug DB', 'realestate-sync'),
        __('Debug DB', 'realestate-sync'),
        'manage_options',
        'realestate-sync-debug-db',
        [$this->instances['admin'], 'display_debug_metafields_page']
    );
}
```

---

### 6.2 Hardcoded Credentials (TEMPORANEO)

**File**: `admin/class-realestate-sync-admin.php` (Lines 716-721)

**Codice**:
```php
Line 716-721:
// 🔧 HARDCODE CREDENZIALI TEMPORANEO - BYPASS ADMIN INTERFACE
$settings = array(
    'xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
    'username' => 'trentinoimmobiliare_it',
    'password' => 'dget6g52'
);
```

**Azione**: 🔧 **MODIFICARE per usare settings da DB**

**Codice Corretto**:
```php
// Get settings from database
$settings = get_option('realestate_sync_settings', array());

if (empty($settings['xml_url']) || empty($settings['username']) || empty($settings['password'])) {
    wp_send_json_error('Configurazione mancante. Completa le impostazioni prima di avviare l\'import.');
    return;
}
```

---

### 6.3 Server Cron Token Hardcoded

**File**: `batch-continuation.php` (Line 17)

**Codice**:
```php
Line 17:
if ($_GET['token'] !== 'TrentinoImmo2025Secret!') {
    http_response_code(403);
    die('Forbidden');
}
```

**Problema**: Token hardcoded nel codice (security risk)

**Azione**: 🔧 **MODIFICARE per usare environment variable**

**Codice Corretto**:
```php
// Security: Verify token from environment variable
$valid_token = getenv('REALESTATE_SYNC_CRON_TOKEN') ?: 'TrentinoImmo2025Secret!';

if (!isset($_GET['token']) || $_GET['token'] !== $valid_token) {
    http_response_code(403);
    die('Forbidden');
}
```

**Setup**:
```bash
# In .htaccess o server config
SetEnv REALESTATE_SYNC_CRON_TOKEN "your-secure-random-token-here"
```

---

## 7. TODO E FIXME COMMENTS

### 7.1 TODO Comments Trovati

**Cerca nel codebase**:
```bash
grep -r "// TODO" includes/ admin/
```

**Azione**: Per ogni TODO trovato:
1. Se completato → RIMUOVI comment
2. Se ancora da fare → CREA GitHub Issue
3. Se non più necessario → RIMUOVI comment

---

### 7.2 FIXME Comments Trovati

**Cerca nel codebase**:
```bash
grep -r "// FIXME" includes/ admin/
```

**Azione**: Per ogni FIXME:
1. Fix immediatamente se critical
2. Altrimenti → GitHub Issue
3. Non lasciare FIXME nel codice senza azione

---

## 8. RIEPILOGO AZIONI

### 8.1 IMMEDIATE (Da Fare SUBITO)

| Azione | File/Codice | Motivo |
|--------|-------------|--------|
| ✅ **ELIMINA** | 6 file di backup (sezione 1.1) | Non utilizzati |
| ✅ **ELIMINA** | `class-realestate-sync-github-updater.php` | Disabled, non usato |
| ✅ **ELIMINA** | AJAX handlers commentati (lines 106-110) | Git history sufficiente |
| ✅ **ELIMINA** | Metodo commentato `handle_manual_import()` (lines 534-593) | Git history sufficiente |

**Commands**:
```bash
# Backup files
rm admin/views/dashboard-backup.php
rm admin/views/dashboard-main-backup.php
rm includes/class-realestate-sync-property-mapper\ backup\ 18-8\ 18e50.php
rm includes/class-realestate-sync-property-mapper-v1-backup.php
rm includes/class-realestate-sync-property-mapper-v3.1-backup.php
rm -rf backups/

# GitHub Updater
rm includes/class-realestate-sync-github-updater.php
```

---

### 8.2 SHORT TERM (Prossimo Sprint)

| Azione | File/Codice | Motivo |
|--------|-------------|--------|
| 🔧 **MODIFICA** | `run_scheduled_import()` (line 664) | Usa batch system invece chunked |
| 🔧 **MODIFICA** | Hardcoded credentials (lines 716-721) | Usa settings da DB |
| 🔧 **MODIFICA** | Server cron token (line 17) | Usa environment variable |
| ⚠️ **@DEPRECATED** | `RealEstate_Sync_Agency_Importer` | Marca deprecated |
| ⚠️ **@DEPRECATED** | `RealEstate_Sync_WP_Importer` | Marca deprecated |

---

### 8.3 LONG TERM (Versione 3.0.0)

| Azione | File/Codice | Motivo |
|--------|-------------|--------|
| ❌ **ELIMINA** | `class-realestate-sync-agency-importer.php` | Dopo 2+ release con @deprecated |
| ❌ **ELIMINA** | `class-realestate-sync-wp-importer.php` | Dopo 2+ release con @deprecated |
| ❌ **ELIMINA** | Debug DB page (se non usata) | Non necessaria in produzione |

---

## 9. CHECKLIST CLEANUP

### Pre-Cleanup

- [ ] **Git commit** - Commit tutto il lavoro corrente
- [ ] **Git branch** - Crea branch `cleanup/deprecated-code`
- [ ] **Backup DB** - Backup database produzione (se applicabile)

### Cleanup Fase 1: Files

- [ ] Elimina 6 file di backup
- [ ] Elimina `class-realestate-sync-github-updater.php`
- [ ] Rimuovi import di GitHub_Updater da realestate-sync.php

### Cleanup Fase 2: Commenti

- [ ] Elimina AJAX handlers commentati (lines 106-110)
- [ ] Elimina metodo `handle_manual_import()` commentato (lines 534-593)
- [ ] Cerca e rimuovi altri blocchi commentati non necessari

### Cleanup Fase 3: Deprecations

- [ ] Aggiungi `@deprecated` a `Agency_Importer`
- [ ] Aggiungi `@deprecated` a `WP_Importer`
- [ ] Aggiungi deprecation logging

### Cleanup Fase 4: Hardcoded Values

- [ ] Modifica `run_scheduled_import()` per usare Batch System
- [ ] Modifica `handle_manual_import()` per usare settings da DB
- [ ] Modifica `batch-continuation.php` per usare env variable

### Post-Cleanup

- [ ] **Test completo** - Verifica che tutto funzioni
- [ ] **Git commit** - Commit cleanup changes
- [ ] **Create PR** - Pull request per review
- [ ] **Update docs** - Aggiorna CHANGELOG.md

---

## 10. SCRIPT DI CLEANUP

### Script Bash Automatico

```bash
#!/bin/bash
# cleanup-deprecated-code.sh

echo "🧹 Starting plugin cleanup..."

# Step 1: Remove backup files
echo "Removing backup files..."
rm -f admin/views/dashboard-backup.php
rm -f admin/views/dashboard-main-backup.php
rm -f "includes/class-realestate-sync-property-mapper backup 18-8 18e50.php"
rm -f includes/class-realestate-sync-property-mapper-v1-backup.php
rm -f includes/class-realestate-sync-property-mapper-v3.1-backup.php
rm -rf backups/

# Step 2: Remove disabled classes
echo "Removing disabled GitHub Updater..."
rm -f includes/class-realestate-sync-github-updater.php

# Step 3: Count files removed
echo "✅ Cleanup complete!"
echo "Files removed: $(git status --short | grep -c '^D')"

# Step 4: Show git status
git status --short
```

**Usage**:
```bash
chmod +x cleanup-deprecated-code.sh
./cleanup-deprecated-code.sh
```

---

## 11. MIGRATION NOTES

### Per Developer Future

**Quando eliminare @deprecated classes**:

1. **Aspetta almeno 2 release** dopo marking come @deprecated
2. **Verifica nessun uso** in codebase:
   ```bash
   grep -r "RealEstate_Sync_Agency_Importer" includes/ admin/
   grep -r "RealEstate_Sync_WP_Importer[^_]" includes/ admin/
   ```
3. **Check plugin esterni** che potrebbero usare classi
4. **Annuncia in CHANGELOG** prima di rimozione
5. **Fornisci migration guide** per chi usa classi deprecate

### Breaking Changes Checklist

Prima di eliminare codice deprecato:

- [ ] Verificato nessun uso interno
- [ ] Verificato nessun uso in addon/plugin esterni
- [ ] Documentato in CHANGELOG con versione
- [ ] Fornita migration guide
- [ ] Annunciato agli utenti (email/blog post)
- [ ] Incrementato MAJOR version (semantic versioning)

---

**Data Creazione**: 03-Dic-2025
**Versione Plugin**: 1.5.0 (Batch System)
**Autore**: Claude Code Analysis
