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
     * ✅ IMPORT ENGINE - Reuses all PROTECTED methods
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
     * ⚠️ PROTECTED CLASS INSTANCES - Used for agencies and queue scanning
     */
    private $agency_parser;      // Protected: extracts agencies from XML (for queue scan)
    private $agency_manager;     // Protected: imports agencies (agencies processed directly, not via Import_Engine)
    private $xml_parser;         // ✅ GOLDEN: parses properties using same logic as streaming import

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
     * Constructor
     *
     * @param string $session_id    Session ID
     * @param string $xml_file_path Path to XML file
     * @param bool   $mark_as_test  Mark items as test (default: false)
     */
    public function __construct($session_id, $xml_file_path, $mark_as_test = false) {
        $this->session_id = $session_id;
        $this->xml_file_path = $xml_file_path;
        $this->mark_as_test = $mark_as_test;

        // Initialize queue manager
        $this->queue_manager = new RealEstate_Sync_Queue_Manager();

        // Initialize logger
        $this->logger = RealEstate_Sync_Logger::get_instance();

        // Initialize debug tracker
        $this->tracker = RealEstate_Sync_Debug_Tracker::get_instance();

        // ✅ Create API importer explicitly (GOLDEN approach)
        $wp_importer = new RealEstate_Sync_WP_Importer_API($this->logger);

        // ✅ Initialize Import_Engine with API importer (dependency injection)
        // This ensures batch uses GOLDEN API methods instead of legacy importer
        $this->import_engine = new RealEstate_Sync_Import_Engine(null, $wp_importer, $this->logger);

        // Configure Import_Engine with test flag
        $settings = get_option('realestate_sync_settings', array());
        $settings['mark_as_test'] = $mark_as_test;
        $this->import_engine->configure($settings);

        error_log("[BATCH-PROCESSOR] ✅ Import_Engine initialized (mark_as_test=" . ($mark_as_test ? 'YES' : 'NO') . ")");

        // Initialize Agency classes (agencies processed directly via Agency_Manager)
        $this->agency_parser = new RealEstate_Sync_Agency_Parser();
        $this->agency_manager = new RealEstate_Sync_Agency_Manager();

        // ✅ Initialize GOLDEN XML_Parser for property parsing
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

                // ✅ CRITICAL: Property ID is inside <info> node
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

                // ✅ CRITICAL: Extract property ID from <info> section
                $property_id = (string)$annuncio->info->id;

                if (empty($property_id)) {
                    error_log("[BATCH-PROCESSOR] ⚠️  Property with empty ID! Comune: {$comune_istat}");
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
            error_log("[BATCH-PROCESSOR] ❌ Scan failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process next batch of items
     *
     * @return array Batch results
     */
    public function process_next_batch() {
        // 🔄 Resume trace if not already active (background continuation)
        if (!$this->tracker->is_active()) {
            $trace_id = get_option('realestate_sync_current_trace_id');
            $trace_start_time = get_option('realestate_sync_current_trace_start_time');
            $trace_context = get_option('realestate_sync_current_trace_context', array());

            if ($trace_id) {
                $resumed = $this->tracker->resume_trace($trace_id, $trace_start_time, $trace_context);
                if ($resumed) {
                    error_log("[BATCH-PROCESSOR] ✅ Trace resumed: {$trace_id}");
                } else {
                    error_log("[BATCH-PROCESSOR] ⚠️  Failed to resume trace: {$trace_id}");
                }
            }
        }

        error_log("[BATCH-PROCESSOR] >>> Processing next batch (max " . self::ITEMS_PER_BATCH . " items, timeout " . self::BATCH_TIMEOUT . "s)");

        $start_time = time();
        $processed = 0;
        $errors = 0;
        $agencies_processed = 0;   // ✅ Track agencies separately
        $properties_processed = 0; // ✅ Track properties separately

        // Get next batch of pending items
        $items = $this->queue_manager->get_next_batch($this->session_id, self::ITEMS_PER_BATCH);

        if (empty($items)) {
            error_log("[BATCH-PROCESSOR] <<< No items to process - batch complete");
            return array(
                'success' => true,
                'complete' => true,
                'processed' => 0,
                'errors' => 0,
                'agencies_processed' => 0,
                'properties_processed' => 0
            );
        }

        error_log("[BATCH-PROCESSOR] Retrieved " . count($items) . " items from queue");

        // Process each item
        foreach ($items as $item) {
            // Check timeout
            if ((time() - $start_time) > self::BATCH_TIMEOUT) {
                error_log("[BATCH-PROCESSOR] ⏱️  Timeout reached, stopping batch");
                break;
            }

            // Mark as processing
            $this->queue_manager->mark_processing($item->id);

            error_log("[BATCH-PROCESSOR]    >>> Processing {$item->item_type} ID={$item->item_id}");

            try {
                // Process based on type
                if ($item->item_type === 'agency') {
                    $result = $this->process_agency($item);
                    $agencies_processed++; // ✅ Count agencies

                    // 🔧 FIX PRIORITÀ 3: Update wp_post_id IMMEDIATELY after agency creation
                    if (!empty($result['wp_post_id'])) {
                        $this->queue_manager->update_wp_post_id($item->id, $result['wp_post_id']);
                        error_log("[BATCH-PROCESSOR]    ✅ wp_post_id updated: {$result['wp_post_id']}");
                    }
                } else {
                    $result = $this->process_property($item);
                    $properties_processed++; // ✅ Count properties

                    // 🔧 FIX PRIORITÀ 3: Update wp_post_id IMMEDIATELY after property creation
                    if (!empty($result['post_id'])) {
                        $this->queue_manager->update_wp_post_id($item->id, $result['post_id']);
                        error_log("[BATCH-PROCESSOR]    ✅ wp_post_id updated: {$result['post_id']}");
                    }
                }

                // Mark as done
                $this->queue_manager->mark_done($item->id);
                $processed++;

                error_log("[BATCH-PROCESSOR]    <<< SUCCESS: {$item->item_type} {$item->item_id}");

            } catch (Exception $e) {
                // Mark as error
                $this->queue_manager->mark_error($item->id, $e->getMessage());
                $errors++;

                error_log("[BATCH-PROCESSOR]    ❌ ERROR: {$item->item_type} {$item->item_id} - " . $e->getMessage());
            }
        }

        // Check if complete
        $is_complete = $this->queue_manager->is_session_complete($this->session_id);
        $stats = $this->queue_manager->get_session_stats($this->session_id);

        error_log("[BATCH-PROCESSOR] <<< Batch complete: processed={$processed}, errors={$errors}, remaining=" . $stats['pending']);

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
        }

        return array(
            'success' => true,
            'complete' => $is_complete,
            'processed' => $processed,
            'errors' => $errors,
            'agencies_processed' => $agencies_processed,     // ✅ Return agency count
            'properties_processed' => $properties_processed, // ✅ Return property count
            'stats' => $stats
        );
    }

    /**
     * Process single agency
     *
     * ⚠️ USES PROTECTED METHODS - DO NOT MODIFY
     *
     * @param object $queue_item Queue item
     * @return array Result
     * @throws Exception On failure
     */
    private function process_agency($queue_item) {
        $agency_id = $queue_item->item_id;

        // 🔍 LOG: Start agency processing
        $this->tracker->log_event('INFO', 'BATCH_PROCESSOR', 'Processing agency', array(
            'agency_id' => $agency_id,
            'session_id' => $this->session_id
        ));

        // ✅ OPTIMIZATION: Read pre-parsed data from database (no XML re-loading!)
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

        // ✅ PROTECTED METHOD: Import agency using Agency_Manager
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
     * ✅ DELEGATES TO IMPORT_ENGINE - Uses exact same processing logic
     * ✅ USES GOLDEN XML_Parser - Same parsing logic as streaming import
     *
     * @param object $queue_item Queue item
     * @return array Result
     * @throws Exception On failure
     */
    private function process_property($queue_item) {
        $property_id = $queue_item->item_id;

        error_log("[BATCH-PROCESSOR]       >>> Looking for property {$property_id} in batch data");

        // ✅ OPTIMIZATION: Read pre-parsed data from database (no XML re-loading!)
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

        // ✅ DELEGATE TO IMPORT_ENGINE
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
        $stats = $this->queue_manager->get_session_stats($this->session_id);
        $retry_successes = $this->queue_manager->get_retry_successes($this->session_id);
        $failed_items = $this->queue_manager->get_items_by_status($this->session_id, 'error');

        return array(
            'stats' => $stats,
            'retry_successes' => count($retry_successes),
            'failed_items' => count($failed_items)
        );
    }
}
