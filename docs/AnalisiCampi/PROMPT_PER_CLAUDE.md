# PROMPT PER CLAUDE DA WEB - Analisi Mapping Properties

## 🎯 OBIETTIVO

Analizzare il flusso completo di import delle properties dal file XML al front-end WordPress, confrontando:
1. **Dati XML** - Cosa arriva dal file XML
2. **Mapping previsto** - Cosa DOVREBBE essere mappato (secondo Property Mapper)
3. **Database** - Cosa è EFFETTIVAMENTE salvato nel database
4. **Front-end** - Cosa APPARE realmente sul sito

## 📁 FILE DA ANALIZZARE

**Repository GitHub**: https://github.com/andreacianni/realestate-sync-plugin
**Branch**: release/v1.4.0
**Cartella**: docs/AnalisiCampi/

**BASE URL**: `https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/`

### 🔍 COME LEGGERE I FILE

Usa il tuo tool di lettura file/web per accedere ai file GitHub. Per ogni file, usa questo URL pattern:
`https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/FILENAME`

### 📄 LISTA FILE CON LINK DIRETTI

#### 1. XML Source Files
- [test-property-complete.xml](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/test-property-complete.xml)
- [test-property-complete-fixed.xml](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/test-property-complete-fixed.xml)

#### 2. Mapping Documentation
- [PROPERTY_MAPPER_FIELDS.md](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/PROPERTY_MAPPER_FIELDS.md) - **LEGGI QUESTO PER PRIMO!**

#### 3. Database Exports (CSV)
- [Dati Post (Properties + Agents).csv](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/Dati%20Post%20(Properties%20+%20Agents).csv)
- [Tutti i Meta Fields (Completo).csv](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/Tutti%20i%20Meta%20Fields%20(Completo).csv)
- [Meta Fields Specifici (Agency Linking).csv](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/Meta%20Fields%20Specifici%20(Agency%20Linking).csv)
- [Taxonomies Associate.csv](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/Taxonomies%20Associate.csv)
- [Query Completa (Tutto in Una Vista).csv](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/Query%20Completa%20(Tutto%20in%20Una%20Vista).csv)

#### 4. Front-end HTML (view-source)
- [Property 5461](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/5461%20view-source_https___trentinoimmobiliare.it_proprieta_attico-panoramico-con-terrazzo-150mq_.html)
- [Property 5455](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/5455%20view-source_https___trentinoimmobiliare.it_proprieta_villa-di-prestigio-con-piscina-classe-a4_.html)
- [Property 5444](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/5444%20view-source_https___trentinoimmobiliare.it_proprieta_appartamento-centro-trento-classe-a_.html)
- [Agent 5443](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/5443%20view-source_https___trentinoimmobiliare.it_agenti_dolomiti-real-estate-sas_.html)
- [Agent 5442](https://github.com/andreacianni/realestate-sync-plugin/blob/release/v1.4.0/docs/AnalisiCampi/5442%20view-source_https___trentinoimmobiliare.it_agenti_trentino-immobiliare-excellence-srl_.html)

## 📊 ANALISI RICHIESTA

### Per OGNI campo importante:

1. **Verifica presenza nel XML**
   - Il campo esiste nel file XML?
   - Qual è il valore?

2. **Verifica mapping previsto**
   - Il campo è documentato in PROPERTY_MAPPER_FIELDS.md?
   - Quale meta_key dovrebbe avere nel database?
   - Ci sono trasformazioni/conversioni previste?

3. **Verifica database**
   - Il campo è presente nei CSV database?
   - Il valore è corretto?
   - Ci sono discrepanze rispetto all'XML?

4. **Verifica front-end**
   - Il campo appare nel HTML front-end?
   - È visibile all'utente?
   - Il valore è formattato correttamente?

### Focus particolare su:
- ⚠️ **Campi MANCANTI**: Presenti in XML ma NON in database
- ⚠️ **Campi PERSI**: Presenti in database ma NON in front-end
- ⚠️ **Valori ERRATI**: Presenti ovunque ma con valore sbagliato
- ✅ **Campi OK**: Flusso completo corretto XML → DB → Front-end

## 📝 OUTPUT RICHIESTO

Crea un file Markdown con questa struttura:

```markdown
# Analisi Mapping Properties - Report Completo

**Data analisi**: [DATA]
**Properties analizzate**: 5461, 5455, 5444
**Agents analizzati**: 5443, 5442

---

## 📊 EXECUTIVE SUMMARY

- **Campi totali analizzati**: X
- **Campi OK (flusso completo)**: X (X%)
- **Campi con problemi**: X (X%)
  - Mancanti in database: X
  - Persi nel front-end: X
  - Valori errati: X

---

## ⚠️ PROBLEMI CRITICI TROVATI

### 1. Campi Mancanti nel Database
[Lista campi presenti in XML ma non salvati in DB]

### 2. Campi Persi nel Front-end
[Lista campi in DB ma non visualizzati]

### 3. Valori Errati o Trasformati Male
[Lista campi con valori incorretti]

---

## 📋 ANALISI DETTAGLIATA PER CAMPO

### Sezione: Core Property Data

#### property_price
- **XML**: [valore e campo source]
- **Mapping previsto**: [da PROPERTY_MAPPER_FIELDS.md]
- **Database**: [valore in CSV]
- **Front-end**: [valore in HTML]
- **Status**: ✅ OK / ⚠️ PROBLEMA
- **Note**: [eventuali discrepanze]

[... ripeti per ogni campo importante ...]

---

## 🏢 ANALISI AGENCY LINKING

### Property → Agent Association
- **property_agent meta**: [analisi]
- **xml_agency_id**: [analisi]
- **Front-end agent sidebar**: [analisi]
- **Status**: [OK o problemi]

---

## 📸 ANALISI GALLERY/MEDIA

### Immagini Property
- **Totale immagini in XML**: X
- **Immagini in database**: X
- **Immagini in front-end**: X
- **Featured image**: [analisi]
- **Status**: [OK o problemi]

---

## 🗺️ ANALISI GOOGLE MAPS

### Coordinate e Display
- **Latitude/Longitude**: [analisi]
- **Address components**: [analisi]
- **Google Maps settings**: [analisi]
- **Mappa visibile in front-end**: [SI/NO]
- **Status**: [OK o problemi]

---

## 💡 RACCOMANDAZIONI

1. [Raccomandazione 1]
2. [Raccomandazione 2]
3. ...

---

## 📊 TABELLA RIASSUNTIVA CAMPI

| Campo | XML | Mapping | Database | Front-end | Status |
|-------|-----|---------|----------|-----------|--------|
| property_price | ✅ | ✅ | ✅ | ✅ | ✅ OK |
| property_size | ✅ | ✅ | ✅ | ⚠️ | ⚠️ Valore errato |
| ... | ... | ... | ... | ... | ... |

---

**Report generato da**: Claude AI
**Versione**: Analysis v1.0
**Data**: [DATA]
```

## 💾 OUTPUT DEL REPORT

**FORMATO**: Markdown (come specificato sopra)

**NOME FILE**: `ANALISI_MAPPING_REPORT.md`

**DOVE SALVARE**:
Fornisci il contenuto completo del report come output. L'utente lo salverà manualmente in:
`docs/AnalisiCampi/ANALISI_MAPPING_REPORT.md`

E poi lo committera su GitHub nello stesso repository.

## 🎯 PRIORITÀ ANALISI

Focus particolare su questi campi critici:
1. **property_price** - Prezzo
2. **property_size** - Superficie
3. **property_address** - Indirizzo completo
4. **property_latitude / property_longitude** - Coordinate
5. **property_agent** - Collegamento agenzia
6. **property_energy_class** - Classe energetica
7. **property_rooms / property_bedrooms / property_bathrooms** - Locali
8. **Gallery images** - Immagini
9. **property_city / property_area** - Località
10. **Google Maps settings** - Visualizzazione mappa

## ✅ CHECKLIST FINALE

Prima di consegnare il report, verifica di aver:
- [ ] Analizzato TUTTI i file forniti
- [ ] Confrontato XML vs Mapping Documentation
- [ ] Confrontato Database vs Mapping previsto
- [ ] Confrontato Front-end vs Database
- [ ] Identificato TUTTI i campi mancanti/errati
- [ ] Fornito raccomandazioni concrete
- [ ] Creato tabella riassuntiva chiara
- [ ] Salvato output in `ANALISI_MAPPING_REPORT.md`

---

**Buona analisi!** 🚀
