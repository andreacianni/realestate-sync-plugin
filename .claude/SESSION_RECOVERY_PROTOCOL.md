# 🤖 Claude Code - Session Recovery Protocol

## ⚡ RECOVERY RAPIDO - 2025-10-13 MATTINA

**UTENTE DIRÀ**: "riprendiamo" o "continuiamo"

**RISPOSTA IMMEDIATA**:
```
Ho letto SESSION_STATUS.md. Siamo pronti per testare la REST API WpResidence!

📊 Situazione:
- ✅ Analisi API completata (endpoint, parametri, JWT tutto identificato)
- ✅ 2 plugin JWT già installati
- ✅ Funzione cruciale wpestate_upload_images_dashboard() trovata
- 🎯 PROSSIMO STEP: Avviare XAMPP e test Postman

Vuoi che avvii XAMPP e proceda con i test API?
```

**FILE DA LEGGERE**: `SESSION_STATUS.md` linee 907-1216 (sezione API Analysis)

---

## 📋 ISTRUZIONI PER CLAUDE

Quando l'utente inizia una nuova sessione, **PRIMA DI FARE QUALSIASI ALTRA COSA**, segui questo protocollo:

### 1. Controlla se esiste un SESSION_STATUS.md attivo
```bash
# Cerca il file nella root del progetto
SESSION_STATUS.md
```

### 2. Se il file esiste, LEGGILO IMMEDIATAMENTE
- Non aspettare che l'utente lo chieda
- Leggi il file per intero, SPECIALMENTE:
  - ✅ Ultima sezione (linee 907-1216): "✅ ANALISI REST API COMPLETATA"
  - ✅ Status finale: "📋 PRONTO PER TEST POSTMAN"
- Carica il contesto delle attività in corso

### 3. Saluta l'utente con un riepilogo SPECIFICO
```
"Ho letto SESSION_STATUS.md. Siamo pronti per testare la REST API WpResidence!

Situazione:
- ✅ Analisi API completata (6 tentativi meta fields falliti)
- ✅ Endpoint identificato: POST /wpresidence/v1/property/add
- ✅ JWT plugins già installati
- ✅ Scoperta cruciale: wpestate_upload_images_dashboard()
- 🎯 PROSSIMO STEP: Test Postman

Vuoi che avvii XAMPP e proceda?"
```

### 4. Durante la sessione - Aggiorna periodicamente
Ogni volta che completi un task importante:
1. Aggiorna SESSION_STATUS.md con:
   - ✅ Task completati
   - 🔍 Scoperte chiave
   - ⏳ Task in corso
   - 📝 Note tecniche rilevanti

### 5. Prima di terminare la sessione
Se l'utente dice "chiudo" o "ci vediamo dopo":
1. Chiedi: "Vuoi che aggiorni SESSION_STATUS.md prima di chiudere?"
2. Se sì, fai un update completo dello status

---

## 🎯 CONTESTO SPECIFICO - SESSIONE 2025-10-13

### Problema da Risolvere
Gallery e Agency Sidebar NON appaiono automaticamente nel frontend dopo import.

### Tentativi Falliti (6 volte)
1. ❌ Meta fields diretti
2. ❌ Trigger save_post manuale
3. ❌ Simulazione $_POST
4. ❌ Backup/restore gallery (Solution C)
5. ❌ property_images() + array
6. ❌ property_images() + stringa comma-separated

**Risultato**: Meta fields 100% corretti ma frontend SEMPRE vuoto.

### Soluzione Scelta: REST API WpResidence
**Status**: ✅ Analisi completata, pronto per test

**Endpoint**: `POST /wp-json/wpresidence/v1/property/add`

**JWT Auth**: 2 plugin già installati
- `jwt-auth`
- `jwt-authentication-for-wp-rest-api`

**Scoperta Cruciale**: Funzione `wpestate_upload_images_dashboard()` trovata - è quella che l'API usa internamente!

### Prossimi Step (ESATTI)
1. Avviare XAMPP (Apache + MySQL)
2. Test JWT token via Postman/curl
3. Test property creation con 2-3 immagini
4. Verificare frontend (gallery e sidebar appaiono?)
5. Se OK → Adattare plugin per usare API
6. Se FAIL → Contattare supporto WpResidence

---

## 📂 File da Controllare All'Avvio

### 1. SESSION_STATUS.md (PRIORITARIO)
**Path**: `C:\Users\Andrea\OneDrive\Lavori\novacom\Trentino-immobiliare\realestate-sync-plugin\SESSION_STATUS.md`

**Sezione cruciale**: Linee 907-1216 ("✅ ANALISI REST API COMPLETATA")

Contiene:
- Storia completa 6 tentativi falliti
- Analisi codice sorgente WpResidence
- Parametri API completi
- Esempi curl pronti
- Scoperta funzione wpestate_upload_images_dashboard()

### 2. DEBUG_CHANGES_LOG.md
Changelog delle modifiche al sistema di debug.

### 4. Git Status
```bash
git status --short
```
Per vedere file modificati non committati.

### 5. Git Log Recente
```bash
git log --oneline -5
```
Per capire gli ultimi commit.

---

## 🎯 Comportamento Proattivo

### Quando l'utente dice:
- **"riprendiamo"** → Rispondi con recovery message (vedi sopra) + chiedi se avviare XAMPP
- **"dove eravamo rimasti?"** → Leggi SESSION_STATUS.md linee 907-1216 e riassumi
- **"recuperiamo la chat di ieri?"** → Spiega che non puoi, ma hai letto SESSION_STATUS.md
- **"cosa stavamo facendo?"** → Spiega: "Testare REST API dopo 6 tentativi falliti con meta fields"
- **"continua"** → Procedi con Step 1: avviare XAMPP

### 🚨 ERRORI DA EVITARE
1. ❌ **NON proporre ancora meta fields** (già provato 6 volte, tutti falliti!)
2. ❌ **NON rileggere codice plugin** (già analizzato, tutto in SESSION_STATUS.md)
3. ❌ **NON modificare plugin** prima di testare API manualmente
4. ❌ **NON dire "proviamo ancora..."** (basta tentativi, ora SOLO API)

### ✅ FARE INVECE
1. ✅ Leggere SESSION_STATUS.md linee 907-1216
2. ✅ Procedere con test API Postman/curl
3. ✅ Se API funziona → modificare plugin
4. ✅ Se API NON funziona → contattare supporto

### Auto-checkpoint
Ogni 5-10 scambi significativi, chiedi:
"Vuoi che faccia un checkpoint del nostro lavoro in SESSION_STATUS.md?"

---

## 📝 Template per SESSION_STATUS.md

```markdown
# Session Status - [DATA]

## 🎯 Obiettivo Corrente
[Descrizione problema/feature in corso]

## ✅ Completato
- [Lista task completati]

## 🔍 Scoperte Chiave
- [Informazioni importanti emerse]

## ⏳ In Corso
- [Task attualmente in lavorazione]

## 📋 Prossimi Step
1. [Prossima azione da fare]
2. [Seconda azione]
3. [Terza azione]

## 🗂️ File Modificati
```
[git status output]
```

## 📍 Riferimenti Utili
- File: [path]
- Query SQL: [se rilevante]
- Commit: [hash]

## 💡 Note Tecniche
[Dettagli tecnici rilevanti per riprendere il lavoro]

## ⚠️ Attenzioni/Avvertenze
[Cose da ricordare/evitare]
```

---

## 🚨 IMPORTANTE

1. **NON aspettare** che l'utente chieda di leggere SESSION_STATUS.md
2. **LEGGI SEMPRE** all'inizio di ogni sessione
3. **AGGIORNA FREQUENTEMENTE** durante il lavoro
4. **USA TodoWrite** tool per task tracking in tempo reale
5. **COMMITTA** spesso su git per non perdere modifiche

---

## 📊 Metriche di Successo

Una buona session recovery significa:
- ✅ L'utente non deve rispiegare il contesto
- ✅ Posso continuare esattamente da dove si era fermato
- ✅ Nessuna perdita di informazioni chiave
- ✅ Continuità del lavoro tra sessioni

---

## 🔄 Workflow Ideale

```
1. Utente apre VSCode
2. Claude legge SESSION_STATUS.md automaticamente
3. Claude saluta con riepilogo: "Eravamo qui: [...]"
4. Lavoro continua
5. Claude aggiorna SESSION_STATUS.md periodicamente
6. Utente chiude VSCode
7. SESSION_STATUS.md è aggiornato e pronto per la prossima sessione
```

---

## 📋 ESEMPIO COMPLETO API CALL (Copia-incolla ready)

### Step 1: Get JWT Token
```bash
curl -X POST "http://localhost/trentino-wp/wp-json/jwt-auth/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin"}'
```

**Response atteso**: `{"token": "eyJ...xyz", "user_id": 1}`

### Step 2: Create Test Property
```bash
curl -X POST "http://localhost/trentino-wp/wp-json/wpresidence/v1/property/add" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {INSERISCI_TOKEN_QUI}" \
  -d '{
    "title": "Test Property API 2025-10-13",
    "property_description": "Testing gallery and agency sidebar via REST API",
    "property_price": 450000,
    "property_bedrooms": 3,
    "property_bathrooms": 2,
    "property_size": 150,
    "property_address": "Via Test 123, Trento",
    "property_city": ["trento"],
    "images": [
      {"id": "img1", "url": "https://picsum.photos/800/600?random=1"},
      {"id": "img2", "url": "https://picsum.photos/800/600?random=2"},
      {"id": "img3", "url": "https://picsum.photos/800/600?random=3"}
    ]
  }'
```

**⚠️ NOTA**: Images devono essere HTTPS URLs pubblici! Usare picsum.photos per test.

**Response atteso**: `{"status": "success", "property_id": 5201}`

### Step 3: Verificare Frontend
1. Aprire: `http://localhost/trentino-wp/?p=5201` (o property_id ritornato)
2. ✅ Verificare gallery visibile
3. ✅ Verificare agency sidebar visibile

---

## 🔧 FILE CHIAVE DEL PROGETTO

### Plugin (Conservare)
```
includes/
├── class-realestate-sync-wp-importer.php     ← Da modificare (solo layer finale)
├── class-realestate-sync-property-mapper.php ← Mantenere (mapping v3.1)
├── class-realestate-sync-data-converter.php  ← Mantenere (conversione v3.0)
├── class-realestate-sync-agency-manager.php  ← Mantenere
└── class-realestate-sync-image-importer.php  ← Mantenere (download images)
```

### WpResidence Source Code Analizzato
```
wp-content/plugins/wpresidence-core/api/rest/properties/
├── properties_routes.php          ← Endpoint registration
├── property_create.php            ← Create logic (linee 86-152)
├── properties_functions.php       ← Request parsing
└── property_update.php            ← Update logic (da analizzare)

wp-content/themes/wpresidence/libs/dashboard_functions/
└── dashboard_functions.php:1204   ← wpestate_upload_images_dashboard()
```

---

## 🎓 LEZIONI APPRESE (Non ripetere!)

### ❌ Cosa NON Funziona
1. Scrivere `wpestate_property_gallery` direttamente (anche se formato corretto)
2. Chiamare `do_action('save_post')` manualmente
3. Simulare `$_POST['image_to_attach']`
4. Backup/restore dopo hook
5. Usare `property_images()` WP All Import Add-On
6. Qualsiasi combinazione di quanto sopra

### ✅ Unica Soluzione
REST API ufficiale che chiama internamente `wpestate_upload_images_dashboard()` e attiva tutti i meccanismi corretti.

---

## 💾 CODICE ESISTENTE DA CONSERVARE

**NON TOCCARE** questi componenti (funzionano perfettamente):
- ✅ XML Parser
- ✅ Data Converter v3.0
- ✅ Property Mapper v3.1
- ✅ Agency Manager
- ✅ Image Importer (download URLs)
- ✅ Tracking system

**MODIFICARE SOLO**: Layer finale in `WP_Importer::process_property_v3()` per chiamare API.

---

**RICORDA**: Il tuo obiettivo è dare continuità tra le sessioni come se fosse una conversazione unica.

**PRIORITÀ ASSOLUTA**: Leggere SESSION_STATUS.md linee 907-1216 all'avvio della sessione!
