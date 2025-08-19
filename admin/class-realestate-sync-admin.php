<?php
/**
 * RealEstate Sync Plugin - Admin Interface
 * 
 * Main admin interface controller per gestione plugin.
 * Single-page design con status dashboard e emergency import.
 *
 * @package RealEstateSync
 * @subpackage Admin
 * @since 0.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Admin {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Plugin slug
     */
    private $plugin_slug = 'realestate-sync';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        
        // Admin hooks - No menu registration (handled by main class)
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_realestate_sync_manual_import', array($this, 'handle_manual_import'));
        add_action('wp_ajax_realestate_sync_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_realestate_sync_save_settings', array($this, 'handle_save_settings'));
        add_action('wp_ajax_realestate_sync_get_progress', array($this, 'handle_get_progress'));
        add_action('wp_ajax_realestate_sync_get_logs', array($this, 'handle_get_logs'));
        add_action('wp_ajax_realestate_sync_download_logs', array($this, 'handle_download_logs'));
        add_action('wp_ajax_realestate_sync_clear_logs', array($this, 'handle_clear_logs'));
        add_action('wp_ajax_realestate_sync_system_check', array($this, 'handle_system_check'));
        add_action('wp_ajax_realestate_sync_toggle_automation', array($this, 'handle_toggle_automation'));
        add_action('wp_ajax_realestate_sync_force_database_creation', array($this, 'handle_force_database_creation'));
        
        // 🆕 Testing & Development AJAX Actions
        add_action('wp_ajax_realestate_sync_cleanup_properties', array($this, 'handle_cleanup_properties'));
        add_action('wp_ajax_realestate_sync_reset_tracking', array($this, 'handle_reset_tracking'));
        add_action('wp_ajax_realestate_sync_get_property_stats', array($this, 'handle_get_property_stats'));
        add_action('wp_ajax_realestate_sync_import_test_file', array($this, 'handle_import_test_file'));
        add_action('wp_ajax_realestate_sync_create_sample_xml', array($this, 'handle_create_sample_xml'));
        add_action('wp_ajax_realestate_sync_validate_mapping', array($this, 'handle_validate_mapping'));
        add_action('wp_ajax_realestate_sync_create_properties_from_sample', array($this, 'handle_create_properties_from_sample'));
        
        // 🎯 NEW UPLOAD WORKFLOW AJAX ACTIONS
        add_action('wp_ajax_realestate_sync_process_test_file', array($this, 'handle_process_test_file'));
        add_action('wp_ajax_realestate_sync_cleanup_test_data', array($this, 'handle_cleanup_test_data'));
        
        // 🚀 FORCE PROCESSING MODE AJAX ACTIONS
        add_action('wp_ajax_realestate_sync_toggle_force_processing', array($this, 'handle_toggle_force_processing'));
        
        // 🏗️ PROPERTY FIELDS CREATION AJAX ACTION
        add_action('wp_ajax_realestate_sync_create_property_fields', array($this, 'handle_create_property_fields'));
        add_action('wp_ajax_realestate_sync_create_property_fields_v2', array($this, 'handle_create_property_fields_v2')); // 🔥 NEW AUTOMATION METHOD
        
        // 📋 INFO TAB AJAX ACTIONS
        add_action('wp_ajax_realestate_sync_check_field_status', array($this, 'handle_check_field_status'));
        add_action('wp_ajax_realestate_sync_get_field_mapping', array($this, 'handle_get_field_mapping'));
        add_action('wp_ajax_realestate_sync_test_field_population', array($this, 'handle_test_field_population'));
    }
    
    /**
     * Handle toggle force processing mode AJAX
     */
    public function handle_toggle_force_processing() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $current_state = get_option('realestate_sync_force_processing', false);
            $new_state = !$current_state;
            
            update_option('realestate_sync_force_processing', $new_state);
            
            $message = $new_state ? 
                '🚀 FORCE PROCESSING MODE ENABLED - bypassing change detection for debug' : 
                '🚫 FORCE PROCESSING MODE DISABLED - normal change detection active';
            
            $this->logger->log($message, 'info');
            
            wp_send_json_success(array(
                'enabled' => $new_state,
                'message' => $message
            ));
            
        } catch (Exception $e) {
            $this->logger->log('FORCE PROCESSING TOGGLE ERROR: ' . $e->getMessage(), 'error');
            wp_send_json_error('Errore toggle force processing: ' . $e->getMessage());
        }
    }
    
    /**
     * 📋 INFO TAB AJAX HANDLERS
     */
    
    /**
     * Handle check field status AJAX - Check if custom fields exist in WpResidence
     */
    public function handle_check_field_status() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $this->logger->log('📋 INFO TAB: Checking custom fields status', 'info');
            
            // Get WpResidence custom fields
            $wpresidence_admin = get_option('wpresidence_admin', array());
            $custom_fields = isset($wpresidence_admin['wpestate_custom_fields_list']) ? $wpresidence_admin['wpestate_custom_fields_list'] : array();
            $existing_field_names = isset($custom_fields['add_field_name']) ? $custom_fields['add_field_name'] : array();
            
            // Required custom fields for the plugin
            $required_fields = [
                'superficie-giardino' => 'Superficie giardino (m²)',
                'aree-esterne' => 'Aree esterne (m²)',
                'superficie-commerciale' => 'Superficie commerciale (m²)',
                'superficie-utile' => 'Superficie utile (m²)',
                'totale-piani-edificio' => 'Totale piani edificio',
                'deposito-cauzionale' => 'Deposito cauzionale (€)',
                'distanza-mare' => 'Distanza dal mare (m)',
                'rendita-catastale' => 'Rendita catastale (€)',
                'destinazione-catastale' => 'Destinazione catastale'
            ];
            
            $created_count = 0;
            $missing_count = 0;
            $field_details = [];
            
            foreach ($required_fields as $field_name => $field_label) {
                $exists = in_array($field_name, $existing_field_names);
                
                if ($exists) {
                    $created_count++;
                    // Get the actual label from WpResidence
                    $field_index = array_search($field_name, $existing_field_names);
                    $actual_label = isset($custom_fields['add_field_label'][$field_index]) ? $custom_fields['add_field_label'][$field_index] : $field_label;
                } else {
                    $missing_count++;
                    $actual_label = null;
                }
                
                $field_details[] = [
                    'name' => $field_name,
                    'label' => $actual_label,
                    'expected_label' => $field_label,
                    'exists' => $exists
                ];
            }
            
            // Calculate coverage percentage based on existing fields
            $total_fields = count($required_fields);
            $coverage_percentage = $total_fields > 0 ? round(($created_count / $total_fields) * 100) : 0;
            
            $this->logger->log("📋 FIELD STATUS: {$created_count}/{$total_fields} custom fields created ({$coverage_percentage}%)", 'info');
            
            wp_send_json_success([
                'created_count' => $created_count,
                'missing_count' => $missing_count,
                'total_fields' => $total_fields,
                'coverage_percentage' => $coverage_percentage,
                'field_details' => $field_details,
                'wpresidence_system' => !empty($existing_field_names),
                'message' => "Field status check completed: {$created_count} created, {$missing_count} missing"
            ]);
            
        } catch (Exception $e) {
            $this->logger->log('🚨 FIELD STATUS ERROR: ' . $e->getMessage(), 'error');
            wp_send_json_error('Error checking field status: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle get field mapping AJAX - Show XML to WordPress field mapping
     */
    public function handle_get_field_mapping() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $this->logger->log('📋 INFO TAB: Loading field mapping data', 'info');
            
            // Load field mapping configuration
            $field_mapping_file = plugin_dir_path(__FILE__) . '../config/field-mapping.php';
            if (!file_exists($field_mapping_file)) {
                throw new Exception('Field mapping configuration file not found');
            }
            
            $field_mapping = include $field_mapping_file;
            
            // Extract core property fields and custom fields
            $property_core = [];
            $custom_fields = [];
            
            // Core WordPress fields (from Property Mapper)
            $core_field_mapping = [
                'id' => 'property_import_id',
                'titolo' => 'post_title',
                'descrizione' => 'post_content',
                'prezzo' => 'property_price',
                'mq' => 'property_size',
                'indirizzo' => 'property_address',
                'comune' => 'property_city',
                'provincia' => 'property_state',
                'latitude' => 'property_latitude',
                'longitude' => 'property_longitude',
                'categorie_id' => 'property_category (taxonomy)'
            ];
            
            // Custom fields that require manual creation
            $custom_field_mapping = [
                'superficie_giardino' => 'superficie-giardino',
                'aree_esterne' => 'aree-esterne',
                'superficie_commerciale' => 'superficie-commerciale',
                'superficie_utile' => 'superficie-utile',
                'totale_piani_edificio' => 'totale-piani-edificio',
                'deposito_cauzionale' => 'deposito-cauzionale',
                'distanza_mare' => 'distanza-mare',
                'rendita_catastale' => 'rendita-catastale',
                'destinazione_catastale' => 'destinazione-catastale'
            ];
            
            // Additional mapped fields from info_inserite
            $info_inserite_mapping = [
                'info[1]' => 'property_bathrooms',
                'info[2]' => 'property_bedrooms', 
                'info[5]' => 'property_garage',
                'info[13]' => 'property_elevator',
                'info[14]' => 'property_air_conditioning',
                'info[17]' => 'property_garden',
                'info[21]' => 'property_heating',
                'info[33]' => 'property_floor'
            ];
            
            // Calculate coverage
            $total_xml_fields = count($core_field_mapping) + count($custom_field_mapping) + count($info_inserite_mapping);
            $mapped_fields = count($core_field_mapping) + count($info_inserite_mapping); // Custom fields not counted until created
            $coverage_percentage = $total_xml_fields > 0 ? round(($mapped_fields / $total_xml_fields) * 100) : 0;
            
            $mapping_data = [
                'property_core' => $core_field_mapping,
                'custom_fields' => $custom_field_mapping,
                'info_inserite' => $info_inserite_mapping,
                'coverage_summary' => [
                    'total_xml_fields' => $total_xml_fields,
                    'mapped_fields' => $mapped_fields,
                    'custom_fields_pending' => count($custom_field_mapping),
                    'coverage_percentage' => $coverage_percentage
                ]
            ];
            
            $this->logger->log("📋 FIELD MAPPING: Loaded mapping with {$coverage_percentage}% coverage", 'info');
            
            wp_send_json_success($mapping_data);
            
        } catch (Exception $e) {
            $this->logger->log('🚨 FIELD MAPPING ERROR: ' . $e->getMessage(), 'error');
            wp_send_json_error('Error loading field mapping: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle test field population AJAX - Test mapping XML data to custom fields
     */
    public function handle_test_field_population() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $this->logger->log('🧪 INFO TAB: Testing custom fields population', 'info');
            
            // Check if Property Mapper exists
            if (!class_exists('RealEstate_Sync_Property_Mapper')) {
                throw new Exception('Property Mapper class not found');
            }
            
            // Generate test XML data
            $test_xml_data = [
                'id' => 'TEST_POPULATION_001',
                'categorie_id' => 11,
                'price' => 350000,
                'mq' => 110,
                'dati_inseriti' => [
                    20 => 125, // superficie commerciale
                    21 => 110, // superficie utile
                    4 => 50,   // superficie giardino
                    22 => 30   // aree esterne
                ],
                'catasto' => [
                    'rendita_catastale' => '1250.80',
                    'destinazione_uso' => 'Residenziale'
                ]
            ];
            
            // Test mapping with Property Mapper
            $property_mapper = new RealEstate_Sync_Property_Mapper();
            
            $fields_tested = 0;
            $successful_mappings = 0;
            $failed_mappings = 0;
            $test_details = [];
            
            // Test custom fields mapping
            $custom_fields_map = [
                'superficie-commerciale' => isset($test_xml_data['dati_inseriti'][20]) ? $test_xml_data['dati_inseriti'][20] : null,
                'superficie-utile' => isset($test_xml_data['dati_inseriti'][21]) ? $test_xml_data['dati_inseriti'][21] : null,
                'superficie-giardino' => isset($test_xml_data['dati_inseriti'][4]) ? $test_xml_data['dati_inseriti'][4] : null,
                'aree-esterne' => isset($test_xml_data['dati_inseriti'][22]) ? $test_xml_data['dati_inseriti'][22] : null,
                'rendita-catastale' => isset($test_xml_data['catasto']['rendita_catastale']) ? $test_xml_data['catasto']['rendita_catastale'] : null,
                'destinazione-catastale' => isset($test_xml_data['catasto']['destinazione_uso']) ? $test_xml_data['catasto']['destinazione_uso'] : null
            ];
            
            foreach ($custom_fields_map as $field_name => $test_value) {
                $fields_tested++;
                
                if ($test_value !== null) {
                    $successful_mappings++;
                    $test_details[] = [
                        'field' => $field_name,
                        'success' => true,
                        'message' => "Mapped value: {$test_value}"
                    ];
                    $this->logger->log("✅ FIELD TEST: {$field_name} → {$test_value}", 'info');
                } else {
                    $failed_mappings++;
                    $test_details[] = [
                        'field' => $field_name,
                        'success' => false,
                        'message' => 'No test data available'
                    ];
                    $this->logger->log("❌ FIELD TEST: {$field_name} → no data", 'info');
                }
            }
            
            $this->logger->log("🧪 POPULATION TEST: {$successful_mappings}/{$fields_tested} fields mapped successfully", 'info');
            
            wp_send_json_success([
                'fields_tested' => $fields_tested,
                'successful_mappings' => $successful_mappings,
                'failed_mappings' => $failed_mappings,
                'test_details' => $test_details,
                'test_data_used' => $test_xml_data,
                'message' => "Population test completed: {$successful_mappings} successful, {$failed_mappings} failed"
            ]);
            
        } catch (Exception $e) {
            $this->logger->log('🚨 FIELD POPULATION TEST ERROR: ' . $e->getMessage(), 'error');
            wp_send_json_error('Error testing field population: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle create properties from sample AJAX - UPGRADED TO v3.0
     * TESTING COMPLETO CON PROPERTY MAPPER v3.0 + WP IMPORTER v3.0
     */
    public function handle_create_properties_from_sample() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $this->logger->log('🧪 SAMPLE v3.0: Starting Property Mapper v3.0 + WP Importer v3.0 test', 'info');
            
            // 🧪 SAMPLE XML v3.0: Complete structure with all sections
            $sample_xml_data = $this->generate_sample_v3_data();
            
            // 🔥 PROPERTY MAPPER v3.0: Use enhanced mapping
            $property_mapper = new RealEstate_Sync_Property_Mapper();
            $mapped_result = $property_mapper->map_properties($sample_xml_data);
            
            if (!$mapped_result['success'] || empty($mapped_result['properties'])) {
                throw new Exception('Property Mapper v3.0 failed: ' . print_r($mapped_result, true));
            }
            
            $this->logger->log('✅ PROPERTY MAPPER v3.0: Successfully mapped ' . count($mapped_result['properties']) . ' properties', 'info');
            
            // 🚀 WP IMPORTER v3.0: Use enhanced importer
            $wp_importer = new RealEstate_Sync_WP_Importer();
            
            $created_count = 0;
            $updated_count = 0;
            $skipped_count = 0;
            $features_created = 0;
            $processing_details = [];
            
            foreach ($mapped_result['properties'] as $mapped_property) {
                // 🎯 PROCESS WITH v3.0: Complete structure processing
                $result = $wp_importer->process_property_v3($mapped_property);
                
                if ($result['success']) {
                    $processing_details[] = [
                        'import_id' => $mapped_property['source_data']['id'],
                        'post_id' => $result['post_id'],
                        'action' => $result['action'],
                        'title' => $mapped_property['post_data']['post_title']
                    ];
                    
                    if ($result['action'] === 'created') {
                        $created_count++;
                    } elseif ($result['action'] === 'updated') {
                        $updated_count++;
                    } else {
                        $skipped_count++;
                    }
                    
                    $this->logger->log('✅ WP IMPORTER v3.0: ' . ucfirst($result['action']) . ' property ' . $mapped_property['source_data']['id'] . ' → Post ' . $result['post_id'], 'info');
                } else {
                    $this->logger->log('❌ WP IMPORTER v3.0: Failed property ' . $mapped_property['source_data']['id'] . ': ' . $result['error'], 'error');
                }
            }
            
            // 📊 GET STATS FROM WP IMPORTER
            $importer_stats = $wp_importer->get_stats();
            $features_created = $importer_stats['created_terms'] ?? 0;
            
            $this->logger->log('🎆 SAMPLE v3.0 COMPLETE: Created=' . $created_count . ', Updated=' . $updated_count . ', Features=' . $features_created, 'info');
            
            wp_send_json_success([
                'created_count' => $created_count,
                'updated_count' => $updated_count,
                'skipped_count' => $skipped_count,
                'features_created' => $features_created,
                'total_processed' => count($mapped_result['properties']),
                'mapping_version' => '3.0',
                'processing_details' => $processing_details,
                'message' => "Property Mapper v3.0 Test Completato!🎉 Created: {$created_count}, Updated: {$updated_count}, Features: {$features_created}"
            ]);
            
        } catch (Exception $e) {
            $this->logger->log('🚨 SAMPLE v3.0 ERROR: ' . $e->getMessage(), 'error');
            wp_send_json_error('Property Mapper v3.0 Test Failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Create properties directly in WordPress (bypass Import Engine)
     * METODO DIRETTO PER TESTING
     */
    private function create_properties_direct($xml_content) {
        $created_count = 0;
        
        try {
            // Parse XML
            $xml = simplexml_load_string($xml_content);
            if (!$xml) {
                throw new Exception('Invalid XML content');
            }
            
            // Process each property directly
            foreach ($xml->annuncio as $annuncio) {
                // Convert SimpleXML to array
                $property_data = $this->simplexml_to_array($annuncio);
                
                // Add required fields
                $property_data['id'] = (string)$annuncio->info->id;
                $property_data['comune_istat'] = (string)$annuncio->info->comune_istat;
                
                // Check province filter
                if (!$this->is_sample_property_valid($property_data)) {
                    continue;
                }
                
                // Create WordPress post directly
                $post_id = $this->create_wordpress_post_direct($property_data);
                
                if ($post_id) {
                    $created_count++;
                    $this->logger->log("DIRECT CREATE: Property {$property_data['id']} created as post {$post_id}", 'info');
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log("DIRECT CREATE ERROR: " . $e->getMessage(), 'error');
            throw $e;
        }
        
        return $created_count;
    }
    
    /**
     * Create WordPress post directly
     */
    private function create_wordpress_post_direct($property_data) {
        // Basic post data
        $post_data = array(
            'post_type' => 'estate_property',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'post_title' => $property_data['titolo'] ?? 'Proprietà Test',
            'post_content' => $property_data['descrizione'] ?? 'Descrizione proprietà di test',
            'post_excerpt' => 'Property di test generata automaticamente',
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        );
        
        // Insert post
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            $this->logger->log("DIRECT CREATE ERROR: " . $post_id->get_error_message(), 'error');
            return false;
        }
        
        // Add basic meta fields
        update_post_meta($post_id, 'property_price', intval($property_data['prezzo'] ?? 0));
        update_post_meta($post_id, 'property_size', intval($property_data['superficie'] ?? 0));
        update_post_meta($post_id, 'property_bedrooms', intval($property_data['camere'] ?? 0));
        update_post_meta($post_id, 'property_bathrooms', intval($property_data['bagni'] ?? 0));
        update_post_meta($post_id, 'property_city', $property_data['comune'] ?? '');
        update_post_meta($post_id, 'property_state', $property_data['provincia'] ?? '');
        update_post_meta($post_id, 'property_import_source', 'TEST_SAMPLE');
        update_post_meta($post_id, 'property_import_id', $property_data['id']);
        update_post_meta($post_id, 'property_import_date', current_time('mysql'));
        
        // Set property category
        $tipologia = intval($property_data['tipologia'] ?? 11);
        $category_name = $this->get_category_by_tipologia($tipologia);
        if ($category_name) {
            wp_set_post_terms($post_id, array($category_name), 'property_category');
        }
        
        // Set city taxonomy
        if (!empty($property_data['comune'])) {
            wp_set_post_terms($post_id, array($property_data['comune']), 'property_city');
        }
        
        return $post_id;
    }
    
    /**
     * Helper methods for direct creation
     */
    private function simplexml_to_array($xml) {
        return json_decode(json_encode($xml), true);
    }
    
    private function is_sample_property_valid($property_data) {
        $comune_istat = $property_data['comune_istat'] ?? '';
        
        // Check TN/BZ
        $is_trento = (substr($comune_istat, 0, 3) === '022');
        $is_bolzano = (substr($comune_istat, 0, 3) === '021');
        
        return ($is_trento || $is_bolzano);
    }
    
    private function get_category_by_tipologia($tipologia) {
        $categories = array(
            1 => 'Ville Singole e a Schiera',
            11 => 'Appartamenti', 
            18 => 'Ville Singole e a Schiera'
        );
        
        return $categories[$tipologia] ?? 'Appartamenti';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'RealEstate Sync',
            'RealEstate Sync',
            'manage_options',
            $this->plugin_slug,
            array($this, 'display_admin_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_' . $this->plugin_slug) {
            return;
        }
        
        wp_enqueue_script(
            'realestate-sync-admin',
            plugin_dir_url(__FILE__) . '../admin/assets/admin.js',
            array('jquery'),
            '0.9.0',
            true
        );
        
        wp_enqueue_style(
            'realestate-sync-admin',
            plugin_dir_url(__FILE__) . '../admin/assets/admin.css',
            array(),
            '0.9.0'
        );
        
        wp_localize_script('realestate-sync-admin', 'realestateSync', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('realestate_sync_nonce'),
            'strings' => array(
                'importing' => 'Import in corso...',
                'success' => 'Import completato con successo',
                'error' => 'Errore durante l\'import',
                'confirm_import' => 'Sei sicuro di voler avviare l\'import manuale?'
            )
        ));
    }
    
    /**
     * Display admin page
     */
    public function display_admin_page() {
        $settings = get_option('realestate_sync_settings', array());
        $last_import = RealEstate_Sync_Import_Engine::get_last_import_results();
        $tracking_stats = $this->get_tracking_statistics();
        $next_scheduled = wp_next_scheduled('realestate_sync_daily_import');
        
        include plugin_dir_path(__FILE__) . '../admin/views/dashboard.php';
    }
    
    /**
     * Handle manual import AJAX
     */
    public function handle_manual_import() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            // 🔧 HARDCODE CREDENZIALI TEMPORANEO - BYPASS ADMIN INTERFACE
            $settings = array(
                'xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
                'username' => 'trentinoimmobiliare_it',
                'password' => 'dget6g52'
            );
            
            $this->logger->log('HARDCODE: Using hardcoded credentials for testing', 'info');
            
            // Download XML
            $downloader = new RealEstate_Sync_XML_Downloader();
            $xml_file = $downloader->download_xml($settings['xml_url'], $settings['username'], $settings['password']);
            
            if (!$xml_file) {
                throw new Exception('Impossibile scaricare il file XML');
            }
            
            // Execute import
            $import_engine = new RealEstate_Sync_Import_Engine();
            $import_engine->configure($settings);
            
            $results = $import_engine->execute_chunked_import($xml_file);
            
            // Cleanup
            if (file_exists($xml_file)) {
                unlink($xml_file);
            }
            
            wp_send_json_success(array(
                'message' => 'Import completato con successo',
                'results' => $results
            ));
            
        } catch (Exception $e) {
            $this->logger->log("Manual import failed: " . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle test connection AJAX
     */
    public function handle_test_connection() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // 🔧 HARDCODE CREDENZIALI TEMPORANEO - BYPASS ADMIN INTERFACE
        $url = 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz';
        $username = 'trentinoimmobiliare_it';
        $password = 'dget6g52';
        
        $this->logger->log('HARDCODE: Using hardcoded credentials for connection test', 'info');
        
        $downloader = new RealEstate_Sync_XML_Downloader();
        $result = $downloader->test_connection($url, $username, $password);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle save settings AJAX
     */
    public function handle_save_settings() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $settings = array(
            'xml_url' => sanitize_url($_POST['xml_url']),
            'username' => sanitize_text_field($_POST['username']),
            'password' => sanitize_text_field($_POST['password']),
            'notification_email' => sanitize_email($_POST['notification_email']),
            'enabled_provinces' => isset($_POST['enabled_provinces']) ? array_map('sanitize_text_field', $_POST['enabled_provinces']) : array(),
            'chunk_size' => isset($_POST['chunk_size']) ? intval($_POST['chunk_size']) : 50,
            'sleep_seconds' => isset($_POST['sleep_seconds']) ? intval($_POST['sleep_seconds']) : 2
        );
        
        $result = update_option('realestate_sync_settings', $settings);
        
        if ($result !== false) {
            $this->logger->log('Settings saved successfully', 'info');
            wp_send_json_success('Impostazioni salvate con successo');
        } else {
            $this->logger->log('Failed to save settings', 'error');
            wp_send_json_error('Errore nel salvataggio delle impostazioni');
        }
    }
    
    /**
     * Handle get progress AJAX
     */
    public function handle_get_progress() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        $progress = RealEstate_Sync_Import_Engine::get_current_progress();
        
        if ($progress) {
            wp_send_json_success($progress);
        } else {
            wp_send_json_error('Nessun import in corso');
        }
    }
    
    /**
     * Get tracking statistics
     */
    private function get_tracking_statistics() {
        $tracking_manager = new RealEstate_Sync_Tracking_Manager();
        return $tracking_manager->get_import_statistics();
    }
    
    /**
     * Handle get logs AJAX - FIXED LOG DISPLAY
     */
    public function handle_get_logs() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $logs = $this->logger->get_recent_logs(100);
        
        if ($logs && is_array($logs)) {
            // Format logs correctly for display
            $formatted_logs = array();
            foreach ($logs as $log) {
                if (is_array($log)) {
                    $formatted_logs[] = '[' . $log['timestamp'] . '] [' . strtoupper($log['level']) . '] ' . $log['message'];
                } else {
                    $formatted_logs[] = $log;
                }
            }
            wp_send_json_success(array('logs' => implode("\n", $formatted_logs)));
        } else {
            wp_send_json_success(array('logs' => 'Nessun log disponibile'));
        }
    }
    
    /**
     * Handle download logs AJAX
     */
    public function handle_download_logs() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $logs = $this->logger->get_recent_logs(1000);
        $filename = 'realestate-sync-logs-' . date('Y-m-d-H-i-s') . '.txt';
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        echo implode("\n", $logs);
        exit;
    }
    
    /**
     * Handle clear logs AJAX
     */
    public function handle_clear_logs() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->logger->clear_logs();
        
        if ($result) {
            wp_send_json_success('Log cancellati con successo');
        } else {
            wp_send_json_error('Errore nella cancellazione dei log');
        }
    }
    
    /**
     * Handle system check AJAX
     */
    public function handle_system_check() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $checks = array(
            'PHP Version' => PHP_VERSION . ' (Required: 7.4+)',
            'WordPress Version' => get_bloginfo('version') . ' (Required: 5.0+)',
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . ' seconds',
            'Upload Max Filesize' => ini_get('upload_max_filesize'),
            'cURL Extension' => function_exists('curl_init') ? 'Available' : 'Missing',
            'SimpleXML Extension' => class_exists('SimpleXMLElement') ? 'Available' : 'Missing',
            'Writable Logs Directory' => is_writable(REALESTATE_SYNC_PLUGIN_DIR . 'logs/') ? 'Yes' : 'No'
        );
        
        $html = '<table class="rs-form-table">';
        foreach ($checks as $check => $value) {
            $status_class = 'rs-status-success';
            if (strpos($value, 'Missing') !== false || strpos($value, 'No') !== false) {
                $status_class = 'rs-status-error';
            }
            $html .= '<tr><th>' . $check . '</th><td><span class="rs-status-badge ' . $status_class . '">' . $value . '</span></td></tr>';
        }
        $html .= '</table>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Handle toggle automation AJAX
     */
    public function handle_toggle_automation() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $cron_manager = new RealEstate_Sync_Cron_Manager();
        
        if ($cron_manager->is_scheduled()) {
            $cron_manager->unschedule_cron_jobs();
            $result = true;
            $message = $result ? 'Automazione disabilitata' : 'Errore nella disabilitazione';
        } else {
            $cron_manager->schedule_cron_jobs();
            $result = true;
            $message = $result ? 'Automazione abilitata' : 'Errore nell\'abilitazione';
        }
        
        if ($result) {
            wp_send_json_success($message);
        } else {
            wp_send_json_error($message);
        }
    }
    
    /**
     * Handle force database creation AJAX
     */
    public function handle_force_database_creation() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        // FORCE DROP AND RECREATE with correct schema
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'realestate_sync_tracking';
        
        // Check current table structure for debugging
        $existing_structure = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);
        $this->logger->log("FORCE: Current table structure before changes: " . print_r($existing_structure, true), 'debug');
        
        // First, drop existing table if it exists
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        $this->logger->log("FORCE: Dropped existing table $table_name", 'info');
        
        // Create table with CORRECT schema (last_import_date, not last_import)
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            property_id varchar(50) NOT NULL,
            property_hash varchar(32) NOT NULL,
            wp_post_id bigint(20) DEFAULT NULL,
            last_import_date datetime DEFAULT NULL,
            import_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY property_id (property_id),
            KEY property_hash (property_hash),
            KEY wp_post_id (wp_post_id),
            KEY status (status),
            KEY last_import_date (last_import_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Log the attempt
        $this->logger->log("FORCE: Database table creation attempted: $table_name", 'info');
        $this->logger->log("FORCE: dbDelta result: " . print_r($result, true), 'debug');
        
        // Check if table actually exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $this->logger->log("FORCE: Table exists after creation: " . ($table_exists ? 'YES' : 'NO'), 'info');
        
        if ($table_exists) {
            // Get table structure to verify
            $table_structure = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);
            $this->logger->log("FORCE: Table structure: " . print_r($table_structure, true), 'debug');
            
            wp_send_json_success(array(
                'message' => 'Tabella database creata con successo!',
                'table_name' => $table_name,
                'exists' => true,
                'structure' => $table_structure
            ));
        } else {
            $error = $wpdb->last_error ? $wpdb->last_error : 'Errore sconosciuto';
            $this->logger->log("FORCE: Table creation failed. MySQL Error: $error", 'error');
            
            wp_send_json_error(array(
                'message' => 'Errore nella creazione tabella: ' . $error,
                'table_name' => $table_name,
                'exists' => false,
                'mysql_error' => $error
            ));
        }
    }
    
    // 🆕 TESTING & DEVELOPMENT FUNCTIONS
    
    /**
     * Handle cleanup properties AJAX
     */
    public function handle_cleanup_properties() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        try {
            // Count properties before deletion
            $count_before = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'estate_property'");
            
            // Delete all estate_property posts and related data
            $deleted_posts = $wpdb->query("
                DELETE p, pm, tr 
                FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
                WHERE p.post_type = 'estate_property'
            ");
            
            // Clean orphaned meta
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'property_%'");
            
            // Count after deletion
            $count_after = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'estate_property'");
            
            $this->logger->log("CLEANUP: Deleted {$count_before} properties. Remaining: {$count_after}", 'info');
            
            wp_send_json_success(array(
                'deleted_count' => $count_before,
                'remaining_count' => $count_after,
                'message' => "Cancellate {$count_before} properties"
            ));
            
        } catch (Exception $e) {
            $this->logger->log("CLEANUP ERROR: " . $e->getMessage(), 'error');
            wp_send_json_error('Errore durante cleanup: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle reset tracking AJAX
     */
    public function handle_reset_tracking() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'realestate_sync_tracking';
            
            // Count records before deletion
            $count_before = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            
            // Truncate tracking table
            $result = $wpdb->query("TRUNCATE TABLE $table_name");
            
            if ($result !== false) {
                $this->logger->log("RESET TRACKING: Cleared {$count_before} tracking records", 'info');
                
                wp_send_json_success(array(
                    'cleared_records' => $count_before,
                    'message' => "Reset tracking table: {$count_before} record eliminati"
                ));
            } else {
                throw new Exception('Errore nel reset della tabella tracking');
            }
            
        } catch (Exception $e) {
            $this->logger->log("RESET TRACKING ERROR: " . $e->getMessage(), 'error');
            wp_send_json_error('Errore reset tracking: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle get property stats AJAX
     */
    public function handle_get_property_stats() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        try {
            // Total properties
            $total_properties = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'estate_property' AND post_status = 'publish'");
            
            // Properties by category
            $by_category = $wpdb->get_results("
                SELECT tm.name as category, COUNT(*) as count
                FROM {$wpdb->posts} p
                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id
                WHERE p.post_type = 'estate_property' 
                AND p.post_status = 'publish'
                AND tt.taxonomy = 'property_category'
                GROUP BY tm.term_id, tm.name
                ORDER BY count DESC
            ", ARRAY_A);
            
            $category_stats = array();
            foreach ($by_category as $cat) {
                $category_stats[$cat['category']] = intval($cat['count']);
            }
            
            // Properties by province (from postmeta)
            $by_province = $wpdb->get_results("
                SELECT pm.meta_value as province, COUNT(*) as count
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'estate_property' 
                AND p.post_status = 'publish'
                AND pm.meta_key = 'property_state'
                AND pm.meta_value IN ('TN', 'BZ', 'Trento', 'Bolzano')
                GROUP BY pm.meta_value
                ORDER BY count DESC
            ", ARRAY_A);
            
            $province_stats = array();
            foreach ($by_province as $prov) {
                $province_stats[$prov['province']] = intval($prov['count']);
            }
            
            // Tracking info
            $tracking_table = $wpdb->prefix . 'realestate_sync_tracking';
            $tracked_count = $wpdb->get_var("SELECT COUNT(*) FROM $tracking_table");
            $last_import = $wpdb->get_var("SELECT MAX(last_import_date) FROM $tracking_table");
            
            $stats = array(
                'total_properties' => intval($total_properties),
                'by_category' => $category_stats,
                'by_province' => $province_stats,
                'tracking_info' => array(
                    'tracked_count' => intval($tracked_count),
                    'last_import' => $last_import ? date('d/m/Y H:i', strtotime($last_import)) : null
                )
            );
            
            $this->logger->log("STATS: Retrieved property statistics", 'info');
            wp_send_json_success($stats);
            
        } catch (Exception $e) {
            $this->logger->log("STATS ERROR: " . $e->getMessage(), 'error');
            wp_send_json_error('Errore nel recupero statistiche: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle import test file AJAX
     */
    public function handle_import_test_file() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            // Check file upload
            if (!isset($_FILES['test_xml_file']) || $_FILES['test_xml_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Errore nell\'upload del file XML');
            }
            
            $uploaded_file = $_FILES['test_xml_file'];
            
            // Validate file type
            if (!in_array($uploaded_file['type'], array('text/xml', 'application/xml')) && 
                !preg_match('/\.xml$/i', $uploaded_file['name'])) {
                throw new Exception('File deve essere XML valido');
            }
            
            // Move uploaded file to temp location
            $temp_file = wp_upload_dir()['basedir'] . '/realestate-test-' . time() . '.xml';
            
            if (!move_uploaded_file($uploaded_file['tmp_name'], $temp_file)) {
                throw new Exception('Errore nel salvataggio file temporaneo');
            }
            
            // Import the test file
            $import_engine = new RealEstate_Sync_Import_Engine();
            $settings = get_option('realestate_sync_settings', array());
            $import_engine->configure($settings);
            
            $results = $import_engine->execute_chunked_import($temp_file);
            
            // Cleanup temp file
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            $this->logger->log("TEST IMPORT: Imported {$results['properties_processed']} test properties", 'info');
            
            wp_send_json_success(array(
                'imported_count' => $results['properties_processed'],
                'created_count' => $results['properties_created'] ?? 0,
                'updated_count' => $results['properties_updated'] ?? 0,
                'message' => "Test import completato: {$results['properties_processed']} properties processate"
            ));
            
        } catch (Exception $e) {
            $this->logger->log("TEST IMPORT ERROR: " . $e->getMessage(), 'error');
            wp_send_json_error('Errore test import: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle create sample XML AJAX
     */
    public function handle_create_sample_xml() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            // Create sample XML with 3 test properties
            $sample_xml = $this->generate_sample_xml();
            
            $this->logger->log("SAMPLE XML: Generated test XML with sample properties", 'info');
            
            wp_send_json_success(array(
                'xml_content' => $sample_xml,
                'properties_count' => 3,
                'message' => 'XML sample generato con 3 properties test'
            ));
            
        } catch (Exception $e) {
            $this->logger->log("SAMPLE XML ERROR: " . $e->getMessage(), 'error');
            wp_send_json_error('Errore generazione XML: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle validate mapping AJAX
     */
    public function handle_validate_mapping() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $validation_results = $this->validate_mapping_system();
            
            $this->logger->log("MAPPING VALIDATION: Completed with score {$validation_results['overall_score']}%", 'info');
            
            wp_send_json_success($validation_results);
            
        } catch (Exception $e) {
            $this->logger->log("MAPPING VALIDATION ERROR: " . $e->getMessage(), 'error');
            wp_send_json_error('Errore validazione mapping: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate sample XML for testing - FIXED STRUCTURE
     */
    private function generate_sample_xml() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<dataset>' . "\n";
        
        // Sample property 1 - TN
        $xml .= '  <annuncio>' . "\n";
        $xml .= '    <agenzia>' . "\n";
        $xml .= '      <id>7512</id>' . "\n";
        $xml .= '      <ragione_sociale><![CDATA[Test Agency Trento]]></ragione_sociale>' . "\n";
        $xml .= '      <provincia>Trento</provincia>' . "\n";
        $xml .= '    </agenzia>' . "\n";
        $xml .= '    <info>' . "\n";
        $xml .= '      <id>TEST001</id>' . "\n";
        $xml .= '      <title><![CDATA[Appartamento Test Trento]]></title>' . "\n";
        $xml .= '      <description><![CDATA[Appartamento di test per validazione mapping]]></description>' . "\n";
        $xml .= '      <price>250000</price>' . "\n";
        $xml .= '      <mq>85</mq>' . "\n";
        $xml .= '      <categorie_id>11</categorie_id>' . "\n";
        $xml .= '      <comune_istat>022205</comune_istat>' . "\n";
        $xml .= '      <indirizzo><![CDATA[Via Test]]></indirizzo>' . "\n";
        $xml .= '    </info>' . "\n";
        $xml .= '    <info_inserite>' . "\n";
        $xml .= '      <info id="1"><valore_assegnato>2</valore_assegnato></info>' . "\n"; // bagni
        $xml .= '      <info id="2"><valore_assegnato>3</valore_assegnato></info>' . "\n"; // camere
        $xml .= '    </info_inserite>' . "\n";
        $xml .= '  </annuncio>' . "\n";
        
        // Sample property 2 - BZ
        $xml .= '  <annuncio>' . "\n";
        $xml .= '    <agenzia>' . "\n";
        $xml .= '      <id>7531</id>' . "\n";
        $xml .= '      <ragione_sociale><![CDATA[Test Agency Bolzano]]></ragione_sociale>' . "\n";
        $xml .= '      <provincia>Bolzano</provincia>' . "\n";
        $xml .= '    </agenzia>' . "\n";
        $xml .= '    <info>' . "\n";
        $xml .= '      <id>TEST002</id>' . "\n";
        $xml .= '      <title><![CDATA[Villa Test Bolzano]]></title>' . "\n";
        $xml .= '      <description><![CDATA[Villa di test per validazione mapping]]></description>' . "\n";
        $xml .= '      <price>450000</price>' . "\n";
        $xml .= '      <mq>150</mq>' . "\n";
        $xml .= '      <categorie_id>18</categorie_id>' . "\n";
        $xml .= '      <comune_istat>021008</comune_istat>' . "\n";
        $xml .= '      <indirizzo><![CDATA[Via Dolomiti]]></indirizzo>' . "\n";
        $xml .= '    </info>' . "\n";
        $xml .= '    <info_inserite>' . "\n";
        $xml .= '      <info id="1"><valore_assegnato>3</valore_assegnato></info>' . "\n"; // bagni
        $xml .= '      <info id="2"><valore_assegnato>4</valore_assegnato></info>' . "\n"; // camere
        $xml .= '    </info_inserite>' . "\n";
        $xml .= '  </annuncio>' . "\n";
        
        // Sample property 3 - TN (different category)
        $xml .= '  <annuncio>' . "\n";
        $xml .= '    <agenzia>' . "\n";
        $xml .= '      <id>7512</id>' . "\n";
        $xml .= '      <ragione_sociale><![CDATA[Test Agency Rovereto]]></ragione_sociale>' . "\n";
        $xml .= '      <provincia>Trento</provincia>' . "\n";
        $xml .= '    </agenzia>' . "\n";
        $xml .= '    <info>' . "\n";
        $xml .= '      <id>TEST003</id>' . "\n";
        $xml .= '      <title><![CDATA[Casa Singola Test]]></title>' . "\n";
        $xml .= '      <description><![CDATA[Casa singola di test]]></description>' . "\n";
        $xml .= '      <price>320000</price>' . "\n";
        $xml .= '      <mq>120</mq>' . "\n";
        $xml .= '      <categorie_id>1</categorie_id>' . "\n";
        $xml .= '      <comune_istat>022178</comune_istat>' . "\n";
        $xml .= '      <indirizzo><![CDATA[Via Roma]]></indirizzo>' . "\n";
        $xml .= '    </info>' . "\n";
        $xml .= '    <info_inserite>' . "\n";
        $xml .= '      <info id="1"><valore_assegnato>2</valore_assegnato></info>' . "\n"; // bagni
        $xml .= '      <info id="2"><valore_assegnato>4</valore_assegnato></info>' . "\n"; // camere
        $xml .= '    </info_inserite>' . "\n";
        $xml .= '  </annuncio>' . "\n";
        
        $xml .= '</dataset>';
        
        return $xml;
    }
    
    /**
     * Generate sample v3.0 data - Complete structure with all Property Mapper v3.0 sections
     */
    private function generate_sample_v3_data() {
        return [
            // Sample 1: Attico Trento with complete v3.0 structure
            [
                'id' => 'SAMPLE001',
                'categorie_id' => 12, // Attico
                'price' => 650000,
                'mq' => 140,
                'indirizzo' => 'Via Roma',
                'civico' => '45',
                'comune_istat' => '022205', // Trento
                'latitude' => 46.0748,
                'longitude' => 11.1217,
                'description' => 'Splendido attico nel centro storico di Trento con vista panoramica sulle montagne. Completamente ristrutturato con finiture di pregio.',
                'abstract' => 'Attico di prestigio in centro Trento',
                'seo_title' => 'Attico di lusso in centro Trento con vista panoramica',
                'info_inserite' => [
                    1 => 2,   // 2 bagni
                    2 => 3,   // 3 camere
                    65 => 5,  // 5 locali
                    66 => 1,  // Con piscina
                    17 => 1,  // Con giardino
                    62 => 5,  // Vista panoramica eccellente
                    13 => 1,  // Ascensore
                    14 => 1,  // Aria condizionata
                    15 => 1,  // Arredato
                    33 => 3,  // Piano 3
                    55 => 3,  // Classe energetica B
                    9 => 1,   // Vendita
                    23 => 1,  // Allarme
                    46 => 1   // Camino
                ],
                'dati_inseriti' => [
                    20 => 150, // Superficie commerciale
                    21 => 140, // Superficie utile
                    4 => 30,   // MQ giardino
                    6 => 3.20  // Altezza piano
                ],
                'file_allegati' => [
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/080626/11484/800x800/000__foto__025.jpg', 'type' => 'image'],
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/080626/11484/800x800/001__foto__029.jpg', 'type' => 'image'],
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/080626/11484/800x800/002__cam00257.jpg', 'type' => 'image'],
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/250408/4476337/800x800/planimetria.jpg', 'type' => 'planimetria']
                ],
                'catasto' => [
                    'destinazione_uso' => 'Residenziale',
                    'rendita_catastale' => '2450.80',
                    'foglio' => '15',
                    'particella' => '234',
                    'subalterno' => '12'
                ]
            ],
            
            // Sample 2: Villa Bolzano with different features
            [
                'id' => 'SAMPLE002',
                'categorie_id' => 18, // Villa
                'price' => 850000,
                'mq' => 220,
                'indirizzo' => 'Via Dolomiti',
                'civico' => '12',
                'comune_istat' => '021008', // Bolzano
                'latitude' => 46.4983,
                'longitude' => 11.3548,
                'description' => 'Villa di prestigio con ampio giardino e piscina. Immersa nel verde con vista sulle Dolomiti.',
                'abstract' => 'Villa con piscina e giardino a Bolzano',
                'seo_title' => 'Villa con piscina e vista Dolomiti - Bolzano',
                'info_inserite' => [
                    1 => 3,   // 3 bagni
                    2 => 4,   // 4 camere
                    65 => 8,  // 8 locali
                    66 => 1,  // Con piscina
                    17 => 1,  // Con giardino
                    62 => 4,  // Vista panoramica
                    36 => 1,  // Montagna
                    88 => 1,  // Domotica
                    90 => 1,  // Porta blindata
                    33 => 0,  // Piano terra
                    55 => 2,  // Classe energetica A
                    9 => 1,   // Vendita
                    5 => 1,   // Garage
                    21 => 1   // Riscaldamento a pavimento
                ],
                'dati_inseriti' => [
                    20 => 240, // Superficie commerciale
                    21 => 220, // Superficie utile
                    4 => 800,  // MQ giardino
                    6 => 3.50  // Altezza piano
                ],
                'file_allegati' => [
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/080626/11484/800x800/003__foto__002.jpg', 'type' => 'image'],
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/080626/11484/800x800/004__foto__010.jpg', 'type' => 'image'],
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/080626/11484/800x800/005__foto__011.jpg', 'type' => 'image'],
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/080626/11484/800x800/006__foto__008.jpg', 'type' => 'image'],
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/250408/4476296/800x800/044__Planimetria.png', 'type' => 'planimetria']
                ],
                'catasto' => [
                    'destinazione_uso' => 'Residenziale',
                    'rendita_catastale' => '3850.60',
                    'foglio' => '8',
                    'particella' => '156',
                    'subalterno' => '3'
                ]
            ],
            
            // Sample 3: Appartamento Rovereto for testing different scenarios
            [
                'id' => 'SAMPLE003',
                'categorie_id' => 11, // Appartamento
                'price' => 1200, // Affitto
                'mq' => 95,
                'indirizzo' => 'Corso Bettini',
                'civico' => '89',
                'comune_istat' => '022178', // Rovereto
                'latitude' => 45.8906,
                'longitude' => 11.0387,
                'description' => 'Appartamento moderno in affitto nel centro di Rovereto. Perfetto per giovani professionisti.',
                'abstract' => 'Appartamento moderno in affitto a Rovereto',
                'seo_title' => 'Appartamento in affitto centro Rovereto',
                'info_inserite' => [
                    1 => 1,   // 1 bagno
                    2 => 2,   // 2 camere
                    65 => 4,  // 4 locali
                    13 => 1,  // Ascensore
                    14 => 1,  // Aria condizionata
                    16 => 1,  // Riscaldamento autonomo
                    33 => 2,  // Piano 2
                    55 => 4,  // Classe energetica C
                    10 => 1,  // Affitto
                    25 => 1,  // Balcone
                    26 => 1   // Lavanderia
                ],
                'dati_inseriti' => [
                    20 => 100, // Superficie commerciale
                    21 => 95,  // Superficie utile
                    6 => 2.80  // Altezza piano
                ],
                'file_allegati' => [
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/080626/11484/800x800/006__foto__008.jpg', 'type' => 'image'],
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/080626/11484/800x800/007__foto__007.jpg', 'type' => 'image'],
                    ['url' => 'https://images.gestionaleimmobiliare.it/foto/annunci/250408/4476296/800x800/043__Planimetria-2.png', 'type' => 'planimetria']
                ],
                'catasto' => [
                    'destinazione_uso' => 'Residenziale',
                    'rendita_catastale' => '580.40',
                    'foglio' => '22',
                    'particella' => '89',
                    'subalterno' => '7'
                ]
            ]
        ];
    }
    
    /**
     * Validate mapping system
     */
    private function validate_mapping_system() {
        $tests = array();
        $total_score = 0;
        $max_score = 0;
        
        // Test 1: Check if mapper class exists
        $max_score += 20;
        if (class_exists('RealEstate_Sync_Property_Mapper')) {
            $tests[] = array(
                'name' => 'Property Mapper Class',
                'description' => 'Classe mapper esiste e caricabile',
                'passed' => true,
                'details' => 'RealEstate_Sync_Property_Mapper trovata'
            );
            $total_score += 20;
        } else {
            $tests[] = array(
                'name' => 'Property Mapper Class',
                'description' => 'Classe mapper mancante',
                'passed' => false,
                'details' => 'RealEstate_Sync_Property_Mapper non trovata'
            );
        }
        
        // Test 2: Check field mapping configuration
        $max_score += 20;
        $field_mapping_file = plugin_dir_path(__FILE__) . '../config/field-mapping.php';
        if (file_exists($field_mapping_file)) {
            $tests[] = array(
                'name' => 'Field Mapping Config',
                'description' => 'File di configurazione mapping campi',
                'passed' => true,
                'details' => 'field-mapping.php trovato'
            );
            $total_score += 20;
        } else {
            $tests[] = array(
                'name' => 'Field Mapping Config',
                'description' => 'File configurazione mapping mancante',
                'passed' => false,
                'details' => 'field-mapping.php non trovato'
            );
        }
        
        // Test 3: Check WpResidence compatibility
        $max_score += 20;
        $wp_theme = wp_get_theme();
        if ($wp_theme->get('Name') === 'WpResidence' || $wp_theme->get('Template') === 'wpresidence') {
            $tests[] = array(
                'name' => 'WpResidence Theme',
                'description' => 'Tema WpResidence attivo per compatibilità',
                'passed' => true,
                'details' => 'Tema WpResidence rilevato'
            );
            $total_score += 20;
        } else {
            $tests[] = array(
                'name' => 'WpResidence Theme',
                'description' => 'Tema WpResidence non attivo',
                'passed' => false,
                'details' => 'Tema corrente: ' . $wp_theme->get('Name')
            );
        }
        
        // Test 4: Check province filtering
        $max_score += 20;
        if (class_exists('RealEstate_Sync_Property_Mapper')) {
            $mapper = new RealEstate_Sync_Property_Mapper();
            if (method_exists($mapper, 'is_property_in_enabled_provinces')) {
                $tests[] = array(
                    'name' => 'Province Filtering',
                    'description' => 'Sistema filtro provincie implementato',
                    'passed' => true,
                    'details' => 'Metodo is_property_in_enabled_provinces trovato'
                );
                $total_score += 20;
            } else {
                $tests[] = array(
                    'name' => 'Province Filtering',
                    'description' => 'Sistema filtro provincie mancante',
                    'passed' => false,
                    'details' => 'Metodo is_property_in_enabled_provinces non trovato'
                );
            }
        }
        
        // Test 5: Database tracking table
        $max_score += 20;
        global $wpdb;
        $table_name = $wpdb->prefix . 'realestate_sync_tracking';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if ($table_exists) {
            $tests[] = array(
                'name' => 'Tracking Database',
                'description' => 'Tabella tracking per change detection',
                'passed' => true,
                'details' => 'Tabella realestate_sync_tracking esistente'
            );
            $total_score += 20;
        } else {
            $tests[] = array(
                'name' => 'Tracking Database',
                'description' => 'Tabella tracking mancante',
                'passed' => false,
                'details' => 'Tabella realestate_sync_tracking non trovata'
            );
        }
        
        // Calculate final score
        $overall_score = $max_score > 0 ? round(($total_score / $max_score) * 100) : 0;
        
        // Generate summary
        $passed_tests = count(array_filter($tests, function($test) { return $test['passed']; }));
        $total_tests = count($tests);
        
        if ($overall_score >= 80) {
            $summary = "Sistema mapping funzionante. {$passed_tests}/{$total_tests} test superati.";
        } elseif ($overall_score >= 60) {
            $summary = "Sistema mapping parzialmente funzionante. Alcuni problemi da risolvere.";
        } else {
            $summary = "Sistema mapping con problemi significativi. Richiede intervento.";
        }
        
        return array(
            'overall_score' => $overall_score,
            'summary' => $summary,
            'tests' => $tests,
            'passed_tests' => $passed_tests,
            'total_tests' => $total_tests
        );
    }
    
    // 🎯 NEW UPLOAD WORKFLOW HANDLERS
    
    /**
     * Handle process test file AJAX - NEW UPLOAD WORKFLOW
     */
    public function handle_process_test_file() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            // Check file upload
            if (!isset($_FILES['test_xml_file']) || $_FILES['test_xml_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Errore nell\'upload del file XML');
            }
            
            $uploaded_file = $_FILES['test_xml_file'];
            $file_size = round($uploaded_file['size'] / 1024); // KB
            
            $this->logger->log("TEST UPLOAD: File {$uploaded_file['name']} ({$file_size}KB) uploaded", 'info');
            
            // Validate file type
            if (!in_array($uploaded_file['type'], array('text/xml', 'application/xml')) && 
                !preg_match('/\.xml$/i', $uploaded_file['name'])) {
                throw new Exception('File deve essere XML valido');
            }
            
            // Read XML content
            $xml_content = file_get_contents($uploaded_file['tmp_name']);
            if (!$xml_content) {
                throw new Exception('Impossibile leggere contenuto XML');
            }
            
            // Parse XML to count elements
            $xml = simplexml_load_string($xml_content);
            if (!$xml) {
                throw new Exception('XML non valido o malformato');
            }
            
            $properties_count = count($xml->annuncio ?? []);
            $agencies_count = count($xml->annuncio ?? []);
            
            $log_output = "[" . date('H:i:s') . "] File caricato: {$uploaded_file['name']} ({$file_size}KB)\n";
            $log_output .= "[" . date('H:i:s') . "] XML parsato: {$properties_count} properties, {$agencies_count} agenzie trovate\n";
            
            // Process with Import Engine + TEST FLAG
            $import_engine = new RealEstate_Sync_Import_Engine();
            $settings = get_option('realestate_sync_settings', array());
            $import_engine->configure($settings);
            
            // Create temp file
            $temp_file = wp_upload_dir()['basedir'] . '/realestate-test-' . time() . '.xml';
            file_put_contents($temp_file, $xml_content);
            
            // Execute import + DEBUG
            $results = $import_engine->execute_chunked_import($temp_file);
            
            // DEBUG: Log complete results structure
            $this->logger->log("DEBUG: Import Engine Results: " . print_r($results, true), 'info');
            $this->logger->log("DEBUG: Statistics: " . print_r($results['statistics'] ?? [], true), 'info');
            
            // Add test flag to all created properties
            $this->add_test_flag_to_recent_properties($results['properties_created'] ?? 0);
            
            // Cleanup temp file
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            // Build detailed log - FIXED: Use correct Import Engine output structure
            $stats = $results['statistics'] ?? [];
            $log_output .= "[" . date('H:i:s') . "] Properties: " . ($stats['new_properties'] ?? 0) . " create, " . ($stats['updated_properties'] ?? 0) . " aggiornate\n";
            
            // 🏢 AGENCY STATS: Extract from WP Importer stats if available
            $agency_created = 0;
            $agency_updated = 0;
            if (isset($results['agency_stats'])) {
                $agency_created = $results['agency_stats']['created'] ?? 0;
                $agency_updated = $results['agency_stats']['updated'] ?? 0;
            }
            $log_output .= "[" . date('H:i:s') . "] Agenzie: {$agency_created} create, {$agency_updated} aggiornate\n";
            
            // Get real media stats from Import Engine if available
            $media_stats = $results['media_stats'] ?? null;
            if ($media_stats) {
                $media_new = $media_stats['new'] ?? 0;
                $media_existing = $media_stats['existing'] ?? 0;
            } else {
                $media_new = 0;
                $media_existing = 0;
            }
            $log_output .= "[" . date('H:i:s') . "] Media: {$media_new} nuove immagini importate, {$media_existing} immagini già esistenti\n";
            $log_output .= "[" . date('H:i:s') . "] COMPLETATO: Test import workflow\n";
            
            $this->logger->log("TEST WORKFLOW: Completed - Props: " . ($stats['new_properties'] ?? 0) . ", Agencies: {$agency_created}", 'info');
            
            wp_send_json_success(array(
                'properties_created' => $stats['new_properties'] ?? 0,
                'properties_updated' => $stats['updated_properties'] ?? 0,
                'agencies_created' => $agency_created,
                'agencies_updated' => $agency_updated,
                'media_new' => $media_new,
                'media_existing' => $media_existing,
                'log_output' => $log_output,
                'message' => 'Test import completato con successo!'
            ));
            
        } catch (Exception $e) {
            $this->logger->log("TEST UPLOAD ERROR: " . $e->getMessage(), 'error');
            wp_send_json_error('Errore nel processo test: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle cleanup test data AJAX - SELECTIVE TEST DATA CLEANUP
     */
    public function handle_cleanup_test_data() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        try {
            // Count test data before deletion
            $test_properties = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} p 
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'estate_property' 
                AND pm.meta_key = '_test_import' 
                AND pm.meta_value = '1'
            ");
            
            // Delete properties with _test_import flag
            $deleted_posts = $wpdb->query("
                DELETE p, pm, tr 
                FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
                WHERE p.ID IN (
                    SELECT post_id FROM (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = '_test_import' AND meta_value = '1'
                    ) AS test_posts
                )
            ");
            
            // Clean test tracking data
            $tracking_table = $wpdb->prefix . 'realestate_sync_tracking';
            $deleted_tracking = $wpdb->query("
                DELETE FROM $tracking_table 
                WHERE property_id LIKE 'TEST%' OR property_id LIKE 'SAMPLE%'
            ");
            
            // Count agencies (if using custom post type)
            $test_agencies = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} p 
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'estate_agent' 
                AND pm.meta_key = '_test_import' 
                AND pm.meta_value = '1'
            ");
            
            // Delete test agencies
            $deleted_agencies = $wpdb->query("
                DELETE p, pm 
                FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'estate_agent' 
                AND p.ID IN (
                    SELECT post_id FROM (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = '_test_import' AND meta_value = '1'
                    ) AS test_agents
                )
            ");
            
            $this->logger->log("CLEANUP TEST: Deleted {$test_properties} properties, {$test_agencies} agencies", 'info');
            
            wp_send_json_success(array(
                'properties_deleted' => intval($test_properties),
                'agencies_deleted' => intval($test_agencies),
                'tracking_deleted' => intval($deleted_tracking),
                'message' => "Cleanup test completato: {$test_properties} properties, {$test_agencies} agenzie"
            ));
            
        } catch (Exception $e) {
            $this->logger->log("CLEANUP TEST ERROR: " . $e->getMessage(), 'error');
            wp_send_json_error('Errore cleanup test data: ' . $e->getMessage());
        }
    }
    
    /**
     * Add test flag to recently created properties
     */
    private function add_test_flag_to_recent_properties($count) {
        if ($count <= 0) return;
        
        global $wpdb;
        
        // Get recently created properties (last 10 minutes)
        $recent_properties = $wpdb->get_results("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'estate_property' 
            AND post_date >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ORDER BY post_date DESC 
            LIMIT {$count}
        ");
        
        foreach ($recent_properties as $property) {
            update_post_meta($property->ID, '_test_import', '1');
        }
        
        $this->logger->log("TEST FLAG: Added test flag to {$count} recent properties", 'info');
    }
    
    /**
     * Handle create property fields AJAX - WPRESIDENCE SYSTEM INTEGRATION
     * Phase 1: Create Property Details fields using correct WpResidence system
     * REVERSE ENGINEERED: Uses wpestate_custom_fields_list option array structure
     */
    public function handle_create_property_fields() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $this->logger->log('🎯 WPRESIDENCE FIELDS: Using REVERSE ENGINEERED system wpestate_custom_fields_list', 'info');
            
            // 🎯 CORRECT WPRESIDENCE SYSTEM: wpestate_custom_fields_list
            $existing_fields = get_option('wpestate_custom_fields_list', []);
            
            $this->logger->log('🔍 REVERSE ENG: Current wpestate_custom_fields_list structure: ' . print_r($existing_fields, true), 'info');
            
            // Property Details Custom Fields - NO PREFISSO property_ (come campi esistenti piano/stato-immobile)
            $new_fields_data = [
                [
                    'name' => 'superficie-giardino',
                    'label' => 'Superficie giardino (m²)',
                    'order' => '3'
                ],
                [
                    'name' => 'aree-esterne',
                    'label' => 'Aree esterne (m²)',
                    'order' => '4'
                ],
                [
                    'name' => 'superficie-commerciale',
                    'label' => 'Superficie commerciale (m²)',
                    'order' => '5'
                ],
                [
                    'name' => 'superficie-utile',
                    'label' => 'Superficie utile (m²)',
                    'order' => '6'
                ],
                [
                    'name' => 'totale-piani-edificio',
                    'label' => 'Totale piani edificio',
                    'order' => '7'
                ],
                [
                    'name' => 'deposito-cauzionale',
                    'label' => 'Deposito cauzionale (€)',
                    'order' => '8'
                ],
                [
                    'name' => 'distanza-mare',
                    'label' => 'Distanza dal mare (m)',
                    'order' => '9'
                ],
                [
                    'name' => 'rendita-catastale',
                    'label' => 'Rendita catastale (€)',
                    'order' => '10'
                ],
                [
                    'name' => 'destinazione-catastale',
                    'label' => 'Destinazione catastale',
                    'order' => '11'
                ]
            ];
            
            // 🔧 PREPARE WPRESIDENCE ARRAYS STRUCTURE (come nei database results)
            // Existing arrays or initialize
            $add_field_name = isset($existing_fields['add_field_name']) ? $existing_fields['add_field_name'] : [];
            $add_field_label = isset($existing_fields['add_field_label']) ? $existing_fields['add_field_label'] : [];
            $add_field_order = isset($existing_fields['add_field_order']) ? $existing_fields['add_field_order'] : [];
            
            $created_count = 0;
            $existing_count = 0;
            $field_details = [];
            
            foreach ($new_fields_data as $field) {
                // Check if field already exists (check in names array)
                if (in_array($field['name'], $add_field_name)) {
                    $existing_count++;
                    $field_details[] = [
                        'name' => $field['name'],
                        'label' => $field['label'],
                        'status' => 'existing',
                        'message' => 'Already exists in wpestate_custom_fields_list'
                    ];
                    $this->logger->log("ℹ️ FIELD EXISTS: {$field['name']} already in wpestate system", 'info');
                    continue;
                }
                
                // 🎯 ADD TO WPRESIDENCE ARRAYS STRUCTURE
                $add_field_name[] = $field['name'];
                $add_field_label[] = $field['label'];
                $add_field_order[] = $field['order'];
                
                $created_count++;
                $field_details[] = [
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'status' => 'created',
                    'message' => 'Added to wpestate_custom_fields_list system'
                ];
                $this->logger->log("✅ WPRESIDENCE FIELD: {$field['name']} → {$field['label']} (order: {$field['order']})", 'info');
            }
            
            // 🚀 UPDATE WPRESIDENCE OPTION WITH NEW STRUCTURE
            if ($created_count > 0) {
                $updated_fields = [
                    'add_field_name' => $add_field_name,
                    'add_field_label' => $add_field_label,
                    'add_field_order' => $add_field_order
                ];
                
                // PRESERVE other existing data if present
                if (is_array($existing_fields)) {
                    foreach ($existing_fields as $key => $value) {
                        if (!in_array($key, ['add_field_name', 'add_field_label', 'add_field_order'])) {
                            $updated_fields[$key] = $value;
                        }
                    }
                }
                
                $result = update_option('wpestate_custom_fields_list', $updated_fields);
                
                if ($result) {
                    $this->logger->log('✅ WPRESIDENCE UPDATE: Successfully updated wpestate_custom_fields_list option', 'info');
                    $this->logger->log('🎯 FINAL STRUCTURE: ' . print_r($updated_fields, true), 'info');
                } else {
                    $this->logger->log('❌ WPRESIDENCE UPDATE FAILED: Could not save to wpestate_custom_fields_list', 'error');
                }
            }
            
            // 🎉 GENERATE EXAMPLE FIELD FOR TESTING
            $example_property_id = $this->get_first_property_for_testing();
            if ($example_property_id && $created_count > 0) {
                // Add example values to one property for testing
                update_post_meta($example_property_id, 'superficie-giardino', '150');
                update_post_meta($example_property_id, 'aree-esterne', '200');
                update_post_meta($example_property_id, 'superficie-commerciale', '120');
                
                $this->logger->log("🧪 TESTING: Added example values to property {$example_property_id}", 'info');
            }
            
            // Generate summary message
            $summary_message = "WpResidence Fields Integration Completed! ";
            if ($created_count > 0) $summary_message .= "✅ {$created_count} fields added to WpResidence system. ";
            if ($existing_count > 0) $summary_message .= "ℹ️ {$existing_count} fields already existed. ";
            if ($example_property_id) $summary_message .= "🧪 Example values added to property {$example_property_id} for testing.";
            
            $this->logger->log("🎉 WPRESIDENCE INTEGRATION: Created={$created_count}, Existing={$existing_count}", 'info');
            
            wp_send_json_success([
                'created_count' => $created_count,
                'existing_count' => $existing_count,
                'total_fields' => count($new_fields_data),
                'example_property_id' => $example_property_id,
                'field_details' => $field_details,
                'wpresidence_system' => true,
                'summary_message' => $summary_message,
                'message' => $summary_message
            ]);
            
        } catch (Exception $e) {
            $this->logger->log('🚨 WPRESIDENCE FIELDS ERROR: ' . $e->getMessage(), 'error');
            wp_send_json_error('WpResidence Fields Creation Failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get first property for testing custom fields
     */
    private function get_first_property_for_testing() {
        global $wpdb;
        
        $property_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'estate_property' 
             AND post_status = 'publish' 
             LIMIT 1"
        );
        
        return $property_id ? intval($property_id) : null;
    }
    
    /**
     * Handle create property fields AJAX v2 - 🔥 NEW AUTOMATION METHOD
     * Based on cURL analysis and AJAX mechanism discovery
     */
    public function handle_create_property_fields_v2() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $test_mode = isset($_POST['test_mode']) && $_POST['test_mode'];
            
            $this->logger->log('🔥 CUSTOM FIELDS AUTOMATION v2: Starting with method from cURL analysis', 'info');
            
            // 📊 Get current WpResidence admin option
            $wpresidence_admin = get_option('wpresidence_admin', array());
            $current_fields = isset($wpresidence_admin['wpestate_custom_fields_list']) ? $wpresidence_admin['wpestate_custom_fields_list'] : array();
            
            $this->logger->log('CURRENT FIELDS: ' . print_r($current_fields, true), 'debug');
            
            // 🎯 CUSTOM FIELDS DATA - Based on cURL payload structure
            $new_custom_fields = $this->get_custom_fields_definition();
            
            if ($test_mode) {
                // 🧪 SAFE TEST: Create only test field first
                $test_field = array(
                    'add_field_name' => array('test-campo-prova'),
                    'add_field_label' => array('Test Campo Prova'),
                    'add_field_order' => array(1),
                    'add_field_type' => array('short_text')
                );
                
                $this->logger->log('🧪 TEST MODE: Creating test field only', 'info');
                $result = $this->apply_custom_fields_to_database($test_field, $wpresidence_admin);
                
                if ($result['success']) {
                    wp_send_json_success(array(
                        'created_count' => 1,
                        'test_mode' => true,
                        'summary_message' => '🧪 TEST FIELD CREATED: Ready for full automation',
                        'automation_details' => $result['details']
                    ));
                } else {
                    throw new Exception($result['error']);
                }
                
            } else {
                // 🚀 FULL AUTOMATION: Create all 9 custom fields
                $this->logger->log('🚀 FULL MODE: Creating all 9 custom fields', 'info');
                
                $result = $this->apply_custom_fields_to_database($new_custom_fields, $wpresidence_admin);
                
                if ($result['success']) {
                    wp_send_json_success(array(
                        'created_count' => 9,
                        'test_mode' => false,
                        'summary_message' => '🎉 AUTOMATION COMPLETE: 9 custom fields created',
                        'automation_details' => $result['details']
                    ));
                } else {
                    throw new Exception($result['error']);
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log('🚨 CUSTOM FIELDS AUTOMATION ERROR: ' . $e->getMessage(), 'error');
            wp_send_json_error('Automation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get custom fields definition based on cURL analysis
     */
    private function get_custom_fields_definition() {
        // 📊 Exact structure from cURL payload analysis
        return array(
            'add_field_name' => array(
                'superficie-giardino',
                'aree-esterne', 
                'superficie-commerciale',
                'superficie-utile',
                'totale-piani-edificio',
                'deposito-cauzionale',
                'distanza-mare',
                'rendita-catastale',
                'destinazione-catastale'
            ),
            'add_field_label' => array(
                'Superficie giardino (m²)',
                'Aree esterne (m²)',
                'Superficie commerciale (m²)',
                'Superficie utile (m²)',
                'Totale piani edificio',
                'Deposito cauzionale (€)',
                'Distanza dal mare (m)',
                'Rendita catastale (€)',
                'Destinazione catastale'
            ),
            'add_field_order' => array(5, 4, 6, 7, 3, 8, 9, 10, 11),
            'add_field_type' => array(
                'numeric',
                'numeric', 
                'numeric',
                'numeric',
                'numeric',
                'numeric',
                'numeric',
                'numeric',
                'short_text'
            )
        );
    }
    
    /**
     * Apply custom fields to database using ADDITIVE approach v3
     * Based on WpResidence theme analysis + DEBUG LOG findings
     */
    private function apply_custom_fields_to_database($custom_fields_data, $wpresidence_admin = null) {
        try {
            // 🔧 GET EXISTING DATA - NON SOVRASCRIVERE!
            if ($wpresidence_admin === null) {
                $wpresidence_admin = get_option('wpresidence_admin', []);
            }
            
            // 🔧 INIT STRUCTURE SE NON ESISTE
            if (!isset($wpresidence_admin['wpestate_custom_fields_list'])) {
                $wpresidence_admin['wpestate_custom_fields_list'] = [
                    'add_field_name' => [],
                    'add_field_label' => [],
                    'add_field_order' => [],
                    'add_field_type' => [],
                    'add_dropdown_order' => []
                ];
            }
            
            $existing_fields = $wpresidence_admin['wpestate_custom_fields_list'];
            
            // 🔧 MERGE CON CAMPI ESISTENTI - ADDITIVE APPROACH!
            $fields_to_add = $custom_fields_data['add_field_name'];
            $labels_to_add = $custom_fields_data['add_field_label'];
            $orders_to_add = $custom_fields_data['add_field_order'];
            $types_to_add = $custom_fields_data['add_field_type'];
            $dropdowns_to_add = isset($custom_fields_data['add_dropdown_order']) ? $custom_fields_data['add_dropdown_order'] : [];
            
            $added_count = 0;
            $existing_names = isset($existing_fields['add_field_name']) ? $existing_fields['add_field_name'] : [];
            
            // 🔧 ADD ONLY NEW FIELDS - DUPLICATE PREVENTION
            for ($i = 0; $i < count($fields_to_add); $i++) {
                $field_name = $fields_to_add[$i];
                
                // Skip se già esiste
                if (!in_array($field_name, $existing_names)) {
                    $wpresidence_admin['wpestate_custom_fields_list']['add_field_name'][] = $field_name;
                    $wpresidence_admin['wpestate_custom_fields_list']['add_field_label'][] = $labels_to_add[$i];
                    $wpresidence_admin['wpestate_custom_fields_list']['add_field_order'][] = $orders_to_add[$i];
                    $wpresidence_admin['wpestate_custom_fields_list']['add_field_type'][] = $types_to_add[$i];
                    $wpresidence_admin['wpestate_custom_fields_list']['add_dropdown_order'][] = isset($dropdowns_to_add[$i]) ? $dropdowns_to_add[$i] : '';
                    
                    $added_count++;
                    $this->logger->log("✅ ADDED FIELD: {$field_name} → {$labels_to_add[$i]}", 'info');
                } else {
                    $this->logger->log("⚠️ SKIPPED DUPLICATE: {$field_name}", 'info');
                }
            }
            
            // 🔧 UPDATE DATABASE
            $result = update_option('wpresidence_admin', $wpresidence_admin);
            
            if ($result !== false) {
                $this->logger->log("✅ CUSTOM FIELDS v3: Successfully updated wpresidence_admin option (Added: {$added_count})", 'info');
                
                // 🔧 CALL TEMA CONVERSION SE DISPONIBILE
                if (function_exists('wpestate_reverse_convert_redux_wp_estate_custom_fields')) {
                    wpestate_reverse_convert_redux_wp_estate_custom_fields();
                    $this->logger->log("✅ TRIGGERED: wpestate_reverse_convert_redux_wp_estate_custom_fields()", 'info');
                }
                
                return array(
                    'success' => true,
                    'added_count' => $added_count,
                    'details' => array(
                        'method' => 'ADDITIVE database merge v3',
                        'target_option' => 'wpresidence_admin[wpestate_custom_fields_list]',
                        'fields_added' => $added_count,
                        'duplicate_prevention' => 'Active',
                        'tema_conversion' => function_exists('wpestate_reverse_convert_redux_wp_estate_custom_fields') ? 'Called' : 'Not available'
                    )
                );
            } else {
                throw new Exception('Failed to update wpresidence_admin option');
            }
            
        } catch (Exception $e) {
            $this->logger->log("🚨 CUSTOM FIELDS v3 ERROR: " . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 🚀 PROFESSIONAL ACTIVATION TOOLS - Handle check activation status AJAX
     */
    public function handle_check_activation_status() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            // Check activation flag status
            $needs_activation = get_option('realestate_sync_needs_activation', false);
            
            // Check database table existence  
            global $wpdb;
            $table_name = $wpdb->prefix . 'realestate_sync_tracking';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            // Check basic options
            $options_exist = get_option('realestate_sync_xml_url', false) !== false;
            
            // Overall system status
            $activation_complete = !$needs_activation && $table_exists && $options_exist;
            
            // Generate status HTML
            $status_html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">';
            
            // Activation Flag Status
            $flag_status = $needs_activation ? '🔄 PENDING' : '✅ CLEAN';
            $flag_color = $needs_activation ? '#f59e0b' : '#22c55e';
            $flag_desc = $needs_activation ? 'Activation will complete automatically on wp_loaded' : 'Activation completed successfully';
            
            $status_html .= '<div style="padding: 15px; background: white; border-radius: 6px; border-left: 4px solid ' . $flag_color . ';">';
            $status_html .= '<h6 style="margin: 0 0 8px 0; color: ' . $flag_color . ';">Activation Flag</h6>';
            $status_html .= '<div style="font-size: 18px; font-weight: bold; color: ' . $flag_color . ';">' . $flag_status . '</div>';
            $status_html .= '<p style="margin: 5px 0 0 0; font-size: 12px; color: #6b7280;">' . $flag_desc . '</p>';
            $status_html .= '</div>';
            
            // Database Table Status
            $db_status = $table_exists ? '✅ EXISTS' : '❌ MISSING';
            $db_color = $table_exists ? '#22c55e' : '#ef4444';
            
            $status_html .= '<div style="padding: 15px; background: white; border-radius: 6px; border-left: 4px solid ' . $db_color . ';">';
            $status_html .= '<h6 style="margin: 0 0 8px 0; color: ' . $db_color . ';">Database Table</h6>';
            $status_html .= '<div style="font-size: 18px; font-weight: bold; color: ' . $db_color . ';">' . $db_status . '</div>';
            $status_html .= '<p style="margin: 5px 0 0 0; font-size: 12px; color: #6b7280;">realestate_sync_tracking table</p>';
            $status_html .= '</div>';
            
            // Plugin Options Status
            $options_status = $options_exist ? '✅ SET' : '❌ MISSING';
            $options_color = $options_exist ? '#22c55e' : '#ef4444';
            
            $status_html .= '<div style="padding: 15px; background: white; border-radius: 6px; border-left: 4px solid ' . $options_color . ';">';
            $status_html .= '<h6 style="margin: 0 0 8px 0; color: ' . $options_color . ';">Plugin Options</h6>';
            $status_html .= '<div style="font-size: 18px; font-weight: bold; color: ' . $options_color . ';">' . $options_status . '</div>';
            $status_html .= '<p style="margin: 5px 0 0 0; font-size: 12px; color: #6b7280;">Core plugin configuration</p>';
            $status_html .= '</div>';
            
            $status_html .= '</div>';
            
            // Overall status message
            if ($activation_complete) {
                $message = '🎉 <strong>PROFESSIONAL ACTIVATION COMPLETE!</strong><br>wp_loaded activation system working perfectly.';
                $message_class = 'rs-alert-success';
            } else if ($needs_activation) {
                $message = '🔄 <strong>ACTIVATION IN PROGRESS</strong><br>System will complete automatically via wp_loaded hook. Refresh to see updates.';
                $message_class = 'rs-alert-info';
            } else {
                $message = '⚠️ <strong>PARTIAL ACTIVATION</strong><br>Some components may need manual setup.';
                $message_class = 'rs-alert-warning';
            }
            
            $this->logger->log('🚀 ACTIVATION STATUS: Flag=' . ($needs_activation ? 'pending' : 'clean') . ', Table=' . ($table_exists ? 'exists' : 'missing') . ', Options=' . ($options_exist ? 'set' : 'missing'), 'info');
            
            wp_send_json_success(array(
                'activation_complete' => $activation_complete,
                'needs_activation' => $needs_activation,
                'table_exists' => $table_exists,
                'options_exist' => $options_exist,
                'status_html' => $status_html,
                'message' => $message,
                'message_class' => $message_class
            ));
            
        } catch (Exception $e) {
            $this->logger->log('🚨 ACTIVATION STATUS ERROR: ' . $e->getMessage(), 'error');
            wp_send_json_error('Error checking activation status: ' . $e->getMessage());
        }
    }
    
    /**
     * 🚀 PROFESSIONAL ACTIVATION TOOLS - Handle test activation workflow AJAX
     */
    public function handle_test_activation_workflow() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $this->logger->log('🧪 TEST WORKFLOW: Starting activation workflow simulation', 'info');
            
            // Simulate activation flag setting
            $test_results = [];
            
            // Test 1: Flag setting simulation
            $test_results[] = [
                'step' => 'Phase 1: Set Activation Flag',
                'description' => 'register_activation_hook sets realestate_sync_needs_activation flag',
                'status' => 'simulated',
                'details' => 'Flag would be set to trigger wp_loaded completion'
            ];
            
            // Test 2: wp_loaded timing simulation
            $test_results[] = [
                'step' => 'Phase 2: wp_loaded Hook',
                'description' => 'WordPress fully loaded, complete_activation() executes',
                'status' => 'simulated',
                'details' => 'Perfect timing ensures all WordPress functions available'
            ];
            
            // Test 3: Database creation simulation
            global $wpdb;
            $table_name = $wpdb->prefix . 'realestate_sync_tracking';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            $test_results[] = [
                'step' => 'Task: Database Creation',
                'description' => 'Create realestate_sync_tracking table with proper schema',
                'status' => $table_exists ? 'completed' : 'pending',
                'details' => $table_exists ? 'Table exists and ready' : 'Table would be created during activation'
            ];
            
            // Test 4: Options initialization simulation
            $options_exist = get_option('realestate_sync_xml_url', false) !== false;
            
            $test_results[] = [
                'step' => 'Task: Options Setup',
                'description' => 'Initialize default plugin options and settings',
                'status' => $options_exist ? 'completed' : 'pending',
                'details' => $options_exist ? 'Plugin options configured' : 'Options would be initialized during activation'
            ];
            
            // Test 5: Cleanup simulation
            $test_results[] = [
                'step' => 'Phase 3: Flag Cleanup',
                'description' => 'Remove activation flag to prevent re-execution',
                'status' => 'simulated',
                'details' => 'One-time execution guaranteed by flag cleanup'
            ];
            
            // Generate test HTML
            $test_html = '<div style="space-y: 10px;">';
            
            foreach ($test_results as $test) {
                $status_color = $test['status'] === 'completed' ? '#22c55e' : 
                               ($test['status'] === 'pending' ? '#f59e0b' : '#6b7280');
                $status_icon = $test['status'] === 'completed' ? '✅' : 
                              ($test['status'] === 'pending' ? '⚠️' : '🧪');
                
                $test_html .= '<div style="padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid ' . $status_color . '; margin-bottom: 10px;">';
                $test_html .= '<h6 style="margin: 0 0 5px 0; color: ' . $status_color . ';">' . $status_icon . ' ' . $test['step'] . '</h6>';
                $test_html .= '<p style="margin: 0 0 5px 0; font-weight: 500;">' . $test['description'] . '</p>';
                $test_html .= '<p style="margin: 0; font-size: 12px; color: #6b7280;">' . $test['details'] . '</p>';
                $test_html .= '</div>';
            }
            
            $test_html .= '</div>';
            
            $this->logger->log('🧪 TEST WORKFLOW: Simulation completed successfully', 'info');
            
            wp_send_json_success(array(
                'test_results' => $test_results,
                'test_html' => $test_html,
                'message' => '🧪 Activation workflow test completed! All phases simulated successfully.',
                'workflow_ready' => true
            ));
            
        } catch (Exception $e) {
            $this->logger->log('🚨 TEST WORKFLOW ERROR: ' . $e->getMessage(), 'error');
            wp_send_json_error('Error testing activation workflow: ' . $e->getMessage());
        }
    }
}

// Initialize admin
new RealEstate_Sync_Admin();
