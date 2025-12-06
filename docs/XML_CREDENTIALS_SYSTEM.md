# 🔐 Sistema Gestione Credenziali XML

**Data Implementazione:** 2025-12-04
**Status:** ✅ DEPLOYATO IN PRODUZIONE
**Priorità:** MEDIUM

---

## 📋 PANORAMICA

Sistema che permette di gestire le credenziali per il download XML tramite database (wp_options) invece di hardcoded nel codice, con toggle per passare facilmente tra i due sistemi.

---

## 🎯 PROBLEMA RISOLTO

**Prima:**
- Credenziali XML hardcoded nel codice (URL, username, password)
- Impossibile cambiarle senza modificare il codice
- Cliente non può gestirle autonomamente

**Dopo:**
- Credenziali salvate in wp_options (plain text, come wp-config.php)
- Toggle nel dashboard: "Usa credenziali hardcoded" / "Usa credenziali database"
- Cliente può modificarle tramite interfaccia admin
- Transizione graduale: sistema attuale (hardcoded) continua a funzionare

---

## 🏗️ ARCHITETTURA

### Database (wp_options):

```
realestate_sync_credential_source = 'hardcoded' | 'database'
realestate_sync_xml_url = 'https://www.gestionaleimmobiliare.it/...'
realestate_sync_xml_user = 'username'
realestate_sync_xml_pass = 'password' (plain text)
```

### File Modificati:

**1. `admin/views/dashboard.php`**
- Sezione "Credenziali Download XML" nel Configuration Panel
- 3 campi: XML URL, Username, Password (readonly di default)
- Toggle radio: Hardcoded / Database
- Pulsante "Modifica Credenziali" → Edit mode
- Pulsante "Test Connessione XML" (usa le credenziali selezionate)

**2. `admin/class-realestate-sync-admin.php`**
- Nuovo handler: `handle_save_credential_source()` - Salva toggle hardcoded/database
- Nuovo handler: `handle_save_xml_credentials()` - Salva URL/user/pass
- Modificato: `handle_test_connection()` - Usa credenziali in base al toggle
- Modificato: `handle_manual_import()` - Usa credenziali in base al toggle

---

## 🔧 COME FUNZIONA

### Stato Default (Attuale):

```php
// wp_options
realestate_sync_credential_source = 'hardcoded' (default)

// Il sistema usa:
$url = 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz';
$username = 'trentinoimmobiliare_it';
$password = 'dget6g52';
```

✅ **Import notturno continua a funzionare normalmente**

### Passaggio a Database:

1. **Inserisci credenziali:**
   - Clicca "Modifica Credenziali"
   - Compila i 3 campi
   - Clicca "Salva Credenziali"
   - ✅ Salvate in wp_options (plain text)

2. **Attiva nuovo sistema:**
   - Seleziona radio "Usa credenziali database"
   - ✅ Auto-salva credential_source = 'database'

3. **Testa:**
   - Clicca "Test Connessione XML"
   - ✅ Usa le credenziali dal database

4. **Importa:**
   - Clicca "Avvia Import Manuale"
   - ✅ Usa le credenziali dal database

---

## 📝 TESTING WORKFLOW

### Test 1: Sistema Attuale (Hardcoded)

```
1. Vai su: https://trentinoimmobiliare.it/wp-admin
2. Apri: RealEstate Sync → Dashboard
3. Scorri a: "Credenziali Download XML"
4. Verifica: Radio "Usa credenziali hardcoded" selezionato
5. Clicca: "Test Connessione XML"
6. Risultato atteso: ✅ Connessione OK (usa credenziali hardcoded)
```

### Test 2: Nuovo Sistema (Database)

```
1. Clicca: "Modifica Credenziali"
2. Compila:
   - XML URL: https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz
   - Username: trentinoimmobiliare_it
   - Password: dget6g52
3. Clicca: "Salva Credenziali"
4. Risultato: ✅ Alert "Credenziali XML salvate con successo!"
5. Cambia radio: "Usa credenziali database"
6. Risultato: ✅ Alert "Sorgente credenziali aggiornata: Database"
7. Clicca: "Test Connessione XML"
8. Risultato atteso: ✅ Connessione OK (usa credenziali database)
```

### Test 3: Import Manuale

```
1. Con radio su "Usa credenziali database"
2. Clicca: "Avvia Import Manuale"
3. Verifica log: "Using XML credentials from database for import"
4. Risultato atteso: ✅ Import parte correttamente
```

### Test 4: Cambio Credenziali

```
1. Modifica uno dei campi (es. URL con typo intenzionale)
2. Salva
3. Test Connessione
4. Risultato atteso: ❌ Errore connessione
5. Ripristina valore corretto
6. Salva
7. Test Connessione
8. Risultato atteso: ✅ Connessione OK
```

---

## 🚀 QUANDO A REGIME

Quando il cliente usa esclusivamente le credenziali database e tutto è stabile:

### Step 1: Rimuovere Toggle

**File:** `admin/views/dashboard.php`

Rimuovere:
```html
<!-- Credential Source Toggle -->
<div style="margin-bottom: 20px; padding: 15px; background: #fff8e1;">
    <!-- ... tutto il blocco radio ... -->
</div>
```

### Step 2: Rimuovere Credenziali Hardcoded

**File:** `admin/class-realestate-sync-admin.php`

In `handle_test_connection()` - Rimuovere:
```php
} else {
    // Use hardcoded credentials
    $url = 'https://www.gestionaleimmobiliare.it/...';
    $username = 'trentinoimmobiliare_it';
    $password = 'dget6g52';

    $this->logger->log('Using hardcoded XML credentials', 'info');
}
```

In `handle_manual_import()` - Rimuovere:
```php
} else {
    // Use hardcoded credentials
    $settings = array(
        'xml_url' => 'https://www.gestionaleimmobiliare.it/...',
        'username' => 'trentinoimmobiliare_it',
        'password' => 'dget6g52'
    );

    $this->logger->log('Using hardcoded XML credentials for import', 'info');
}
```

### Step 3: Cambiare Default

**File:** `admin/class-realestate-sync-admin.php`

In entrambe le funzioni, cambiare:
```php
$credential_source = get_option('realestate_sync_credential_source', 'hardcoded');

if ($credential_source === 'database') {
    // ... solo questo blocco rimane ...
}
```

A:
```php
// Always use database credentials
$settings = array(
    'xml_url' => get_option('realestate_sync_xml_url', ''),
    'username' => get_option('realestate_sync_xml_user', ''),
    'password' => get_option('realestate_sync_xml_pass', '')
);

if (empty($settings['xml_url']) || empty($settings['username']) || empty($settings['password'])) {
    throw new Exception('Credenziali XML non configurate');
}
```

---

## 🔒 SICUREZZA

### Plain Text Storage

**Domanda:** È sicuro salvare password in plain text?

**Risposta:**
- WordPress salva credenziali database in plain text in `wp-config.php`
- Tutti i siti WordPress funzionano così
- Le credenziali sono in database accessibile solo da WordPress
- Protezione a livello filesystem/database, non a livello applicazione

### Alternative Scartate

1. **Encryption:** Troppi problemi (tentato precedentemente)
2. **File system locale:** Non adatto perché plugin usato dal cliente
3. **Variabili ambiente:** Non supportato standard WordPress

---

## 📊 VANTAGGI

✅ **Cliente autonomo:** Può cambiare credenziali senza toccare codice
✅ **Transizione graduale:** Sistema attuale continua a funzionare
✅ **Testabile:** Può testare nuove credenziali prima di attivare
✅ **Rollback facile:** Cambia radio e torna a hardcoded
✅ **Nessun rischio:** Import notturno non è impattato durante test

---

## 📁 FILE CORRELATI

- `admin/views/dashboard.php` - UI Form + Toggle + JavaScript
- `admin/class-realestate-sync-admin.php` - Backend AJAX handlers
- `deploy-xml-credentials.ps1` - Script deployment

---

## 🐛 TROUBLESHOOTING

### Problema: "Credenziali database non configurate"

**Causa:** Cambiato toggle a "database" senza salvare credenziali

**Soluzione:**
1. Torna a "Usa credenziali hardcoded"
2. Clicca "Modifica Credenziali"
3. Compila tutti i campi
4. Salva
5. Poi cambia toggle a "database"

### Problema: Test connessione fallisce con credenziali database

**Causa:** Credenziali errate o URL sbagliato

**Soluzione:**
1. Verifica URL completo (copia/incolla da hardcoded se necessario)
2. Verifica username/password esatti
3. Test connessione
4. Controlla log: `/wp-content/debug.log`

### Problema: Import usa credenziali sbagliate

**Causa:** Toggle non salvato correttamente

**Soluzione:**
1. Ricarica pagina dashboard
2. Verifica quale radio è selezionato
3. Cambia se necessario (auto-salva)
4. Riprova import

---

## 📅 TIMELINE

**2025-12-04:**
- ✅ Implementazione completata
- ✅ Deployato in produzione
- ⏳ Testing da parte utente

**Prossimi Step:**
- [ ] Utente testa sistema hardcoded (conferma funziona)
- [ ] Utente inserisce credenziali database
- [ ] Utente testa sistema database
- [ ] Quando stabile: rimuovere toggle e hardcoded

---

**Documento Creato:** 2025-12-04
**Autore:** Claude Code Assistant
**Versione:** 1.0
