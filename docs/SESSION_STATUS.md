# Session Status - 2025-11-29

## 🎯 STATO ATTUALE: PHASE 2 INFO FIELDS - CRITICAL BUGS FOUND

**Data/Ora ultima sessione**: 2025-11-29 (Custom Fields Bug Discovery)
**Stato**: ✅ **AGENCIES** | ✅ **GEOGRAPHIC** | ✅ **MAPS** | ✅ **TAXONOMIES** → 🔴 **BUGS: Custom Fields + Micro-categories**

---

## ✅ BUGS FIXED (2025-11-29)

### ✅ BUG #1 FIXED: Custom Fields Separators
**Severity**: 🔴 **CRITICAL** - Custom fields non visibili in frontend

**Problema**: WPResidence API convertiva `-` in `_` nei meta_key, rendendo i custom fields non visibili

**Root Cause**: WPResidence API sanitizza automaticamente i meta_key sostituendo dash con underscore

**Soluzione**: Eliminati TUTTI i separatori dai nomi dei campi custom

**Campi Custom (PRIMA - con separatori)**:
1. ❌ `stato-immobile` → salvato come `stato_immobile`
2. ❌ `superficie-commerciale` → salvato come `superficie_commerciale`
3. ❌ `mq-giardino` → salvato come `mq_giardino`
4. ❌ `mq-aree-esterne` → salvato come `mq_aree_esterne`
5. ❌ `altezza-soffitti` → salvato come `altezza_soffitti`
6. ❌ `mq-ufficio` → salvato come `mq_ufficio`
7. ✅ `posizione` → OK (no separatori)

**Campi Custom (DOPO - senza separatori)** - `includes/class-realestate-sync-property-mapper.php`:
1. ✅ `statoimmobile` (riga 617)
2. ✅ `posizione` (riga 624)
3. ✅ `superficiecommerciale` (riga 1250)
4. ✅ `superficieutile` (riga 1254)
5. ✅ `mqgiardino` (riga 1258)
6. ✅ `mqareeesterne` (riga 1263)
7. ✅ `altezzasoffitti` (riga 1267)
8. ✅ `mqufficio` (riga 1272)

**Backend WPResidence**: Creati manualmente 8 custom fields con nome senza separatori

**Status**: ✅ **FIXED** - Testato e funzionante

---

### 🎯 NEXT: BUG #2 - Micro-categorie da implementare
**Severity**: 🟡 **MEDIUM** - Tassonomia gerarchica non funzionante

**Problema**: Le proprietà hanno SOLO la categoria parent, manca la micro-categoria child

**Soluzione Pianificata**: Passare entrambe come termini flat della stessa tassonomia (es: "Appartamenti" + "Quadrilocale")

**Evidence dal Database** (Query 4 - righe 281-305):
```
ID   | category_name  | category_slug  | parent_category | term_type
-----|----------------|----------------|-----------------|----------
5829 | NULL           | NULL           | NULL            | CHILD (x12)
5840 | NULL           | NULL           | NULL            | CHILD (x12)
5846 | NULL           | NULL           | NULL            | CHILD (x12)
```

**Tutti i risultati mostrano**: `NULL` per category_name/slug, tipo `CHILD` ma senza dati

**Expected dall'XML**:
- **TEST001** (5829): `categorie_id=11`, `categorie_micro_id=47` → "Appartamenti" + "Quadrilocale"
- **TEST002** (5840): `categorie_id=10`, `categorie_micro_id=8` → "Uffici e Commerciali" + "Ferramenta/casalinghi"
- **TEST003** (5846): `categorie_id=12`, `categorie_micro_id=2` → "Appartamenti" (12→11 mapping) + micro eliminata (OK)

**Actual nel DB**:
- Nessuna categoria assegnata visibile nella query

**Impact**:
- ❌ Categorie gerarchiche non funzionanti
- ❌ Impossibile filtrare per micro-categoria
- ❌ SEO structure incompleta

**Root Cause**: ❓ **DA INVESTIGARE** - `ensure_hierarchical_category_terms()` non crea termini? `wp_set_object_terms()` fallisce?

---

### ❌ BUG #3: Micro-categorie con valore ERRATO (mq_balconi, mq_terrazzi)
**Severity**: 🟡 **LOW** - Mapping scorretto ma non critico

**Evidence** (Query 3 - righe 275-276):
```
5840 | TEST002 | mq_balconi  | 1 | UNDERSCORE (vecchio)
5840 | TEST002 | mq_terrazzi | 1 | UNDERSCORE (vecchio)
```

**Problema**:
- Salvati con UNDERSCORE invece di DASH
- Valore `1` sembra errato (dovrebbe essere metri quadri, non booleano)

**Info ID source**:
- Info[67] = mq balconi
- Info[68] = mq terrazzi

**Expected**: Se Info[67]=100 → `mq-balconi = 100` (non `mq_balconi = 1`)

---

## 📋 QUERY RESULTS SUMMARY

### Proprietà Testate (2025-11-29 re-import)
- **ID 59**: Proprietà manuale (riferimento di confronto)
- **ID 5829**: TEST001 - Appartamento Centro Trento
- **ID 5840**: TEST002 - Villa di Prestigio con Piscina
- **ID 5846**: TEST003 - Attico Panoramico con Terrazzo

### Custom Fields Status (Query 2)
```
ID   | stato-immobile | posizione                    | mq-giardino/ecc
-----|----------------|------------------------------|----------------
59   | ✅ Ottimo      | NULL                         | NULL
5829 | ❌ NULL        | ✅ Centro città              | ❌ NULL
5840 | ❌ NULL        | ✅ Zona collinare/panoramica | ❌ NULL
5846 | ❌ NULL        | ✅ Zona semicentrale         | ❌ NULL
```

**Note**:
- `posizione` funziona perché nessun trattino/underscore (parola singola)
- `stato-immobile` NON funziona (salvato come `stato_immobile`)
- Altri custom fields con dash NON presenti

### Property Details Status (Query 7)
```
Campo                        | 59  | 5829 | 5840 | 5846
-----------------------------|-----|------|------|-----
property_bathrooms           | ✅  | ✅   | ✅   | ✅
property_bedrooms            | ✅  | ✅   | ✅   | ✅
property_rooms               | ✅  | ✅   | ✅   | ✅
piano                        | ✅  | ✅   | ✅   | ✅
energy_class                 | ✅  | ✅   | ✅   | ✅
property_position            | ❌  | ✅   | ✅   | ✅
posizione (custom)           | ❌  | ✅   | ✅   | ✅
property_maintenance_status  | ❌  | ✅   | ✅   | ✅
stato-immobile (custom DASH) | ✅  | ❌   | ❌   | ❌
stato_immobile (OLD UNDER)   | ❌  | ✅   | ✅   | ✅
```

**Conclusione**:
- Campi standard `property_*` funzionano ✅
- Custom field `posizione` (no dash) funziona ✅
- Custom field `stato-immobile` (DASH) → salvato come `stato_immobile` (UNDERSCORE) ❌

---

## 🎯 PROSSIMI STEP (Priorità)

### 🔴 PRIORITY 1: Fix Custom Fields UNDERSCORE → DASH
**Task**: Investigare perché il Property Mapper salva `_` invece di `-`

**Ipotesi**:
1. WordPress `update_post_meta()` sanitizza automaticamente i meta_key sostituendo `-` con `_`?
2. WPResidence API Writer modifica i nomi dei campi durante il salvataggio?
3. C'è un filtro/hook che intercetta e modifica i meta_key?

**Investigation Steps**:
1. Leggere il WP Importer (`includes/class-realestate-sync-wpresidence-api-writer.php`)
2. Verificare se ci sono filtri su meta_key
3. Test diretto: `update_post_meta(5846, 'stato-immobile', 'Test')` → Verificare se salva `-` o `_`
4. Soluzione: Se WP/WPResidence forzano `_`, cambiare codice per usare underscore

**Files to Check**:
- `includes/class-realestate-sync-wpresidence-api-writer.php`
- `includes/class-realestate-sync-wp-importer.php`

---

### 🟡 PRIORITY 2: Fix Micro-categorie Missing
**Task**: Capire perché le categorie gerarchiche non vengono assegnate

**Investigation Steps**:
1. Verificare se `ensure_hierarchical_category_terms()` viene chiamato durante init
2. Verificare se i termini esistono nel database (`kre_terms`, `kre_term_taxonomy`)
3. Verificare se `wp_set_object_terms()` in `map_taxonomies_v3()` funziona
4. Log dettagliato in `map_taxonomies_v3()` per vedere cosa passa

**Query Debug**:
```sql
-- Verifica se i termini micro-categoria esistono
SELECT t.term_id, t.name, t.slug, tt.parent, parent_term.name as parent_name
FROM kre_terms t
JOIN kre_term_taxonomy tt ON t.term_id = tt.term_id AND tt.taxonomy = 'property_category'
LEFT JOIN kre_terms parent_term ON tt.parent = parent_term.term_id
WHERE t.name IN ('Quadrilocale', 'Ferramenta/casalinghi', 'Appartamenti', 'Uffici e Commerciali')
ORDER BY tt.parent, t.name;
```

---

### 🟢 PRIORITY 3: Verify mq_balconi / mq_terrazzi Values
**Task**: Verificare se i valori sono corretti o c'è un bug nella mappatura

**Check**:
- Info[67,68] nel XML TEST002 → Dovrebbero avere valori numerici (metri quadri)
- Se XML ha `<info id="67"><valore_assegnato>1</valore_assegnato>` → È corretto (significa "presente")
- Ma potrebbe essere nella sezione `<dati_inseriti>` invece?

---

## 📁 DOCUMENTATION CREATED (2025-11-29)

### 1. `docs/test-property-complete-label.xml`
File XML annotato con commenti su come ogni campo viene passato (tassonomia/meta/feature/custom).

### 2. `docs/Info-Verification/QUERY_PARAMETRICHE_VERIFICA_IMPORT.sql`
10 query parametriche con variabile `@PROP_IDS` per verifica import completa.

### 3. `docs/Info-Verification/QUERY_QUICK_TEST.sql`
5 query veloci per diagnostica rapida (TEST 1-5).

### 4. `docs/Info-Verification/ANALISI_CUSTOM_FIELDS_FRONTEND.md`
Documento diagnostico con:
- 4 ipotesi sul problema custom fields
- Checklist di verifica
- 4 soluzioni possibili
- Prossimi passi

### 5. `docs/Info-Verification/QUERY_PARAMETRICHE_VERIFICA_IMPORT.txt`
Risultati delle query eseguite sul database (evidence dei bug).

---

## 🔍 RECOVERY PROMPT

**For Next Session** (Resume from 2025-11-29):
> "Leggi SESSION_STATUS.md. 🔴 **CRITICAL BUGS FOUND**: Custom fields salvati con UNDERSCORE invece di DASH (`stato_immobile` instead of `stato-immobile`) → NON visibili in frontend. Micro-categorie gerarchiche NON assegnate (tutte NULL). Evidence in `docs/Info-Verification/QUERY_PARAMETRICHE_VERIFICA_IMPORT.txt`. Priorità: (1) Fix underscore→dash bug, (2) Fix micro-categorie missing, (3) Verify mq_balconi values. Proprietà test: 59, 5829, 5840, 5846. Branch: release/v1.4.0."

---

## 📊 PREVIOUS ACHIEVEMENTS (2025-11-27)

### ✅ CRITICAL FIX #1: AGENCIES WORKING
- Property Mapper adds `import_id` to source_data (line 334)
- Agency Manager reads from `agency_data` subarray (line 103)
- Properties correctly linked to agencies ✅

### ✅ CRITICAL FIX #2: GEOGRAPHIC DATA COMPLETE
- ISTAT Lookup Service with 282 comuni TN/BZ
- Automatic enrichment of XML data
- All geographic taxonomies populated ✅

### ✅ CRITICAL FIX #3: MAPS DISPLAY FIXED
- Coordinates passed as-is (string)
- Google Maps settings: zoom=15, Street View enabled
- Maps render correctly ✅

---

## 🚀 ROADMAP TO PRODUCTION

### ✅ PHASE 1: CORE MAPPING - COMPLETED (2025-11-27)
- ✅ Agency linking
- ✅ Geographic data (ISTAT Lookup)
- ✅ Maps display
- ✅ Geographic taxonomies
- ✅ Cleanup Test Data tool

### 🔴 PHASE 2: INFO FIELDS - CRITICAL BUGS (2025-11-29)
**Current Status**: 🔴 **BLOCKED by critical bugs**

**Blockers**:
1. 🔴 Custom fields underscore bug → Frontend non mostra campi
2. 🟡 Micro-categorie missing → Tassonomia gerarchica non funziona
3. 🟢 mq_balconi/terrazzi values → Verifica necessaria

**Must Fix Before Continue**:
- Custom fields DASH vs UNDERSCORE
- Micro-categorie hierarchical assignment

**Then Resume**:
1. ⏳ Complete inventory of INFO[1-105] fields
2. ⏳ Verify all INFO mappings
3. ⏳ Fix missing/incorrect INFO mappings
4. ⏳ Test all INFO fields in frontend

### ⏳ PHASE 3: MASSIVE IMPORT - PENDING
After Phase 2 complete

### ⏳ PHASE 4: CLEANUP & REFACTORING - PENDING
After production deployment

---

## 📊 PROGRESS TRACKER

```
[████████████████████████░░░░░░░░] 75% Complete
                                   ↑
                              BLOCKED by bugs

✅ Core Architecture
✅ Agency System
✅ Geographic Data (ISTAT)
✅ Maps Integration
✅ Taxonomies
✅ Test Tools
🔴 Custom Fields (BUG - underscore)
🟡 Micro-categories (BUG - missing)
⏳ INFO Fields Verification
⏳ Massive Import
⏳ Automation
⏳ Production Polish
```

---

**Ultima modifica**: 2025-11-29 (Custom Fields & Micro-categories Bugs Discovery)
**Autore**: Claude + Andrea
**Status**: 🔴 **BLOCKED** - Critical bugs must be fixed before continue

**User Direction**: "Posizione funziona (creata manualmente), piano funziona (no dash/underscore). Nelle query è chiaro che stiamo passando `stato_immobile` invece di `stato-immobile`. Domani investigare e fixare."

---
