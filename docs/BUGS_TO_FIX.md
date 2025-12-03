# 🐛 Bugs da Fixare

**Data**: 2 Dicembre 2025
**Status**: Import test in corso

---

## Bug #1: Undefined variable $property_id in Property_Mapper

**Severità**: ⚠️ WARNING (non blocca l'import, ma da fixare)

**Errore**:
```
PHP Warning: Undefined variable $property_id
in /home/trentinoimreit/public_html/wp-content/plugins/realestate-sync-plugin/includes/class-realestate-sync-property-mapper.php
on line 1343
```

**Quando appare**:
- Durante il batch import
- Appare di tanto in tanto nel debug.log

**File**:
- `includes/class-realestate-sync-property-mapper.php`
- Linea: 1343

**Possibile causa**:
- La variabile `$property_id` viene usata ma non è definita in quel contesto
- Potrebbe essere un log o debug statement che usa `$property_id` senza che sia stata passata/definita

**Priority**: MEDIUM
- ✅ Non blocca l'import
- ✅ Processo funziona correttamente
- ⚠️ Warning da pulire per avere log puliti

**To Fix**:
1. Leggere `class-realestate-sync-property-mapper.php` linea 1343
2. Verificare dove viene usato `$property_id`
3. Assicurarsi che la variabile sia definita o usare un valore di default
4. Testare che il fix non rompa nulla

---

## Bug #2: Log UI non si aggiorna in Dashboard → Import Manuale

**Severità**: ⚠️ LOW (UX issue, non blocca funzionalità)

**Problema**:
- Il log box in Dashboard → Import Manuale mostra solo messaggio iniziale
- Non si aggiorna con log batch in corso
- Resta fermo su "Avvio processo import manuale..."

**Comportamento attuale**:
```
Log Processo:
Avvio processo...[06:36:12] Avvio processo import manuale...
```
E resta così senza aggiornamenti.

**File coinvolti**:
- `admin/views/dashboard.php` (JavaScript `updateManualImportLog()`)
- `admin/class-realestate-sync-admin.php` (AJAX handler)

**Causa**:
- Il codice mostra il log UNA VOLTA quando AJAX ritorna
- Non fa POLLING per aggiornare con nuovi log mentre batch continua
- Il batch gira in background ma UI non si aggiorna

**Priority**: LOW
- ✅ Import funziona correttamente in background
- ⚠️ Solo problema di visualizzazione
- 💡 Utente può sempre controllare log da Tools → Testing tab

**To Fix**:
1. Aggiungere polling JavaScript ogni 3-5 secondi
2. Chiamare endpoint AJAX per ottenere nuove righe log
3. Appendere solo nuove righe (evitare duplicati)
4. Fermare polling quando batch completo

**Alternativa più semplice**:
- Rimuovere il log box da Import Manuale
- Dire all'utente di guardare Tools → Testing tab per i log
- Evita complessità del polling

---

## Prossimi Passi

1. ✅ Lasciare finire il processo in corso
2. ⏳ Aspettare completamento import
3. 🔧 Fixare bug #1 quando il processo è terminato
4. ✅ Testare con nuovo import

---

**Note**:
- Il processo sta funzionando bene nonostante il warning
- Questo è un cleanup tecnico, non un fix critico
- Da fare dopo che l'import corrente è completato
