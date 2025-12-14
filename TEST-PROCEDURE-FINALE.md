# Test Procedure Finale - Self-Healing System

**Data:** 2025-12-14
**Fix deployati:** 4 fix critici
**Obiettivo:** Verificare che self-healing previene duplicati

---

## ✅ FASE 1: Pulizia Pre-Test

### Step 1.1: Elimina Post Orfani Attuali

**Dashboard:** https://trentinoimmobiliare.it/wp-admin/tools.php?page=realestate-sync

Clicca "Cleanup Orphan Posts"

**Risultato atteso:** 36 post orfani eliminati

### Step 1.2: Resetta Queue per le 9 Properties Problematiche

**SQL da eseguire:**
```sql
UPDATE kre_realestate_import_queue
SET status = 'pending',
    wp_post_id = NULL,
    processed_at = NULL,
    error_message = NULL
WHERE item_id IN (
    4555668, 4589478, 4611751, 4613963, 4626683,
    4644206, 4648845, 4685330, 4792110
)
AND item_type = 'property';
```

**Verifica:**
```sql
SELECT item_id, status FROM kre_realestate_import_queue
WHERE item_id IN (4555668, 4589478, 4611751, 4613963, 4626683, 4644206, 4648845, 4685330, 4792110);
```

**Risultato atteso:** Tutte le 9 properties con `status = 'pending'`

### Step 1.3: Verifica Tracking (OPZIONALE)

**SQL:**
```sql
SELECT property_id, wp_post_id, last_import_date
FROM kre_realestate_sync_tracking
WHERE property_id IN (4555668, 4589478, 4611751, 4613963, 4626683, 4644206, 4648845, 4685330, 4792110);
```

**Nota:** Alcune potrebbero avere tracking, altre no. È normale.

---

## ✅ FASE 2: Trigger Re-Import

### Step 2.1: Lascia Lavorare il Batch Processor

Il batch processor in background processerà automaticamente le 9 properties pending.

**Tempo stimato:** ~10-15 minuti (9 properties, processing in batch da 5)

### Step 2.2: Monitora Progress (OPZIONALE)

**SQL ogni 2 minuti:**
```sql
SELECT
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error
FROM kre_realestate_import_queue
WHERE item_id IN (4555668, 4589478, 4611751, 4613963, 4626683, 4644206, 4648845, 4685330, 4792110);
```

**Attendi finché:** `pending = 0` e `done = 9`

---

## ✅ FASE 3: Verifica Risultati

### Step 3.1: Check Post Orfani nella Dashboard

**URL:** https://trentinoimmobiliare.it/wp-admin/tools.php?page=realestate-sync

**Risultato ATTESO:** **ZERO post orfani** 🎯

**Se vedi post orfani:**
❌ Self-healing NON ha funzionato → torna da me con il log

### Step 3.2: Verifica Tracking Database

**SQL:**
```sql
SELECT property_id, wp_post_id, last_import_date
FROM kre_realestate_sync_tracking
WHERE property_id IN (4555668, 4589478, 4611751, 4613963, 4626683, 4644206, 4648845, 4685330, 4792110)
ORDER BY property_id;
```

**Risultato ATTESO:**
- 9 righe (una per ogni property)
- Ogni riga con `wp_post_id` popolato
- Ogni riga con `last_import_date` recente (oggi)

### Step 3.3: Verifica Queue

**SQL:**
```sql
SELECT item_id, status, wp_post_id, error_message
FROM kre_realestate_import_queue
WHERE item_id IN (4555668, 4589478, 4611751, 4613963, 4626683, 4644206, 4648845, 4685330, 4792110)
ORDER BY item_id;
```

**Risultato ATTESO:**
- Tutte le 9 con `status = 'done'`
- Tutte le 9 con `wp_post_id` popolato
- `error_message = NULL`

### Step 3.4: Conta Post per Property ID

**SQL per verificare ZERO duplicati:**
```sql
SELECT
    pm.meta_value as property_id,
    COUNT(*) as numero_post
FROM wp_postmeta pm
JOIN wp_posts p ON pm.post_id = p.ID
WHERE pm.meta_key = 'property_import_id'
AND pm.meta_value IN ('4555668', '4589478', '4611751', '4613963', '4626683', '4644206', '4648845', '4685330', '4792110')
AND p.post_status != 'trash'
GROUP BY pm.meta_value
ORDER BY pm.meta_value;
```

**Risultato ATTESO:**
```
property_id | numero_post
4555668     | 1
4589478     | 1
4611751     | 1
4613963     | 1
4626683     | 1
4644206     | 1
4648845     | 1
4685330     | 1
4792110     | 1
```

**Se vedi `numero_post > 1`:**
❌ Ci sono ancora duplicati → torna da me

---

## ✅ FASE 4: Verifica Log (Se tutto OK)

### Step 4.1: Scarica Debug Log

Download da FTP: `/public_html/wp-content/debug.log`

### Step 4.2: Cerca Log Self-Healing

**Cerca nel log:**
```
SELF-HEALING
tracking_missing_force_update
Tracking rebuilt successfully
```

**Risultato ATTESO:**
- Se properties avevano tracking mancante, dovresti vedere:
  - `"🩹 [SELF-HEALING] Tracking missing → REBUILD + UPDATE"`
  - `"✅ [SELF-HEALING] Tracking rebuilt successfully"`
- ZERO errori `"Unknown column 'last_sync'"`

### Step 4.3: Verifica ZERO Errori Database

**Cerca nel log:**
```
WordPress database error
Commands out of sync
```

**Risultato ATTESO:**
- ZERO errori "Unknown column 'last_sync'"
- ZERO errori "Commands out of sync" durante import

---

## 📊 RIEPILOGO SUCCESSO

### ✅ Test PASSED se:

1. **ZERO post orfani** nella dashboard
2. **9 tracking records** salvati correttamente
3. **9 properties** con status 'done'
4. **Ogni property ha ESATTAMENTE 1 post** (no duplicati)
5. **ZERO errori database** nel log
6. **Log self-healing presenti** (se tracking era mancante)

### ❌ Test FAILED se:

1. Ci sono post orfani nella dashboard
2. `numero_post > 1` per qualsiasi property
3. Errori "Unknown column 'last_sync'" nel log
4. Errori "Commands out of sync" nel log
5. Tracking records mancanti

---

## 🔧 Se il Test Fallisce

**Torna da me con:**

1. Screenshot dashboard post orfani
2. Risultato SQL conteggio duplicati
3. Debug.log (ultimi 500 righe)
4. Risultato verifica tracking database

---

## 📝 Note Importanti

- **NON fare import completo** - testa solo le 9 properties problematiche
- **Aspetta che batch finisca** - non interrompere
- **Se tutto OK** - poi puoi fare import completo in sicurezza
- **Backup disponibile** - possiamo fare rollback se serve

---

**Buon Test!** 🎯
