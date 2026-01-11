<?php
/**
 * Email Report Builder (Phase 1 - no send)
 *
 * Builds a structured end-of-batch report using existing data sources.
 *
 * @package RealEstate_Sync
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Email_Report {
    /**
     * Build structured report for a completed session.
     *
     * @param string $session_id Session ID.
     * @param array  $progress   Progress option data.
     * @return array Report data.
     */
    public static function build_report($session_id, $progress) {
        $queue_stats = self::get_queue_stats($session_id);
        $verification = self::get_verification_results($session_id);
        $verification_total_issues = (int) ($verification['total_issues'] ?? 0);

        $verified_total = (int) ($queue_stats['done'] ?? 0);
        if ($verified_total <= 0) {
            $verified_total = (int) ($verification['verified_total'] ?? 0);
        }

        $issues_properties = $verification['issues']['properties'] ?? array();
        $issue_ids = array_keys($issues_properties);

        $prev_snapshot = get_option('realestate_sync_email_snapshot');
        $prev_issue_ids = $prev_snapshot['verification']['issues']['property_ids'] ?? array();
        $issues_delta = self::build_issues_delta($prev_issue_ids, $issue_ids);

        $business_counts = self::get_business_counts($progress);

        $report = array(
            'session_id' => $session_id,
            'start_time' => $progress['start_time'] ?? null,
            'end_time' => $progress['end_time'] ?? null,
            'queue_stats' => $queue_stats,
            'verification' => array(
                'verified_total' => $verified_total,
                'total_issues' => $verification_total_issues,
                'issues' => array(
                    'property_ids' => array_values($issue_ids),
                    'properties' => self::build_issues_with_titles($issues_properties)
                )
            ),
            'issues_delta' => $issues_delta,
            'business_counts' => $business_counts
        );

        $report['email_subject'] = self::format_email_subject($report);
        $report['email_body'] = self::format_email_body($report);

        if (!$business_counts['reliable']) {
            error_log('[EMAIL-REPORT] business counts not reliable: ' . $business_counts['reason']);
        }

        return $report;
    }

    /**
     * Save snapshot options and log report summary.
     *
     * @param array $report Report data.
     * @return void
     */
    public static function save_snapshot($report) {
        $prev_snapshot = get_option('realestate_sync_email_snapshot');
        if (!empty($prev_snapshot)) {
            update_option('realestate_sync_email_snapshot_prev', $prev_snapshot, false);
        }

        $snapshot = self::build_snapshot($report);
        update_option('realestate_sync_email_snapshot', $snapshot, false);

        $processed = self::get_processed_total($report['queue_stats'] ?? array());
        $ok = max(0, (int) ($report['verification']['verified_total'] ?? 0) - (int) ($report['verification']['total_issues'] ?? 0));
        $issues = (int) ($report['verification']['total_issues'] ?? 0);
        $resolved = count($report['issues_delta']['resolved_ids'] ?? array());
        $new = count($report['issues_delta']['new_ids'] ?? array());
        $persisting = count($report['issues_delta']['persisting_ids'] ?? array());

        error_log('[EMAIL-REPORT] Built report for session ' . $report['session_id'] . ': processed=' . $processed . ' ok=' . $ok . ' issues=' . $issues . ' resolved=' . $resolved . ' new=' . $new . ' persisting=' . $persisting);
    }

    /**
     * Send email for a completed session report (no attachments).
     *
     * @param array $report Report data.
     * @return void
     */
    public static function send_email($report) {
        $session_id = $report['session_id'] ?? '';
        if (empty($session_id)) {
            return;
        }

        $sent_key = 'realestate_sync_email_sent_' . $session_id;
        if (get_transient($sent_key)) {
            error_log('[EMAIL-REPORT] Email already sent for session ' . $session_id);
            return;
        }

        $enabled = get_option('realestate_sync_email_enabled', false);
        if (!$enabled) {
            error_log('[EMAIL-REPORT] Email disabled (no send) for session ' . $session_id);
            return;
        }

        $admin_email = get_option('admin_email');
        $to = get_option('realestate_sync_email_to', $admin_email);
        if (empty($to) || !is_email($to)) {
            $to = $admin_email;
        }

        if (empty($to) || !is_email($to)) {
            error_log('[EMAIL-REPORT] Email send failed for session ' . $session_id);
            return;
        }

        $cc_raw = get_option('realestate_sync_email_cc', '');
        $cc_list = self::parse_cc_list($cc_raw);

        $subject = self::format_email_subject($report);
        $body = self::format_email_body($report);

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8'
        );

        if (!empty($cc_list)) {
            $headers[] = 'Cc: ' . implode(', ', $cc_list);
        }

        $sent = wp_mail($to, $subject, $body, $headers);
        if ($sent) {
            set_transient($sent_key, 1, 2 * DAY_IN_SECONDS);
            error_log('[EMAIL-REPORT] Sent email to ' . $to . ' (cc=' . count($cc_list) . ') for session ' . $session_id);
        } else {
            error_log('[EMAIL-REPORT] Email send failed for session ' . $session_id);
        }
    }

    /**
     * Send a test email using the report formatter.
     *
     * @return bool True if sent successfully.
     */
    public static function send_test_email() {
        $report = self::get_report_for_test();

        $admin_email = get_option('admin_email');
        $to = get_option('realestate_sync_email_to', $admin_email);
        if (empty($to) || !is_email($to)) {
            $to = $admin_email;
        }

        if (empty($to) || !is_email($to)) {
            return false;
        }

        $cc_raw = get_option('realestate_sync_email_cc', '');
        $cc_list = self::parse_cc_list($cc_raw);

        $subject = '[TEST] ' . self::format_email_subject($report);
        $body = self::format_email_body($report);

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8'
        );

        if (!empty($cc_list)) {
            $headers[] = 'Cc: ' . implode(', ', $cc_list);
        }

        $sent = wp_mail($to, $subject, $body, $headers);
        if ($sent) {
            error_log('[EMAIL-REPORT] Sent TEST email to ' . $to . ' (cc=' . count($cc_list) . ')');
        }

        return (bool) $sent;
    }

    /**
     * Format subject line for the report.
     *
     * @param array $report Report data.
     * @return string Subject line.
     */
    public static function format_email_subject($report) {
        $processed = self::get_processed_total($report['queue_stats'] ?? array());
        $ok = max(0, (int) ($report['verification']['verified_total'] ?? 0) - (int) ($report['verification']['total_issues'] ?? 0));
        return 'Import completato ' . $report['session_id'] . ' - ' . $processed . ' proprieta, ' . $ok . ' ok';
    }

    /**
     * Format body text for the report.
     *
     * @param array $report Report data.
     * @return string Body text.
     */
    public static function format_email_body($report) {
        $lines = array();
        $processed = self::get_processed_total($report['queue_stats'] ?? array());
        $ok = max(0, (int) ($report['verification']['verified_total'] ?? 0) - (int) ($report['verification']['total_issues'] ?? 0));

        $lines[] = 'Processo completato, ' . $processed . ' proprieta processate, ' . $ok . ' ok al 100%';

        if (!empty($report['session_id'])) {
            $lines[] = 'Sessione: ' . $report['session_id'];
        }

        $queue = $report['queue_stats'] ?? array();
        if (!empty($queue)) {
            $lines[] = 'Queue: total=' . ($queue['total'] ?? 0) . ', done=' . ($queue['done'] ?? 0) . ', error=' . ($queue['error'] ?? 0) . ', processing=' . ($queue['processing'] ?? 0);
        }

        $issues = $report['verification']['issues']['properties'] ?? array();
        if (!empty($issues)) {
            $lines[] = 'Le seguenti proprieta hanno avuto un problema e saranno sistemate domani:';
            foreach ($issues as $property_id => $data) {
                $title = $data['title'] ?? '';
                $line = '- ' . $property_id;
                if (!empty($title)) {
                    $line .= ' - ' . $title;
                }
                $lines[] = $line;
            }
        }

        $resolved = $report['issues_delta']['resolved_ids'] ?? array();
        if (!empty($resolved)) {
            $lines[] = 'Oggi risultano OK anche: ' . implode(', ', $resolved);
        }

        return implode("\n", $lines);
    }

    private static function get_queue_stats($session_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'realestate_import_queue';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count
                 FROM {$table}
                 WHERE session_id = %s
                 GROUP BY status",
                $session_id
            ),
            ARRAY_A
        );

        $stats = array(
            'pending' => 0,
            'processing' => 0,
            'done' => 0,
            'error' => 0,
            'total' => 0
        );

        foreach ($rows as $row) {
            $status = $row['status'];
            $count = (int) $row['count'];
            if (isset($stats[$status])) {
                $stats[$status] = $count;
            }
            $stats['total'] += $count;
        }

        return $stats;
    }

    private static function get_verification_results($session_id) {
        $transient_key = 'realestate_sync_verification_' . $session_id;
        $results = get_transient($transient_key);

        if (empty($results)) {
            $latest = get_option('realestate_sync_latest_verification');
            if (!empty($latest) && ($latest['session_id'] ?? '') === $session_id) {
                $results = $latest;
            }
        }

        if (empty($results)) {
            return array(
                'total_issues' => 0,
                'issues' => array(
                    'properties' => array()
                )
            );
        }

        $properties = $results['properties'] ?? array();
        $issues = array();
        foreach ($properties as $property_id => $data) {
            $issues[$property_id] = array(
                'title' => $data['title'] ?? '',
                'issues' => $data['issues'] ?? array()
            );
        }

        return array(
            'total_issues' => $results['total_issues'] ?? count($properties),
            'issues' => array(
                'properties' => $issues
            )
        );
    }

    private static function build_issues_delta($prev_ids, $current_ids) {
        $prev_ids = array_values(array_unique(array_map('strval', (array) $prev_ids)));
        $current_ids = array_values(array_unique(array_map('strval', (array) $current_ids)));

        $resolved = array_values(array_diff($prev_ids, $current_ids));
        $new = array_values(array_diff($current_ids, $prev_ids));
        $persisting = array_values(array_intersect($prev_ids, $current_ids));

        return array(
            'resolved_ids' => $resolved,
            'persisting_ids' => $persisting,
            'new_ids' => $new
        );
    }

    private static function build_issues_with_titles($issues_properties) {
        $list = array();
        foreach ($issues_properties as $property_id => $data) {
            $list[] = array(
                'property_id' => $property_id,
                'title' => $data['title'] ?? ''
            );
        }
        return $list;
    }

    private static function parse_cc_list($cc_raw) {
        if (empty($cc_raw)) {
            return array();
        }

        $parts = preg_split('/[;,]+/', $cc_raw);
        $cc_list = array();

        foreach ($parts as $part) {
            $email = trim($part);
            if ($email && is_email($email)) {
                $cc_list[] = $email;
            }
        }

        return array_values(array_unique($cc_list));
    }

    private static function build_snapshot($report) {
        return array(
            'session_id' => $report['session_id'],
            'start_time' => $report['start_time'],
            'end_time' => $report['end_time'],
            'queue_stats' => $report['queue_stats'],
            'verification' => array(
                'total_issues' => $report['verification']['total_issues'] ?? 0,
                'issues' => array(
                    'property_ids' => $report['verification']['issues']['property_ids'] ?? array()
                )
            )
        );
    }

    private static function get_processed_total($queue_stats) {
        if (!empty($queue_stats['total'])) {
            return (int) $queue_stats['total'];
        }

        $sum = 0;
        foreach (array('done', 'error', 'processing') as $key) {
            $sum += (int) ($queue_stats[$key] ?? 0);
        }
        return $sum;
    }

    private static function get_report_for_test() {
        $snapshot = get_option('realestate_sync_email_snapshot');
        if (empty($snapshot) || !is_array($snapshot)) {
            return self::build_mock_report();
        }

        $queue_stats = $snapshot['queue_stats'] ?? array();
        $issue_ids = $snapshot['verification']['issues']['property_ids'] ?? array();
        $issues_properties = array();

        foreach ($issue_ids as $property_id) {
            $issues_properties[$property_id] = array(
                'title' => '',
                'issues' => array()
            );
        }

        return array(
            'session_id' => $snapshot['session_id'] ?? 'test',
            'start_time' => $snapshot['start_time'] ?? null,
            'end_time' => $snapshot['end_time'] ?? null,
            'queue_stats' => $queue_stats,
            'verification' => array(
                'verified_total' => (int) ($queue_stats['done'] ?? 0),
                'total_issues' => (int) ($snapshot['verification']['total_issues'] ?? count($issue_ids)),
                'issues' => array(
                    'property_ids' => array_values($issue_ids),
                    'properties' => $issues_properties
                )
            ),
            'issues_delta' => array(
                'resolved_ids' => array(),
                'persisting_ids' => array(),
                'new_ids' => array()
            ),
            'business_counts' => array(
                'reliable' => false,
                'reason' => 'test email',
                'properties_new' => null,
                'properties_updated' => null,
                'agencies_new' => null,
                'agencies_updated' => null
            )
        );
    }

    private static function build_mock_report() {
        return array(
            'session_id' => 'test',
            'start_time' => null,
            'end_time' => null,
            'queue_stats' => array(
                'total' => 0,
                'done' => 0,
                'error' => 0,
                'processing' => 0
            ),
            'verification' => array(
                'verified_total' => 0,
                'total_issues' => 0,
                'issues' => array(
                    'property_ids' => array(),
                    'properties' => array()
                )
            ),
            'issues_delta' => array(
                'resolved_ids' => array(),
                'persisting_ids' => array(),
                'new_ids' => array()
            ),
            'business_counts' => array(
                'reliable' => false,
                'reason' => 'test email',
                'properties_new' => null,
                'properties_updated' => null,
                'agencies_new' => null,
                'agencies_updated' => null
            )
        );
    }

    private static function get_business_counts($progress) {
        global $wpdb;

        if (empty($progress['start_time']) || empty($progress['end_time'])) {
            return array(
                'reliable' => false,
                'reason' => 'missing start_time or end_time in progress option',
                'properties_new' => null,
                'properties_updated' => null,
                'agencies_new' => null,
                'agencies_updated' => null
            );
        }

        $start = self::format_mysql_datetime($progress['start_time']);
        $end = self::format_mysql_datetime($progress['end_time']);

        $property_table = $wpdb->prefix . 'realestate_sync_tracking';
        $agency_table = $wpdb->prefix . 'realestate_sync_agency_tracking';

        if (!self::table_exists($property_table) || !self::table_exists($agency_table)) {
            return array(
                'reliable' => false,
                'reason' => 'tracking tables not found',
                'properties_new' => null,
                'properties_updated' => null,
                'agencies_new' => null,
                'agencies_updated' => null
            );
        }

        $properties_new = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$property_table}
                 WHERE created_date BETWEEN %s AND %s",
                $start,
                $end
            )
        );

        $properties_updated = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$property_table}
                 WHERE last_import_date BETWEEN %s AND %s
                 AND created_date < %s",
                $start,
                $end,
                $start
            )
        );

        $agencies_new = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$agency_table}
                 WHERE created_date BETWEEN %s AND %s",
                $start,
                $end
            )
        );

        $agencies_updated = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$agency_table}
                 WHERE last_import_date BETWEEN %s AND %s
                 AND created_date < %s",
                $start,
                $end,
                $start
            )
        );

        if ($wpdb->last_error) {
            return array(
                'reliable' => false,
                'reason' => 'db error while reading tracking tables: ' . $wpdb->last_error,
                'properties_new' => null,
                'properties_updated' => null,
                'agencies_new' => null,
                'agencies_updated' => null
            );
        }

        return array(
            'reliable' => true,
            'reason' => '',
            'properties_new' => $properties_new,
            'properties_updated' => $properties_updated,
            'agencies_new' => $agencies_new,
            'agencies_updated' => $agencies_updated
        );
    }

    private static function format_mysql_datetime($timestamp) {
        return date_i18n('Y-m-d H:i:s', (int) $timestamp);
    }

    private static function table_exists($table_name) {
        global $wpdb;
        $table = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        return $table === $table_name;
    }
}
