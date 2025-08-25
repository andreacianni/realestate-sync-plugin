<?php
/**
 * RealEstate Sync Plugin - Streaming XML Parser with Add-On Integration
 * 
 * XMLReader-based streaming parser con Add-On integration per eliminare
 * BLOCCO #3 debugging issues. Utilizza Add-On tested functions per:
 * - Gallery system (property_images)
 * - Features auto-creation (import_features) 
 * - Agency auto-creation (import_agent)
 *
 * @package RealEstateSync
 * @subpackage Core  
 * @version 2.0.0 - Add-On Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_XML_Parser {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * XMLReader instance
     */
    private $reader;
    
    /**
     * Add-On Integration Components
     */
    private $addon_adapter;
    private $addon_importer;
    private $addon_helper;
    
    /**
     * Configurazione chunk processing
     */
    private $chunk_size = 25; // Properties per chunk (conservative)
    private $sleep_seconds = 1; // Pausa tra chunks
    private $max_memory_mb = 256; // Memory limit warning
    
    /**
     * Progress tracking
     */
    private $total_processed = 0;
    private $chunk_processed = 0;
    private $start_time;
    private $last_checkpoint;
    
    /**
     * Error handling
     */
    private $max_errors = 10;
    private $error_count = 0;
    
    /**
     * Add-On Integration Statistics
     */
    private $addon_stats = [
        'properties_created' => 0,
        'features_processed' => 0,
        'agencies_created' => 0,
        'images_imported' => 0,
        'addon_errors' => 0
    ];
    
    /**
     * Callback functions per processing
     */
    private $property_callback;
    private $chunk_callback;
    private $progress_callback;
    
    /**
     * Constructor with Add-On Integration
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        $this->start_time = microtime(true);
        $this->last_checkpoint = $this->start_time;
        
        // Initialize Add-On Integration Components
        $this->init_addon_integration();
    }
    
    /**
     * Initialize Add-On Integration System
     */
    private function init_addon_integration() {
        try {
            // Load Add-On Integration Files
            $this->load_addon_files();
            
            // Initialize Add-On Components  
            $this->addon_adapter = new RealEstate_Sync_AddOn_Adapter();
            $this->addon_importer = new RealEstate_Sync_AddOn_Importer();
            $this->addon_helper = new RealEstate_Sync_AddOn_Helper();
            
            $this->logger->log("Add-On Integration initialized successfully", 'info');
            
        } catch (Exception $e) {
            $this->logger->log("Failed to initialize Add-On integration: " . $e->getMessage(), 'error');
            throw new Exception("Add-On integration initialization failed");
        }
    }
    
    /**
     * Load required Add-On integration files
     */
    private function load_addon_files() {
        $addon_files = [
            'class-realestate-sync-addon-adapter.php',
            'addon-integration/class-addon-main.php',
            'addon-integration/class-addon-helper.php', 
            'addon-integration/class-addon-importer.php',
            'addon-integration/class-addon-importer-properties.php',
            'addon-integration/class-addon-importer-location.php',
            'addon-integration/class-addon-importer-agents.php'
        ];
        
        $plugin_includes = plugin_dir_path(__FILE__);
        
        foreach ($addon_files as $file) {
            $file_path = $plugin_includes . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->logger->log("Loaded Add-On file: {$file}", 'debug');
            } else {
                throw new Exception("Required Add-On file not found: {$file}");
            }
        }
    }
    
    /**
     * Parse XML file con streaming e Add-On integration
     * 
     * @param string $xml_file_path Path al file XML
     * @return array Results summary
     */
    public function parse_xml_file($xml_file_path) {
        if (!file_exists($xml_file_path)) {
            throw new Exception("XML file not found: $xml_file_path");
        }
        
        $this->logger->log("Starting streaming XML parse with Add-On integration: $xml_file_path", 'info');
        
        // Initialize XMLReader
        $this->reader = new XMLReader();
        if (!$this->reader->open($xml_file_path)) {
            throw new Exception("Cannot open XML file: $xml_file_path");
        }
        
        $results = array(
            'total_processed' => 0,
            'chunks_processed' => 0,
            'errors' => 0,
            'addon_stats' => $this->addon_stats,
            'start_time' => $this->start_time,
            'end_time' => null,
            'duration' => null,
            'memory_peak' => 0
        );
        
        try {
            // Find dataset root
            $this->move_to_element('dataset');
            
            // Process annunci in chunks with Add-On integration
            $chunk_data = array();
            $chunk_index = 0;
            
            while ($this->reader->read()) {
                if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->localName === 'annuncio') {
                    
                    try {
                        // Parse single property (unchanged XML parsing)
                        $property_data = $this->parse_single_property();
                        
                        if ($property_data) {
                            // 🔄 NEW: Convert XML data to Add-On format using Adapter
                            $addon_data = $this->addon_adapter->convert_xml_to_addon_format($property_data);
                            
                            if (!empty($addon_data)) {
                                // 🚀 NEW: Import property using Add-On functions 
                                $import_result = $this->import_property_via_addon($property_data, $addon_data);
                                
                                if ($import_result['success']) {
                                    $this->update_addon_stats($import_result);
                                    $chunk_data[] = array(
                                        'xml_data' => $property_data,
                                        'addon_data' => $addon_data,
                                        'import_result' => $import_result
                                    );
                                    $this->total_processed++;
                                }
                                
                                // Call property callback if set
                                if ($this->property_callback) {
                                    call_user_func($this->property_callback, $property_data, $this->total_processed);
                                }
                                
                                // Check se chunk è completo
                                if (count($chunk_data) >= $this->chunk_size) {
                                    $this->process_chunk($chunk_data, $chunk_index);
                                    $chunk_data = array(); // Reset chunk
                                    $chunk_index++;
                                    
                                    // Memory e performance management
                                    $this->manage_resources();
                                }
                            } else {
                                $this->logger->log("Failed to convert XML property to Add-On format", 'warning');
                            }
                        }
                        
                    } catch (Exception $e) {
                        $this->error_count++;
                        $this->addon_stats['addon_errors']++;
                        $this->logger->log("Error processing property with Add-On at position {$this->total_processed}: " . $e->getMessage(), 'error');
                        
                        if ($this->error_count >= $this->max_errors) {
                            throw new Exception("Too many Add-On processing errors ({$this->error_count}), aborting");
                        }
                    }
                }
            }
            
            // Process ultimo chunk se non vuoto
            if (!empty($chunk_data)) {
                $this->process_chunk($chunk_data, $chunk_index);
                $chunk_index++;
            }
            
            // Final results with Add-On statistics
            $results['total_processed'] = $this->total_processed;
            $results['chunks_processed'] = $chunk_index;
            $results['errors'] = $this->error_count;
            $results['addon_stats'] = $this->addon_stats;
            $results['end_time'] = microtime(true);
            $results['duration'] = $results['end_time'] - $results['start_time'];
            $results['memory_peak'] = memory_get_peak_usage(true) / 1024 / 1024; // MB
            
            $this->logger->log("Streaming parse with Add-On integration completed: {$results['total_processed']} properties in {$results['chunks_processed']} chunks", 'info');
            $this->log_addon_summary($results);
            
        } finally {
            $this->reader->close();
        }
        
        return $results;
    }
    
    /**
     * 🚀 NEW: Import property using Add-On tested functions
     * This replaces BLOCCO #3 custom mapping with battle-tested Add-On functions
     * 
     * @param array $xml_property Original XML data
     * @param array $addon_data Converted Add-On format data
     * @return array Import result
     */
    private function import_property_via_addon($xml_property, $addon_data) {
        $import_result = [
            'success' => false,
            'post_id' => null,
            'features_count' => 0,
            'images_count' => 0,
            'agency_created' => false,
            'errors' => []
        ];
        
        try {
            // Step 1: Create WordPress property post
            $post_data = [
                'post_type' => 'estate_property',
                'post_title' => $this->generate_property_title($xml_property),
                'post_content' => $addon_data['property_description'] ?? '',
                'post_status' => 'publish',
                'meta_input' => [
                    '_gi_import_id' => $xml_property['id'] ?? '',
                    '_gi_import_date' => current_time('mysql')
                ]
            ];
            
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                throw new Exception("Failed to create property post: " . $post_id->get_error_message());
            }
            
            $import_result['post_id'] = $post_id;
            $import_result['success'] = true;
            
            // Step 2: 🖼️ Import images using Add-On property_images() function
            if (!empty($xml_property['file_allegati'])) {
                $images_imported = $this->import_images_via_addon($post_id, $xml_property['file_allegati']);
                $import_result['images_count'] = $images_imported;
                $this->logger->log("Imported {$images_imported} images via Add-On for property {$post_id}", 'debug');
            }
            
            // Step 3: 🏷️ Import features using Add-On import_features() function  
            if (!empty($addon_data['property_features'])) {
                $features_result = $this->addon_importer->import_features($post_id, $addon_data, [], []);
                $import_result['features_count'] = substr_count($addon_data['property_features'], ',') + 1;
                $this->logger->log("Processed features via Add-On for property {$post_id}", 'debug');
            }
            
            // Step 4: 🏢 Import agent/agency using Add-On import_agent() function
            if (!empty($addon_data['property_agent'])) {
                $agent_result = $this->addon_importer->import_agent($post_id, $addon_data, [], []);
                $import_result['agency_created'] = true;
                $this->logger->log("Processed agency via Add-On for property {$post_id}", 'debug');
            }
            
            // Step 5: 📍 Import location using Add-On location importer
            if (!empty($addon_data['property_address']) || !empty($addon_data['_property_latitude'])) {
                $location_result = $this->addon_importer->import_location($post_id, $addon_data, [], []);
                $this->logger->log("Processed location via Add-On for property {$post_id}", 'debug');
            }
            
            // Step 6: 🔧 Import all other property fields using Add-On
            $this->addon_importer->import_text_image_custom_details($post_id, $addon_data, [], []);
            
            $this->logger->log("Property {$post_id} imported successfully via Add-On integration", 'info');
            
        } catch (Exception $e) {
            $import_result['success'] = false;
            $import_result['errors'][] = $e->getMessage();
            $this->logger->log("Add-On import failed for property: " . $e->getMessage(), 'error');
        }
        
        return $import_result;
    }
    
    /**
     * 🖼️ Import images using Add-On property_images function
     * 
     * @param int $post_id WordPress post ID
     * @param array $media_files Array of media files from XML
     * @return int Number of images imported
     */
    private function import_images_via_addon($post_id, $media_files) {
        $images_imported = 0;
        
        foreach ($media_files as $media) {
            if ($media['type'] !== 'foto') continue; // Only process images
            
            try {
                // Download image to WordPress media library
                $image_url = $media['url'];
                $attachment_id = $this->download_image_to_media_library($image_url, $post_id);
                
                if ($attachment_id) {
                    // 🚀 Use Add-On property_images() function for gallery integration
                    $this->addon_importer->property_images($post_id, $attachment_id, $image_url, []);
                    $images_imported++;
                }
                
            } catch (Exception $e) {
                $this->logger->log("Failed to import image via Add-On: " . $e->getMessage(), 'warning');
            }
        }
        
        return $images_imported;
    }
    
    /**
     * Download image to WordPress media library
     * 
     * @param string $image_url Remote image URL
     * @param int $post_id Parent post ID
     * @return int|null Attachment ID or null if failed
     */
    private function download_image_to_media_library($image_url, $post_id) {
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        // Download file to temp location
        $temp_file = download_url($image_url);
        if (is_wp_error($temp_file)) {
            return null;
        }
        
        // Prepare file array for media_handle_sideload
        $file_array = [
            'name' => basename($image_url),
            'tmp_name' => $temp_file
        ];
        
        // Import to media library
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        // Cleanup temp file
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }
        
        return is_wp_error($attachment_id) ? null : $attachment_id;
    }
    
    /**
     * Parse singola property da XMLReader position
     * UPDATED for Add-On compatibility
     * 
     * @return array|null Property data o null se errore
     */
    private function parse_single_property() {
        if ($this->reader->localName !== 'annuncio') {
            return null;
        }
        
        // Leggi tutto il nodo annuncio
        $annuncio_xml = $this->reader->readOuterXML();
        
        if (!$annuncio_xml) {
            return null;
        }
        
        // Parse con DOMDocument per singolo annuncio (efficiente)
        $dom = new DOMDocument();
        if (!$dom->loadXML($annuncio_xml)) {
            return null;
        }
        
        $xpath = new DOMXPath($dom);
        $property_data = array();
        
        // Parse dati base da <info>
        $info_nodes = $xpath->query('//info');
        if ($info_nodes->length > 0) {
            $info = $info_nodes->item(0);
            
            foreach ($info->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $property_data[$child->nodeName] = trim($child->textContent);
                }
            }
        }
        
        // Parse features da <info_inserite> - Format for Add-On Adapter
        $caratteristiche = array();
        $feature_nodes = $xpath->query('//info_inserite/info');
        
        foreach ($feature_nodes as $feature) {
            $feature_id = intval($feature->getAttribute('id'));
            $feature_value = $xpath->query('valore_assegnato', $feature)->item(0);
            if ($feature_value) {
                $caratteristiche[] = [
                    'id' => $feature_id,
                    'valore' => trim($feature_value->textContent)
                ];
            }
        }
        
        // Convert to expected format for Add-On Adapter
        $property_data['caratteristiche'] = $caratteristiche;
        
        // Parse dati numerici da <dati_inseriti>
        $dati_inseriti = array();
        $data_nodes = $xpath->query('//dati_inseriti/dati');
        foreach ($data_nodes as $data) {
            $data_id = $data->getAttribute('id');
            $data_value = $xpath->query('valore_assegnato', $data)->item(0);
            if ($data_value) {
                $dati_inseriti[$data_id] = trim($data_value->textContent);
            }
        }
        $property_data['dati_inseriti'] = $dati_inseriti;
        
        // Parse media files da <file_allegati>
        $media_files = array();
        $media_nodes = $xpath->query('//file_allegati/allegato');
        foreach ($media_nodes as $media) {
            $media_id = $media->getAttribute('id');
            $media_type = $media->getAttribute('type');
            $file_path = $xpath->query('file_path', $media)->item(0);
            if ($file_path) {
                $media_files[] = array(
                    'id' => $media_id,
                    'type' => $media_type,
                    'url' => trim($file_path->textContent)
                );
            }
        }
        $property_data['file_allegati'] = $media_files; // Changed key for consistency
        
        // Parse agency data da <agenzia>
        $agency_data = null;
        $agenzia_nodes = $xpath->query('agenzia');
        if ($agenzia_nodes->length > 0) {
            $agenzia = $agenzia_nodes->item(0);
            $agency_data = array();
            
            foreach ($agenzia->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $agency_data[$child->nodeName] = trim($child->textContent);
                }
            }
            
            // Extract attributes from comune if present
            $comune_node = $xpath->query('comune', $agenzia)->item(0);
            if ($comune_node && $comune_node->hasAttribute('istat')) {
                $agency_data['comune_istat'] = $comune_node->getAttribute('istat');
            }
            
            // Only include agency if has valid ID
            if (!empty($agency_data['id']) && intval($agency_data['id']) > 0) {
                $property_data['agency_data'] = $agency_data;
                $this->logger->log("Agency data extracted: ID {$agency_data['id']}, Name: " . ($agency_data['ragione_sociale'] ?? 'Unknown'), 'debug');
            }
        }
        
        // Validate required fields
        if (!isset($property_data['id']) || empty($property_data['id'])) {
            return null; // Skip properties senza ID
        }
        
        return $property_data;
    }
    
    /**
     * Generate property title from XML data
     * 
     * @param array $xml_property XML property data
     * @return string Generated title
     */
    private function generate_property_title($xml_property) {
        $title_parts = [];
        
        // Property type
        if (!empty($xml_property['tipologia'])) {
            $type_names = [
                1 => 'Casa singola',
                2 => 'Bifamiliare', 
                11 => 'Appartamento',
                12 => 'Attico',
                18 => 'Villa',
                19 => 'Terreno',
                14 => 'Negozio',
                17 => 'Ufficio',
                8 => 'Garage'
            ];
            $title_parts[] = $type_names[$xml_property['tipologia']] ?? 'Immobile';
        }
        
        // Location
        if (!empty($xml_property['comune'])) {
            $title_parts[] = 'a ' . $xml_property['comune'];
        }
        
        // Property ID for uniqueness
        if (!empty($xml_property['id'])) {
            $title_parts[] = '(ID: ' . $xml_property['id'] . ')';
        }
        
        return implode(' ', $title_parts) ?: 'Proprietà Immobiliare';
    }
    
    /**
     * Update Add-On integration statistics
     */
    private function update_addon_stats($import_result) {
        if ($import_result['success']) {
            $this->addon_stats['properties_created']++;
        }
        
        $this->addon_stats['features_processed'] += $import_result['features_count'] ?? 0;
        $this->addon_stats['images_imported'] += $import_result['images_count'] ?? 0;
        
        if ($import_result['agency_created']) {
            $this->addon_stats['agencies_created']++;
        }
        
        if (!empty($import_result['errors'])) {
            $this->addon_stats['addon_errors'] += count($import_result['errors']);
        }
    }
    
    /**
     * Log Add-On integration summary
     */
    private function log_addon_summary($results) {
        $stats = $results['addon_stats'];
        
        $summary = "🚀 ADD-ON INTEGRATION SUMMARY:\n" .
                  "Properties Created: {$stats['properties_created']}\n" .
                  "Features Processed: {$stats['features_processed']}\n" .
                  "Images Imported: {$stats['images_imported']}\n" .
                  "Agencies Created: {$stats['agencies_created']}\n" .
                  "Add-On Errors: {$stats['addon_errors']}";
        
        $this->logger->log($summary, 'info');
    }
    
    // Utility methods remain unchanged
    private function process_chunk($chunk_data, $chunk_index) {
        $chunk_size = count($chunk_data);
        $this->logger->log("Processing chunk $chunk_index with $chunk_size properties (Add-On integrated)", 'debug');
        
        if ($this->chunk_callback) {
            call_user_func($this->chunk_callback, $chunk_data, $chunk_index);
        }
        
        $this->update_progress($chunk_index, $chunk_size);
        
        if ($this->sleep_seconds > 0) {
            sleep($this->sleep_seconds);
        }
    }
    
    private function update_progress($chunk_index, $chunk_size) {
        $current_time = microtime(true);
        $elapsed = $current_time - $this->start_time;
        $chunk_elapsed = $current_time - $this->last_checkpoint;
        
        $progress_data = array(
            'total_processed' => $this->total_processed,
            'current_chunk' => $chunk_index,
            'chunk_size' => $chunk_size,
            'elapsed_seconds' => $elapsed,
            'chunk_duration' => $chunk_elapsed,
            'properties_per_second' => $chunk_size / max($chunk_elapsed, 0.1),
            'memory_usage_mb' => memory_get_usage(true) / 1024 / 1024,
            'errors' => $this->error_count,
            'addon_stats' => $this->addon_stats
        );
        
        if ($this->progress_callback) {
            call_user_func($this->progress_callback, $progress_data);
        }
        
        $this->last_checkpoint = $current_time;
    }
    
    private function manage_resources() {
        $memory_mb = memory_get_usage(true) / 1024 / 1024;
        
        if ($memory_mb > $this->max_memory_mb) {
            $this->logger->log("High memory usage detected: {$memory_mb}MB", 'warning');
            
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            if ($memory_mb > $this->max_memory_mb * 1.5) {
                $this->chunk_size = max(5, intval($this->chunk_size * 0.8));
                $this->logger->log("Reduced chunk size to {$this->chunk_size} due to memory pressure", 'info');
            }
        }
        
        $elapsed = microtime(true) - $this->start_time;
        if ($elapsed > 600) { // 10 minutes warning
            $this->logger->log("Long running process detected: {$elapsed}s elapsed", 'warning');
        }
    }
    
    private function move_to_element($element_name) {
        while ($this->reader->read()) {
            if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->localName === $element_name) {
                return true;
            }
        }
        return false;
    }
    
    public function get_current_stats() {
        $current_time = microtime(true);
        $elapsed = $current_time - $this->start_time;
        
        return array(
            'total_processed' => $this->total_processed,
            'elapsed_seconds' => $elapsed,
            'properties_per_second' => $this->total_processed / max($elapsed, 0.1),
            'memory_usage_mb' => memory_get_usage(true) / 1024 / 1024,
            'errors' => $this->error_count,
            'chunk_size' => $this->chunk_size,
            'addon_stats' => $this->addon_stats
        );
    }
    
    public function get_addon_status() {
        return [
            'adapter_initialized' => $this->addon_adapter !== null,
            'importer_initialized' => $this->addon_importer !== null,
            'helper_initialized' => $this->addon_helper !== null,
            'integration_active' => true,
            'stats' => $this->addon_stats
        ];
    }
    
    public function cleanup() {
        if ($this->reader) {
            $this->reader->close();
        }
        
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    public function __destruct() {
        $this->cleanup();
    }
}
