# 📧 Email Notification - Implementation Guide

**Status:** READY TO IMPLEMENT
**Priority:** MEDIUM
**Estimated Time:** 2-3 hours
**Created:** 2025-12-04

---

## 📋 OVERVIEW

Sistema di notifica email con ASCII art che invia:
- ✅ Email di completamento con statistiche dettagliate
- ⚠️ Email di errore con riepilogo problemi
- 📎 Log file allegato

---

## 🎯 REQUISITI

### Quando Inviare Email:

1. **Completamento Successo**
   - Trigger: Queue completamente vuota (tutti items processati)
   - Condizione: `$result['complete'] = true` E nessun pending item in queue
   - Una sola email per session

2. **Errore Critico**
   - Trigger: Accumulo di N errori (es. 5 errori)
   - Trigger: Timeout detection (nessun progresso per X minuti)
   - Trigger: Exception non recuperabile

### Cosa Includere:

**Statistiche Generali:**
- Agenzie: nuove, aggiornate, skippate
- Proprietà: nuove, aggiornate, skippate
- Tempi: inizio, fine, durata
- Performance: batch count, items/min, tempo/batch

**Allegato:**
- Log file della sessione (se disponibile)
- Path: `wp-content/plugins/realestate-sync-plugin/logs/import-logs/realestate-sync-YYYY-MM-DD.log`

---

## 📁 FILE CREATI

### 1. Email Notifier Class

**File:** `includes/class-realestate-sync-email-notifier.php`

**Status:** ✅ COMPLETATO

**Funzioni:**
- `send_completion_email($session_id, $stats, $log_file_path)`
- `send_error_email($session_id, $stats, $errors, $log_file_path)`
- ASCII art rendering con progress bars

---

## 🔧 MODIFICHE DA FARE

### 1. Batch Continuation - Stats Tracking

**File:** `batch-continuation.php`

**Modifiche:**

```php
// DOPO: $result = $batch_processor->process_next_batch();

// Increment batch count
$progress['batch_count'] = ($progress['batch_count'] ?? 0) + 1;

// Get action counts from batch result
$actions = $result['actions'] ?? [];
$progress['agencies_inserted'] = ($progress['agencies_inserted'] ?? 0) + ($actions['agencies_inserted'] ?? 0);
$progress['agencies_updated'] = ($progress['agencies_updated'] ?? 0) + ($actions['agencies_updated'] ?? 0);
$progress['agencies_skipped'] = ($progress['agencies_skipped'] ?? 0) + ($actions['agencies_skipped'] ?? 0);
$progress['properties_inserted'] = ($progress['properties_inserted'] ?? 0) + ($actions['properties_inserted'] ?? 0);
$progress['properties_updated'] = ($progress['properties_updated'] ?? 0) + ($actions['properties_updated'] ?? 0);
$progress['properties_skipped'] = ($progress['properties_skipped'] ?? 0) + ($actions['properties_skipped'] ?? 0);

update_option('realestate_sync_background_import_progress', $progress);
```

**Quando completato:**

```php
// QUANDO: if ($result['complete'])

// Load Email Notifier
require_once dirname(__FILE__) . '/includes/class-realestate-sync-email-notifier.php';

// Get log file path
$log_date = gmdate('Y-m-d');
$log_file = dirname(__FILE__) . '/logs/import-logs/realestate-sync-' . $log_date . '.log';

// Send completion email
RealEstate_Sync_Email_Notifier::send_completion_email(
    $session_id,
    $progress,
    file_exists($log_file) ? $log_file : null
);

error_log('[BATCH-CONTINUATION] >>> Completion email sent');
```

**Quando errore:**

```php
// DENTRO: catch (Exception $e)

// Track errors
$progress['errors'] = $progress['errors'] ?? [];
$progress['errors'][] = [
    'time' => time(),
    'message' => $e->getMessage(),
    'batch' => $progress['batch_count'] ?? 0
];

// If too many errors, send alert email
$error_count = count($progress['errors']);
if ($error_count >= 5) {

    error_log('[BATCH-CONTINUATION] >>> Too many errors, sending alert email');

    // Load Email Notifier
    require_once dirname(__FILE__) . '/includes/class-realestate-sync-email-notifier.php';

    // Get log file
    $log_date = gmdate('Y-m-d');
    $log_file = dirname(__FILE__) . '/logs/import-logs/realestate-sync-' . $log_date . '.log';

    // Send error email
    RealEstate_Sync_Email_Notifier::send_error_email(
        $session_id,
        $progress,
        $progress['errors'],
        file_exists($log_file) ? $log_file : null
    );

    // Clear errors to prevent spam
    $progress['errors'] = [];
}

update_option('realestate_sync_background_import_progress', $progress);
```

---

### 2. Batch Processor - Return Action Counts

**File:** `includes/class-realestate-sync-batch-processor.php`

**Modifiche:**

Nel metodo `process_next_batch()`, dobbiamo ritornare i conteggi delle azioni:

```php
// ALLA FINE del metodo process_next_batch()

return array(
    'success' => true,
    'processed' => $processed_count,
    'complete' => ($pending_count === 0),
    'stats' => array(
        'pending' => $pending_count,
        'processing' => $processing_count,
        'done' => $done_count,
        'error' => $error_count,
        'total' => $total_count
    ),
    // ✅ NEW: Add action breakdown
    'actions' => array(
        'agencies_inserted' => $this->stats['agencies_inserted'] ?? 0,
        'agencies_updated' => $this->stats['agencies_updated'] ?? 0,
        'agencies_skipped' => $this->stats['agencies_skipped'] ?? 0,
        'properties_inserted' => $this->stats['properties_inserted'] ?? 0,
        'properties_updated' => $this->stats['properties_updated'] ?? 0,
        'properties_skipped' => $this->stats['properties_skipped'] ?? 0,
    )
);
```

**Modifiche nel loop di processing:**

```php
// DOPO: $item_result = $this->process_single_item($item);

if ($item_result['success']) {
    $action = $item_result['action'] ?? 'unknown';
    $item_type = $item->item_type; // 'agency' or 'property'

    // Track action
    $stat_key = $item_type . 's_' . $action; // es: "properties_inserted"
    $this->stats[$stat_key] = ($this->stats[$stat_key] ?? 0) + 1;
}
```

---

### 3. Import Engine - Return Action in Result

**File:** `includes/class-realestate-sync-import-engine.php`

**Verifica:**

Il metodo `process_single_property()` già ritorna:

```php
return array(
    'success' => true,
    'property_id' => $property_id,
    'action' => $change_status['action'] // ✅ Già presente
);
```

**Status:** ✅ GIÀ OK

---

## 📊 ESEMPIO OUTPUT EMAIL

### Email Successo:

```
╔══════════════════════════════════════════════════════════════════╗
║                                                                  ║
║        🏠  REALESTATE SYNC - IMPORT COMPLETATO  🏠               ║
║                                                                  ║
╚══════════════════════════════════════════════════════════════════╝

Session: import_69313fc126da87.27853391
Status:  ✓ COMPLETATO

┌──────────────────────────────────────────────────────────────────┐
│ ⏱️  TEMPI                                                         │
├──────────────────────────────────────────────────────────────────┤
│ Inizio:     04/12/2025 09:01:13 UTC                             │
│ Fine:       04/12/2025 11:16:45 UTC                             │
│ Durata:     2h 15m 32s                                           │
│ Batch:      162 batch processati (5 items/batch)                │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ 📊 AGENZIE                                                        │
├──────────────────────────────────────────────────────────────────┤
│ Nuove:      ████████████████████████░░  25  (83%)               │
│ Aggiornate: ████░░░░░░░░░░░░░░░░░░░░   5  (17%)               │
│ Skippate:   ░░░░░░░░░░░░░░░░░░░░░░░░   0   (0%)               │
│             ─────────────────────────                            │
│ TOTALE:                                30                        │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ 🏘️  PROPRIETÀ                                                     │
├──────────────────────────────────────────────────────────────────┤
│ Nuove:      ████████████████████████  725  (93%)               │
│ Aggiornate: ██░░░░░░░░░░░░░░░░░░░░░░  45   (6%)               │
│ Skippate:   ░░░░░░░░░░░░░░░░░░░░░░░░   8   (1%)               │
│             ─────────────────────────                            │
│ TOTALE:                               778                        │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ ⚡ PERFORMANCE                                                    │
├──────────────────────────────────────────────────────────────────┤
│ Velocità media:    6.2 items/minuto                             │
│ Tempo/batch:       9.7 secondi                                   │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ 🔗 LINKS                                                          │
├──────────────────────────────────────────────────────────────────┤
│ Proprietà:  https://trentinoimmobiliare.it/wp-admin/edit.ph...  │
│ Agenzie:    https://trentinoimmobiliare.it/wp-admin/edit.ph...  │
└──────────────────────────────────────────────────────────────────┘

╔══════════════════════════════════════════════════════════════════╗
║  Generato da RealEstate Sync Plugin v1.6                         ║
╚══════════════════════════════════════════════════════════════════╝

ALLEGATO: realestate-sync-2025-12-04.log (245 KB)
```

---

## ✅ TESTING CHECKLIST

Prima di deployare in produzione:

- [ ] Test email successo con import completo
- [ ] Test email errore con errori simulati
- [ ] Verifica allegato log funziona
- [ ] Verifica ASCII art rendering corretto
- [ ] Test con email client diversi (Gmail, Outlook, etc.)
- [ ] Verifica stats accurate (insert/update/skip counts)
- [ ] Test che non vengano inviate email multiple per stessa sessione
- [ ] Test che email errore venga inviata solo dopo N errori

---

## 🚨 CONSIDERAZIONI IMPORTANTI

### 1. Prevenire Email Duplicate

**Problema:** Se il cron gira più volte e trova queue vuota, potrebbe inviare email multiple.

**Soluzione:** Usare flag in progress option:

```php
// Prima di inviare email
if (!empty($progress['completion_email_sent'])) {
    error_log('[BATCH-CONTINUATION] Completion email already sent, skipping');
    return;
}

// Invia email...

// Marca come inviata
$progress['completion_email_sent'] = true;
update_option('realestate_sync_background_import_progress', $progress);
```

### 2. Limite Allegato Email

WordPress `wp_mail()` può avere limiti sulla dimensione allegati (tipicamente 10-25 MB).

**Soluzione:** Se log > 10MB, non allegare e includere link per download invece.

### 3. Configurazione Email Destinatari

Per ora usa `get_option('admin_email')`.

**Futuro:** Aggiungere campo settings per email custom:

```php
$recipients = get_option('realestate_sync_notification_emails', get_option('admin_email'));
```

---

## 📝 DEPLOYMENT STEPS

1. ✅ File Email Notifier già creato
2. ⏳ Modificare Batch Continuation (stats tracking + email trigger)
3. ⏳ Modificare Batch Processor (return action counts)
4. ⏳ Test in locale
5. ⏳ Deploy in staging/produzione
6. ⏳ Verificare prima email ricevuta

---

## 🔗 FILE CORRELATI

- `includes/class-realestate-sync-email-notifier.php` (✅ Creato)
- `batch-continuation.php` (⏳ Da modificare)
- `includes/class-realestate-sync-batch-processor.php` (⏳ Da modificare)
- `includes/class-realestate-sync-import-engine.php` (✅ Già OK)

---

## 📅 TIMELINE SUGGERITA

**Quando implementare:**
- Dopo aver risolto il bug "action: update" con "WP Post ID: none"
- Dopo aver verificato che l'import completa correttamente 808 items
- Quando il sistema è stabile e testato

**Priorità:** MEDIUM (nice to have, non urgente)

---

**Documento Creato:** 2025-12-04
**Ultima Modifica:** 2025-12-04
**Autore:** Claude Code Assistant
