# 📋 Proposta UX: Ristrutturazione Dashboard
**Data:** 2025-12-18
**Ruolo:** UX Designer
**Obiettivo:** Dashboard per 2 profili utente distinti

---

## 🎯 UTENTI TARGET

### 👨‍💼 PROFILO 1: Amministratore Non Tecnico
**Necessità:**
- Gestione quotidiana flusso import
- Controllo qualità proprietà importate
- Decisioni su proprietà problematiche
- Zero conoscenza tecnica (database, queue, tracking)

**Frustrations Attuali:**
- Confusione tra strumenti tecnici e operativi
- Terminologia tecnica ("queue", "tracking", "orphan posts")
- Troppe opzioni pericolose visibili
- Workflow non lineare

### 🔧 PROFILO 2: Sviluppatore/Tecnico
**Necessità:**
- Debug sistema non ancora al 100%
- Controllo code, tracking, database
- Pulizia dati corrotti/orfani
- Accesso a tutte le funzioni tecniche

**Frustrations Attuali:**
- Strumenti di debug mescolati con operazioni quotidiane
- Mancanza di tools avanzati visibili
- Serve modo rapido per "riparare" import falliti

---

## 🏗️ PROPOSTA: ARCHITETTURA A 3 LIVELLI

```
┌─────────────────────────────────────────────────────────────────┐
│  LIVELLO 1: OPERAZIONI QUOTIDIANE (Default View)                │
│  → Per admin non tecnico                                         │
│  → Sempre visibile, user-friendly                                │
└─────────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────────┐
│  LIVELLO 2: MONITORAGGIO & LOGS                                 │
│  → Per entrambi i profili                                        │
│  → Visibilità su cosa succede                                    │
└─────────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────────┐
│  LIVELLO 3: STRUMENTI TECNICI (Collapsed by default)            │
│  → Solo per sviluppatore                                         │
│  → Nascosto dietro toggle "Modalità Sviluppatore"               │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🗂️ NUOVA STRUTTURA TAB

### TAB 1: 🏠 IMPORT (Operazioni Quotidiane)
**Per:** Admin Non Tecnico
**Descrizione:** Tutto ciò che serve per gestire il flusso quotidiano

#### SEZIONE 1: Azioni Import (Sempre in alto)
```
┌─────────────────────────────────────────────────────────┐
│ 📥 IMPORT IMMEDIATO                                     │
│                                                          │
│ [Scarica e Importa Ora]  🔄 Importa subito da server   │
│                                                          │
│ ⏰ Prossimo Import Automatico: Domani alle 03:00        │
│    [Configura Automazione →]                            │
└─────────────────────────────────────────────────────────┘
```

**Razionale:**
- CTA primaria subito visibile
- Stato automazione chiaro senza entrare in altro tab
- Link diretto a configurazione se serve modificare

#### SEZIONE 2: Proprietà da Verificare (Conditional)
```
┌─────────────────────────────────────────────────────────┐
│ ⚠️ ATTENZIONE: 5 proprietà richiedono verifica         │
│                                                          │
│ [Tabella con azioni: Vedi | Cancella | Ignora]         │
│                                                          │
│ 💡 Questi avvisi possono essere falsi positivi          │
│    (es. immagini 404 nel feed originale)                │
└─────────────────────────────────────────────────────────┘
```

**Razionale:**
- Solo se ci sono effettivamente avvisi
- Linguaggio chiaro: non "verification results" ma "proprietà da verificare"
- Spiegazione inline dei falsi positivi

#### SEZIONE 3: Statistiche Import (Nuovo)
```
┌─────────────────────────────────────────────────────────┐
│ 📊 STATO ATTUALE                                        │
│                                                          │
│ Proprietà sincronizzate: 1,247                          │
│ Ultimo import: Oggi alle 03:00 (52 nuove, 15 aggiornate)│
│ Prossimo import: Domani alle 03:00                      │
│                                                          │
│ [Vedi Storico Import →]                                 │
└─────────────────────────────────────────────────────────┘
```

**Razionale:**
- Dashboard deve mostrare STATUS prima di azioni
- Admin vuole sapere "tutto ok?" a colpo d'occhio
- Link a storico se serve approfondire

---

### TAB 2: ⏰ AUTOMAZIONE (Configurazione Schedule)
**Per:** Admin Non Tecnico (setup iniziale) + Tecnico
**Descrizione:** Configurazione import automatici

#### SEZIONE: Configurazione Completa
```
┌─────────────────────────────────────────────────────────┐
│ 🔄 IMPORT AUTOMATICI                                    │
│                                                          │
│ [X] Attiva import automatici                            │
│                                                          │
│ Frequenza:  [Ogni giorno ▼]                             │
│ Orario:     [03:00 ▼]  (Fuso orario server: UTC+1)     │
│                                                          │
│ 🎯 Prossima esecuzione: Domani 18 Dic alle 03:00       │
│                                                          │
│ [Salva Configurazione]                                  │
│                                                          │
│ ℹ️ Gli import automatici scaricano e processano        │
│    le proprietà dal gestionale ogni giorno.             │
└─────────────────────────────────────────────────────────┘
```

**Razionale:**
- Form semplice, tutto in una schermata
- Anteprima "prossima esecuzione" immediata
- Spiegazione chiara di cosa fa
- Opzioni avanzate (giorni custom, etc) in collapsible section

---

### TAB 3: 📊 STORICO & LOGS (Monitoraggio)
**Per:** Entrambi i profili
**Descrizione:** Visibilità su import passati e sistema

#### SEZIONE 1: Storico Import (Nuovo - Priority)
```
┌─────────────────────────────────────────────────────────┐
│ 📅 ULTIMI IMPORT                                        │
│                                                          │
│ Data       | Tipo      | Risultato | Dettagli           │
│ 18/12 03:00| Automatico| ✅ 67 prop| 52 nuove, 15 agg  │
│ 17/12 15:30| Manuale   | ✅ 03 prop| 0 nuove, 3 agg    │
│ 17/12 03:00| Automatico| ⚠️ 65 prop| 2 errori          │
│                                                          │
│ [Mostra più...]                                         │
└─────────────────────────────────────────────────────────┘
```

**Razionale:**
- Admin vuole vedere "gli import vanno bene?"
- Storico > Log tecnici per uso quotidiano
- Click su riga → dettaglio import specifico

#### SEZIONE 2: Log di Sistema (Existing, Renamed)
```
┌─────────────────────────────────────────────────────────┐
│ 🔍 LOG TECNICI                                          │
│                                                          │
│ [Visualizza] [Scarica] [Cancella] [System Check]       │
│                                                          │
│ [Contenuto log...]                                      │
└─────────────────────────────────────────────────────────┘
```

**Razionale:**
- Mantenuto per tecnico
- Spostato dopo storico import (priority)
- Admin generalmente non usa questa sezione

---

### TAB 4: 🛠️ STRUMENTI (Tools & Debug)
**Per:** Principalmente Tecnico
**Descrizione:** Operazioni tecniche e pulizia

#### LAYOUT: Toggle Developer Mode
```
┌─────────────────────────────────────────────────────────┐
│ ⚙️ STRUMENTI AVANZATI                                   │
│                                                          │
│ [ ] Modalità Sviluppatore                               │
│     (Mostra strumenti tecnici di debug)                 │
│                                                          │
│ ──────────────────────────────────────────────────────  │
│                                                          │
│ [STRUMENTI BASE - SEMPRE VISIBILI]                      │
│ - Carica file XML manuale                               │
│ - Configura credenziali server                          │
│ - Pulizia proprietà senza immagini                      │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ [STRUMENTI SVILUPPATORE - SOLO SE TOGGLE ATTIVO]        │
│                                                          │
│ 🔴 GESTIONE CODE IMPORT (Critical per debug)            │
│ - Monitor sessione import attiva                        │
│ - Elementi bloccati: 3 in "processing"                  │
│ - [Sblocca Elementi] [Svuota Queue] [Dettagli]         │
│                                                          │
│ 🧹 PULIZIA DATABASE                                     │
│ - Elimina post orfani (senza tracking)                  │
│ - Elimina dati di test (_test_import=1)                 │
│                                                          │
│ 🏥 DIAGNOSTICA SISTEMA                                  │
│ - Consistenza tracking table                            │
│ - Verifica integrità immagini                           │
│ - Report performance import                             │
└─────────────────────────────────────────────────────────┘
```

**Razionale:**
- Toggle nasconde complessità da admin non tecnico
- Strumenti base accessibili a tutti
- Strumenti sviluppatore: terminologia tecnica OK
- Gestione code IN EVIDENZA per tecnico (critical)

---

## 🎨 MIGLIORAMENTI UX TRASVERSALI

### 1. LINGUAGGIO USER-FRIENDLY
**Prima → Dopo:**
- "Queue Stats" → "Stato Processamento"
- "Orphan Posts" → "Proprietà Non Sincronizzate"
- "Tracking Table" → "Sincronizzazione"
- "Verification Results" → "Proprietà da Verificare"
- "Manual Import" → "Import Immediato"

### 2. FEEDBACK VISIVO CHIARO
**Elementi mancanti:**
- ✅ Icone di stato colorate (verde/giallo/rosso)
- 📊 Progress bars per import in corso
- 🔔 Notifiche toast invece di alert()
- ⏱️ Timestamp relativi ("2 ore fa" invece di "2025-12-18 15:30")

### 3. PROGRESSIVE DISCLOSURE
**Principio:** Non mostrare tutto subito
- Sezioni collapsible per opzioni avanzate
- Dettagli on-demand (click per espandere)
- Modaltà sviluppatore per strumenti tecnici

### 4. HELP CONTESTUALE
**Aggiungere:**
- 💡 Tooltip su hover per termini tecnici rimasti
- ℹ️ Help inline per operazioni critiche
- 📖 Link "Documentazione" per ogni sezione complessa

---

## 🔧 PIANO DI IMPLEMENTAZIONE

### FASE 1: Quick Wins (2-3 ore)
1. **Rinominare tab e sezioni** (linguaggio user-friendly)
2. **Aggiungere commenti HTML** esplicativi nel codice
3. **Riorganizzare ordine sezioni** nell'HTML (no logic changes)
4. **Aggiungere sezione "Stato Attuale"** nel tab Import

### FASE 2: Restructuring (4-6 ore)
1. **Implementare toggle "Modalità Sviluppatore"**
2. **Spostare Queue Management** sotto developer toggle
3. **Creare sezione "Storico Import"** (query wp_realestate_import_sessions)
4. **Collapsare opzioni avanzate** schedule

### FASE 3: Polish (2-3 ore)
1. **Migliorare feedback visivo** (icone, colori, states)
2. **Aggiungere tooltip** e help inline
3. **Implementare toast notifications** invece di alert
4. **Responsive adjustments** per mobile (se necessario)

---

## 📝 DOVE AGGIUNGERE COMMENTI HTML

### Nel file dashboard.php, aggiungere prima di ogni sezione:

```html
<!--
═══════════════════════════════════════════════════════════
📥 SEZIONE: IMPORT IMMEDIATO
───────────────────────────────────────────────────────────
UTENTE: Admin Non Tecnico
SCOPO: Trigger manuale di download e import da gestionale
AZIONI:
  - Scarica XML da server remoto
  - Processa proprietà e agenzie
  - Scarica e allega immagini
MANIPOLA:
  - estate_property posts (create/update)
  - wp_realestate_sync_tracking (create/update)
  - wp_realestate_import_queue (populate)
  - media library (download images)
FREQUENZA USO: Occasionale (backup per automazione)
═══════════════════════════════════════════════════════════
-->
```

### Template per ogni widget:
```html
<!--
WIDGET: [Nome User-Friendly]
UTENTE: [Admin / Tecnico / Entrambi]
SCOPO: [Cosa fa dal punto di vista utente]
TECNICO: [Cosa manipola effettivamente]
CRITICO: [Sì/No] - Se richiede attenzione particolare
-->
```

---

## 🎯 METRICHE DI SUCCESSO

### Per Admin Non Tecnico:
- ✅ Può triggerare import manuale in <5 secondi
- ✅ Capisce stato sistema senza vedere codice/log
- ✅ Non vede terminologia tecnica che non capisce
- ✅ Sa dove cliccare per ogni task quotidiano

### Per Sviluppatore:
- ✅ Accesso rapido a queue management (1 click)
- ✅ Può sbloccare import bloccato in <30 secondi
- ✅ Vede tutti strumenti debug senza cercare
- ✅ Logs accessibili immediatamente per troubleshooting

---

## 📎 ALLEGATI CONSIGLIATI

1. **Wireframe interattivi** (Figma/Sketch)
2. **User flow diagrams** per entrambi i profili
3. **Glossario termini** (tecnico → user-friendly mapping)
4. **Test di usabilità** con admin reale

---

## ❓ DOMANDE APERTE

1. **Storico import:** Esiste già tabella per tracking import sessions?
   → Se no, serve creare `wp_realestate_import_sessions`

2. **Toggle developer mode:** Salvare preferenza in user meta o session?
   → Preferenza: user meta per persistenza

3. **Mobile usage:** Admin usa dashboard da mobile?
   → Valutare responsive priority

4. **Multilingua:** Serve supporto altre lingue oltre italiano?
   → Per ora solo IT

5. **Ruoli WP:** Differenziare admin/editor capabilities?
   → Attualmente tutto requires `manage_options`

---

**Next Step:** Feedback e approvazione proposta prima di implementazione FASE 1.
