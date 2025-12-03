<?php
/**
 * RealEstate Sync Agency Manager
 *
 * ⚠️ CRITICAL FILE - PROTECTED - DO NOT MODIFY
 * This file is part of the WORKING import system (commit cbbc9c0 / tag: working-import-cbbc9c0)
 * Verified working: 30-Nov-2025 - Creates agencies WITH logos via API
 *
 * Any batch system modifications must go through wrapper/adapter pattern
 * DO NOT modify the core import_agencies() method
 *
 * Handles agency creation and management from XML data
 * Direct mapping: XML agency data → WordPress estate_agency CPT
 *
 * @package RealEstateSync
 * @version 1.0.0
 * @since 1.1.0
 * @protected-since 30-Nov-2025
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Agency_Manager {
    
    /**
     * Logger instance
     */
    private $logger;

    /**
     * Agency cache for session performance
     */
    private $agency_cache = array();

    /**
     * Agency API Writer instance
     */
    private $api_writer;

    /**
     * Import statistics
     */
    private $import_stats = array(
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'with_logo' => 0
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        $this->api_writer = new RealEstate_Sync_WPResidence_Agency_API_Writer($this->logger);
    }

    /**
     * Import multiple agencies from Agency Parser
     *
     * Replaces Agency_Importer->import_agencies() with API-based import
     *
     * @param array $agencies Array of agency data from Agency Parser
     * @param bool $mark_as_test Mark agencies as test imports
     * @return array Import results with statistics
     */
    public function import_agencies($agencies, $mark_as_test = false) {
        $this->logger->log('Starting agency import via API: ' . count($agencies) . ' agencies', 'INFO');

        if ($mark_as_test) {
            $this->logger->log('🔖 Test mode enabled - agencies will be marked with _test_import flag', 'INFO');
        }

        // Reset statistics
        $this->import_stats = array(
            'total' => count($agencies),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'agency_ids' => array()
        );

        foreach ($agencies as $agency_data) {
            try {
                // Convert Agency Parser format to Agency Manager format
                $converted_data = $this->convert_parser_data_to_manager_format($agency_data);

                // Find existing agency by XML ID
                $existing_id = $this->find_agency_by_xml_id($converted_data['xml_agency_id']);

                if ($existing_id) {
                    // Update existing
                    $result = $this->update_agency_via_api($existing_id, $converted_data, $mark_as_test);
                    if ($result) {
                        $this->import_stats['updated']++;
                        $this->import_stats['agency_ids'][] = $existing_id;
                    } else {
                        $this->import_stats['errors']++;
                    }
                } else {
                    // Create new
                    $new_id = $this->create_agency_via_api($converted_data, $mark_as_test);
                    if ($new_id) {
                        $this->import_stats['imported']++;
                        $this->import_stats['agency_ids'][] = $new_id;
                    } else {
                        $this->import_stats['errors']++;
                    }
                }

                // Count logos
                if (!empty($converted_data['logo_url'])) {
                    $this->import_stats['with_logo']++;
                }

            } catch (Exception $e) {
                $this->logger->log('Error importing agency: ' . $e->getMessage(), 'ERROR');
                $this->import_stats['errors']++;
            }
        }

        $this->logger->log('Agency import completed via API', 'SUCCESS', $this->import_stats);

        return $this->import_stats;
    }

    /**
     * Convert Agency Parser data format to Agency Manager format
     *
     * @param array $parser_data Data from Agency Parser
     * @return array Data in Agency Manager format
     */
    private function convert_parser_data_to_manager_format($parser_data) {
        return array(
            'name' => $parser_data['ragione_sociale'] ?? 'Agenzia Immobiliare',
            'xml_agency_id' => $parser_data['id'] ?? '',
            'address' => $this->build_parser_address($parser_data),
            'phone' => $parser_data['telefono'] ?? '',
            'email' => $parser_data['email'] ?? '',
            'website' => $parser_data['url'] ?? '',
            'logo_url' => $parser_data['logo'] ?? '',
            'contact_person' => $parser_data['referente'] ?? '',
            'vat_number' => $parser_data['iva'] ?? '',
            'province' => $parser_data['provincia'] ?? '',
            'city' => $parser_data['comune'] ?? '',
            'mobile' => $parser_data['cellulare'] ?? ''
        );
    }

    /**
     * Build address from Agency Parser data
     *
     * @param array $parser_data Agency Parser data
     * @return string Full address
     */
    private function build_parser_address($parser_data) {
        $parts = array();

        if (!empty($parser_data['indirizzo'])) {
            $parts[] = $parser_data['indirizzo'];
        }

        if (!empty($parser_data['comune'])) {
            $city_part = $parser_data['comune'];
            if (!empty($parser_data['provincia'])) {
                $city_part .= ' (' . $parser_data['provincia'] . ')';
            }
            $parts[] = $city_part;
        }

        return implode(', ', $parts);
    }

    /**
     * Find agency by XML ID
     *
     * @param string $xml_id XML agency ID
     * @return int|false Agency post ID if found, false otherwise
     */
    private function find_agency_by_xml_id($xml_id) {
        // 🔍 Debug tracker
        $tracker = RealEstate_Sync_Debug_Tracker::get_instance();

        $tracker->log_event('DEBUG', 'AGENCY_MANAGER', 'Finding agency by XML ID', array(
            'xml_id' => $xml_id,
            'meta_key' => 'agency_xml_id'
        ));

        $args = array(
            'post_type' => 'estate_agency',  // FIXED: API creates estate_agency, not estate_agent
            'meta_key' => 'agency_xml_id',  // Fixed: match lookup query
            'meta_value' => $xml_id,
            'posts_per_page' => 1,
            'fields' => 'ids'
        );

        $query = new WP_Query($args);

        $tracker->log_query('AGENCY_MANAGER', $args, array(
            'found_posts' => $query->found_posts,
            'posts' => $query->posts
        ));

        if ($query->have_posts()) {
            $agency_id = $query->posts[0];
            $tracker->log_event('INFO', 'AGENCY_MANAGER', 'Agency found', array(
                'xml_id' => $xml_id,
                'wp_id' => $agency_id
            ));
            return $agency_id;
        }

        $tracker->log_event('INFO', 'AGENCY_MANAGER', 'Agency not found', array(
            'xml_id' => $xml_id
        ));

        return false;
    }

    /**
     * Create agency via API
     *
     * @param array $agency_data Agency data
     * @param bool $mark_as_test Mark as test import
     * @return int|false Agency ID on success, false on failure
     */
    private function create_agency_via_api($agency_data, $mark_as_test = false) {
        // 🔍 Debug tracker
        $tracker = RealEstate_Sync_Debug_Tracker::get_instance();

        $tracker->log_event('INFO', 'AGENCY_MANAGER', 'Creating agency via API', array(
            'name' => $agency_data['name'],
            'xml_id' => $agency_data['xml_agency_id']
        ));

        // Format for API
        $api_body = $this->api_writer->format_api_body($agency_data);

        // Create via API
        $result = $this->api_writer->create_agency($api_body);

        // Log API call
        $tracker->log_api_call('AGENCY_MANAGER', 'POST', '/wpresidence/v1/agency/add', $api_body, $result);

        if (!$result['success']) {
            $tracker->log_event('ERROR', 'AGENCY_MANAGER', 'Agency creation failed', array(
                'error' => $result['error']
            ));
            $this->logger->log('Failed to create agency via API: ' . $result['error'], 'ERROR');
            return false;
        }

        $agency_id = $result['agency_id'];

        $tracker->log_event('INFO', 'AGENCY_MANAGER', 'Agency created', array(
            'xml_id' => $agency_data['xml_agency_id'],
            'wp_id' => $agency_id
        ));

        // Store XML ID for tracking (CRITICAL for property→agency lookup in PHASE 2)
        // NOTE: Using 'agency_xml_id' (not 'xml_agency_id') to match lookup query
        $xml_id = $agency_data['xml_agency_id'];

        // Log meta save operation
        $tracker->log_meta_operation('AGENCY_MANAGER', 'save', $agency_id, 'agency_xml_id', $xml_id);

        update_post_meta($agency_id, 'agency_xml_id', $xml_id);
        $this->logger->log("Stored agency_xml_id meta: agency_id=$agency_id, xml_id=$xml_id", 'INFO');

        // Verify it was saved
        $saved_value = get_post_meta($agency_id, 'agency_xml_id', true);

        $tracker->log_meta_operation('AGENCY_MANAGER', 'verify', $agency_id, 'agency_xml_id', $saved_value, array(
            'matches_expected' => ($saved_value === $xml_id)
        ));

        $this->logger->log("Verified agency_xml_id meta: saved_value=$saved_value", 'INFO');

        // Mark as test if requested
        if ($mark_as_test) {
            update_post_meta($agency_id, '_test_import', 1);
        }

        return $agency_id;
    }

    /**
     * Update agency via API
     *
     * @param int $agency_id Agency post ID
     * @param array $agency_data Agency data
     * @param bool $mark_as_test Mark as test import
     * @return bool Success status
     */
    private function update_agency_via_api($agency_id, $agency_data, $mark_as_test = false) {
        // 🔍 Debug tracker
        $tracker = RealEstate_Sync_Debug_Tracker::get_instance();

        $tracker->log_event('INFO', 'AGENCY_MANAGER', 'Updating agency via API', array(
            'wp_id' => $agency_id,
            'xml_id' => $agency_data['xml_agency_id'],
            'name' => $agency_data['name']
        ));

        // Format for API
        $api_body = $this->api_writer->format_api_body($agency_data);

        // Update via API
        $result = $this->api_writer->update_agency($agency_id, $api_body);

        // Log API call
        $tracker->log_api_call('AGENCY_MANAGER', 'PUT', '/wpresidence/v1/agency/edit/' . $agency_id, $api_body, $result);

        if (!$result['success']) {
            $tracker->log_event('ERROR', 'AGENCY_MANAGER', 'Agency update failed', array(
                'wp_id' => $agency_id,
                'error' => $result['error']
            ));
            $this->logger->log("Failed to update agency $agency_id via API: " . $result['error'], 'ERROR');
            return false;
        }

        $tracker->log_event('INFO', 'AGENCY_MANAGER', 'Agency updated', array(
            'wp_id' => $agency_id,
            'xml_id' => $agency_data['xml_agency_id']
        ));

        // Update XML ID (in case it changed)
        // NOTE: Using 'agency_xml_id' (not 'xml_agency_id') to match lookup query
        $tracker->log_meta_operation('AGENCY_MANAGER', 'update', $agency_id, 'agency_xml_id', $agency_data['xml_agency_id']);

        update_post_meta($agency_id, 'agency_xml_id', $agency_data['xml_agency_id']);

        // Mark as test if requested
        if ($mark_as_test) {
            update_post_meta($agency_id, '_test_import', 1);
        }

        return true;
    }

    /**
     * Get import statistics
     *
     * @return array Import statistics
     */
    public function get_import_statistics() {
        return array(
            'agents_with_logo' => $this->import_stats['with_logo']
        );
    }
    
    /**
     * Create or update agency from XML property data
     * 
     * @param array $xml_property XML property data containing agency info
     * @return int|false Agency ID on success, false on failure
     */
    public function create_or_update_agency_from_xml($xml_property) {
        try {
            $this->logger->log('INFO', '🏢 AGENCY MANAGER: create_or_update_agency_from_xml called', array(
                'property_id' => isset($xml_property['id']) ? $xml_property['id'] : 'unknown',
                'method' => 'create_or_update_agency_from_xml',
                'timestamp' => current_time('mysql')
            ));
            
            $this->logger->log('INFO', 'Processing agency from XML property', array(
                'property_id' => isset($xml_property['id']) ? $xml_property['id'] : 'unknown'
            ));
            
            // Extract agency data from XML property
            $agency_data = $this->extract_agency_data_from_xml($xml_property);
            
            if (empty($agency_data)) {
                $this->logger->log('WARNING', 'No agency data found in XML property');
                return false;
            }
            
            // Check if agency already exists
            $existing_agency_id = $this->find_existing_agency($agency_data);
            
            if ($existing_agency_id) {
                $this->logger->log('INFO', 'Updating existing agency', array(
                    'agency_id' => $existing_agency_id,
                    'agency_name' => $agency_data['name']
                ));
                return $this->update_agency($existing_agency_id, $agency_data);
            } else {
                $this->logger->log('INFO', 'Creating new agency', array(
                    'agency_name' => $agency_data['name']
                ));
                return $this->create_agency($agency_data);
            }
            
        } catch (Exception $e) {
            $this->logger->log('ERROR', 'Error processing agency from XML: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Extract agency data from XML property
     * 
     * @param array $xml_property XML property data
     * @return array Agency data array
     */
    private function extract_agency_data_from_xml($xml_property) {
        // Extract agency information from converted agency data
        // Data comes already converted from Import Engine v3.0

        // 🔍 DEBUG: Log what we receive from Property Mapper
        $this->logger->log('🏢 [AGENCY MANAGER - STEP 3] Received data from Property Mapper', 'info');
        $this->logger->log('   Has agency_data key: ' . (isset($xml_property['agency_data']) ? 'YES' : 'NO'), 'debug');
        if (isset($xml_property['agency_data'])) {
            $this->logger->log('   agency_data is array: ' . (is_array($xml_property['agency_data']) ? 'YES' : 'NO'), 'debug');
            if (is_array($xml_property['agency_data'])) {
                $this->logger->log('   agency_data fields: ' . implode(', ', array_keys($xml_property['agency_data'])), 'debug');
            }
        }

        $agency_data = array();

        // 🔧 FIX: Read from agency_data subarray (converted by Import Engine)
        $agency_source = $xml_property['agency_data'] ?? $xml_property;

        // 🆕 UPDATED FIELD MAPPING: Use converted field names, not original XML names
        $agency_data['name'] = $this->get_xml_value($agency_source, 'name');
        $agency_data['xml_agency_id'] = $this->get_xml_value($agency_source, 'id');

        // Contact information - using converted field names
        $agency_data['address'] = $this->get_xml_value($agency_source, 'address');
        $agency_data['email'] = $this->get_xml_value($agency_source, 'email');
        $agency_data['phone'] = $this->get_xml_value($agency_source, 'phone');
        $agency_data['mobile'] = $this->get_xml_value($agency_source, 'mobile');
        $agency_data['website'] = $this->get_xml_value($agency_source, 'website');

        // Business information - using converted field names
        $agency_data['license'] = $this->get_xml_value($agency_source, 'vat_number');
        $agency_data['vat_number'] = $this->get_xml_value($agency_source, 'vat_number');

        // Location information - using converted field names
        $agency_data['city'] = $this->get_xml_value($agency_source, 'city');
        $agency_data['province'] = $this->get_xml_value($agency_source, 'province');
        $agency_data['zip_code'] = $this->get_xml_value($agency_source, 'zip_code');

        // Additional contact info
        $agency_data['contact_person'] = $this->get_xml_value($agency_source, 'contact_person');
        $agency_data['logo_url'] = $this->get_xml_value($agency_source, 'logo_url');
        
        // 🔍 DEBUG: Log tutti i campi che stiamo cercando vs quello che troviamo
        $this->logger->log('🏢 [AGENCY MANAGER - STEP 4] Field mapping results', 'info');
        $this->logger->log('   Source keys available: ' . implode(', ', array_keys($agency_source)), 'debug');
        $this->logger->log('   Extracted name: ' . ($agency_data['name'] ?: 'EMPTY'), 'debug');
        $this->logger->log('   Extracted id: ' . ($agency_data['xml_agency_id'] ?: 'EMPTY'), 'debug');
        $this->logger->log('   Extracted email: ' . ($agency_data['email'] ?: 'EMPTY'), 'debug');
        $this->logger->log('   Extracted phone: ' . ($agency_data['phone'] ?: 'EMPTY'), 'debug');
        
        // ✅ VALIDATE REQUIRED FIELDS - ROBUST LOGIC
        $missing_required = array();
        
        if (empty($agency_data['name'])) {
            $missing_required[] = 'name (ragione_sociale)';
        }
        
        if (empty($agency_data['xml_agency_id'])) {
            $missing_required[] = 'id (identificativo univoco)';
        }
        
        // 😨 STOP se mancano campi obbligatori
        if (!empty($missing_required)) {
            $this->logger->log('ERROR', 'Agency NOT created - Missing required fields: ' . implode(', ', $missing_required), array(
                'available_data' => $xml_property
            ));
            return array();
        }
        
        // ⚠️ VALIDATE OPTIONAL FIELDS - WARNING only
        $missing_optional = array();
        if (empty($agency_data['address'])) $missing_optional[] = 'address';
        if (empty($agency_data['phone'])) $missing_optional[] = 'phone';
        if (empty($agency_data['email'])) $missing_optional[] = 'email';
        if (empty($agency_data['website'])) $missing_optional[] = 'website';
        
        if (!empty($missing_optional)) {
            $this->logger->log('WARNING', 'Agency will be created but missing optional fields: ' . implode(', ', $missing_optional));
        }
        
        // Clean and validate data
        $agency_data = $this->sanitize_agency_data($agency_data);
        
        $this->logger->log('SUCCESS', 'Agency data extracted successfully', array(
            'agency_name' => $agency_data['name'],
            'agency_id' => $agency_data['xml_agency_id'],
            'optional_fields_found' => count($agency_data) - 2
        ));
        
        return $agency_data;
    }
    
    /**
     * Get value from XML array with fallback
     * 
     * @param array $xml_data XML data array
     * @param string $key Key to search for
     * @return string Value or empty string
     */
    private function get_xml_value($xml_data, $key) {
        return isset($xml_data[$key]) ? trim((string)$xml_data[$key]) : '';
    }
    
    /**
     * Sanitize agency data
     * 
     * @param array $agency_data Raw agency data
     * @return array Sanitized agency data
     */
    private function sanitize_agency_data($agency_data) {
        $sanitized = array();

        // Sanitize each field
        $sanitized['name'] = sanitize_text_field($agency_data['name'] ?? '');
        $sanitized['xml_agency_id'] = sanitize_text_field($agency_data['xml_agency_id'] ?? '');
        $sanitized['address'] = sanitize_textarea_field($agency_data['address'] ?? '');
        $sanitized['email'] = sanitize_email($agency_data['email'] ?? '');
        $sanitized['phone'] = sanitize_text_field($agency_data['phone'] ?? '');
        $sanitized['mobile'] = sanitize_text_field($agency_data['mobile'] ?? '');
        $sanitized['website'] = esc_url_raw($agency_data['website'] ?? '');
        $sanitized['logo_url'] = esc_url_raw($agency_data['logo_url'] ?? '');
        $sanitized['license'] = sanitize_text_field($agency_data['license'] ?? '');
        $sanitized['vat_number'] = sanitize_text_field($agency_data['vat_number'] ?? '');
        $sanitized['city'] = sanitize_text_field($agency_data['city'] ?? '');
        $sanitized['province'] = sanitize_text_field($agency_data['province'] ?? '');
        $sanitized['zip_code'] = sanitize_text_field($agency_data['zip_code'] ?? '');
        $sanitized['contact_person'] = sanitize_text_field($agency_data['contact_person'] ?? '');

        return $sanitized;
    }
    
    /**
     * Find existing agency by name or XML ID
     * 
     * @param array $agency_data Agency data
     * @return int|false Agency ID if found, false otherwise
     */
    private function find_existing_agency($agency_data) {
        // Check cache first
        $cache_key = md5($agency_data['name'] . $agency_data['xml_agency_id']);
        if (isset($this->agency_cache[$cache_key])) {
            return $this->agency_cache[$cache_key];
        }
        
        $existing_agency_id = false;
        
        // Try to find by XML agency ID first (most accurate)
        if (!empty($agency_data['xml_agency_id'])) {
            $meta_query = array(
                array(
                    'key' => 'xml_agency_id',
                    'value' => $agency_data['xml_agency_id'],
                    'compare' => '='
                )
            );
            
            $query = new WP_Query(array(
                'post_type' => 'estate_agency',
                'post_status' => 'publish',
                'meta_query' => $meta_query,
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));
            
            if ($query->have_posts()) {
                $existing_agency_id = $query->posts[0];
                $this->logger->log('DEBUG', 'Found existing agency by XML ID', array(
                    'agency_id' => $existing_agency_id,
                    'xml_agency_id' => $agency_data['xml_agency_id']
                ));
            }
            
            wp_reset_postdata();
        }
        
        // If not found by XML ID, try by name
        if (!$existing_agency_id && !empty($agency_data['name'])) {
            $query = new WP_Query(array(
                'post_type' => 'estate_agency',
                'post_status' => 'publish',
                'title' => $agency_data['name'],
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));
            
            if ($query->have_posts()) {
                $existing_agency_id = $query->posts[0];
                $this->logger->log('DEBUG', 'Found existing agency by name', array(
                    'agency_id' => $existing_agency_id,
                    'agency_name' => $agency_data['name']
                ));
            }
            
            wp_reset_postdata();
        }
        
        // Cache result
        if ($existing_agency_id) {
            $this->agency_cache[$cache_key] = $existing_agency_id;
        }
        
        return $existing_agency_id;
    }

    /**
     * Lookup agency by XML ID (for PHASE 2 - property mapping)
     *
     * This function is used during property import to find agencies
     * that were already created in PHASE 1 (standalone agency import).
     * It ONLY does lookup, never creates or updates.
     *
     * @param string $xml_agency_id XML agency ID to lookup
     * @return int|false Agency WordPress Post ID if found, false otherwise
     */
    public function lookup_agency_by_xml_id($xml_agency_id) {
        if (empty($xml_agency_id)) {
            $this->logger->log('🔍 Lookup agency by XML ID: Empty ID provided', 'warning');
            return false;
        }

        $this->logger->log('🔍 Looking up agency by XML ID: ' . $xml_agency_id, 'info');

        // Check cache first
        $cache_key = 'xmlid_' . md5($xml_agency_id);
        if (isset($this->agency_cache[$cache_key])) {
            $this->logger->log('✅ Found agency in cache - XML ID: ' . $xml_agency_id . ', WP ID: ' . $this->agency_cache[$cache_key], 'info');
            return $this->agency_cache[$cache_key];
        }

        // STRATEGY 1: Try direct meta lookup (for agencies with xml_agency_id meta)
        $this->logger->log('🔍 Strategy 1: Looking for xml_agency_id meta', 'debug');
        $query = new WP_Query(array(
            'post_type' => 'estate_agency',  // FIXED: API creates estate_agency, not estate_agent
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'agency_xml_id',  // Fixed: was 'xml_agency_id' (wrong order)
                    'value' => $xml_agency_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        $agency_id = false;
        if ($query->have_posts()) {
            $agency_id = $query->posts[0];
            $this->logger->log('✅ Found agency by XML ID: ' . $xml_agency_id . ' → WP ID: ' . $agency_id, 'info');

            // Cache result
            $this->agency_cache[$cache_key] = $agency_id;
        } else {
            $this->logger->log('⚠️ Agency NOT found by XML ID: ' . $xml_agency_id, 'warning');
        }

        wp_reset_postdata();
        return $agency_id;
    }

    /**
     * Create new agency via WPResidence REST API
     *
     * @param array $agency_data Agency data
     * @return int|false Agency ID on success, false on failure
     */
    private function create_agency($agency_data) {
        $this->logger->log('INFO', 'Creating agency via WPResidence API', array(
            'agency_name' => $agency_data['name']
        ));

        // Format data for API
        $api_body = $this->api_writer->format_api_body($agency_data);

        // Create via API
        $result = $this->api_writer->create_agency($api_body);

        if (!$result['success']) {
            $this->logger->log('ERROR', 'Failed to create agency via API: ' . $result['error']);
            return false;
        }

        $agency_id = $result['agency_id'];

        $this->logger->log('SUCCESS', 'Created new agency via API', array(
            'agency_id' => $agency_id,
            'agency_name' => $agency_data['name']
        ));

        // Store XML agency ID for tracking
        update_post_meta($agency_id, 'xml_agency_id', $agency_data['xml_agency_id']);

        // Cache the new agency
        $cache_key = md5($agency_data['name'] . $agency_data['xml_agency_id']);
        $this->agency_cache[$cache_key] = $agency_id;

        return $agency_id;
    }
    
    /**
     * Update existing agency via WPResidence REST API
     *
     * @param int $agency_id Agency ID
     * @param array $agency_data Agency data
     * @return int|false Agency ID on success, false on failure
     */
    private function update_agency($agency_id, $agency_data) {
        $this->logger->log('INFO', 'Updating agency via WPResidence API', array(
            'agency_id' => $agency_id,
            'agency_name' => $agency_data['name']
        ));

        // Format data for API
        $api_body = $this->api_writer->format_api_body($agency_data);

        // Update via API
        $result = $this->api_writer->update_agency($agency_id, $api_body);

        if (!$result['success']) {
            $this->logger->log('ERROR', 'Failed to update agency via API: ' . $result['error']);
            return false;
        }

        $this->logger->log('SUCCESS', 'Updated agency via API', array(
            'agency_id' => $agency_id,
            'agency_name' => $agency_data['name']
        ));

        // Update XML agency ID for tracking (in case it changed)
        update_post_meta($agency_id, 'xml_agency_id', $agency_data['xml_agency_id']);

        return $agency_id;
    }
    
    /**
     * Get agency by XML ID
     * 
     * @param string $xml_agency_id XML agency ID
     * @return int|false Agency ID if found, false otherwise
     */
    public function get_agency_by_xml_id($xml_agency_id) {
        if (empty($xml_agency_id)) {
            return false;
        }
        
        $meta_query = array(
            array(
                'key' => 'xml_agency_id',
                'value' => $xml_agency_id,
                'compare' => '='
            )
        );
        
        $query = new WP_Query(array(
            'post_type' => 'estate_agency',
            'post_status' => 'publish',
            'meta_query' => $meta_query,
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));
        
        $agency_id = $query->have_posts() ? $query->posts[0] : false;
        wp_reset_postdata();
        
        return $agency_id;
    }
    
    /**
     * Bulk process agencies from XML properties array
     * 
     * @param array $xml_properties Array of XML properties
     * @return array Array of results with agency IDs
     */
    public function bulk_process_agencies($xml_properties) {
        $results = array();
        $processed_agencies = array();
        
        $this->logger->log('INFO', 'Starting bulk agency processing', array(
            'properties_count' => count($xml_properties)
        ));
        
        foreach ($xml_properties as $index => $xml_property) {
            // Extract agency data
            $agency_data = $this->extract_agency_data_from_xml($xml_property);
            
            if (empty($agency_data) || empty($agency_data['name'])) {
                $results[$index] = array(
                    'success' => false,
                    'agency_id' => false,
                    'message' => 'No valid agency data found'
                );
                continue;
            }
            
            // Check if we already processed this agency in this batch
            $agency_key = md5($agency_data['name'] . $agency_data['xml_agency_id']);
            if (isset($processed_agencies[$agency_key])) {
                $results[$index] = array(
                    'success' => true,
                    'agency_id' => $processed_agencies[$agency_key],
                    'message' => 'Agency already processed in this batch'
                );
                continue;
            }
            
            // Process the agency
            $agency_id = $this->create_or_update_agency_from_xml($xml_property);
            
            if ($agency_id) {
                $processed_agencies[$agency_key] = $agency_id;
                $results[$index] = array(
                    'success' => true,
                    'agency_id' => $agency_id,
                    'message' => 'Agency processed successfully'
                );
            } else {
                $results[$index] = array(
                    'success' => false,
                    'agency_id' => false,
                    'message' => 'Failed to process agency'
                );
            }
        }
        
        $successful_count = count(array_filter($results, function($result) {
            return $result['success'];
        }));
        
        $this->logger->log('INFO', 'Completed bulk agency processing', array(
            'total_properties' => count($xml_properties),
            'successful_agencies' => $successful_count,
            'unique_agencies' => count($processed_agencies)
        ));
        
        return $results;
    }
    
    /**
     * Get agency statistics
     * 
     * @return array Agency statistics
     */
    public function get_agency_statistics() {
        // Total agencies
        $total_agencies = wp_count_posts('estate_agency');
        
        // Agencies with XML ID (imported from XML)
        $xml_agencies_query = new WP_Query(array(
            'post_type' => 'estate_agency',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'xml_agency_id',
                    'compare' => 'EXISTS'
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        $xml_agencies_count = $xml_agencies_query->found_posts;
        wp_reset_postdata();
        
        return array(
            'total_agencies' => $total_agencies->publish,
            'xml_imported_agencies' => $xml_agencies_count,
            'manual_agencies' => $total_agencies->publish - $xml_agencies_count
        );
    }
    
    /**
     * Clean orphaned agencies (agencies not associated with any property)
     * 
     * @return array Cleanup results
     */
    public function clean_orphaned_agencies() {
        $this->logger->log('INFO', 'Starting orphaned agencies cleanup');
        
        $cleanup_results = array(
            'checked' => 0,
            'orphaned' => 0,
            'deleted' => 0,
            'errors' => 0
        );
        
        // Get all agencies
        $agencies_query = new WP_Query(array(
            'post_type' => 'estate_agency',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        $cleanup_results['checked'] = $agencies_query->found_posts;
        
        foreach ($agencies_query->posts as $agency_id) {
            // Check if agency has associated properties
            $properties_query = new WP_Query(array(
                'post_type' => 'estate_property',
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => 'property_agent',
                        'value' => $agency_id,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));
            
            if (!$properties_query->have_posts()) {
                $cleanup_results['orphaned']++;
                
                // Delete orphaned agency
                $deleted = wp_delete_post($agency_id, true);
                if ($deleted) {
                    $cleanup_results['deleted']++;
                } else {
                    $cleanup_results['errors']++;
                }
            }
            
            wp_reset_postdata();
        }
        
        wp_reset_postdata();
        
        $this->logger->log('INFO', 'Completed orphaned agencies cleanup', $cleanup_results);

        return $cleanup_results;
    }
}

// End of class
