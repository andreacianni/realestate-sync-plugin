# 📧 Email Notification System - Roadmap & Implementation Guide

**Status:** 🟡 In Planning
**Version:** 1.0
**Created:** 2025-12-09
**Plugin Version Target:** 1.8.0

---

## 📖 Table of Contents

- [Overview](#overview)
- [Current Situation](#current-situation)
- [Architecture](#architecture)
- [Implementation Roadmap](#implementation-roadmap)
  - [Sprint 1: Foundation](#sprint-1-foundation--essential)
  - [Sprint 2: Configuration](#sprint-2-configuration)
  - [Sprint 3: Monitoring](#sprint-3-monitoring)
  - [Sprint 4: Polish](#sprint-4-polish)
- [Technical Specifications](#technical-specifications)
- [Testing Plan](#testing-plan)
- [Deployment Strategy](#deployment-strategy)

---

## Overview

Implementazione di un sistema completo di notifiche email per il processo di importazione immobili, con tracking dettagliato delle statistiche e visualizzazione in dashboard.

### Obiettivi

1. ✅ Email automatica al completamento import (successo/errore)
2. ✅ Tracking completo statistiche sessione
3. ✅ Configurazione destinatari email da dashboard
4. ✅ Visualizzazione storico import in dashboard
5. ✅ Allegato log file alle notifiche

### Stakeholders

- **Primary Recipient:** `importer@trentinoimmobiliare.it`
- **Optional Recipients:** Lista configurabile da dashboard
- **Admin Dashboard:** Visualizzazione statistiche e storico

---

## Current Situation

### ✅ Già Implementato

| Componente | File | Status |
|------------|------|--------|
| Email Notifier Class | `includes/class-realestate-sync-email-notifier.php` | ✅ Completo |
| ASCII Email Templates | Inclusi nella classe | ✅ Completo |
| Debug Tracker con Log | `includes/class-realestate-sync-debug-tracker.php` | ✅ Funzionante |
| Orchestrator Stats | Nel log JSON | ✅ Genera dati |

### ❌ Mancante

| Componente | Motivo |
|------------|--------|
| Email Notifier caricamento | Non incluso in `realestate-sync.php` |
| Statistiche persistenti | Non salvate in DB, solo nei log |
| Chiamata invio email | Non integrata nel flusso |
| Configurazione email | Nessuna UI per impostazioni |
| Dashboard storico | Non visualizzabile |
| Log path accessor | Nessun metodo pubblico nel tracker |

### 🔍 Problema Chiave

**L'Orchestrator genera le statistiche troppo presto:**
```json
[ORCHESTRATOR] Orchestrator phase complete, background continuation will follow
{
  "session_id": "import_693766d3674112.78964832",
  "total_queued": 781,
  "agencies_queued": 25,
  "properties_queued": 756,
  "first_batch_processed": 5,
  "complete": false,  // ⚠️ Non è ancora finito!
  "remaining": 776
}
```

**Soluzione:** Salvare statistiche in una tabella dedicata, aggiornata dal Batch Processor durante tutto il processo.

---

## Architecture

### Component Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    Import Process Flow                      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│  Batch Orchestrator                                          │
│  • Crea sessione in DB                                       │
│  • Inizializza stats (queued counts)                         │
└──────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│  Batch Processor (loop)                                      │
│  • Processa item                                             │
│  • Aggiorna stats in DB (inserted/updated/skipped/failed)    │
│  • Ripete fino a completion                                  │
└──────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│  Completion Handler                                          │
│  • Session Manager → mark_completed()                        │
│  • Recupera final stats da DB                                │
│  • Email Notifier → send_completion_email()                  │
└──────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│  Email Notifier                                              │
│  • Build ASCII art email                                     │
│  • Allega log file                                           │
│  • wp_mail() ai destinatari configurati                      │
└──────────────────────────────────────────────────────────────┘
```

### Database Schema

```sql
-- Nuova tabella per tracking sessioni
CREATE TABLE wp_realestate_import_sessions (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id varchar(100) NOT NULL UNIQUE,

    -- Timing
    start_time datetime NOT NULL,
    end_time datetime DEFAULT NULL,
    total_duration int DEFAULT 0 COMMENT 'Secondi',
    status varchar(20) NOT NULL DEFAULT 'running',

    -- Stats Agenzie
    agencies_queued int DEFAULT 0,
    agencies_inserted int DEFAULT 0,
    agencies_updated int DEFAULT 0,
    agencies_skipped int DEFAULT 0,
    agencies_failed int DEFAULT 0,

    -- Stats Proprietà
    properties_queued int DEFAULT 0,
    properties_inserted int DEFAULT 0,
    properties_updated int DEFAULT 0,
    properties_skipped int DEFAULT 0,
    properties_failed int DEFAULT 0,

    -- Stats Cancellazioni
    deletion_stats longtext DEFAULT NULL COMMENT 'JSON',

    -- Metadata
    log_file_path varchar(255) DEFAULT NULL,
    xml_file_path varchar(255) DEFAULT NULL,
    batch_count int DEFAULT 0,
    error_message text DEFAULT NULL,

    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY session_id (session_id),
    KEY status (status),
    KEY start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Implementation Roadmap

### Sprint 1: Foundation ~ ESSENTIAL

**Durata stimata:** 4-6 ore
**Priorità:** 🔴 ALTA

#### Task 1.1: Database Table Creation

**File:** `includes/class-realestate-sync-session-manager.php` (nuovo)

```php
<?php
/**
 * Session Manager Class
 *
 * Manages import session statistics and lifecycle
 *
 * @package RealEstate_Sync
 * @version 1.8.0
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Session_Manager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'realestate_import_sessions';
    }

    /**
     * Create sessions table
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL UNIQUE,
            start_time datetime NOT NULL,
            end_time datetime DEFAULT NULL,
            total_duration int DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'running',
            agencies_queued int DEFAULT 0,
            agencies_inserted int DEFAULT 0,
            agencies_updated int DEFAULT 0,
            agencies_skipped int DEFAULT 0,
            agencies_failed int DEFAULT 0,
            properties_queued int DEFAULT 0,
            properties_inserted int DEFAULT 0,
            properties_updated int DEFAULT 0,
            properties_skipped int DEFAULT 0,
            properties_failed int DEFAULT 0,
            deletion_stats longtext DEFAULT NULL,
            log_file_path varchar(255) DEFAULT NULL,
            xml_file_path varchar(255) DEFAULT NULL,
            batch_count int DEFAULT 0,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY status (status),
            KEY start_time (start_time)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create new session
     */
    public function create_session($session_id, $xml_file_path = null) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'session_id' => $session_id,
                'start_time' => current_time('mysql'),
                'xml_file_path' => $xml_file_path,
                'status' => 'running'
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            error_log("[SESSION-MANAGER] Failed to create session: " . $wpdb->last_error);
            return false;
        }

        error_log("[SESSION-MANAGER] ✅ Session created: {$session_id}");
        return true;
    }

    /**
     * Update session statistics
     */
    public function update_stats($session_id, $stats_array) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            $stats_array,
            array('session_id' => $session_id),
            null,
            array('%s')
        );

        return $result !== false;
    }

    /**
     * Increment specific stat counter
     */
    public function increment_stat($session_id, $stat_name, $value = 1) {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name}
             SET {$stat_name} = {$stat_name} + %d
             WHERE session_id = %s",
            $value,
            $session_id
        ));
    }

    /**
     * Mark session as completed
     */
    public function mark_completed($session_id) {
        global $wpdb;

        $session = $this->get_session($session_id);
        if (!$session) {
            return false;
        }

        $start_time = strtotime($session['start_time']);
        $end_time = time();
        $duration = $end_time - $start_time;

        $result = $wpdb->update(
            $this->table_name,
            array(
                'status' => 'completed',
                'end_time' => current_time('mysql'),
                'total_duration' => $duration
            ),
            array('session_id' => $session_id),
            array('%s', '%s', '%d'),
            array('%s')
        );

        error_log("[SESSION-MANAGER] ✅ Session completed: {$session_id} (duration: {$duration}s)");
        return $result !== false;
    }

    /**
     * Mark session as failed
     */
    public function mark_failed($session_id, $error_message = null) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            array(
                'status' => 'failed',
                'end_time' => current_time('mysql'),
                'error_message' => $error_message
            ),
            array('session_id' => $session_id),
            array('%s', '%s', '%s'),
            array('%s')
        );

        error_log("[SESSION-MANAGER] ❌ Session failed: {$session_id}");
        return $result !== false;
    }

    /**
     * Get session data
     */
    public function get_session($session_id) {
        global $wpdb;

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE session_id = %s",
            $session_id
        ), ARRAY_A);

        return $session;
    }

    /**
     * Get recent sessions
     */
    public function get_recent_sessions($limit = 20) {
        global $wpdb;

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             ORDER BY start_time DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return $sessions;
    }

    /**
     * Set log file path for session
     */
    public function set_log_file_path($session_id, $log_file_path) {
        return $this->update_stats($session_id, array(
            'log_file_path' => $log_file_path
        ));
    }
}
```

**Checklist:**
- [ ] Creare file `includes/class-realestate-sync-session-manager.php`
- [ ] Implementare tutti i metodi
- [ ] Aggiungere error handling
- [ ] Testare creazione tabella
- [ ] Testare CRUD operations

---

#### Task 1.2: Integrate Session Creation

**File:** `includes/class-realestate-sync-batch-orchestrator.php`

**Modifiche necessarie:**

```php
// Dopo la riga ~200 (dopo start_trace)
$session_manager = new RealEstate_Sync_Session_Manager();
$session_manager->create_session($session_id, $xml_file);

// Dopo la creazione della queue (riga ~408)
$session_manager->update_stats($session_id, array(
    'agencies_queued' => $agencies_queued,
    'properties_queued' => $properties_queued,
    'batch_count' => 0
));

// Salvare log path se disponibile
if ($tracker->is_active()) {
    $log_path = $tracker->get_log_file_path(); // DA IMPLEMENTARE
    if ($log_path) {
        $session_manager->set_log_file_path($session_id, $log_path);
    }
}
```

**Checklist:**
- [ ] Istanziare Session Manager nell'orchestrator
- [ ] Chiamare `create_session()` all'inizio
- [ ] Salvare `agencies_queued` e `properties_queued`
- [ ] Testare con import reale

---

#### Task 1.3: Track Stats During Processing

**File:** `includes/class-realestate-sync-batch-processor.php`

**Modifiche necessarie:**

```php
// Nel costruttore, aggiungere:
private $session_manager;

public function __construct($session_id, $xml_file_path, $mark_as_test = false) {
    // ... existing code ...
    $this->session_manager = new RealEstate_Sync_Session_Manager();
}

// Nel metodo process_agency() (dopo successo):
if ($result['action'] === 'inserted') {
    $this->session_manager->increment_stat($this->session_id, 'agencies_inserted');
} elseif ($result['action'] === 'updated') {
    $this->session_manager->increment_stat($this->session_id, 'agencies_updated');
} elseif ($result['action'] === 'skipped') {
    $this->session_manager->increment_stat($this->session_id, 'agencies_skipped');
}

// Nel catch di process_agency():
$this->session_manager->increment_stat($this->session_id, 'agencies_failed');

// Stesso pattern per process_property()
// Incrementare batch_count ad ogni batch completato
$this->session_manager->increment_stat($this->session_id, 'batch_count');
```

**Checklist:**
- [ ] Aggiungere Session Manager al Batch Processor
- [ ] Incrementare stats per agencies (inserted/updated/skipped/failed)
- [ ] Incrementare stats per properties
- [ ] Incrementare batch_count
- [ ] Testare che i conteggi siano accurati

---

#### Task 1.4: Add Log Path Accessor

**File:** `includes/class-realestate-sync-debug-tracker.php`

**Aggiungere metodo pubblico:**

```php
/**
 * Get log file path for current trace
 *
 * @return string|null Log file path or null if no active trace
 */
public function get_log_file_path() {
    if (!$this->trace_id) {
        return null;
    }

    $log_dir = plugin_dir_path(dirname(__FILE__)) . 'logs';
    $log_file_path = $log_dir . '/import-' . $this->trace_id . '.log';

    // Verifica che il file esista
    if (!file_exists($log_file_path)) {
        return null;
    }

    return $log_file_path;
}
```

**Checklist:**
- [ ] Aggiungere metodo `get_log_file_path()`
- [ ] Testare che restituisca il path corretto
- [ ] Gestire caso trace non attivo

---

#### Task 1.5: Load Email Notifier Class

**File:** `realestate-sync.php`

**Dopo riga 144 (dopo deletion-manager):**

```php
// Email & Session Management (v1.8.0+)
require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-session-manager.php';
require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-email-notifier.php';
```

**Checklist:**
- [ ] Aggiungere require_once per Session Manager
- [ ] Aggiungere require_once per Email Notifier
- [ ] Verificare che non ci siano errori di caricamento

---

#### Task 1.6: Trigger Email on Completion

**File:** `includes/class-realestate-sync-batch-processor.php` (linea ~345)

**Dopo `end_trace()` quando `is_complete = true`:**

```php
// 🏁 End trace if ALL batches complete
if ($is_complete && $this->tracker->is_active()) {
    $this->tracker->end_trace('completed', array(
        'session_id' => $this->session_id,
        'final_stats' => $stats,
        'total_processed' => $stats['completed'],
        'total_errors' => $stats['failed'],
        'completion_time' => date('Y-m-d H:i:s')
    ));

    // Clean up trace metadata
    delete_option('realestate_sync_current_trace_id');
    delete_option('realestate_sync_current_trace_start_time');
    delete_option('realestate_sync_current_trace_context');

    error_log("[BATCH-PROCESSOR] ✅ All batches complete - trace ended and session log closed");

    // ✨ NEW: Mark session as completed
    $this->session_manager->mark_completed($this->session_id);

    // ✨ NEW: Send completion email
    $session_stats = $this->session_manager->get_session($this->session_id);
    if ($session_stats) {
        RealEstate_Sync_Email_Notifier::send_completion_email(
            $this->session_id,
            $session_stats,
            $this->tracker->get_log_file_path()
        );
    }
}
```

**Checklist:**
- [ ] Chiamare `mark_completed()` quando finisce
- [ ] Recuperare session stats dal DB
- [ ] Chiamare `send_completion_email()`
- [ ] Testare con import completo

---

#### Task 1.7: Update Email Notifier Recipients

**File:** `includes/class-realestate-sync-email-notifier.php`

**Modificare linea 66:**

```php
// OLD:
$to = get_option('admin_email');

// NEW:
$primary_recipient = get_option('realestate_sync_email_recipient', 'importer@trentinoimmobiliare.it');
$additional_recipients = get_option('realestate_sync_email_recipients', array());

// Combina destinatari
$recipients = array_merge(array($primary_recipient), $additional_recipients);
$recipients = array_filter($recipients); // Rimuovi vuoti
$recipients = array_unique($recipients); // Rimuovi duplicati

$to = implode(',', $recipients);

error_log("[EMAIL-NOTIFIER] Sending to: " . $to);
```

**Checklist:**
- [ ] Modificare destinatario default
- [ ] Supportare lista multipla
- [ ] Validare email (opzionale)
- [ ] Testare invio a più destinatari

---

### Sprint 1 Testing

**Test Case 1: Session Creation**
```
1. Avvia import manuale
2. Verifica che record sia creato in wp_realestate_import_sessions
3. Verifica session_id corretto
4. Verifica agencies_queued e properties_queued
```

**Test Case 2: Stats Tracking**
```
1. Durante import, verifica incrementi stats
2. Controlla agencies_inserted/updated/skipped
3. Controlla properties_inserted/updated/skipped
4. Verifica batch_count incrementa
```

**Test Case 3: Email Delivery**
```
1. Completa import
2. Verifica email inviata a importer@trentinoimmobiliare.it
3. Verifica log allegato
4. Verifica stats corrette nell'email
```

---

## Sprint 2: Configuration

**Durata stimata:** 2-3 ore
**Priorità:** 🟡 MEDIA

### Task 2.1: Email Settings Tab

**File:** `admin/class-realestate-sync-admin.php`

**Aggiungere nuovo tab "Email Notifications":**

```php
// Rendering settings page
public function render_email_settings_page() {
    ?>
    <div class="wrap">
        <h1>Email Notification Settings</h1>

        <form method="post" action="options.php">
            <?php
            settings_fields('realestate_sync_email_settings');
            do_settings_sections('realestate-sync-email');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
public function register_email_settings() {
    register_setting('realestate_sync_email_settings', 'realestate_sync_email_enabled');
    register_setting('realestate_sync_email_settings', 'realestate_sync_email_recipient');
    register_setting('realestate_sync_email_settings', 'realestate_sync_email_recipients');
    register_setting('realestate_sync_email_settings', 'realestate_sync_email_on_success');
    register_setting('realestate_sync_email_settings', 'realestate_sync_email_on_error');

    add_settings_section(
        'realestate_sync_email_section',
        'Email Notification Configuration',
        array($this, 'email_section_callback'),
        'realestate-sync-email'
    );

    add_settings_field(
        'email_enabled',
        'Enable Email Notifications',
        array($this, 'render_checkbox_field'),
        'realestate-sync-email',
        'realestate_sync_email_section',
        array('option_name' => 'realestate_sync_email_enabled', 'default' => true)
    );

    add_settings_field(
        'email_recipient',
        'Primary Recipient',
        array($this, 'render_text_field'),
        'realestate-sync-email',
        'realestate_sync_email_section',
        array('option_name' => 'realestate_sync_email_recipient', 'default' => 'importer@trentinoimmobiliare.it', 'placeholder' => 'email@example.com')
    );

    add_settings_field(
        'email_recipients',
        'Additional Recipients',
        array($this, 'render_textarea_field'),
        'realestate-sync-email',
        'realestate_sync_email_section',
        array('option_name' => 'realestate_sync_email_recipients', 'description' => 'One email per line')
    );

    add_settings_field(
        'email_on_success',
        'Email on Success',
        array($this, 'render_checkbox_field'),
        'realestate-sync-email',
        'realestate_sync_email_section',
        array('option_name' => 'realestate_sync_email_on_success', 'default' => true)
    );

    add_settings_field(
        'email_on_error',
        'Email on Error',
        array($this, 'render_checkbox_field'),
        'realestate-sync-email',
        'realestate_sync_email_section',
        array('option_name' => 'realestate_sync_email_on_error', 'default' => true)
    );
}
```

**Checklist:**
- [ ] Creare tab Email Settings
- [ ] Form per primary recipient
- [ ] Textarea per additional recipients (uno per riga)
- [ ] Checkbox enable/disable notifications
- [ ] Checkbox email on success
- [ ] Checkbox email on error
- [ ] Validazione email input
- [ ] Save settings

---

### Task 2.2: Apply Settings in Email Notifier

**File:** `includes/class-realestate-sync-email-notifier.php`

**Modificare metodi send_completion_email e send_error_email:**

```php
public static function send_completion_email($session_id, $stats, $log_file_path = null) {
    // Check if email notifications enabled
    if (!get_option('realestate_sync_email_enabled', true)) {
        error_log("[EMAIL-NOTIFIER] Email notifications disabled");
        return;
    }

    // Check if success emails enabled
    if (!get_option('realestate_sync_email_on_success', true)) {
        error_log("[EMAIL-NOTIFIER] Success emails disabled");
        return;
    }

    // ... rest of method
}

public static function send_error_email($session_id, $stats, $errors, $log_file_path = null) {
    // Check if error emails enabled
    if (!get_option('realestate_sync_email_on_error', true)) {
        error_log("[EMAIL-NOTIFIER] Error emails disabled");
        return;
    }

    // ... rest of method
}
```

**Checklist:**
- [ ] Rispettare setting email_enabled
- [ ] Rispettare email_on_success
- [ ] Rispettare email_on_error
- [ ] Testare con settings vari

---

## Sprint 3: Monitoring

**Durata stimata:** 3-4 ore
**Priorità:** 🟡 MEDIA

### Task 3.1: Import History Tab

**File:** `admin/class-realestate-sync-admin.php`

**Nuovo tab "Import History":**

```php
public function render_import_history_page() {
    $session_manager = new RealEstate_Sync_Session_Manager();
    $sessions = $session_manager->get_recent_sessions(20);

    ?>
    <div class="wrap">
        <h1>Import History</h1>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Session ID</th>
                    <th>Start Time</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Agencies</th>
                    <th>Properties</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><code><?php echo esc_html($session['session_id']); ?></code></td>
                    <td><?php echo esc_html($session['start_time']); ?></td>
                    <td><?php echo $this->format_duration($session['total_duration']); ?></td>
                    <td><?php echo $this->render_status_badge($session['status']); ?></td>
                    <td>
                        <span class="dashicons dashicons-plus"></span> <?php echo $session['agencies_inserted']; ?>
                        <span class="dashicons dashicons-update"></span> <?php echo $session['agencies_updated']; ?>
                    </td>
                    <td>
                        <span class="dashicons dashicons-plus"></span> <?php echo $session['properties_inserted']; ?>
                        <span class="dashicons dashicons-update"></span> <?php echo $session['properties_updated']; ?>
                    </td>
                    <td>
                        <a href="?page=realestate-sync-session&id=<?php echo urlencode($session['session_id']); ?>" class="button">View Details</a>
                        <?php if ($session['log_file_path'] && file_exists($session['log_file_path'])): ?>
                        <a href="?page=realestate-sync-download-log&id=<?php echo urlencode($session['session_id']); ?>" class="button">Download Log</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
```

**Checklist:**
- [ ] Creare tab Import History
- [ ] Tabella con ultimi 20 import
- [ ] Mostrare stats chiave
- [ ] Link dettaglio sessione
- [ ] Link download log
- [ ] Styling con dashicons

---

### Task 3.2: Session Detail Page

**Mostra dettagli completi sessione:**

```php
public function render_session_detail_page() {
    $session_id = $_GET['id'] ?? null;
    if (!$session_id) {
        echo '<div class="error"><p>Invalid session ID</p></div>';
        return;
    }

    $session_manager = new RealEstate_Sync_Session_Manager();
    $session = $session_manager->get_session($session_id);

    if (!$session) {
        echo '<div class="error"><p>Session not found</p></div>';
        return;
    }

    ?>
    <div class="wrap">
        <h1>Import Session: <?php echo esc_html($session_id); ?></h1>

        <div class="session-detail-grid">
            <!-- Timing Info -->
            <div class="card">
                <h2>Timing</h2>
                <table class="form-table">
                    <tr>
                        <th>Start Time:</th>
                        <td><?php echo esc_html($session['start_time']); ?></td>
                    </tr>
                    <tr>
                        <th>End Time:</th>
                        <td><?php echo esc_html($session['end_time'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Duration:</th>
                        <td><?php echo $this->format_duration($session['total_duration']); ?></td>
                    </tr>
                    <tr>
                        <th>Batches:</th>
                        <td><?php echo $session['batch_count']; ?></td>
                    </tr>
                </table>
            </div>

            <!-- Agencies Stats -->
            <div class="card">
                <h2>Agencies</h2>
                <table class="form-table">
                    <tr>
                        <th>Queued:</th>
                        <td><?php echo $session['agencies_queued']; ?></td>
                    </tr>
                    <tr>
                        <th>Inserted:</th>
                        <td><?php echo $session['agencies_inserted']; ?></td>
                    </tr>
                    <tr>
                        <th>Updated:</th>
                        <td><?php echo $session['agencies_updated']; ?></td>
                    </tr>
                    <tr>
                        <th>Skipped:</th>
                        <td><?php echo $session['agencies_skipped']; ?></td>
                    </tr>
                    <tr>
                        <th>Failed:</th>
                        <td><?php echo $session['agencies_failed']; ?></td>
                    </tr>
                </table>
            </div>

            <!-- Properties Stats -->
            <div class="card">
                <h2>Properties</h2>
                <table class="form-table">
                    <tr>
                        <th>Queued:</th>
                        <td><?php echo $session['properties_queued']; ?></td>
                    </tr>
                    <tr>
                        <th>Inserted:</th>
                        <td><?php echo $session['properties_inserted']; ?></td>
                    </tr>
                    <tr>
                        <th>Updated:</th>
                        <td><?php echo $session['properties_updated']; ?></td>
                    </tr>
                    <tr>
                        <th>Skipped:</th>
                        <td><?php echo $session['properties_skipped']; ?></td>
                    </tr>
                    <tr>
                        <th>Failed:</th>
                        <td><?php echo $session['properties_failed']; ?></td>
                    </tr>
                </table>
            </div>

            <!-- Files & Logs -->
            <div class="card">
                <h2>Files & Logs</h2>
                <table class="form-table">
                    <tr>
                        <th>XML File:</th>
                        <td><code><?php echo esc_html(basename($session['xml_file_path'] ?? 'N/A')); ?></code></td>
                    </tr>
                    <tr>
                        <th>Log File:</th>
                        <td>
                            <?php if ($session['log_file_path'] && file_exists($session['log_file_path'])): ?>
                            <a href="?page=realestate-sync-download-log&id=<?php echo urlencode($session_id); ?>" class="button">Download Log</a>
                            <?php else: ?>
                            N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <?php
}
```

**Checklist:**
- [ ] Creare pagina dettaglio
- [ ] Mostrare tutte le statistiche
- [ ] Layout grid responsive
- [ ] Download log button
- [ ] Breadcrumb navigation

---

### Task 3.3: Log Download Handler

```php
public function handle_log_download() {
    $session_id = $_GET['id'] ?? null;
    if (!$session_id) {
        wp_die('Invalid session ID');
    }

    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $session_manager = new RealEstate_Sync_Session_Manager();
    $session = $session_manager->get_session($session_id);

    if (!$session || !$session['log_file_path']) {
        wp_die('Log file not found');
    }

    $log_file = $session['log_file_path'];
    if (!file_exists($log_file)) {
        wp_die('Log file does not exist');
    }

    // Force download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . basename($log_file) . '"');
    header('Content-Length: ' . filesize($log_file));
    readfile($log_file);
    exit;
}
```

**Checklist:**
- [ ] Handler per download log
- [ ] Security checks (capability)
- [ ] Verificare file exists
- [ ] Headers corretti per download
- [ ] Testare download

---

## Sprint 4: Polish

**Durata stimata:** 2 ore
**Priorità:** 🟢 BASSA

### Task 4.1: Email on Error

**File:** `includes/class-realestate-sync-batch-processor.php`

**Nel catch del process loop:**

```php
try {
    // ... process items ...
} catch (Exception $e) {
    error_log("[BATCH-PROCESSOR] ❌ Critical error: " . $e->getMessage());

    // Mark session as failed
    $this->session_manager->mark_failed($this->session_id, $e->getMessage());

    // Send error email
    $session_stats = $this->session_manager->get_session($this->session_id);
    if ($session_stats) {
        RealEstate_Sync_Email_Notifier::send_error_email(
            $this->session_id,
            $session_stats,
            array(array('time' => time(), 'message' => $e->getMessage())),
            $this->tracker->get_log_file_path()
        );
    }

    throw $e; // Re-throw
}
```

**Checklist:**
- [ ] Catch errori critici
- [ ] Mark session failed
- [ ] Send error email
- [ ] Include stack trace nel log
- [ ] Testare con errore forzato

---

### Task 4.2: Smart Log Attachment

**File:** `includes/class-realestate-sync-email-notifier.php`

**Gestire log file grandi:**

```php
// In send_completion_email, before wp_mail()
$attachments = [];
if ($log_file_path && file_exists($log_file_path)) {
    $log_size = filesize($log_file_path);
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($log_size > $max_size) {
        // Log troppo grande - aggiungi link invece di allegare
        $download_url = admin_url('admin.php?page=realestate-sync-download-log&id=' . urlencode($session_id));
        $email_body .= "\n\n";
        $email_body .= "┌──────────────────────────────────────────────────────────────────┐\n";
        $email_body .= "│ ⚠️  LOG FILE TOO LARGE FOR EMAIL ATTACHMENT                      │\n";
        $email_body .= "├──────────────────────────────────────────────────────────────────┤\n";
        $email_body .= "│ Download log: " . str_pad($download_url, 50) . "│\n";
        $email_body .= "└──────────────────────────────────────────────────────────────────┘\n";

        error_log("[EMAIL-NOTIFIER] Log file too large ({$log_size} bytes), sending link instead");
    } else {
        $attachments[] = $log_file_path;
    }
}
```

**Checklist:**
- [ ] Check dimensione log
- [ ] Se > 5MB, invia link invece di allegare
- [ ] Aggiungere link download in email body
- [ ] Testare con log grande

---

### Task 4.3: Email Templates Improvements

**Possibili miglioramenti:**

- [ ] Aggiungere deletion stats nell'email
- [ ] Grafico ASCII per progress
- [ ] Link diretti a WP Admin
- [ ] Codice colore per status
- [ ] Summary in alto (TL;DR)

---

## Technical Specifications

### Email Format

**Subject Lines:**
- Success: `✅ Import Completato - {N} items processati`
- Error: `⚠️ Import Fallito - {N}/{M} processati`

**Body Format:**
- Plain text con ASCII art
- Content-Type: `text/plain; charset=UTF-8`
- Attachment: Log file (se < 5MB)

**Recipients:**
- Primary: `importer@trentinoimmobiliare.it`
- Additional: Lista configurabile (comma-separated)
- BCC: Nessuno (privacy)

### Log File Strategy

**Opzione scelta:** **A) Allegare fisicamente alla mail**

**Rationale:**
- ✅ Più semplice da implementare
- ✅ Log disponibile immediatamente nell'email
- ✅ Non richiede accesso al sito per vedere il log
- ✅ Backup automatico via email

**Fallback:** Se log > 5MB, inviare link download

### WordPress Options

```php
// Email settings
realestate_sync_email_enabled          // bool, default: true
realestate_sync_email_recipient        // string, default: 'importer@trentinoimmobiliare.it'
realestate_sync_email_recipients       // array, default: []
realestate_sync_email_on_success       // bool, default: true
realestate_sync_email_on_error         // bool, default: true
```

---

## Testing Plan

### Unit Tests

```php
// Test Session Manager
- create_session() → verifica insert DB
- update_stats() → verifica update corretto
- increment_stat() → verifica incremento atomico
- mark_completed() → verifica duration calcolato
- get_session() → verifica retrieve dati

// Test Email Notifier
- send_completion_email() → mock wp_mail(), verifica chiamata
- build_success_email() → verifica formato ASCII
- Recipients handling → verifica multiple emails
```

### Integration Tests

```php
// End-to-End Import Flow
1. Start import → verifica session created
2. Process batch → verifica stats updated
3. Complete import → verifica email sent
4. Check DB → verifica dati corretti
5. Check email → verifica contenuto

// Error Handling
1. Force error durante import
2. Verifica session marked failed
3. Verifica error email sent
4. Check log attached
```

### Manual Testing Checklist

**Pre-deployment:**
- [ ] Testare import completo (successo)
- [ ] Verificare email ricevuta
- [ ] Verificare stats corrette
- [ ] Verificare log allegato leggibile
- [ ] Testare import con errore
- [ ] Verificare error email
- [ ] Testare dashboard history
- [ ] Testare download log
- [ ] Testare configurazione email settings
- [ ] Testare multiple recipients
- [ ] Verificare no email se disabled

---

## Deployment Strategy

### Development (Local)

```bash
# 1. Sviluppo locale
git checkout -b feature/email-notifications

# 2. Implementare Sprint 1
# 3. Testing locale
# 4. Commit incrementali

git add .
git commit -m "feat: add session tracking and email notifications"
```

### Staging (Server Test)

```bash
# 1. Merge in develop branch
git checkout develop
git merge feature/email-notifications

# 2. Push to server
git push origin develop

# 3. Deploy su server test
# 4. Testing con dati reali
# 5. Validazione email delivery
```

### Production

```bash
# 1. Merge in main
git checkout main
git merge develop

# 2. Tag version
git tag v1.8.0
git push origin main --tags

# 3. Deploy su produzione
# 4. Monitor prima importazione
# 5. Verificare email ricevute
```

### Rollback Plan

**Se qualcosa va storto:**

```sql
-- Rimuovere tabella sessions (se necessario)
DROP TABLE IF EXISTS wp_realestate_import_sessions;
```

```php
// Disabilitare email temporaneamente
update_option('realestate_sync_email_enabled', false);
```

```bash
# Revert git commit
git revert <commit-hash>
git push
```

---

## Performance Considerations

### Database Impact

**Writes per import:**
- 1 INSERT (create session)
- ~N UPDATE (increment stats) dove N = numero items
- 1 UPDATE (mark completed)

**Total:** ~782 queries per import medio (781 items)

**Optimization:**
- Usare prepared statements (già fatto)
- Batch updates dove possibile
- Index su session_id (già presente)

### Email Delivery

**Potenziali problemi:**
- SMTP timeout → Usare async queue (futuro)
- Large attachments → Implementato fallback con link
- Bounce rate → Monitorare via SMTP logs

**Mitigazioni:**
- Timeout wp_mail default: 30s (sufficiente)
- Log file max 5MB per allegato
- Validazione email addresses

---

## Future Enhancements

### v1.9.0 - Advanced Notifications

- [ ] Slack integration
- [ ] Webhook notifications
- [ ] SMS notifications (Twilio)
- [ ] Push notifications (browser)

### v2.0.0 - Analytics Dashboard

- [ ] Chart.js grafici trend
- [ ] Statistiche comparative (mese/anno)
- [ ] Export CSV storico
- [ ] Scheduled reports (settimanali)

### v2.1.0 - Email Templates

- [ ] HTML email templates
- [ ] Template customizer
- [ ] Brand colors configuration
- [ ] Logo upload

---

## Support & Troubleshooting

### Common Issues

**Email non arriva:**
1. Verificare `realestate_sync_email_enabled = true`
2. Controllare spam folder
3. Verificare SMTP configurato correttamente
4. Check WordPress debug.log per errori wp_mail()

**Stats non corrette:**
1. Verificare Session Manager caricato
2. Check increment_stat() chiamato nei posti giusti
3. Verificare no race conditions (transazioni DB)
4. Controllare log per errori INSERT/UPDATE

**Log non allegato:**
1. Verificare file esiste (get_log_file_path())
2. Check dimensione < 5MB
3. Verificare permessi lettura file
4. Controllare path assoluto corretto

### Debug Mode

**Abilitare debug dettagliato:**

```php
// In wp-config.php
define('REALESTATE_SYNC_DEBUG_EMAIL', true);

// In Email Notifier
if (defined('REALESTATE_SYNC_DEBUG_EMAIL') && REALESTATE_SYNC_DEBUG_EMAIL) {
    error_log("[EMAIL-DEBUG] Recipients: " . print_r($recipients, true));
    error_log("[EMAIL-DEBUG] Email body: " . $email_body);
    error_log("[EMAIL-DEBUG] Attachments: " . print_r($attachments, true));
}
```

---

## Changelog

### v1.8.0 (Planned)
- ✨ NEW: Email notification system
- ✨ NEW: Session tracking database table
- ✨ NEW: Import history dashboard
- ✨ NEW: Email settings configuration
- ✨ NEW: Log file download
- 🔧 FIX: Statistics tracking accurato
- 📧 Email on import completion
- 📧 Email on import error
- 📊 Dashboard visualization

---

## Contributors

- **Andrea Cianni** - Initial implementation
- **Claude (Anthropic)** - Architecture & documentation

---

## License

GPL v2 or later

---

**Document Version:** 1.0
**Last Updated:** 2025-12-09
**Status:** 🟡 Ready for Implementation
