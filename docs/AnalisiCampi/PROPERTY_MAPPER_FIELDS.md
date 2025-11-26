# Property Mapper v3.3 - Campi Mappati

Documentazione completa dei campi mappati dal Property Mapper dal XML al database WordPress/WpResidence.

## 📋 STRUTTURA OUTPUT

Il Property Mapper restituisce un array con queste sezioni:
- `post_data` - Dati base del post WordPress
- `meta_fields` - Meta fields custom di WpResidence
- `taxonomies` - Tassonomie (categorie, città, features)
- `features` - Caratteristiche property (bagni, camere, etc.)
- `gallery` - Immagini e media
- `catasto` - Dati catastali
- `source_data` - Dati originali XML
- `content_hash_v3` - Hash per tracking modifiche

---

## 1️⃣ POST_DATA (WordPress Core Fields)

| Campo WordPress | Campo XML Source | Note |
|----------------|------------------|------|
| `post_type` | - | Sempre `'estate_property'` |
| `post_status` | - | Sempre `'publish'` |
| `post_author` | - | Sempre `1` |
| `post_title` | `title` o fallback | Usa `<title>` XML o genera da tipologia+comune |
| `post_content` | `descrizione` | Descrizione completa (cleaned HTML) |
| `post_excerpt` | `abstract` o `descrizione` | Riassunto breve |
| `post_name` | generato | Slug: titolo-sanitizzato + ID |
| `comment_status` | - | Sempre `'closed'` |
| `ping_status` | - | Sempre `'closed'` |

---

## 2️⃣ META_FIELDS (WpResidence Custom Fields)

### 🏠 Core Property Data
| Meta Key | Campo XML Source | Tipo | Note |
|----------|------------------|------|------|
| `property_price` | `price` | float | Prezzo vendita/affitto |
| `property_size` | `superficie` | int | Superficie mq (usa get_best_surface_area) |
| `property_address` | `indirizzo` + `civico` | string | Via + numero civico completo |
| `property_latitude` | `latitude` | string | Coordinata (convertita a string per API) |
| `property_longitude` | `longitude` | string | Coordinata (convertita a string per API) |

### 🗺️ Address Components (Google Maps)
| Meta Key | Campo XML Source | Tipo | Note |
|----------|------------------|------|------|
| `property_county` | `comune_istat` | string | "Trento" o "Bolzano" (derivato da ISTAT) |
| `property_state` | - | string | Sempre "Trentino-Alto Adige" |
| `property_zip` | `comune_istat` + `zona` | string | CAP (mapping 17 comuni + fallback) |
| `property_country` | - | string | Sempre "Italia" |
| `property_city` | `comune` | string | Nome comune |
| `property_area` | `zona` | string | Zona/Quartiere |
| `property_neighborhood` | `zona` | string | Zona (duplicato di property_area) |

### 🗺️ Google Maps Display Settings
| Meta Key | Valore | Note |
|----------|--------|------|
| `google_camera_angle` | '0' | Vista orizzontale standard |
| `property_google_view` | '1' | Abilita Street View |
| `property_hide_map_marker` | '0' | Mostra posizione esatta (Opzione A) |

### 🏗️ Building Data
| Meta Key | Campo XML Source | Tipo | Note |
|----------|------------------|------|------|
| `property_year` | `age` | int | Anno costruzione (validato 1800-2030) |
| `property_year_built` | `age` | int | Duplicato property_year |
| `property_floors` | `piani` | int | Numero piani edificio |
| `property_floor` | `piano` | int | Piano dell'immobile |

### ⚡ Energy Performance
| Meta Key | Campo XML Source | Tipo | Note |
|----------|------------------|------|------|
| `property_energy_class` | `classe_energetica_id` | string | A4, A3, A2, A1, B, C, D, E, F, G |
| `property_energy_index` | `ipe` | float | IPE valore numerico |
| `property_energy_unit` | `ipe_unit` | string | Unità misura IPE |
| `property_energy_certificate` | `ape` | string | Certificato energetico (se ≠ 'ape2015') |

### 🏢 Property Details
| Meta Key | Campo XML Source | Tipo | Note |
|----------|------------------|------|------|
| `property_status` | `contract` | string | vendita/affitto/affitto_turistico |
| `property_rooms` | `total_locali` | int | Numero locali totali |
| `property_bedrooms` | `total_camere` | int | Numero camere da letto |
| `property_bathrooms` | `total_bagni` | int | Numero bagni |
| `property_balcony` | `balconi` | int | Numero balconi |
| `property_terrace` | `terrazzo` | string | Presenza terrazzo (yes/no) |
| `property_garage` | `posti_auto_coperti` | int | Numero posti auto coperti |
| `property_parking_spaces` | `posti_auto_scoperti` | int | Numero posti auto scoperti |
| `property_lot_size` | `superficie_scoperta` | int | Superficie scoperta/giardino |

### 🔧 Property Condition & Features
| Meta Key | Campo XML Source | Tipo | Note |
|----------|------------------|------|------|
| `property_maintenance_status` | `info[9]` | string | Stato manutenzione (mapping 0-9) |
| `property_position` | `info[56]` | string | Posizione commerciale (mapping 0-9) |
| `property_furnished` | `arredato` | int | 0=Non arredato, 1=Arredato, 2=Parziale |
| `property_heating_type` | `info[62]` o `riscaldamento` | string | Tipo riscaldamento |
| `property_elevator` | `ascensore` | string | yes/no |
| `property_balcony_terrace` | `balconi` + `terrazzo` | string | Combinato balcone/terrazzo |

### 📐 Extended Dimensions
| Meta Key | Campo XML Source | Tipo | Note |
|----------|------------------|------|------|
| `property_kitchen_size` | `superficie_cucina` | float | Superficie cucina mq |
| `property_living_size` | `superficie_soggiorno` | float | Superficie soggiorno mq |
| `property_terrace_size` | `superficie_terrazzo` | float | Superficie terrazzo mq |
| `property_balcony_size` | `superficie_balcone` | float | Superficie balcone mq |
| `property_garden_size` | `superficie_giardino` | float | Superficie giardino mq |
| `property_warehouse_size` | `superficie_magazzino` | float | Superficie magazzino mq |
| `property_basement_size` | `superficie_seminterrato` | float | Superficie seminterrato mq |

### 🔗 References & Tracking
| Meta Key | Campo XML Source | Tipo | Note |
|----------|------------------|------|------|
| `import_id` | `id` | string | ID property nel sistema origine |
| `property_agency_code` | `agency_code` | string | Codice agenzia di riferimento |
| `property_source_url` | `url` | string | URL property nel sistema origine |
| `property_subcategory` | `categorie_micro_id` | int | Micro-categoria (1-97) |
| `property_last_sync` | - | datetime | Timestamp ultimo import (auto) |
| `content_hash_v3` | - | string | Hash contenuto per tracking modifiche |

### 📋 Additional Info Fields
| Meta Key | Campo XML Source | Tipo | Note |
|----------|------------------|------|------|
| `property_info_*` | `info[*]` array | mixed | Array info[0-99] mappato in campi separati |

---

## 3️⃣ TAXONOMIES (WpResidence Taxonomies)

### 🏘️ Property Category
| Taxonomy | Campo XML Source | Note |
|----------|------------------|------|
| `property_category` | `contract` | vendita/affitto/affitto-turistico |

### 🏠 Property Type
| Taxonomy | Campo XML Source | Note |
|----------|------------------|------|
| `property_type_category` | `categorie_id` + mapping | Appartamento, Villa, Terreno, etc. (13 tipi base) |

### 📍 Property City
| Taxonomy | Campo XML Source | Note |
|----------|------------------|------|
| `property_city` | `comune` | Nome comune (es. "Trento", "Bolzano") |

### ⭐ Property Features
| Taxonomy | Campo XML Source | Note |
|----------|------------------|------|
| `property_features` | `info[]` array | Features multipli mappati (piscina, garage, etc.) |

---

## 4️⃣ FEATURES (Property Characteristics)

Caratteristiche mappate dal campo `info[]` XML:

| Feature WpResidence | Campo XML | Valore | Note |
|---------------------|-----------|--------|------|
| Piscina | `info[39]` | 1 | Presenza piscina |
| Giardino | `superficie_giardino` | >0 | Presenza giardino |
| Garage | `posti_auto_coperti` | >0 | Posto auto coperto |
| Ascensore | `ascensore` | 1 | Presenza ascensore |
| Aria condizionata | `info[4]` | 1 | Climatizzazione |
| Riscaldamento | `riscaldamento` | presente | Tipo riscaldamento |
| Balcone | `balconi` | >0 | Presenza balcone |
| Terrazzo | `terrazzo` | 1 | Presenza terrazzo |
| Cantina | `info[77]` | 1 | Presenza cantina |
| Soffitta | `info[78]` | 1 | Presenza soffitta |

*(+ altre features mappate da info[0-99])*

---

## 5️⃣ GALLERY (Media Files)

| Campo | Campo XML Source | Tipo | Note |
|-------|------------------|------|------|
| `gallery` | `file_allegati` array | array | Tutte le immagini property |
| Item structure | - | - | {url, nome_file, ordine, tipo} |
| `featured_image` | `file_allegati[0]` | string | Prima immagine come featured |

---

## 6️⃣ CATASTO (Dati Catastali)

| Campo | Campo XML Source | Tipo | Note |
|-------|------------------|------|------|
| `property_cadastral_data` | `catasto[]` array | array | Array dati catastali completi |
| Sezione | `catasto[*][sezione]` | string | Sezione catastale |
| Foglio | `catasto[*][foglio]` | string | Foglio catastale |
| Particella | `catasto[*][particella]` | string | Numero particella |
| Subalterno | `catasto[*][subalterno]` | string | Subalterno |

---

## 7️⃣ SOURCE_DATA (Dati Originali XML)

Contiene TUTTI i dati XML originali non trasformati per:
- Debugging
- Tracking modifiche
- Riferimento futuro
- Agency linking

**Campo aggiunto**: `import_id` = `id` (per compatibilità WP Importer)

---

## 🔧 MAPPING SPECIALI

### CAP (ZIP Code) Mapping
17 comuni mappati + fallback provincia:

| Comune ISTAT | CAP |
|--------------|-----|
| 022205 | 38122 (Trento centro) |
| 022001 | 38062 (Arco) |
| 022178 | 38068 (Rovereto) |
| ... | ... |
| Fallback TN | 38100 |
| Fallback BZ | 39100 |

### Energy Class Mapping
| ID XML | Classe |
|--------|--------|
| 10 | A4 |
| 1 | A3 |
| 2 | A2 |
| 3 | A1 |
| 4 | B |
| 5 | C |
| 6 | D |
| 7 | E |
| 8 | F |
| 9 | G |

### Property Type Mapping (13 Types)
| Categoria ID | Tipo WpResidence |
|--------------|------------------|
| 1 | Appartamento |
| 2 | Attico |
| 3 | Villa |
| 4 | Casa singola |
| 5 | Casa a schiera |
| 6 | Rustico/Casale |
| 7 | Terreno |
| 8 | Garage/Posto auto |
| 9 | Locale commerciale |
| 10 | Ufficio |
| 11 | Capannone |
| 12 | Albergo |
| 13 | Stanze |

### Micro-Categories (43 Types)
Mapping dettagliato micro-categorie (ID 1-97, 43 mantenute, 56 escluse)

---

## 📝 NOTE VERSIONING

- **v3.0**: Base structure con post_data, meta_fields, taxonomies
- **v3.1**: Aggiunti energy class, maintenance status, position
- **v3.2**: Aggiunti extended dimensions, micro-categories (43 types)
- **v3.3**: Aggiunti Google Maps settings, address components, CAP mapping

---

**File generato da**: Property Mapper Class
**Versione**: v3.3 - OPZIONE A Phase 1 & 2
**Data**: 2025-11-26
**Autore**: Property Mapper Documentation Tool
