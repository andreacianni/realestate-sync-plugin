# Mappa Workflow: Indirizzo e Posizione Proprietà

**Data**: 2025-11-26
**Versione**: 1.0
**Scope**: Analisi completa del flusso dati per indirizzo e posizione delle proprietà

---

## 📋 Indice

1. [Campi XML Sorgente](#1-campi-xml-sorgente)
2. [Import Engine: Conversione XML](#2-import-engine-conversione-xml)
3. [Property Mapper: Mappatura Campi](#3-property-mapper-mappatura-campi)
4. [API Writer: Formattazione API](#4-api-writer-formattazione-api)
5. [Database: Struttura Salvata](#5-database-struttura-salvata)
6. [Frontend: Visualizzazione](#6-frontend-visualizzazione)
7. [Diagramma Flusso Completo](#7-diagramma-flusso-completo)

---

## 1. Campi XML Sorgente

### 1.1 Indirizzo (Address Components)

```xml
<annuncio>
    <!-- Indirizzo completo -->
    <info>
        <indirizzo><![CDATA[Piazza Duomo]]></indirizzo>
        <zona><![CDATA[Centro Storico]]></zona>
        <comune_istat>022205</comune_istat>
        <latitude>46.0664</latitude>
        <longitude>11.1257</longitude>
    </info>

    <!-- Dati strutturati da API esterna -->
    <comuni>
        <nome><![CDATA[Trento]]></nome>
        <provincia><![CDATA[Trento]]></provincia>
        <cap>38122</cap>
        <regione><![CDATA[Trentino-Alto Adige]]></regione>
    </comuni>
</annuncio>
```

### 1.2 Posizione (Commercial Position)

```xml
<info_inserite>
    <!-- info[56]: Posizione commerciale -->
    <info id="56"><valore_assegnato>1</valore_assegnato></info>
</info_inserite>
```

**Valori Possibili per info[56]:**
- `0` = Non specificato
- `1` = Centro città
- `2` = Zona semicentrale
- `3` = Zona collinare/panoramica
- `4` = Zona periferica
- `5` = Zona residenziale
- `6` = Zona turistica
- `7` = Zona montagna/isolata
- `8` = Lungomare/lungolago
- `9` = Zona commerciale

---

## 2. Import Engine: Conversione XML

**File**: `includes/class-realestate-sync-import-engine.php`

### 2.1 Conversione Indirizzo

```php
// Lines ~150-200: Conversione campi indirizzo
$converted = [
    'address' => $xml['indirizzo'] ?? '',           // "Piazza Duomo"
    'zone' => $xml['zona'] ?? '',                   // "Centro Storico"
    'city_istat_code' => $xml['comune_istat'] ?? '', // "022205"
    'latitude' => $xml['latitude'] ?? '',           // "46.0664"
    'longitude' => $xml['longitude'] ?? '',         // "11.1257"
];

// Dati comuni da API esterna
if (isset($xml['comuni'])) {
    $converted['city'] = $xml['comuni']['nome'] ?? '';        // "Trento"
    $converted['province'] = $xml['comuni']['provincia'] ?? ''; // "Trento"
    $converted['zip_code'] = $xml['comuni']['cap'] ?? '';     // "38122"
    $converted['region'] = $xml['comuni']['regione'] ?? '';   // "Trentino-Alto Adige"
}
```

### 2.2 Conversione Posizione

```php
// Lines ~250-280: Conversione info array
if (isset($xml['info_inserite']['info'])) {
    foreach ($xml['info_inserite']['info'] as $info) {
        $id = $info['@attributes']['id'] ?? null;
        $value = $info['valore_assegnato'] ?? '';

        if ($id == 56) {
            $converted['position_id'] = $value; // "1"
        }
    }
}
```

**Output Import Engine:**
```php
[
    'address' => 'Piazza Duomo',
    'zone' => 'Centro Storico',
    'city' => 'Trento',
    'province' => 'Trento',
    'zip_code' => '38122',
    'region' => 'Trentino-Alto Adige',
    'city_istat_code' => '022205',
    'latitude' => '46.0664',
    'longitude' => '11.1257',
    'position_id' => '1',  // info[56]
]
```

---

## 3. Property Mapper: Mappatura Campi

**File**: `includes/class-realestate-sync-property-mapper.php`

### 3.1 Mappatura Indirizzo Completo

```php
// Lines 401-402: Indirizzo completo
private function map_meta_fields_v3($xml_property, $agency_id = false) {
    $meta = [];

    // Indirizzo completo (concatenato)
    $meta['property_address'] = $this->build_full_address($xml_property);

    // Componenti indirizzo separati
    $meta['property_county'] = $xml_property['province'] ?? '';        // "Trento"
    $meta['property_state'] = $xml_property['region'] ?? '';           // "Trentino-Alto Adige"
    $meta['property_city'] = $xml_property['city'] ?? '';              // "Trento"
    $meta['property_area'] = $xml_property['zone'] ?? '';              // "Centro Storico"
    $meta['property_zip'] = $xml_property['zip_code'] ?? '';           // "38122"

    // Coordinate GPS
    $meta['property_latitude'] = (string) ($xml_property['latitude'] ?? '');   // "46.0664"
    $meta['property_longitude'] = (string) ($xml_property['longitude'] ?? ''); // "11.1257"
}
```

**Metodo `build_full_address()`** (lines ~1100-1120):
```php
private function build_full_address($xml_property) {
    $parts = [];

    // Indirizzo base
    if (!empty($xml_property['address'])) {
        $parts[] = $xml_property['address'];  // "Piazza Duomo"
    }

    // Città
    if (!empty($xml_property['city'])) {
        $parts[] = $xml_property['city'];     // "Trento"
    }

    // Provincia
    if (!empty($xml_property['province'])) {
        $parts[] = $xml_property['province']; // "Trento"
    }

    return implode(', ', $parts); // "Piazza Duomo, Trento, Trento"
}
```

### 3.2 Mappatura Posizione

```php
// Lines 163-177: Position mapping con array lookup
$this->position_mapping = [
    0 => 'Non specificato',
    1 => 'Centro città',                    // ← Mapping per ID 1
    2 => 'Zona semicentrale',
    3 => 'Zona collinare/panoramica',
    4 => 'Zona periferica',
    5 => 'Zona residenziale',
    6 => 'Zona turistica',
    7 => 'Zona montagna/isolata',
    8 => 'Lungomare/lungolago',
    9 => 'Zona commerciale'
];

// Lines ~450-460: Applicazione mapping
$position_id = intval($xml_property['position_id'] ?? 0);  // 1
$meta['property_position'] = $this->position_mapping[$position_id] ?? 'Non specificato';
// Result: "Centro città"
```

**Output Property Mapper (meta_fields):**
```php
[
    'property_address' => 'Piazza Duomo, Trento, Trento',
    'property_county' => 'Trento',
    'property_state' => 'Trentino-Alto Adige',
    'property_city' => 'Trento',
    'property_area' => 'Centro Storico',
    'property_zip' => '38122',
    'property_latitude' => '46.0664',
    'property_longitude' => '11.1257',
    'property_position' => 'Centro città',  // Mappato da info[56]=1
]
```

---

## 4. API Writer: Formattazione API

**File**: `includes/class-realestate-sync-wpresidence-api-writer.php`

### 4.1 Formattazione per WPResidence API

```php
// Lines 176-183: Tutti i meta_fields diventano parametri API
public function format_api_body($mapped_property) {
    $api_body = [];

    // Itera su TUTTI i meta fields
    foreach ($mapped_property['meta_fields'] as $key => $value) {
        if ($value !== '' && $value !== null) {
            $api_body[$key] = $value;  // Passa direttamente alle API
        }
    }

    return $api_body;
}
```

**Output API Writer (body della chiamata POST):**
```json
{
    "property_address": "Piazza Duomo, Trento, Trento",
    "property_county": "Trento",
    "property_state": "Trentino-Alto Adige",
    "property_city": "Trento",
    "property_area": "Centro Storico",
    "property_zip": "38122",
    "property_latitude": "46.0664",
    "property_longitude": "11.1257",
    "property_position": "Centro città"
}
```

**API Endpoint chiamato:**
```
POST /wp-json/wpresidence/v1/property/add
Authorization: Bearer {JWT_TOKEN}
```

---

## 5. Database: Struttura Salvata

**Tabella**: `kre_postmeta`

### 5.1 Struttura Record

```sql
-- Esempio per property_id = 5564
SELECT meta_key, meta_value
FROM kre_postmeta
WHERE post_id = 5564
  AND meta_key LIKE 'property_%'
ORDER BY meta_key;
```

**Risultato:**
| meta_key | meta_value |
|----------|------------|
| `property_address` | "Piazza Duomo, Trento, Trento" |
| `property_area` | "Centro Storico" |
| `property_city` | "Trento" |
| `property_county` | "Trento" |
| `property_latitude` | "46.0664" |
| `property_longitude` | "11.1257" |
| `property_position` | "Centro città" |
| `property_state` | "Trentino-Alto Adige" |
| `property_zip` | "38122" |

### 5.2 Tipo Dati

- **Tutti i campi**: Salvati come `TEXT` in `meta_value`
- **Coordinate**: Salvate come stringhe (non float) per compatibilità API
- **Position**: Salvato come valore testuale mappato ("Centro città"), NON l'ID numerico (1)

---

## 6. Frontend: Visualizzazione

### 6.1 Template WPResidence

**File tema**: `wp-content/themes/wpresidence/single-estate_property.php`

```php
// Recupero campi indirizzo
$address = get_post_meta($post->ID, 'property_address', true);
$city = get_post_meta($post->ID, 'property_city', true);
$area = get_post_meta($post->ID, 'property_area', true);
$position = get_post_meta($post->ID, 'property_position', true);

// Coordinate per mappa
$latitude = get_post_meta($post->ID, 'property_latitude', true);
$longitude = get_post_meta($post->ID, 'property_longitude', true);
```

### 6.2 Visualizzazione Tipica

```html
<!-- Indirizzo principale -->
<div class="property-address">
    <i class="fa fa-map-marker"></i>
    Piazza Duomo, Trento, Trento
</div>

<!-- Zona/Area -->
<div class="property-area">
    <strong>Zona:</strong> Centro Storico
</div>

<!-- Posizione commerciale -->
<div class="property-position">
    <strong>Posizione:</strong> Centro città
</div>

<!-- Mappa Google Maps -->
<div id="property-map"
     data-lat="46.0664"
     data-lng="11.1257">
</div>
```

---

## 7. Diagramma Flusso Completo

```
┌─────────────────────────────────────────────────────────────┐
│ 1. XML SORGENTE (GestionaleImmobiliare)                   │
├─────────────────────────────────────────────────────────────┤
│ <indirizzo>Piazza Duomo</indirizzo>                        │
│ <zona>Centro Storico</zona>                                 │
│ <comune_istat>022205</comune_istat>                         │
│ <latitude>46.0664</latitude>                                │
│ <longitude>11.1257</longitude>                              │
│ <info id="56"><valore_assegnato>1</valore_assegnato></info>│
│ <comuni><nome>Trento</nome><provincia>Trento</provincia>   │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. IMPORT ENGINE (Conversione XML)                         │
├─────────────────────────────────────────────────────────────┤
│ 'address' => 'Piazza Duomo'                                │
│ 'zone' => 'Centro Storico'                                  │
│ 'city' => 'Trento'                                          │
│ 'province' => 'Trento'                                      │
│ 'city_istat_code' => '022205'                               │
│ 'latitude' => '46.0664'                                     │
│ 'longitude' => '11.1257'                                    │
│ 'position_id' => '1'          ← Da info[56]               │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. PROPERTY MAPPER (Mappatura + Trasformazione)            │
├─────────────────────────────────────────────────────────────┤
│ build_full_address():                                       │
│   "Piazza Duomo" + "Trento" + "Trento"                     │
│   = "Piazza Duomo, Trento, Trento"                         │
│                                                             │
│ position_mapping[1]:                                        │
│   ID 1 → "Centro città"                                    │
│                                                             │
│ Output meta_fields:                                         │
│   'property_address' => 'Piazza Duomo, Trento, Trento'    │
│   'property_area' => 'Centro Storico'                      │
│   'property_city' => 'Trento'                              │
│   'property_latitude' => '46.0664'                         │
│   'property_longitude' => '11.1257'                        │
│   'property_position' => 'Centro città'                    │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. API WRITER (Formattazione per WPResidence)              │
├─────────────────────────────────────────────────────────────┤
│ POST /wp-json/wpresidence/v1/property/add                  │
│ {                                                           │
│   "property_address": "Piazza Duomo, Trento, Trento",     │
│   "property_area": "Centro Storico",                       │
│   "property_city": "Trento",                               │
│   "property_latitude": "46.0664",                          │
│   "property_longitude": "11.1257",                         │
│   "property_position": "Centro città"                      │
│ }                                                           │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. WPRESIDENCE API (Salvataggio)                           │
├─────────────────────────────────────────────────────────────┤
│ wp_insert_post() → post_id = 5564                          │
│                                                             │
│ update_post_meta(5564, 'property_address', '...')         │
│ update_post_meta(5564, 'property_area', '...')            │
│ update_post_meta(5564, 'property_city', '...')            │
│ update_post_meta(5564, 'property_latitude', '...')        │
│ update_post_meta(5564, 'property_longitude', '...')       │
│ update_post_meta(5564, 'property_position', '...')        │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. DATABASE (kre_postmeta)                                 │
├─────────────────────────────────────────────────────────────┤
│ post_id | meta_key              | meta_value              │
│ --------+-----------------------+-------------------------│
│ 5564    | property_address      | Piazza Duomo, Trento... │
│ 5564    | property_area         | Centro Storico          │
│ 5564    | property_city         | Trento                  │
│ 5564    | property_latitude     | 46.0664                 │
│ 5564    | property_longitude    | 11.1257                 │
│ 5564    | property_position     | Centro città            │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 7. FRONTEND (WPResidence Theme)                            │
├─────────────────────────────────────────────────────────────┤
│ get_post_meta(5564, 'property_address', true)             │
│ → "Piazza Duomo, Trento, Trento"                          │
│                                                             │
│ Visualizzazione:                                            │
│ ┌───────────────────────────────────────┐                 │
│ │ 📍 Piazza Duomo, Trento, Trento      │                 │
│ │ Zona: Centro Storico                  │                 │
│ │ Posizione: Centro città               │                 │
│ │ [Mappa Google Maps]                   │                 │
│ └───────────────────────────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
```

---

## 8. Note Importanti

### 8.1 Trasformazioni Chiave

1. **Indirizzo Completo**: Concatenato da 3 componenti separati
2. **Position**: Da ID numerico (1) a valore testuale ("Centro città")
3. **Coordinate**: Salvate come stringhe, non float
4. **Zona**: Campo XML `zona` diventa `property_area`

### 8.2 Campi Critici

**Obbligatori per mappa:**
- `property_latitude`
- `property_longitude`

**Obbligatori per ricerca:**
- `property_city`
- `property_county`

**Opzionali ma utili:**
- `property_area` (zona)
- `property_position` (posizione commerciale)

### 8.3 Fix Applicato (FIX #3)

**Problema**: Position mapping completamente errato
**Soluzione**: Corretti tutti e 10 i valori nel `position_mapping` array
**File**: `includes/class-realestate-sync-property-mapper.php:163-177`
**Commit**: `0857358`

---

## 9. Riferimenti

- **Analysis Report**: `docs/AnalisiCampi/ANALISI_MAPPING_REPORT.md`
- **Property Mapper Fields**: `docs/AnalisiCampi/PROPERTY_MAPPER_FIELDS.md`
- **Session Status**: `SESSION_STATUS.md`
- **Test XML**: `docs/test-property-complete-fixed.xml`

---

## 10. Problemi Identificati e Modifiche Necessarie

### 🔴 PROBLEMA CRITICO: Taxonomies vs Meta Fields

**Scoperto**: 2025-11-26 (Analisi API WPResidence)

Le API WPResidence distinguono tra:
1. **Meta Fields** (stringhe) - Salvati in `postmeta`
2. **Taxonomies** (array di slug) - Salvati come termini WordPress

**Attualmente SBAGLIATO**: Passiamo tutto come meta fields (stringhe).

---

### 10.1 Campi Taxonomy Mancanti/Errati

#### ❌ `property_city` (Taxonomy)

**API si aspetta**:
```json
"property_city": ["trento"]  // Array di slug
```

**Noi passiamo**:
```json
"property_city": "Trento"    // Stringa (meta field)
```

**Problema**:
- Le API NON salvano la taxonomy
- Il filtro di ricerca per città NON funziona
- La mappa geografica potrebbe non funzionare

**Soluzione necessaria**:
```php
// Nel Property Mapper - map_taxonomies_v3()
$taxonomies['property_city'] = [$this->slugify($xml_property['city'])];
// Input: "Trento" → Output: ["trento"]
```

**File da modificare**: `class-realestate-sync-property-mapper.php`
**Metodo**: `map_taxonomies_v3()`

---

#### ❌ `property_area` (Taxonomy)

**API si aspetta**:
```json
"property_area": ["centro-storico"]  // Array di slug
```

**Noi passiamo**:
```json
"property_area": "Centro Storico"    // Stringa (meta field)
```

**Problema**:
- La zona NON viene salvata come taxonomy
- Il filtro per zona NON funziona
- Le properties non vengono categorizzate per area

**Soluzione necessaria**:
```php
// Nel Property Mapper - map_taxonomies_v3()
$taxonomies['property_area'] = [$this->slugify($xml_property['zone'])];
// Input: "Centro Storico" → Output: ["centro-storico"]
```

**File da modificare**: `class-realestate-sync-property-mapper.php`
**Metodo**: `map_taxonomies_v3()`

---

#### ❌ `property_county_state` (Taxonomy) - MANCANTE

**API si aspetta**:
```json
"property_county_state": ["trentino-alto-adige"]  // Array di slug
```

**Noi passiamo**:
```json
// ❌ Non lo passiamo affatto
```

**Problema**:
- La regione/provincia NON viene salvata come taxonomy
- Il filtro geografico regionale NON funziona
- Impossibile filtrare properties per provincia

**Soluzione necessaria**:
```php
// Nel Property Mapper - map_taxonomies_v3()
$taxonomies['property_county_state'] = [$this->slugify($xml_property['province'])];
// Input: "Trento" → Output: ["trento"]

// Oppure usare la regione se più appropriato:
// Input: "Trentino-Alto Adige" → Output: ["trentino-alto-adige"]
```

**File da modificare**: `class-realestate-sync-property-mapper.php`
**Metodo**: `map_taxonomies_v3()`

---

#### ❌ `property_country` (Meta Field) - MANCANTE

**API si aspetta**:
```json
"property_country": "Italy"  // Stringa
```

**Noi passiamo**:
```json
// ❌ Non lo passiamo affatto
```

**Problema**:
- Il paese NON viene salvato
- Problemi per siti multi-nazione
- Mappa potrebbe non funzionare correttamente

**Soluzione necessaria**:
```php
// Nel Property Mapper - map_meta_fields_v3()
$meta['property_country'] = 'Italy';  // Hardcoded per Italia
// Oppure prendere da XML se disponibile
```

**File da modificare**: `class-realestate-sync-property-mapper.php`
**Metodo**: `map_meta_fields_v3()`

---

### 10.2 Metodo Helper Necessario: slugify()

Per convertire i nomi in slug per le taxonomies:

```php
/**
 * Convert string to URL-friendly slug for taxonomies
 *
 * @param string $text Text to slugify
 * @return string Slug
 */
private function slugify($text) {
    // Convert to lowercase
    $text = mb_strtolower($text, 'UTF-8');

    // Replace accented characters
    $transliteration = [
        'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'À' => 'a', 'È' => 'e', 'É' => 'e', 'Ì' => 'i', 'Ò' => 'o', 'Ù' => 'u'
    ];
    $text = strtr($text, $transliteration);

    // Replace spaces and special chars with hyphens
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    // Remove leading/trailing hyphens
    $text = trim($text, '-');

    return $text;
}
```

**Esempi**:
- "Trento" → "trento"
- "Centro Storico" → "centro-storico"
- "Trentino-Alto Adige" → "trentino-alto-adige"

---

### 10.3 Confronto: Prima vs Dopo

#### PRIMA (Attuale - Sbagliato):
```php
// API Writer output
$api_body = [
    'property_city' => 'Trento',           // ❌ Stringa (dovrebbe essere taxonomy)
    'property_area' => 'Centro Storico',   // ❌ Stringa (dovrebbe essere taxonomy)
    'property_county' => 'Trento',         // ✅ OK (meta field)
    'property_state' => 'Trentino-Alto Adige', // ✅ OK (meta field)
    // ❌ property_county_state mancante
    // ❌ property_country mancante
];
```

#### DOPO (Corretto):
```php
// Property Mapper output
$mapped = [
    'meta_fields' => [
        // Meta fields (stringhe)
        'property_address' => 'Piazza Duomo, Trento, Trento',
        'property_county' => 'Trento',
        'property_state' => 'Trentino-Alto Adige',
        'property_zip' => '38122',
        'property_country' => 'Italy',  // ✅ AGGIUNTO
        'property_latitude' => '46.0664',
        'property_longitude' => '11.1257',
    ],
    'taxonomies' => [
        // Taxonomies (array di slug)
        'property_city' => ['trento'],  // ✅ CORRETTO
        'property_area' => ['centro-storico'],  // ✅ CORRETTO
        'property_county_state' => ['trento'],  // ✅ AGGIUNTO
    ]
];
```

---

### 10.4 Impatto delle Modifiche

**Benefici**:
1. ✅ Filtri di ricerca per città/zona funzionanti
2. ✅ Mappa geografica corretta
3. ✅ Categorizzazione properties per location
4. ✅ Compatibilità completa con WPResidence
5. ✅ SEO migliorato (URL con slug corretti)

**Rischi**:
- ⚠️ Le taxonomies devono esistere in WordPress prima dell'import
- ⚠️ Possibile necessità di re-import delle properties esistenti

---

### 10.5 Priorità Implementazione

🔴 **ALTA**: `property_city`, `property_area` (critici per filtri)
🟡 **MEDIA**: `property_county_state` (utile per filtri regionali)
🟢 **BASSA**: `property_country` (hardcoded "Italy" accettabile)

---

**Ultima modifica**: 2025-11-26
**Autore**: Claude Code
