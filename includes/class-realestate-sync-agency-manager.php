<?php
/**
 * RealEstate Sync Agency Manager
 * 
 * Handles agency creation and management from XML data
 * Direct mapping: XML agency data → WordPress estate_agency CPT
 * 
 * @package RealEstateSync
 * @version 1.0.0
 * @since 1.1.0
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
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
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
        // Extract agency information from XML property data
        // Based on GestionaleImmobiliare.it XML structure
        
        $agency_data = array();
        
        // Agency basic info
        $agency_data['name'] = $this->get_xml_value($xml_property, 'ragione_sociale');
        $agency_data['xml_agency_id'] = $this->get_xml_value($xml_property, 'agenzia_id');
        
        // Contact information
        $agency_data['address'] = $this->get_xml_value($xml_property, 'agenzia_indirizzo');
        $agency_data['email'] = $this->get_xml_value($xml_property, 'agenzia_email');
        $agency_data['phone'] = $this->get_xml_value($xml_property, 'agenzia_telefono');
        $agency_data['mobile'] = $this->get_xml_value($xml_property, 'agenzia_cellulare');
        $agency_data['website'] = $this->get_xml_value($xml_property, 'agenzia_sito_web');
        
        // Business information
        $agency_data['license'] = $this->get_xml_value($xml_property, 'partita_iva');
        $agency_data['vat_number'] = $this->get_xml_value($xml_property, 'partita_iva');
        
        // Location information
        $agency_data['city'] = $this->get_xml_value($xml_property, 'agenzia_citta');
        $agency_data['province'] = $this->get_xml_value($xml_property, 'agenzia_provincia');
        $agency_data['zip_code'] = $this->get_xml_value($xml_property, 'agenzia_cap');
        
        // Additional contact info
        $agency_data['fax'] = $this->get_xml_value($xml_property, 'agenzia_fax');
        
        // 🔍 DEBUG: Log tutti i campi che stiamo cercando vs quello che troviamo
        $this->logger->log('DEBUG', 'Agency Manager field mapping attempt:', array(
            'looking_for_ragione_sociale' => 'ragione_sociale',
            'found_name' => $agency_data['name'],
            'looking_for_agenzia_id' => 'agenzia_id', 
            'found_xml_agency_id' => $agency_data['xml_agency_id'],
            'available_keys' => array_keys($xml_property)
        ));
        
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
        $sanitized['name'] = sanitize_text_field($agency_data['name']);
        $sanitized['xml_agency_id'] = sanitize_text_field($agency_data['xml_agency_id']);
        $sanitized['address'] = sanitize_textarea_field($agency_data['address']);
        $sanitized['email'] = sanitize_email($agency_data['email']);
        $sanitized['phone'] = sanitize_text_field($agency_data['phone']);
        $sanitized['mobile'] = sanitize_text_field($agency_data['mobile']);
        $sanitized['website'] = esc_url_raw($agency_data['website']);
        $sanitized['license'] = sanitize_text_field($agency_data['license']);
        $sanitized['vat_number'] = sanitize_text_field($agency_data['vat_number']);
        $sanitized['city'] = sanitize_text_field($agency_data['city']);
        $sanitized['province'] = sanitize_text_field($agency_data['province']);
        $sanitized['zip_code'] = sanitize_text_field($agency_data['zip_code']);
        $sanitized['fax'] = sanitize_text_field($agency_data['fax']);
        
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
     * Create new agency
     * 
     * @param array $agency_data Agency data
     * @return int|false Agency ID on success, false on failure
     */
    private function create_agency($agency_data) {
        // Prepare post data
        $post_data = array(
            'post_type' => 'estate_agency',
            'post_title' => $agency_data['name'],
            'post_status' => 'publish',
            'post_author' => 1, // Admin user
            'meta_input' => $this->prepare_agency_meta_fields($agency_data)
        );
        
        // Insert the post
        $agency_id = wp_insert_post($post_data);
        
        if (is_wp_error($agency_id)) {
            $this->logger->log('ERROR', 'Failed to create agency: ' . $agency_id->get_error_message());
            return false;
        }
        
        if (!$agency_id) {
            $this->logger->log('ERROR', 'Failed to create agency - wp_insert_post returned 0');
            return false;
        }
        
        $this->logger->log('SUCCESS', 'Created new agency', array(
            'agency_id' => $agency_id,
            'agency_name' => $agency_data['name']
        ));
        
        // Cache the new agency
        $cache_key = md5($agency_data['name'] . $agency_data['xml_agency_id']);
        $this->agency_cache[$cache_key] = $agency_id;
        
        return $agency_id;
    }
    
    /**
     * Update existing agency
     * 
     * @param int $agency_id Agency ID
     * @param array $agency_data Agency data
     * @return int|false Agency ID on success, false on failure
     */
    private function update_agency($agency_id, $agency_data) {
        // Update post data
        $post_data = array(
            'ID' => $agency_id,
            'post_title' => $agency_data['name'],
            'post_modified' => current_time('mysql'),
        );
        
        // Update the post
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            $this->logger->log('ERROR', 'Failed to update agency: ' . $result->get_error_message());
            return false;
        }
        
        // Update meta fields
        $meta_fields = $this->prepare_agency_meta_fields($agency_data);
        foreach ($meta_fields as $meta_key => $meta_value) {
            update_post_meta($agency_id, $meta_key, $meta_value);
        }
        
        $this->logger->log('SUCCESS', 'Updated agency', array(
            'agency_id' => $agency_id,
            'agency_name' => $agency_data['name']
        ));
        
        return $agency_id;
    }
    
    /**
     * Prepare WpResidence agency meta fields
     * 
     * @param array $agency_data Agency data
     * @return array Meta fields array
     */
    private function prepare_agency_meta_fields($agency_data) {
        $meta_fields = array();
        
        // XML tracking field
        if (!empty($agency_data['xml_agency_id'])) {
            $meta_fields['xml_agency_id'] = $agency_data['xml_agency_id'];
        }
        
        // WpResidence agency meta fields mapping
        if (!empty($agency_data['address'])) {
            $meta_fields['agency_address'] = $agency_data['address'];
        }
        
        if (!empty($agency_data['email'])) {
            $meta_fields['agency_email'] = $agency_data['email'];
        }
        
        if (!empty($agency_data['phone'])) {
            $meta_fields['agency_phone'] = $agency_data['phone'];
        }
        
        if (!empty($agency_data['mobile'])) {
            $meta_fields['agency_mobile'] = $agency_data['mobile'];
        }
        
        if (!empty($agency_data['website'])) {
            $meta_fields['agency_website'] = $agency_data['website'];
        }
        
        if (!empty($agency_data['license'])) {
            $meta_fields['agency_license'] = $agency_data['license'];
        }
        
        if (!empty($agency_data['city'])) {
            $meta_fields['agency_city'] = $agency_data['city'];
        }
        
        if (!empty($agency_data['province'])) {
            $meta_fields['agency_state'] = $agency_data['province'];
        }
        
        if (!empty($agency_data['zip_code'])) {
            $meta_fields['agency_zip'] = $agency_data['zip_code'];
        }
        
        if (!empty($agency_data['fax'])) {
            $meta_fields['agency_fax'] = $agency_data['fax'];
        }
        
        // Additional WpResidence fields with defaults
        $meta_fields['agency_languages'] = 'Italiano';
        $meta_fields['agency_skype'] = '';
        $meta_fields['agency_facebook'] = '';
        $meta_fields['agency_twitter'] = '';
        $meta_fields['agency_linkedin'] = '';
        $meta_fields['agency_pinterest'] = '';
        $meta_fields['agency_instagram'] = '';
        
        // Last import timestamp
        $meta_fields['last_xml_import'] = current_time('timestamp');
        
        $this->logger->log('DEBUG', 'Prepared agency meta fields', array(
            'fields_count' => count($meta_fields),
            'agency_email' => isset($meta_fields['agency_email']) ? $meta_fields['agency_email'] : 'not_set'
        ));
        
        return $meta_fields;
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
