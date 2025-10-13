# WpResidence REST API - Riepilogo Capacità

**Data**: 2025-10-13
**Endpoint Base**: `/wp-json/wpresidence/v1/`
**Autenticazione**: JWT Token (Bearer)

---

## 🎯 ENDPOINT DISPONIBILI

### 1. **POST** `/property/add` - Create Property
Crea una nuova property con tutti i dati associati.

### 2. **PUT** `/property/update/{id}` - Update Property
Aggiorna una property esistente.

### 3. **GET** `/properties` - List Properties
Recupera lista properties con filtri e paginazione.

### 4. **GET** `/property/{id}` - Get Single Property
Recupera dettagli completi di una property.

### 5. **DELETE** `/property/{id}` - Delete Property
Elimina una property.

---

## 📋 CAMPI SUPPORTATI (Property Creation)

### ✅ Core Fields (Funzionano TUTTI)
```json
{
  "title": "string",                    // Required
  "property_description": "string",     // Required (post_content)
  "property_price": "number",
  "property_size": "number",
  "property_bedrooms": "number",
  "property_bathrooms": "number",
  "property_rooms": "number"
}
```

### ✅ Location Fields
```json
{
  "property_address": "string",
  "property_city": "string",            // Taxonomy term slug
  "property_area": "string",            // Taxonomy term slug
  "property_county": "string",
  "property_state": "string",
  "property_zip": "string",
  "property_country": "string",
  "property_latitude": "string",
  "property_longitude": "string"
}
```

### ✅ Taxonomies (Auto-Processing)
**Meccanismo**: Qualsiasi campo che corrisponde a una taxonomy esistente viene automaticamente assegnato.

```json
{
  "property_category": ["slug1", "slug2"],     // Array
  "property_status": ["vendita", "affitto"],
  "property_features": ["pool", "garage"],
  "property_city": ["milano"],
  "property_area": ["centro"]
}
```

**Codice sorgente** (property_create.php:114-119):
```php
foreach ($input_data as $key => $value) {
    if (taxonomy_exists($key)) {
        if (is_array($value)) {
            wp_set_object_terms($post_id, $value, $key);
        }
    }
}
```

### ✅ Gallery Images
**Formato**: Array di oggetti con `id` e `url`

```json
{
  "images": [
    {"id": "main", "url": "https://example.com/image1.jpg"},
    {"id": "view", "url": "https://example.com/image2.jpg"}
  ]
}
```

**Processing**:
1. Download HTTPS images (max 5MB, jpeg/png/gif/webp)
2. Security validation (MIME type, size, extension)
3. Create WordPress attachments
4. Chiama `wpestate_upload_images_dashboard()` (tema WpResidence)
5. Set featured image (primo della lista)

**Tempo stimato**: ~0.5s per immagine (23 immagini = ~43 secondi)

### ✅ Custom Fields
**Formato**: Array di oggetti con `slug` e `value`

```json
{
  "custom_fields": [
    {"slug": "property_year_built", "value": "1945"},
    {"slug": "property_agency_code", "value": "ZL311"},
    {"slug": "campo_custom", "value": "valore"}
  ]
}
```

**Codice sorgente** (property_create.php:166-173):
```php
if (!empty($input_data['custom_fields']) && is_array($input_data['custom_fields'])) {
    foreach ($input_data['custom_fields'] as $field) {
        if (isset($field['slug']) && isset($field['value'])) {
            update_post_meta($post_id, $field['slug'], $field['value']);
        }
    }
}
```

### ✅ Media & Virtual Tour
```json
{
  "embed_video_type": "youtube",
  "embed_video_id": "https://www.youtube.com/watch?v=...",
  "virtual_tour": "https://...",
  "embed_virtual_tour": "<iframe>...</iframe>"
}
```

### ✅ Energy & Building Info
```json
{
  "energy_class": "G",
  "energy_index": "150 kWh/m3anno",
  "owner_notes": "string"
}
```

### ⚠️ GENERIC META FIELDS (Funzionano ma non verificati)
**Meccanismo**: Qualsiasi campo NON-taxonomy viene salvato come meta field.

```json
{
  "qualsiasi_campo": "valore"
}
```

**Codice sorgente** (property_create.php:123-128):
```php
if (!taxonomy_exists($key)) {
    if ($key != 'title' && $key != 'property_description') {
        update_post_meta($post_id, $key, $value);
    }
}
```

**Significa**: Puoi passare QUALSIASI campo custom e verrà salvato come meta field!

---

## ❌ AGENCY ASSOCIATION - PROBLEMA IDENTIFICATO

### Test Eseguito
**Property ID**: 5197
**Campo passato**: `"property_agent": "5074"`
**Risultato**: ❌ Sidebar NON si popola automaticamente

### Analisi Codice Sorgente
L'API **NON gestisce** il campo `property_agent` in modo speciale:

```php
// property_create.php:98
'post_author' => $current_user,  // ← Sempre l'user loggato JWT
```

**Problema**:
- `post_author` viene impostato all'user corrente (user ID 1 nel nostro caso)
- `property_agent` viene salvato come meta field generico
- **WpResidence sidebar** potrebbe leggere `post_author` invece di `property_agent`

### Possibili Soluzioni

#### Opzione A: Modificare `post_author` alla creazione
**Non supportato dall'API** - `post_author` è sempre `$current_user`

#### Opzione B: Hook `wp_insert_post` per cambiare author
Aggiungere nel nostro plugin:
```php
add_action('wp_insert_post', function($post_id, $post, $update) {
    if ($post->post_type === 'estate_property' && !$update) {
        $agent_id = get_post_meta($post_id, 'property_agent', true);
        if ($agent_id) {
            wp_update_post([
                'ID' => $post_id,
                'post_author' => $agent_id
            ]);
        }
    }
}, 10, 3);
```

#### Opzione C: Campo `sidebar_agent_option`
Provare a passare:
```json
{
  "property_agent": "5074",
  "sidebar_agent_option": "global"  // o "author_info"
}
```

#### Opzione D: Chiamata API Separata per Agency
Se esiste endpoint `/property/{id}/agent` (da verificare).

---

## 🔧 AUTOMATIC PROCESSING (API)

### ✅ Cosa fa AUTOMATICAMENTE l'API

1. **Cache Clearing** ✅
   ```php
   if (function_exists('wpestate_delete_cache')) {
       wpestate_delete_cache();
   }
   ```

2. **Gallery Processing** ✅
   - Download immagini HTTPS
   - Security validation completa
   - Creazione attachments WordPress
   - Chiamata `wpestate_upload_images_dashboard()`
   - Featured image automatica

3. **Taxonomy Assignment** ✅
   - Auto-detect taxonomies esistenti
   - Assegnazione automatica termini

4. **Metadata** ✅
   - Salvataggio tutti i meta fields
   - Custom fields supportati

5. **Post Status** ✅
   - Pubblicazione automatica (`post_status: 'publish'`)

### ❌ Cosa NON fa l'API

1. **Agency Sidebar** ❌
   - Non popola automaticamente la sidebar
   - Serve workaround (vedi sopra)

2. **Custom Taxonomies Non-Standard** ❌
   - Solo taxonomies già registrate in WP
   - Serve pre-creazione termini

3. **Validazione Business Logic** ❌
   - Nessun check su price > 0
   - Nessun check su required fields oltre title
   - Nessun check formato coordinates

---

## 📊 PERFORMANCE

**Test Property 5197** (sample8.xml):
- 23 immagini HTTPS
- Tutti i campi property
- Custom fields
- Video YouTube

**Tempo totale**: ~43 secondi
- Download immagini: ~40s (23 × ~1.7s)
- Processing API: ~3s

**Ottimizzazione possibile**:
- Pre-download immagini in locale
- Upload batch invece di URL remoti

---

## 🎯 CONCLUSIONI & RACCOMANDAZIONI

### ✅ Cosa Usare dall'API
1. Gallery management (funziona perfettamente!)
2. Property core fields
3. Taxonomies (se già esistenti in WP)
4. Custom fields generici
5. Location & coordinates
6. Media (video, virtual tour)

### ⚠️ Cosa Gestire Manualmente
1. **Agency association** - Serve hook custom o modifica post_author
2. Custom taxonomies - Pre-creare termini in WP
3. Validazione business logic - Fare nel plugin prima di chiamare API

### 🚀 Strategia Integrazione Plugin

**Layer 1**: Parsing XML + Mapping (INVARIATO)
- XML Parser ✅
- Data Converter v3.0 ✅
- Property Mapper v3.1 ✅

**Layer 2**: API Writer (NUOVO)
- Formatta dati per API
- Gestisce JWT authentication
- Chiama endpoint `/property/add`
- Handle response & errors

**Layer 3**: Post-Processing (NUOVO)
- Fix agency association (hook o update)
- Verifica taxonomies create
- Custom business logic

---

## 📝 PROSSIMI TEST NECESSARI

### Test 1: Agency Association Fix
Testare le 4 opzioni proposte:
- [ ] Hook `wp_insert_post` + change `post_author`
- [ ] Campo `sidebar_agent_option`
- [ ] Verificare se esiste endpoint `/property/{id}/agent`
- [ ] Modifica manuale post-creazione

### Test 2: Taxonomies
- [ ] Verificare slug esatti taxonomies esistenti
- [ ] Testare creazione con taxonomies inesistenti (errore?)
- [ ] Property_city, property_area, property_category

### Test 3: Custom Fields
- [ ] Testare campi custom non-standard
- [ ] Verificare salvataggio catasto data
- [ ] Piano, stato immobile, etc.

### Test 4: Full Integration
- [ ] Import completo file XML via API
- [ ] Verifica tutti i campi nel frontend
- [ ] Performance test (batch import)

---

**Autore**: Claude + Andrea
**Versione**: 1.0
**Ultima Modifica**: 2025-10-13
