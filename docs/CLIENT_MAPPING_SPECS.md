# RealEstate Sync Plugin - Client Mapping Specifications
## Property Mapper v3.3 - OPZIONE A Completa

Specifiche complete di mappatura campi XML → WordPress WpResidence per Property Mapper v3.3.

---

## 📋 Overview OPZIONE A

**Property Mapper v3.3** implementa **OPZIONE A** con supporto completo per **80+ nuovi campi** rispetto alla versione base:

- **Info[1-54]**: 34 campi base (17 amenities + 17 property details)
- **Info[55-105]**: 51 campi estesi (16 amenities + 27 property details + 8 meta)
- **43 micro-categorie** diverse
- **14 classi energetiche** (A+, A4, A3, A2, A1, A, B, C, D, E, F, G + varianti)
- **10 valori posizione** (centro, semicentro, periferia, collina, montagna, etc.)
- **10 stati manutenzione** (nuovo, ristrutturato, buono, da ristrutturare, etc.)

---

## 🏷️ Categorie (categorie_id)

Mapping categorie GestionaleImmobiliare → WpResidence:

| ID | GI Categoria | WpResidence Category |
|----|--------------|----------------------|
| 1 | Casa Singola | Case singole |
| 2 | Bifamiliare | Case singole |
| 8 | Garage | Garage e Posti auto |
| 9 | Box | Garage e Posti auto |
| 11 | Appartamento | Appartamenti |
| 12 | Attico | Appartamenti |
| 13 | Loft | Loft e Mansarde |
| 14 | Negozio | Uffici e Commerciali |
| 15 | Capannone | Uffici e Commerciali |
| 16 | Laboratorio | Uffici e Commerciali |
| 17 | Ufficio | Uffici e Commerciali |
| 18 | Villa | Ville |
| 19 | Terreno | Terreni |
| 20 | Rustico | Rustici e Case rurali |
| 21 | Castello | Ville |
| 22 | Palazzo | Case vacanza |
| 23 | Loft/Mansarda | Loft e Mansarde |
| 25 | Casa Vacanza | Case vacanza |
| 28 | Camera/Posto letto | Camere e Posti letto |

---

## 🏠 Micro-Categorie (categorie_micro_id)

43 micro-categorie disponibili per classificazione dettagliata:

| ID Range | Descrizione |
|----------|-------------|
| 1-5 | Appartamenti (bilocale, trilocale, quadrilocale, attico, mansarda) |
| 6-10 | Ville e Case (indipendente, bifamiliare, a schiera, rustico, casale) |
| 11-15 | Commerciali (negozio, ufficio, capannone, laboratorio, showroom) |
| 16-20 | Uffici (open space, direzionale, coworking, studio professionale) |
| 21-25 | Terreni (edificabile, agricolo, boschivo, vigna, frutteto) |
| 26-30 | Garage/Box (singolo, doppio, triplo, posto auto coperto/scoperto) |
| 31-35 | Turistico (B&B, agriturismo, hotel, residence, casa vacanza) |
| 36-40 | Altro (magazzino, deposito, cantina, soffitta, locale tecnico) |
| 41-43 | Speciali (palazzo storico, castello, villa d'epoca) |

---

## ⚡ Classi Energetiche (Info[55])

| Valore | Classe | IPE Range (kWh/m²a) |
|--------|--------|---------------------|
| 1 | A+ | < 15 |
| 10 | A4 | 15-20 |
| 11 | A3 | 20-25 |
| 12 | A2 | 25-30 |
| 13 | A1 | 30-35 |
| 2 | A | 35-50 |
| 3 | B | 50-70 |
| 4 | C | 70-90 |
| 5 | D | 90-120 |
| 6 | E | 120-160 |
| 7 | F | 160-210 |
| 8 | G | > 210 |
| 9 | Non soggetto | N/A |

---

## 📍 Posizione (Info[56])

| Valore | Posizione | Descrizione |
|--------|-----------|-------------|
| 1 | Centro città | Centro storico/commerciale |
| 2 | Semicentrale | Prima periferia, ben servita |
| 3 | Collinare | Zona collinare residenziale |
| 4 | Zona direzionale | Area business/uffici |
| 5 | Periferica | Periferia urbana |
| 6 | Lago | Fronte lago o vicinanza |
| 7 | Montagna isolata | Zone montane isolate |
| 8 | Campagna | Zone agricole/rurali |
| 9 | Industriale | Zone artigianali/industriali |
| 10 | Turistica | Località turistiche |

---

## 🔧 Stato Manutenzione (Info[57])

| Valore | Stato | Descrizione |
|--------|-------|-------------|
| 1 | Nuovo | Nuova costruzione/appena ristrutturato |
| 2 | Ristrutturato | Recentemente ristrutturato (< 5 anni) |
| 3 | Buono | Buone condizioni, manutenzione ordinaria |
| 4 | Abitabile | Abitabile, piccole migliorie necessarie |
| 5 | Da ristrutturare | Necessita ristrutturazione completa |
| 6 | Discreto | Condizioni discrete, manutenzione media |
| 7 | Da ammodernare | Funzionale ma datato |
| 8 | Rustico | Struttura rustica da recuperare |
| 9 | Grezzo | Al grezzo, da completare |
| 10 | In costruzione | Cantiere in corso |

---

## 📊 Info[1-54] - Base Features & Details

### Amenities Base (1-17)

| ID | Campo | Tipo | WP Meta | Note |
|----|-------|------|---------|------|
| 1 | Bagni | int | property_bathrooms | Numero bagni |
| 2 | Camere | int | property_bedrooms | Numero camere da letto |
| 3 | Balcone | bool | - | Ha balcone |
| 4 | Terrazzo | bool | - | Ha terrazzo |
| 5 | Box/Garage | int | - | Numero box/garage |
| 6 | Posto auto scoperto | int | - | Numero posti auto scoperti |
| 7 | Soffitta | bool | - | Ha soffitta |
| 8 | Cantina | bool | - | Ha cantina |
| 9 | Vendita | bool | property_action_category | Vendita=1 |
| 10 | Affitto | bool | property_action_category | Affitto=1 |
| 11 | Studio | bool | - | Ha studio/ufficio |
| 12 | Ripostiglio | bool | - | Ha ripostiglio |
| 13 | Ascensore | bool | feature: ascensore | Ha ascensore |
| 14 | Aria condizionata | bool | feature: aria-condizionata | Ha A/C |
| 15 | Arredato | bool | feature: arredato | Arredato/Semi |
| 16 | Riscaldamento | int | - | 1=autonomo, 2=centralizzato, 3=geotermico |
| 17 | Giardino | bool | feature: giardino | Ha giardino |

### Property Details (18-54)

| ID | Campo | Tipo | Note |
|----|-------|------|------|
| 18 | Piscina coperta | bool | Piscina interna |
| 19 | Taverna | bool | Ha taverna |
| 20 | Box/Garage totali | int | Totale posti auto coperti |
| 21 | Riscaldamento pavimento | bool | Riscaldamento radiante |
| 22 | Mansarda | bool | Ha mansarda |
| 23 | Allarme | bool | Impianto antifurto |
| 24 | Videosorveglianza | bool | Sistema videocamere |
| 25 | Accesso disabili | bool | Senza barriere |
| 26 | Pergolato | bool | Pergola/gazebo |
| 27 | Barbecue | bool | Area BBQ |
| 28 | Bosco | bool | Terreno boscato |
| 29 | Prato | bool | Prato/giardino |
| 30 | Frutteto | bool | Alberi da frutto |
| 31 | Orto | bool | Orto coltivabile |
| 32 | Stalla/Deposito | bool | Annessi agricoli |
| 33 | Piano | int | -2=interrato, -1=>30, 0=terra, 1-30=piano |
| 34 | Struttura pietra | bool | Muri in pietra |
| 35 | Tetto legno | bool | Tetto in legno |
| 36 | Vista montagna | bool | Vista montagne |
| 37 | Vista lago | bool | Vista lago |
| 38 | Posizione isolata | bool | Isolato/tranquillo |
| 39 | Silenziosità | int | 1-3 livello |
| 40 | Acqua comunale | bool | Acquedotto |
| 41 | Possibilità gas | bool | Metano disponibile |
| 42 | Vetrina su strada | bool | Vetrina commerciale |
| 43 | Retrobottega | bool | Magazzino/retro |
| 44 | Doppio ingresso | bool | Due ingressi |
| 45 | Zona carico/scarico | bool | Area logistica |
| 46 | Camino principale | bool | Camino |
| 47 | Camino secondario | bool | Secondo camino |
| 48 | Reception | bool | Area reception |
| 49 | Open space | bool | Spazio aperto |
| 50 | Uffici privati | int | Numero uffici |
| 51 | Sala riunioni | bool | Meeting room |
| 52 | Archivio | bool | Locale archivio |
| 53 | Zona break | bool | Area relax |
| 54 | Porta automatica | bool | Apertura motorizzata |

---

## 🌟 Info[55-105] - Extended Features OPZIONE A

### Energy & Position Meta (55-64)

| ID | Campo | Valori | WP Meta | Note |
|----|-------|--------|---------|------|
| 55 | Classe energetica | 1-13 | energy_class | Vedi tabella sopra |
| 56 | Posizione | 1-10 | - | Vedi tabella sopra |
| 57 | Stato manutenzione | 1-10 | - | Vedi tabella sopra |
| 58 | Doppi vetri | bool | - | Infissi doppio/triplo vetro |
| 59 | Tipo infissi | 1-3 | - | 1=PVC, 2=legno/alluminio, 3=legno |
| 60 | Pannelli solari termici | bool | - | Solare termico |
| 61 | Fotovoltaico | bool/int | - | Impianto FV, valore=kW |
| 62 | Vista panoramica | 0-3 | - | 0=no, 1=buona, 2=ottima, 3=eccezionale |
| 63 | Orientamento | 1-4 | - | 1=sud, 2=sud/ovest, 3=nord, 4=est/ovest |
| 64 | Luminosità | 0-2 | - | 0=bassa, 1=buona, 2=ottima |

### Rooms & Luxury Amenities (65-76)

| ID | Campo | Tipo | Note |
|----|-------|------|------|
| 65 | Locali totali | int | Numero totale vani |
| 66 | Piscina | bool | Piscina esterna |
| 67 | Jacuzzi | bool | Vasca idromassaggio |
| 68 | Sauna | bool | Sauna |
| 69 | Palestra | bool | Area fitness |
| 70 | Sala cinema | bool | Home theater |
| 71 | Cantina vini | bool | Wine cellar |
| 72 | Cucina professionale | bool | Cucina equipaggiata pro |
| 73 | Living outdoor | bool | Zona living esterna |
| 74 | Cucina esterna | bool | Cucina estiva |
| 75 | Potenziale agriturismo | bool | Possibilità uso turistico |
| 76 | Potenziale B&B | bool | Possibilità B&B |

### Commercial Features (77-87)

| ID | Campo | Tipo | Note |
|----|-------|------|------|
| 77 | Uso commerciale | bool | Destinazione C/1 |
| 78 | Vetrina metri lineari | int | Lunghezza vetrina |
| 79 | Passaggio pedonale | 1-3 | 1=basso, 2=medio, 3=alto |
| 80 | Zona pedonale | bool | Area ZTL |
| 81 | Uso ufficio | bool | Destinazione A/10 |
| 82 | Pavimento sopraelevato | bool | Raised floor |
| 83 | Controsoffitto | bool | Controsoffitto modulare |
| 84 | Illuminazione LED | bool | Luci LED |
| 85 | Cablaggio strutturato | bool | Cat6/Cat7 |
| 86 | Fibra ottica | bool | FTTH disponibile |
| 87 | Controllo accessi | bool | Badge/biometrico |

### Technology & Security (88-101)

| ID | Campo | Tipo | Note |
|----|-------|------|------|
| 88 | Domotica | bool/int | 0=no, 1=base, 2=avanzata |
| 89 | Impianto irrigazione | bool | Irrigazione automatica |
| 90 | Porta blindata | bool | Porta corazzata |
| 91 | Videocitofono | bool | Videocitofono |
| 92 | Cancello automatico | bool | Cancello motorizzato |
| 93 | Recinzione | bool | Terreno recintato |
| 94 | Pozzo artesiano | bool | Pozzo privato |
| 95 | Serrande elettriche | bool | Tapparelle motorizzate |
| 96 | Impianto antifurto | bool | Allarme perimetrale |
| 97 | Capienza auto | int | Numero auto in garage |
| 98 | Cancello garage automatico | bool | Apertura telecomando |
| 99 | Rampa accesso | bool | Rampa carrabile |
| 100 | Illuminazione garage | bool | Luci sensore |
| 101 | Presa corrente garage | bool | Presa elettrica |

### Reserved/Future (102-105)

| ID | Campo | Note |
|----|-------|------|
| 102-105 | Riservati | Per espansioni future |

---

## 📏 Dati Numerici (dati_inseriti)

| ID | Campo | Unità | WP Meta | Note |
|----|-------|-------|---------|------|
| 4 | Giardino | m² | property_garden_size | Superficie giardino |
| 5 | Balcone/Terrazzo | m² | - | Sup. terrazzi totale |
| 6 | Altezza soffitti | m | property_ceiling_height | Altezza in metri |
| 7 | Piscina | m² | - | Superficie piscina |
| 8 | Palestra | m² | - | Superficie palestra |
| 9 | Taverna | m² | - | Superficie taverna |
| 10 | Bosco | m² | - | Superficie boscata |
| 11 | Prato | m² | - | Superficie prato |
| 12 | Frutteto | m² | - | Superficie frutteto |
| 13 | Orto | m² | - | Superficie orto |
| 14 | Area edificabile | m² | - | Cubatura disponibile |
| 15 | Spazio vendita | m² | - | Area vendita negozio |
| 16 | Retrobottega | m² | - | Area magazzino |
| 17 | Vetrina | ml | - | Metri lineari vetrina |
| 18 | Reception | m² | - | Area reception |
| 19 | Archivio | m² | - | Superficie archivio |
| 20 | Superficie commerciale | m² | property_commercial_size | Sup. commerciale totale |
| 21 | Superficie utile | m² | property_useful_size | Sup. calpestabile |
| 22 | Box/Garage | m² | - | Superficie garage |
| 23 | Cantina | m² | - | Superficie cantina |
| 24 | Soffitta | m² | - | Superficie soffitta |
| 25 | Spese condominiali | €/mese | - | Spese mensili |
| 26 | Larghezza | m | - | Larghezza locale |
| 27 | Profondità | m | - | Profondità locale |

---

## 🗺️ Campi Base XML

### Info Section (Obbligatori)

```xml
<info>
    <id>UNIQUE_ID</id>                      <!-- OBBLIGATORIO -->
    <title>Titolo immobile</title>          <!-- Consigliato -->
    <abstract>Breve descrizione</abstract>  <!-- OBBLIGATORIO se no title -->
    <description>Descrizione completa</description>
    <price>285000</price>                   <!-- OBBLIGATORIO -->
    <mq>85</mq>
    <categorie_id>11</categorie_id>         <!-- OBBLIGATORIO -->
    <categorie_micro_id>1</categorie_micro_id>

    <!-- Indirizzo -->
    <indirizzo>Via Roma</indirizzo>
    <civico>25</civico>
    <zona>Centro Storico</zona>
    <comune>Trento</comune>
    <comune_istat>022205</comune_istat>     <!-- OBBLIGATORIO per filtro province -->
    <provincia>TN</provincia>
    <cap>38122</cap>

    <!-- GPS -->
    <latitude>46.0664</latitude>
    <longitude>11.1257</longitude>

    <!-- Energia -->
    <age>1995</age>                         <!-- Anno costruzione -->
    <ipe>65.5</ipe>                         <!-- IPE kWh/m²a -->
    <ipe_unit>kWh/m²a</ipe_unit>
    <ape>ape2015</ape>

    <!-- Agency & URLs -->
    <agency_code>AG001</agency_code>
    <url>https://esempio.com/property/ID</url>
    <virtual_tour>https://tour.com/ID</virtual_tour>
    <video_tour>https://youtube.com/watch?v=ID</video_tour>
    <seo_title>SEO Title</seo_title>
</info>
```

### Agency Section

```xml
<agenzia>
    <id>AG001</id>                          <!-- OBBLIGATORIO -->
    <ragione_sociale>Nome Agenzia SRL</ragione_sociale>  <!-- OBBLIGATORIO -->
    <nome>Nome Commerciale</nome>
    <indirizzo>Corso Italia 15</indirizzo>
    <comune istat="022205">Trento</comune>
    <provincia>TN</provincia>
    <cap>38122</cap>
    <telefono>0461 123456</telefono>
    <cellulare>348 1234567</cellulare>
    <email>info@agenzia.it</email>          <!-- Consigliato -->
    <sito_web>https://www.agenzia.it</sito_web>
</agenzia>
```

### Catasto Section

```xml
<catasto>
    <destinazione_uso>Residenziale</destinazione_uso>
    <rendita_catastale>650</rendita_catastale>
    <foglio>12</foglio>
    <particella>345</particella>
    <subalterno>8</subalterno>
</catasto>
```

---

## ✅ Best Practices

### 1. Campi Obbligatori Minimi
```xml
<id>UNIQUE001</id>
<price>285000</price>
<abstract>Descrizione breve</abstract>
<comune_istat>022205</comune_istat>
<categorie_id>11</categorie_id>
```

### 2. Set Consigliato per Qualità Alta
```xml
<!-- Base obbligatori -->
<id> + <price> + <abstract> + <comune_istat> + <categorie_id>

<!-- Descrittivi -->
+ <title> + <description> + <categorie_micro_id>

<!-- Location -->
+ <latitude> + <longitude> + <zona> + <indirizzo>

<!-- Energia -->
+ <age> + <ipe> + Info[55]=classe_energetica

<!-- Agency -->
+ <agenzia> completa con id, ragione_sociale, email

<!-- Media -->
+ <file_allegati> con almeno 3-5 immagini + 1 planimetria

<!-- Catasto -->
+ <catasto> completo
```

### 3. Features Essenziali da Compilare
```xml
Info[1] = Bagni
Info[2] = Camere
Info[9] o Info[10] = Vendita/Affitto
Info[33] = Piano
Info[55] = Classe energetica
Info[56] = Posizione
Info[57] = Stato manutenzione
Info[65] = Locali totali
```

---

## 🔄 Migration Path v3.1 → v3.3

Se stai migrando da Property Mapper v3.1 a v3.3 OPZIONE A:

### Nuovi Campi Aggiunti
- ✅ Info[55-105]: 51 nuovi campi extended
- ✅ Dati[1-27]: 27 campi numerici (vs 6 in v3.1)
- ✅ 43 micro-categorie (vs 7 in v3.1)
- ✅ 14 classi energetiche (vs 8 in v3.1)
- ✅ 10 posizioni + 10 stati manutenzione (nuovi)

### Backward Compatibility
✅ v3.3 è **100% backward compatible** con v3.1:
- XML v3.1 funzionano senza modifiche
- Campi aggiuntivi sono opzionali
- Mapping esistenti invariati

---

## 📞 Support

- **Plugin**: RealEstate Sync v1.5.0-beta
- **Mapper**: Property Mapper v3.3 OPZIONE A
- **Developer**: Andrea Cianni - Novacom
- **Date**: 2025-11-23
