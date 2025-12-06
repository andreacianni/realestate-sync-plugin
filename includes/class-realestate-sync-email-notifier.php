<?php
/**
 * Email Notifier - ASCII Art Edition
 *
 * Sends beautiful plain-text emails with import results
 *
 * @package RealEstate_Sync
 * @version 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Email_Notifier {

    /**
     * Send completion email
     *
     * @param string $session_id Import session ID
     * @param array $stats Import statistics
     * @param string $log_file_path Path to log file (optional attachment)
     */
    public static function send_completion_email($session_id, $stats, $log_file_path = null) {

        $start_time = $stats['start_time'] ?? time();
        $end_time = $stats['end_time'] ?? time();
        $duration = $end_time - $start_time;

        // Calculate stats
        $agencies_inserted = $stats['agencies_inserted'] ?? 0;
        $agencies_updated = $stats['agencies_updated'] ?? 0;
        $agencies_skipped = $stats['agencies_skipped'] ?? 0;
        $agencies_total = $agencies_inserted + $agencies_updated + $agencies_skipped;

        $properties_inserted = $stats['properties_inserted'] ?? 0;
        $properties_updated = $stats['properties_updated'] ?? 0;
        $properties_skipped = $stats['properties_skipped'] ?? 0;
        $properties_total = $properties_inserted + $properties_updated + $properties_skipped;

        $total_items = $agencies_total + $properties_total;
        $batch_count = $stats['batch_count'] ?? ceil($total_items / 5);

        // Build ASCII art email
        $email_body = self::build_success_email(
            $session_id,
            $start_time,
            $end_time,
            $duration,
            $batch_count,
            [
                'inserted' => $agencies_inserted,
                'updated' => $agencies_updated,
                'skipped' => $agencies_skipped,
                'total' => $agencies_total
            ],
            [
                'inserted' => $properties_inserted,
                'updated' => $properties_updated,
                'skipped' => $properties_skipped,
                'total' => $properties_total
            ]
        );

        // Send email
        $to = get_option('admin_email');
        $subject = '✅ Import Completato - ' . $total_items . ' items processati';
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        $attachments = [];
        if ($log_file_path && file_exists($log_file_path)) {
            $attachments[] = $log_file_path;
        }

        wp_mail($to, $subject, $email_body, $headers, $attachments);

        error_log("[EMAIL-NOTIFIER] Completion email sent to {$to}");
    }

    /**
     * Send error email
     *
     * @param string $session_id Import session ID
     * @param array $stats Current statistics
     * @param array $errors Array of error messages
     * @param string $log_file_path Path to log file
     */
    public static function send_error_email($session_id, $stats, $errors, $log_file_path = null) {

        $start_time = $stats['start_time'] ?? time();
        $error_time = time();
        $duration = $error_time - $start_time;

        $processed = ($stats['processed_items'] ?? 0);
        $total = ($stats['total_items'] ?? 0);
        $remaining = $total - $processed;
        $batch_count = $stats['batch_count'] ?? 0;

        // Build ASCII art error email
        $email_body = self::build_error_email(
            $session_id,
            $start_time,
            $error_time,
            $duration,
            $processed,
            $total,
            $remaining,
            $batch_count,
            $errors
        );

        // Send email
        $to = get_option('admin_email');
        $subject = '⚠️ Import Fallito - ' . $processed . '/' . $total . ' processati';
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        $attachments = [];
        if ($log_file_path && file_exists($log_file_path)) {
            $attachments[] = $log_file_path;
        }

        wp_mail($to, $subject, $email_body, $headers, $attachments);

        error_log("[EMAIL-NOTIFIER] Error email sent to {$to}");
    }

    /**
     * Build success email body
     */
    private static function build_success_email($session_id, $start_time, $end_time, $duration, $batch_count, $agencies, $properties) {

        $start_str = gmdate('d/m/Y H:i:s', $start_time) . ' UTC';
        $end_str = gmdate('d/m/Y H:i:s', $end_time) . ' UTC';
        $duration_str = self::format_duration($duration);

        $total_items = $agencies['total'] + $properties['total'];
        $avg_speed = $duration > 0 ? round($total_items / ($duration / 60), 1) : 0;
        $avg_batch_time = $batch_count > 0 ? round($duration / $batch_count, 1) : 0;

        $email = "";
        $email .= "╔══════════════════════════════════════════════════════════════════╗\n";
        $email .= "║                                                                  ║\n";
        $email .= "║        🏠  REALESTATE SYNC - IMPORT COMPLETATO  🏠               ║\n";
        $email .= "║                                                                  ║\n";
        $email .= "╚══════════════════════════════════════════════════════════════════╝\n\n";

        $email .= "Session: {$session_id}\n";
        $email .= "Status:  ✓ COMPLETATO\n\n";

        $email .= "┌──────────────────────────────────────────────────────────────────┐\n";
        $email .= "│ ⏱️  TEMPI                                                         │\n";
        $email .= "├──────────────────────────────────────────────────────────────────┤\n";
        $email .= "│ Inizio:     " . str_pad($start_str, 50) . "│\n";
        $email .= "│ Fine:       " . str_pad($end_str, 50) . "│\n";
        $email .= "│ Durata:     " . str_pad($duration_str, 50) . "│\n";
        $email .= "│ Batch:      " . str_pad("{$batch_count} batch processati (5 items/batch)", 50) . "│\n";
        $email .= "└──────────────────────────────────────────────────────────────────┘\n\n";

        if ($agencies['total'] > 0) {
            $email .= "┌──────────────────────────────────────────────────────────────────┐\n";
            $email .= "│ 📊 AGENZIE                                                        │\n";
            $email .= "├──────────────────────────────────────────────────────────────────┤\n";
            $email .= "│ Nuove:      " . self::build_progress_bar($agencies['inserted'], $agencies['total'], 24) . "  " . str_pad($agencies['inserted'], 3, ' ', STR_PAD_LEFT) . "  (" . self::percentage($agencies['inserted'], $agencies['total']) . "%)               │\n";
            $email .= "│ Aggiornate: " . self::build_progress_bar($agencies['updated'], $agencies['total'], 24) . "  " . str_pad($agencies['updated'], 3, ' ', STR_PAD_LEFT) . "  (" . self::percentage($agencies['updated'], $agencies['total']) . "%)               │\n";
            $email .= "│ Skippate:   " . self::build_progress_bar($agencies['skipped'], $agencies['total'], 24) . "  " . str_pad($agencies['skipped'], 3, ' ', STR_PAD_LEFT) . "  (" . self::percentage($agencies['skipped'], $agencies['total']) . "%)               │\n";
            $email .= "│             ─────────────────────────                            │\n";
            $email .= "│ TOTALE:                                " . str_pad($agencies['total'], 2, ' ', STR_PAD_LEFT) . "                        │\n";
            $email .= "└──────────────────────────────────────────────────────────────────┘\n\n";
        }

        if ($properties['total'] > 0) {
            $email .= "┌──────────────────────────────────────────────────────────────────┐\n";
            $email .= "│ 🏘️  PROPRIETÀ                                                     │\n";
            $email .= "├──────────────────────────────────────────────────────────────────┤\n";
            $email .= "│ Nuove:      " . self::build_progress_bar($properties['inserted'], $properties['total'], 24) . "  " . str_pad($properties['inserted'], 4, ' ', STR_PAD_LEFT) . " (" . self::percentage($properties['inserted'], $properties['total']) . "%)               │\n";
            $email .= "│ Aggiornate: " . self::build_progress_bar($properties['updated'], $properties['total'], 24) . "  " . str_pad($properties['updated'], 4, ' ', STR_PAD_LEFT) . " (" . self::percentage($properties['updated'], $properties['total']) . "%)               │\n";
            $email .= "│ Skippate:   " . self::build_progress_bar($properties['skipped'], $properties['total'], 24) . "  " . str_pad($properties['skipped'], 4, ' ', STR_PAD_LEFT) . " (" . self::percentage($properties['skipped'], $properties['total']) . "%)               │\n";
            $email .= "│             ─────────────────────────                            │\n";
            $email .= "│ TOTALE:                               " . str_pad($properties['total'], 4, ' ', STR_PAD_LEFT) . "                        │\n";
            $email .= "└──────────────────────────────────────────────────────────────────┘\n\n";
        }

        $email .= "┌──────────────────────────────────────────────────────────────────┐\n";
        $email .= "│ ⚡ PERFORMANCE                                                    │\n";
        $email .= "├──────────────────────────────────────────────────────────────────┤\n";
        $email .= "│ Velocità media:    {$avg_speed} items/minuto                             │\n";
        $email .= "│ Tempo/batch:       {$avg_batch_time} secondi                                   │\n";
        $email .= "└──────────────────────────────────────────────────────────────────┘\n\n";

        $email .= "┌──────────────────────────────────────────────────────────────────┐\n";
        $email .= "│ 🔗 LINKS                                                          │\n";
        $email .= "├──────────────────────────────────────────────────────────────────┤\n";
        $email .= "│ Proprietà:  " . str_pad(get_site_url() . '/wp-admin/edit.php?post_type=estate_property', 51) . "│\n";
        $email .= "│ Agenzie:    " . str_pad(get_site_url() . '/wp-admin/edit.php?post_type=estate_agency', 51) . "│\n";
        $email .= "└──────────────────────────────────────────────────────────────────┘\n\n";

        $email .= "╔══════════════════════════════════════════════════════════════════╗\n";
        $email .= "║  Generato da RealEstate Sync Plugin v1.6                         ║\n";
        $email .= "╚══════════════════════════════════════════════════════════════════╝\n";

        return $email;
    }

    /**
     * Build error email body
     */
    private static function build_error_email($session_id, $start_time, $error_time, $duration, $processed, $total, $remaining, $batch_count, $errors) {

        $start_str = gmdate('d/m/Y H:i:s', $start_time) . ' UTC';
        $error_str = gmdate('d/m/Y H:i:s', $error_time) . ' UTC';
        $duration_str = self::format_duration($duration);
        $progress_pct = $total > 0 ? round(($processed / $total) * 100) : 0;

        $email = "";
        $email .= "╔══════════════════════════════════════════════════════════════════╗\n";
        $email .= "║                                                                  ║\n";
        $email .= "║         ⚠️  REALESTATE SYNC - IMPORT FALLITO  ⚠️                 ║\n";
        $email .= "║                                                                  ║\n";
        $email .= "╚══════════════════════════════════════════════════════════════════╝\n\n";

        $email .= "Session: {$session_id}\n";
        $email .= "Status:  ✗ ERRORE\n\n";

        $email .= "┌──────────────────────────────────────────────────────────────────┐\n";
        $email .= "│ 💥 ERRORI RILEVATI                                                │\n";
        $email .= "├──────────────────────────────────────────────────────────────────┤\n";

        foreach ($errors as $error) {
            $time = gmdate('H:i:s', $error['time'] ?? time());
            $msg = substr($error['message'] ?? 'Unknown error', 0, 50);
            $email .= "│ • [{$time}] " . str_pad($msg, 52) . "│\n";
        }

        $email .= "└──────────────────────────────────────────────────────────────────┘\n\n";

        $email .= "┌──────────────────────────────────────────────────────────────────┐\n";
        $email .= "│ 📊 PROGRESSO AL MOMENTO DELL'ERRORE                              │\n";
        $email .= "├──────────────────────────────────────────────────────────────────┤\n";
        $email .= "│ Processati:   {$processed} / {$total}  ({$progress_pct}%)                                  │\n";
        $email .= "│ Rimasti:      {$remaining}                                                │\n";
        $email .= "│ Batch:        {$batch_count} batch completati                                │\n";
        $email .= "│ Durata:       {$duration_str} prima dell'errore                           │\n";
        $email .= "└──────────────────────────────────────────────────────────────────┘\n\n";

        $email .= "┌──────────────────────────────────────────────────────────────────┐\n";
        $email .= "│ 🔧 AZIONI SUGGERITE                                               │\n";
        $email .= "├──────────────────────────────────────────────────────────────────┤\n";
        $email .= "│ 1. Controlla log allegato per dettagli                          │\n";
        $email .= "│ 2. Verifica connessione database                                │\n";
        $email .= "│ 3. Import riprenderà automaticamente se possibile                │\n";
        $email .= "└──────────────────────────────────────────────────────────────────┘\n\n";

        $email .= "╔══════════════════════════════════════════════════════════════════╗\n";
        $email .= "║  Generato da RealEstate Sync Plugin v1.6                         ║\n";
        $email .= "╚══════════════════════════════════════════════════════════════════╝\n";

        return $email;
    }

    /**
     * Build ASCII progress bar
     */
    private static function build_progress_bar($value, $total, $width = 20) {
        if ($total == 0) return str_repeat('░', $width);

        $filled = round(($value / $total) * $width);
        $empty = $width - $filled;

        return str_repeat('█', $filled) . str_repeat('░', $empty);
    }

    /**
     * Calculate percentage
     */
    private static function percentage($value, $total) {
        if ($total == 0) return 0;
        return round(($value / $total) * 100);
    }

    /**
     * Format duration
     */
    private static function format_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }
}
