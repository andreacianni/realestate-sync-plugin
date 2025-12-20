# Dashboard Structure Map
**File:** `admin/views/dashboard.php`
**Aggiornato:** 2025-12-18

---

## 📋 TAB NAVIGATION (righe 30-46)

```
┌─────────────────────────────────────────────────────────┐
│  Dashboard | Automazione Import | Info | Tools | Logs  │
└─────────────────────────────────────────────────────────┘
```

| Tab | ID | Icona | Riga |
|-----|-----|-------|------|
| Dashboard | `#dashboard` | dashicons-dashboard | 31-33 |
| Automazione Import | `#automazione` | dashicons-clock | 34-36 |
| Info | `#info` | dashicons-info | 37-39 |
| Tools | `#tools` | dashicons-admin-tools | 40-42 |
| Logs | `#logs` | dashicons-list-view | 43-45 |

---

## 🏠 TAB 1: DASHBOARD (righe 48-268)

### Widget: Proprietà da Verificare (righe 52-147)
- **Condizione:** Visibile solo se `get_option('realestate_sync_latest_verification')` contiene proprietà
- **Stile:** Warning (background giallo)
- **Elementi:**
  - Tabella con Property ID, Problemi, Azioni
  - Bottoni per riga: `#view-property-{id}`, `#delete-property-{id}`, `#ignore-verification-{id}`

### Widget: Import Manuale (righe 148-180)
- **ID carta:** `.rs-card` (senza ID specifico)
- **Titolo:** "Import Manuale"
- **Elementi:**
  - Checkbox: `#mark-as-test-manual-import`
  - Bottone: `#start-manual-import`
  - Log output: `#manual-import-log-output` (hidden di default)
  - Contenuto log: `#manual-import-log-content`

### Widget: Configurazione Credenziali XML (righe 181-267)
- **ID carta:** `.rs-card` (senza ID specifico)
- **Titolo:** "Configurazione Credenziali Download XML"
- **Elementi:**
  - Radio buttons: `credential_source` (hardcoded/database)
  - Form: `#rs-xml-credentials-form`
  - Input: `#xml_url`, `#xml_user`, `#xml_pass`
  - Bottone edit: `#rs-xml-edit-btn`
  - Container save/cancel: `#rs-xml-save-cancel-btns`
  - Bottone test: `#rs-test-connection`
  - Risultato test: `#rs-test-connection-result`

---

## ⏰ TAB 2: AUTOMAZIONE IMPORT (righe 269-443)

### Widget: Configurazione Schedule (righe 290-442)
- **Elementi:**
  - Toggle enable: `#enable-schedule`
  - Select frequency: `#schedule-frequency`
  - Input time: `#schedule-time`
  - Config settimanale: `#weekly-config` (conditional)
  - Config giorni custom: `#custom-days-config` (conditional)
  - Config mesi custom: `#custom-months-config` (conditional)
  - Preview: `#next-run-preview`
  - Bottone salva: `#save-schedule-config`
  - Status: `#schedule-status`

---

## ℹ️ TAB 3: INFO (righe 444-646)

### Sezione: Required Custom Fields (righe 449-595)
- **Auto-display status:** `#field-status-auto-display`

### Sezione: XML Mapping Coverage (righe 597-624)
- **Container mapping:** `#xml-mapping-always-expanded`

### Sezione: Field Management (righe 626-645)
- **Bottone:** `#test-field-population-enhanced`
- **Container risultati:** `#test-results` (hidden)
- **Contenuto:** `#test-results-content`

---

## 🛠️ TAB 4: TOOLS (righe 647-974)

### Card 1: Strumenti Amministrazione (righe 649-768)

#### Sezione: Gestione Queue Import (righe 653-729)
- **Container status:** `#last-import-status`
- **Elementi display:**
  - `#import-session-id`
  - `#import-start-time`
  - `#import-process-status`
  - `#import-total-items`
  - `#import-completed-items`
  - `#import-remaining-items`
  - `#import-progress-fill`
  - `#import-progress-text`
- **Bottone:** `#refresh-import-status`

#### Alert: Pending Items (righe 707-728)
- **Container:** `#pending-items-alert` (hidden di default)
- **Messaggio:** `#pending-items-message`
- **Bottoni:**
  - `#show-pending-details`
  - `#retry-pending-items`
  - `#delete-pending-items`
- **Lista:** `#pending-items-list` (hidden, expandable)

#### Sezione: Pulizia Queue (righe 731-742)
- **Bottone:** `#clear-all-queue`

#### Sezione: Cleanup Orphan Posts (righe 744-766)
- **Bottoni:**
  - `#scan-orphan-posts`
  - `#cleanup-orphan-posts` (hidden di default)
- **Report:** `#orphan-posts-report` (hidden)

### Card 2: Testing & Development (righe 770-910)

#### Sezione: Import XML (righe 774-806)
- **Input file:** `#test-xml-file`
- **Checkbox:** `#mark-as-test-import`
- **Bottone:** `#process-test-file` (disabled di default)
- **Log output:** `#test-log-output` (hidden)
- **Contenuto log:** `#test-log-content`

#### Sezione: Normal Processing Mode (righe 808-826)
- **Info only** - nessun elemento interattivo

#### ⚠️ Sezione: Database Tools (righe 828-862) - **PERICOLOSA**
- **Bottoni:**
  - `#create-property-fields` ⚠️ PERICOLOSO
  - `#create-properties-from-sample` ⚠️ PERICOLOSO
  - `#show-property-stats`
  - `#cleanup-test-data` ⚠️ PERICOLOSO
  - `#cleanup-properties` ⚠️ MOLTO PERICOLOSO
- **Display stats:** `#property-stats-display` (hidden)
- **Contenuto stats:** `#property-stats-content`

#### Sezione: Cleanup Proprietà Senza Immagini (righe 864-910)
- **Bottone analisi:** `#analyze-no-images`
- **Risultati analisi:** `#no-images-analysis` (hidden)
- **Contenuto analisi:** `#no-images-analysis-content`
- **Container azioni:** `#cleanup-actions` (hidden)
- **Bottoni cleanup:**
  - `#cleanup-no-images-trash`
  - `#cleanup-no-images-permanent`
- **Risultati cleanup:** `#cleanup-results` (hidden)
- **Contenuto risultati:** `#cleanup-results-content`

#### ⚠️ Sezione: Professional Activation Tools (righe 911-972) - **TECNICA**
- **Bottoni:**
  - `#check-activation-status`
  - `#view-activation-info`
  - `#test-activation-workflow`
- **Display status:** `#activation-status-display` (hidden)
- **Contenuto status:** `#activation-status-content`
- **Display info:** `#activation-info-display` (hidden)
- **Contenuto info:** `#activation-info-content`

---

## 📊 TAB 5: LOGS (righe 975-1003)

### Widget: Log & Monitoraggio (righe 977-1002)
- **Bottoni:**
  - `#view-logs`
  - `#download-logs`
  - `#clear-logs`
  - `#system-check`
- **Viewer:** `#log-viewer` (hidden)
- **Contenuto:** `#log-content`

---

## 🎨 ELEMENTI GLOBALI

### Alert Container
- **ID:** `#rs-alerts-container` (riga 27)
- **Uso:** Toast notifications dinamiche

### Classi CSS Comuni
- `.rs-card` - Widget/sezione container
- `.rs-button-primary` - Bottone principale (blu)
- `.rs-button-secondary` - Bottone secondario (grigio)
- `.rs-button-warning` - Bottone warning (giallo)
- `.rs-button-danger` - Bottone pericoloso (rosso)
- `.rs-hidden` - Elemento nascosto
- `.rs-dashboard-grid` - Grid layout del dashboard

---

## 📝 FORMATO COMANDI

### Sintassi
```
[AZIONE] [TARGET] [→ DESTINAZIONE] [NOTE]
```

### Esempi
```
RIMUOVI #create-property-fields
RIMUOVI sezione "Database Tools" (righe 828-862)
COMMENTA #professional-activation-tools
SPOSTA #manual-import-widget → #automazione
RINOMINA tab "Dashboard" → "Import"
AGGIUNGI sezione "Email Notifications" in #info
```

### Azioni Disponibili
- `RIMUOVI` - Elimina completamente
- `COMMENTA` - Commenta (per futuro uso)
- `SPOSTA` - Cambia posizione
- `RINOMINA` - Cambia testo/label
- `AGGIUNGI` - Crea nuovo elemento
- `MODIFICA` - Cambia comportamento/stile

---

## ⚠️ SEZIONI PERICOLOSE IDENTIFICATE

### Alta Pericolosità 🔴
1. **Database Tools** (righe 828-862)
   - `#create-property-fields` - Crea campi duplicati
   - `#create-properties-from-sample` - Crea dati fittizi
   - `#cleanup-test-data` - Cancella senza conferma chiara
   - `#cleanup-properties` - **CANCELLA TUTTO** ⚠️⚠️⚠️

2. **Professional Activation Tools** (righe 911-972)
   - Modifiche a basso livello del plugin
   - Solo per sviluppatori

### Media Pericolosità 🟡
1. **Cleanup Orphan Posts** (righe 744-766)
   - Cancellazione permanente ma con conferma
   - Scope limitato (solo orfani)

2. **Cleanup Proprietà Senza Immagini** (righe 864-910)
   - Due modalità: Cestino (reversibile) / Permanente
   - Con analisi preventiva

### Sicure 🟢
- Import Manuale (con flag test)
- Upload Test XML (isolato)
- Configurazione credenziali
- Automazione schedule
- Queue management
- Logs viewer
