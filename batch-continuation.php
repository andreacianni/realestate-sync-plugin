<?php
/**
 * Batch Continuation Endpoint
 *
 * Called by server cron every minute to process pending batches.
 * Security: Requires secret token to prevent unauthorized access.
 *
 * Server Cron Command:
 * * * * * * wget -q -O - "https://trentinoimmobiliare.it/wp-content/plugins/realestate-sync-plugin/batch-continuation.php?token=TrentinoImmo2025Secret!" >/dev/null 2>&1
 *
 * @package RealEstate_Sync
 * @version 1.5.0
 * @since 1.5.0
 */

// Security check - require secret token
if (!isset($_GET['token']) || $_GET['token'] !== 'TrentinoImmo2025Secret!') {
    error_log('[BATCH-CONTINUATION] ❌ Unauthorized access attempt from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    http_response_code(403);
    die('Forbidden');
}

error_log('[BATCH-CONTINUATION] ========== Cron check started ==========');

// Load WordPress
define('WP_USE_THEMES', false);
require_once(dirname(__FILE__) . '/../../../wp-load.php');

// Check if there's a pending batch
$pending_session = get_transient('realestate_sync_pending_batch');

if (!$pending_session) {
    error_log('[BATCH-CONTINUATION] No pending batch found - exiting');
    echo "OK - No pending batch\n";
    exit;
}

error_log("[BATCH-CONTINUATION] >>> Found pending session: {$pending_session}");

// Get XML file path and mark_as_test flag from session progress
$progress = get_option('realestate_sync_background_import_progress', array());
$xml_file_path = $progress['xml_file_path'] ?? '';
$mark_as_test = $progress['mark_as_test'] ?? false;

if (empty($xml_file_path)) {
    error_log('[BATCH-CONTINUATION] ❌ ERROR: XML file path not found in session progress');
    echo "ERROR - XML file path not found\n";
    exit;
}

if (!file_exists($xml_file_path)) {
    error_log("[BATCH-CONTINUATION] ❌ ERROR: XML file does not exist: {$xml_file_path}");
    echo "ERROR - XML file not found\n";
    exit;
}

error_log("[BATCH-CONTINUATION] >>> XML file: {$xml_file_path}");
error_log("[BATCH-CONTINUATION] >>> Mark as test: " . ($mark_as_test ? 'YES' : 'NO'));

// Delete transient to prevent concurrent execution
delete_transient('realestate_sync_pending_batch');
error_log('[BATCH-CONTINUATION] >>> Transient deleted (preventing concurrent runs)');

// Load the batch processor
require_once(dirname(__FILE__) . '/includes/class-realestate-sync-queue-manager.php');
require_once(dirname(__FILE__) . '/includes/class-realestate-sync-batch-processor.php');

try {
    error_log('[BATCH-CONTINUATION] >>> Creating batch processor...');
    $batch_processor = new RealEstate_Sync_Batch_Processor($pending_session, $xml_file_path, $mark_as_test);

    error_log('[BATCH-CONTINUATION] >>> Processing next batch...');
    $result = $batch_processor->process_next_batch();

    error_log('[BATCH-CONTINUATION] <<< Batch result: ' . json_encode($result['stats'] ?? []));

    // Update progress
    $progress['processed_items'] = ($progress['processed_items'] ?? 0) + ($result['processed'] ?? 0);
    $progress['last_batch_time'] = time();
    update_option('realestate_sync_background_import_progress', $progress);

    // If not complete, set transient for next run
    if (!$result['complete']) {
        set_transient('realestate_sync_pending_batch', $pending_session, 300);
        error_log("[BATCH-CONTINUATION] >>> Batch not complete - transient reset for continuation");
        echo "OK - Batch processed, more pending\n";
    } else {
        error_log('[BATCH-CONTINUATION] ========== ALL BATCHES COMPLETE ==========');

        // Mark import as complete
        $progress['status'] = 'completed';
        $progress['end_time'] = time();
        update_option('realestate_sync_background_import_progress', $progress);

        echo "OK - All batches complete!\n";
    }

} catch (Exception $e) {
    error_log('[BATCH-CONTINUATION] ❌ ERROR: ' . $e->getMessage());
    error_log('[BATCH-CONTINUATION] Stack trace: ' . $e->getTraceAsString());

    // Reset transient on error for retry
    set_transient('realestate_sync_pending_batch', $pending_session, 300);

    // Mark error in progress
    $progress['last_error'] = $e->getMessage();
    $progress['last_error_time'] = time();
    update_option('realestate_sync_background_import_progress', $progress);

    http_response_code(500);
    echo "ERROR - " . $e->getMessage() . "\n";
}

error_log('[BATCH-CONTINUATION] ========== Cron check ended ==========');
