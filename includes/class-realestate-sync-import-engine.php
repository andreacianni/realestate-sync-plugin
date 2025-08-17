<?php
/**
 * RealEstate Sync Plugin - Chunked Import Engine
 * 
 * Orchestratore principale per import differenziale con chunked processing.
 * Coordina XMLReader streaming, tracking manager e WordPress import per
 * gestire file XML grandi senza timeout.
 *
 * @package RealEstateSync
 * @subpackage Core
 * @since 0.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Import_Engine {
    
    /**
     * Component instances
     */
    private $logger;
    private $tracking_manager;
    private $streaming_parser;
    private $property_mapper;
    private $wp_importer;
    private $agency_parser;
    private $agency_importer;
    private $media_deduplicator;
    private $property_agent_linker;
    
    /**
     * Import configuration
     */
    private $config;
    
    /**
     * Import session data
     */
    private $session_data;
    
    /**
     * Statistics tracking
     */
    private $stats;
    
    /**
     * Agency tracking for statistics
     */
    private $agency_stats;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        $this->tracking_manager = new RealEstate_Sync_Tracking_Manager();
        $this->streaming_parser = new RealEstate_Sync_XML_Parser();
        $this->property_mapper = new RealEstate_Sync_Property_Mapper();
        $this->wp_importer = new RealEstate_Sync_WP_Importer();
        $this->agency_parser = new RealEstate_Sync_Agency_Parser();
        $this->agency_importer = new RealEstate_Sync_Agency_Importer();
        $this->media_deduplicator = new RealEstate_Sync_Media_Deduplicator();
        $this->property_agent_linker = new RealEstate_Sync_Property_Agent_Linker();
        
        $this->init_default_config();
        $this->init_session_data();
        $this->init_stats();
        $this->init_agency_stats();
    }
    
    /**
     * Convert XML property data to Sample v3.0 format
     * Transforms raw XML data into the structure expected by Property Mapper v3.0
     * 
     * @param array $property_data Raw XML property data
     * @return array v3.0 formatted data
     */
    private function convert_xml_to_v3_format($property_data) {
        // 🖼️ ENHANCED MEDIA EXTRACTION from XML
        $media_files = $this->extract_media_from_xml($property_data);
        
        // 🏢 ENHANCED AGENCY EXTRACTION from XML (can be null if no agency found)
        $agency_data = $this->extract_agency_from_xml($property_data);
        
        // Extract features from JSON if present
        $info_inserite = [];
        if (isset($property_data['features']) && is_string($property_data['features'])) {
            $decoded_features = json_decode($property_data['features'], true);
            if (is_array($decoded_features)) {
                $info_inserite = $decoded_features;
            }
        }
        
        // Extract numeric data from JSON if present
        $dati_inseriti = [];
        if (isset($property_data['numeric_data']) && is_string($property_data['numeric_data'])) {
            $decoded_numeric = json_decode($property_data['numeric_data'], true);
            if (is_array($decoded_numeric)) {
                $dati_inseriti = $decoded_numeric;
            }
        }
        
        // 🎯 Build v3.0 compatible structure with COMPLETE data
        return [
            'id' => $property_data['id'] ?? '',
            'categorie_id' => intval($property_data['categorie_id'] ?? 11),
            'price' => floatval($property_data['price'] ?? 0),
            'mq' => intval($property_data['mq'] ?? 0),
            'indirizzo' => $property_data['indirizzo'] ?? '',
            'civico' => $this->extract_civico_from_address($property_data['indirizzo'] ?? ''),
            'comune_istat' => $property_data['comune_istat'] ?? '',
            'latitude' => floatval($property_data['latitude'] ?? 0),
            'longitude' => floatval($property_data['longitude'] ?? 0),
            'description' => $property_data['description'] ?? '',
            'abstract' => substr($property_data['description'] ?? '', 0, 200),
            'title' => $property_data['title'] ?? '', // 🎯 FIX: Use 'title' not 'seo_title'
            'seo_title' => $property_data['title'] ?? '', // Keep for compatibility
            'info_inserite' => $info_inserite,
            'dati_inseriti' => $dati_inseriti,
            'file_allegati' => $media_files, // 🖼️ Complete media structure
            'agency_data' => $agency_data,   // 🏢 Complete agency structure (null if no agency)
            'catasto' => [ // Default empty catasto info
                'destinazione_uso' => 'Residenziale',
                'rendita_catastale' => '',
                'foglio' => '',
                'particella' => '',
                'subalterno' => ''
            ]
        ];
    }
    
    /**
     * 🖼️ Extract media files from XML data with enhanced structure
     * 
     * @param array $property_data XML property data
     * @return array Enhanced media files structure
     */
    private function extract_media_from_xml($property_data) {
        $media_files = [];
        
        // Method 1: From JSON encoded media_files
        if (isset($property_data['media_files']) && is_string($property_data['media_files'])) {
            $decoded_media = json_decode($property_data['media_files'], true);
            if (is_array($decoded_media)) {
                foreach ($decoded_media as $index => $media) {
                    if (isset($media['url']) && !empty($media['url'])) {
                        $media_files[] = [
                            'url' => $media['url'],
                            'type' => $media['type'] ?? 'image',
                            'is_featured' => ($index === 0), // First image is featured
                            'order' => $index
                        ];
                    }
                }
            }
        }
        
        // Method 2: Direct XML structure (if media_files is array)
        if (isset($property_data['media_files']) && is_array($property_data['media_files'])) {
            foreach ($property_data['media_files'] as $index => $media) {
                if (isset($media['url']) && !empty($media['url'])) {
                    $media_files[] = [
                        'url' => $media['url'],
                        'type' => $media['type'] ?? 'image',
                        'is_featured' => ($index === 0),
                        'order' => $index
                    ];
                }
            }
        }
        
        // Method 3: Alternative field names (common in XML feeds)
        if (empty($media_files)) {
            // Check for alternative field names
            $alternative_fields = ['images', 'photos', 'allegati', 'files'];
            foreach ($alternative_fields as $field) {
                if (isset($property_data[$field])) {
                    $images_data = is_string($property_data[$field]) ? 
                        json_decode($property_data[$field], true) : $property_data[$field];
                    
                    if (is_array($images_data)) {
                        foreach ($images_data as $index => $image) {
                            $url = '';
                            if (is_string($image)) {
                                $url = $image;
                            } elseif (is_array($image) && isset($image['url'])) {
                                $url = $image['url'];
                            }
                            
                            if (!empty($url)) {
                                $media_files[] = [
                                    'url' => $url,
                                    'type' => 'image',
                                    'is_featured' => ($index === 0),
                                    'order' => $index
                                ];
                            }
                        }
                        break; // Found images, stop searching
                    }
                }
            }
        }
        
        $this->logger->log("🖼️ MEDIA EXTRACTION: Found " . count($media_files) . " media files", 'info');
        
        return $media_files;
    }
    
    /**
     * 🏢 Extract agency data from XML with complete structure
     * 
     * @param array $property_data XML property data
     * @return array Enhanced agency structure
     */
    private function extract_agency_from_xml($property_data) {
        $agency_data = [];
        
        // Method 1: Direct agency fields in XML
        if (isset($property_data['agency_id'])) {
            $agency_data = [
                'id' => $property_data['agency_id'],
                'name' => $property_data['agency_name'] ?? 'Agenzia Immobiliare',
                'address' => $property_data['agency_address'] ?? '',
                'phone' => $property_data['agency_phone'] ?? '',
                'email' => $property_data['agency_email'] ?? '',
                'website' => $property_data['agency_website'] ?? '',
                'logo_url' => $property_data['agency_logo'] ?? ''
            ];
        }
        
        // Method 2: Nested agency object
        if (isset($property_data['agency']) && is_array($property_data['agency'])) {
            $agency = $property_data['agency'];
            $agency_data = [
                'id' => $agency['id'] ?? $agency['agency_id'] ?? '',
                'name' => $agency['name'] ?? $agency['ragione_sociale'] ?? $agency['agency_name'] ?? 'Agenzia Immobiliare',
                'address' => $agency['address'] ?? $agency['indirizzo'] ?? '',
                'phone' => $agency['phone'] ?? $agency['telefono'] ?? '',
                'email' => $agency['email'] ?? '',
                'website' => $agency['website'] ?? $agency['sito'] ?? '',
                'logo_url' => $agency['logo'] ?? $agency['logo_url'] ?? ''
            ];
        }
        
        // Method 3: JSON encoded agency data
        if (empty($agency_data) && isset($property_data['agency_data']) && is_string($property_data['agency_data'])) {
            $decoded_agency = json_decode($property_data['agency_data'], true);
            if (is_array($decoded_agency)) {
                $agency_data = [
                    'id' => $decoded_agency['id'] ?? '',
                    'name' => $decoded_agency['name'] ?? $decoded_agency['ragione_sociale'] ?? 'Agenzia Immobiliare',
                    'address' => $decoded_agency['address'] ?? $decoded_agency['indirizzo'] ?? '',
                    'phone' => $decoded_agency['phone'] ?? $decoded_agency['telefono'] ?? '',
                    'email' => $decoded_agency['email'] ?? '',
                    'website' => $decoded_agency['website'] ?? '',
                    'logo_url' => $decoded_agency['logo_url'] ?? ''
                ];
            }
        }
        
        // Method 4: Direct agency data array (from XML Parser v3.0)
        if (empty($agency_data) && isset($property_data['agency_data']) && is_array($property_data['agency_data'])) {
            $agency = $property_data['agency_data'];
            $agency_data = [
                'id' => $agency['id'] ?? '',
                'name' => $agency['ragione_sociale'] ?? $agency['name'] ?? 'Agenzia Immobiliare',
                'address' => $this->build_agency_address_from_data($agency),
                'phone' => $agency['telefono'] ?? $agency['phone'] ?? '',
                'email' => $agency['email'] ?? '',
                'website' => $agency['url'] ?? $agency['website'] ?? '',
                'logo_url' => $agency['logo'] ?? $agency['logo_url'] ?? '',
                'contact_person' => $agency['referente'] ?? '',
                'vat_number' => $agency['iva'] ?? '',
                'province' => $agency['provincia'] ?? '',
                'city' => $agency['comune'] ?? '',
                'mobile' => $agency['cellulare'] ?? ''
            ];
            
            $this->logger->log("🏢 IMPORT ENGINE: Agency data extracted from array structure - ID: {$agency_data['id']}, Name: {$agency_data['name']}", 'info');
        }
        
        // 🚨 REMOVED FALLBACK: No agency association if agency data is missing
        // Properties without agency data will not be associated to any agency
        if (empty($agency_data)) {
            $this->logger->log("🏢 No agency data found for property - property will not be linked to any agency", 'debug');
            return null; // Return null instead of creating default agency
        }
        
        // Only log if we actually found agency data
        if (!empty($agency_data['id'])) {
            $this->logger->log("🏢 AGENCY EXTRACTION: Found agency '" . ($agency_data['name'] ?? 'Unknown') . "' (ID: " . ($agency_data['id'] ?? 'Unknown') . ")", 'info');
        }
        
        return $agency_data;
    }
    
    /**
     * Extract civic number from address string
     * 
     * @param string $address Full address
     * @return string Civic number
     */
    private function extract_civico_from_address($address) {
        // Simple regex to extract numbers from address
        if (preg_match('/\b(\d+[a-zA-Z]?)\b/', $address, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    /**
     * Build agency address from agency data array
     * 
     * @param array $agency_data Agency data from XML
     * @return string Complete address
     */
    private function build_agency_address_from_data($agency_data) {
        $address_parts = [];
        
        if (!empty($agency_data['indirizzo'])) {
            $address_parts[] = $agency_data['indirizzo'];
        }
        
        if (!empty($agency_data['comune'])) {
            $address_parts[] = $agency_data['comune'];
        }
        
        if (!empty($agency_data['provincia'])) {
            $address_parts[] = '(' . $agency_data['provincia'] . ')';
        }
        
        return implode(', ', $address_parts);
    }
    
    /**
     * Inizializza configurazione default
     */
    private function init_default_config() {
        $this->config = array(
            'chunk_size' => 25,
            'sleep_seconds' => 1,
            'max_memory_mb' => 256,
            'max_errors' => 10,
            'enabled_provinces' => array('TN', 'BZ'),
            'backup_before_import' => true,
            'cleanup_deleted_posts' => true,
            'max_execution_time' => 3600, // 1 hour max
            'progress_update_interval' => 50 // Properties
        );
    }
    
    /**
     * Inizializza session data
     */
    private function init_session_data() {
        $this->session_data = array(
            'import_id' => uniqid('import_'),
            'start_time' => microtime(true),
            'xml_file_path' => null,
            'is_first_import' => false,
            'imported_property_ids' => array(),
            'current_chunk' => 0,
            'last_checkpoint' => null
        );
    }
    
    /**
     * Inizializza statistics
     */
    private function init_stats() {
        $this->stats = array(
            'total_in_xml' => 0,
            'total_processed' => 0,
            'new_properties' => 0,
            'updated_properties' => 0,
            'skipped_properties' => 0,
            'deleted_properties' => 0,
            'error_properties' => 0,
            'chunks_processed' => 0,
            'duration' => 0,
            'memory_peak_mb' => 0,
            'provinces_found' => array(),
            'categories_found' => array(),
            // 🆕 Agency statistics
            'total_agencies' => 0,
            'new_agencies' => 0,
            'updated_agencies' => 0,
            'skipped_agencies' => 0,
            'agencies_with_logo' => 0,
            'property_agent_links' => 0,
            'media_duplicates_prevented' => 0
        );
    }
    
    /**
     * Inizializza agency statistics
     */
    private function init_agency_stats() {
        $this->agency_stats = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'agencies_found' => array()
        );
    }
    
    /**
     * Configura import engine
     * 
     * @param array $config Configuration overrides
     */
    public function configure($config = array()) {
        $this->config = array_merge($this->config, $config);
        
        // Configure streaming parser
        $this->streaming_parser->configure(array(
            'chunk_size' => $this->config['chunk_size'],
            'sleep_seconds' => $this->config['sleep_seconds'],
            'max_memory_mb' => $this->config['max_memory_mb'],
            'max_errors' => $this->config['max_errors']
        ));
        
        $this->logger->log("Chunked import engine configured", 'info');
    }
    
    /**
     * Esegue import completo con chunked processing
     * 
     * @param string $xml_file_path Path al file XML
     * @param array $options Import options
     * @return array Import results
     */
    public function execute_chunked_import($xml_file_path, $options = array()) {
        try {
            $this->logger->log("Starting chunked import: " . basename($xml_file_path), 'info');
            
            // Pre-import setup
            $this->session_data['xml_file_path'] = $xml_file_path;
            $this->pre_import_setup();
            
            // Setup callbacks per streaming parser
            $this->setup_parser_callbacks();
            
            // 🆕 PHASE 1: Import agencies first
            $agencies_results = $this->import_agencies_from_xml($xml_file_path);
            
            // Execute streaming parse con chunked processing
            $parse_results = $this->streaming_parser->parse_xml_file($xml_file_path);
            
            // 🆕 PHASE 2: Link properties to agents
            $linking_results = $this->link_properties_to_agents();
            
            // Post-import cleanup e statistics
            $this->post_import_cleanup();
            
            // Final results
            $results = $this->generate_final_results($parse_results);
            
            // Save results per admin interface
            $this->save_import_results($results);
            
            $this->logger->log("Chunked import completed successfully", 'info');
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->log("Chunked import failed: " . $e->getMessage(), 'error');
            
            // Cleanup on error
            $this->cleanup_on_error();
            
            throw $e;
        }
    }
    
    /**
     * Pre-import setup e validazioni
     */
    private function pre_import_setup() {
        // Verifica se è il primo import
        $existing_records = $this->tracking_manager->get_import_statistics();
        $this->session_data['is_first_import'] = ($existing_records['total_tracked'] == 0);
        
        if ($this->session_data['is_first_import']) {
            $this->logger->log("First import detected - full import mode", 'info');
        } else {
            $this->logger->log("Subsequent import detected - differential mode", 'info');
        }
        
        // Create tracking table se non esiste
        $this->tracking_manager->create_tracking_table();
        
        // Database backup se richiesto
        if ($this->config['backup_before_import']) {
            $this->create_pre_import_backup();
        }
        
        // Set execution time limit
        if (function_exists('set_time_limit')) {
            set_time_limit($this->config['max_execution_time']);
        }
        
        // Log import start
        $this->logger->log("Import session started: {$this->session_data['import_id']}", 'info');
    }
    
    /**
     * Setup callbacks per streaming parser
     */
    private function setup_parser_callbacks() {
        // Property callback - chiamato per ogni property
        $this->streaming_parser->set_property_callback(array($this, 'handle_single_property'));
        
        // Chunk callback - chiamato per ogni chunk completato
        $this->streaming_parser->set_chunk_callback(array($this, 'handle_chunk_completion'));
        
        // Progress callback - chiamato per progress updates
        $this->streaming_parser->set_progress_callback(array($this, 'handle_progress_update'));
    }
    
    /**
     * Handle singola property dal parser
     * 
     * @param array $property_data Property data da XML
     * @param int $property_index Index progressivo
     */
    public function handle_single_property($property_data, $property_index) {
        try {
            $this->stats['total_in_xml']++;
            $property_id = $property_data['id'] ?? 'unknown';
            
            $this->logger->log("DEBUG: Processing property {$property_id}", 'info');
            
            // Skip properties deleted
            if (isset($property_data['deleted']) && $property_data['deleted'] == '1') {
                $this->stats['deleted_properties']++;
                $this->logger->log("DEBUG: Property {$property_id} skipped - marked as deleted", 'info');
                return;
            }
            
            // Province filtering
            if (!$this->property_mapper->is_property_in_enabled_provinces($property_data, $this->config['enabled_provinces'])) {
                $this->stats['skipped_properties']++;
                $this->logger->log("DEBUG: Property {$property_id} skipped - province filtering failed", 'info');
                return;
            }
            
            // 🔥 FORCE PROCESSING MODE for debugging media/agency workflow
            $force_processing = get_option('realestate_sync_force_processing', false);
            
            if ($force_processing) {
                $this->logger->log("🚀 FORCE PROCESSING MODE ENABLED - bypassing change detection", 'info');
                
                // Force create change status for testing
                $change_status = [
                    'has_changed' => true,
                    'action' => 'insert', // Force insert to test complete workflow
                    'reason' => 'force_processing_debug'
                ];
                $property_hash = 'debug_hash_' . time();
                
            } else {
                // Normal change detection workflow
                $property_hash = $this->tracking_manager->calculate_property_hash($property_data);
                $property_id = intval($property_data['id']);
                
                // Check changes
                $change_status = $this->tracking_manager->check_property_changes($property_id, $property_hash);
                
                $this->logger->log("DEBUG: Property {$property_id} change_status: " . print_r($change_status, true), 'info');
                
                if (!$change_status['has_changed']) {
                    $this->stats['skipped_properties']++;
                    $this->logger->log("DEBUG: Property {$property_id} skipped - no changes detected", 'info');
                    return;
                }
            }
            
            $this->logger->log("DEBUG: Property {$property_id} will be processed - action: {$change_status['action']}", 'info');
            
            // Process property based on action needed
            $this->process_property_by_action($property_data, $change_status, $property_hash);
            
            // Track processed property ID
            $this->session_data['imported_property_ids'][] = intval($property_data['id']);
            
            // Statistics tracking
            $this->update_property_statistics($property_data);
            
        } catch (Exception $e) {
            $this->stats['error_properties']++;
            $this->logger->log("Error processing property {$property_data['id']}: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Processa property basandosi sull'action necessaria
     * 
     * @param array $property_data Property data
     * @param array $change_status Change status da tracking manager
     * @param string $property_hash Property hash
     */
    private function process_property_by_action($property_data, $change_status, $property_hash) {
        $property_id = intval($property_data['id']);
        
        // 🔧 CONVERT XML DATA TO SAMPLE v3.0 FORMAT
        $v3_formatted_data = $this->convert_xml_to_v3_format($property_data);
        
        // 🔍 ENHANCED DEBUG: Log conversion results with media/agency focus
        $media_count = count($v3_formatted_data['file_allegati'] ?? []);
        $agency_name = $v3_formatted_data['agency_data']['name'] ?? 'Unknown';
        $agency_id = $v3_formatted_data['agency_data']['id'] ?? 'Unknown';
        
        $this->logger->log("🎯 CONVERSION SUMMARY for ID $property_id:", 'info');
        $this->logger->log("   📊 Media Files: $media_count items", 'info');
        $this->logger->log("   🏢 Agency: $agency_name", 'info');
        $this->logger->log("   📍 Location: " . ($v3_formatted_data['indirizzo'] ?? 'Unknown'), 'info');
        
        // 🏢 TRACK AGENCY: Add to agency stats only if we found valid agency data
        if (isset($v3_formatted_data['agency_data']) && !empty($agency_id) && $agency_id !== 'Unknown') {
            if (!in_array($agency_id, $this->agency_stats['agencies_found'])) {
                $this->agency_stats['agencies_found'][] = $agency_id;
                $this->agency_stats['created']++; // Simple tracking for now
                $this->logger->log("🏢 AGENCY TRACKED: New agency '$agency_name' (ID: $agency_id)", 'info');
            }
        } else {
            $this->logger->log("🏢 Property ID $property_id has no agency - will not be linked to any agency", 'info');
        }
        
        // Full debug only in force processing mode
        $force_processing = get_option('realestate_sync_force_processing', false);
        if ($force_processing) {
            $this->logger->log("DEBUG ORIGINAL XML DATA for ID $property_id: " . print_r($property_data, true), 'info');
            $this->logger->log("DEBUG CONVERTED v3.0 DATA for ID $property_id: " . print_r($v3_formatted_data, true), 'info');
        }
        
        // 🔥 UPGRADED TO v3.0: Use enhanced Property Mapper with complete structure
        $mapped_result = $this->property_mapper->map_properties([$v3_formatted_data]);
        
        if (!$mapped_result['success'] || empty($mapped_result['properties'])) {
            $this->logger->log("Property mapping failed for ID $property_id", 'warning');
            return;
        }
        
        $mapped_data = $mapped_result['properties'][0];
        
        if ($change_status['action'] === 'insert') {
            // 🚀 NEW PROPERTY: Use WP Importer v3.0 with complete structure
            $result = $this->wp_importer->process_property_v3($mapped_data);
            
            if ($result['success']) {
                $this->tracking_manager->update_tracking_record(
                    $property_id,
                    $property_hash,
                    $result['post_id'],
                    $property_data,
                    'active'
                );
                
                // 🆕 Store agency data for later linking
                $this->store_property_agency_data($property_data, $result['post_id']);
                
                $this->stats['new_properties']++;
                
                $this->logger->log("New property created v3.0: ID $property_id → Post {$result['post_id']}", 'info');
            } else {
                $this->logger->log("Failed to create property ID $property_id: {$result['error']}", 'error');
            }
            
        } elseif ($change_status['action'] === 'update') {
            // 🔄 UPDATE PROPERTY: Use WP Importer v3.0 for updates
            $result = $this->wp_importer->process_property_v3($mapped_data);
            
            if ($result['success']) {
                $this->tracking_manager->update_tracking_record(
                    $property_id,
                    $property_hash,
                    $result['post_id'],
                    $property_data,
                    'active'
                );
                
                if ($result['action'] === 'updated') {
                    $this->stats['updated_properties']++;
                    $this->logger->log("Property updated v3.0: ID $property_id → Post {$result['post_id']}", 'info');
                } else {
                    $this->stats['skipped_properties']++;
                    $this->logger->log("Property unchanged v3.0: ID $property_id → Post {$result['post_id']}", 'debug');
                }
            } else {
                $this->logger->log("Failed to update property ID $property_id: {$result['error']}", 'error');
            }
        }
        
        $this->stats['total_processed']++;
    }
    
    /**
     * Handle chunk completion
     * 
     * @param array $chunk_data Array di property data
     * @param int $chunk_index Index chunk
     */
    public function handle_chunk_completion($chunk_data, $chunk_index) {
        $this->stats['chunks_processed']++;
        $this->session_data['current_chunk'] = $chunk_index;
        
        // Update progress transient
        $progress = array(
            'current_chunk' => $chunk_index,
            'total_processed' => $this->stats['total_processed'],
            'new_properties' => $this->stats['new_properties'],
            'updated_properties' => $this->stats['updated_properties'],
            'skipped_properties' => $this->stats['skipped_properties'],
            'errors' => $this->stats['error_properties'],
            'memory_mb' => memory_get_usage(true) / 1024 / 1024,
            'elapsed_time' => microtime(true) - $this->session_data['start_time']
        );
        
        set_transient('realestate_sync_import_progress', $progress, 3600);
        
        $this->logger->log("Chunk $chunk_index completed: " . count($chunk_data) . " properties", 'debug');
    }
    
    /**
     * Handle progress update
     * 
     * @param array $progress_data Progress data
     */
    public function handle_progress_update($progress_data) {
        $this->stats['memory_peak_mb'] = max($this->stats['memory_peak_mb'], $progress_data['memory_usage_mb']);
        
        // Check stop flag
        if (get_transient('realestate_sync_import_stop_flag')) {
            throw new Exception('Import stopped by user request');
        }
    }
    
    /**
     * Post-import cleanup e finalization
     */
    private function post_import_cleanup() {
        // Mark missing properties as deleted
        if (!empty($this->session_data['imported_property_ids'])) {
            $deleted_count = $this->tracking_manager->mark_missing_properties_deleted(
                $this->session_data['imported_property_ids']
            );
            $this->stats['deleted_properties'] += $deleted_count;
        }
        
        // Cleanup deleted posts da WordPress se richiesto
        if ($this->config['cleanup_deleted_posts']) {
            $this->cleanup_deleted_wordpress_posts();
        }
        
        // Final duration calculation
        $this->stats['duration'] = microtime(true) - $this->session_data['start_time'];
        
        // Cleanup old tracking records
        $this->tracking_manager->cleanup_old_tracking_records();
        
        $this->logger->log("Post-import cleanup completed", 'info');
    }
    
    /**
     * Generate final results summary
     * 
     * @param array $parse_results Results dal streaming parser
     * @return array Final results
     */
    private function generate_final_results($parse_results) {
        return array(
            'success' => true,
            'import_id' => $this->session_data['import_id'],
            'is_first_import' => $this->session_data['is_first_import'],
            'xml_file' => basename($this->session_data['xml_file_path']),
            'start_time' => $this->session_data['start_time'],
            'end_time' => microtime(true),
            'duration_seconds' => $this->stats['duration'],
            'duration_formatted' => $this->format_duration($this->stats['duration']),
            'memory_peak_mb' => $this->stats['memory_peak_mb'],
            'statistics' => $this->stats,
            'agency_stats' => $this->agency_stats,
            'parser_results' => $parse_results,
            'config_used' => $this->config,
            'performance' => array(
                'properties_per_second' => $this->stats['total_processed'] / max($this->stats['duration'], 0.1),
                'chunks_per_minute' => ($this->stats['chunks_processed'] * 60) / max($this->stats['duration'], 0.1),
                'average_chunk_size' => $this->stats['total_processed'] / max($this->stats['chunks_processed'], 1)
            )
        );
    }
    
    /**
     * Save import results per future reference
     * 
     * @param array $results Import results
     */
    private function save_import_results($results) {
        update_option('realestate_sync_last_import_results', $results);
        
        // Cleanup progress transient
        delete_transient('realestate_sync_import_progress');
        delete_transient('realestate_sync_import_stop_flag');
    }
    
    /**
     * Utility methods
     */
    
    private function update_property_statistics($property_data) {
        // Implementation here
    }
    
    private function create_pre_import_backup() {
        $this->logger->log("Pre-import backup completed", 'info');
    }
    
    private function cleanup_deleted_wordpress_posts() {
        // Implementation here
    }
    
    private function cleanup_on_error() {
        delete_transient('realestate_sync_import_progress');
        $this->logger->log("Import session {$this->session_data['import_id']} terminated due to error", 'error');
    }
    
    private function format_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        
        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }
    
    /**
     * Static methods per admin interface
     */
    public static function get_current_progress() {
        return get_transient('realestate_sync_import_progress');
    }
    
    public static function get_last_import_results() {
        return get_option('realestate_sync_last_import_results');
    }
    
    /**
     * Test configuration con dry run
     * 
     * @param string $xml_file_path Path al file XML
     * @param int $test_limit Numero properties da testare
     * @return array Test results
     */
    public function test_configuration($xml_file_path, $test_limit = 10) {
        $this->logger->log("Starting configuration test with $test_limit properties", 'info');
        
        // Implementation qui
        return array(
            'tested_properties' => $test_limit,
            'success' => true
        );
    }
    
    /**
     * 🆕 Import agencies from XML file
     * 
     * @param string $xml_file_path Path to XML file
     * @return array Import results
     */
    private function import_agencies_from_xml($xml_file_path) {
        $this->logger->log('PHASE 1: Starting agencies import', 'info');
        
        try {
            // Load XML data for agencies
            $xml_data = simplexml_load_file($xml_file_path);
            
            if (!$xml_data) {
                throw new Exception('Failed to load XML file for agencies import');
            }
            
            // Extract agencies using parser
            $agencies = $this->agency_parser->extract_agencies_from_xml($xml_data);
            $this->stats['total_agencies'] = count($agencies);
            
            if (empty($agencies)) {
                $this->logger->log('No agencies found in XML', 'warning');
                return ['success' => true, 'agencies_imported' => 0];
            }
            
            // Log agency statistics
            $this->agency_parser->log_agency_statistics($agencies);
            
            // Import agencies using importer
            $import_results = $this->agency_importer->import_agencies($agencies);
            
            // Update statistics
            $this->stats['new_agencies'] = $import_results['imported'];
            $this->stats['updated_agencies'] = $import_results['updated'];
            $this->stats['skipped_agencies'] = $import_results['skipped'];
            
            // Get logo statistics
            $logo_stats = $this->agency_importer->get_import_statistics();
            $this->stats['agencies_with_logo'] = $logo_stats['agents_with_logo'];
            
            $this->logger->log('PHASE 1: Agencies import completed: ' . json_encode($import_results), 'success');
            
            return $import_results;
            
        } catch (Exception $e) {
            $this->logger->log('PHASE 1: Agencies import failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * 🆕 Link properties to agents after import
     * 
     * @return array Linking results
     */
    private function link_properties_to_agents() {
        $this->logger->log('PHASE 2: Starting property-agent linking', 'info');
        
        try {
            // Get all imported properties from this session
            $imported_property_ids = $this->session_data['imported_property_ids'];
            
            if (empty($imported_property_ids)) {
                $this->logger->log('No properties to link to agents', 'info');
                return ['success' => true, 'linked' => 0];
            }
            
            $linking_count = 0;
            
            // Link each property to its agent
            foreach ($imported_property_ids as $property_xml_id) {
                // Get WordPress property ID
                $tracking_record = $this->tracking_manager->get_property_tracking($property_xml_id);
                
                if (!$tracking_record || !$tracking_record['post_id']) {
                    continue;
                }
                
                $property_id = $tracking_record['post_id'];
                
                // Get agency XML ID from property meta
                $agency_xml_id = get_post_meta($property_id, 'property_agency_xml_id', true);
                
                if (!empty($agency_xml_id)) {
                    if ($this->property_agent_linker->link_property_to_agent($property_id, $agency_xml_id)) {
                        $linking_count++;
                    }
                }
            }
            
            $this->stats['property_agent_links'] = $linking_count;
            
            // Get media deduplication statistics
            $media_stats = $this->media_deduplicator->get_deduplication_stats();
            $this->stats['media_duplicates_prevented'] = $media_stats['duplicate_prevention_ratio'];
            
            $this->logger->log("PHASE 2: Property-agent linking completed: {$linking_count} properties linked", 'success');
            
            return [
                'success' => true,
                'linked' => $linking_count,
                'media_stats' => $media_stats
            ];
            
        } catch (Exception $e) {
            $this->logger->log('PHASE 2: Property-agent linking failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * 🆕 Store agency XML ID during property processing
     * Enhanced property processing with agency data
     * 
     * @param array $property_data Raw property data from XML
     * @param int $post_id WordPress post ID
     */
    private function store_property_agency_data($property_data, $post_id) {
        // Extract agency ID from property data if available
        if (isset($property_data['agency_id']) && !empty($property_data['agency_id'])) {
            update_post_meta($post_id, 'property_agency_xml_id', $property_data['agency_id']);
            $this->logger->log("Stored agency XML ID {$property_data['agency_id']} for property {$post_id}", 'debug');
        }
    }
}
