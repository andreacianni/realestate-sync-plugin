# RealEstate Sync Plugin - Claude Code Instructions

## 📋 PROGETTO

**Nome**: RealEstate Sync - WordPress Plugin per Import XML Automatizzato  
**Repository**: https://github.com/andreacianni/realestate-sync-plugin  
**Ambiente Locale**: Symlink a `C:\xampp\htdocs\trentino-wp\wp-content\plugins\realestate-sync-plugin`

## 📚 KNOWLEDGE BASE COMPLETA

Prima di qualsiasi operazione, **LEGGI SEMPRE** questi file per il contesto completo:

1. **Context generale**: `docs/KB-Claude/trentino-immobiliare-KB-Context.md`
2. **Stato corrente**: `docs/KB-Claude/trentino-immobiliare-KB-State-Current.md`  
3. **Workflow Git**: `docs/KB-Claude/trentino-immobiliare-KB-Workflow-Git.md`

> **CRITICO**: Non procedere senza aver letto lo State file - contiene i dettagli dell'issue corrente e lo stato esatto dello sviluppo.

---

## 🏗️ ARCHITETTURA PLUGIN

### Struttura Directory Principale
```
realestate-sync-plugin/
├── includes/               # Core classes
│   ├── class-realestate-sync-logger.php
│   ├── class-realestate-sync-xml-downloader.php
│   ├── class-realestate-sync-xml-parser.php
│   ├── class-realestate-sync-property-mapper.php
│   ├── class-realestate-sync-wp-importer.php
│   ├── class-realestate-sync-import-engine.php
│   ├── class-realestate-sync-agency-manager.php
│   └── class-realestate-sync-addon-adapter.php
├── admin/                  # Admin interface
│   ├── class-realestate-sync-admin.php
│   └── views/
├── config/                 # Configurazioni
│   ├── field-mapping.php
│   └── province-config.php
└── logs/                   # System logs
```

### Stack Tecnologico
- **WordPress**: 6.x con WpResidence theme
- **PHP**: 7.4+ (oggetti, namespace, type hints)
- **Database**: WordPress wp_posts + wp_postmeta
- **Testing**: Ambiente locale XAMPP con symlink

---

## 🎯 WORKFLOW DEVELOPMENT

### Branch Strategy
```bash
# Development attivo
git checkout develop

# Testing locale via symlink
# Commit incrementali per checkpoint sicuri
git add . && git commit -m "feat: checkpoint - descrizione"
git push origin develop

# Release (quando punto consistente)
git checkout main && git merge develop
git tag v1.X.X && git push origin main --tags
```

### Testing Locale Completo
- ✅ **Step 1**: XML Download + Parsing
- ✅ **Step 2**: Data processing + validation
- ✅ **Step 3**: WpResidence integration
- ✅ **Admin Interface**: Test workflow completo

**IMPORTANTE**: Tutto testabile in locale via symlink. NO server dipendenze necessarie.

---

## 🔧 CONVENZIONI CODICE

### Naming Conventions
```php
// Classes
class RealEstate_Sync_Component_Name {}

// Methods (snake_case)
public function process_xml_data() {}

// Variables (snake_case)
$property_data = [];

// Constants (UPPER_SNAKE_CASE)
define('REALESTATE_SYNC_VERSION', '1.0.0');
```

### WordPress Standards
- **Hooks**: Usa prefix `realestate_sync_`
- **Options**: Namespace `realestate_sync_settings`
- **Transients**: Cache 12h per API calls
- **Nonces**: Sempre per security admin actions
- **Escaping**: wp_kses_post(), esc_html(), esc_url()

### Logging System
```php
// SEMPRE usa logger per debug
$logger = new RealEstate_Sync_Logger();
$logger->log("Messaggio debug", 'info');
$logger->log("Errore critico", 'error');
$logger->log("Warning importante", 'warning');
```

---

## 🚨 REGOLE CRITICHE

### NEVER DO
- ❌ **MAI** modificare file core WordPress
- ❌ **MAI** query dirette DB senza $wpdb
- ❌ **MAI** hardcode credenziali (usa get_option)
- ❌ **MAI** commit su main direttamente (sempre via develop)
- ❌ **MAI** fare push --force su main (solo su develop se necessario)

### ALWAYS DO
- ✅ **SEMPRE** leggere State file prima di modifiche
- ✅ **SEMPRE** usare logger per troubleshooting
- ✅ **SEMPRE** testare in locale prima di commit
- ✅ **SEMPRE** commit incrementali con messaggi descrittivi
- ✅ **SEMPRE** validare input utente e sanitize output

---

## 📋 COMMIT STANDARDS

### Template Professionale
```bash
git commit -m "[tipo]: [descrizione_sintetica]

- [bullet_point_principale]
- [risolve_cosa_specifico]
- [dettaglio_implementazione]"
```

### Tipi Commit
- `feat:` - Nuova funzionalità
- `fix:` - Bug fix normale
- `hotfix:` - Fix critico/bloccante
- `refactor:` - Ristrutturazione codice
- `docs:` - Documentazione
- `clean:` - Pulizia codice

### Esempio
```bash
git commit -m "feat: implementato Agency Manager completo

- Aggiunta classe RealEstate_Sync_Agency_Manager
- Mapping XML → WordPress CPT estate_agency
- Integration con Property Mapper
- Testing completo su sample XML"
```

---

## 🔍 STATO CORRENTE SVILUPPO

> **LEGGI `trentino-immobiliare-KB-State-Current.md` PER DETTAGLI COMPLETI**

### Issue Attivo
Consulta sempre lo State file per:
- Issue corrente in lavorazione
- Test risultati e problemi aperti
- Task pending e next steps
- Breakthrough discoveries recenti

### Testing Workflow
1. Modifica codice in local workspace
2. Test via symlink in WordPress locale
3. Verifica logs in `logs/import-logs/`
4. Commit checkpoint se funzionante
5. Push develop per backup continuo

---

## 🎯 OBIETTIVI SESSIONE

### Pre-Lavoro Checklist
- [ ] Letto State file completo
- [ ] Compreso issue corrente
- [ ] Identificato file da modificare
- [ ] Pianificato approach testing

### Post-Lavoro Update
- [ ] Testato modifiche in locale
- [ ] Commit con messaggio descrittivo
- [ ] Aggiornato State file se necessario
- [ ] Documentato breakthrough/issue

---

## 💡 COLLABORATION APPROACH - REGOLE CRITICHE

### 🧠 RAGIONA SEMPRE INSIEME
**FONDAMENTALE**: Non implementare mai direttamente senza prima discutere l'approccio.

#### Workflow Collaborativo Obbligatorio:
1. **STOP & THINK**: Prima di qualsiasi modifica, esponi il tuo ragionamento
2. **DISCUSS**: Presenta l'approach e aspetta feedback/validazione
3. **VALIDATE**: Discuti pro/contro delle alternative
4. **IMPLEMENT**: Solo dopo approvazione procedi con il codice

#### Esempio Corretto:
```
User: "Dobbiamo fixare l'autenticazione XML"

Claude: "Ho letto il Context e lo State file. Vedo che il problema è 
nelle credenziali GestionaleImmobiliare.it.

RAGIONIAMO INSIEME sull'approach:

OPZIONE A: Modificare XML Downloader per gestire auth header
- Pro: Minimo impatto, fix localizzato
- Contro: Potrebbe non risolvere se issue è server-side

OPZIONE B: Implementare retry logic con backoff
- Pro: Resiliente a errori temporanei  
- Contro: Non risolve se credenziali sbagliate

OPZIONE C: Debug completo con logging auth flow
- Pro: Identifica causa esatta
- Contro: Richiede più tempo

Quale approach preferisci? O hai altre idee?"

[ASPETTA RISPOSTA PRIMA DI PROCEDERE]
```

### 🚫 MAI FARE

❌ **Implementazione diretta** senza discussione
❌ **Assumere soluzioni** senza validare ipotesi  
❌ **Modifiche massive** senza breakdown del piano
❌ **"Ho fatto X"** → Sempre **"Propongo di fare X perché..."**

### ✅ SEMPRE FARE

✅ **Esponi il ragionamento** prima del codice
✅ **Presenta alternative** con pro/contro
✅ **Chiedi feedback** sull'approach
✅ **Valida ipotesi** con evidenza prima di procedere
✅ **Breakdown tasks** in step incrementali discutibili

### Debug Methodology - Evidence-Based
1. **Analizza situation**: Leggi logs, State file, codice esistente
2. **Ipotesi verificabile**: Formula theory basata su evidenza
3. **Discuti approach**: Presenta piano di test/fix
4. **Test incrementale**: Small changes, quick validation dopo approvazione
5. **Documentation**: Update State con findings e reasoning

### Tone & Communication
- **Collaborative**: "Ragioniamo insieme su...", "Cosa ne pensi di..."
- **Humble**: "Potrei sbagliarmi, ma...", "Vedo due possibilità..."  
- **Evidence-based**: "Dai logs emerge che...", "Il Context file indica..."
- **Clear**: Breakdown complessi in step semplici e discussibili

---

## 📞 QUICK REFERENCE

### Comandi Utili
```bash
# Reset a checkpoint sicuro
git reset --hard [commit-hash]

# Sincronizza con remote
git fetch origin
git pull origin develop

# Check status dettagliato
git status
git log --oneline -10

# Testing logs
tail -f logs/import-logs/import-*.log
```

### File Chiave Debugging
- `logs/import-logs/` - Import execution logs
- `admin/views/logs.php` - Log viewer interface
- `includes/class-realestate-sync-logger.php` - Logging system

---

## 🎉 OBIETTIVI PROGETTO

- **Professional**: Standard WordPress enterprise
- **Maintainable**: Admin-friendly interface
- **Scalable**: Architettura modulare
- **Reliable**: Testing completo + rollback safety
- **Documented**: KB completa + inline docs

---

**📅 Updated**: Ottobre 2025  
**🎯 Status**: Production-Ready Plugin - Hook Investigation Phase  
**👨‍💻 Author**: Andrea Cianni - Novacom  
**🌐 Repository**: https://github.com/andreacianni/realestate-sync-plugin