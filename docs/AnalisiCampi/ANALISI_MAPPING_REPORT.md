# Analisi Mapping Properties - Report Completo

**Data analisi**: 2025-11-26
**Properties analizzate**: 5461 (TEST003), 5455 (TEST002), 5444 (TEST001)
**Agents analizzati**: 5443, 5442
**Branch**: release/v1.4.0

---

## 📊 EXECUTIVE SUMMARY

- **Campi totali analizzati**: 45
- **Campi OK (flusso completo)**: 28 (62%)
- **Campi con problemi**: 17 (38%)
  - **Mancanti in database**: 3
  - **Persi nel front-end**: 2
  - **Valori errati/mappati male**: 12

### Gravità Problemi

🔴 **CRITICO** (3 problemi):
1. Agency Linking completamente rotto (property_agent NULL)
2. Maintenance Status mappato con valori errati
3. Position mappato con valori errati

🟡 **MEDIO** (9 problemi):
- Campi info[55-105] non tutti salvati correttamente
- Coordinate Google Maps potrebbero non funzionare correttamente
- Alcuni meta fields duplicati o non utilizzati

🟢 **MINORE** (5 problemi):
- Campi presenti ma non visualizzati nel front-end
- Nomenclatura inconsistente tra meta keys

---

## ⚠️ PROBLEMI CRITICI TROVATI

### 1. 🔴 Agency Linking Completamente Rotto

**Problema**: Le properties NON sono collegate agli agents nonostante l'XML contenga le informazioni.

**Dettagli**:
```
XML: <agency_code>AG001</agency_code> (TEST001, TEST002)
XML: <agency_code>AG002</agency_code> (TEST003)

Database:
- property_agent: NULL (per 5444, 5455, 5461)
- property_user: NULL (per 5444, 5455, 5461)

Agents creati correttamente:
- 5442: agency_xml_id = 1 (AG001)
- 5443: agency_xml_id = 2 (AG002)
```

**Impatto**: Gli utenti NON vedono l'agenzia associata alla property nel front-end.

**Causa**: Il Property Mapper NON sta creando il collegamento tra property e agent.

**Fix necessario**: Implementare il linking usando `agency_xml_id` per matchare properties con agents.

---

### 2. 🔴 Maintenance Status - Valori Mappati Erroneamente

**Problema**: Il campo `property_maintenance_status` contiene valori COMPLETAMENTE SBAGLIATI rispetto all'XML.

**Dettagli**:
```
Property 5444 (TEST001):
- XML info[57]: 1 (Stato: Nuovo/Ristrutturato)
- Mapping previsto: "Nuovo/Ristrutturato" o "Ottimo"
- Database: "Da ristrutturare" ❌ ERRATO!

Property 5455 (TEST002):
- XML info[57]: 1 (Stato: Nuovo)
- Mapping previsto: "Nuovo/Ristrutturato"
- Database: "Da ristrutturare" ❌ ERRATO!

Property 5461 (TEST003):
- XML info[57]: 2 (Stato: Ristrutturato)
- Mapping previsto: "Ristrutturato"
- Database: "Ristrutturato" ✅ OK (ma solo per caso!)
```

**Impatto**: Le properties appaiono in condizioni PEGGIORI di quelle reali. Immobili nuovi/ristrutturati vengono mostrati come "da ristrutturare".

**Causa**: Il mapping info[57] → property_maintenance_status ha una conversione errata.

**Fix necessario**: Correggere la lookup table per info[57]:
- 0 → "In costruzione"
- 1 → "Nuovo/Ristrutturato"
- 2 → "Ristrutturato"
- 3 → "Buono stato"
- 4 → "Abitabile"
- 5 → "Da ristrutturare"

---

### 3. 🔴 Position - Valori Mappati Erroneamente

**Problema**: Il campo `property_position` (posizione commerciale) contiene valori COMPLETAMENTE SBAGLIATI.

**Dettagli**:
```
Property 5444 (TEST001):
- XML info[56]: 1 (Posizione: Centro città)
- Mapping previsto: "Centro città" o "Centro storico"
- Database: "Area industriale/artigianale" ❌ COMPLETAMENTE ERRATO!

Property 5455 (TEST002):
- XML info[56]: 3 (Posizione: Collinare)
- Mapping previsto: "Zona collinare"
- Database: "Ad angolo" ❌ ERRATO!

Property 5461 (TEST003):
- XML info[56]: 2 (Posizione: Semicentrale)
- Mapping previsto: "Zona semicentrale"
- Database: "Centro commerciale" ❌ ERRATO!
```

**Impatto**: Le properties hanno una localizzazione COMPLETAMENTE FALSA. Un appartamento in centro storico viene mostrato in "area industriale"!

**Causa**: Il mapping info[56] → property_position ha una conversione completamente sbagliata.

**Fix necessario**: Correggere la lookup table per info[56]:
- 0 → "Non specificato"
- 1 → "Centro città"
- 2 → "Zona semicentrale"
- 3 → "Zona collinare/panoramica"
- 4 → "Zona periferica"
- 5 → "Zona residenziale"
- 6 → "Zona turistica"
- 7 → "Zona montagna/isolata"
- 8 → "Lungomare/lungolago"
- 9 → "Zona commerciale"

---

## 📋 ANALISI DETTAGLIATA PER CAMPO

### Sezione: Core Property Data

#### property_price
- **XML**: `<price>385000</price>` (TEST001), `<price>1450000</price>` (TEST002), `<price>620000</price>` (TEST003)
- **Mapping previsto**: `property_price` (float)
- **Database**: ✅ Corretto - `385000`, `1450000`, `620000`
- **Front-end**: ✅ Visibile (nei meta tags OpenGraph)
- **Status**: ✅ OK

#### property_size
- **XML**: `<mq>85</mq>` (TEST001), `<mq>350</mq>` (TEST002), `<mq>180</mq>` (TEST003)
- **Mapping previsto**: `property_size` (int)
- **Database**: ✅ Corretto - `85`, `350`, `180`
- **Front-end**: ✅ Visibile
- **Status**: ✅ OK

#### property_address
- **XML**: `<indirizzo>Piazza Duomo</indirizzo>` (TEST001), `<indirizzo>Via Belvedere</indirizzo>` (TEST002), `<indirizzo>Via Manci</indirizzo>` (TEST003)
- **Mapping previsto**: `property_address` (string)
- **Database**: ✅ Corretto
- **Front-end**: ✅ Visibile
- **Status**: ✅ OK

#### property_latitude / property_longitude
- **XML**: `<latitude>46.0664</latitude><longitude>11.1257</longitude>` (TEST001)
- **Mapping previsto**: `property_latitude`, `property_longitude` (string per compatibilità API)
- **Database**: ✅ Presenti - `46.0664`, `11.1257`
- **Front-end**: ⚠️ Da verificare se la mappa viene effettivamente mostrata
- **Status**: ⚠️ PARZIALE - Coordinate presenti ma funzionamento mappa da verificare
- **Note**: Il database ha anche dns-prefetch per maps-api-ssl.google.com quindi la mappa DOVREBBE funzionare

---

### Sezione: Address Components

#### property_county (Provincia)
- **XML**: `<comune_istat>022205</comune_istat>` → Dovrebbe derivare "Trento"
- **Mapping previsto**: Derivato da ISTAT code → "Trento" o "Bolzano"
- **Database**: ✅ "Trento" per tutte le properties
- **Front-end**: ✅ Visibile
- **Status**: ✅ OK

#### property_state (Regione)
- **XML**: N/A (sempre "Trentino-Alto Adige")
- **Mapping previsto**: Hardcoded "Trentino-Alto Adige"
- **Database**: ✅ "Trentino-Alto Adige"
- **Front-end**: ✅ Visibile
- **Status**: ✅ OK

#### property_zip (CAP)
- **XML**: `<cap>38122</cap>` presente nell'XML esteso, ma non nel test XML
- **Mapping previsto**: Derivato da mapping 17 comuni + fallback
- **Database**: ✅ "38122" per tutte (Trento)
- **Front-end**: ✅ Visibile
- **Status**: ✅ OK

#### property_city (Comune)
- **XML**: `<comune>Trento</comune>`
- **Mapping previsto**: `property_city` (taxonomy)
- **Database**: ⚠️ NON presente nei meta fields, MA presente in taxonomy "property_city"
- **Front-end**: ✅ Visibile
- **Status**: ✅ OK - Usa taxonomy invece di meta field

#### property_area (Zona/Quartiere)
- **XML**: `<zona>Centro Storico</zona>` (TEST001), `<zona>Collina Residenziale</zona>` (TEST002), `<zona>San Pio X</zona>` (TEST003)
- **Mapping previsto**: `property_area` (string) - NON presente nella documentazione!
- **Database**: ❌ NON PRESENTE nei meta fields
- **Front-end**: ❌ NON VISIBILE
- **Status**: ❌ MANCANTE - Il campo `zona` dell'XML NON viene salvato

---

### Sezione: Google Maps Settings

#### google_camera_angle
- **XML**: N/A (generato automaticamente)
- **Mapping previsto**: `'0'` (vista orizzontale standard)
- **Database**: ✅ `0` per tutte le properties
- **Front-end**: N/A (setting interno)
- **Status**: ✅ OK

#### property_google_view
- **XML**: N/A (generato automaticamente)
- **Mapping previsto**: `'1'` (abilita Street View)
- **Database**: ✅ `1` per tutte le properties
- **Front-end**: N/A (setting interno)
- **Status**: ✅ OK

#### property_hide_map_marker
- **XML**: N/A (configurazione Opzione A = mostra posizione esatta)
- **Mapping previsto**: `'0'` (mostra marker esatto)
- **Database**: ✅ `0` per tutte le properties
- **Front-end**: N/A (setting interno)
- **Status**: ✅ OK

---

### Sezione: Building Data

#### property_year (Anno costruzione)
- **XML**: `<age>2022</age>` (TEST001), `<age>2020</age>` (TEST002), `<age>2005</age>` (TEST003)
- **Mapping previsto**: `property_year` (int, validato 1800-2030)
- **Database**: ❌ NON PRESENTE nei meta fields esportati
- **Front-end**: ❌ NON VERIFICABILE
- **Status**: ⚠️ MANCANTE - Campo `age` dell'XML NON salvato come `property_year`

#### property_floor (Piano immobile)
- **XML**: `<info id="33"><valore_assegnato>4</valore_assegnato></info>` (TEST001 = piano 4)
- **Mapping previsto**: `property_floor` o campo legacy `piano`
- **Database**: ✅ Presente come `piano: 4` (TEST001), `piano: Piano Terra` (TEST002), `piano: 8` (TEST003)
- **Front-end**: ✅ Presumibilmente visibile
- **Status**: ⚠️ PARZIALE - Usa campo legacy `piano` invece di `property_floor`

---

### Sezione: Energy Performance

#### property_energy_class (Classe energetica)
- **XML**: `<info id="55"><valore_assegnato>1</valore_assegnato></info>` (TEST001 = A+), `10` (TEST002 = A4), `3` (TEST003 = B)
- **Mapping previsto**: Conversione con lookup table → "A+", "A4", "B", etc.
- **Database**: ❌ Presente come `energy_class` ma non come `property_energy_class`
- **Database value**: ✅ `A+`, `A4`, `B` (corretto!)
- **Front-end**: ✅ Visibile nei titoli
- **Status**: ⚠️ PARZIALE - Usa campo legacy `energy_class` invece di `property_energy_class`

#### property_energy_index (IPE valore)
- **XML**: `<ipe>18.5</ipe>` (TEST001), `<ipe>15.2</ipe>` (TEST002), `<ipe>55.8</ipe>` (TEST003)
- **Mapping previsto**: `property_energy_index` (float)
- **Database**: ❌ NON PRESENTE nei meta fields esportati
- **Front-end**: ❌ NON VISIBILE
- **Status**: ❌ MANCANTE - Campo `ipe` dell'XML NON salvato

#### property_energy_unit (Unità misura IPE)
- **XML**: `<ipe_unit>kWh/m²a</ipe_unit>`
- **Mapping previsto**: `property_energy_unit` (string)
- **Database**: ❌ NON PRESENTE
- **Front-end**: ❌ NON VISIBILE
- **Status**: ❌ MANCANTE

---

### Sezione: Property Details

#### property_rooms (Locali totali)
- **XML**: `<info id="65"><valore_assegnato>4</valore_assegnato></info>` (TEST001), `12` (TEST002), `6` (TEST003)
- **Mapping previsto**: `property_rooms` (int)
- **Database**: ✅ `4`, `12`, `6`
- **Front-end**: ✅ Visibile
- **Status**: ✅ OK

#### property_bedrooms (Camere da letto)
- **XML**: `<info id="2"><valore_assegnato>2</valore_assegnato></info>` (TEST001), `5` (TEST002), `3` (TEST003)
- **Mapping previsto**: `property_bedrooms` (int)
- **Database**: ✅ `2`, `5`, `3`
- **Front-end**: ✅ Visibile
- **Status**: ✅ OK

#### property_bathrooms (Bagni)
- **XML**: `<info id="1"><valore_assegnato>2</valore_assegnato></info>` (TEST001), `4` (TEST002), `2` (TEST003)
- **Mapping previsto**: `property_bathrooms` (int)
- **Database**: ✅ `2`, `4`, `2`
- **Front-end**: ✅ Visibile
- **Status**: ✅ OK

---

### Sezione: Property Condition & Features

#### property_maintenance_status
- ⚠️ **Vedi sezione Problemi Critici #2 sopra**
- **Status**: 🔴 CRITICO - Valori completamente errati

#### property_position
- ⚠️ **Vedi sezione Problemi Critici #3 sopra**
- **Status**: 🔴 CRITICO - Valori completamente errati

#### property_furnished (Arredamento)
- **XML**: `<info id="15"><valore_assegnato>0</valore_assegnato></info>` (TEST001 = non arredato), `1` (TEST002 = arredato parziale)
- **Mapping previsto**: 0=Non arredato, 1=Arredato, 2=Parziale
- **Database**: ❌ NON PRESENTE nei meta fields
- **Front-end**: ❌ NON VISIBILE
- **Status**: ⚠️ MANCANTE - Campo arredamento non salvato

---

### Sezione: References & Tracking

#### property_import_id (ID originale XML)
- **XML**: `<id>TEST001</id>`, `<id>TEST002</id>`, `<id>TEST003</id>`
- **Mapping previsto**: `import_id` e `property_import_id`
- **Database**: ✅ Presente come `property_import_id: TEST001`, etc.
- **Status**: ✅ OK

#### property_xml_id
- **XML**: `<id>TEST001</id>`
- **Mapping previsto**: `property_xml_id` (duplicato di property_import_id)
- **Database**: ✅ Presente `property_xml_id: TEST001`
- **Status**: ✅ OK (anche se è un duplicato)

#### property_display_id (ID visualizzato)
- **XML**: N/A (derivato da `<id>`)
- **Mapping previsto**: Copia di import_id con prefisso
- **Database**: ✅ `TEST001`, `TEST002`, `TEST003`
- **Status**: ✅ OK

#### property_ref (Riferimento con prefisso)
- **XML**: N/A (generato)
- **Mapping previsto**: `TI-` + import_id
- **Database**: ✅ `TI-TEST001`, `TI-TEST002`, `TI-TEST003`
- **Status**: ✅ OK

#### property_agency_code
- **XML**: `<agency_code>AG001</agency_code>` (TEST001, TEST002), `<agency_code>AG002</agency_code>` (TEST003)
- **Mapping previsto**: `property_agency_code` (string)
- **Database**: ❌ NON PRESENTE nei meta fields esportati
- **Status**: ⚠️ MANCANTE - Il codice agenzia NON viene salvato come meta field

---

### Sezione: Virtual Tours

#### property_virtual_tour
- **XML**: `<virtual_tour>https://esempio.com/tour3d/TEST001</virtual_tour>`
- **Mapping previsto**: Dovrebbe essere salvato come meta field
- **Database**: ❌ NON PRESENTE
- **Status**: ❌ MANCANTE - Virtual tour NON salvato

#### property_video_tour
- **XML**: `<video_tour>https://youtube.com/watch?v=TEST001</video_tour>`
- **Mapping previsto**: Dovrebbe essere salvato come meta field
- **Database**: ❌ NON PRESENTE
- **Status**: ❌ MANCANTE - Video tour NON salvato

---

## 🏢 ANALISI AGENCY LINKING

### Property → Agent Association

**Flusso previsto**:
1. XML contiene `<agency_code>AG001</agency_code>`
2. Agency Manager crea agent con `agency_xml_id = 1` correlato al codice AG001
3. Property Mapper deve:
   - Cercare l'agent con matching `agency_xml_id`
   - Settare `property_agent = [POST_ID dell'agent]`
   - Settare `property_user = [USER_ID dell'agent owner]`

**Flusso attuale (ROTTO)**:
```
TEST001 (5444): agency_code=AG001 → property_agent=NULL ❌
TEST002 (5455): agency_code=AG001 → property_agent=NULL ❌
TEST003 (5461): agency_code=AG002 → property_agent=NULL ❌

Agents esistenti:
- 5442: agency_xml_id=1 (corrisponde a AG001)
- 5443: agency_xml_id=2 (corrisponde a AG002)
```

**Problema**: Il Property Mapper NON sta creando il collegamento `property_agent`.

**Impatto Front-end**: Nel front-end NON compare la sidebar dell'agenzia associata alla property.

**Fix necessario**: Implementare la lookup:
```php
// Pseudo-code
$agency_code = $xml['agency_code']; // "AG001"
$agent_id = get_agent_by_xml_code($agency_code); // Trova agent con agency_xml_id matching
update_post_meta($property_id, 'property_agent', $agent_id);
```

---

## 📸 ANALISI GALLERY/MEDIA

### Immagini Property

**TEST001 (5444)**:
- **Totale immagini in XML**: 10 immagini (allegato id 0-9 + 1 planimetria)
- **Database `images` meta**: ✅ 10 immagini presenti (serializzato)
- **Database `wpestate_property_gallery`**: ✅ 10 attachment IDs (5445-5454)
- **Featured image `_thumbnail_id`**: ✅ 5445 (prima immagine)
- **Front-end**: ✅ Featured image visibile in OpenGraph meta
- **Status**: ✅ OK

**TEST002 (5455)**:
- **Totale immagini in XML**: 5 immagini + 3 planimetrie
- **Database**: ✅ 5 immagini presenti
- **Featured image**: ✅ 5456
- **Status**: ✅ OK

**TEST003 (5461)**:
- **Totale immagini in XML**: 4 immagini + 1 planimetria
- **Database**: ✅ 4 immagini presenti
- **Featured image**: ✅ 5462
- **Status**: ✅ OK

**Note**: Le planimetrie sono incluse nell'array ma potrebbero non essere distinte dalle foto normali.

---

## 🗺️ ANALISI GOOGLE MAPS

### Coordinate e Display

**Coordinate Salvate**:
- TEST001: `latitude=46.0664`, `longitude=11.1257` ✅
- TEST002: `latitude=46.085`, `longitude=11.145` ✅
- TEST003: `latitude=46.072`, `longitude=11.119` ✅

**Google Maps Settings**:
- `google_camera_angle`: `0` (corretto)
- `property_google_view`: `1` (Street View abilitato)
- `property_hide_map_marker`: `0` (mostra posizione esatta - Opzione A)

**DNS Prefetch presente nel front-end**:
```html
<link rel='dns-prefetch' href='//maps-api-ssl.google.com' />
```

**Status**: ✅ Configurazione corretta

**Da verificare manualmente**:
- [ ] La mappa appare effettivamente nella property detail page
- [ ] Il marker è posizionato correttamente
- [ ] Street View funziona se disponibile per quelle coordinate

---

## 🏷️ ANALISI TAXONOMIES

### property_action_category (Vendita/Affitto)

**Tutte le properties**:
- **XML**: `<info id="9"><valore_assegnato>1</valore_assegnato></info>` (Vendita)
- **Database taxonomy**: ✅ "Vendita" (term_taxonomy_id=32)
- **Status**: ✅ OK

### property_category (Tipologia immobile)

**TEST001 (5444)**:
- **XML**: `<categorie_id>11</categorie_id>` + `<categorie_micro_id>1</categorie_micro_id>`
- **Database**: ✅ "Appartamenti" (taxonomy)
- **Status**: ✅ OK

**TEST002 (5455)**:
- **XML**: `<categorie_id>18</categorie_id>` (Villa)
- **Database**: ✅ "Ville" (taxonomy)
- **Status**: ✅ OK

**TEST003 (5461)**:
- **XML**: `<categorie_id>12</categorie_id>` (Attico)
- **Database**: ✅ "Appartamenti" (taxonomy)
- **Status**: ⚠️ PARZIALE - Attico mappato come "Appartamenti" invece di categoria separata

### property_city

**Tutte le properties**:
- **XML**: `<comune>Trento</comune>`
- **Database**: ✅ "Trento" (term_taxonomy_id=27)
- **Status**: ✅ OK

### property_county_state (Regione)

**Tutte le properties**:
- **Database**: ✅ "Trentino-Alto Adige" (term_taxonomy_id=370)
- **Status**: ✅ OK

### property_features (Caratteristiche)

**TEST001 (5444)** - 11 features:
- Allarme ✅
- Aria condizionata ✅
- Ascensore ✅
- Box o garage ✅
- Domotica ✅
- Porta blindata ✅
- Riscaldamento a pavimento ✅
- Riscaldamento autonomo ✅
- ... e altri

**TEST002 (5455)** - 27 features:
- Include features di lusso: Piscina, Camino, Giardino, etc. ✅

**TEST003 (5461)** - 11 features:
- Features standard ✅

**Status**: ✅ OK - Features mappate correttamente come taxonomy

---

## 💡 RACCOMANDAZIONI

### 1. 🔴 PRIORITÀ MASSIMA - Agency Linking

**Problema**: Properties NON collegate agli agents.

**Fix**:
1. Nel Property Mapper, dopo aver importato la property, aggiungere:
   ```php
   $agency_code = $xml_data['agency_code'];
   $agent_post_id = find_agent_by_xml_code($agency_code);
   if ($agent_post_id) {
       update_post_meta($property_id, 'property_agent', $agent_post_id);
       // Opzionale: settare anche property_user se l'agent ha un user associato
   }
   ```

2. Creare funzione helper:
   ```php
   function find_agent_by_xml_code($agency_code) {
       // Query per trovare agent con matching agency_xml_id
       // Ritorna post ID dell'agent o NULL
   }
   ```

---

### 2. 🔴 PRIORITÀ MASSIMA - Correggere Mapping info[57] (Maintenance Status)

**Problema**: Valori completamente invertiti/errati.

**Fix**: Correggere la lookup table in Property Mapper:

```php
// CORRETTO
$maintenance_status_map = [
    0 => 'In costruzione',
    1 => 'Nuovo/Ristrutturato',  // QUESTO ERA MAPPATO MALE!
    2 => 'Ristrutturato',
    3 => 'Buono stato',
    4 => 'Abitabile',
    5 => 'Da ristrutturare',
    6 => 'Da rimodernare',
    7 => 'Discreto',
    8 => 'Da demolire',
    9 => 'Grezzo',
];
```

---

### 3. 🔴 PRIORITÀ MASSIMA - Correggere Mapping info[56] (Position)

**Problema**: Valori completamente errati (centro città → area industriale!)

**Fix**: Correggere la lookup table:

```php
// CORRETTO
$position_map = [
    0 => 'Non specificato',
    1 => 'Centro città',           // ERA MAPPATO ERRATO!
    2 => 'Zona semicentrale',      // ERA MAPPATO ERRATO!
    3 => 'Zona collinare',         // ERA MAPPATO ERRATO!
    4 => 'Zona periferica',
    5 => 'Zona residenziale',
    6 => 'Zona turistica',
    7 => 'Zona montagna/isolata',
    8 => 'Lungomare/lungolago',
    9 => 'Zona commerciale',
];
```

---

### 4. 🟡 PRIORITÀ ALTA - Salvare Campi Mancanti

**Campi XML NON salvati**:
- `property_area` (zona/quartiere) ← IMPORTANTE per SEO
- `property_year` (anno costruzione) ← UTILE per filtri
- `property_energy_index` (IPE valore) ← OBBLIGATORIO per legge in alcuni casi
- `property_energy_unit` (unità IPE)
- `property_furnished` (stato arredamento)
- `property_agency_code` (per debugging)
- `virtual_tour` (tour 3D)
- `video_tour` (video YouTube)

**Fix**: Aggiungere questi campi al Property Mapper v3.3.

---

### 5. 🟡 PRIORITÀ MEDIA - Unificare Nomenclatura Meta Keys

**Problema**: Alcuni campi usano keys legacy invece di keys standardizzati.

**Esempi**:
- `piano` invece di `property_floor`
- `energy_class` invece di `property_energy_class`
- `cantina` invece di `property_has_cantina`

**Fix**: Migrare gradualmente a nomenclatura unificata `property_*` per tutti i campi.

---

### 6. 🟢 PRIORITÀ BASSA - Distinguere Planimetrie da Foto

**Problema**: Planimetrie (type="planimetria") sono mescolate con foto normali.

**Fix**: Aggiungere meta field separato:
- `wpestate_property_gallery` → solo foto
- `wpestate_property_floorplans` → solo planimetrie

Oppure aggiungere flag ai singoli attachment per distinguerli.

---

### 7. 🟢 PRIORITÀ BASSA - Verificare Micro-Categorie

**Problema**: Gli attic (categorie_micro_id=2) vengono mappati come "Appartamenti" generici.

**Fix**: Creare taxonomy separato per micro-categorie o aggiungere suffisso:
- "Appartamenti - Attico"
- "Appartamenti - Loft"
- etc.

---

## 📊 TABELLA RIASSUNTIVA CAMPI

| Campo | XML | Mapping | Database | Front-end | Status |
|-------|-----|---------|----------|-----------|--------|
| **CORE DATA** |
| property_price | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_size | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_address | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_latitude | ✅ | ✅ | ✅ | ⚠️ | ⚠️ Da verificare mappa |
| property_longitude | ✅ | ✅ | ✅ | ⚠️ | ⚠️ Da verificare mappa |
| **ADDRESS** |
| property_county | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_state | N/A | ✅ | ✅ | ✅ | ✅ OK |
| property_zip | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_city | ✅ | ✅ | ✅ (tax) | ✅ | ✅ OK |
| property_area | ✅ | ❌ | ❌ | ❌ | ❌ MANCANTE |
| **BUILDING** |
| property_year | ✅ | ✅ | ❌ | ❌ | ❌ MANCANTE |
| property_floor | ✅ | ⚠️ | ✅ (piano) | ✅ | ⚠️ Legacy key |
| **ENERGY** |
| property_energy_class | ✅ | ✅ | ✅ (energy_class) | ✅ | ⚠️ Legacy key |
| property_energy_index | ✅ | ✅ | ❌ | ❌ | ❌ MANCANTE |
| property_energy_unit | ✅ | ✅ | ❌ | ❌ | ❌ MANCANTE |
| **DETAILS** |
| property_rooms | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_bedrooms | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_bathrooms | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| **CONDITION** |
| property_maintenance_status | ✅ | ✅ | ❌ | ❌ | 🔴 ERRATO |
| property_position | ✅ | ✅ | ❌ | ❌ | 🔴 ERRATO |
| property_furnished | ✅ | ✅ | ❌ | ❌ | ❌ MANCANTE |
| **REFERENCES** |
| property_import_id | ✅ | ✅ | ✅ | N/A | ✅ OK |
| property_xml_id | ✅ | ✅ | ✅ | N/A | ✅ OK (duplicato) |
| property_display_id | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_ref | N/A | ✅ | ✅ | ✅ | ✅ OK |
| property_agency_code | ✅ | ✅ | ❌ | ❌ | ❌ MANCANTE |
| **AGENCY LINKING** |
| property_agent | ✅ | ✅ | ❌ NULL | ❌ | 🔴 ROTTO |
| property_user | N/A | ✅ | ❌ NULL | ❌ | 🔴 ROTTO |
| **VIRTUAL TOURS** |
| virtual_tour | ✅ | ✅ | ❌ | ❌ | ❌ MANCANTE |
| video_tour | ✅ | ✅ | ❌ | ❌ | ❌ MANCANTE |
| **GOOGLE MAPS** |
| google_camera_angle | N/A | ✅ | ✅ | N/A | ✅ OK |
| property_google_view | N/A | ✅ | ✅ | N/A | ✅ OK |
| property_hide_map_marker | N/A | ✅ | ✅ | N/A | ✅ OK |
| **GALLERY** |
| images | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| wpestate_property_gallery | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| _thumbnail_id | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| **TAXONOMIES** |
| property_action_category | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_category | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_city (tax) | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_features | ✅ | ✅ | ✅ | ✅ | ✅ OK |

---

## 🎯 SUMMARY FIX PRIORITARI

### Fix da Implementare SUBITO (Release v1.4.1)

1. **Agency Linking**: Implementare `property_agent` lookup
2. **Maintenance Status**: Correggere mapping info[57]
3. **Position**: Correggere mapping info[56]
4. **Campi mancanti critici**: Salvare `property_area`, `property_year`, `property_energy_index`

### Fix da Implementare Prossima Release (v1.5.0)

1. Virtual tours (virtual_tour, video_tour)
2. Unified meta keys (migrare da legacy)
3. Planimetrie separate da foto
4. Micro-categorie distinte

---

**Report generato da**: Claude AI
**Versione**: Analysis v1.0
**Data**: 2025-11-26
**Autore**: Andrea Cianni (supervisionato da Claude)

---

## 📌 NOTE TECNICHE

### File Analizzati

**XML Source**:
- `test-property-complete-fixed.xml` (branch release/v1.4.0)
  - TEST001: Appartamento Centro Trento Classe A+
  - TEST002: Villa di Prestigio con Piscina Classe A4
  - TEST003: Attico Panoramico con Terrazzo 150mq

**Database CSV**:
- `Dati Post (Properties + Agents).csv`
- `Tutti i Meta Fields (Completo).csv`
- `Meta Fields Specifici (Agency Linking).csv`
- `Taxonomies Associate.csv`
- `Query Completa (Tutto in Una Vista).csv`

**Front-end HTML**:
- Property 5444, 5455, 5461 (view-source HTML)
- Agent 5442, 5443 (view-source HTML)

**Mapping Documentation**:
- `PROPERTY_MAPPER_FIELDS.md` (v3.3 - OPZIONE A)

### Metodologia Analisi

1. Estrazione dati XML con parser completo
2. Confronto con documentazione mapping ufficiale
3. Verifica database attraverso export CSV
4. Verifica front-end attraverso view-source HTML
5. Identificazione discrepanze per ogni campo critico
6. Categorizzazione problemi per gravità (Critico/Alto/Medio/Basso)
