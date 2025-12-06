# RealEstate Sync Plugin - Roadmap

**Versione Corrente:** v1.6.1-cleanup
**Data:** 06 Dicembre 2025
**Stato:** Post-Cleanup, Ready for Enhancements

---

## 📍 STATO ATTUALE

### ✅ Completato - Cleanup (v1.6.1)

**Branch:** `cleanup/final-cleanup`
**Data completamento:** 06 Dicembre 2025

**Modifiche:**
- ✅ Rimossi 6 file temporanei di test/debug
- ✅ Rimossi 4 file di documentazione obsoleta
- ✅ Debug DB page condizionata a WP_DEBUG
- ✅ Classi deprecate marcate correttamente
- ✅ Token security system implementato (multi-level)
- ✅ Documentazione token completa (CRON_TOKEN_SETUP.md)

**Risultato:**
- ~2.250 righe di codice obsoleto rimosse
- ~670 righe di documentazione aggiunta
- Codebase pulito e production-ready

**Prossimo step:** Merge su main → Deploy → Test smoke → Migliorie

---

## 🎯 ROADMAP MIGLIORIE

### **Priorità 1: Queue Optimization** ⭐⭐⭐⭐⭐

**Status:** 📋 Pianificato
**Branch:** `feature/queue-optimization`
**Versione target:** v1.7.0
**Tempo stimato:** 2-3 ore implementazione + 1 ora test
**Priorità:** ALTISSIMA

#### Problema Identificato
Import di 772 proprietà richiede 7+ ore, ma:
- 75% (581 items) vengono processati e poi skippati (no changes)
- Controllo hash fatto DOPO l'accodamento
- Spreco di tempo e risorse server

#### Soluzione
Spostare controllo hash PRIMA dell'accodamento:
- Calcolare hash in fase di filtering (Orchestrator)
- Accodare solo items con modifiche effettive
- Riduzione attesa: 772 → ~198 items in queue

#### Impatto Atteso
- ⏱️ Tempo import: 7h 10min → ~2h (↓72%)
- 📊 Items processati: 772 → 198 (↓74%)
- 💻 Carico server: Ridotto drasticamente
- 💰 Costi: Meno CPU usage prolungato

#### Implementazione
1. Modificare `Batch_Orchestrator::process_xml_batch()` (linea 158-172)
2. Aggiungere pre-filtering con `Tracking_Manager::check_property_changes()`
3. Logging dettagliato statistiche pre-filtering
4. (Opzionale) Applicare stesso filtro alle agenzie

#### File Coinvolti
- `includes/class-realestate-sync-batch-orchestrator.php`
- `includes/class-realestate-sync-tracking-manager.php` (già pronto)

#### Test Plan
- Test con XML sample (verificare hash consistency)
- Test import completo su staging
- Confronto tempi before/after
- Verifica log statistiche

#### Documentazione
- Piano dettagliato: `docs/QUEUE_OPTIMIZATION_PLAN.md`
- Analisi completa con pseudo-codice e metriche

---

### **Priorità 2: Email Notification System** ⭐⭐⭐⭐

**Status:** 📋 Pianificato
**Branch:** `feature/email-notifications`
**Versione target:** v1.8.0
**Tempo stimato:** 3-4 ore
**Priorità:** ALTA

#### Obiettivo
Notifiche email automatiche per eventi import:
- Import completato con successo
- Import fallito con errori
- Import completato con warning
- Statistiche dettagliate nel corpo email

#### Vantaggi
- 📧 Monitoring automatico import schedulati
- 🔔 Alert immediati in caso di problemi
- 📊 Report statistiche via email
- 👀 Meno necessità di controllare manualmente log

#### Implementazione
Basata su documentazione esistente:
- File: `docs/EMAIL_NOTIFICATION_IMPLEMENTATION.md`
- Classe: `includes/class-realestate-sync-email-notifier.php` (già creata)

#### Feature Incluse
1. **Email su Import Completato**
   - Statistiche: insert/update/skip/errors
   - Durata import
   - Link a dashboard

2. **Email su Import Fallito**
   - Messaggio errore
   - Stack trace (se disponibile)
   - Suggerimenti troubleshooting

3. **Configurazione Dashboard**
   - Enable/disable notifications
   - Indirizzo email destinatario
   - Selezione eventi da notificare

#### File Coinvolti
- `includes/class-realestate-sync-email-notifier.php` (espandere)
- `includes/class-realestate-sync-batch-processor.php` (trigger email)
- `batch-continuation.php` (trigger email on completion)
- `admin/views/settings-email.php` (UI configurazione)

---

### **Priorità 3: Dashboard UX Refactoring** ⭐⭐⭐

**Status:** 📋 Pianificato
**Branch:** `feature/dashboard-refactor`
**Versione target:** v1.9.0
**Tempo stimato:** 4-6 ore
**Priorità:** MEDIA

#### Obiettivi
1. **Rimuovere Hardcoded Credentials**
   - Spostare da codice a database settings
   - UI per configurazione XML credentials
   - Security: non esporre credentials in source

2. **Migliorare UI/UX Dashboard**
   - Real-time progress bar durante import
   - Live statistics (properties inserted/updated)
   - Queue status visualizzazione
   - Log viewer integrato (evitare FTP per debug)

3. **Import Management**
   - History import recenti
   - Possibilità di cancellare import di test
   - Re-run import falliti
   - Pause/resume import in corso (futuro)

#### Componenti da Creare
1. **Settings Tab "XML Credentials"**
   - Form per XML URL, username, password
   - Test connection button
   - Credential source selector (DB vs hardcoded)

2. **Dashboard Tab "Import Status"**
   - Real-time progress (AJAX polling)
   - Visual progress bar
   - Statistics cards (insert/update/skip/errors)
   - Queue visualization

3. **Dashboard Tab "Import History"**
   - Lista ultimi 10 import
   - Filtri: success/failed/test
   - Actions: view details, delete, re-run

4. **Log Viewer Tab**
   - Filtro per session_id
   - Filtro per level (error/warning/info)
   - Search in logs
   - Download log file

#### Security Fix Inclusi
- Rimozione credentials da `admin/class-realestate-sync-admin.php:716-721`
- Storage encrypted in database (optional)
- Permissions check per accesso credentials

#### File Coinvolti
- `admin/class-realestate-sync-admin.php`
- `admin/views/dashboard-*.php` (refactor)
- `admin/views/settings-credentials.php` (nuovo)
- `admin/assets/admin.js` (AJAX per real-time updates)

---

### **Priorità 4: Internazionalizzazione (i18n)** ⭐⭐

**Status:** 📋 Pianificato
**Branch:** `feature/i18n-loco-translate`
**Versione target:** v2.0.0
**Tempo stimato:** 2-3 ore
**Priorità:** BASSA

#### Obiettivo
Rendere plugin completamente traducibile con Loco Translate

#### Requisiti
1. **Text Domain Consistency**
   - Verificare che tutti i testi usino `realestate-sync`
   - Rimuovere stringhe hardcoded

2. **POT File Generation**
   - Generare file template traduzioni
   - Includere tutti i testi UI

3. **Loco Translate Compatibility**
   - Testare con plugin Loco Translate
   - Verificare che tutte le stringhe siano traducibili

4. **Lingue Iniziali**
   - Italiano (IT_it) - lingua principale
   - Inglese (EN_us) - fallback

#### Scope
- Admin interface strings
- Error messages
- Email notifications
- Dashboard UI
- Settings labels

#### File da Verificare
- Tutti i file in `admin/views/*.php`
- `admin/class-realestate-sync-admin.php`
- `includes/*.php` (messaggi utente)
- File JavaScript con stringhe (`admin/assets/*.js`)

---

## 🔄 WORKFLOW IMPLEMENTAZIONE

### Per Ogni Feature

```bash
# 1. Partire da main pulito
git checkout main
git pull origin main

# 2. Creare branch feature
git checkout -b feature/nome-feature

# 3. Implementare + commit incrementali
git add ...
git commit -m "feat: ..."

# 4. Testing locale/staging
# ... test ...

# 5. Merge su main
git checkout main
git merge feature/nome-feature

# 6. Tag versione
git tag v1.X.0-descrizione
git push origin main --tags

# 7. Deploy su produzione
# ... upload via FTP/Git ...

# 8. Monitoring 24-48h
# ... verifica log e stabilità ...

# 9. Solo dopo stabilità → prossima feature
```

---

## 📅 TIMELINE STIMATA

### Scenario Ideale (full-time)

```
Week 1:
- Cleanup merge + deploy + test (FATTO ✅)
- Queue Optimization implementation
- Queue Optimization test + deploy

Week 2:
- Email Notifications implementation
- Email Notifications test + deploy
- Dashboard UX planning

Week 3-4:
- Dashboard UX implementation
- Dashboard UX test + deploy
- i18n implementation
- i18n test + deploy
```

### Scenario Realistico (part-time)

```
Mese 1:
- Cleanup (FATTO ✅)
- Queue Optimization (v1.7.0)

Mese 2:
- Email Notifications (v1.8.0)

Mese 3:
- Dashboard UX Refactoring (v1.9.0)

Mese 4:
- i18n Loco Translate (v2.0.0)
```

---

## 🎯 OBIETTIVI VERSIONI

### v1.6.1-cleanup (CORRENTE)
- ✅ Codebase pulito
- ✅ Security token system
- ✅ Documentazione aggiornata

### v1.7.0-queue-optimization
- 🎯 Import 72% più veloce
- 🎯 Queue ottimizzata
- 🎯 Pre-filtering hash-based

### v1.8.0-email-notifications
- 🎯 Monitoring automatico
- 🎯 Alert email
- 🎯 Report statistiche

### v1.9.0-dashboard-refactor
- 🎯 UX migliorata
- 🎯 Real-time progress
- 🎯 Credentials da DB

### v2.0.0-production-ready
- 🎯 Plugin completamente traducibile
- 🎯 Tutte le feature implementate
- 🎯 Production-grade quality

---

## 📊 METRICHE DI SUCCESSO

### Queue Optimization
- ✅ Import time < 2.5 ore (attuale: 7h)
- ✅ Items in queue ↓70%+
- ✅ Zero regressioni

### Email Notifications
- ✅ Email delivery 100%
- ✅ Configurazione UI funzionante
- ✅ No spam (solo eventi rilevanti)

### Dashboard Refactoring
- ✅ Credentials rimossi da source
- ✅ Real-time updates funzionanti
- ✅ UI responsive e chiara

### i18n
- ✅ 100% stringhe traducibili
- ✅ Loco Translate compatibilità
- ✅ IT + EN traduzioni complete

---

## 🚀 FUTURE ENHANCEMENTS (Post v2.0)

### Advanced Features (backlog)

1. **CLI Runner** (da review architettura cron)
   - Bypass HTTP timeout
   - PHP CLI execution
   - Più robusto per processi lunghi

2. **Incremental Sync**
   - Sync solo properties modificate ultimamente
   - Filtro per data modifica
   - Scheduling intelligente

3. **Multi-Source Support**
   - Supporto multipli feed XML
   - Merge properties da diverse fonti
   - Conflict resolution

4. **Advanced Filtering**
   - Filtri custom per property type
   - Price range filtering
   - Geographic area filtering
   - Category blacklist/whitelist

5. **Performance Monitoring**
   - Dashboard performance metrics
   - Slow query detection
   - Resource usage tracking

6. **Automated Testing**
   - PHPUnit test suite
   - Integration tests
   - CI/CD pipeline

---

## 📝 NOTES

### Decisioni Architetturali
- ✅ Mantenere batch system (robusto e testato)
- ✅ Separare cleanup da nuove feature (risk management)
- ✅ Testing incrementale (feature per feature)
- ✅ Documentazione completa per ogni feature

### Rischi Identificati
- ⚠️ Queue optimization: dipendenza tracking table
- ⚠️ Email system: spam filter, deliverability
- ⚠️ Dashboard refactoring: complessità UI
- ⚠️ i18n: stringhe JavaScript più complesse

### Mitigazioni
- ✅ Fallback graceful per tracking errors
- ✅ Test email delivery prima del deploy
- ✅ UI incrementale (un pezzo alla volta)
- ✅ wp_localize_script per stringhe JS

---

**Maintainer:** Andrea Cianni - Novacom
**Last Update:** 06/12/2025
**Status:** Active Development
