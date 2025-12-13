# 🚀 Self-Healing System - Piano Deployment Completo

**Data**: 2025-12-13
**Obiettivo**: Implementare sistema self-healing in produzione con rollback facile
**Approccio**: Branch dedicato + Deploy controllato + Testing

---

## ✅ **FASE 1: CLEANUP E BACKUP (5 minuti)**

### **Step 1.1: Commit documentazione e classe stub**

```bash
# Aggiungi documentazione self-healing
git add docs/SELF-HEALING-IMPLEMENTATION-MASTER.md
git add docs/SELF-HEALING-IMPACT-ANALYSIS.md
git add docs/SELF-HEALING-INTEGRATION.md

# Aggiungi altre docs utili
git add docs/DELETION_SYSTEM.md
git add docs/EMAIL-NOTIFICATION-SYSTEM.md
git add docs/SSH-ACCESS-GUIDE.md
git add "docs/Refactory della Dashboard.md"
git add docs/CLEANUP_ORPHANS_HYBRID.sql
git add docs/QUERY_VERIFY_ORPHANS.sql

# Aggiungi classe self-healing (già creata ma non committata)
git add includes/class-realestate-sync-self-healing-manager.php

# Commit
git commit -m "docs: add self-healing documentation and base class

- Complete implementation guide (MASTER document)
- Impact analysis and integration docs
- Self-healing manager class (stub, to be implemented)
- Deletion system and email notification docs
- SQL queries for verification"
```

### **Step 1.2: Commit modifiche admin (cleanup button già deployato)**

```bash
# Commit modifiche admin
git add admin/class-realestate-sync-admin.php
git add admin/views/dashboard.php
git add admin/assets/admin.js

# Commit
git commit -m "feat: add cleanup orphan posts functionality

- Add scan and cleanup orphan posts buttons
- AJAX handlers for orphan detection
- wp_delete_post() integration for safe deletion
- Already tested and deployed in production"
```

### **Step 1.3: Commit .gitignore**

```bash
# Verifica modifiche .gitignore
git diff .gitignore

# Se ok, commit
git add .gitignore
git commit -m "chore: update .gitignore for project files"
```

### **Step 1.4: Push TUTTO su GitHub (BACKUP COMPLETO!)**

```bash
# Push tutti i commit locali su GitHub
git push origin main

# Verifica che tutto sia pushato
git status
# Output atteso: "Your branch is up to date with 'origin/main'"
```

**✅ CHECKPOINT 1**: Tutto salvato su GitHub! Puoi tornare indietro in qualsiasi momento.

---

## 🔧 **FASE 2: IMPLEMENTAZIONE SU BRANCH (45 minuti)**

### **Step 2.1: Crea branch dedicato**

```bash
# Crea e passa al nuovo branch
git checkout -b feature/self-healing-system

# Verifica di essere sul branch corretto
git branch
# Output atteso: * feature/self-healing-system
```

### **Step 2.2: Implementa Self-Healing Manager (già fatto!)**

Il file `includes/class-realestate-sync-self-healing-manager.php` è già stato creato dal documento MASTER.

**Verifica che esista e sia corretto:**

```bash
# Verifica syntax PHP
php -l includes/class-realestate-sync-self-healing-manager.php
# Output atteso: No syntax errors detected
```

**✅ Se OK, procedi. Se errore, copia il codice dal documento MASTER.**

---

### **Step 2.3: Modifica Import Engine (3 modifiche)**

Apri file: `includes/class-realestate-sync-import-engine.php`

#### **Modifica 2.3.1: Costruttore (aggiungi dopo tracking_manager init)**

**CERCA** (circa riga ~50-60):
```php
// Initialize managers
$this->tracking_manager = new RealEstate_Sync_Tracking_Manager();
$this->logger = new RealEstate_Sync_Logger($this->session_id);
```

**AGGIUNGI DOPO**:
```php
// 🩹 Initialize Self-Healing Manager
require_once plugin_dir_path(__FILE__) . 'class-realestate-sync-self-healing-manager.php';
$this->self_healing_manager = new RealEstate_Sync_Self_Healing_Manager(
    $this->tracking_manager,
    $this->logger
);

$this->logger->log("🩹 Self-Healing Manager initialized", 'info');
```

---

#### **Modifica 2.3.2: Change Detection (circa riga ~784)**

**CERCA**:
```php
// Check if property has changed
$change_status = $this->tracking_manager->check_property_changes($property_id, $property_hash);

if (!$change_status['has_changed']) {
    $this->logger->log("Property {$property_id} unchanged, skipping", 'info');
    return array(
        'success' => true,
        'property_id' => $property_id,
        'action' => 'skipped'
    );
}
```

**SOSTITUISCI CON**:
```php
// 🩹 SELF-HEALING: Resolve action (create/update/skip)
// NOTA: Quando trova tracking mancante, il sistema:
//       1. Ricostruisce il tracking record
//       2. Ritorna SEMPRE 'update' per garantire dati aggiornati
$change_status = $this->self_healing_manager->resolve_property_action($property_id, $property_hash);

// Gestione azioni speciali: SKIP
if ($change_status['action'] === 'skip') {
    // Hash uguale → nessun cambiamento
    $this->logger->log("⏭️ Property {$property_id} unchanged, skipping", 'info');

    // Aggiorna queue item come 'done'
    $this->queue_manager->update_queue_item_status(
        $queue_item['id'],
        'done',
        100,
        'No changes detected'
    );

    return array(
        'success' => true,
        'property_id' => $property_id,
        'action' => 'skipped',
        'post_id' => $change_status['wp_post_id']
    );
}

// Se action è 'create' o 'update', procedi con workflow normale
// NOTA: 'update' include anche il caso di self-healing (tracking mancante + force update)
$this->logger->log("🔄 Property {$property_id} needs processing: action={$change_status['action']}", 'info');

// Force has_changed to true per compatibility con codice esistente
$change_status['has_changed'] = true;
```

---

#### **Modifica 2.3.3: Timeout Error Handling (circa riga ~819)**

**CERCA** (DOPO chiamata a `create_or_update_property`):
```php
$wp_post_id = $this->create_or_update_property($property_data, $change_status);

return array(
    'success' => true,
    'property_id' => $property_id,
    'action' => $change_status['action'],
    'post_id' => $wp_post_id
);
```

**SOSTITUISCI CON**:
```php
$wp_post_id = $this->create_or_update_property($property_data, $change_status);

// ⚠️ FIX TIMEOUT BUG: Se wp_post_id è NULL per insert/update, è un ERRORE
if (empty($wp_post_id) && in_array($change_status['action'], ['insert', 'update', 'create'])) {
    throw new Exception("Post creation/update failed: No wp_post_id returned (possible timeout or API error)");
}

return array(
    'success' => true,
    'property_id' => $property_id,
    'action' => $change_status['action'],
    'post_id' => $wp_post_id
);
```

---

**Verifica syntax dopo modifiche:**

```bash
php -l includes/class-realestate-sync-import-engine.php
# Output atteso: No syntax errors detected
```

---

### **Step 2.4: Fix bug Verifier**

Apri file: `includes/class-realestate-sync-import-verifier.php`

**CERCA** (circa riga ~53):
```php
$query = "SELECT * FROM {$queue_table} WHERE status = 'completed' AND ...";
```

**SOSTITUISCI CON**:
```php
$query = "SELECT * FROM {$queue_table} WHERE status = 'done' AND ...";
```

**Verifica syntax:**

```bash
php -l includes/class-realestate-sync-import-verifier.php
# Output atteso: No syntax errors detected
```

---

### **Step 2.5: OPZIONALE - Aumenta timeout API**

Apri file: `includes/class-realestate-sync-wp-importer-api.php`

**CERCA** (dentro funzione `create_property` o `update_property`):
```php
$args = array(
    'timeout' => 120,  // 2 minuti
    'body' => $body,
    'headers' => $headers
);
```

**SOSTITUISCI CON**:
```php
$args = array(
    'timeout' => 180,  // 3 minuti (riduce probabilità timeout)
    'body' => $body,
    'headers' => $headers
);
```

**Verifica syntax:**

```bash
php -l includes/class-realestate-sync-wp-importer-api.php
# Output atteso: No syntax errors detected
```

---

### **Step 2.6: Commit implementazione su branch**

```bash
# Aggiungi tutti i file modificati
git add includes/class-realestate-sync-self-healing-manager.php
git add includes/class-realestate-sync-import-engine.php
git add includes/class-realestate-sync-import-verifier.php
git add includes/class-realestate-sync-wp-importer-api.php

# Commit
git commit -m "feat: implement self-healing system (conservative approach)

IMPLEMENTATION:
- Self-Healing Manager: idempotent property resolution
- Import Engine: integrate self-healing with resolve_property_action()
- When tracking missing: rebuild + FORCE UPDATE (guarantees data consistency)
- Skip action: prevents unnecessary API calls for unchanged properties
- Timeout fix: throw exception if wp_post_id NULL on create/update
- Verifier fix: status 'completed' → 'done'
- API timeout: 120s → 180s (reduces timeout probability)

CONSERVATIVE APPROACH (Opzione A):
- Self-healing always forces update after rebuilding tracking
- Cost: 1 extra API call per orphan post
- Benefit: 100% data consistency guarantee

Implements: docs/SELF-HEALING-IMPLEMENTATION-MASTER.md
"

# Push branch su GitHub (BACKUP!)
git push origin feature/self-healing-system
```

**✅ CHECKPOINT 2**: Implementazione completa su branch separato, pushata su GitHub!

---

## 🚀 **FASE 3: DEPLOY CONTROLLATO SU SERVER (15 minuti)**

### **Step 3.1: Crea tag di backup PRIMA del deploy**

```bash
# Torna su main
git checkout main

# Crea tag di backup
git tag -a "pre-self-healing-deploy-$(date +%Y%m%d-%H%M%S)" -m "Backup before self-healing deployment"

# Push tag su GitHub
git push origin --tags

# Torna su branch feature
git checkout feature/self-healing-system
```

---

### **Step 3.2: Verifica file da uploadare**

**Controlla che questi 4 file siano pronti:**

```bash
ls -lh includes/class-realestate-sync-self-healing-manager.php
ls -lh includes/class-realestate-sync-import-engine.php
ls -lh includes/class-realestate-sync-import-verifier.php
ls -lh includes/class-realestate-sync-wp-importer-api.php
```

---

### **Step 3.3: Upload su server via PowerShell**

```bash
# Esegui script upload (già preparato nel documento MASTER)
powershell -ExecutionPolicy Bypass -File upload-self-healing.ps1
```

**Output atteso:**
```
============================================================
  UPLOAD SELF-HEALING SYSTEM
============================================================

Uploading: 🩹 Self-Healing Manager (NEW FILE)
  ✅ SUCCESS

Uploading: 🔧 Import Engine (MODIFIED)
  ✅ SUCCESS

Uploading: 🐛 Import Verifier (BUG FIX)
  ✅ SUCCESS

Uploading: ⏱️ WP Importer API (TIMEOUT FIX)
  ✅ SUCCESS

============================================================
  UPLOAD COMPLETATO
============================================================

✅ Success: 4 files
```

---

### **Step 3.4: Verifica deploy su server**

```bash
# Vai nella dashboard WordPress
# URL: https://trentinoimmobiliare.it/wp-admin/

# Controlla log PHP per errori
# (Se hai accesso SSH, altrimenti usa FTP per scaricare debug.log)
```

**✅ CHECKPOINT 3**: File deployati in produzione!

---

## 🧪 **FASE 4: TESTING E VERIFICA (30 minuti)**

### **Test 1: Verifica Nessun Errore PHP**

**Via SSH (se disponibile):**
```bash
tail -f /var/www/html/wp-content/debug.log
```

**Via FTP:**
- Scarica: `wp-content/debug.log`
- Cerca errori recenti (timestamp dopo deploy)

**✅ Expected**: Nessun errore PHP fatal/warning

---

### **Test 2: Test Import Manuale Semplice**

1. **Vai in Dashboard** → RealEstate Sync → **Import**
2. **Avvia Import Manuale** (5-10 properties)
3. **Monitora log in tempo reale** (se possibile)

**✅ Expected**: Import completa senza errori

---

### **Test 3: Verifica Post Orfani = 0**

**Via Database (phpMyAdmin o SSH):**

```sql
-- Query 1: Count post orfani (DEVE essere 0)
SELECT COUNT(*) as orphan_count
FROM kre_posts p
LEFT JOIN kre_postmeta pm ON (p.ID = pm.post_id AND pm.meta_key = 'property_import_id')
LEFT JOIN kre_realestate_sync_tracking t ON t.property_id = pm.meta_value
WHERE p.post_type = 'estate_property'
AND p.post_status != 'trash'
AND t.property_id IS NULL;
-- Expected: 0
```

**✅ Expected**: orphan_count = 0

---

### **Test 4: Verifica Duplicati = 0**

```sql
-- Query 2: Count duplicati (DEVE essere 0)
SELECT
    pm.meta_value as property_id,
    COUNT(*) as post_count
FROM kre_posts p
INNER JOIN kre_postmeta pm ON (p.ID = pm.post_id AND pm.meta_key = 'property_import_id')
WHERE p.post_type = 'estate_property'
AND p.post_status != 'trash'
GROUP BY pm.meta_value
HAVING COUNT(*) > 1;
-- Expected: 0 rows
```

**✅ Expected**: Nessun duplicato trovato

---

### **Test 5: Test Self-Healing (Simulazione Post Orfano)**

**Setup:**
1. Scegli una property di test (es. property_id = '12345')
2. **Cancella tracking record** (simula post orfano):

```sql
-- Backup prima
SELECT * FROM kre_realestate_sync_tracking WHERE property_id = '12345';

-- Cancella tracking (simula orfano)
DELETE FROM kre_realestate_sync_tracking WHERE property_id = '12345';
```

3. **Avvia import manuale** della stessa property dal gestionale
4. **Monitora log** per vedere self-healing in azione

**✅ Expected nei log:**
```
🩹 SELF-HEALING: Post exists but tracking missing! Rebuilding and forcing update...
✅ Tracking rebuilt → Forcing UPDATE to guarantee data consistency
🔄 Property 12345 needs processing: action=update
```

5. **Verifica tracking ricostruito:**

```sql
SELECT * FROM kre_realestate_sync_tracking WHERE property_id = '12345';
-- DEVE esistere di nuovo con dati aggiornati
```

6. **Verifica NESSUN duplicato creato:**

```sql
SELECT COUNT(*) FROM kre_posts p
INNER JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = 'property_import_id'
AND pm.meta_value = '12345';
-- DEVE essere = 1 (un solo post!)
```

**✅ Self-healing funziona!** ✅

---

### **Test 6: Test Skip Action (Property Invariata)**

1. **Avvia import manuale** 2 volte consecutive
2. **Seconda volta**: Dovrebbe skippare properties invariate

**✅ Expected nei log (seconda volta):**
```
⏭️ Property [id] unchanged, skipping
```

---

## 🔄 **ROLLBACK RAPIDO (Se serve)**

### **Se qualcosa va storto, rollback in 2 minuti:**

```bash
# 1. Torna su main (codice vecchio)
git checkout main

# 2. Re-upload file VECCHI
powershell -ExecutionPolicy Bypass -File upload-self-healing.ps1
```

**In alternativa, usa tag di backup:**

```bash
# 1. Trova ultimo tag backup
git tag -l "pre-self-healing-deploy-*"

# 2. Checkout tag
git checkout pre-self-healing-deploy-20251213-150000

# 3. Re-upload
powershell -ExecutionPolicy Bypass -File upload-self-healing.ps1
```

---

## ✅ **SE TUTTO OK: MERGE NEL MAIN**

```bash
# 1. Torna su main
git checkout main

# 2. Merge branch feature
git merge feature/self-healing-system

# 3. Tag release
git tag -a "v1.0-self-healing" -m "Self-healing system release - Production ready"

# 4. Push tutto
git push origin main
git push origin --tags

# 5. (Opzionale) Elimina branch feature
git branch -d feature/self-healing-system
git push origin --delete feature/self-healing-system
```

---

## 📊 **MONITORING POST-DEPLOY (Primi 7 giorni)**

### **Daily Checks:**

1. **Orphan Count** (mattina e sera)
   ```sql
   SELECT COUNT(*) FROM kre_posts p
   LEFT JOIN kre_postmeta pm ON (p.ID = pm.post_id AND pm.meta_key = 'property_import_id')
   LEFT JOIN kre_realestate_sync_tracking t ON t.property_id = pm.meta_value
   WHERE p.post_type = 'estate_property' AND p.post_status != 'trash' AND t.property_id IS NULL;
   -- Expected: 0
   ```

2. **Duplicate Count** (mattina e sera)
   ```sql
   SELECT pm.meta_value, COUNT(*) FROM kre_posts p
   INNER JOIN kre_postmeta pm ON (p.ID = pm.post_id AND pm.meta_key = 'property_import_id')
   WHERE p.post_type = 'estate_property' AND p.post_status != 'trash'
   GROUP BY pm.meta_value HAVING COUNT(*) > 1;
   -- Expected: 0 rows
   ```

3. **Self-Healing Events** (cerca nei log)
   ```bash
   grep "SELF-HEALING: Post exists but tracking missing" wp-content/uploads/realestate-sync-logs/*.log
   ```

4. **Error Rate** (check import errors)
   ```sql
   SELECT status, COUNT(*) FROM kre_realestate_import_queue
   WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
   GROUP BY status;
   -- Expected: status='error' < 5%
   ```

---

## 🎯 **SUCCESS CRITERIA**

| Metric | Target | Status |
|--------|--------|--------|
| Post Orfani | 0 | ⏳ Monitor |
| Duplicati | 0 | ⏳ Monitor |
| Import Success Rate | > 95% | ⏳ Monitor |
| Self-Healing Events | Logged correttamente | ⏳ Monitor |
| PHP Errors | 0 | ⏳ Monitor |

---

## 📞 **SUPPORT**

**Se problemi:**
1. Check `wp-content/debug.log` per errori PHP
2. Check log import: `wp-content/uploads/realestate-sync-logs/`
3. Rollback usando procedura sopra
4. Contatta: Andrea Denti

---

**🚀 Ready to deploy!**

Segui gli step in ordine, verifica ogni checkpoint, e testa accuratamente.

**Tempo totale stimato**: 1.5 ore (45 min implementazione + 45 min testing)
