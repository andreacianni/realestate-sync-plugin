# Email Report Handoff

## A) Overview
- End-of-batch email report runs in `batch-continuation.php` only when `$result['complete'] === true`.
- Flow: verifier runs, then report is built, snapshot saved, and email sent (real) using the report formatter.
- Report builder lives in `includes/class-realestate-sync-email-report.php`.
- Test email is sent via admin-post action and uses the same formatter, with a `[TEST]` subject prefix.
- Email sending is gated by `realestate_sync_email_enabled`.
- Anti-double-send guard uses `realestate_sync_email_sent_{session_id}` transient (2 days).
- Email settings are stored via AJAX in `admin/class-realestate-sync-admin.php` and read by the report sender.
- UI email widget is in `admin/views/widgets/config-email.php`.
- "Allega Report Dettagliato" is visible but disabled in the widget and ignored by send logic.
- No debug.log parsing; all data comes from options, transients, and DB tables.

## B) Punti di aggancio (file + ordine)
1) `batch-continuation.php` (branch `$result['complete'] === true`):
   - `RealEstate_Sync_Import_Verifier::verify_session($session_id)` (`includes/class-realestate-sync-import-verifier.php`)
   - `RealEstate_Sync_Email_Report::build_report($session_id, $progress)`
   - `RealEstate_Sync_Email_Report::save_snapshot($report)`
   - `RealEstate_Sync_Email_Report::send_email($report)`
2) Guard anti-double-send: `RealEstate_Sync_Email_Report::send_email()` checks transient `realestate_sync_email_sent_{session_id}`.

## C) Options/Settings (chiavi e significato)
- `realestate_sync_email_enabled` (bool): gate per invio reale.
- `realestate_sync_email_to` (string): destinatario principale.
- `realestate_sync_email_cc` (string): lista CC separata da virgola/;.
- `realestate_sync_email_attach_report` (bool): presente ma UI disabilitata e invio la ignora.

Salvataggio (AJAX):
- Handler: `RealEstate_Sync_Admin::handle_save_email_settings()` in `admin/class-realestate-sync-admin.php`.
- Sanitizzazione:
  - TO: `sanitize_email`, errore se input non vuoto ma non valido.
  - CC: split `/[;,]+/`, `is_email`, salva stringa normalizzata con virgola.
- Chiamato da: `admin/assets/admin.js` (`handleSaveEmailSettings`) su click `#save-email-config`.

Lettura per invio:
- `RealEstate_Sync_Email_Report::send_email()` e `send_test_email()` leggono le stesse option.

## D) Report structure (schema dati)
Generated report (array) in `RealEstate_Sync_Email_Report::build_report()`:
```
{
  session_id,
  start_time,
  end_time,
  queue_stats: { total, done, error, processing, pending },
  verification: {
    verified_total,
    total_issues,
    issues: {
      property_ids: [id, ...],
      properties: [{ property_id, title }]
    }
  },
  issues_delta: {
    resolved_ids: [...],
    persisting_ids: [...],
    new_ids: [...]
  },
  business_counts: {
    reliable,
    reason,
    properties_new,
    properties_updated,
    agencies_new,
    agencies_updated
  },
  email_subject,
  email_body
}
```

Snapshot options:
- `realestate_sync_email_snapshot` (latest)
- `realestate_sync_email_snapshot_prev` (previous)

Delta logic (sets):
- `resolved_ids = prev_ids - current_ids`
- `new_ids = current_ids - prev_ids`
- `persisting_ids = intersection(prev_ids, current_ids)`

Sources:
- `queue_stats`: DB table `wp_realestate_import_queue` (via `session_id`).
- `verification`: transient `realestate_sync_verification_{session_id}` or option `realestate_sync_latest_verification`.

## E) Verifier outputs (schema utile per allegato)
Stored by `RealEstate_Sync_Import_Verifier::save_verification_results()` in `includes/class-realestate-sync-import-verifier.php`:
- Transient: `realestate_sync_verification_{session_id}` (7 days).
- Option: `realestate_sync_latest_verification`.

Structure:
```
{
  session_id,
  timestamp,
  total_issues,
  properties: {
    property_id: {
      title,
      issues: [ { field, issue, expected?, actual?, missing? }, ... ]
    },
    ...
  }
}
```

To get issues for a session:
- Read transient `realestate_sync_verification_{session_id}`.
- If missing, fallback to `realestate_sync_latest_verification` only if `session_id` matches.

## F) Email sending (reale + test)
Reale:
- Method: `RealEstate_Sync_Email_Report::send_email($report)`.
- Guard: transient `realestate_sync_email_sent_{session_id}` (TTL 2 days). If present -> log and stop.
- Gate: `realestate_sync_email_enabled` false -> log "Email disabled (no send)...".
- Recipients: `realestate_sync_email_to` (fallback `admin_email`), CC parsed and validated.
- Headers: `Content-Type: text/plain; charset=UTF-8`.
- Call: `wp_mail($to, $subject, $body, $headers)`.

Test:
- Method: `RealEstate_Sync_Email_Report::send_test_email()`.
- No guard/transient (must not block real email).
- Subject prefixed `[TEST]`.
- Body uses formatter on existing snapshot or a mock report.
- Logs: `[EMAIL-REPORT] Sent TEST email to {to} (cc={N})`.
- Trigger: admin-post action `realestate_sync_send_test_email` in `realestate-sync.php`.

## G) Stato UI “Allega report”
- File: `admin/views/widgets/config-email.php`.
- Checkbox remains visible but has `disabled`.
- Helper text: "Temporaneamente disabilitato: logging in revisione".
- Option key not removed; saving unchanged (still read-only in UI).

## H) Cosa serve per riattivare allegato report
Checklist tecnico:
- Decide contenuto allegato:
  - full session log file (se esiste un log per sessione), or
  - verifier issues export (CSV/JSON), or
  - report snapshot text (plain text).
- Generazione file:
  - Prefer `wp_upload_dir()` or a temp file under uploads (not transient).
  - Ensure cleanup (delete after send or scheduled cleanup).
- Point of generation:
  - In `batch-continuation.php` right after report build, before email send.
  - Must be non-blocking (fail-safe: if attach fails, still send email).
- Use `wp_mail` attachments param (array of file paths).
- Encoding: UTF-8; ensure file name ASCII-safe.
- Retention: delete after send or keep for N days (if keep, add cleanup).
- Privacy: avoid sensitive data; consider masking if needed.

## Open Questions
- Is there a reliable per-session log file to attach (path + retention)? Current email flow does not reference any log path.
- Should attachments include verifier issue details only or full batch log?
- Max acceptable attachment size (FTP/SMTP limits) not defined.
