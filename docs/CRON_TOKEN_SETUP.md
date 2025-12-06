# Configurazione e Test del Cron Token

**Data:** 06 Dicembre 2025
**Feature:** Token Security per batch-continuation.php
**Versione Plugin:** 1.6.1+

---

## 📋 Sommario

Il sistema di batch processing usa un token di sicurezza per proteggere l'endpoint `batch-continuation.php` da accessi non autorizzati. Questo documento spiega come configurare e testare il token.

---

## 🔐 Configurazione Token (3 Opzioni)

Il sistema supporta **3 metodi di configurazione** con questo ordine di priorità:

### **Opzione 1: Costante in wp-config.php** (⭐ RACCOMANDATO)

Aggiungi questa riga in `wp-config.php` (prima di `/* That's all, stop editing! */`):

```php
// RealEstate Sync - Cron Token Security
define('REALESTATE_SYNC_CRON_TOKEN', 'tuo-token-super-segreto-qui');
```

**Vantaggi:**
- ✅ Non committato in Git (wp-config.php è in .gitignore)
- ✅ Facile da configurare
- ✅ Priorità massima (override di tutto)

---

### **Opzione 2: Environment Variable**

Configura la variabile d'ambiente nel server web.

**Per Apache (.htaccess o httpd.conf):**
```apache
SetEnv REALESTATE_SYNC_CRON_TOKEN "tuo-token-super-segreto-qui"
```

**Per Nginx (nginx.conf):**
```nginx
fastcgi_param REALESTATE_SYNC_CRON_TOKEN "tuo-token-super-segreto-qui";
```

**Per cPanel (PHP INI Editor):**
```ini
; Non supportato direttamente - usa Opzione 1 o 3
```

**Vantaggi:**
- ✅ Separazione completa dal codice
- ✅ Ideale per deployment automatici
- ⚠️ Richiede accesso alla configurazione server

---

### **Opzione 3: Fallback (Token di Default)**

Se nessuna delle opzioni sopra è configurata, il sistema usa il token di default:
```
TrentinoImmo2025Secret!
```

**⚠️ ATTENZIONE:**
- Questo è il token attuale già in uso
- Funziona ma non è sicuro a lungo termine
- Dovresti configurare Opzione 1 o 2 prima di andare in produzione

---

## 🧪 Come Testare il Token

### **Test 1: Token Corretto (deve funzionare)**

```bash
# Sostituisci YOUR_TOKEN con il token configurato
wget -q -O - "https://trentinoimmobiliare.it/wp-content/plugins/realestate-sync-plugin/batch-continuation.php?token=YOUR_TOKEN"
```

**Risultato atteso:**
```
OK - No pending work
# oppure
OK - Batch processed, more pending
```

**Verifica nel log:**
```bash
tail -f /path/to/wp-content/debug.log
```

Dovresti vedere:
```
[BATCH-CONTINUATION] ========== Cron check started ==========
```

---

### **Test 2: Token Errato (deve fallire)**

```bash
wget -q -O - "https://trentinoimmobiliare.it/wp-content/plugins/realestate-sync-plugin/batch-continuation.php?token=token-sbagliato"
```

**Risultato atteso:**
```
Forbidden
```

**Verifica nel log:**
```
[BATCH-CONTINUATION] ❌ Unauthorized access attempt from xxx.xxx.xxx.xxx
```

---

### **Test 3: Nessun Token (deve fallire)**

```bash
wget -q -O - "https://trentinoimmobiliare.it/wp-content/plugins/realestate-sync-plugin/batch-continuation.php"
```

**Risultato atteso:**
```
Forbidden
```

---

### **Test 4: Verifica Configurazione Attiva**

Controlla quale metodo di configurazione è attivo:

```bash
tail -f /path/to/wp-content/debug.log | grep "WARNING: Using default token"
```

**Se vedi il warning:**
- ⚠️ Stai usando il token di default
- Dovresti configurare Opzione 1 o 2

**Se NON vedi il warning:**
- ✅ Hai configurato correttamente il token via constant o env variable

---

## 🔄 Aggiornamento Cron Server

Dopo aver configurato il nuovo token, aggiorna il comando cron sul server:

**PRIMA (token hardcoded):**
```bash
* * * * * wget -q -O - "https://trentinoimmobiliare.it/wp-content/plugins/realestate-sync-plugin/batch-continuation.php?token=TrentinoImmo2025Secret!" >/dev/null 2>&1
```

**DOPO (token configurato):**
```bash
* * * * * wget -q -O - "https://trentinoimmobiliare.it/wp-content/plugins/realestate-sync-plugin/batch-continuation.php?token=IL_TUO_NUOVO_TOKEN" >/dev/null 2>&1
```

---

## 📝 Generare un Token Sicuro

Per generare un token casuale sicuro:

**Linux/Mac:**
```bash
openssl rand -base64 32
```

**PowerShell:**
```powershell
[Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Minimum 0 -Maximum 256 }))
```

**Online (se necessario):**
https://www.random.org/strings/

Parametri suggeriti:
- Lunghezza: 32+ caratteri
- Caratteri: Alfanumerici + simboli
- Evitare spazi e caratteri speciali URL (? & = %)

---

## 🚨 Troubleshooting

### Problema: "Forbidden" anche con token corretto

**Causa possibile 1:** Token configurato in modo errato

**Soluzione:**
1. Verifica che il token in wp-config.php non abbia spazi extra
2. Controlla che il token nel cron command sia identico
3. Riavvia il web server (se usi environment variables)

**Causa possibile 2:** Cache o CDN

**Soluzione:**
1. Flush cache di WordPress
2. Bypass CDN (se presente) per il path del plugin
3. Verifica che PHP stia leggendo il file wp-config.php corretto

---

### Problema: Warning "Using default token" sempre presente

**Causa:** La costante non è definita correttamente

**Soluzione:**
1. Verifica che `define('REALESTATE_SYNC_CRON_TOKEN', ...)` sia in wp-config.php
2. Verifica che sia PRIMA della riga `/* That's all, stop editing! */`
3. Riavvia PHP-FPM se usi server con opcache

---

### Problema: Cron non esegue più

**Causa:** Token nel cron command non aggiornato

**Soluzione:**
1. Aggiorna il comando cron con il nuovo token
2. Verifica che il cron job sia attivo: `crontab -l` (Linux) o cPanel Cron Jobs
3. Testa manualmente con wget (vedi Test 1)

---

## ✅ Checklist di Deployment

Prima di deployare in produzione:

- [ ] Generato token casuale sicuro (32+ caratteri)
- [ ] Configurato token in wp-config.php (Opzione 1) **OPPURE** environment variable (Opzione 2)
- [ ] Testato endpoint con token corretto (Test 1) ✅
- [ ] Testato endpoint con token errato (Test 2) ❌
- [ ] Aggiornato comando cron sul server con nuovo token
- [ ] Verificato che warning "default token" NON appaia più nel log
- [ ] Documentato token in password manager o vault aziendale
- [ ] Testato esecuzione cron automatica (attendere 1 minuto)

---

## 📚 Reference

- **File modificato:** `batch-continuation.php`
- **Versione:** 1.6.1+
- **Review architetturale:** `docs/review_architettura_cron.md` (Gemini folder)
- **Priorità sicurezza:** ⭐⭐⭐⭐⭐ (Alta)

---

**Autore:** Claude Code
**Data creazione:** 06/12/2025
**Ultimo aggiornamento:** 06/12/2025
