Buona lettura di tutto il piano. Ora la validazione contro il codice reale.

---

## Validazione piano vs codice

### ✅ Cosa è coerente col codice esistente

**`Deletion_Manager` — contratto del delete singolo (Step 6)** Il piano prevede di formalizzare `success / not_found / error`. Il metodo `delete_property()` restituisce già esattamente questi tre stati (`success`, `not_found`, e fallimento implicito). Il piano è allineato: non si tratta di riscrivere, ma di esporre quell'interfaccia a un entry point pubblico richiamabile dal worker. È un intervento chirurgico.

**`Deletion_Manager` — `update_tracking_deleted()`** Il piano menciona la dipendenza da `Tracking_Manager` ma nel codice reale la tracking update è gestita direttamente con `$wpdb->update()` inline nel `Deletion_Manager` stesso, senza delegare a un manager esterno. Non è un problema, ma la sezione "Dipendenze" del piano che cita `Tracking_Manager` come classe esterna è imprecisa — in realtà non esiste questa delega.

**`Queue_Manager` — pattern da riusare per `Delete_Queue_Manager`** Il piano prevede di creare `class-realestate-sync-delete-queue-manager.php` da zero con CRUD, stats, recovery stantii. Il `Queue_Manager` esistente è un modello diretto, quasi uno schema da clonare e adattare. Il piano non lo cita esplicitamente come riferimento, ma dovresti farlo per coerenza e velocità.

**`Import_Verifier` — timing di invocazione** Il piano dice "adattare il momento di invocazione, nessuna nuova logica". Il codice lo conferma: `verify_session()` lavora esclusivamente su `status = 'done'` nella import queue. Basta posticipare la chiamata nel cron — nessuna modifica interna alla classe. Piano corretto.

---

### ⚠️ Punti da precisare o correggere

**1. `Deletion_Manager` — doppio cleanup attachment**

Il piano segnala il rischio correttamente (sezione 6, *Media cleanup*), ma lo tratta come "rischio osservato da monitorare". Vale la pena chiarire subito cosa fa già oggi il codice: `delete_property()` chiama `wp_delete_attachment($id, true)` esplicitamente per ogni attachment **prima** di `wp_delete_post`. Se esiste anche un hook `before_delete_post` gestito da `Attachment_Cleanup`, i file potrebbero essere già spariti quando `wp_delete_attachment` li cerca. Di per sé non è un errore bloccante (WP gestisce attachment già assenti silenziosamente), ma il conteggio `disk_space_freed` sarà inaffidabile perché `filesize()` restituisce 0 su file già rimossi. Suggerisco di annotarlo nel piano come **comportamento noto accettato**, non solo come rischio generico.

**2. `Delete_Queue_Manager` — mancanza di unique key esplicita nel piano**

Il piano cita "verifica unique key session/item/reason" nel test del Step 1, ma non definisce esplicitamente lo schema della tabella. Guardando `Queue_Manager` come riferimento, manca in quel codice una `UNIQUE KEY (session_id, item_type, item_id)`. Se la nuova tabella ha la stessa assenza, il bulk insert di candidati missing rischia duplicati in caso di retry dell'orchestratore. Da aggiungere esplicitamente allo schema nel piano.

**3. `not_found` → stato queue: ambiguità residua**

Il piano dice `not_found → done oppure skipped (non bloccante)` senza decidere. Il `Deletion_Manager` oggi logga `not found - skipping` ma non distingue tra "mai esistita in WP" e "già eliminata in un run precedente". Per la delete queue entrambi i casi sono innocui, ma ai fini del report e dei contatori è utile distinguerli — altrimenti le stats finali nel `Email_Report` saranno ambigue. Conviene scegliere ora: **`skipped`** per not found, **`done`** solo per delete effettiva.

**4. `Batch_Orchestrator` — detection fuori filtro provincia**

Il piano identifica correttamente il rischio. Ma non specifica dove nel codice avviene il filtro provincia attuale. Senza vedere l'orchestratore allegato è impossibile validare questo punto completamente — però è il rischio più sottile dell'intero piano: se la raccolta di `feed_active_property_ids` avviene prima del filtro, i candidati missing includeranno properties di province non importate, generando falsi positivi massicci. **Da verificare esplicitamente nel codice dell'orchestratore prima di iniziare Step 2.**

**5. Blocco nuovo import durante delete (Step 7)**

Il piano lo cita come comportamento atteso in `batch-continuation.php`. Ma non specifica quale meccanismo lo implementa: lock transient esistente, nuova option di stato, o controllo sulla delete queue stessa? Va definito prima di Step 7, altrimenti il check rischia di essere aggiunto in modo incoerente con il lock già presente.

---

### 📋 Riepilogo azioni consigliate sul documento

| #   | Cosa                                                                                                            | Priorità |
| --- | --------------------------------------------------------------------------------------------------------------- | -------- |
| 1   | Correggere dipendenza `Tracking_Manager` nel piano: nel codice reale non è una classe delegata                  | Bassa    |
| 2   | Aggiungere `Queue_Manager` come riferimento esplicito per la struttura di `Delete_Queue_Manager`                | Bassa    |
| 3   | Definire schema tabella `realestate_delete_queue` con UNIQUE KEY `(session_id, item_id)`                        | Alta     |
| 4   | Scegliere definitivamente `skipped` vs `done` per `not_found`, documentarlo                                     | Media    |
| 5   | Annotare il doppio-cleanup come comportamento noto accettato con effetto su `disk_space_freed`                  | Bassa    |
| 6   | Aggiungere nota esplicita: verificare nel codice orchestratore dove avviene il filtro provincia prima di Step 2 | Alta     |
| 7   | Definire meccanismo di blocco nuovo import durante delete prima di Step 7                                       | Media    |

Il piano è complessivamente solido e l'ordine degli step è corretto. I punti 3, 6 e 7 sono quelli da risolvere prima di mettere mano al codice.
