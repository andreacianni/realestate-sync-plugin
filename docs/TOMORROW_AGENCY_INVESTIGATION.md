# 🚨 DOMANI: Agency vs Agent Investigation

**Data**: 2025-11-25
**Priorità**: CRITICA
**Problema**: Stiamo creando AGENTS (persone) invece di AGENCIES (aziende)

---

## 🎯 Obiettivo

Capire se WPResidence distingue tra:
- **Agency** = Agenzia immobiliare (company/organization)
- **Agent** = Agente individuale (person)

E identificare come creare correttamente le AGENCIES dall'XML.

---

## ❓ Domande da Rispondere

### 1. WPResidence Data Model
- [ ] WPResidence ha due entità separate (Agency + Agent) o solo una (Agent)?
- [ ] Qual è il `post_type` corretto per agencies?
  - `estate_agent` = agents (persone)?
  - Esiste `estate_agency` per companies?
- [ ] Come distingue tra company vs individual?

### 2. REST API Endpoints
- [ ] `/wpresidence/v1/agency/add` → crea cosa esattamente?
- [ ] Esiste `/wpresidence/v1/agent/add` separato?
- [ ] Quali campi differenziano company da person?
- [ ] Campo `agency_type` o simile?

### 3. Property Association
- [ ] Campo `property_agent` → accetta solo agent ID?
- [ ] O accetta anche agency ID?
- [ ] Quando viene impostato questo campo?
  - Durante creazione proprietà?
  - In un secondo momento?
- [ ] Perché ora le proprietà NON sono associate?

### 4. XML Data Structure
```xml
<agenzia>
    <id>1</id>
    <ragione_sociale>Trentino Immobiliare Excellence SRL</ragione_sociale>
    <referente>Marco Rossi</referente>
    <iva>02345678901</iva>
    <email>info@trentinoimmobiliare.it</email>
    <!-- ... -->
</agenzia>
```

- `ragione_sociale` = Company name (agency)
- `referente` = Contact person (agent?)
- Dobbiamo creare:
  - [ ] Solo agency (company)?
  - [ ] Solo agent (person)?
  - [ ] Entrambi (agency + agent contact)?

---

## 🔍 Come Investigare

### Step 1: Analizzare WPResidence Theme Code
```bash
# Sul server
ssh trentinoimmobiliare
cd public_html/wp-content/themes/wpresidence

# Cercare post types
grep -r "estate_agent" .
grep -r "estate_agency" .
grep -r "register_post_type" . | grep -i agent

# Cercare differenza agency/agent
grep -r "agency_type" .
grep -r "is_agency" .
```

### Step 2: Verificare Database
```sql
-- Vedere post types esistenti
SELECT DISTINCT post_type FROM wp_posts WHERE post_type LIKE '%agent%';

-- Vedere meta fields degli agents creati
SELECT meta_key, meta_value
FROM wp_postmeta
WHERE post_id IN (5190, 5191, 5198);

-- Vedere se c'è campo che distingue company vs person
SELECT DISTINCT meta_key FROM wp_postmeta
WHERE post_id IN (SELECT ID FROM wp_posts WHERE post_type = 'estate_agent');
```

### Step 3: Test API Endpoints
```bash
# Test endpoint esistenti
curl https://trentinoimmobiliare.it/wp-json/wpresidence/v1/ | jq .

# Vedere parametri agency/add
curl https://trentinoimmobiliare.it/wp-json/wpresidence/v1/agency/add \
  -X OPTIONS -H "Authorization: Bearer $TOKEN"
```

### Step 4: Controllare Documentazione WPResidence
- [ ] Cercare in `/docs/` del tema
- [ ] README API del plugin wpresidence-core
- [ ] Commenti nel codice API endpoint
- [ ] File: `wp-content/plugins/wpresidence-core/api/rest/agencies/agency_create.php`

### Step 5: Verificare Property Mapper
```php
// In class-realestate-sync-property-mapper.php
// Cercare dove viene impostato property_agent

// Vedere se c'è logica per:
$api_body['property_agent'] = $agency_id; // ← Questo viene fatto?
```

---

## 🎯 Risultato Atteso

Dopo l'investigazione, sapere:

1. **Cosa Creare**:
   - [ ] Agency (company) tramite endpoint X
   - [ ] Agent (person) tramite endpoint Y
   - [ ] Entrambi?

2. **Come Associare**:
   - [ ] Impostare `property_agent` = agency/agent ID
   - [ ] Dove nel codice farlo
   - [ ] Quando (durante creazione o dopo)

3. **Fix da Implementare**:
   - [ ] Modificare endpoint chiamato
   - [ ] Aggiungere campi per distinguere company/person
   - [ ] Implementare associazione property → agency/agent
   - [ ] DOPO: fixare estrazione email

---

## 📝 Note Rapide

- **Link Postman**: https://www.postman.com/universal-eclipse-339362/wpresidence/request/pozkn9h/add-agency
- **Endpoint attuale**: `POST /wpresidence/v1/agency/add`
- **Risultato attuale**: Crea agents (ID: 5190, 5191, 5198)
- **Proprietà NON associate**: verificare campo `property_agent` nel DB

---

## ⚠️ IMPORTANTE

Prima di cambiare codice:
1. ✅ Capire il data model WPResidence
2. ✅ Identificare endpoint/campi corretti
3. ✅ Testare con un solo record
4. ✅ Verificare associazione proprietà
5. Solo dopo: deploy su produzione
