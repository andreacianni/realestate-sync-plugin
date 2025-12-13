# 📡 Guida Accesso SSH al Server

**Server:** trentinoimmobiliare.it
**Data creazione:** 2025-12-08

---

## 🔑 Prerequisiti

- Git Bash installato (Windows) o Terminal (Mac/Linux)
- Chiave SSH configurata in `.ssh-config/`

---

## 📋 Step 1: Aprire Git Bash

**Windows:**
1. Cerca "Git Bash" nel menu Start
2. Apri come utente normale (NON administrator)

**Mac/Linux:**
1. Apri Terminal (Cmd+Space → "Terminal")

---

## 📋 Step 2: Navigare alla Cartella del Progetto

```bash
# Naviga alla cartella del plugin
cd "C:/Users/Andrea/OneDrive/Lavori/novacom/Trentino-immobiliare/realestate-sync-plugin"

# Verifica di essere nella cartella corretta
pwd
# Output atteso: /c/Users/Andrea/OneDrive/Lavori/novacom/Trentino-immobiliare/realestate-sync-plugin
```

---

## 📋 Step 3: Verificare Configurazione SSH

```bash
# Controlla se esiste la cartella .ssh-config
ls -la .ssh-config/

# Dovresti vedere:
# - config (file di configurazione)
# - id_rsa o altra chiave privata
# - id_rsa.pub o altra chiave pubblica
```

---

## 📋 Step 4: Connettersi al Server

### Opzione A: Se la configurazione SSH è già pronta

```bash
# Connessione diretta (se configurato in .ssh-config/config)
ssh trentinoimmobiliare

# O con host completo
ssh wp@trentinoimmobiliare.it
```

### Opzione B: Se devi specificare la chiave manualmente

```bash
# Specifica percorso chiave
ssh -i .ssh-config/id_rsa wp@trentinoimmobiliare.it

# Se chiede password, inseriscila
```

### Opzione C: Connessione con porta specifica

```bash
# Se il server usa porta diversa da 22
ssh -i .ssh-config/id_rsa -p 22 wp@trentinoimmobiliare.it
```

---

## 📋 Step 5: Navigare alla Cartella WordPress

```bash
# Una volta connesso, naviga alla cartella del plugin
cd public_html/wp-content/plugins/realestate-sync-plugin

# Verifica di essere nella cartella corretta
pwd
# Output atteso: /home/wp/public_html/wp-content/plugins/realestate-sync-plugin

# Elenca file per conferma
ls -la
# Dovresti vedere: includes/, admin/, realestate-sync-plugin.php, ecc.
```

---

## 📋 Step 6: Verificare WP-CLI

```bash
# Verifica che wp-cli sia installato
wp --info

# Se non installato, chiedi all'hosting provider
```

---

## 🗑️ OPERAZIONI COMUNI

### A) Cancellare Tutti i Dati Importati

```bash
# DRY RUN (simulazione, nessuna modifica)
wp eval-file includes/scripts/delete-all-imported-data.php --dry-run

# ESECUZIONE REALE (cancella tutto!)
wp eval-file includes/scripts/delete-all-imported-data.php --confirm
```

### B) Analizzare Dati Corrotti

```bash
# Analisi senza modifiche
wp eval-file includes/scripts/analyze-corrupted-data.php
```

### C) Fixare Hash Corrotti

```bash
# DRY RUN (simulazione)
wp eval-file includes/scripts/fix-corrupted-hashes.php --dry-run

# ESECUZIONE REALE
wp eval-file includes/scripts/fix-corrupted-hashes.php
```

### D) Controllare Stato Database

```bash
# Conta properties
wp post list --post_type=estate_property --format=count

# Conta agencies
wp post list --post_type=estate_agency --format=count

# Vedi ultimi 10 post
wp post list --post_type=estate_property --posts_per_page=10 --format=table
```

### E) Vedere Log di Import

```bash
# Log WordPress
tail -f public_html/wp-content/debug.log

# O log errori server
tail -f ~/logs/error_log

# Ctrl+C per uscire dal tail
```

---

## 🚪 Disconnessione

```bash
# Per uscire da SSH
exit

# O premi Ctrl+D
```

---

## 🆘 Troubleshooting

### Problema: "Permission denied (publickey)"

**Causa:** Chiave SSH non riconosciuta

**Soluzione:**
```bash
# 1. Verifica permessi chiave
chmod 600 .ssh-config/id_rsa

# 2. Aggiungi chiave all'agent
eval $(ssh-agent -s)
ssh-add .ssh-config/id_rsa

# 3. Riprova connessione
ssh wp@trentinoimmobiliare.it
```

### Problema: "Host key verification failed"

**Causa:** Fingerprint server cambiato

**Soluzione:**
```bash
# Rimuovi vecchia entry
ssh-keygen -R trentinoimmobiliare.it

# Riconnetti (conferma nuovo fingerprint)
ssh wp@trentinoimmobiliare.it
```

### Problema: "bash: wp: command not found"

**Causa:** WP-CLI non installato o non nel PATH

**Soluzione:**
```bash
# Verifica dove si trova wp
which wp

# Se esiste ma non nel PATH, usa percorso completo
/usr/local/bin/wp --info

# Oppure installa wp-cli (chiedi all'hosting)
```

### Problema: Timeout durante operazioni lunghe

**Soluzione:**
```bash
# Usa screen per sessioni persistenti
screen -S import

# Esegui comando
wp eval-file includes/scripts/delete-all-imported-data.php --confirm

# Detach: Ctrl+A, poi D
# Riattach: screen -r import
```

---

## 📝 Note Importanti

1. **Backup Prima di Modifiche:** Sempre fare backup prima di operazioni distruttive
2. **Dry Run First:** Usa sempre --dry-run prima di eseguire script che modificano DB
3. **Check Logs:** Controlla sempre debug.log dopo operazioni importanti
4. **Permessi File:** Assicurati che i file caricati abbiano permessi corretti (644 per file, 755 per cartelle)

---

## 🔐 Sicurezza

- ✅ Mai condividere chiavi SSH
- ✅ Mai committare chiavi in Git
- ✅ Usare chiavi diverse per server diversi
- ✅ Cambiare chiavi periodicamente
- ✅ Disconnettersi sempre dopo operazioni

---

**Fine guida - Ultimo aggiornamento: 2025-12-08**
