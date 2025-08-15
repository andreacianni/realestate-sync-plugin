<?php
/**
 * RealEstate Sync Plugin - Agency Manager v1.0
 * 
 * Gestisce la creazione e gestione delle agenzie immobiliari come Custom Post Type
 * Associa properties alle agenzie per il tema WpResidence
 * 
 * @package RealEstateSync
 * @version 1.0.0
 * @author Andrea Cianni - Novacom
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

class RealEstate_Sync_Agency_Manager {
    
    private $logger;
    private $agency_post_type = 'estate_agent';
    private $stats = [];
    
    public function __construct($logger = null) {
        $this->logger = $logger ?: RealEstate_Sync_Logger::get_instance();
        $this->init_stats();
        $this->logger->log('🏢 Agency Manager v1.0 initialized', 'info');
    }
    
    private function init_stats() {
        $this->stats = [
            'agencies_created' => 0,
            'agencies_updated' => 0,
            'agencies_skipped' => 0,
            'agencies_failed' => 0,
            'properties_linked' => 0
        ];
    }
    
    /**
     * Process agency data and create/update agency post
     * 
     * @param array $agency_data Agency data from XML conversion
     * @return array Processing result
     */
    public function process_agency($agency_data) {
        if (empty($agency_data['id'])) {
            $this->stats['agencies_failed']++;
            return [
                'success' => false,
                'error' => 'Agency ID missing'
            ];
        }
        
        try {
            // Check if agency already exists
            $existing_agency_id = $this->find_existing_agency($agency_data['id']);
            
            if ($existing_agency_id) {
                $result = $this->update_agency($existing_agency_id, $agency_data);
                if ($result['success']) {
                    $this->stats['agencies_updated']++;
                }
            } else {
                $result = $this->create_agency($agency_data);
                if ($result['success']) {
                    $this->stats['agencies_created']++;
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->stats['agencies_failed']++;
            $this->logger->log('🏢 Agency processing failed: ' . $e->getMessage(), 'error', [
                'agency_id' => $agency_data['id'],
                'agency_name' => $agency_data['name'] ?? 'Unknown'
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create new agency post
     * 
     * @param array $agency_data Agency data
     * @return array Creation result
     */
    private function create_agency($agency_data) {
        $post_data = [
            'post_type' => $this->agency_post_type,
            'post_status' => 'publish',
            'post_author' => 1,
            'post_title' => $agency_data['name'],
            'post_content' => $this->generate_agency_description($agency_data),
            'post_name' => sanitize_title($agency_data['name'] . '-' . $agency_data['id']),
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ];
        
        $agency_post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($agency_post_id)) {
            return [
                'success' => false,
                'error' => 'Agency post creation failed: ' . $agency_post_id->get_error_message()
            ];
        }
        
        // Add agency meta fields
        $this->assign_agency_meta_fields($agency_post_id, $agency_data);
        
        $this->logger->log('🏢 Agency created successfully', 'info', [
            'agency_id' => $agency_data['id'],
            'agency_name' => $agency_data['name'],
            'post_id' => $agency_post_id
        ]);
        
        return [
            'success' => true,
            'action' => 'created',
            'post_id' => $agency_post_id,
            'message' => 'Agency created successfully'
        ];
    }
    
    /**
     * Update existing agency post
     * 
     * @param int $agency_post_id WordPress post ID
     * @param array $agency_data New agency data
     * @return array Update result
     */
    private function update_agency($agency_post_id, $agency_data) {
        // Check if content has changed
        $existing_hash = get_post_meta($agency_post_id, 'agency_content_hash', true);
        $new_hash = $this->generate_agency_hash($agency_data);
        
        if ($existing_hash === $new_hash) {
            $this->stats['agencies_skipped']++;
            $this->logger->log('🏢 Agency content unchanged - skipping update', 'debug', [
                'agency_id' => $agency_data['id'],
                'post_id' => $agency_post_id
            ]);
            
            return [
                'success' => true,
                'action' => 'skipped',
                'post_id' => $agency_post_id,
                'message' => 'No changes detected'
            ];
        }
        
        $post_data = [
            'ID' => $agency_post_id,
            'post_title' => $agency_data['name'],
            'post_content' => $this->generate_agency_description($agency_data)
        ];
        
        $result = wp_update_post($post_data, true);
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Agency post update failed: ' . $result->get_error_message()
            ];
        }
        
        // Update agency meta fields
        $this->assign_agency_meta_fields($agency_post_id, $agency_data);
        
        // Update content hash
        update_post_meta($agency_post_id, 'agency_content_hash', $new_hash);
        
        $this->logger->log('🏢 Agency updated successfully', 'info', [
            'agency_id' => $agency_data['id'],
            'agency_name' => $agency_data['name'],
            'post_id' => $agency_post_id
        ]);
        
        return [
            'success' => true,
            'action' => 'updated',
            'post_id' => $agency_post_id,
            'message' => 'Agency updated successfully'
        ];
    }
    
    /**
     * Assign meta fields to agency post
     * 
     * @param int $agency_post_id WordPress post ID
     * @param array $agency_data Agency data
     */
    private function assign_agency_meta_fields($agency_post_id, $agency_data) {
        $meta_fields = [
            // WpResidence standard fields
            'agent_position' => 'Agenzia Immobiliare',
            'agent_mobile' => $agency_data['phone'] ?? '',
            'agent_phone' => $agency_data['phone'] ?? '',
            'agent_email' => $agency_data['email'] ?? '',
            'agent_website' => $agency_data['website'] ?? '',
            'agent_address' => $agency_data['address'] ?? '',
            'agent_skype' => '',
            'agent_facebook' => '',
            'agent_twitter' => '',
            'agent_linkedin' => '',
            'agent_instagram' => '',
            'agent_pinterest' => '',
            
            // Custom import fields
            'agency_import_id' => $agency_data['id'],
            'agency_import_source' => 'GestionaleImmobiliare',
            'agency_import_date' => current_time('mysql'),
            'agency_content_hash' => $this->generate_agency_hash($agency_data),
            'agency_logo_url' => $agency_data['logo_url'] ?? '',
            
            // Statistics fields
            'agency_total_properties' => 0, // Will be updated when properties are linked
            'agency_last_property_sync' => current_time('mysql')
        ];
        
        foreach ($meta_fields as $meta_key => $meta_value) {
            if ($meta_value !== null && $meta_value !== '') {
                update_post_meta($agency_post_id, $meta_key, $meta_value);
            }
        }
        
        // Handle agency logo if URL provided
        if (!empty($agency_data['logo_url'])) {
            $this->process_agency_logo($agency_post_id, $agency_data['logo_url']);
        }
    }
    
    /**
     * Process agency logo from URL
     * 
     * @param int $agency_post_id WordPress post ID
     * @param string $logo_url Logo URL
     */
    private function process_agency_logo($agency_post_id, $logo_url) {
        // Simple implementation - store URL for now
        // Could be enhanced to download and store logo locally
        update_post_meta($agency_post_id, 'agent_logo_url', $logo_url);
        
        $this->logger->log('🏢 Agency logo URL stored', 'debug', [
            'post_id' => $agency_post_id,
            'logo_url' => $logo_url
        ]);
    }
    
    /**
     * Link property to agency
     * 
     * @param int $property_post_id Property post ID
     * @param int $agency_post_id Agency post ID
     * @return bool Success status
     */
    public function link_property_to_agency($property_post_id, $agency_post_id) {
        if (empty($property_post_id) || empty($agency_post_id)) {
            return false;
        }
        
        // Set property agent field (WpResidence standard)
        update_post_meta($property_post_id, 'property_agent', $agency_post_id);
        
        // Update agency statistics
        $this->update_agency_property_count($agency_post_id);
        
        $this->stats['properties_linked']++;
        
        $this->logger->log('🔗 Property linked to agency', 'debug', [
            'property_id' => $property_post_id,
            'agency_id' => $agency_post_id
        ]);
        
        return true;
    }
    
    /**
     * Update agency property count
     * 
     * @param int $agency_post_id Agency post ID
     */
    private function update_agency_property_count($agency_post_id) {
        // Count properties linked to this agency
        $property_count = $this->count_agency_properties($agency_post_id);
        
        update_post_meta($agency_post_id, 'agency_total_properties', $property_count);
        update_post_meta($agency_post_id, 'agency_last_property_sync', current_time('mysql'));
    }
    
    /**
     * Count properties linked to agency
     * 
     * @param int $agency_post_id Agency post ID
     * @return int Property count
     */
    private function count_agency_properties($agency_post_id) {
        $properties = get_posts([
            'post_type' => 'estate_property',
            'meta_query' => [
                [
                    'key' => 'property_agent',
                    'value' => $agency_post_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        return count($properties);
    }
    
    /**
     * Find existing agency by import ID
     * 
     * @param string $agency_import_id Agency import ID
     * @return int|null Agency post ID or null
     */
    private function find_existing_agency($agency_import_id) {
        $agencies = get_posts([
            'post_type' => $this->agency_post_type,
            'meta_query' => [
                [
                    'key' => 'agency_import_id',
                    'value' => $agency_import_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);
        
        return !empty($agencies) ? $agencies[0] : null;
    }
    
    /**
     * Generate agency description
     * 
     * @param array $agency_data Agency data
     * @return string Agency description
     */
    private function generate_agency_description($agency_data) {
        $description = "Agenzia immobiliare " . $agency_data['name'];
        
        if (!empty($agency_data['address'])) {
            $description .= " con sede in " . $agency_data['address'];
        }
        
        $description .= ". Specializzata nella vendita e affitto di immobili in Trentino Alto Adige.";
        
        if (!empty($agency_data['website'])) {
            $description .= " Visita il nostro sito web: " . $agency_data['website'];
        }
        
        return $description;
    }
    
    /**
     * Generate agency content hash for change detection
     * 
     * @param array $agency_data Agency data
     * @return string Content hash
     */
    private function generate_agency_hash($agency_data) {
        $hash_fields = ['id', 'name', 'address', 'phone', 'email', 'website', 'logo_url'];
        $hash_data = [];
        
        foreach ($hash_fields as $field) {
            $hash_data[$field] = $agency_data[$field] ?? '';
        }
        
        return md5(serialize($hash_data));
    }
    
    /**
     * Get agency manager statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        return $this->stats;
    }
    
    /**
     * Reset statistics
     */
    public function reset_stats() {
        $this->init_stats();
    }
    
    /**
     * Get all agencies
     * 
     * @return array Agency posts
     */
    public function get_all_agencies() {
        return get_posts([
            'post_type' => $this->agency_post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
    }
    
    /**
     * Get agency by import ID
     * 
     * @param string $agency_import_id Agency import ID
     * @return WP_Post|null Agency post or null
     */
    public function get_agency_by_import_id($agency_import_id) {
        $agency_post_id = $this->find_existing_agency($agency_import_id);
        
        if ($agency_post_id) {
            return get_post($agency_post_id);
        }
        
        return null;
    }
    
    /**
     * Delete orphaned agencies (agencies without properties)
     * 
     * @return int Number of deleted agencies
     */
    public function cleanup_orphaned_agencies() {
        $agencies = $this->get_all_agencies();
        $deleted_count = 0;
        
        foreach ($agencies as $agency) {
            $property_count = $this->count_agency_properties($agency->ID);
            
            if ($property_count === 0) {
                // Check if agency was imported (has import_id)
                $import_id = get_post_meta($agency->ID, 'agency_import_id', true);
                
                if (!empty($import_id)) {
                    wp_delete_post($agency->ID, true);
                    $deleted_count++;
                    
                    $this->logger->log('🏢 Orphaned agency deleted', 'info', [
                        'agency_id' => $agency->ID,
                        'agency_name' => $agency->post_title,
                        'import_id' => $import_id
                    ]);
                }
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Get version and capabilities
     * 
     * @return array Version info
     */
    public function get_version_info() {
        return [
            'version' => '1.0.0',
            'capabilities' => [
                'agency_creation' => true,
                'agency_updates' => true,
                'property_linking' => true,
                'change_detection' => true,
                'logo_processing' => true,
                'statistics_tracking' => true,
                'orphan_cleanup' => true
            ],
            'supported_post_type' => $this->agency_post_type,
            'wpresidence_integration' => true
        ];
    }
}
