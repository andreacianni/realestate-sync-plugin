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
     */
    const ITEMS_PER_BATCH = 10;

    /**
     * Batch timeout (seconds)
     */
    const BATCH_TIMEOUT = 50;

    /**
     * Queue Manager instance
     */
    private $queue_manager;

    /**
     * Logger instance
     */
    private $logger;

    /**
     * ⚠️ PROTECTED CLASS INSTANCES - DO NOT MODIFY THESE CLASSES
     */
    private $agency_parser;      // Protected: extracts agencies from XML
    private $agency_manager;     // Protected: creates agencies with logos
    private $property_mapper;    // Protected: maps XML to property format
    private $wp_importer;        // Protected: creates properties via API

    /**
     * Session ID
     */
    private $session_id;

    /**
     * XML file path
     */
    private $xml_file_path;

    /**
     * Constructor
     *
     * @param string $session_id    Session ID
     * @param string $xml_file_path Path to XML file
     */
    public function __construct($session_id, $xml_file_path) {
        $this->session_id = $session_id;
        $this->xml_file_path = $xml_file_path;

        // Initialize queue manager
        $this->queue_manager = new RealEstate_Sync_Queue_Manager();

        // Initialize logger
        $this->logger = RealEstate_Sync_Logger::get_instance();

        // Initialize PROTECTED class instances
        $this->agency_parser = new RealEstate_Sync_Agency_Parser();
        $this->agency_manager = new RealEstate_Sync_Agency_Manager();
        $this->property_mapper = new RealEstate_Sync_Property_Mapper();
        $this->wp_importer = new RealEstate_Sync_WP_Importer_API($this->logger);
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
        error_log("[BATCH-PROCESSOR] >>> Processing next batch (max " . self::ITEMS_PER_BATCH . " items, timeout " . self::BATCH_TIMEOUT . "s)");

        $start_time = time();
        $processed = 0;
        $errors = 0;

        // Get next batch of pending items
        $items = $this->queue_manager->get_next_batch($this->session_id, self::ITEMS_PER_BATCH);

        if (empty($items)) {
            error_log("[BATCH-PROCESSOR] <<< No items to process - batch complete");
            return array(
                'success' => true,
                'complete' => true,
                'processed' => 0,
                'errors' => 0
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
                } else {
                    $result = $this->process_property($item);
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

        return array(
            'success' => true,
            'complete' => $is_complete,
            'processed' => $processed,
            'errors' => $errors,
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

        // Load XML
        $xml = simplexml_load_file($this->xml_file_path);

        // ✅ PROTECTED METHOD: Extract agencies using Agency_Parser
        $all_agencies = $this->agency_parser->extract_agencies_from_xml($xml);

        // Find specific agency
        $agency_data = null;
        foreach ($all_agencies as $agency) {
            if (isset($agency['id']) && $agency['id'] === $agency_id) {
                $agency_data = $agency;
                break;
            }
        }

        if (!$agency_data) {
            throw new Exception("Agency not found in XML: {$agency_id}");
        }

        // Get mark_as_test flag from session
        $progress = get_option('realestate_sync_background_import_progress', array());
        $mark_as_test = isset($progress['mark_as_test']) ? $progress['mark_as_test'] : false;

        // ✅ PROTECTED METHOD: Import agency using Agency_Manager
        error_log("[BATCH-PROCESSOR]       >>> Calling Agency_Manager::import_agencies()");
        $agencies_array = array($agency_data);
        $import_results = $this->agency_manager->import_agencies($agencies_array, $mark_as_test);
        error_log("[BATCH-PROCESSOR]       <<< Import result: imported=" . ($import_results['imported'] ?? 0) . ", updated=" . ($import_results['updated'] ?? 0));

        return $import_results;
    }

    /**
     * Process single property
     *
     * ⚠️ USES PROTECTED METHODS - DO NOT MODIFY
     *
     * @param object $queue_item Queue item
     * @return array Result
     * @throws Exception On failure
     */
    private function process_property($queue_item) {
        $property_id = $queue_item->item_id;

        // Load XML
        $xml = simplexml_load_file($this->xml_file_path);

        // Find property in XML
        $property_data = null;
        foreach ($xml->annuncio as $annuncio) {
            // ✅ CRITICAL: Property ID is in <info> section
            $current_id = (string)$annuncio->info->id;

            if ($current_id === $property_id) {
                // Parse property data (same as working XML_Parser)
                $dom = new DOMDocument();
                if (!$dom->loadXML($annuncio->asXML())) {
                    throw new Exception("Failed to parse property XML");
                }

                $xpath = new DOMXPath($dom);
                $property_data = array();

                // Parse base data from <info>
                $info_nodes = $xpath->query('//info');
                if ($info_nodes->length > 0) {
                    $info = $info_nodes->item(0);
                    foreach ($info->childNodes as $child) {
                        if ($child->nodeType === XML_ELEMENT_NODE) {
                            $property_data[$child->nodeName] = trim($child->textContent);
                        }
                    }
                }

                // Parse agency data from <agenzia>
                $agency_nodes = $xpath->query('//agenzia');
                if ($agency_nodes->length > 0) {
                    $agenzia = $agency_nodes->item(0);
                    $agency_data = array();

                    foreach ($agenzia->childNodes as $child) {
                        if ($child->nodeType === XML_ELEMENT_NODE) {
                            $agency_data[$child->nodeName] = trim($child->textContent);
                        }
                    }

                    if (isset($agency_data['id']) && !empty($agency_data['id'])) {
                        $property_data['agency_id'] = $agency_data['id'];
                    }

                    $property_data['agency_data'] = $agency_data;
                }

                break;
            }
        }

        if (!$property_data) {
            throw new Exception("Property not found in XML: {$property_id}");
        }

        // ✅ PROTECTED METHOD: Map property using Property_Mapper
        error_log("[BATCH-PROCESSOR]       >>> Calling Property_Mapper::map_property()");
        $mapped_data = $this->property_mapper->map_property($property_data);

        // ✅ PROTECTED METHOD: Import property using WP_Importer_API
        error_log("[BATCH-PROCESSOR]       >>> Calling WP_Importer_API::process_property()");
        $result = $this->wp_importer->process_property($mapped_data);
        error_log("[BATCH-PROCESSOR]       <<< Property created: post_id=" . ($result['post_id'] ?? 'N/A'));

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
