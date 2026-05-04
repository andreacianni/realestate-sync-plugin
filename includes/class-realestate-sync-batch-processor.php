<?php
/**
 * Batch Processor Class
 *
 * Processes import queue in batches using PROTECTED import methods.
 * This class is a WRAPPER - it CALLS protected methods, does NOT modify them.
 *
 * Protected Methods Used:
 * - RealEstate_Sync_Agency_Parser::extract_agencies_from_xml()
 * - RealEstate_Sync_Agency_Manager::import_agencies()
 * - RealEstate_Sync_Property_Mapper::map_property()
 * - RealEstate_Sync_WP_Importer_API::process_property()
 *
 * @package RealEstate_Sync
 * @version 1.5.0
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Batch_Processor {

    /**
     * Items per batch
     * DIAGNOSTIC: Reduced to 2 to isolate timing issues
     */
    const ITEMS_PER_BATCH = 2;

    /**
     * Threshold (seconds) after which a processing job is considered stale
     */
    const STALE_PROCESSING_THRESHOLD = 900; // 15 minutes

    /**
     * Maximum age (seconds) for automatic stale processing recovery.
     * Older items are marked error for manual cleanup instead of being requeued.
     */
    const STALE_PROCESSING_RECOVERY_MAX_AGE = 604800; // 7 days

    /**
     * Batch timeout (seconds)
     * DIAGNOSTIC: Increased to 600s (matches PHP max_execution_time)
     * to determine if timeouts are in batch or API layer
     */
    const BATCH_TIMEOUT = 600;

    /**
     * Queue Manager instance
     */
    private $queue_manager;

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Debug Tracker instance
     */
    private $tracker;

    /**
     * Tracking Manager instance
     */
    private $tracking_manager;

    /**
     * âœ… IMPORT ENGINE - Reuses all PROTECTED methods
     * Delegates PROPERTY processing to Import_Engine
     * which already has working logic for:
     * - convert_xml_to_v3_format()
     * - map_properties() (plural)
     * - call_wp_importer()
     * - tracking database update
     * - test flag marking
     * - agency data storage
     */
    private $import_engine;

    /**
     * âš ï¸ PROTECTED CLASS INSTANCES - Used for agencies and queue scanning
     */
    private $agency_parser;      // Protected: extracts agencies from XML (for queue scan)
    private $agency_manager;     // Protected: imports agencies (agencies processed directly, not via Import_Engine)
    private $xml_parser;         // âœ… GOLDEN: parses properties using same logic as streaming import

    /**
     * Session ID
     */
    private $session_id;

    /**
     * XML file path
     */
    private $xml_file_path;

    /**
     * Mark as test flag
     */
    private $mark_as_test;

    /**
     * Force update flag
     */
    private $force_update;

    /**
     * Constructor
     *
     * @param string $session_id    Session ID
     * @param string $xml_file_path Path to XML file
     * @param bool   $mark_as_test  Mark items as test (default: false)
     * @param bool   $force_update  Force update bypass (default: false)
     */
    public function __construct($session_id, $xml_file_path, $mark_as_test = false, $force_update = false) {
        $this->session_id = $session_id;
        $this->xml_file_path = $xml_file_path;
        $this->mark_as_test = $mark_as_test;
        $this->force_update = $force_update;

        // Initialize queue manager
        $this->queue_manager = new RealEstate_Sync_Queue_Manager();

        // Initialize logger
        $this->logger = RealEstate_Sync_Logger::get_instance();

        // Initialize debug tracker
        $this->tracker = RealEstate_Sync_Debug_Tracker::get_instance();

        // Initialize tracking manager lazily for recovery paths that need direct hash/record access
        $this->tracking_manager = class_exists('RealEstate_Sync_Tracking_Manager')
            ? new RealEstate_Sync_Tracking_Manager()
            : null;

        // âœ… Create API importer explicitly (GOLDEN approach)
        $wp_importer = new RealEstate_Sync_WP_Importer_API($this->logger);

        // âœ… Initialize Import_Engine with API importer (dependency injection)
        // This ensures batch uses GOLDEN API methods instead of legacy importer
        $this->import_engine = new RealEstate_Sync_Import_Engine(null, $wp_importer, $this->logger);

        // Configure Import_Engine with test flag
        $settings = get_option('realestate_sync_settings', array());
        $settings['mark_as_test'] = $mark_as_test;
        $settings['force_update'] = $force_update;
        $this->import_engine->configure($settings);

        error_log("[BATCH-PROCESSOR] âœ… Import_Engine initialized (mark_as_test=" . ($mark_as_test ? 'YES' : 'NO') . "), force_update=" . ($force_update ? 'YES' : 'NO') . ")");

        // Initialize Agency classes (agencies processed directly via Agency_Manager)
        $this->agency_parser = new RealEstate_Sync_Agency_Parser();
        $this->agency_manager = new RealEstate_Sync_Agency_Manager();

        // âœ… Initialize GOLDEN XML_Parser for property parsing
        $this->xml_parser = new RealEstate_Sync_XML_Parser();
    }

    /**
     * Scan XML and populate queue
     *
     * @param bool $mark_as_test Mark items as test (optional)
     * @return array Scan results
     */
    public function scan_and_populate_queue($mark_as_test = false) {
        error_log("[BATCH-PROCESSOR] >>> Starting XML scan and queue population");

        $start_time = microtime(true);

        try {
            // Load XML
            if (!file_exists($this->xml_file_path)) {
                throw new Exception("XML file not found: {$this->xml_file_path}");
            }

            $xml = simplexml_load_file($this->xml_file_path);
            if (!$xml) {
                throw new Exception("Failed to parse XML file");
            }

            // Get province filter from settings
            $settings = get_option('realestate_sync_settings', array());
            $enabled_provinces = isset($settings['enabled_provinces']) ? $settings['enabled_provinces'] : array('TN', 'BZ');

            error_log("[BATCH-PROCESSOR] Province filter: " . implode(', ', $enabled_provinces));

            // Extract agencies using PROTECTED parser
            error_log("[BATCH-PROCESSOR] >>> Extracting agencies via Agency_Parser");
            $all_agencies = $this->agency_parser->extract_agencies_from_xml($xml);
            $agency_ids = array();
            foreach ($all_agencies as $agency) {
                if (isset($agency['id'])) {
                    $agency_ids[] = $agency['id'];
                }
            }
            error_log("[BATCH-PROCESSOR] <<< Extracted " . count($agency_ids) . " agencies");

            // Scan properties and filter by province
            $valid_property_ids = array();
            $total_announcements = 0;

            foreach ($xml->annuncio as $annuncio) {
                $total_announcements++;

                // Skip deleted
                if (isset($annuncio->deleted) && (string)$annuncio->deleted === '1') {
                    continue;
                }

                // âœ… CRITICAL: Property ID is inside <info> node
                $comune_istat = (string)($annuncio->info->comune_istat ?? '');

                if (empty($comune_istat)) {
                    continue;
                }

                // Filter by province
                $prefix = substr($comune_istat, 0, 3);
                $is_trento = ($prefix === '022' && in_array('TN', $enabled_provinces));
                $is_bolzano = ($prefix === '021' && in_array('BZ', $enabled_provinces));

                if (!$is_trento && !$is_bolzano) {
                    continue;
                }

                // âœ… CRITICAL: Extract property ID from <info> section
                $property_id = (string)$annuncio->info->id;

                if (empty($property_id)) {
                    error_log("[BATCH-PROCESSOR] âš ï¸  Property with empty ID! Comune: {$comune_istat}");
                    continue;
                }

                $valid_property_ids[] = $property_id;
            }

            error_log("[BATCH-PROCESSOR] Scanned {$total_announcements} announcements, found " . count($valid_property_ids) . " valid properties");

            // Clear existing queue for this session
            $this->queue_manager->clear_session_queue($this->session_id);

            // Populate queue - agencies first (higher priority)
            foreach ($agency_ids as $agency_id) {
                $this->queue_manager->add_agency($this->session_id, $agency_id);
            }

            // Then properties
            foreach ($valid_property_ids as $property_id) {
                $this->queue_manager->add_property($this->session_id, $property_id);
            }

            $total_items = count($agency_ids) + count($valid_property_ids);
            $duration = microtime(true) - $start_time;

            error_log("[BATCH-PROCESSOR] <<< Queue populated: {$total_items} items in " . round($duration, 2) . "s");

            return array(
                'success' => true,
                'queue_items' => $total_items,
                'agencies' => count($agency_ids),
                'properties' => count($valid_property_ids),
                'duration' => $duration
            );

        } catch (Exception $e) {
            error_log("[BATCH-PROCESSOR] âŒ Scan failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process next batch of items
     *
     * @return array Batch results
     */
    public function process_next_batch() {
        // Recover stale processing items before starting a new batch
        $recovery_stats = $this->recover_stale_processing_items();
        // ðŸ”„ Resume trace if not already active (background continuation)
        if (!$this->tracker->is_active()) {
            $trace_id = get_option('realestate_sync_current_trace_id');
            $trace_start_time = get_option('realestate_sync_current_trace_start_time');
            $trace_context = get_option('realestate_sync_current_trace_context', array());

            if ($trace_id) {
                $resumed = $this->tracker->resume_trace($trace_id, $trace_start_time, $trace_context);
                if ($resumed) {
                    error_log("[BATCH-PROCESSOR] âœ… Trace resumed: {$trace_id}");
                } else {
                    error_log("[BATCH-PROCESSOR] âš ï¸  Failed to resume trace: {$trace_id}");
                }
            }
        }

        error_log("[BATCH-PROCESSOR] >>> Processing next batch (max " . self::ITEMS_PER_BATCH . " items, timeout " . self::BATCH_TIMEOUT . "s)");

        $start_time = time();
        $processed = 0;
        $errors = 0;
        $agencies_processed = 0;   // âœ… Track agencies separately
        $properties_processed = 0; // âœ… Track properties separately

        // Get next batch of pending items
        $items = $this->queue_manager->get_next_batch($this->session_id, self::ITEMS_PER_BATCH);

        if (empty($items)) {
            $processed += (int) ($recovery_stats['done'] ?? 0);
            $errors += (int) ($recovery_stats['requeued'] ?? 0) + (int) ($recovery_stats['terminal_errors'] ?? 0);
            error_log("[BATCH-PROCESSOR] <<< No items to process - batch complete");
            return array(
                'success' => true,
                'complete' => true,
                'processed' => $processed,
                'errors' => $errors,
                'agencies_processed' => 0,
                'properties_processed' => 0
            );
        }

        error_log("[BATCH-PROCESSOR] Retrieved " . count($items) . " items from queue");

        // Process each item
        foreach ($items as $item) {
            $this->log_queue_fetch($item);
            // Check timeout
            if ((time() - $start_time) > self::BATCH_TIMEOUT) {
                error_log("[BATCH-PROCESSOR] ƒ?ñ‹÷?  Timeout reached, stopping batch");
                break;
            }

            // Mark as processing
            $status_before = $item->status ?? 'pending';
            $retry_before = (int) ($item->retry_count ?? 0);
            $this->queue_manager->mark_processing($item->id);
            $this->log_queue_status_change($item->id, $item->item_id, $item->item_type, $status_before, $retry_before, 'processing');
            $updated_state = $this->queue_manager->get_item($item->id);
            if ($updated_state) {
                $item->status = $updated_state->status;
                $item->retry_count = $updated_state->retry_count;
            }

            $item_start_time = microtime(true);
            $result = array();
            $had_exception = false;
            $property_failed = false;
            error_log("[BATCH-PROCESSOR]    >>> Processing {$item->item_type} ID={$item->item_id}");

            try {
                // Process based on type
                if ($item->item_type === 'agency') {
                    $result = $this->process_agency($item);
                    $agencies_processed++; // ƒo. Count agencies

                    // ÐY"õ FIX PRIORITÇ? 3: Update wp_post_id IMMEDIATELY after agency creation
                    if (!empty($result['wp_post_id'])) {
                        $this->queue_manager->update_wp_post_id($item->id, $result['wp_post_id']);
                        error_log("[BATCH-PROCESSOR]    ƒo. wp_post_id updated: {$result['wp_post_id']}");
                    }

                    // Agencies considered success unless exception thrown
                    $done_status_before = $item->status ?? 'processing';
                    $done_retry_before = (int) ($item->retry_count ?? 0);
                    $this->queue_manager->mark_done($item->id);
                    $this->log_queue_status_change($item->id, $item->item_id, $item->item_type, $done_status_before, $done_retry_before, 'done');
                    $processed++;
                    error_log("[BATCH-PROCESSOR]    <<< SUCCESS: {$item->item_type} {$item->item_id}");
                } else {
                    $result = $this->process_property($item);
                    $properties_processed++; // ƒo. Count properties

                    // ÐY"õ FIX PRIORITÇ? 3: Update wp_post_id IMMEDIATELY after property creation
                    if (!empty($result['post_id'])) {
                        if ($this->finalize_queue_item_success($item, $result['post_id'])) {
                            error_log("[BATCH-PROCESSOR]    ƒo. wp_post_id updated: {$result['post_id']}");
                            $processed++;
                            error_log("[BATCH-PROCESSOR]    <<< SUCCESS: {$item->item_type} {$item->item_id}");
                        } else {
                            $error_msg = 'Failed to finalize property queue item after wp_post_id update';
                            $error_status_before = $item->status ?? 'processing';
                            $error_retry_before = (int) ($item->retry_count ?? 0);
                            $this->queue_manager->mark_error($item->id, $error_msg);
                            $this->log_queue_status_change($item->id, $item->item_id, $item->item_type, $error_status_before, $error_retry_before, 'error', $error_msg);
                            $property_failed = true;
                            $errors++;
                            error_log("[BATCH-PROCESSOR]    ƒ?O ERROR: {$item->item_type} {$item->item_id} - {$error_msg}");
                        }
                    } else {
                        $error_msg = $result['error'] ?? 'Property processing returned no wp_post_id';
                        $error_status_before = $item->status ?? 'processing';
                        $error_retry_before = (int) ($item->retry_count ?? 0);
                        $this->queue_manager->mark_error($item->id, $error_msg);
                        $this->log_queue_status_change($item->id, $item->item_id, $item->item_type, $error_status_before, $error_retry_before, 'error', $error_msg);
                        $property_failed = true;
                        $errors++;
                        error_log("[BATCH-PROCESSOR]    ƒ?O ERROR: {$item->item_type} {$item->item_id} - {$error_msg}");
                    }
                }

            } catch (Exception $e) {
                // Mark as error
                $error_status_before = $item->status ?? 'processing';
                $error_retry_before = (int) ($item->retry_count ?? 0);
                $this->queue_manager->mark_error($item->id, $e->getMessage());
                $this->log_queue_status_change($item->id, $item->item_id, $item->item_type, $error_status_before, $error_retry_before, 'error', $e->getMessage());
                $errors++;
                $had_exception = true;
                if ($item->item_type === 'property') {
                    $property_failed = true;
                }

                error_log("[BATCH-PROCESSOR]    Ÿ?O ERROR: {$item->item_type} {$item->item_id} - " . $e->getMessage());
            }

            // Override success for properties without wp_post_id/success flag
            if (!$had_exception && !$property_failed && $item->item_type === 'property' && (empty($result['success']) || empty($result['post_id']))) {
                $error_msg = $result['error'] ?? 'Property processing returned no wp_post_id';
                $error_status_before = $item->status ?? 'processing';
                $error_retry_before = (int) ($item->retry_count ?? 0);
                $this->queue_manager->mark_error($item->id, $error_msg);
                $this->log_queue_status_change($item->id, $item->item_id, $item->item_type, $error_status_before, $error_retry_before, 'error', $error_msg);
                if ($processed > 0) {
                    $processed--;
                }
                $errors++;
                error_log("[BATCH-PROCESSOR]    ERROR: {$item->item_type} {$item->item_id} - {$error_msg}");
            }

            $duration_ms = round((microtime(true) - $item_start_time) * 1000, 1);
            $wp_post_id = $result['post_id'] ?? ($result['wp_post_id'] ?? null);
            $outcome = ($item->item_type === 'property' && (empty($result['success']) || empty($result['post_id']))) ? 'error' : 'success';
            error_log("[BATCH-PROCESSOR]    >>> Item finished ({$item->item_type} {$item->item_id}) duration_ms={$duration_ms} outcome={$outcome} wp_post_id=" . ($wp_post_id ?? 'null'));
        }
        // Check if complete
        $is_complete = $this->queue_manager->is_session_complete($this->session_id);
        $stats = array_merge([
            'pending' => 0,
            'processing' => 0,
            'done' => 0,
            'error' => 0,
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
        ], (array) $this->queue_manager->get_session_stats($this->session_id));

        $processing_stats = method_exists($this->import_engine, 'get_processing_stats')
            ? $this->import_engine->get_processing_stats()
            : array();
        $agency_session_stats = method_exists($this->agency_manager, 'get_session_stats')
            ? $this->agency_manager->get_session_stats()
            : array();
        $functional_stats = RealEstate_Sync_Tracking_Manager::get_functional_stats_defaults();

        if (!empty($processing_stats['functional_stats']) && is_array($processing_stats['functional_stats'])) {
            $functional_stats = RealEstate_Sync_Tracking_Manager::merge_functional_stats(
                $functional_stats,
                $processing_stats['functional_stats']
            );
        }

        if (!empty($agency_session_stats['functional_stats']) && is_array($agency_session_stats['functional_stats'])) {
            $functional_stats = RealEstate_Sync_Tracking_Manager::merge_functional_stats(
                $functional_stats,
                $agency_session_stats['functional_stats']
            );
        }

        $recovered_done = (int) ($recovery_stats['done'] ?? 0);
        $recovered_errors = (int) ($recovery_stats['requeued'] ?? 0) + (int) ($recovery_stats['terminal_errors'] ?? 0);
        $total_processed = $processed + $recovered_done;
        $total_errors = $errors + $recovered_errors;

        $summary_data = array_merge(array(
            'session_id' => $this->session_id,
            'batch_processed' => $total_processed,
            'batch_errors' => $total_errors,
            'pending' => $stats['pending'],
            'functional_stats' => $functional_stats,
        ), $processing_stats);

        if (!empty($processing_stats)) {
            $this->logger->log(
                'Batch summary: processed=' . ($summary_data['processed'] ?? $processed) . ' created=' . ($summary_data['created'] ?? 0) . ' updated=' . ($summary_data['updated'] ?? 0) . ' skipped=' . ($summary_data['skipped'] ?? 0) . ' deleted=' . ($summary_data['deleted'] ?? 0) . ' errors=' . ($summary_data['errors'] ?? $errors) . ' pending=' . $summary_data['pending'],
                'info',
                $summary_data
            );
        } else {
            $this->logger->log(
                'Batch summary: processed=' . $total_processed . ' errors=' . $total_errors . ' pending=' . $stats['pending'],
                'info',
                $summary_data
            );
        }

        error_log("[BATCH-PROCESSOR] <<< Batch complete: processed={$total_processed}, errors={$total_errors}, remaining=" . $stats['pending']);

        // ðŸ End trace if ALL batches complete
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

            error_log("[BATCH-PROCESSOR] âœ… All batches complete - trace ended and session log closed");
        }

        return array(
            'success' => true,
            'complete' => $is_complete,
            'processed' => $total_processed,
            'errors' => $total_errors,
            'agencies_processed' => $agencies_processed,     // âœ… Return agency count
            'properties_processed' => $properties_processed, // âœ… Return property count
            'recovered_done' => $recovered_done,
            'recovered_requeued' => (int) ($recovery_stats['requeued'] ?? 0),
            'recovered_terminal_errors' => (int) ($recovery_stats['terminal_errors'] ?? 0),
            'processing_stats' => $processing_stats,
            'functional_stats' => $functional_stats,
            'stats' => $stats
        );
    }

    /**
     * Process single agency
     *
     * âš ï¸ USES PROTECTED METHODS - DO NOT MODIFY
     *
     * @param object $queue_item Queue item
     * @return array Result
     * @throws Exception On failure
     */
    private function process_agency($queue_item) {
        $agency_id = $queue_item->item_id;

        // ðŸ” LOG: Start agency processing
        $this->tracker->log_event('INFO', 'BATCH_PROCESSOR', 'Processing agency', array(
            'agency_id' => $agency_id,
            'session_id' => $this->session_id
        ));

        // âœ… OPTIMIZATION: Read pre-parsed data from database (no XML re-loading!)
        $this->tracker->log_event('DEBUG', 'BATCH_PROCESSOR', 'Reading pre-parsed data from database', array(
            'session_id' => $this->session_id
        ));

        $batch_data = get_option("realestate_sync_batch_data_{$this->session_id}");

        if (!$batch_data || !isset($batch_data['agencies'])) {
            $this->tracker->log_event('ERROR', 'BATCH_PROCESSOR', 'Batch data not found in database', array(
                'session_id' => $this->session_id
            ));
            throw new Exception("Batch data not found - session may have expired");
        }

        $all_agencies = $batch_data['agencies'];

        $this->tracker->log_event('DEBUG', 'BATCH_PROCESSOR', 'Agencies loaded from database', array(
            'total_agencies' => count($all_agencies)
        ));

        // Find specific agency
        $agency_data = null;
        foreach ($all_agencies as $agency) {
            if (isset($agency['id']) && $agency['id'] === $agency_id) {
                $agency_data = $agency;
                break;
            }
        }

        if (!$agency_data) {
            $this->tracker->log_event('ERROR', 'BATCH_PROCESSOR', 'Agency not found in batch data', array(
                'agency_id' => $agency_id,
                'available_agencies' => array_column($all_agencies, 'id')
            ));
            throw new Exception("Agency not found in batch data: {$agency_id}");
        }

        $this->tracker->log_event('INFO', 'BATCH_PROCESSOR', 'Agency found in batch data', array(
            'agency_id' => $agency_id,
            'agency_name' => $agency_data['name'] ?? 'unknown'
        ));

        // Get mark_as_test flag from session
        $progress = get_option('realestate_sync_background_import_progress', array());
        $mark_as_test = isset($progress['mark_as_test']) ? $progress['mark_as_test'] : false;

        // âœ… PROTECTED METHOD: Import agency using Agency_Manager
        error_log("[BATCH-PROCESSOR]       >>> Calling Agency_Manager::import_agencies()");
        $this->tracker->log_event('INFO', 'BATCH_PROCESSOR', 'Calling Agency_Manager', array(
            'agency_id' => $agency_id,
            'mark_as_test' => $mark_as_test
        ));

        $agencies_array = array($agency_data);
        $import_results = $this->agency_manager->import_agencies($agencies_array, $mark_as_test);

        error_log("[BATCH-PROCESSOR]       <<< Import result: imported=" . ($import_results['imported'] ?? 0) . ", updated=" . ($import_results['updated'] ?? 0));
        $this->tracker->log_event('INFO', 'BATCH_PROCESSOR', 'Agency_Manager returned', array(
            'imported' => $import_results['imported'] ?? 0,
            'updated' => $import_results['updated'] ?? 0
        ));

        return $import_results;
    }

    /**
     * Process single property
     *
     * âœ… DELEGATES TO IMPORT_ENGINE - Uses exact same processing logic
     * âœ… USES GOLDEN XML_Parser - Same parsing logic as streaming import
     *
     * @param object $queue_item Queue item
     * @return array Result
     * @throws Exception On failure
     */
    private function process_property($queue_item) {
        $property_id = $queue_item->item_id;

        error_log("[BATCH-PROCESSOR]       >>> Looking for property {$property_id} in batch data");

        // âœ… OPTIMIZATION: Read pre-parsed data from database (no XML re-loading!)
        $this->tracker->log_event('DEBUG', 'BATCH_PROCESSOR', 'Reading pre-parsed property data from database', array(
            'property_id' => $property_id,
            'session_id' => $this->session_id
        ));

        $batch_data = get_option("realestate_sync_batch_data_{$this->session_id}");

        if (!$batch_data || !isset($batch_data['properties'])) {
            $this->tracker->log_event('ERROR', 'BATCH_PROCESSOR', 'Batch data not found in database', array(
                'session_id' => $this->session_id
            ));
            throw new Exception("Batch data not found - session may have expired");
        }

        // Get property data directly from pre-parsed data
        $property_data = $batch_data['properties'][$property_id] ?? null;

        if (!$property_data) {
            $this->tracker->log_event('ERROR', 'BATCH_PROCESSOR', 'Property not found in batch data', array(
                'property_id' => $property_id,
                'available_properties' => count($batch_data['properties'])
            ));
            throw new Exception("Property not found in batch data: {$property_id}");
        }

        error_log("[BATCH-PROCESSOR]       <<< Property found in batch data (ID: " . ($property_data['id'] ?? 'unknown') . ")");

        // âœ… DELEGATE TO IMPORT_ENGINE
        // This calls the EXACT same workflow as Import_Engine::execute_chunked_import()
        // - convert_xml_to_v3_format()
        // - map_properties() (plural)
        // - call_wp_importer()
        // - tracking database update
        // - test flag marking
        // - agency data storage
        error_log("[BATCH-PROCESSOR]       >>> Delegating to Import_Engine::process_single_property()");
        $result = $this->import_engine->process_single_property($property_data);
        error_log("[BATCH-PROCESSOR]       <<< Import_Engine result: " . ($result['success'] ? 'SUCCESS' : 'FAILED'));

        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Unknown error');
        }

        return $result;
    }


    /**
     * Check if processing is complete
     *
     * @return bool True if complete
     */
    public function is_complete() {
        return $this->queue_manager->is_session_complete($this->session_id);
    }

    /**
     * Get final summary
     *
     * @return array Summary data
     */
    public function get_final_summary() {
        $stats = array_merge([
            'pending' => 0,
            'processing' => 0,
            'done' => 0,
            'error' => 0,
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
        ], (array) $this->queue_manager->get_session_stats($this->session_id));
        $retry_successes = $this->queue_manager->get_retry_successes($this->session_id);
        $failed_items = $this->queue_manager->get_items_by_status($this->session_id, 'error');
        $processing_stats = method_exists($this->import_engine, 'get_processing_stats')
            ? $this->import_engine->get_processing_stats()
            : array();
        $agency_session_stats = method_exists($this->agency_manager, 'get_session_stats')
            ? $this->agency_manager->get_session_stats()
            : array();
        $functional_stats = RealEstate_Sync_Tracking_Manager::get_functional_stats_defaults();

        if (!empty($processing_stats['functional_stats']) && is_array($processing_stats['functional_stats'])) {
            $functional_stats = RealEstate_Sync_Tracking_Manager::merge_functional_stats(
                $functional_stats,
                $processing_stats['functional_stats']
            );
        }

        if (!empty($agency_session_stats['functional_stats']) && is_array($agency_session_stats['functional_stats'])) {
            $functional_stats = RealEstate_Sync_Tracking_Manager::merge_functional_stats(
                $functional_stats,
                $agency_session_stats['functional_stats']
            );
        }

        return array(
            'stats' => $stats,
            'processing_stats' => $processing_stats,
            'functional_stats' => $functional_stats,
            'retry_successes' => count($retry_successes),
            'failed_items' => count($failed_items)
        );
    }

    /**
     * Log queue fetch event for a single item
     *
     * @param object $item Queue item
     */
    private function log_queue_fetch($item) {
        $this->logger->log(
            "Queue fetch: id={$item->id} item_id={$item->item_id} status=" . ($item->status ?? 'unknown') . " retry=" . (int) ($item->retry_count ?? 0),
            'DEBUG',
            array(
                'session_id' => $this->session_id,
                'item_type' => $item->item_type ?? null
            )
        );
    }

    /**
     * Log a queue status transition with retry counts and optional error message
     *
     * @param int         $queue_item_id Queue item ID
     * @param string      $item_id       Domain item id
     * @param string|null $item_type     Item type
     * @param string      $status_before Previous status
     * @param int         $retry_before  Retry count before update
     * @param string|null $status_after_hint Expected status after update (optional)
     * @param string|null $error_message Error message (optional)
     */
    private function log_queue_status_change($queue_item_id, $item_id, $item_type, $status_before, $retry_before, $status_after_hint = null, $error_message = null) {
        $after = $this->queue_manager->get_item($queue_item_id);
        $status_after = $status_after_hint !== null ? $status_after_hint : ($after->status ?? 'unknown');
        $retry_after = $after && isset($after->retry_count) ? (int) $after->retry_count : null;

        $this->logger->log(
            "Queue status change: id={$queue_item_id} item_id={$item_id} {$status_before} -> {$status_after} retry {$retry_before} -> " . ($retry_after !== null ? $retry_after : 'null'),
            'DEBUG',
            array_filter(array(
                'session_id' => $this->session_id,
                'item_type' => $item_type,
                'error_message' => $error_message ? $this->truncate_error_message($error_message) : null
            ), function ($value) {
                return $value !== null && $value !== '';
            })
        );
    }

    /**
     * Truncate error message to avoid log bloat
     *
     * @param string $message Error message
     * @param int    $limit   Max length
     * @return string
     */
    private function truncate_error_message($message, $limit = 200) {
        $message = (string) $message;
        if (strlen($message) <= $limit) {
            return $message;
        }
        return substr($message, 0, $limit) . '...';
    }

    /**
     * Recover stale items stuck in processing for too long
     */
    private function recover_stale_processing_items() {
        $processing_items = $this->queue_manager->get_items_by_status($this->session_id, 'processing');
        if (empty($processing_items)) {
            return array(
                'done' => 0,
                'requeued' => 0,
                'terminal_errors' => 0
            );
        }

        $now = time();
        $recovery_stats = array(
            'done' => 0,
            'requeued' => 0,
            'terminal_errors' => 0
        );

        foreach ($processing_items as $processing_item) {
            $created_at_ts = isset($processing_item->created_at) ? strtotime($processing_item->created_at) : 0;
            if ($created_at_ts <= 0) {
                continue;
            }

            $age_seconds = $now - $created_at_ts;
            if ($age_seconds < self::STALE_PROCESSING_THRESHOLD) {
                continue;
            }

            if ($age_seconds > self::STALE_PROCESSING_RECOVERY_MAX_AGE) {
                $message = 'Manual cleanup required: processing item older than 7 days excluded from auto-recovery';
                $stale_status_before = $processing_item->status ?? 'processing';
                $stale_retry_before = (int) ($processing_item->retry_count ?? 0);
                $this->queue_manager->mark_error($processing_item->id, $message);
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, $processing_item->item_type ?? 'property', $stale_status_before, $stale_retry_before, 'error', $message);
                $recovery_stats['terminal_errors']++;
                error_log("[BATCH-PROCESSOR] Stale item moved to error for manual cleanup: ID={$processing_item->id}, item_id={$processing_item->item_id}, age_seconds={$age_seconds}");
                continue;
            }

            if (($processing_item->item_type ?? 'property') !== 'property') {
                $message = "Auto-reset stale processing item after " . self::STALE_PROCESSING_THRESHOLD . "s";
                $stale_status_before = $processing_item->status ?? 'processing';
                $stale_retry_before = (int) ($processing_item->retry_count ?? 0);
                $this->queue_manager->mark_error($processing_item->id, $message);
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, $processing_item->item_type ?? 'property', $stale_status_before, $stale_retry_before, 'error', $message);
                $this->queue_manager->update_item_status($processing_item->id, 'pending');
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, $processing_item->item_type ?? 'property', 'error', $stale_retry_before + 1, 'pending');
                $recovery_stats['requeued']++;
                error_log("[BATCH-PROCESSOR] Stale non-property item reset to pending: ID={$processing_item->id}, item_id={$processing_item->item_id}, age_seconds={$age_seconds}");
                continue;
            }

            $retry_count = (int) ($processing_item->retry_count ?? 0);
            if ($retry_count >= RealEstate_Sync_Queue_Manager::MAX_RETRIES) {
                $message = "Auto-error stale processing item after {$retry_count} recovery attempts";
                $stale_status_before = $processing_item->status ?? 'processing';
                $this->queue_manager->mark_error($processing_item->id, $message);
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, 'property', $stale_status_before, $retry_count, 'error', $message);
                $recovery_stats['terminal_errors']++;
                error_log("[BATCH-PROCESSOR] Stale property item exceeded recovery limit: ID={$processing_item->id}, item_id={$processing_item->item_id}, retry_count={$retry_count}");
                continue;
            }

            $property_id = (string) $processing_item->item_id;
            $property_data = $this->get_batch_property_data($property_id);
            if (empty($property_data)) {
                $this->queue_item_to_pending_or_error($processing_item, "Stale recovery pending: batch data not available for property {$property_id}", "Stale recovery pending: batch data not available for property {$property_id}");
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, 'property', $processing_item->status ?? 'processing', $retry_count, 'pending', "Stale recovery pending: batch data not available for property {$property_id}");
                $recovery_stats['requeued']++;
                error_log("[BATCH-PROCESSOR] Stale property item requeued - batch data missing: {$property_id}");
                continue;
            }

            $wp_post_id = $this->find_existing_property_post($property_id);
            if (empty($wp_post_id)) {
                $this->queue_item_to_pending_or_error($processing_item, "Stale recovery pending: property post not found for {$property_id}", "Stale recovery pending: property post not found for {$property_id}");
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, 'property', $processing_item->status ?? 'processing', $retry_count, 'pending', "Stale recovery pending: property post not found for {$property_id}");
                $recovery_stats['requeued']++;
                error_log("[BATCH-PROCESSOR] Stale property item requeued - post missing: {$property_id}");
                continue;
            }

            $validation = $this->validate_recovery_candidate($property_id, $property_data, $wp_post_id);
            if (empty($validation['success'])) {
                $reason = $validation['reason'] ?? "Stale recovery pending: property {$property_id} not yet coherent";
                $this->queue_item_to_pending_or_error($processing_item, $reason, $reason);
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, 'property', $processing_item->status ?? 'processing', $retry_count, 'pending', $reason);
                $recovery_stats['requeued']++;
                error_log("[BATCH-PROCESSOR] Stale property item requeued - {$reason}");
                continue;
            }

            $tracking_manager = $this->get_tracking_manager();
            if (!$tracking_manager) {
                $message = "Stale recovery pending: tracking manager unavailable for property {$property_id}";
                $this->queue_item_to_pending_or_error($processing_item, $message, $message);
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, 'property', $processing_item->status ?? 'processing', $retry_count, 'pending', $message);
                $recovery_stats['requeued']++;
                error_log("[BATCH-PROCESSOR] Stale property item requeued - tracking manager unavailable: {$property_id}");
                continue;
            }

            $property_hash = $tracking_manager->calculate_property_hash($property_data);
            $tracking_updated = $tracking_manager->update_tracking_record(
                intval($property_id),
                $property_hash,
                intval($wp_post_id),
                $property_data,
                'active'
            );

            if (!$tracking_updated) {
                $reason = "Stale recovery pending: tracking not updateable for property {$property_id}";
                $this->queue_item_to_pending_or_error($processing_item, $reason, $reason);
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, 'property', $processing_item->status ?? 'processing', $retry_count, 'pending', $reason);
                $recovery_stats['requeued']++;
                error_log("[BATCH-PROCESSOR] Stale property item requeued - tracking update failed: {$property_id}");
                continue;
            }

            if (!$this->finalize_queue_item_success($processing_item, $wp_post_id)) {
                $reason = "Stale recovery pending: finalize failed for property {$property_id}";
                $this->queue_item_to_pending_or_error($processing_item, $reason, $reason);
                $this->log_queue_status_change($processing_item->id, $processing_item->item_id, 'property', $processing_item->status ?? 'processing', $retry_count, 'pending', $reason);
                $recovery_stats['requeued']++;
                error_log("[BATCH-PROCESSOR] Stale property item requeued - finalize failed: {$property_id}");
                continue;
            }

            $recovery_stats['done']++;
            error_log("[BATCH-PROCESSOR] Stale property item finalized: ID={$processing_item->id}, item_id={$processing_item->item_id}, wp_post_id={$wp_post_id}");
        }

        return $recovery_stats;
    }

    /**
     * Get or build the tracking manager used by stale recovery.
     *
     * @return RealEstate_Sync_Tracking_Manager|null
     */
    private function get_tracking_manager() {
        if ($this->tracking_manager instanceof RealEstate_Sync_Tracking_Manager) {
            return $this->tracking_manager;
        }

        if (!class_exists('RealEstate_Sync_Tracking_Manager')) {
            return null;
        }

        $this->tracking_manager = new RealEstate_Sync_Tracking_Manager();
        return $this->tracking_manager;
    }

    /**
     * Load pre-parsed property data from session batch cache.
     *
     * @param string $property_id Property import ID.
     * @return array|null
     */
    private function get_batch_property_data($property_id) {
        $batch_data = get_option("realestate_sync_batch_data_{$this->session_id}");

        if (!$batch_data || !isset($batch_data['properties']) || !is_array($batch_data['properties'])) {
            return null;
        }

        return $batch_data['properties'][$property_id] ?? null;
    }

    /**
     * Find existing estate_property post by property_import_id.
     *
     * @param string $property_id Property import ID.
     * @return int|null
     */
    private function find_existing_property_post($property_id) {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = 'property_import_id'
              AND pm.meta_value = %s
              AND p.post_type = 'estate_property'
              AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
            ORDER BY p.ID ASC
            LIMIT 1
        ", $property_id));

        return $post_id ? intval($post_id) : null;
    }

    /**
     * Validate whether a stale processing property can be finalized safely.
     *
     * @param string $property_id Property import ID.
     * @param array  $property_data Pre-parsed XML property data.
     * @param int    $wp_post_id Existing WordPress post ID.
     * @return array
     */
    private function validate_recovery_candidate($property_id, $property_data, $wp_post_id) {
        $post = get_post($wp_post_id);
        if (!$post || $post->post_type !== 'estate_property') {
            return array(
                'success' => false,
                'reason' => "Stale recovery pending: property post missing or invalid for {$property_id}"
            );
        }

        $meta_import_id = (string) get_post_meta($wp_post_id, 'property_import_id', true);
        if ($meta_import_id !== (string) $property_id) {
            return array(
                'success' => false,
                'reason' => "Stale recovery pending: property_import_id mismatch for {$property_id}"
            );
        }

        if (empty($post->post_title)) {
            return array(
                'success' => false,
                'reason' => "Stale recovery pending: empty post title for {$property_id}"
            );
        }

        $property_price = get_post_meta($wp_post_id, 'property_price', true);
        if ($property_price === '' || $property_price === null) {
            return array(
                'success' => false,
                'reason' => "Stale recovery pending: property price missing for {$property_id}"
            );
        }

        $expected_media = $this->count_expected_media_items($property_data);
        $present_media = $this->count_present_wp_media($wp_post_id);
        $required_media = $expected_media > 0 ? (int) ceil($expected_media * 0.8) : 0;

        $this->tracker->log_event('DEBUG', 'BATCH_PROCESSOR', 'Recovery media fallback check', array(
            'property_id' => $property_id,
            'expected_media' => $expected_media,
            'present_media' => $present_media,
            'required_media' => $required_media,
            'mode' => 'count-based-fallback',
            'http_distinction' => 'not_implemented'
        ));
        error_log("[BATCH-PROCESSOR] Recovery media fallback: property_id={$property_id} expected={$expected_media} present={$present_media} required={$required_media}");

        if ($expected_media > 0 && $present_media < $required_media) {
            return array(
                'success' => false,
                'reason' => "Stale recovery pending: media below threshold for {$property_id} ({$present_media}/{$expected_media})",
                'media' => array(
                    'expected' => $expected_media,
                    'present' => $present_media,
                    'required' => $required_media
                )
            );
        }

        return array(
            'success' => true,
            'property_id' => $property_id,
            'wp_post_id' => $wp_post_id,
            'media' => array(
                'expected' => $expected_media,
                'present' => $present_media,
                'required' => $required_media
            )
        );
    }

    /**
     * Count expected media items already available in parsed XML data.
     *
     * @param array $property_data Pre-parsed XML property data.
     * @return int
     */
    private function count_expected_media_items($property_data) {
        $fields = array('media_files', 'file_allegati', 'images', 'photos', 'allegati', 'files');

        foreach ($fields as $field) {
            if (!isset($property_data[$field])) {
                continue;
            }

            $value = $property_data[$field];
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                } else {
                    $value = array($value);
                }
            }

            if (!is_array($value)) {
                continue;
            }

            $count = 0;
            foreach ($value as $media) {
                if (is_string($media) && trim($media) !== '') {
                    $count++;
                } elseif (is_array($media) && !empty($media['url'])) {
                    $count++;
                }
            }

            if ($count > 0) {
                return $count;
            }
        }

        return 0;
    }

    /**
     * Count WP media currently attached to a property post.
     *
     * @param int $wp_post_id WordPress post ID.
     * @return int
     */
    private function count_present_wp_media($wp_post_id) {
        $attachment_ids = array();
        $attachments = get_attached_media('image', $wp_post_id);

        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (!empty($attachment->ID)) {
                    $attachment_ids[] = (int) $attachment->ID;
                }
            }
        }

        $thumbnail_id = get_post_thumbnail_id($wp_post_id);
        if (!empty($thumbnail_id)) {
            $attachment_ids[] = (int) $thumbnail_id;
        }

        $gallery_meta_keys = array('wpestate_property_gallery', 'property_gallery');
        foreach ($gallery_meta_keys as $meta_key) {
            $gallery_meta = get_post_meta($wp_post_id, $meta_key, true);
            if (!is_array($gallery_meta)) {
                continue;
            }

            foreach ($gallery_meta as $gallery_item) {
                if (is_numeric($gallery_item)) {
                    $attachment_ids[] = (int) $gallery_item;
                }
            }
        }

        return count(array_unique(array_filter($attachment_ids)));
    }

    /**
     * Move an item back to pending or terminal error based on retry threshold.
     *
     * @param object $item Queue item.
     * @param string $error_msg Error message.
     * @param string $log_message Logging message.
     * @return void
     */
    private function queue_item_to_pending_or_error($item, $error_msg, $log_message) {
        $retry_count = (int) ($item->retry_count ?? 0);

        if ($retry_count >= RealEstate_Sync_Queue_Manager::MAX_RETRIES) {
            $status_before = $item->status ?? 'processing';
            $this->queue_manager->mark_error($item->id, $error_msg);
            $this->log_queue_status_change($item->id, $item->item_id, $item->item_type ?? 'property', $status_before, $retry_count, 'error', $error_msg);
            return;
        }

        $status_before = $item->status ?? 'processing';
        $this->queue_manager->mark_error($item->id, $error_msg);
        $this->log_queue_status_change($item->id, $item->item_id, $item->item_type ?? 'property', $status_before, $retry_count, 'error', $error_msg);
        $this->queue_manager->update_item_status($item->id, 'pending');
        $this->log_queue_status_change($item->id, $item->item_id, $item->item_type ?? 'property', 'error', $retry_count + 1, 'pending', $log_message);
    }

    /**
     * Finalize a property queue item after recovery or normal processing.
     *
     * @param object $item Queue item.
     * @param int    $wp_post_id WordPress post ID.
     * @return bool
     */
    private function finalize_queue_item_success($item, $wp_post_id) {
        if (empty($wp_post_id)) {
            return false;
        }

        if (!$this->queue_manager->update_wp_post_id($item->id, $wp_post_id)) {
            return false;
        }

        $done_status_before = $item->status ?? 'processing';
        $done_retry_before = (int) ($item->retry_count ?? 0);
        if (!$this->queue_manager->mark_done($item->id)) {
            return false;
        }

        $this->log_queue_status_change($item->id, $item->item_id, $item->item_type, $done_status_before, $done_retry_before, 'done');
        return true;
    }
}
