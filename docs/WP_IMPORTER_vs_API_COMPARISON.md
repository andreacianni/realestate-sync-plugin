# WP Importer vs REST API - Analisi Comparativa

**Data**: 2025-10-13
**Obiettivo**: Identificare cosa mantenere, cosa eliminare, cosa delegare all'API

---

## 📊 OPERAZIONI CORRENTI WP_IMPORTER

### ✅ MANTENIAMO (Invariate)

#### 1. **Parsing & Mapping Layer**
- XML Parser
- Data Converter v3.0
- Property Mapper v3.1
- Agency Manager

**Motivo**: Serve pre-processare dati XML → formato utilizzabile

#### 2. **Session Management & Tracking**
```php
- $this->session_id
- $this->stats (statistics)
- update_post_meta('property_import_id')
- update_post_meta('property_last_sync')
- update_post_meta('property_import_hash')
- update_post_meta('property_import_version')
```

**Motivo**: Tracking import, change detection, statistics

#### 3. **Duplicate Detection**
```php
find_existing_property($import_id)
```

**Motivo**: Necessario per decidere se create o update via API

#### 4. **Logger & Error Handling**
```php
$this->logger->log()
try/catch blocks
$this->stats['errors']
```

**Motivo**: Debugging e monitoring

---

## ❌ ELIMINIAMO (Delegate all'API)

### 1. **Post Creation/Update Diretti**
```php
// BEFORE (WP_Importer)
$post_id = wp_insert_post($mapped_property['post_data'], true);
wp_update_post($post_data, true);
```

```php
// AFTER (API Writer)
POST /wpresidence/v1/property/add
{
  "title": "...",
  "property_description": "...",
  ...
}
```

**Motivo**: L'API gestisce automaticamente creation con tutti gli hook WpResidence

---

### 2. **Meta Fields Diretti**
```php
// ❌ ELIMINIAMO TUTTO
private function assign_meta_fields($post_id, $meta_fields) {
    foreach ($meta_fields as $meta_key => $meta_value) {
        update_post_meta($post_id, $meta_key, $meta_value);
    }
}
```

**Motivo**: L'API accetta QUALSIASI campo come parametro e lo salva come meta field automaticamente

**API Equivalente**:
```json
{
  "property_price": "120000",
  "property_size": "70",
  "property_bedrooms": "2",
  "qualsiasi_campo_custom": "valore"
}
```

---

### 3. **Custom Fields Diretti**
```php
// ❌ ELIMINIAMO
private function assign_custom_fields($post_id, $custom_fields) {
    foreach ($custom_fields as $field_key => $field_value) {
        update_post_meta($post_id, $field_key, $field_value);
    }
}
```

**Motivo**: L'API ha parametro dedicato `custom_fields`

**API Equivalente**:
```json
{
  "custom_fields": [
    {"slug": "property_year_built", "value": "2000"},
    {"slug": "property_agency_code", "value": "74"}
  ]
}
```

---

### 4. **Taxonomies Management**
```php
// ❌ ELIMINIAMO
private function assign_taxonomies($post_id, $taxonomies) {
    foreach ($taxonomies as $taxonomy => $terms) {
        // get_or_create_term()
        // wp_set_object_terms()
    }
}

private function get_or_create_term($term_name, $taxonomy)
```

**Motivo**: L'API rileva automaticamente taxonomies e assegna termini

**API Equivalente**:
```json
{
  "property_category": ["appartamento"],
  "property_city": ["grisignano-di-zocco"],
  "property_area": ["centro"]
}
```

**NOTA IMPORTANTE**: I termini devono già esistere in WP! L'API NON crea termini mancanti.

**Strategia**:
- Mantenere funzione `get_or_create_term()` per **pre-creare termini** PRIMA della chiamata API
- Oppure verificare termini esistenti e loggare warning se mancanti

---

### 5. **Property Features**
```php
// ❌ ELIMINIAMO (ma verifichiamo prima)
private function assign_property_features($post_id, $features)
private function assign_property_features_v3($post_id, $features)
private function humanize_feature_name($slug)
```

**Motivo**: L'API supporta `property_features` taxonomy

**API Equivalente**:
```json
{
  "property_features": [
    "arredato",
    "garage",
    "cantina"
  ]
}
```

**ATTENZIONE**: Come per taxonomies, le features devono già esistere!

---

### 6. **Gallery Processing COMPLETO**
```php
// ❌ ELIMINIAMO TUTTO IL SISTEMA GALLERY
private function process_gallery_v3($post_id, $gallery)
private function set_wpresidence_gallery_compatibility($post_id, $attachment_ids)
private function set_wpresidence_gallery_fallback($post_id, $attachment_ids)
private function update_image_to_attach_field($post_id, $attachment_ids)
private function set_gallery_menu_order($post_id, $attachment_ids)
private function process_gallery_v3_fallback($post_id, $gallery)

// ❌ ELIMINIAMO ANCHE Image Importer
$this->image_importer->process_property_images($post_id, $gallery)
```

**Motivo**: L'API gestisce TUTTO automaticamente:
- Download immagini HTTPS
- Security validation
- WordPress attachments
- `wpestate_property_gallery`
- `image_to_attach`
- Featured image
- Menu order
- Cache clearing

**API Equivalente**:
```json
{
  "images": [
    {"id": "img00", "url": "https://..."},
    {"id": "img01", "url": "https://..."}
  ]
}
```

**RISPARMIO**: ~600 righe di codice complesso + gestione download/errori!

---

### 7. **WpResidence Required Fields**
```php
// ❌ ELIMINIAMO (probabilmente)
private function add_wpresidence_required_fields($post_id, $meta_fields)
```

**Campi attualmente gestiti**:
- `hidden_address`
- `property_country`
- `local_show_hide_price`

**Da verificare**: L'API li genera automaticamente?

**Test necessario**: Creare property via API e verificare se questi campi sono presenti.

---

### 8. **Agency Assignment**
```php
// ❌ SEMPLIFICHIAMO
private function assign_agency_to_property($post_id, $source_data) {
    update_post_meta($post_id, 'property_agent', $agency_id);
}

private function process_agency_v3($mapped_property)
```

**API Equivalente**:
```json
{
  "property_agent": "5074"
}
```

**Motivo**: Basta passare l'ID nell'API body

---

### 9. **Catasto Data**
```php
// ❌ ELIMINIAMO
private function process_catasto_v3($post_id, $catasto) {
    foreach ($catasto as $key => $value) {
        update_post_meta($post_id, $key, $value);
    }
}
```

**API Equivalente**: Custom fields o parametri diretti
```json
{
  "custom_fields": [
    {"slug": "catasto_foglio", "value": "123"},
    {"slug": "catasto_particella", "value": "456"}
  ]
}
```

---

### 10. **Cache Clearing & Hooks**
```php
// ❌ ELIMINIAMO
private function refresh_wpresidence_cache($post_id)
private function init_wpresidence_integration_hooks()
public function enhance_wpresidence_markers()
public function refresh_property_cache($post_id, $action)

// ❌ ELIMINIAMO manual hooks
do_action('save_post', $post_id, ...)
do_action('edit_post', $post_id, ...)
usleep(500000) // delays
```

**Motivo**: L'API chiama automaticamente:
```php
// Nel codice API (property_create.php:140-145)
if (function_exists('wpestate_delete_cache')) {
    wpestate_delete_cache();
}
```

---

### 11. **Debug & Verification Functions**
```php
// ❌ ELIMINIAMO (non più necessarie)
private function debug_verify_dual_gallery_system($post_id, $expected_attachment_ids)
public function debug_test_dual_gallery_on_property($post_id)
```

**Motivo**: API gestisce tutto internamente, non serve più verificare manualmente

---

## 🔄 MODIFICHIAMO (Adaptiamo per API)

### 1. **Process Property v3** (Entry Point)
```php
// BEFORE
public function process_property_v3($mapped_property) {
    // 1. Check existing
    // 2. Create/Update post directly
    // 3. Assign meta fields
    // 4. Assign taxonomies
    // 5. Process gallery
    // 6. Process agency
    // 7. Process catasto
    // 8. Manual hooks
}

// AFTER (con API Writer)
public function process_property_v3($mapped_property) {
    // 1. Check existing (manteniamo)
    $existing_post_id = $this->find_existing_property($import_id);

    // 2. Pre-create taxonomies/features se necessario
    $this->ensure_terms_exist($mapped_property['taxonomies']);
    $this->ensure_features_exist($mapped_property['features']);

    // 3. Formatta dati per API
    $api_body = $this->api_writer->format_api_body($mapped_property);

    // 4. Chiamata API (create o update)
    if ($existing_post_id) {
        $result = $this->api_writer->update_property($existing_post_id, $api_body);
    } else {
        $result = $this->api_writer->create_property($api_body);
    }

    // 5. Update tracking metadata (solo questo!)
    if ($result['success']) {
        update_post_meta($result['post_id'], 'property_import_id', $import_id);
        update_post_meta($result['post_id'], 'property_import_hash', $mapped_property['content_hash']);
        update_post_meta($result['post_id'], 'property_last_sync', current_time('mysql'));
        update_post_meta($result['post_id'], 'property_import_version', '3.0');
    }

    return $result;
}
```

**Riduzione**: Da ~400 righe a ~50 righe!

---

### 2. **Create/Update Methods**
```php
// ELIMINIAMO COMPLETAMENTE
private function create_new_property_v3($mapped_property, $import_id)
private function update_existing_property_v3($post_id, $mapped_property, $import_id)
```

Sostituiti da:
```php
$this->api_writer->create_property($api_body)
$this->api_writer->update_property($post_id, $api_body)
```

---

## 🆕 NUOVE FUNZIONI NECESSARIE

### 1. **Ensure Terms Exist** (Pre-creazione)
```php
/**
 * Ensure taxonomy terms exist before API call
 * API doesn't create missing terms automatically
 */
private function ensure_terms_exist($taxonomies) {
    foreach ($taxonomies as $taxonomy => $terms) {
        foreach ($terms as $term_name) {
            $term = get_term_by('name', $term_name, $taxonomy);
            if (!$term) {
                wp_insert_term($term_name, $taxonomy);
                $this->logger->log("Created missing term: $term_name in $taxonomy");
            }
        }
    }
}
```

### 2. **Ensure Features Exist** (Pre-creazione)
```php
/**
 * Ensure property features exist before API call
 */
private function ensure_features_exist($features) {
    foreach ($features as $feature_slug) {
        $term = get_term_by('slug', $feature_slug, 'property_features');
        if (!$term) {
            $feature_name = $this->humanize_feature_name($feature_slug);
            wp_insert_term($feature_name, 'property_features', ['slug' => $feature_slug]);
            $this->logger->log("Created missing feature: $feature_name ($feature_slug)");
        }
    }
}
```

---

## 📋 CHECKLIST IMPLEMENTAZIONE API WRITER

### Classe `RealEstate_Sync_WPResidence_API_Writer`

**Metodi necessari**:

```php
class RealEstate_Sync_WPResidence_API_Writer {

    private $logger;
    private $jwt_token;
    private $jwt_expiration;
    private $api_base_url;

    public function __construct($logger = null) {
        $this->logger = $logger ?: RealEstate_Sync_Logger::get_instance();
        $this->api_base_url = get_site_url() . '/wp-json/wpresidence/v1';
    }

    /**
     * Generate or refresh JWT token
     */
    private function get_jwt_token() {
        // Check if current token is still valid
        if ($this->jwt_token && time() < $this->jwt_expiration) {
            return $this->jwt_token;
        }

        // Get credentials from options
        $username = get_option('realestate_sync_api_username');
        $password = get_option('realestate_sync_api_password');

        // Call JWT endpoint
        $response = wp_remote_post($this->api_base_url . '/../../jwt-auth/v1/token', [
            'body' => json_encode(['username' => $username, 'password' => $password]),
            'headers' => ['Content-Type' => 'application/json']
        ]);

        // Parse response and store token
        // ...

        return $this->jwt_token;
    }

    /**
     * Format mapped property data to API body format
     */
    public function format_api_body($mapped_property) {
        $api_body = [];

        // 1. Core fields
        $api_body['title'] = $mapped_property['post_data']['post_title'];
        $api_body['property_description'] = $mapped_property['post_data']['post_content'];

        // 2. Meta fields (TUTTI i campi diventano parametri API)
        foreach ($mapped_property['meta_fields'] as $key => $value) {
            $api_body[$key] = $value;
        }

        // 3. Taxonomies (array format)
        foreach ($mapped_property['taxonomies'] as $taxonomy => $terms) {
            $api_body[$taxonomy] = $terms; // API accepts arrays
        }

        // 4. Features
        if (!empty($mapped_property['features'])) {
            $api_body['property_features'] = $mapped_property['features'];
        }

        // 5. Gallery images (HTTPS URLs)
        if (!empty($mapped_property['gallery'])) {
            $api_body['images'] = [];
            foreach ($mapped_property['gallery'] as $index => $image) {
                $api_body['images'][] = [
                    'id' => 'img' . str_pad($index, 2, '0', STR_PAD_LEFT),
                    'url' => $image['url']
                ];
            }
        }

        // 6. Agency (if present)
        if (!empty($mapped_property['source_data']['agency_id'])) {
            $api_body['property_agent'] = $mapped_property['source_data']['agency_id'];
        }

        // 7. Custom fields (formato API)
        if (!empty($mapped_property['catasto'])) {
            $api_body['custom_fields'] = [];
            foreach ($mapped_property['catasto'] as $key => $value) {
                $api_body['custom_fields'][] = [
                    'slug' => $key,
                    'value' => $value
                ];
            }
        }

        return $api_body;
    }

    /**
     * Create property via API
     */
    public function create_property($api_body) {
        $token = $this->get_jwt_token();

        $response = wp_remote_post($this->api_base_url . '/property/add', [
            'body' => json_encode($api_body),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ],
            'timeout' => 120 // 2 minutes for image processing
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($body['status'] === 'success') {
            return [
                'success' => true,
                'action' => 'created',
                'post_id' => $body['property_id'],
                'message' => 'Property created via API'
            ];
        }

        return [
            'success' => false,
            'error' => $body['message'] ?? 'Unknown API error'
        ];
    }

    /**
     * Update property via API
     */
    public function update_property($post_id, $api_body) {
        $token = $this->get_jwt_token();

        $response = wp_remote_request($this->api_base_url . '/property/update/' . $post_id, [
            'method' => 'PUT',
            'body' => json_encode($api_body),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ],
            'timeout' => 120
        ]);

        // Similar error handling...
    }

    /**
     * Handle API errors with retry logic
     */
    private function handle_api_error($response, $retry_count = 0) {
        // Token expired? Refresh and retry
        // Network error? Retry with backoff
        // Other error? Log and return
    }
}
```

---

## 📊 RIEPILOGO SEMPLIFICAZIONI

### Righe di Codice
- **Prima**: ~1700 righe (WP_Importer)
- **Dopo**: ~300 righe (WP_Importer) + ~400 righe (API Writer)
- **Riduzione**: ~1000 righe (-60%)

### Complessità
- ❌ **Eliminato**: Gallery download & processing (~600 righe)
- ❌ **Eliminato**: Manual meta fields assignment (~150 righe)
- ❌ **Eliminato**: Manual taxonomies management (~200 righe)
- ❌ **Eliminato**: WpResidence hooks & cache management (~300 righe)
- ❌ **Eliminato**: Debug & verification (~200 righe)

### Manutenibilità
- ✅ API ufficiale WpResidence (supporto garantito)
- ✅ Meno codice custom = meno bug
- ✅ Gallery gestita automaticamente
- ✅ Cache clearing automatico
- ✅ Hook WpResidence automatici

---

## ⚠️ ATTENZIONI & VERIFICHE

### 1. **Taxonomies & Features Pre-Exist**
L'API NON crea termini mancanti → serve pre-creazione o warning

### 2. **WpResidence Required Fields**
Verificare se API genera automaticamente:
- `hidden_address`
- `property_country`
- `local_show_hide_price`

### 3. **Image URLs HTTPS**
API accetta solo HTTPS URLs (max 5MB, jpeg/png/gif/webp)

### 4. **Performance**
API più lenta (download immagini 23× = ~40s)
Ma elimina complessità nostro codice!

### 5. **JWT Token Management**
- Token expira dopo 10 minuti
- Serve refresh automatico
- Credenziali sicure (options WP)

---

## 🚀 PROSSIMI STEP

1. ✅ Creare classe `RealEstate_Sync_WPResidence_API_Writer`
2. ✅ Aggiungere funzioni `ensure_terms_exist()` e `ensure_features_exist()` al WP_Importer
3. ✅ Modificare `process_property_v3()` per usare API Writer
4. ✅ Test import completo file XML
5. ✅ Verificare campi generati automaticamente dall'API
6. ✅ Ottimizzare gestione token JWT
7. ✅ Aggiungere retry logic per errori API

---

**Autore**: Claude + Andrea
**Versione**: 1.0
**Data**: 2025-10-13
