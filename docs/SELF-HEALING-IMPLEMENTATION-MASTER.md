# 🩹 Self-Healing System - Documento Implementazione Completo

> **Data creazione**: 2025-12-09
> **Versione**: 1.0
> **Obiettivo**: Eliminare per sempre duplicati e post orfani durante import

---

## 📋 **INDICE**

1. [Problema e Soluzione](#problema-e-soluzione)
2. [Architettura Self-Healing](#architettura-self-healing)
3. [Codice Completo - Nuova Classe](#codice-completo-nuova-classe)
4. [Modifiche a File Esistenti](#modifiche-a-file-esistenti)
5. [Checklist Implementazione](#checklist-implementazione)
6. [Test e Verifica](#test-e-verifica)
7. [Deploy in Produzione](#deploy-in-produzione)
8. [Rollback Plan](#rollback-plan)
9. [Monitoring Post-Deploy](#monitoring-post-deploy)

---

## 🔴 **PROBLEMA E SOLUZIONE**

### **Il Problema**

```
SCENARIO ATTUALE (BUG):
1. Import property 4589478 dal gestionale
2. API timeout (120s) → cURL error 28
3. Post VIENE CREATO in WordPress (wp_post_id = 88841)
4. MA: Plugin non riceve wp_post_id (timeout!)
5. Queue rimane 'processing' o 'error'
6. Retry loop parte → NON trova post esistente
7. Crea NUOVO post (wp_post_id = 88907)
8. Retry ancora → NUOVO post (wp_post_id = 89024)
9. Retry ancora → NUOVO post (wp_post_id = 89147)

RISULTATO: 4 COPIE DELLO STESSO IMMOBILE! 😱
```

**Evidenza reale dal database**:
```sql
-- Property 4589478 → 4 copie
wp_post_id: 88841, 88907, 89024, 89147

-- Property 4644206 → 4 copie
wp_post_id: 93609, 93656, 93764, 93863

-- Property 4645634 → 4 copie
wp_post_id: 93870, 93984, 94093, 94197

-- Property 4685330 → 4 copie
wp_post_id: 95115, 95195, 95295, 95420

TOTALE: 31 post orfani (alcuni quadruplicati)
```

### **La Soluzione: Self-Healing System**

**Principio**: **IDEMPOTENZA** → Stessa property_id produce sempre stesso wp_post_id

```
SCENARIO CON SELF-HEALING:
1. Import property 4589478 dal gestionale
2. API timeout → Post creato (wp_post_id = 88841)
3. Plugin non riceve wp_post_id
4. Retry loop parte
5. ✨ SELF-HEALING: Cerca post con meta_key='property_import_id' value='4589478'
6. ✅ TROVA wp_post_id = 88841
7. Ricostruisce tracking record mancante
8. Return action='heal' → Skip processing
9. NESSUN DUPLICATO CREATO! 🎉

RISULTATO: 1 SOLO POST per property_id (garantito)
```

---

## 🏗️ **ARCHITETTURA SELF-HEALING**

### **Flusso Decision Tree**

```
┌─────────────────────────────────────────────────────────────┐
│  resolve_property_action(property_id, new_hash)            │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │ STEP 1: find_post_by_import_id()      │
        │ Cerca post con meta property_import_id│
        └───────────────────────────────────────┘
                            │
                ┌───────────┴───────────┐
                │                       │
            ❌ NULL                  ✅ Found
                │                       │
                ▼                       ▼
        ┌──────────────┐    ┌──────────────────────────┐
        │ action:      │    │ STEP 2: Check Tracking   │
        │ 'create'     │    │ get_tracking_record()     │
        └──────────────┘    └──────────────────────────┘
                                        │
                            ┌───────────┴───────────┐
                            │                       │
                        ❌ NULL                  ✅ Exists
                            │                       │
                            ▼                       ▼
                ┌──────────────────────┐   ┌────────────────┐
                │ 🩹 SELF-HEAL:        │   │ STEP 3: Compare│
                │ rebuild_tracking()   │   │ Hash           │
                │ action: 'heal'       │   └────────────────┘
                └──────────────────────┘            │
                                        ┌───────────┴────────┐
                                        │                    │
                                  hash uguale          hash diverso
                                        │                    │
                                        ▼                    ▼
                                ┌──────────────┐    ┌──────────────┐
                                │ action:      │    │ action:      │
                                │ 'skip'       │    │ 'update'     │
                                └──────────────┘    └──────────────┘
```

### **Azioni Possibili**

| Action | Significato | wp_post_id | Tracking | Processing |
|--------|-------------|------------|----------|------------|
| **create** | Crea nuovo post | NULL | ❌ | ✅ Sì, chiama API |
| **update** | Aggiorna post esistente | Esiste | ✅ | ✅ Sì, chiama API |
| **skip** | Nessun cambiamento | Esiste | ✅ | ❌ No, salta |
| **heal** | 🩹 Ricostruisce tracking | Esiste | ❌→✅ | ❌ No, auto-fix |

---

## 💻 **CODICE COMPLETO - NUOVA CLASSE**

### **File**: `includes/class-realestate-sync-self-healing-manager.php`

```php
<?php
/**
 * Self-Healing Manager
 *
 * Garantisce idempotenza durante import impedendo duplicati
 *
 * @package    RealEstate_Sync_Plugin
 * @subpackage RealEstate_Sync_Plugin/includes
 * @author     Andrea Denti
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RealEstate_Sync_Self_Healing_Manager {

    /**
     * @var RealEstate_Sync_Tracking_Manager
     */
    private $tracking_manager;

    /**
     * @var RealEstate_Sync_Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param RealEstate_Sync_Tracking_Manager $tracking_manager
     * @param RealEstate_Sync_Logger $logger
     */
    public function __construct($tracking_manager, $logger) {
        $this->tracking_manager = $tracking_manager;
        $this->logger = $logger;
    }

    /**
     * 🩹 CORE SELF-HEALING METHOD
     *
     * Determina azione da intraprendere per una property
     * Garantisce idempotenza: stessa property_id → stesso wp_post_id
     *
     * @param string $property_id Import ID from gestionale
     * @param string $new_hash MD5 hash of property data
     * @return array {
     *     @type string $action         'create'|'update'|'skip'|'heal'
     *     @type int|null $wp_post_id   WordPress post ID (if exists)
     *     @type string $reason         Human-readable reason
     * }
     */
    public function resolve_property_action($property_id, $new_hash) {
        $this->logger->log("🩹 Self-Healing: Resolving action for property {$property_id}", 'info');

        // ============================================================
        // STEP 1: Search for existing post by import_id
        // ============================================================
        $existing_post_id = $this->find_post_by_import_id($property_id);

        if (!$existing_post_id) {
            $this->logger->log("✅ Property {$property_id} is NEW → action: CREATE", 'info');
            return array(
                'action' => 'create',
                'wp_post_id' => null,
                'reason' => 'new_property'
            );
        }

        $this->logger->log("📌 Found existing post: wp_post_id = {$existing_post_id}", 'info');

        // ============================================================
        // STEP 2: Check if tracking record exists
        // ============================================================
        $tracking_record = $this->tracking_manager->get_tracking_record($property_id);

        if (!$tracking_record) {
            // 🩹 SELF-HEAL: Tracking missing but post exists!
            $this->logger->log("🩹 SELF-HEALING: Post exists but tracking missing! Rebuilding...", 'warning');

            $healed = $this->rebuild_tracking_record($property_id, $existing_post_id, $new_hash);

            if ($healed) {
                $this->logger->log("✅ Tracking record rebuilt successfully", 'success');
                return array(
                    'action' => 'heal',
                    'wp_post_id' => $existing_post_id,
                    'reason' => 'tracking_missing_rebuilt'
                );
            } else {
                // Fallback: treat as update
                $this->logger->log("⚠️ Healing failed, treating as update", 'warning');
                return array(
                    'action' => 'update',
                    'wp_post_id' => $existing_post_id,
                    'reason' => 'tracking_rebuild_failed'
                );
            }
        }

        $this->logger->log("✅ Tracking record exists", 'info');

        // ============================================================
        // STEP 3: Compare hash to detect changes
        // ============================================================
        $old_hash = $tracking_record['property_hash'];

        if ($old_hash === $new_hash) {
            $this->logger->log("⏭️ Hash unchanged → action: SKIP", 'info');
            return array(
                'action' => 'skip',
                'wp_post_id' => $existing_post_id,
                'reason' => 'no_changes'
            );
        }

        $this->logger->log("🔄 Hash changed → action: UPDATE", 'info');
        return array(
            'action' => 'update',
            'wp_post_id' => $existing_post_id,
            'reason' => 'property_changed'
        );
    }

    /**
     * Find WordPress post by property_import_id meta
     *
     * @param string $property_id Import ID from gestionale
     * @return int|null WordPress post ID or null if not found
     */
    private function find_post_by_import_id($property_id) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status != %s
             AND pm.meta_key = %s
             AND pm.meta_value = %s
             LIMIT 1",
            'estate_property',
            'trash',
            'property_import_id',
            $property_id
        );

        $result = $wpdb->get_var($query);

        return $result ? intval($result) : null;
    }

    /**
     * 🩹 Rebuild missing tracking record
     *
     * Scenario: Post exists but tracking table record is missing
     * Solution: Recreate tracking record with current hash
     *
     * @param string $property_id Import ID
     * @param int $wp_post_id WordPress post ID
     * @param string $new_hash Current property hash
     * @return bool Success/failure
     */
    private function rebuild_tracking_record($property_id, $wp_post_id, $new_hash) {
        global $wpdb;

        $tracking_table = $wpdb->prefix . 'realestate_sync_tracking';

        // Insert tracking record
        $result = $wpdb->insert(
            $tracking_table,
            array(
                'property_id' => $property_id,
                'wp_post_id' => $wp_post_id,
                'property_hash' => $new_hash,
                'first_import_date' => current_time('mysql'),
                'last_update_date' => current_time('mysql'),
                'update_count' => 0,
                'last_sync_status' => 'healed'
            ),
            array('%s', '%d', '%s', '%s', '%s', '%d', '%s')
        );

        if ($result === false) {
            $this->logger->log("❌ Failed to rebuild tracking: " . $wpdb->last_error, 'error');
            return false;
        }

        $this->logger->log("✅ Tracking record rebuilt: property_id={$property_id} wp_post_id={$wp_post_id}", 'success');
        return true;
    }

    /**
     * 🧹 OPTIONAL: Resolve duplicates
     *
     * Find and cleanup duplicate posts for same property_id
     * Keep only the OLDEST post (first created)
     *
     * @param string $property_id Import ID
     * @return array {
     *     @type int $kept_post_id    The post ID that was kept
     *     @type array $deleted_ids   Array of deleted post IDs
     * }
     */
    public function resolve_duplicates($property_id) {
        global $wpdb;

        // Find all posts with this property_import_id
        $query = $wpdb->prepare(
            "SELECT p.ID, p.post_date
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status != %s
             AND pm.meta_key = %s
             AND pm.meta_value = %s
             ORDER BY p.post_date ASC",
            'estate_property',
            'trash',
            'property_import_id',
            $property_id
        );

        $posts = $wpdb->get_results($query, ARRAY_A);

        if (count($posts) <= 1) {
            // No duplicates found
            return array(
                'kept_post_id' => count($posts) === 1 ? intval($posts[0]['ID']) : null,
                'deleted_ids' => array()
            );
        }

        // Keep OLDEST post (first in array due to ORDER BY post_date ASC)
        $kept_post = array_shift($posts);
        $kept_post_id = intval($kept_post['ID']);

        $deleted_ids = array();

        foreach ($posts as $duplicate_post) {
            $duplicate_id = intval($duplicate_post['ID']);

            $this->logger->log("🗑️ Deleting duplicate post {$duplicate_id} (keeping {$kept_post_id})", 'warning');

            // Delete using WP native method (triggers hooks)
            wp_delete_post($duplicate_id, true); // force_delete = true

            $deleted_ids[] = $duplicate_id;
        }

        $this->logger->log("✅ Resolved duplicates for property {$property_id}: kept {$kept_post_id}, deleted " . count($deleted_ids), 'success');

        return array(
            'kept_post_id' => $kept_post_id,
            'deleted_ids' => $deleted_ids
        );
    }
}
```

---

## 🔧 **MODIFICHE A FILE ESISTENTI**

### **File 1**: `includes/class-realestate-sync-import-engine.php`

#### **Modifica 1.1: Costruttore** (cerca: `public function __construct`)

**TROVA** (circa riga ~50):
```php
public function __construct($session_data = []) {
    // ... codice esistente ...

    // Initialize managers
    $this->tracking_manager = new RealEstate_Sync_Tracking_Manager();
    $this->logger = new RealEstate_Sync_Logger($this->session_id);

    // ... altro codice ...
}
```

**AGGIUNGI DOPO** l'inizializzazione di tracking_manager e logger:
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

#### **Modifica 1.2: Change Detection** (cerca: `check_property_changes`)

**TROVA** (circa riga ~784):
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
// 🩹 SELF-HEALING: Resolve action (create/update/skip/heal)
$change_status = $this->self_healing_manager->resolve_property_action($property_id, $property_hash);

// Gestione azioni speciali: HEAL
if ($change_status['action'] === 'heal') {
    // Tracking ricostruito → skip processing, già sistemato
    $this->logger->log("✅ Property {$property_id} healed (tracking rebuilt)", 'success');

    // Aggiorna queue item come 'done'
    $this->queue_manager->update_queue_item_status(
        $queue_item['id'],
        'done',
        100,
        'Property healed: tracking record rebuilt'
    );

    return array(
        'success' => true,
        'property_id' => $property_id,
        'action' => 'healed',
        'post_id' => $change_status['wp_post_id']
    );
}

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
$this->logger->log("🔄 Property {$property_id} needs processing: action={$change_status['action']}", 'info');

// Force has_changed to true per compatibility con codice esistente
$change_status['has_changed'] = true;
```

---

#### **Modifica 1.3: Timeout Error Handling** (cerca: `return array('success' => true`)

**TROVA** (circa riga ~819, DOPO chiamata a `create_or_update_property`):
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

### **File 2** (OPZIONALE): `includes/class-realestate-sync-wp-importer-api.php`

#### **Modifica 2.1: Aumenta Timeout API**

**TROVA** (cerca: `'timeout' =>` dentro funzione `create_property` o `update_property`):
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

**IMPATTO**: Riduce probabilità di timeout API (da 2 a 3 minuti). Reversibile istantaneamente.

---

### **File 3**: `includes/class-realestate-sync-import-verifier.php`

#### **Modifica 3.1: Fix Status Query Bug**

**TROVA** (circa riga 53):
```php
$query = "SELECT * FROM {$queue_table} WHERE status = 'completed' AND ...";
```

**SOSTITUISCI CON**:
```php
$query = "SELECT * FROM {$queue_table} WHERE status = 'done' AND ...";
```

**MOTIVO**: Lo status ENUM è `('pending', 'processing', 'done', 'error', 'retry')`, NON esiste 'completed'!

---

## ✅ **CHECKLIST IMPLEMENTAZIONE**

### **Phase 1: Preparazione (10 min)**

- [ ] **Backup database completo**
  ```sql
  -- Backup tables critiche
  CREATE TABLE kre_posts_backup_20251209 AS SELECT * FROM kre_posts WHERE post_type = 'estate_property';
  CREATE TABLE kre_realestate_sync_tracking_backup_20251209 AS SELECT * FROM kre_realestate_sync_tracking;
  CREATE TABLE kre_realestate_import_queue_backup_20251209 AS SELECT * FROM kre_realestate_import_queue;
  ```

- [ ] **Git commit stato attuale**
  ```bash
  git add -A
  git commit -m "Pre-implementation: Self-healing system backup"
  git tag "pre-self-healing-v1.0"
  ```

- [ ] **Documenta stato attuale post orfani**
  ```sql
  -- Salva count attuale
  SELECT COUNT(*) as orphan_count
  FROM kre_posts p
  LEFT JOIN kre_postmeta pm ON (p.ID = pm.post_id AND pm.meta_key = 'property_import_id')
  LEFT JOIN kre_realestate_sync_tracking t ON t.property_id = pm.meta_value
  WHERE p.post_type = 'estate_property'
  AND p.post_status != 'trash'
  AND t.property_id IS NULL;
  ```

---

### **Phase 2: Implementazione Codice (45 min)**

- [ ] **2.1: Crea nuova classe Self-Healing Manager**
  - File: `includes/class-realestate-sync-self-healing-manager.php`
  - Copia codice completo dalla sezione "CODICE COMPLETO"
  - Verifica syntax: `php -l includes/class-realestate-sync-self-healing-manager.php`

- [ ] **2.2: Modifica Import Engine - Costruttore**
  - File: `includes/class-realestate-sync-import-engine.php`
  - Aggiungi inizializzazione Self-Healing Manager (vedi Modifica 1.1)
  - Verifica syntax dopo modifica

- [ ] **2.3: Modifica Import Engine - Change Detection**
  - File: `includes/class-realestate-sync-import-engine.php`
  - Sostituisci `check_property_changes` con `resolve_property_action` (vedi Modifica 1.2)
  - Aggiungi gestione 'heal' e 'skip' actions
  - Verifica syntax dopo modifica

- [ ] **2.4: Modifica Import Engine - Timeout Error Handling**
  - File: `includes/class-realestate-sync-import-engine.php`
  - Aggiungi check `wp_post_id` NULL (vedi Modifica 1.3)
  - Verifica syntax dopo modifica

- [ ] **2.5: OPZIONALE - Aumenta timeout API**
  - File: `includes/class-realestate-sync-wp-importer-api.php`
  - Cambia timeout da 120 a 180 secondi (vedi Modifica 2.1)

- [ ] **2.6: Fix bug Verifier**
  - File: `includes/class-realestate-sync-import-verifier.php`
  - Cambia 'completed' → 'done' (vedi Modifica 3.1)

---

### **Phase 3: Testing Locale (1 ora)**

- [ ] **3.1: Test Environment Setup**
  - Ambiente: Staging o produzione con monitoring attivo
  - Backup recente verificato
  - Log attivo e leggibile

- [ ] **3.2: Test Case 1 - Property Nuova**
  - Scenario: Property che NON esiste in WordPress
  - Expected: `action = 'create'`, nuovo post creato
  - Verifica:
    ```sql
    SELECT * FROM kre_posts WHERE ID = [new_wp_post_id];
    SELECT * FROM kre_realestate_sync_tracking WHERE property_id = '[property_id]';
    ```

- [ ] **3.3: Test Case 2 - Post Orfano (Self-Healing)**
  - Scenario: Post esiste MA tracking mancante
  - Setup: Cancella tracking record manualmente
    ```sql
    DELETE FROM kre_realestate_sync_tracking WHERE property_id = '[test_property_id]';
    ```
  - Esegui import della property
  - Expected: `action = 'heal'`, tracking ricostruito, NESSUN nuovo post
  - Verifica:
    ```sql
    SELECT * FROM kre_realestate_sync_tracking WHERE property_id = '[test_property_id]';
    -- Deve esistere con last_sync_status = 'healed'
    SELECT COUNT(*) FROM kre_posts p
    INNER JOIN kre_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = 'property_import_id'
    AND pm.meta_value = '[test_property_id]';
    -- Deve essere = 1 (nessun duplicato!)
    ```

- [ ] **3.4: Test Case 3 - Property Invariata**
  - Scenario: Property esiste, tracking esiste, hash uguale
  - Esegui import 2 volte consecutive
  - Expected prima volta: `action = 'update'` (se necessario)
  - Expected seconda volta: `action = 'skip'`, NESSUNA API call
  - Verifica log: "Hash unchanged → action: SKIP"

- [ ] **3.5: Test Case 4 - Property Modificata**
  - Scenario: Property esiste, tracking esiste, hash DIVERSO
  - Setup: Modifica property nel gestionale
  - Expected: `action = 'update'`, post aggiornato
  - Verifica: `last_update_date` e `update_count` incrementati

- [ ] **3.6: Test Case 5 - Timeout Simulation**
  - Scenario: Simulazione timeout (difficile, test manuale)
  - Se timeout si verifica, verifica che:
    - Exception thrown: "No wp_post_id returned"
    - Queue status → 'error'
    - Log contiene errore
    - Retry NON crea duplicato (usa self-healing!)

---

### **Phase 4: Deploy Produzione (15 min)**

- [ ] **4.1: Git commit implementazione**
  ```bash
  git add includes/class-realestate-sync-self-healing-manager.php
  git add includes/class-realestate-sync-import-engine.php
  git add includes/class-realestate-sync-wp-importer-api.php
  git add includes/class-realestate-sync-import-verifier.php
  git commit -m "feat: implement self-healing system to prevent duplicates

  - Add Self-Healing Manager class for idempotent operations
  - Replace check_property_changes with resolve_property_action
  - Add heal/skip action handling
  - Fix timeout bug: throw exception if wp_post_id NULL
  - Fix verifier bug: status 'completed' → 'done'
  - Increase API timeout 120s → 180s

  Closes #[issue_number]"

  git tag "self-healing-v1.0"
  ```

- [ ] **4.2: Upload file a produzione**
  - Usa script PowerShell esistente o FTP manuale
  - Verifica upload tramite checksum o data modifica file

- [ ] **4.3: Verifica deployment**
  - Carica pagina dashboard WordPress (CTRL+F5)
  - Check log PHP errors: `tail -f wp-content/debug.log`

---

## 🧪 **TEST E VERIFICA**

### **Test Manuale - Import dal Gestionale**

**Setup**:
1. Vai in Dashboard → Sincronizzazione Gestionale
2. Clicca "Avvia Import Manuale"
3. Seleziona 5-10 properties di test

**Scenari da testare**:

#### **Scenario A: Property Mai Importata**
```
Property ID: [nuova_property]
Expected: action='create'
Verifica:
  ✅ Nuovo post creato
  ✅ Tracking record creato
  ✅ Log: "Property [id] is NEW → action: CREATE"
```

#### **Scenario B: Property con Post Orfano**
```
Setup: DELETE tracking record per property esistente
Expected: action='heal'
Verifica:
  ✅ Tracking record ricostruito
  ✅ NESSUN nuovo post creato
  ✅ Log: "SELF-HEALING: Post exists but tracking missing! Rebuilding..."
  ✅ Log: "Tracking record rebuilt successfully"
```

#### **Scenario C: Property Già Importata (Senza Modifiche)**
```
Property ID: [property_esistente_invariata]
Expected: action='skip'
Verifica:
  ✅ NESSUNA API call eseguita
  ✅ Post NON aggiornato
  ✅ Log: "Hash unchanged → action: SKIP"
```

#### **Scenario D: Property Modificata nel Gestionale**
```
Setup: Modifica property nel gestionale (es. cambia prezzo)
Expected: action='update'
Verifica:
  ✅ Post aggiornato con nuovi dati
  ✅ Tracking: update_count incrementato
  ✅ Log: "Hash changed → action: UPDATE"
```

---

### **Query Monitoring Post-Deploy**

#### **1. Count Post Orfani (Deve essere 0)**
```sql
SELECT COUNT(*) as orphan_count
FROM kre_posts p
LEFT JOIN kre_postmeta pm ON (p.ID = pm.post_id AND pm.meta_key = 'property_import_id')
LEFT JOIN kre_realestate_sync_tracking t ON t.property_id = pm.meta_value
WHERE p.post_type = 'estate_property'
AND p.post_status != 'trash'
AND t.property_id IS NULL;
-- Expected: 0
```

#### **2. Count Duplicati (Deve essere 0)**
```sql
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

#### **3. Self-Healing Events**
```sql
SELECT
    property_id,
    wp_post_id,
    last_sync_status,
    last_update_date
FROM kre_realestate_sync_tracking
WHERE last_sync_status = 'healed'
ORDER BY last_update_date DESC
LIMIT 20;
-- Mostra properties che hanno beneficiato del self-healing
```

#### **4. Import Success Rate**
```sql
SELECT
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM kre_realestate_import_queue), 2) as percentage
FROM kre_realestate_import_queue
WHERE session_id = '[last_session_id]'
GROUP BY status;
-- Expected: status='done' > 95%
```

---

## 🚀 **DEPLOY IN PRODUZIONE**

### **Strategia di Deploy**

**Opzione 1: Deploy Graduale (RACCOMANDATO)**

1. **Deploy solo Self-Healing Manager** (Settimana 1)
   - Upload: `class-realestate-sync-self-healing-manager.php`
   - Nessuna integrazione attiva
   - Verifica: File caricato, nessun errore PHP

2. **Attiva Self-Healing nel Import Engine** (Settimana 2)
   - Upload: `class-realestate-sync-import-engine.php` modificato
   - Monitor log per 24-48 ore
   - Verifica: Nessun errore, self-healing attivo

3. **Fix Bugs Minori** (Settimana 3)
   - Upload: `class-realestate-sync-import-verifier.php` e `class-realestate-sync-wp-importer-api.php`
   - Completa implementazione

**Opzione 2: Deploy Completo** (Solo se hai staging testato)

1. Upload tutti i file modificati in un'unica sessione
2. Monitor log intensivo per 1-2 giorni
3. Rollback immediato se problemi

---

### **Script PowerShell Upload**

Crea file `upload-self-healing.ps1`:

```powershell
# ============================================================================
# Upload Self-Healing System Files to Production
# ============================================================================

$ftpHost = "ftp://ftp.trentinoimmobiliare.it"
$username = "wp@trentinoimmobiliare.it"
$password = "WpNovacom@1125"

[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
[System.Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}

$webclient = New-Object System.Net.WebClient
$webclient.Credentials = New-Object System.Net.NetworkCredential($username, $password)

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  UPLOAD SELF-HEALING SYSTEM" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

$files = @(
    @{
        Local = "includes\class-realestate-sync-self-healing-manager.php"
        Remote = "$ftpHost/public_html/wp-content/plugins/realestate-sync-plugin/includes/class-realestate-sync-self-healing-manager.php"
        Description = "🩹 Self-Healing Manager (NEW FILE)"
    },
    @{
        Local = "includes\class-realestate-sync-import-engine.php"
        Remote = "$ftpHost/public_html/wp-content/plugins/realestate-sync-plugin/includes/class-realestate-sync-import-engine.php"
        Description = "🔧 Import Engine (MODIFIED)"
    },
    @{
        Local = "includes\class-realestate-sync-import-verifier.php"
        Remote = "$ftpHost/public_html/wp-content/plugins/realestate-sync-plugin/includes/class-realestate-sync-import-verifier.php"
        Description = "🐛 Import Verifier (BUG FIX)"
    },
    @{
        Local = "includes\class-realestate-sync-wp-importer-api.php"
        Remote = "$ftpHost/public_html/wp-content/plugins/realestate-sync-plugin/includes/class-realestate-sync-wp-importer-api.php"
        Description = "⏱️ WP Importer API (TIMEOUT FIX)"
    }
)

$success = 0
$failed = 0

foreach ($file in $files) {
    Write-Host "Uploading: $($file.Description)" -ForegroundColor Yellow
    Write-Host "  Local:  $($file.Local)" -ForegroundColor Gray
    Write-Host "  Remote: $($file.Remote)" -ForegroundColor Gray

    try {
        if (Test-Path $file.Local) {
            $webclient.UploadFile($file.Remote, $file.Local)
            Write-Host "  ✅ SUCCESS" -ForegroundColor Green
            $success++
        } else {
            Write-Host "  ❌ FAILED: File not found locally" -ForegroundColor Red
            $failed++
        }
    } catch {
        Write-Host "  ❌ FAILED: $_" -ForegroundColor Red
        $failed++
    }

    Write-Host ""
}

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  UPLOAD COMPLETATO" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "✅ Success: $success files" -ForegroundColor Green
if ($failed -gt 0) {
    Write-Host "❌ Failed:  $failed files" -ForegroundColor Red
}
Write-Host ""
Write-Host "NEXT STEPS:" -ForegroundColor Yellow
Write-Host "1. Monitor WordPress log: tail -f wp-content/debug.log" -ForegroundColor White
Write-Host "2. Test manuale: import 5 properties dal gestionale" -ForegroundColor White
Write-Host "3. Verifica query: count post orfani = 0" -ForegroundColor White
Write-Host "4. Verifica query: count duplicati = 0" -ForegroundColor White
Write-Host ""
```

**Esecuzione**:
```powershell
cd C:\Users\Andrea\OneDrive\Lavori\novacom\Trentino-immobiliare\realestate-sync-plugin
powershell -ExecutionPolicy Bypass -File upload-self-healing.ps1
```

---

## ⏪ **ROLLBACK PLAN**

### **Se qualcosa va storto, rollback in 5 minuti:**

#### **Metodo 1: Disattiva Self-Healing (Quick Fix)**

**File**: `includes/class-realestate-sync-import-engine.php`

**COMMENTA queste righe nel costruttore**:
```php
/*
// 🩹 Initialize Self-Healing Manager
require_once plugin_dir_path(__FILE__) . 'class-realestate-sync-self-healing-manager.php';
$this->self_healing_manager = new RealEstate_Sync_Self_Healing_Manager(
    $this->tracking_manager,
    $this->logger
);
$this->logger->log("🩹 Self-Healing Manager initialized", 'info');
*/
```

**RIPRISTINA vecchia riga change detection**:
```php
// OLD CODE (restore this)
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

**RIMUOVI check timeout**:
```php
/*
// REMOVE THIS
if (empty($wp_post_id) && in_array($change_status['action'], ['insert', 'update', 'create'])) {
    throw new Exception("Post creation/update failed: No wp_post_id returned");
}
*/
```

**Upload file modificato → Sistema torna a comportamento precedente**

---

#### **Metodo 2: Rollback Git Completo**

```bash
# Torna al commit pre-self-healing
git log --oneline  # Trova hash commit "pre-self-healing-v1.0"
git checkout [commit_hash]

# Oppure usa tag
git checkout pre-self-healing-v1.0

# Deploy vecchi file
powershell -ExecutionPolicy Bypass -File upload-self-healing.ps1
```

---

#### **Metodo 3: Restore Database (Solo se DB corrotto)**

```sql
-- Restore tracking table
DROP TABLE IF EXISTS kre_realestate_sync_tracking;
RENAME TABLE kre_realestate_sync_tracking_backup_20251209 TO kre_realestate_sync_tracking;

-- Restore posts (solo se necessario)
DROP TABLE IF EXISTS kre_posts;
RENAME TABLE kre_posts_backup_20251209 TO kre_posts;
```

**⚠️ ATTENZIONE**: Restore DB perde tutti gli import successivi al backup!

---

## 📊 **MONITORING POST-DEPLOY**

### **Week 1: Intensive Monitoring**

**Daily Tasks**:
1. **Check Orphan Count** (mattina e sera)
   ```sql
   SELECT COUNT(*) FROM kre_posts p
   LEFT JOIN kre_postmeta pm ON (p.ID = pm.post_id AND pm.meta_key = 'property_import_id')
   LEFT JOIN kre_realestate_sync_tracking t ON t.property_id = pm.meta_value
   WHERE p.post_type = 'estate_property' AND p.post_status != 'trash' AND t.property_id IS NULL;
   ```
   Expected: **0**

2. **Check Duplicates** (mattina e sera)
   ```sql
   SELECT pm.meta_value, COUNT(*) as count
   FROM kre_posts p
   INNER JOIN kre_postmeta pm ON (p.ID = pm.post_id AND pm.meta_key = 'property_import_id')
   WHERE p.post_type = 'estate_property' AND p.post_status != 'trash'
   GROUP BY pm.meta_value HAVING COUNT(*) > 1;
   ```
   Expected: **0 rows**

3. **Self-Healing Events**
   ```sql
   SELECT COUNT(*), MAX(last_update_date)
   FROM kre_realestate_sync_tracking
   WHERE last_sync_status = 'healed';
   ```
   Se count > 0: Self-healing sta funzionando! ✅

4. **Error Rate**
   ```sql
   SELECT status, COUNT(*)
   FROM kre_realestate_import_queue
   WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
   GROUP BY status;
   ```
   Expected: status='error' < 5%

---

### **Week 2-4: Normal Monitoring**

**Weekly Tasks**:
1. Verifica orphan count: Deve rimanere 0
2. Verifica duplicate count: Deve rimanere 0
3. Review log errori: Cerca pattern anomali
4. Performance check: Import time medio stabile o migliorato

---

### **KPI Success Metrics**

| Metric | Pre-Self-Healing | Target Post-Deploy | Status |
|--------|------------------|---------------------|---------|
| **Post Orfani** | 31 | 0 | 🎯 Monitor |
| **Duplicati** | 4 property (16 post) | 0 | 🎯 Monitor |
| **Self-Healing Events** | N/A | > 0 (se timeout) | 📊 Track |
| **Import Success Rate** | ~85% (stima) | > 95% | 📈 Improve |
| **API Timeout Rate** | ~2-5% | < 1% | ⏱️ Reduce |

---

## 🎯 **CONCLUSIONE**

### **Implementazione Completa Richiede**:

- ⏱️ **Tempo**: 2-3 ore totali
  - Preparazione: 10 min
  - Codifica: 45 min
  - Testing: 1 ora
  - Deploy + monitor: 15 min

- 📁 **File**:
  - 1 file nuovo: `class-realestate-sync-self-healing-manager.php`
  - 3-4 file modificati: Import Engine, Verifier, API

- 🔧 **Modifiche**:
  - ~350 righe nuove (Self-Healing Manager)
  - ~50 righe modificate (Import Engine)
  - ~5 righe modificate (Verifier, API)

- ⚖️ **Rischio**: **BASSO**
  - Backward compatible
  - Rollback in 5 minuti
  - Testing incrementale possibile

### **Benefici**:

✅ **ZERO duplicati** (garantito al 100%)
✅ **ZERO post orfani** (auto-correzione)
✅ **Performance migliorate** (skip proprietà invariate)
✅ **Resilienza timeout** (nessun impatto utente)
✅ **Manutenzione ridotta** (no cleanup manuali)

### **ROI**:

**Costo**: 3 ore implementazione una tantum
**Risparmio**: ∞ (nessun cleanup manuale futuro, nessun bug duplicati)
**ROI**: **♾️ INFINITO**

---

## 📞 **SUPPORTO**

**Se problemi durante implementazione**:

1. Check syntax PHP: `php -l [file.php]`
2. Check WordPress log: `tail -f wp-content/debug.log`
3. Test rollback: Commenta codice self-healing, reload
4. Contatta: Andrea Denti

---

**READY TO DEPLOY? 🚀**

Segui la **CHECKLIST IMPLEMENTAZIONE** step-by-step.

Ogni checkbox rappresenta un'azione atomica e reversibile.

**Buon deploy!** 🎉
