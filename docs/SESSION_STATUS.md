# Session Status - 2025-11-27

## ✅ STATO ATTUALE: AGENCIES ✅ + GEOGRAPHIC DATA ✅ + MAPS ✅ = CRITICAL BLOCKERS RESOLVED!

**Data/Ora ultima sessione**: 2025-11-27 (Geographic & Maps session)
**Stato**: ✅ **AGENCIES WORKING** | ✅ **GEOGRAPHIC DATA COMPLETE** | ✅ **MAPS DISPLAY FIXED**

---

## 🎉 MAJOR BREAKTHROUGH (2025-11-27)

### ✅ CRITICAL FIX #1: AGENCIES NOW WORKING!

**Problem Fixed**: Agency lookup era fallito nelle sessioni precedenti
**Root Cause**: Missing `import_id` in Property Mapper source_data

**Solution Implemented**:
1. **Property Mapper** (`includes/class-realestate-sync-property-mapper.php:334`):
   - Added explicit `import_id` field to source_data
   - Now WP Importer can create properties with correct agency linkage

2. **Agency Manager** (`includes/class-realestate-sync-agency-manager.php:103`):
   - Fixed data structure reading: now reads from `agency_data` subarray
   - Correctly processes agency email and other fields

**Result**: ✅ Properties now correctly linked to agencies!

---

### ✅ CRITICAL FIX #2: GEOGRAPHIC DATA COMPLETE (ISTAT Lookup System)

**Problem**: XML feed provides ONLY `comune_istat` code and `zona`, missing actual comune name, provincia, regione, CAP

**Solution**: Complete ISTAT Lookup Service
1. **ISTAT Lookup Table** (`data/istat-lookup-tn-bz.php`):
   - 282 comuni for Trentino-Alto Adige (116 Bolzano + 166 Trento)
   - Maps ISTAT codes → comune name, provincia, regione, CAP
   - Source: GitHub matteocontrini/comuni-json

2. **ISTAT Lookup Service** (`includes/class-realestate-sync-istat-lookup.php`):
   - Static lookup methods (no database queries)
   - Fallback logic: try XML first, then ISTAT lookup

3. **Import Engine** (`includes/class-realestate-sync-import-engine.php:142-243`):
   - New `derive_geographic_data()` method
   - Automatically enriches XML data with missing geographic fields
   - Passes complete data to Property Mapper

**Mapping (Italia → USA structure)**:
- Zona → Area (property_area taxonomy)
- Comune → City (property_city taxonomy)
- Provincia → County (property_county meta + property_county_state taxonomy)
- Regione → State (property_state meta)
- CAP → ZIP (property_zip meta)

**Result**: ✅ All geographic taxonomies and meta fields now populated!

---

### ✅ CRITICAL FIX #3: MAPS DISPLAY FIXED

**Problem**: Map rendering issues - wrong zoom, coordinates not displaying correctly

**Solutions Applied** (`includes/class-realestate-sync-property-mapper.php:404-420`):

1. **Coordinates**: Pass as-is from XML (string) without conversions
   ```php
   $meta['property_latitude'] = $xml_property['latitude'];   // No floatval()
   $meta['property_longitude'] = $xml_property['longitude']; // No strval()
   ```

2. **Google Maps Settings**:
   ```php
   $meta['google_camera_angle'] = '45';         // Camera angle
   $meta['property_google_view'] = '1';         // Enable Street View
   $meta['property_hide_map_marker'] = '0';     // Show exact marker
   $meta['page_custom_zoom'] = '15';            // Map zoom level (1-20)
   ```

**Key Field Discovered**: `page_custom_zoom` - controls property page map zoom (default: 15)

**Result**: ✅ Maps now display correctly with proper zoom and Street View!

---

## ✅ PROGRESS UPDATE (2025-11-26)

### ✅ PREREQUISITE #1: CLEANUP TOOL - COMPLETED!

**Commit**: `387fb41` - "fix: Implement Cleanup Test Data tool for agencies"

**Problems Fixed**:
1. ❌ **Agencies not marked as test**: Agency Importer wasn't setting `_test_import` meta
2. ❌ **Cleanup only searched `estate_agent`**: Should search BOTH `estate_agent` AND `estate_agency`

**Solutions**:
1. **Agency Importer** (`includes/class-realestate-sync-agency-importer.php`):
   - Added `mark_as_test` parameter to `import_agencies()` method
   - Mark agencies with `_test_import` meta after creation (line 74-77)
   - Pass flag from Import Engine (line 1045)

2. **Cleanup Handler** (`admin/class-realestate-sync-admin.php:1853-1876`):
   - Updated SQL queries to search `IN ('estate_agent', 'estate_agency')`
   - Both count and delete queries fixed

**Status**: ✅ **READY TO TEST** (needs server upload)

---

### ⏸️ PREREQUISITE #2: SSH PASSWORDLESS - IN PROGRESS

**Current Status**:
- ✅ Windows SSH Agent service enabled and running
- ✅ New SSH key generated WITHOUT passphrase
- ⏸️ **BLOCKED**: Server banned IP after multiple failed connection attempts
- ❌ Public key NOT yet added to server

**SSH Key Info**:
- **Location**: `.ssh-config/id_rsa` (NEW, no passphrase)
- **Backup**: `.ssh-config/id_rsa.backup` (OLD, with unknown passphrase)
- **Public Key**: `.ssh-config/id_rsa.pub`
- **Fingerprint**: `SHA256:G45uMYFtmNP9zBWhmkSg2DrvkmfwRrHQxoU3Fy9FrbE`

**Problem Encountered**:
```
Corrupted MAC on input.
ssh_dispatch_run_fatal: Connection to 185.220.245.107 port 22: message authentication code incorrect
```

**Root Cause**: Server fail2ban activated after multiple SSH attempts
**User Confirmed**: "Anche ieri dopo una serie di tentativi errati mi ha bannato per una mezz'ora"

**Next Steps** (when ban expires ~30min):

**Option A - Manual via cPanel** (RECOMMENDED):
1. Access server cPanel → SSH Access → Manage SSH Keys
2. Import public key from: `.ssh-config\id_rsa.pub`
3. Content starts with: `ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQC9b7Yie1Zz0sQE...`

**Option B - Automatic via SCP** (when server unblocks):
```powershell
# Wait 30 minutes for ban to expire, then:
scp -o PreferredAuthentications=password .ssh-config\id_rsa.pub trentinoimreit@185.220.245.107:~/temp_key.pub

ssh -o PreferredAuthentications=password trentinoimreit@185.220.245.107 "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat ~/temp_key.pub >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && rm ~/temp_key.pub"

# Test passwordless connection
ssh -i .ssh-config\id_rsa trentinoimreit@185.220.245.107 "pwd"
```

**SSH Config Issue Identified**:
- Line 7: `UserKnownHostsFile /dev/null` - `/dev/null` doesn't exist on Windows
- This may have contributed to connection issues
- **Fix**: Use direct ssh commands without `-F .ssh-config\config` for now

---

## 🔥 PROBLEMA ATTUALE (2025-11-25) - STILL OPEN

### ❌ Agency Lookup Non Funziona

**Situazione**:
- PHASE 1: Agenzie create correttamente (es: 5341, 5343)
- PHASE 2: Lookup fallisce → Proprietà senza agenzia associata
- Post_type: WPResidence crea `estate_agent` invece di `estate_agency`
- Meta field: `xml_agency_id` mancante o non trovato

**Log Evidence**:
```
PHASE 1: "agency_ids":[5341,5343],"skipped":2  ← Agenzie skipped (esistenti)
PHASE 2: 🔍 Looking up agency by XML ID: 1
        ⚠️ Agency NOT found by XML ID: 1  ← Lookup FAIL!
```

**Root Cause Identificato**:
1. ❌ Agenzie create in import precedente **SENZA** `xml_agency_id` meta
2. ❌ Import corrente le salta (skip) → NON aggiornate
3. ❌ Lookup cerca `xml_agency_id` che non esiste → FAIL
4. ⚠️ WPResidence `/agency/add` crea `estate_agent` non `estate_agency`

---

## 🛠️ FIX IMPLEMENTATI (2025-11-25)

### Fix #1: Property User Field Implementation ✅
**File**: `config/default-settings.php`, `includes/class-realestate-sync-wpresidence-api-writer.php`
**Commit**: `c29f2cb`

Aggiunto campo `property_user` per ownership esplicita:
```php
$property_user_id = get_option('realestate_sync_property_user_id', '');
if (!empty($property_user_id)) {
    $api_body['property_user'] = (string) $property_user_id;
}
```

**Riferimento**: `docs/API_AGENT_FIELDS_VERIFICATION.md`

---

### Fix #2: Agency Lookup in PHASE 2 (Instead of Create/Update) ✅
**File**: `includes/class-realestate-sync-agency-manager.php`, `includes/class-realestate-sync-property-mapper.php`
**Commit**: `e11d287`

**Problema**: Property Mapper chiamava `create_or_update_agency_from_xml()` in PHASE 2
**Risultato**: Trovava agenzia pre-esistente 5291, la aggiornava, tutte le proprietà su 5291

**Fix**:
- Aggiunta funzione `lookup_agency_by_xml_id()` in Agency Manager
- Property Mapper ora fa SOLO lookup, NON create/update
- Cerca agenzie create in PHASE 1 per `xml_agency_id` meta

```php
// NEW CODE (with rollback comments)
$xml_agency_id = $xml_property['agency_data']['id'];
$agency_id = $this->agency_manager->lookup_agency_by_xml_id($xml_agency_id);

// OLD CODE (keep commented for rollback)
// $agency_id = $this->agency_manager->create_or_update_agency_from_xml($xml_property);
```

**Rollback**: Decommenta riga 1250 in Property Mapper

---

### Fix #3: Logger Parameter Order Fixed ✅
**File**: `includes/class-realestate-sync-agency-manager.php`, `includes/class-realestate-sync-property-mapper.php`
**Commit**: `b792657`

**Problema**: Logger calls con parametri invertiti: `log(LEVEL, MESSAGE)`
**Correct**: `log(MESSAGE, LEVEL)`

Ora i log mostrano messaggi con emoji invece di solo "INFO", "WARNING".

---

### Fix #4: XML Agency ID Added to API Body ✅
**File**: `includes/class-realestate-sync-wpresidence-agency-api-writer.php`
**Commit**: `ffb2f5c`

**CRITICAL**: Agency API Writer NON passava `xml_agency_id` all'API!

```php
// NEW: Pass xml_agency_id to save as meta
if (!empty($agency_data['xml_agency_id'])) {
    $api_body['xml_agency_id'] = $agency_data['xml_agency_id'];
    $this->logger->log('✅ XML Agency ID added to API body: ' . $agency_data['xml_agency_id'], 'info');
}
```

**Senza questo**: Agenzie create senza meta → Lookup fallisce sempre

---

### Fix #5: Search Both estate_agent AND estate_agency ✅
**File**: `includes/class-realestate-sync-agency-manager.php`
**Commit**: `26e9fe5`

**Problema**: WPResidence `/agency/add` crea `estate_agent` CPT, non `estate_agency`
**Log Evidence**: "Created new agent: Trentino Immobiliare Excellence SRL"

```php
// OLD: Cerca solo estate_agency
'post_type' => 'estate_agency'

// NEW: Cerca entrambi (WPResidence usa agent per agencies)
'post_type' => array('estate_agent', 'estate_agency')
```

**Nota**: Semanticamente sbagliato (SRL/SAS sono aziende, non persone), ma WPResidence non distingue

---

### Fix #6: Manual Meta Addition (Temp Workaround) ✅
**Method**: SSH wp-cli
**Date**: 2025-11-25 22:28

Aggiunto manualmente `xml_agency_id` alle agenzie esistenti:
```bash
wp post meta add 5341 xml_agency_id '1'
wp post meta add 5343 xml_agency_id '2'
```

**Status**: ❌ Lookup ancora fallisce (motivo sconosciuto)

---

## 🚫 BLOCKERS: 2 CRITICAL PREREQUISITES

### ⚠️ PREREQUISITE #1: Test Data Cleanup Tool (CRITICO)

**Problema**:
- Dashboard ha tab "Tools" con button "Cleanup Test Data"
- Dovrebbe cancellare proprietà/agenti/agenzie con meta `_test_import=1`
- **Attualmente NON funziona**
- Necessario per testing rapido senza cancellazioni manuali

**Impact**:
- ❌ Ogni test richiede cancellazione manuale di 3 proprietà + 2 agenzie
- ❌ Rallenta debugging (5+ minuti per test invece di 30 secondi)
- ❌ Impossibile iterare rapidamente sui fix

**Task**:
1. Verificare funzione `cleanup_test_data()` in codebase
2. Verificare se meta `_test_import` viene salvato durante import di test
3. Fixare query o hook che cancella test data
4. Testare cancellazione funzionante

**Files Probabili**:
- `admin/class-realestate-sync-admin.php`
- `admin/views/dashboard-*.php`
- Query: `DELETE FROM wp_posts WHERE ID IN (SELECT post_id FROM wp_postmeta WHERE meta_key = '_test_import' AND meta_value = '1')`

---

### ⚠️ PREREQUISITE #2: SSH Passwordless Authentication (CRITICO)

**Problema**:
- Ogni comando SSH/SCP richiede passphrase della chiave privata
- Rallenta upload modifiche: ~10 secondi wait per password ogni volta
- Interruzione workflow durante debugging

**Impact**:
- ❌ 5-10 upload file per sessione = 50-100 secondi persi in password prompts
- ❌ Distrazione durante debug flow
- ❌ Impossibile automatizzare deploy scripts

**Configurazione Attuale**:
- SSH Key: `.ssh-config/id_rsa` (con passphrase)
- Password: Stored in `C:/Users/Andrea/OneDrive/Lavori/novacom/Trentino-immobiliare/accessi/pswSSH.txt`
- Config: `.ssh-config/config`

**Soluzione Raccomandata**: SSH Agent (Windows)

**Task**:
1. Enable Windows OpenSSH Authentication Agent service:
   ```powershell
   Set-Service -Name ssh-agent -StartupType Automatic
   Start-Service ssh-agent
   ```

2. Add key to agent (ask password ONCE):
   ```bash
   ssh-add C:/Users/Andrea/OneDrive/Lavori/novacom/Trentino-immobiliare/realestate-sync-plugin/.ssh-config/id_rsa
   ```

3. Verify:
   ```bash
   ssh-add -l  # Should show key fingerprint
   ```

4. Test passwordless:
   ```bash
   ssh -F .ssh-config/config trentinoimmobiliare "pwd"  # No password prompt!
   ```

**Alternative**: Remove passphrase (less secure):
```bash
ssh-keygen -p -f .ssh-config/id_rsa
# Enter old passphrase, press Enter for new (empty)
```

---

## 📋 STATO CODEBASE

### Files Modificati Oggi (2025-11-25)

1. ✅ `config/default-settings.php` - Added property_user setting
2. ✅ `includes/class-realestate-sync-wpresidence-api-writer.php` - property_user field
3. ✅ `includes/class-realestate-sync-agency-manager.php` - lookup_agency_by_xml_id() + search both post types
4. ✅ `includes/class-realestate-sync-property-mapper.php` - Use lookup instead of create_or_update
5. ✅ `includes/class-realestate-sync-wpresidence-agency-api-writer.php` - xml_agency_id in API body
6. ✅ `docs/API_AGENT_FIELDS_VERIFICATION.md` - User-created analysis document

### Git Status

**Branch**: `release/v1.4.0`
**Commits Today**: 6
- `26e9fe5` - Search both estate_agent AND estate_agency
- `ffb2f5c` - Add xml_agency_id to agency API body
- `b792657` - Correct logger parameter order
- `e11d287` - Agency lookup in PHASE 2 instead of create/update
- `c29f2cb` - Add property_user field implementation
- `a270dfc` - Cherry-pick: API agent fields verification doc

**Backup Files on Server**:
- `class-realestate-sync-agency-manager.php.backup-20251125-214758`
- `class-realestate-sync-property-mapper.php.backup-20251125-214758`
- `class-realestate-sync-wpresidence-agency-api-writer.php.backup-20251125-220246`

**Rollback**: Restore backup files via SSH if needed

---

## 🐛 ISSUE ANCORA APERTA

### Agency Lookup Failure (Causa Ignota)

**Anche dopo tutti i fix**:
- ✅ xml_agency_id presente nel database (verificato via wp-cli)
- ✅ Lookup cerca in entrambi post_types (estate_agent + estate_agency)
- ✅ Log mostra query eseguita correttamente
- ❌ Lookup ritorna "NOT found"

**Possibili Cause Residue**:
1. Meta key type mismatch (string vs numeric)
2. Post status non 'publish'
3. Cache issue (WP_Query cache)
4. Meta table corruption
5. Post_type effettivo diverso da estate_agent/estate_agency

**Next Debug Steps**:
1. Query diretta SQL per verificare meta esistente:
   ```sql
   SELECT post_id, meta_key, meta_value
   FROM wp_postmeta
   WHERE meta_key = 'xml_agency_id'
   AND meta_value IN ('1', '2');
   ```

2. Query diretta post type:
   ```sql
   SELECT ID, post_title, post_type, post_status
   FROM wp_posts
   WHERE ID IN (5341, 5343);
   ```

3. Test WP_Query manualmente:
   ```php
   $query = new WP_Query([
       'post_type' => ['estate_agent', 'estate_agency'],
       'meta_query' => [['key' => 'xml_agency_id', 'value' => '1']]
   ]);
   var_dump($query->posts);
   ```

---

## 📚 DOCUMENTATION REFERENCES

### User-Created Docs
- `docs/API_AGENT_FIELDS_VERIFICATION.md` - Comprehensive field analysis
- `docs/Agencies API.txt` - WPResidence API documentation (Postman export)

### Internal Docs
- `docs/SIDEBAR_AGENCY_FIX.md` - Agency sidebar association analysis
- `docs/TOMORROW_AGENCY_INVESTIGATION.md` - Investigation plan (obsolete)
- `docs/API_ADD_EDIT_OPERATIONS.md` - API operations examples
- `docs/SESSION_STATUS.md` - This file

---

## 🎯 NEXT SESSION PLAN

### BEFORE Debugging Agency Issue:

1. **IMPLEMENT Cleanup Test Data Tool** 🔴
   - Enable "Cleanup Test Data" button in dashboard
   - Verify `_test_import` meta is saved during test imports
   - Test deletion of test properties/agencies
   - **Time estimate**: 30-60 minutes
   - **Blocker**: Cannot iterate quickly without this

2. **SETUP SSH Agent** 🔴
   - Configure Windows OpenSSH Authentication Agent
   - Add SSH key to agent
   - Test passwordless connection
   - **Time estimate**: 10-15 minutes
   - **Blocker**: Slows down every file upload

### AFTER Prerequisites:

3. **DEBUG Agency Lookup Failure** 🟡
   - Run SQL queries to verify meta + post_type
   - Test WP_Query manually
   - Identify root cause
   - Implement fix
   - **Time estimate**: 30-90 minutes

4. **VERIFY End-to-End Flow** 🟢
   - Cleanup test data
   - Run fresh import
   - Verify: PHASE 1 creates agencies WITH xml_agency_id
   - Verify: PHASE 2 finds agencies and assigns to properties
   - Verify: Frontend shows property with agency sidebar
   - **Time estimate**: 15-30 minutes

---

## 🔍 RECOVERY PROMPT

**For Next Session** (Resume from 2025-11-26 morning):
> "Leggi SESSION_STATUS.md. ✅ PREREQUISITE #1 COMPLETATO: Cleanup Test Data tool fixato e committato (387fb41). ⏸️ PREREQUISITE #2 IN CORSO: SSH passwordless setup quasi completo - chiave generata senza passphrase, ma server ha bannato IP per troppi tentativi SSH (fail2ban). Chiave pubblica pronta in `.ssh-config/id_rsa.pub`. NEXT STEP: Aspettare 30min che ban scada, poi aggiungere chiave pubblica al server (via cPanel o SCP). Poi testare connessione passwordless, uploadare fix cleanup tool, e finalmente debuggare agency lookup failure. Branch: release/v1.4.0, commit 387fb41."

---

## 📊 TESTING STATUS

### Test Environment
- **Server**: trentinoimmobiliare.it (185.220.245.107)
- **Plugin Path**: `public_html/wp-content/plugins/realestate-sync-plugin/`
- **Test File**: `docs/test-property-complete-fixed.xml` (3 properties, 2 agencies)
- **SSH User**: `trentinoimreit`

### Test Agencies Created
- ID 5341: Trentino Immobiliare Excellence SRL (xml_agency_id=1) - estate_agent
- ID 5343: Dolomiti Real Estate SAS (xml_agency_id=2) - estate_agent

### Test Properties
- TEST001: XML agency_id=1 → Should link to 5341 → ❌ NOT linked
- TEST002: XML agency_id=1 → Should link to 5341 → ❌ NOT linked
- TEST003: XML agency_id=2 → Should link to 5343 → ❌ NOT linked

### Expected vs Actual
**Expected**: Properties assigned to agencies 5341/5343
**Actual**: Properties created WITHOUT agency assignment

---

## 🚀 DEFINITION OF DONE

### Session Complete When:
1. ✅ "Cleanup Test Data" button works
2. ✅ SSH passwordless connection active
3. ✅ Agency lookup finds agencies
4. ✅ Properties assigned to correct agencies
5. ✅ Frontend shows property with agency sidebar
6. ✅ All test properties cleaned up
7. ✅ Ready for production XML import

---

**Ultima modifica**: 2025-11-25 22:30
**Autore**: Claude + Andrea
**Status**: ⏸️ **PAUSED - PREREQUISITES NEEDED** - Cleanup tool + SSH agent required before continuing

**User Feedback**: "vado a farmi una sega" - Session ends, resume after prerequisites implemented.

---

