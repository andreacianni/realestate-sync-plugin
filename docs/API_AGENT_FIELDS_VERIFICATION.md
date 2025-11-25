# Verifica Campi Agente nelle Chiamate API WPResidence

**Data**: 2025-11-25
**Scopo**: Verificare come vengono passati i dati dell'agente/agenzia nelle chiamate API
**Riferimento**: Documentazione Postman WPResidence

---

## 📋 Campi Agente secondo Documentazione WPResidence

Secondo gli esempi nella documentazione Postman WPResidence:
- [Add Property](https://www.postman.com/universal-eclipse-339362/wpresidence/request/u1y022d/add-property?tab=body)
- [Edit Property](https://www.postman.com/universal-eclipse-339362/wpresidence/request/tutgujw/edit-property)

I campi agente devono essere passati così:

```json
{
  "property_agent": "31327",
  "property_agent_secondary": ["23157", "22914"],
  "property_user": "11"
}
```

---

## ✅ VERIFICA 1: Campo `property_agent`

### Domanda
La stringa che passiamo come `property_agent` è l'ID dell'Agenzia/Agente che viene creato dal flusso XML?

### Risposta
**✅ SÍ, CONFERMATO**

### Flusso Completo

```
XML Property (agency_data)
    ↓
Property Mapper (process_agency_for_property)
    ↓
Agency Manager (create_or_update_agency_from_xml)
    ↓
Agency API Writer (create_agency via REST API)
    ↓
WordPress Post ID creato (es: 5179)
    ↓
Property Mapper salva in source_data['agency_id']
    ↓
API Writer passa come property_agent
```

### Codice Rilevante

**1. Property Mapper estrae agency_id**
```php
// includes/class-realestate-sync-property-mapper.php:330-337
$agency_id = $this->process_agency_for_property($xml_property);
if ($agency_id) {
    $source_data['agency_id'] = $agency_id;  // ← WordPress Post ID
}
```

**2. Agency Manager crea l'agenzia e ritorna Post ID**
```php
// includes/class-realestate-sync-agency-manager.php:307-337
private function create_agency($agency_data) {
    $result = $this->api_writer->create_agency($api_body);
    $agency_id = $result['agency_id'];  // ← WordPress Post ID (tipo: estate_agency)
    return $agency_id;
}
```

**3. API Writer passa il Post ID come stringa**
```php
// includes/class-realestate-sync-wpresidence-api-writer.php:206-208
if (!empty($mapped_property['source_data']['agency_id'])) {
    $api_body['property_agent'] = (string) $mapped_property['source_data']['agency_id'];
    $this->logger->log('Agency/Agent ID: ' . $api_body['property_agent'], 'INFO');
}
```

### Tipo di Dato

| Campo | Formato | Valore Esempio | Tipo WordPress |
|-------|---------|----------------|----------------|
| `property_agent` | String | `"5179"` | Post ID del CPT `estate_agency` |

**Importante:**
- ✅ È il **WordPress Post ID** dell'agenzia (Custom Post Type `estate_agency`)
- ✅ Viene creato dall'**Agency Manager** tramite API REST di WPResidence
- ✅ NON è l'ID XML originale (salvato separatamente in meta `xml_agency_id`)
- ✅ Il campo accetta sia post type `estate_agent` che `estate_agency` (unified dropdown)

### Verifica Post Type

```php
// includes/class-realestate-sync-wp-importer.php:1228-1229
$agency_post = get_post($agency_id);
if (!$agency_post || $agency_post->post_type !== 'estate_agency') {
    // Agency non trovata o tipo errato
}
```

### Esempio Pratico

```json
{
  "property_agent": "5179",        // ← WordPress Post ID dell'agenzia
  "sidebar_agent_option": "global" // ← Abilita sidebar agenzia
}
```

**Riferimento:** `docs/SIDEBAR_AGENCY_FIX.md:118`

---

## ⚠️ VERIFICA 2: Campo `property_agent_secondary`

### Stato Attuale
**❌ NON IMPLEMENTATO**

### Documentazione WPResidence

```json
{
  "property_agent_secondary": ["23157", "22914"]  // Array di ID agenti
}
```

### Documentazione Plugin

**File:** `includes/addon-integration/class-addon-field-factory-properties.php:243`

```php
$this->add_on->add_field(
    'property_agent_secondary',
    'Secondary Agents',
    'text',
    null,
    'Match by Agent name. Separate multiple agents with commas.
     If existing ones are not found, new ones will be created.'
)
```

### Conclusione

✅ **Può essere lasciato vuoto o non passato** - non abbiamo agenti secondari nel flusso XML.

Il campo è supportato dal sistema addon per import manuali, ma non è richiesto dall'API.

---

## ⚠️ VERIFICA 3: Campo `property_user`

### Stato Attuale
**❌ NON IMPLEMENTATO**

### Documentazione WPResidence

```json
{
  "property_user": "11"  // ID utente WordPress
}
```

### Scopo del Campo

Secondo la documentazione addon:

**File:** `includes/addon-integration/class-addon-field-factory-properties.php:244`

```php
$this->add_on->add_field(
    'property_user',
    'Assign Property to User',
    'text',
    null,
    'Match by user ID, email, login, or slug'
)
```

**File:** `includes/addon-integration/class-addon-importer-properties.php:310-375`

```php
public function import_property_user($post_id, $data, $import_options, $article) {
    // Cerca utente per: ID, slug, email, o login
    $user = get_user_by('id', $data[$field]);

    if ($user != false) {
        // 1. Salva in meta field
        $this->helper->update_meta($post_id, $field, $id);

        // 2. ⚠️ IMPORTANTE: Modifica anche post_author
        wp_update_post([
            'ID'          => $post_id,
            'post_author' => $id  // ← Cambia autore del post
        ]);
    }
}
```

### Relazione con post_author

**File:** `WPRESIDENCE_API_CAPABILITIES.md:180-186`

```php
// property_create.php:98 (WPResidence API)
'post_author' => $current_user,  // ← Sempre l'user loggato JWT

// Problema:
// - post_author viene impostato all'user corrente (JWT authenticated user)
// - property_agent viene salvato come meta field generico
// - WpResidence sidebar potrebbe leggere post_author invece di property_agent
```

### Chi Dovrebbe Essere property_user?

**Opzione A - Utente Importer** (✅ RACCOMANDATO):
- **User ID**: `59` (produzione)
- **Username**: `importer`
- **Email**: `importer@trentinoimmobiliare.it`
- **Ruolo**: Administrator
- **Motivo**: È l'utente che esegue le chiamate API via JWT

**Riferimento:** `SESSION_STATUS.md:109-112`

**Opzione B - Non Passarlo**:
- L'API imposta automaticamente `post_author` all'utente JWT corrente
- `property_user` è opzionale

### Conclusione

💡 **Raccomandazione**:
- Passare `property_user` = `"59"` (ID utente importer in produzione)
- Questo rende esplicito che tutte le proprietà appartengono all'utente importer
- Non abbiamo informazioni sull'owner nell'XML, quindi usare l'importer è logico

---

## 📊 Riepilogo Situazione Attuale vs Documentazione

| Campo | Documentazione API | Codice Attuale | Tipo | Stato |
|-------|-------------------|----------------|------|-------|
| `property_agent` | `"31327"` | ✅ `"5179"` | String (Post ID) | **IMPLEMENTATO** |
| `property_agent_secondary` | `["23157", "22914"]` | ❌ Non passato | Array | **OPZIONALE** |
| `property_user` | `"11"` | ❌ Non passato | String (User ID) | **DA VALUTARE** |

### Esempio Chiamata API Completa (Ideale)

```json
{
  "title": "Appartamento centro Trento",
  "property_description": "...",
  "property_agent": "5179",              // ← WordPress Post ID agenzia
  "property_agent_secondary": [],        // ← Vuoto (non abbiamo agenti secondari)
  "property_user": "59",                 // ← User ID importer
  "sidebar_agent_option": "global"
}
```

### Esempio Chiamata API Attuale (Minimo)

```json
{
  "title": "Appartamento centro Trento",
  "property_description": "...",
  "property_agent": "5179",              // ✅ Implementato
  "sidebar_agent_option": "global"       // ✅ Implementato
}
```

**Nota**: L'API funziona correttamente anche senza `property_agent_secondary` e `property_user`.

---

## 🔍 Riferimenti Codice

### File Coinvolti

1. **Property Mapper** - Estrae agency_id
   - `includes/class-realestate-sync-property-mapper.php:330-347`

2. **Agency Manager** - Crea/aggiorna agenzia
   - `includes/class-realestate-sync-agency-manager.php:48-88`
   - `includes/class-realestate-sync-agency-manager.php:307-337`

3. **API Writer** - Formatta dati per API
   - `includes/class-realestate-sync-wpresidence-api-writer.php:206-213`

4. **WP Importer** - Verifica agency exists
   - `includes/class-realestate-sync-wp-importer.php:1217-1245`

### Documentazione Progetto

- `docs/SIDEBAR_AGENCY_FIX.md` - Problema sidebar agency
- `docs/API_ADD_EDIT_OPERATIONS.md` - Esempi chiamate API
- `docs/WPRESIDENCE_API_CAPABILITIES.md` - Analisi API WPResidence
- `docs/SESSION_STATUS.md` - Configurazione utente importer

---

## ✅ Conclusioni

1. **`property_agent`** = ✅ **CORRETTO**
   - Passa l'ID WordPress dell'agenzia creata dal flusso XML
   - Tipo: String con Post ID del CPT `estate_agency`
   - Esempio: `"5179"`

2. **`property_agent_secondary`** = ⚠️ **NON NECESSARIO**
   - Non abbiamo agenti secondari nel flusso XML
   - Può essere omesso dalla chiamata API

3. **`property_user`** = 💡 **DA VALUTARE**
   - Dovrebbe essere l'ID dell'utente importer (`"59"` in produzione)
   - Opzionale ma raccomandato per esplicitare ownership
   - L'API usa comunque l'utente JWT se non specificato

---

**Ultima verifica**: 2025-11-25
**Branch**: `claude/verify-api-agent-data-01XFQUzqLNr1q3LkCSEDNFeG`
