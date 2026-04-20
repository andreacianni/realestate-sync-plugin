Sulla base dell'analisi del documento `2026-04-20-delete-missing-properties-implementation-plan-validated.md` e del codice sorgente fornito, ecco la validazione tecnica del piano di implementazione.

### 1. Coerenza Architetturale

Il piano è **architetturalmente solido** e segue correttamente il pattern a "stadi isolati" già presente nel plugin.

- **Punto di Iniezione (Orchestrator):** L'uso di `RealEstate_Sync_Batch_Orchestrator` per la detection è corretto. Poiché questa classe gestisce già il parsing iniziale dell'XML, aggiungere la raccolta degli ID presenti nel feed è l'approccio più efficiente per identificare le proprietà mancanti (missing).

- **Separazione delle Code:** La decisione di creare una nuova tabella `realestate_delete_queue` è eccellente. Evita di inquinare la `realestate_import_queue` gestita dal `Queue_Manager` attuale, riducendo il rischio di regressioni sui processi di importazione standard.

- **Gestione Asincrona:** L'integrazione del worker di cancellazione in `batch-continuation.php` (richiamato dopo l'import) è coerente con la logica di processamento a blocchi del plugin.

### 2. Analisi dei File e Impatto sul Codice

- **`class-realestate-sync-deletion-manager.php`:** Il piano prevede il riuso di questa classe. **Attenzione:** Attualmente la classe è dichiarata come "Always performs LIVE deletion" (sempre in modalità live). Per supportare i guardrail (`dry_run`, `soft`) previsti nel piano, la classe dovrà essere modificata per accettare una modalità operativa nel costruttore o nei metodi di cancellazione.

- **`class-realestate-sync-batch-orchestrator.php`:** Il piano richiede di non alterare `total_queued`. Questo è critico perché `total_queued` viene usato per monitorare l'avanzamento dell'importazione. La nuova logica dovrà gestire contatori separati (es. `total_deleted_queued`).

- **`class-realestate-sync-email-report.php`:** Dovrà essere aggiornato per leggere i dati dalla nuova coda di cancellazione, altrimenti il report finale non mostrerà l'esito delle eliminazioni "missing from feed".

### 3. Valutazione dei Guardrail e Sicurezza

Il piano introduce misure di sicurezza (guardrail) indispensabili per operazioni distruttive:

- **Kill Switch e Cap:** L'inserimento di un limite massimo (cap) di cancellazioni per sessione è fondamentale per prevenire eliminazioni di massa dovute a feed XML corrotti o parziali.

- **Stato `dry_run`:** Permette di validare la logica di detection senza toccare il database di WordPress, il che è lo step di sicurezza più importante durante il rollout.

### 4. Punti di Attenzione (Rischi identificati)

1. **Conflitto con `deleted=1`:** Esiste un rischio di sovrapposizione tra le proprietà marcate esplicitamente come eliminate nel feed (`deleted=1`) e quelle semplicemente assenti. Il piano menziona di mantenere i rami separati, ma la logica di `Deletion_Manager` deve assicurarsi di non tentare di eliminare due volte lo stesso oggetto se presente in entrambe le liste.

2. **Stallo degli Item `processing`:** Il `Batch_Processor` attuale ha una soglia di 15 minuti per gli item stantii. Il nuovo sistema di cancellazione dovrà implementare una logica di recovery identica per evitare che un errore durante una cancellazione blocchi l'intera coda.

3. **Spostamento Finalizzazione:** Il punto 7 del piano ("Spostare la finalizzazione alla vera fine sessione") è delicato. Attualmente l'orchestratore logga il completamento della sua fase ma lascia la traccia aperta per il background. Bisognerà assicurarsi che `Import_Verifier` e `Email_Report` non partano finché sia la coda import che la coda delete non sono `done`.

### Conclusione della Validazione

Il documento è **VALIDATO** con le seguenti raccomandazioni:

1. **Modifica `Deletion_Manager`:** Aggiornare la classe per supportare il parametro `$mode` (dry_run/soft/live) prima di procedere allo Step 6.

2. **Unicità della Coda:** Assicurarsi che la detection nel `Batch_Orchestrator` escluda dagli ID "missing" quelli già processati come "deleted=1" per evitare ridondanza.

3. **Logging:** Estendere l'uso del `Debug_Tracker` anche alla fase di cancellazione per mantenere la tracciabilità end-to-end richiesta dal sistema.

Il piano segue correttamente le best practice di sviluppo difensivo e può procedere verso l'implementazione dello Step 1.
