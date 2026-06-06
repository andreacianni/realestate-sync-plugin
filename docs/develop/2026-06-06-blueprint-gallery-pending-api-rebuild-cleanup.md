# Blueprint Operativa - Modulo `gallery pending` + API rebuild + cleanup

> Tipo documento: OPERATIVO  
> Stato: blueprint vivo per sviluppo incrementale  
> Vincolo chiave: gallery aggiornata solo via API WPResidence, cleanup riusato dopo rebuild, niente rebuild nel batch ordinario

## 1. Obiettivo

1. gestire automaticamente le property con `gallery pending`
2. fare rebuild gallery solo via API WPResidence con `images[]`
3. dopo rebuild riuscito, far partire cleanup scoped su `post-id`
4. rimuovere attachment extra lasciati fuori gallery dal rebuild
5. chiudere pending con update signature e flag
6. usare payload pending salvato da feed/mapper

## 2. Stato attuale validato

### 2.1 Base gia' chiusa

1. `CREATE` continua a inviare `images[]` via API WPResidence
2. `UPDATE` ordinario non invia piu' `images[]`
3. se gallery cambia, viene marcata pending:
   - `property_gallery_changed_pending = 1`
   - `property_gallery_signature_pending = nuova signature`
   - `property_gallery_payload_pending_json = gallery mappata dal feed`
4. modulo attuale non aggiorna gallery
5. cleanup media esistente funziona e va riusato

### 2.2 Vincolo funzione

1. gallery rebuild non sta nel batch ordinario import
2. approval manuale esclusa
3. direct meta update di `wpestate_property_gallery` escluso
4. sync interno gallery via meta WordPress escluso

## 3. Vincoli non negoziabili

1. gallery create/update solo via API WPResidence
2. no update diretto `wpestate_property_gallery`
3. no sync interno gallery via meta WP
4. no approval manuale
5. no `images[]` su UPDATE ordinario
6. no rebuild gallery nel batch ordinario
7. cleanup media esistente deve restare riusabile
8. no modifica a cleanup core, queue, scanner, worker, monitor admin, `batch-continuation.php` salvo punti di aggancio futuri ben isolati

## 4. Architettura scelta

### 4.1 Flusso target

1. `gallery_changed_pending`
2. Gallery Rebuild Worker separato
3. rebuild completo via API WPResidence con `images[]`
4. scan cleanup scoped su `post-id` con `execute=true`
5. queue cleanup
6. update `property_gallery_signature`
7. clear `property_gallery_signature_pending`
8. `property_gallery_changed_pending = 0`
9. worker cleanup esistente

### 4.2 Decisione tecnica

1. rebuild gallery e cleanup restano separati
2. queue cleanup usa session stabile `gallery-rebuild`
3. scanner scoped su singola property
4. worker esistente resta consumer finale

## 5. Flusso operativo end-to-end

1. UPDATE rileva gallery changed e marca pending
2. Gallery Rebuild Worker prende pending
3. worker chiama API WPResidence con `images[]`
4. WPResidence sostituisce gallery visibile
5. WPResidence crea nuovi attachment
6. scanner cleanup scoped su `post-id` viene innescato
7. scanner trova attachment eleggibili oppure nessun extra
8. scanner popola queue se serve
9. update `property_gallery_signature`
10. clear `property_gallery_signature_pending`
11. `property_gallery_changed_pending = 0`
12. worker cleanup esistente rimuove extra nei tick successivi

## 6. Ruolo cleanup esistente

1. non va rifatto
2. va riusato
3. serve solo dopo rebuild riuscito
4. lavora su queue gia' popolata
5. `run_media_cleanup_tick()` oggi esegue solo worker, non scan
6. per rebuild gallery il punto utile e' scanner scoped + queue + worker

## 7. Casi in cui aggiornare gallery

1. gallery pending su property con `comparison_result = changed`
2. gallery pending con `property_gallery_changed_pending = 1`
3. rebuild solo quando property e' pronta, non nel batch ordinario
4. cleanup solo dopo rebuild riuscito

## 8. Cosa NON fare

1. non aggiornare `wpestate_property_gallery` direttamente
2. non fare sync interno gallery via meta WP
3. non approvare manualmente gallery
4. non reinviare `images[]` negli UPDATE ordinari
5. non fare rebuild nel batch ordinario
6. non usare `wpestate_property_gallery` come fonte rebuild
7. non toccare cleanup core, queue, scanner, worker, monitor admin, `batch-continuation.php` salvo agganci futuri minimi

## 9. Step di sviluppo piccoli e testabili

### Step 1 - Blueprint + discovery finale API rebuild

- Obiettivo: fissare contratto API rebuild e cleanup
- File toccati: nessuno
- Modifica minima prevista: nessuna
- Verifica umana: confermare punti d'ingresso e stop condition
- Stop condition: architettura chiusa
- Rischio principale: hook rebuild troppo generico
- Stato: completato
- Nota validazione: discovery API + scanner scoped completata

#### Discovery validata

- `images[]` costruito in `RealEstate_Sync_WPResidence_API_Writer::format_api_body()`
- `images[]` disponibile solo nel path `create`
- API reali: `create_property()` e `update_property()`
- vincolo architetturale confermato: futuro Gallery Rebuild Worker non deve usare normale path `UPDATE` importer
- rebuild corretto: `update_property(post_id, api_body con images[])`
- scanner cleanup callable direttamente da PHP, no WP-CLI required
- scanner scoped supporta `post-id`, `execute`, `session-id`
- session-id consigliata: `gallery-rebuild`
- fonte rebuild preview/rebuild: `property_gallery_payload_pending_json`
- `wpestate_property_gallery` non e' fonte rebuild, solo gallery visibile vecchia
- `UPDATE` ordinario resta senza `images[]`
- `run_media_cleanup_tick()` esegue solo worker, non scanner

### Step 2 - Gallery Rebuild Worker dry-run/read-only

- Obiettivo: simulare rebuild senza scrivere
- File toccati: nuovo worker separato, se serve
- Modifica minima prevista: solo lettura/preview
- Verifica umana: output property e payload previsto
- Stop condition: dry-run affidabile
- Rischio principale: preview non uguale a rebuild reale
- Stato: validated in production
- Nota validazione: worker dry-run isolato, nessun side effect, preview via `property_gallery_payload_pending_json`, no fallback `wpestate_property_gallery`
- Validazione produzione:
  - `post_id = 214741`
  - `property_import_id = 9999996`
  - `property_gallery_signature = db21d6fa4cc2313dd714fed53adc31fe`
  - `property_gallery_signature_pending = 5fe88aa263405f79e09481913109ab6c`
  - `property_gallery_changed_pending = 1`
  - `property_gallery_payload_pending_json` presente e valido
  - dry-run `status = ok`
  - `images_count = 11`
  - `images_preview` valorizzato con `images[]` corretta
- Prossimo step: Step 3 - Rebuild API reale su singola property pending
- prerequisito: `property_gallery_payload_pending_json` scritto da import su `comparison_result = changed`

### Step 3 - Rebuild API su singola property pending

- Obiettivo: rebuild gallery su una property pending
- File toccati: worker rebuild, integrazione API
- Modifica minima prevista: chiamata API WPResidence con `images[]`
- Verifica umana: gallery visibile aggiornata
- Stop condition: rebuild singolo corretto
- Rischio principale: doppio upload attachment
- Stato: validated
- Nota validazione: rebuild reale singola property OK, scanner scoped OK, pending chiuso

## Validazione produzione Step 3

1. property test:
   - `post_id = 214741`
   - `property_import_id = 9999996`
2. esito rebuild:
   - `api_success = 1`
   - `images_count = 11`
3. esito cleanup:
   - scanner scoped eseguito
   - queue popolata
   - worker cleanup consumato queue
   - queue finale: `done = 38128`, `error = 283`, `pending = 0`
4. chiusura pending:
   - `property_gallery_signature` aggiornata a `5fe88aa263405f79e09481913109ab6c`
   - `property_gallery_signature_pending` rimossa
   - `property_gallery_payload_pending_json` rimossa
   - `property_gallery_changed_pending` rimossa
5. aggiornamento signature:
   - baseline aggiornata con pending signature

### Step 4 - Trigger cleanup scoped post-id

- Obiettivo: innescare cleanup solo per property rebuildata
- File toccati: integrazione post-rebuild
- Modifica minima prevista: scan scoped su `post-id` con `execute=true`
- Verifica umana: queue popolata solo per property target
- Stop condition: cleanup scoped funziona
- Rischio principale: scope troppo ampio
- Stato: validated

### Step 5 - Chiusura pending e aggiornamento signature

- Obiettivo: consolidare stato finale
- File toccati: worker rebuild o supporto meta
- Modifica minima prevista: chiudere pending solo dopo scan cleanup scoped innescato correttamente
- Verifica umana: pending rimosso, signature aggiornata
- Stop condition: stato property pulito
- Rischio principale: pending chiuso prima scan scoped/queue trigger corretto
- Stato: validated

### Step 6 - Batch limitato su piu' pending

- Obiettivo: processare piu' property pending in modo controllato
- File toccati: batch/worker rebuild
- Modifica minima prevista: limit e selezione pending
- Verifica umana: batch piccolo e prevedibile
- Stop condition: multi-property affidabile
- Rischio principale: saturazione upload o cleanup
- Stato: da fare

### Step 7 - Integrazione runtime controllata

- Obiettivo: agganciare rebuild+cleanup nel flusso operativo giusto
- File toccati: point di orchestrazione minimo
- Modifica minima prevista: trigger sicuro, niente auto batch ordinario
- Verifica umana: flusso attivato solo quando previsto
- Stop condition: integrazione runtime limitata e stabile
- Rischio principale: trigger involontario su import normale
- Stato: da fare

### Step 8 - Validazione produzione

- Obiettivo: test end-to-end su property reale
- File toccati: nessuno o minimi
- Modifica minima prevista: nessuna o solo wiring finale
- Verifica umana: gallery, attachment, cleanup, meta
- Stop condition: comportamento confermato in prod
- Rischio principale: caso test non rappresentativo
- Stato: da fare

### Step 9 - Commit/deploy dopo validazione

- Obiettivo: chiudere rilascio solo dopo evidenza
- File toccati: quelli effettivamente validati
- Modifica minima prevista: nessuna extra
- Verifica umana: commit, deploy, smoke test
- Stop condition: rilascio pronto
- Rischio principale: includere codice non validato
- Stato: da fare

## 13. Architettura finale validata

`gallery_changed_pending`
→ `property_gallery_payload_pending_json`
→ rebuild API
→ scanner scoped
→ cleanup queue
→ update signature
→ clear pending
→ cleanup worker elimina media

## 14. Stato finale step

1. Step 1: validated
2. Step 2: validated
3. Step 3: validated

## 15. Residui per passare da manual singola property a worker automatico

1. aggancio runtime controllato del worker rebuild
2. selezione batch limitato su piu' pending
3. eventuale orchestration minimale fuori import ordinario

## 16. Discovery Step 4 validata

- Opzione scelta: C, fase separata post-import
- Motivazioni: no rebuild durante UPDATE ordinario, rebuild isolato dal flusso import, meno race su import notturno
- Lock model: lock batch esistente + lock dedicato gallery rebuild + session-id stabile `gallery-rebuild`
- Failure model: API fail/timeout lascia pending intatto; scanner fail lascia pending intatto; retry naturale sul tick successivo
- Strategia una property per tick: prima iterazione automatica limita rischio e rende retry facile
- Stato: GO per implementazione futura Step 4

## 10. Test plan umano

1. property pending singola
2. rebuild API con `images[]`
3. gallery visibile aggiornata
4. attachment nuovi creati da WPResidence
5. scan cleanup scoped su stesso `post-id`
6. queue contiene solo attachment extra target oppure nessun item
7. `property_gallery_signature` aggiornata dopo scan trigger
8. `property_gallery_signature_pending` svuotata
9. `property_gallery_changed_pending = 0`
10. `property_gallery_payload_pending_json` presente e valido
11. worker cleanup rimuove extra nei tick successivi

## 11. Rischi e stop condition

### 11.1 Rischi

1. rebuild API crea attachment duplicati se cleanup non parte
2. scan troppo largo tocca altre property
3. pending chiuso prima scan scoped/queue trigger corretto
4. session queue non stabile
5. uso accidentale del normale `UPDATE` importer path rimuove `images[]` e impedisce rebuild gallery
6. uso `wpestate_property_gallery` come preview/rebuild source porta gallery vecchia

### 11.2 Stop condition

1. gallery visibile aggiornata
2. scan scoped su `post-id` innescato
3. queue popolata oppure nessun extra trovato
4. signature baseline aggiornata
5. pending chiuso
6. worker cleanup rimuove extra nei tick successivi
7. nessun effetto collaterale fuori property target

## 12. Tabella stato avanzamento iniziale

| Data | Step | Stato | Commit | Note validazione |
|---|---|---|---|---|
| 2026-06-06 | 1 | completed | - | discovery API + scanner scoped completata |
| 2026-06-06 | 2 | validated in production | - | Gallery Rebuild Worker dry-run/read-only |
| 2026-06-06 | 3 | validated in production | - | Rebuild API su singola property pending |
| 2026-06-06 | 4 | validated in production | - | Trigger cleanup scoped post-id |
| 2026-06-06 | 5 | validated in production | - | Chiusura pending e aggiornamento signature |
| 2026-06-06 | 6 | da fare | - | Batch limitato su piu' pending |
| 2026-06-06 | 7 | da fare | - | Integrazione runtime controllata |
| 2026-06-06 | 8 | da fare | - | Validazione produzione |
| 2026-06-06 | 9 | da fare | - | Commit/deploy dopo validazione |
