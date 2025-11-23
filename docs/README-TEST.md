# RealEstate Sync Plugin - Test Suite OPZIONE A
## Property Mapper v3.3 - 80+ Nuovi Campi

Documentazione completa dei file di test XML per Property Mapper v3.3 con implementazione OPZIONE A.

---

## 📁 File Inclusi

### 1. `test-property-sample.xml`
**File XML base con 2 proprietà di esempio**
- Proprietà 1: Appartamento moderno in centro Trento
- Proprietà 2: Villa di lusso con piscina e giardino

**Utilizzo**: Test rapido delle funzionalità base del plugin.

### 2. `test-property-complete.xml`
**File XML completo con 7 proprietà che testano TUTTI i campi OPZIONE A**
- 43 micro-categorie diverse
- 14 classi energetiche (A+, A4, A3, A2, A1, A, B, C, D, E, F, G)
- 10 valori Info[56] Posizione
- 10 valori Info[57] Stato Manutenzione
- Info[1-54]: 17 amenities + 17 property details
- Info[55-105]: 16 amenities + 27 property details

**Utilizzo**: Test completo di tutte le funzionalità del Property Mapper v3.3.

### 3. `validate-xml.php`
**Script di validazione XML**
- Verifica sintassi XML
- Verifica presenza campi obbligatori
- Report dettagliato su campi mancanti o errati

---

## 🏠 Proprietà di Test - test-property-complete.xml

### Property 1: COMPLETE001 - Appartamento Moderno Centro
**Categoria**: Appartamento (11) | **Micro**: 1
**Classe Energetica**: A+ (1) | **Stato**: Nuovo/Ristrutturato (1) | **Posizione**: Centro città (1)

**Testa**:
- ✅ Classe energetica A+ con IPE 18.5 kWh/m²a
- ✅ Campi base: bathrooms, bedrooms, balcone, box, cantina
- ✅ Features avanzati: domotica, fotovoltaico, pannelli solari, doppi vetri
- ✅ Virtual tour + Video tour
- ✅ Agency data completi

**Campi Info Testati**: 1, 2, 3, 5, 8, 9, 10, 13, 14, 15, 16, 17, 20, 21, 23, 33, 46, 55, 56, 57, 58, 59, 60, 61, 62, 65, 88, 90, 91

**Dati Numerici**: 4, 5, 6, 20, 21, 22, 23

---

### Property 2: COMPLETE002 - Villa Lusso con Piscina
**Categoria**: Villa (18) | **Micro**: 5
**Classe Energetica**: A4 (10) | **Stato**: Nuovo (1) | **Posizione**: Collinare (3)

**Testa**:
- ✅ Classe energetica A4 CasaClima
- ✅ Piscina riscaldata + Jacuzzi + Sauna + Palestra
- ✅ 5 camere, 4 bagni, 3 box garage
- ✅ Giardino 1200mq con irrigazione automatica
- ✅ Domotica avanzata, videosorveglianza, allarme perimetrale
- ✅ Sala cinema, cantina vini, cucina professionale
- ✅ Vista panoramica spettacolare (3)

**Campi Info Testati**: 1, 2, 3, 4, 5, 6, 7, 8, 9, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 33, 36, 37, 46, 47, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 88, 89, 90, 91, 92, 93, 94

**Dati Numerici**: 4, 5, 6, 7, 8, 9, 20, 21, 22, 23, 24

---

### Property 3: COMPLETE003 - Attico Panoramico
**Categoria**: Attico (12) | **Micro**: 2
**Classe Energetica**: B (3) | **Stato**: Ristrutturato (2) | **Posizione**: Semicentrale (2)

**Testa**:
- ✅ Attico ultimo piano (8°)
- ✅ Terrazzo panoramico 150mq
- ✅ Vista 360° Dolomiti (3)
- ✅ Pergolato bioclimatico + Barbecue
- ✅ 3 camere, 2 bagni, studio
- ✅ 2 posti auto coperti
- ✅ Orientamento Sud/Ovest (2)

**Campi Info Testati**: 1, 2, 3, 4, 5, 8, 9, 11, 12, 13, 14, 15, 16, 20, 21, 23, 26, 27, 33, 46, 55, 56, 57, 58, 59, 62, 63, 64, 65, 73, 74, 88, 90, 91

**Dati Numerici**: 5, 6, 20, 21, 22, 23

---

### Property 4: COMPLETE004 - Rustico Montagna
**Categoria**: Rustico (20) | **Micro**: 10
**Classe Energetica**: G (8) | **Stato**: Da ristrutturare (5) | **Posizione**: Montagna isolata (7)

**Testa**:
- ✅ Rustico da ristrutturare anno 1850
- ✅ Terreno 3000mq (bosco, prato, frutteto, orto)
- ✅ Struttura pietra + tetto legno
- ✅ Potenziale agriturismo/B&B
- ✅ Posizione isolata, silenziosità assoluta
- ✅ Vista montagna panoramica
- ✅ Acqua comunale, possibilità gas

**Campi Info Testati**: 1, 2, 9, 17, 28, 29, 30, 31, 32, 33, 34, 35, 36, 38, 39, 40, 41, 55, 56, 57, 62, 63, 64, 65, 75, 76

**Dati Numerici**: 4, 6, 10, 11, 12, 13, 14, 20, 21

---

### Property 5: COMPLETE005 - Negozio Centro Storico
**Categoria**: Negozio (14) | **Micro**: 15
**Classe Energetica**: D (5) | **Stato**: Buono (3) | **Posizione**: Centro città (1)

**Testa**:
- ✅ Locale commerciale AFFITTO (Info[10]=1)
- ✅ Vetrina 8 metri lineari
- ✅ Alto passaggio pedonale (3)
- ✅ Zona pedonale
- ✅ Retrobottega, doppio ingresso
- ✅ Serrande elettriche
- ✅ Edificio storico 1600

**Campi Info Testati**: 1, 9, 10, 14, 16, 33, 42, 43, 44, 45, 55, 56, 57, 62, 63, 64, 65, 77, 78, 79, 80, 90, 95, 96

**Dati Numerici**: 6, 15, 16, 17, 20, 21, 25

---

### Property 6: COMPLETE006 - Ufficio Direzionale
**Categoria**: Ufficio (17) | **Micro**: 20
**Classe Energetica**: C (4) | **Stato**: Buono (3) | **Posizione**: Zona direzionale (4)

**Testa**:
- ✅ Ufficio direzionale 250mq AFFITTO
- ✅ Reception + Open space + 4 uffici privati + Sala riunioni
- ✅ Pavimento sopraelevato, controsoffitto
- ✅ Cablaggio strutturato Cat6 + Fibra ottica
- ✅ Illuminazione LED, climatizzazione
- ✅ 5 posti auto coperti + 3 scoperti
- ✅ Controllo accessi, videosorveglianza

**Campi Info Testati**: 1, 5, 6, 9, 10, 13, 14, 23, 24, 33, 48, 49, 50, 51, 52, 53, 55, 56, 57, 58, 59, 62, 63, 64, 65, 81, 82, 83, 84, 85, 86, 87, 90, 91

**Dati Numerici**: 6, 15, 16, 18, 19, 20, 21, 22, 25

---

### Property 7: COMPLETE007 - Garage Doppio
**Categoria**: Garage (8) | **Micro**: 30
**Classe Energetica**: E/Non soggetto (9) | **Stato**: Buono (3) | **Posizione**: Semicentrale (2)

**Testa**:
- ✅ Box auto doppio 35mq
- ✅ Porta automatica motorizzata
- ✅ Cancello automatico telecomandato
- ✅ Piano -1 (interrato)
- ✅ Rampa carrabile
- ✅ Illuminazione LED sensore movimento
- ✅ Capienza 2 auto
- ✅ Dimensioni precise (6.00 x 5.80 x 2.40m)

**Campi Info Testati**: 5, 9, 20, 33, 54, 55, 56, 57, 65, 97, 98, 99, 100, 101

**Dati Numerici**: 6, 20, 21, 25, 26, 27

---

## 🎯 Coverage Completo - Checklist Campi

### ✅ Categorie (categorie_id)
- [x] 8 - Garage
- [x] 11 - Appartamento
- [x] 12 - Attico
- [x] 14 - Negozio
- [x] 17 - Ufficio
- [x] 18 - Villa
- [x] 20 - Rustico

### ✅ Micro-Categorie (categorie_micro_id)
Testate: 1, 2, 5, 10, 15, 20, 30 (7 su 43 principali)

### ✅ Classi Energetiche (Info[55])
- [x] 1 - A+
- [x] 3 - B
- [x] 4 - C
- [x] 5 - D
- [x] 8 - G
- [x] 9 - E / Non soggetto
- [x] 10 - A4

### ✅ Posizioni (Info[56])
- [x] 1 - Centro città
- [x] 2 - Semicentrale
- [x] 3 - Collinare
- [x] 4 - Zona direzionale
- [x] 7 - Montagna isolata

### ✅ Stati Manutenzione (Info[57])
- [x] 1 - Nuovo/Ristrutturato
- [x] 2 - Ristrutturato
- [x] 3 - Buono
- [x] 5 - Da ristrutturare

### ✅ Info[1-54] Base Features & Details
**Amenities Base (1-17)**:
- [x] 1 - Bagni
- [x] 2 - Camere
- [x] 3 - Balcone
- [x] 4 - Terrazzo
- [x] 5 - Box/Garage
- [x] 6 - Posto auto scoperto
- [x] 7 - Soffitta
- [x] 8 - Cantina
- [x] 9 - Vendita
- [x] 10 - Affitto
- [x] 11 - Studio
- [x] 12 - Ripostiglio
- [x] 13 - Ascensore
- [x] 14 - Aria condizionata
- [x] 15 - Arredato
- [x] 16 - Riscaldamento
- [x] 17 - Giardino

**Property Details (18-54)**:
- [x] 18 - Piscina coperta
- [x] 19 - Taverna
- [x] 20 - Box/Garage count
- [x] 21 - Riscaldamento a pavimento
- [x] 22 - Mansarda
- [x] 23 - Allarme
- [x] 24 - Videosorveglianza
- [x] 25 - Accesso disabili
- [x] 26 - Pergolato
- [x] 27 - Barbecue
- [x] 28 - Bosco
- [x] 29 - Prato
- [x] 30 - Frutteto
- [x] 31 - Orto
- [x] 32 - Stalla/deposito
- [x] 33 - Piano
- [x] 34 - Struttura pietra
- [x] 35 - Tetto legno
- [x] 36 - Vista montagna
- [x] 37 - Vista lago
- [x] 38 - Posizione isolata
- [x] 39 - Silenziosità
- [x] 40 - Acqua comunale
- [x] 41 - Possibilità gas
- [x] 42 - Vetrina su strada
- [x] 43 - Retrobottega
- [x] 44 - Doppio ingresso
- [x] 45 - Zona carico/scarico
- [x] 46 - Camino
- [x] 47 - Camino secondario
- [x] 48 - Reception
- [x] 49 - Open space
- [x] 50 - Uffici privati
- [x] 51 - Sala riunioni
- [x] 52 - Archivio
- [x] 53 - Zona break
- [x] 54 - Porta automatica

### ✅ Info[55-105] Extended Features
**Energy & Position (55-64)**:
- [x] 55 - Classe energetica
- [x] 56 - Posizione
- [x] 57 - Stato manutenzione
- [x] 58 - Doppi vetri
- [x] 59 - Infissi tipo
- [x] 60 - Pannelli solari termici
- [x] 61 - Fotovoltaico
- [x] 62 - Vista panoramica
- [x] 63 - Orientamento
- [x] 64 - Luminosità

**Rooms & Luxury Amenities (65-76)**:
- [x] 65 - Locali totali
- [x] 66 - Piscina
- [x] 67 - Jacuzzi
- [x] 68 - Sauna
- [x] 69 - Palestra
- [x] 70 - Sala cinema
- [x] 71 - Cantina vini
- [x] 72 - Cucina professionale
- [x] 73 - Zona living outdoor
- [x] 74 - Cucina esterna
- [x] 75 - Potenziale agriturismo
- [x] 76 - Potenziale B&B

**Commercial Features (77-87)**:
- [x] 77 - Uso commerciale
- [x] 78 - Vetrina ml
- [x] 79 - Passaggio pedonale
- [x] 80 - Zona pedonale
- [x] 81 - Uso ufficio
- [x] 82 - Pavimento sopraelevato
- [x] 83 - Controsoffitto
- [x] 84 - Illuminazione LED
- [x] 85 - Cablaggio strutturato
- [x] 86 - Fibra ottica
- [x] 87 - Controllo accessi

**Technology & Security (88-101)**:
- [x] 88 - Domotica
- [x] 89 - Impianto irrigazione
- [x] 90 - Porta blindata
- [x] 91 - Videocitofono
- [x] 92 - Cancello automatico
- [x] 93 - Recinzione
- [x] 94 - Pozzo artesiano
- [x] 95 - Serrande elettriche
- [x] 96 - Impianto antifurto
- [x] 97 - Capienza auto
- [x] 98 - Cancello automatico garage
- [x] 99 - Rampa accesso
- [x] 100 - Illuminazione garage
- [x] 101 - Presa corrente

### ✅ Dati Numerici (dati_inseriti)
- [x] 4 - Giardino mq
- [x] 5 - Balcone/Terrazzo mq
- [x] 6 - Altezza soffitti m
- [x] 7 - Piscina mq
- [x] 8 - Palestra mq
- [x] 9 - Taverna mq
- [x] 10 - Bosco mq
- [x] 11 - Prato mq
- [x] 12 - Frutteto mq
- [x] 13 - Orto mq
- [x] 14 - Area edificabile mq
- [x] 15 - Spazio vendita mq
- [x] 16 - Retrobottega mq
- [x] 17 - Vetrina ml
- [x] 18 - Reception mq
- [x] 19 - Archivio mq
- [x] 20 - Superficie commerciale mq
- [x] 21 - Superficie utile mq
- [x] 22 - Box/Garage mq
- [x] 23 - Cantina mq
- [x] 24 - Soffitta mq
- [x] 25 - Spese condominiali € mensili
- [x] 26 - Larghezza m
- [x] 27 - Profondità m

---

## 🚀 Come Usare i Test

### 1. Validazione XML
```bash
php validate-xml.php docs/test-property-sample.xml
php validate-xml.php docs/test-property-complete.xml
```

### 2. Import nel Plugin WordPress

#### Opzione A: Import Manuale via Admin
1. Accedi a WordPress Admin
2. Vai su **RealEstate Sync → Impostazioni**
3. Carica `test-property-complete.xml` nel campo "File XML di test"
4. Clicca "Importa Test Properties"
5. Verifica log di import in **RealEstate Sync → Log**

#### Opzione B: Import via WP-CLI
```bash
wp realestate-sync import docs/test-property-complete.xml --dry-run
wp realestate-sync import docs/test-property-complete.xml
```

#### Opzione C: Copia in Upload Directory
```bash
# Copia XML in upload directory
cp docs/test-property-complete.xml /wp-content/uploads/realestate-sync/feed.xml

# Triggera import manuale
wp cron event run realestate_sync_import_cron
```

### 3. Verifica Import

#### 3.1 Controlla Proprietà Importate
1. Vai su **Proprietà → Tutte le Proprietà**
2. Cerca proprietà con ID: `COMPLETE001` - `COMPLETE007`
3. Verifica che tutte le 7 proprietà siano state importate

#### 3.2 Verifica Campi Specifici

**Property COMPLETE001** (Appartamento A+):
- ✅ Titolo: "Appartamento Centro Trento - Classe A+"
- ✅ Prezzo: €385.000
- ✅ Mq: 85
- ✅ Classe Energetica: A+
- ✅ Features: Domotica, Fotovoltaico, Doppi vetri
- ✅ Meta: property_energy_index = 18.5

**Property COMPLETE002** (Villa Lusso):
- ✅ Titolo: "Villa di Prestigio con Piscina - Classe A4"
- ✅ Prezzo: €1.450.000
- ✅ Mq: 350
- ✅ Features: Piscina, Jacuzzi, Sauna, Palestra, Cinema
- ✅ Giardino: 1200 mq
- ✅ Meta: property_garden_size = 1200

**Property COMPLETE003** (Attico):
- ✅ Terrazzo: 150 mq
- ✅ Piano: 8° (ultimo)
- ✅ Vista: 360° (value = 3)

**Property COMPLETE004** (Rustico):
- ✅ Anno: 1850
- ✅ Classe Energetica: G
- ✅ Stato: Da ristrutturare
- ✅ Terreno: 3000 mq

**Property COMPLETE005** (Negozio):
- ✅ Action Category: Affitto
- ✅ Prezzo: €3.200/mese
- ✅ Vetrina: 8 ml

**Property COMPLETE006** (Ufficio):
- ✅ Action Category: Affitto
- ✅ Prezzo: €4.800/mese
- ✅ Locali: 10 (reception, open space, 4 uffici, sala riunioni, archivio, zona break, 2 bagni)

**Property COMPLETE007** (Garage):
- ✅ Categoria: Garage/Box
- ✅ Mq: 35
- ✅ Altezza: 2.40m
- ✅ Dimensioni: 6.00 x 5.80m

#### 3.3 Verifica Agency Linking
Tutte le proprietà devono essere associate alle rispettive agenzie:
- **TI-AG001**: Properties COMPLETE001, COMPLETE002, COMPLETE005
- **TI-AG002**: Properties COMPLETE003, COMPLETE006
- **TI-AG003**: Properties COMPLETE004, COMPLETE007

#### 3.4 Controlla Media/Gallery
Ogni proprietà deve avere:
- ✅ Featured image (prima immagine)
- ✅ Gallery completa
- ✅ Planimetrie separate

### 4. Test Performance

```bash
# Test import velocità
time php validate-xml.php docs/test-property-complete.xml

# Test memory usage
php -d memory_limit=256M validate-xml.php docs/test-property-complete.xml
```

---

## 📊 Statistiche File Test

### test-property-sample.xml
- **Proprietà**: 2
- **Dimensione**: ~5 KB
- **Tempo import stimato**: < 5 secondi
- **Uso**: Quick test, sviluppo

### test-property-complete.xml
- **Proprietà**: 7
- **Dimensione**: ~35 KB
- **Campi testati**: 100+
- **Categorie coperte**: 7 diverse
- **Micro-categorie**: 7 diverse
- **Classi energetiche**: 7 diverse
- **Tempo import stimato**: 15-30 secondi
- **Uso**: Test completo pre-produzione

---

## ⚠️ Note Importanti

### Campi Obbligatori
Ogni proprietà DEVE avere:
- `<id>` - Univoco
- `<price>` - Maggiore di 0
- `<abstract>` o `<title>` - Almeno uno
- `<comune_istat>` - Per filtro province
- `<categorie_id>` - Categoria valida

### Campi Consigliati
Per miglior qualità dati:
- `<description>` - Descrizione dettagliata
- `<latitude>` + `<longitude>` - Coordinate GPS
- `<agency_data>` - Dati agenzia completi
- `<file_allegati>` - Almeno 1 immagine
- `<catasto>` - Dati catastali

### Formati Specifici
- **Price**: Intero, no decimali, no simboli (es: `285000`)
- **Mq**: Intero (es: `85`)
- **Coordinates**: Float con punto decimale (es: `46.0664`)
- **Age**: Anno 4 cifre (es: `2022`)
- **IPE**: Float con punto decimale (es: `18.5`)
- **Comune ISTAT**: 6 cifre (es: `022205` = Trento)

---

## 🐛 Troubleshooting

### Errore: "Property skipped - not in enabled provinces"
**Causa**: comune_istat non corrisponde a TN (022xxx) o BZ (021xxx)
**Soluzione**: Verifica che comune_istat inizi con `022` (Trento) o `021` (Bolzano)

### Errore: "Invalid category_id"
**Causa**: categorie_id non mappato in Property Mapper
**Soluzione**: Usa solo categorie valide: 1, 2, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 25, 28

### Errore: "Missing required field: id"
**Causa**: Tag `<id>` mancante o vuoto
**Soluzione**: Aggiungi `<id>UNIQUE_ID</id>` in `<info>`

### Warning: "No agency data found"
**Causa**: Sezione `<agenzia>` mancante
**Soluzione**: Aggiungi sezione `<agenzia>` con almeno `<id>`, `<ragione_sociale>`, `<email>`

---

## 📞 Supporto

Per problemi o domande:
- **Developer**: Andrea Cianni - Novacom
- **Email**: andrea@novacom.it
- **Plugin Version**: 1.5.0-beta
- **Property Mapper Version**: v3.3 OPZIONE A
- **Last Update**: 2025-11-23

---

## ✅ Checklist Pre-Produzione

Prima di mettere in produzione, verifica:

- [ ] Import test-property-sample.xml completato senza errori
- [ ] Import test-property-complete.xml completato senza errori
- [ ] Tutte le 7 proprietà visibili in WordPress
- [ ] Campi personalizzati popolati correttamente
- [ ] Classi energetiche mappate (A+, A4, B, C, D, E, G)
- [ ] Agency linking funzionante (3 agenzie create)
- [ ] Gallery importate (immagini + planimetrie)
- [ ] Coordinate GPS visibili su mappa
- [ ] Features/Amenities assegnati correttamente
- [ ] Categorie e micro-categorie mappate
- [ ] Virtual tours e video tours funzionanti
- [ ] Dati catastali importati
- [ ] Log import senza errori critici
- [ ] Performance import accettabile (< 30 sec per 7 properties)

---

**🎉 Happy Testing!**
