<?php
/**
 * Batch Continuation Endpoint
 *
 * Called by server cron every minute to process pending batches.
 * Security: Requires secret token to prevent unauthorized access.
 *
 * ✅ NEW ROBUST APPROACH: Checks queue directly instead of transients
 * No more cache fragility - queue is the source of truth!
 *
 * Server Cron Command:
 * * * * * * wget -q -O - "https://trentinoimmobiliare.it/wp-content/plugins/realestate-sync-plugin/batch-continuation.php?token=YOUR_SECRET_TOKEN" >/dev/null 2>&1
 *
 * Token Configuration (priority order):
 * 1. wp-config.php constant: define('REALESTATE_SYNC_CRON_TOKEN', 'your-secret-token');
 * 2. Environment variable: REALESTATE_SYNC_CRON_TOKEN
 * 3. WordPress option: realestate_sync_cron_token (set in plugin settings)
 *
 * @package RealEstate_Sync
 * @version 1.6.1
 * @since 1.5.0
 */

// Security check - require secret token
// Priority: 1. PHP constant, 2. Environment variable, 3. WordPress option (loaded later)
$valid_token = null;

// Option 1: Check for PHP constant (defined in wp-config.php)
if (defined('REALESTATE_SYNC_CRON_TOKEN')) {
    $valid_token = REALESTATE_SYNC_CRON_TOKEN;
}
// Option 2: Check for environment variable
elseif (getenv('REALESTATE_SYNC_CRON_TOKEN')) {
    $valid_token = getenv('REALESTATE_SYNC_CRON_TOKEN');
}
// Option 3: Fallback to default (TEMPORARY - should configure one of the above)
else {
    $valid_token = 'TrentinoImmo2025Secret!';
    error_log('[BATCH-CONTINUATION] ⚠️ WARNING: Using default token. Please configure REALESTATE_SYNC_CRON_TOKEN in wp-config.php or environment.');
}

// Verify token
if (!isset($_GET['token']) || $_GET['token'] !== $valid_token) {
    error_log('[BATCH-CONTINUATION] ❌ Unauthorized access attempt from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    http_response_code(403);
    die('Forbidden');
}

// Load WordPress
define('WP_USE_THEMES', false);
require_once(dirname(__FILE__) . '/../../../wp-load.php');
require_once(dirname(__FILE__) . '/includes/class-realestate-sync-delete-queue-manager.php');
require_once(dirname(__FILE__) . '/includes/class-realestate-sync-delete-batch-processor.php');

$update_delete_phase_progress = static function (array $progress, array $delete_queue_stats, $status, $worker_enabled = false) {
    $delete_runtime = isset($progress['delete_runtime']) && is_array($progress['delete_runtime']) ? $progress['delete_runtime'] : array();
    $mode = $delete_runtime['mode'] ?? 'dry_run';
    $session_completed = ($progress['status'] ?? '') === 'completed';

    if (!($mode === 'dry_run' && $session_completed)) {
        $progress['status'] = $status;
    }

    $progress['delete_state'] = RealEstate_Sync_Batch_Orchestrator::build_delete_state_payload(
        $delete_queue_stats,
        $status,
        $worker_enabled
    );
    $progress['last_batch_time'] = time();

    return $progress;
};

$run_media_cleanup_tick_if_ready = static function () {
    if (function_exists('run_media_cleanup_tick')) {
        run_media_cleanup_tick();
    }
};

// ========================================================================
// ✅ STEP 1: Check if scheduled import should start
// ========================================================================
$schedule_enabled = get_option('realestate_sync_schedule_enabled', false);

if ($schedule_enabled) {
    // Get last run timestamp
    $last_run = get_option('realestate_sync_last_scheduled_run', 0);
    $schedule_config = array(
        'time' => get_option('realestate_sync_schedule_time', '23:00'),
        'frequency' => get_option('realestate_sync_schedule_frequency', 'daily'),
        'weekday' => get_option('realestate_sync_schedule_weekday', 1),
        'custom_days' => get_option('realestate_sync_schedule_custom_days', 1),
        'custom_months' => get_option('realestate_sync_schedule_custom_months', 1)
    );

    // Check if it's time to run
    $should_run = false;
    $now = current_time('timestamp');
    $today = strtotime(current_time('Y-m-d'));

    list($hour, $minute) = explode(':', $schedule_config['time']);
    $scheduled_time_today = $today + ($hour * 3600) + ($minute * 60);

    switch ($schedule_config['frequency']) {
        case 'daily':
            // Run if we passed scheduled time today and haven't run today yet
            if ($now >= $scheduled_time_today && $last_run < $today) {
                $should_run = true;
            }
            break;

        case 'weekly':
            $current_weekday = intval(date('w', $now));
            if ($current_weekday == $schedule_config['weekday'] &&
                $now >= $scheduled_time_today &&
                $last_run < $today) {
                $should_run = true;
            }
            break;

        case 'custom_days':
            $days_since_last = floor(($now - $last_run) / 86400);
            if ($days_since_last >= $schedule_config['custom_days'] &&
                $now >= $scheduled_time_today &&
                $last_run < $today) {
                $should_run = true;
            }
            break;

        case 'custom_months':
            $months_since_last = floor(($now - $last_run) / (86400 * 30));
            if ($months_since_last >= $schedule_config['custom_months'] &&
                $now >= $scheduled_time_today &&
                $last_run < $today) {
                $should_run = true;
            }
            break;
    }

    if ($should_run) {
        $block_message = RealEstate_Sync_Batch_Orchestrator::get_import_start_block_message();

        if (null !== $block_message) {
        // Intentionally silent.
        } else {
        // Update last run timestamp BEFORE starting (prevent duplicate runs)
        update_option('realestate_sync_last_scheduled_run', $now);

        try {
            // Get credential source
            $credential_source = get_option('realestate_sync_credential_source', 'hardcoded');

            if ($credential_source === 'database') {
                $settings = array(
                    'xml_url' => get_option('realestate_sync_xml_url', ''),
                    'username' => get_option('realestate_sync_xml_user', ''),
                    'password' => get_option('realestate_sync_xml_pass', '')
                );

                if (empty($settings['xml_url']) || empty($settings['username']) || empty($settings['password'])) {
                    throw new Exception('XML credentials not configured');
                }

            } else {
                $settings = array(
                    'xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
                    'username' => 'trentinoimmobiliare_it',
                    'password' => 'dget6g52'
                );

            }

            // Download XML
            $downloader = new RealEstate_Sync_XML_Downloader();
            $xml_file = $downloader->download_xml($settings['xml_url'], $settings['username'], $settings['password']);

            if (!$xml_file) {
                throw new Exception('Failed to download XML file');
            }

            // Get test mode setting
            $mark_as_test = get_option('realestate_sync_schedule_mark_test', false);

            // Start batch import using Batch Orchestrator
            $result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch($xml_file, $mark_as_test);

            if (!$result['success']) {
                throw new Exception('Batch processing failed: ' . ($result['error'] ?? 'Unknown error'));
            }

        } catch (Exception $e) {
            error_log('[BATCH-CONTINUATION] ❌ Scheduled import failed: ' . $e->getMessage());
        }
        }
    }
}

// ========================================================================
// ✅ STEP 2: Continue processing existing batches
// ========================================================================

// ✅ Check for processing lock (prevent concurrent runs)
// NOTE: Keep lock as transient - it's short-lived (2 min) and not critical if lost
$processing_lock = get_transient('realestate_sync_processing_lock');
if ($processing_lock) {
    echo "OK - Batch already processing\n";
    exit;
}

// Step 7 runtime path: import continuation or delete phase continuation.
global $wpdb;
$queue_table = $wpdb->prefix . 'realestate_import_queue';
$progress = get_option('realestate_sync_background_import_progress', array());
$delete_phase_status = RealEstate_Sync_Batch_Orchestrator::get_blocking_delete_phase_status($progress);
$delete_queue_manager = new RealEstate_Sync_Delete_Queue_Manager();

$active_session = $wpdb->get_row("
    SELECT
        session_id,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 900 SECOND) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as stale_processing_recent,
        SUM(CASE WHEN status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as stale_processing_expired,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing
    FROM {$queue_table}
    GROUP BY session_id
    HAVING pending > 0 OR stale_processing_recent > 0
    ORDER BY MIN(created_at) ASC
    LIMIT 1
");

if ((!$active_session || ((int) ($active_session->pending ?? 0) === 0 && (int) ($active_session->stale_processing_recent ?? 0) === 0)) && null === $delete_phase_status) {
    $run_media_cleanup_tick_if_ready();
    echo "OK - No pending work\n";
    exit;
}

$session_id = $active_session && (((int) ($active_session->pending ?? 0) > 0) || ((int) ($active_session->stale_processing_recent ?? 0) > 0))
    ? $active_session->session_id
    : ($progress['session_id'] ?? '');

if (empty($session_id)) {
    $run_media_cleanup_tick_if_ready();
    echo "OK - No pending work\n";
    exit;
}

set_transient('realestate_sync_processing_lock', $session_id, 120);

try {
    if ($active_session && (((int) ($active_session->pending ?? 0) > 0) || ((int) ($active_session->stale_processing_recent ?? 0) > 0))) {
        if (($progress['session_id'] ?? '') !== $session_id) {
            // Session mismatch is tolerated; queue remains source of truth.
        }

        $xml_file_path = $progress['xml_file_path'] ?? '';
        $mark_as_test = $progress['mark_as_test'] ?? false;
        $force_update = $progress['force_update'] ?? false;

        if (empty($xml_file_path)) {
            throw new Exception('XML file path not found');
        }

        require_once(dirname(__FILE__) . '/includes/class-realestate-sync-queue-manager.php');
        require_once(dirname(__FILE__) . '/includes/class-realestate-sync-batch-processor.php');

        $batch_processor = new RealEstate_Sync_Batch_Processor($session_id, $xml_file_path, $mark_as_test, $force_update);
        $result = $batch_processor->process_next_batch();

        $progress['processed_items'] = ($progress['processed_items'] ?? 0) + ($result['processed'] ?? 0);
        $current_functional_stats = $progress['functional_stats'] ?? RealEstate_Sync_Tracking_Manager::get_functional_stats_defaults();
        $batch_functional_stats = $result['functional_stats'] ?? array();
        if (!empty($batch_functional_stats)) {
            $progress['functional_stats'] = RealEstate_Sync_Tracking_Manager::merge_functional_stats($current_functional_stats, $batch_functional_stats);
        }
        $progress['last_batch_time'] = time();
        update_option('realestate_sync_background_import_progress', $progress);

        if (!$result['complete']) {
            delete_transient('realestate_sync_processing_lock');
            echo "OK - Batch processed, more pending\n";
            exit;
        }

        $delete_queue_stats = $delete_queue_manager->get_stats($session_id);

        if ((int) $delete_queue_stats['pending'] > 0) {
            $delete_runtime = isset($progress['delete_runtime']) && is_array($progress['delete_runtime']) ? $progress['delete_runtime'] : array();
            $mode = $delete_runtime['mode'] ?? 'dry_run';
            $worker_enabled = (($delete_runtime['mode'] ?? 'dry_run') !== 'dry_run') && empty($delete_runtime['kill_switch']);

            if ($mode === 'dry_run') {
                $progress['status'] = 'completed';
                $progress['end_time'] = time();
                $progress['delete_state'] = RealEstate_Sync_Batch_Orchestrator::build_delete_state_payload($delete_queue_stats, 'delete_pending', false);
                update_option('realestate_sync_background_import_progress', $progress);

                delete_transient('realestate_sync_processing_lock');
                echo "OK - Dry run complete, delete queue observed only\n";
                exit;
            }

            $progress = $update_delete_phase_progress($progress, $delete_queue_stats, 'delete_pending', $worker_enabled);
            update_option('realestate_sync_background_import_progress', $progress);

            delete_transient('realestate_sync_processing_lock');
            echo "OK - Import complete, delete pending\n";
            exit;
        }

        $progress['status'] = 'completed';
        $progress['end_time'] = time();
        update_option('realestate_sync_background_import_progress', $progress);

        try {
            require_once(dirname(__FILE__) . '/includes/class-realestate-sync-import-verifier.php');
            $verifier = new RealEstate_Sync_Import_Verifier();
            $verifier->verify_session($session_id);
        } catch (Exception $e) {
            error_log('[VERIFICATION] ERROR: ' . $e->getMessage());
        }

        try {
            require_once(dirname(__FILE__) . '/includes/class-realestate-sync-email-report.php');
            $report = RealEstate_Sync_Email_Report::build_report($session_id, $progress);
            RealEstate_Sync_Email_Report::save_snapshot($report);
            RealEstate_Sync_Email_Report::send_email($report);
        } catch (Exception $e) {
            error_log('[EMAIL-REPORT] ERROR: ' . $e->getMessage());
        }

        delete_transient('realestate_sync_processing_lock');
        $run_media_cleanup_tick_if_ready();
        echo "OK - All batches complete!\n";
        exit;
    }

    $delete_runtime = isset($progress['delete_runtime']) && is_array($progress['delete_runtime']) ? $progress['delete_runtime'] : array();
    $mode = $delete_runtime['mode'] ?? 'dry_run';
    $kill_switch = !empty($delete_runtime['kill_switch']);
    $cap = max(1, (int) ($delete_runtime['cap'] ?? 10));

    $recovery_stats = $delete_queue_manager->recover_stale_processing_items();
    $delete_queue_stats = $delete_queue_manager->get_stats($session_id);
    $processed_delete_count = (int) $delete_queue_stats['done'] + (int) $delete_queue_stats['skipped'] + (int) $delete_queue_stats['error'];

    if ((int) $delete_queue_stats['pending'] === 0 && (int) $delete_queue_stats['processing'] === 0) {
        $progress['status'] = 'completed';
        $progress['end_time'] = time();
        $progress['delete_state'] = RealEstate_Sync_Batch_Orchestrator::build_delete_state_payload($delete_queue_stats, 'idle', false);
        update_option('realestate_sync_background_import_progress', $progress);

        delete_transient('realestate_sync_processing_lock');
        $run_media_cleanup_tick_if_ready();
        echo "OK - Delete phase complete\n";
        exit;
    }

    if ($kill_switch) {
        $progress = $update_delete_phase_progress($progress, $delete_queue_stats, 'delete_paused', false);
        update_option('realestate_sync_background_import_progress', $progress);

        delete_transient('realestate_sync_processing_lock');
        echo "OK - Delete phase paused by kill switch\n";
        exit;
    }

    if ($mode === 'dry_run') {
        $progress['status'] = 'completed';
        $progress['end_time'] = time();
        $progress['delete_state'] = RealEstate_Sync_Batch_Orchestrator::build_delete_state_payload($delete_queue_stats, 'delete_pending', false);
        update_option('realestate_sync_background_import_progress', $progress);

        delete_transient('realestate_sync_processing_lock');
        echo "OK - Dry run complete, delete queue observed only\n";
        exit;
    }

    if ($processed_delete_count >= $cap) {
        $progress = $update_delete_phase_progress($progress, $delete_queue_stats, 'delete_paused', false);
        update_option('realestate_sync_background_import_progress', $progress);

        delete_transient('realestate_sync_processing_lock');
        echo "OK - Delete phase paused by cap\n";
        exit;
    }

    $progress = $update_delete_phase_progress($progress, $delete_queue_stats, 'delete_processing', true);
    update_option('realestate_sync_background_import_progress', $progress);

    $delete_batch_processor = new RealEstate_Sync_Delete_Batch_Processor($delete_queue_manager);
    $delete_result = $delete_batch_processor->process_next_item($session_id);

    $delete_queue_stats = $delete_queue_manager->get_stats($session_id);

    if ((int) $delete_queue_stats['pending'] === 0 && (int) $delete_queue_stats['processing'] === 0) {
        $progress['status'] = 'completed';
        $progress['end_time'] = time();
        $progress['delete_state'] = RealEstate_Sync_Batch_Orchestrator::build_delete_state_payload($delete_queue_stats, 'idle', false);
        update_option('realestate_sync_background_import_progress', $progress);

        delete_transient('realestate_sync_processing_lock');
        $run_media_cleanup_tick_if_ready();
        echo "OK - Delete phase complete\n";
        exit;
    }

    $next_delete_status = (int) $delete_queue_stats['processing'] > 0 ? 'delete_processing' : 'delete_pending';
    $progress = $update_delete_phase_progress($progress, $delete_queue_stats, $next_delete_status, true);
    update_option('realestate_sync_background_import_progress', $progress);

    delete_transient('realestate_sync_processing_lock');
    echo "OK - Delete item processed, more pending\n";
    exit;

} catch (Exception $e) {
    error_log('[BATCH-CONTINUATION] ERROR: ' . $e->getMessage());
    error_log('[BATCH-CONTINUATION] Stack trace: ' . $e->getTraceAsString());

    delete_transient('realestate_sync_processing_lock');

    $progress['last_error'] = $e->getMessage();
    $progress['last_error_time'] = time();
    update_option('realestate_sync_background_import_progress', $progress);

    http_response_code(500);
    echo "ERROR - " . $e->getMessage() . "\n";
    exit;
}

// ✅ NEW: Query queue directly for active sessions with pending items
// This is ROBUST - no transient to lose, queue is source of truth!
global $wpdb;
$queue_table = $wpdb->prefix . 'realestate_import_queue';

$active_session = $wpdb->get_row("
    SELECT
        session_id,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 900 SECOND) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as stale_processing_recent,
        SUM(CASE WHEN status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as stale_processing_expired,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing
    FROM {$queue_table}
    GROUP BY session_id
    HAVING pending > 0 OR stale_processing_recent > 0
    ORDER BY MIN(created_at) ASC
    LIMIT 1
");

if (!$active_session || ((int) ($active_session->pending ?? 0) === 0 && (int) ($active_session->stale_processing_recent ?? 0) === 0)) {
    echo "OK - No pending work\n";
    exit;
}

$session_id = $active_session->session_id;

// Set processing lock (expires in 2 minutes - longer than batch timeout)
set_transient('realestate_sync_processing_lock', $session_id, 120);

// Get session info from progress option
$progress = get_option('realestate_sync_background_import_progress', array());

// Verify session match (warn if mismatch but continue)
if (($progress['session_id'] ?? '') !== $session_id) {
    // Session mismatch is tolerated; queue remains source of truth.
}

$xml_file_path = $progress['xml_file_path'] ?? '';
$mark_as_test = $progress['mark_as_test'] ?? false;
$force_update = $progress['force_update'] ?? false;

if (empty($xml_file_path)) {
    error_log('[BATCH-CONTINUATION] ❌ ERROR: XML file path not found in session progress');
    delete_transient('realestate_sync_processing_lock'); // Release lock
    echo "ERROR - XML file path not found\n";
    exit;
}

// ⚠️  XML file check REMOVED - batch processor uses pre-parsed data from database!
// The XML file is only needed during orchestrator phase (already completed).
// Background batches read data from wp_options, not from XML file.
// See: class-realestate-sync-batch-processor.php:477 - get_option("realestate_sync_batch_data_{$session_id}")
//
// if (!file_exists($xml_file_path)) {
//     error_log("[BATCH-CONTINUATION] ❌ ERROR: XML file does not exist: {$xml_file_path}");
//     ...OMITTED...
// }

// Load the batch processor
require_once(dirname(__FILE__) . '/includes/class-realestate-sync-queue-manager.php');
require_once(dirname(__FILE__) . '/includes/class-realestate-sync-batch-processor.php');

try {
    $batch_processor = new RealEstate_Sync_Batch_Processor($session_id, $xml_file_path, $mark_as_test, $force_update);

	    $result = $batch_processor->process_next_batch();

	    // Update progress
	    $progress['processed_items'] = ($progress['processed_items'] ?? 0) + ($result['processed'] ?? 0);
	    $current_functional_stats = $progress['functional_stats'] ?? RealEstate_Sync_Tracking_Manager::get_functional_stats_defaults();
	    $batch_functional_stats = $result['functional_stats'] ?? array();
	    if (!empty($batch_functional_stats)) {
	        $progress['functional_stats'] = RealEstate_Sync_Tracking_Manager::merge_functional_stats($current_functional_stats, $batch_functional_stats);
	    }
	    $progress['last_batch_time'] = time();
	    update_option('realestate_sync_background_import_progress', $progress);

    // ✅ Release processing lock
    delete_transient('realestate_sync_processing_lock');
    // ✅ NO MORE TRANSIENT MANAGEMENT - Queue is source of truth!
    // Cron will keep running and checking queue until it's empty
    if (!$result['complete']) {
        echo "OK - Batch processed, more pending\n";
    } else {
        // Mark import as complete
        $progress['status'] = 'completed';
        $progress['end_time'] = time();
        update_option('realestate_sync_background_import_progress', $progress);

        // ✅ POST-IMPORT VERIFICATION: Quality check su proprietà INSERT/UPDATE
        try {
            require_once(dirname(__FILE__) . '/includes/class-realestate-sync-import-verifier.php');
            $verifier = new RealEstate_Sync_Import_Verifier();
            $verifier->verify_session($session_id);
        } catch (Exception $e) {
            error_log('[VERIFICATION] ERROR: ' . $e->getMessage());
            // Non blocca - verifica è opzionale
        }

        // Build end-of-batch report (no email send in phase 1)
        try {
            require_once(dirname(__FILE__) . '/includes/class-realestate-sync-email-report.php');
            $report = RealEstate_Sync_Email_Report::build_report($session_id, $progress);
            RealEstate_Sync_Email_Report::save_snapshot($report);
            RealEstate_Sync_Email_Report::send_email($report);
        } catch (Exception $e) {
            error_log('[EMAIL-REPORT] ERROR: ' . $e->getMessage());
        }

        $run_media_cleanup_tick_if_ready();
        echo "OK - All batches complete!\n";
    }

} catch (Exception $e) {
    error_log('[BATCH-CONTINUATION] ❌ ERROR: ' . $e->getMessage());
    error_log('[BATCH-CONTINUATION] Stack trace: ' . $e->getTraceAsString());

    // ✅ Release processing lock on error
    delete_transient('realestate_sync_processing_lock');
    // Mark error in progress
    $progress['last_error'] = $e->getMessage();
    $progress['last_error_time'] = time();
    update_option('realestate_sync_background_import_progress', $progress);

    http_response_code(500);
    echo "ERROR - " . $e->getMessage() . "\n";
}
