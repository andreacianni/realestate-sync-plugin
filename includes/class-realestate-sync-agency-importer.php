<?php
/**
 * Agency Importer Class
 * 
 * Imports agencies into WpResidence estate_agent Custom Post Type
 * Handles duplicate prevention and WordPress integration
 * 
 * @package RealEstate_Sync
 * @version 1.3.0
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RealEstate_Sync_Agency_Importer {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Media deduplicator instance
     */
    private $media_deduplicator;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        $this->media_deduplicator = new RealEstate_Sync_Media_Deduplicator();
    }
    
    /**
     * Import agencies into WordPress
     * 
     * @param array $agencies Array of agency data from parser
     * @return array Import results with statistics
     */
    public function import_agencies($agencies) {
        $this->logger->log('Starting agency import: ' . count($agencies) . ' agencies to process', 'info');
        
        $results = [
            'total_agencies' => count($agencies),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'agency_ids' => []
        ];
        
        foreach ($agencies as $agency_data) {
            $result = $this->import_single_agency($agency_data);
            
            if ($result['success']) {
                $results['agency_ids'][] = $result['agent_id'];
                
                if ($result['action'] === 'created') {
                    $results['imported']++;
                } elseif ($result['action'] === 'updated') {
                    $results['updated']++;
                } else {
                    $results['skipped']++;
                }
            } else {
                $results['errors']++;
            }
        }
        
        $this->logger->log('Agency import completed: ' . json_encode($results), 'success');
        
        return $results;
    }
    
    /**
     * Import single agency
     * 
     * @param array $agency_data
     * @return array Result with success status and agent ID
     */
    public function import_single_agency($agency_data) {
        try {
            // Check if agency already exists
            $existing_agent_id = $this->find_existing_agent($agency_data['id']);
            
            if ($existing_agent_id) {
                return $this->update_existing_agent($existing_agent_id, $agency_data);
            } else {
                return $this->create_new_agent($agency_data);
            }
            
        } catch (Exception $e) {
            $this->logger->log("Agency import failed for {$agency_data['ragione_sociale']}: " . $e->getMessage(), 'error');
            return ['success' => false, 'action' => 'error', 'agent_id' => null];
        }
    }
    
    /**
     * Find existing agent by XML ID
     * 
     * @param string $agency_xml_id
     * @return int|false Agent post ID or false if not found
     */
    private function find_existing_agent($agency_xml_id) {
        $agents = get_posts([
            'post_type' => 'estate_agent',
            'meta_query' => [
                [
                    'key' => 'agency_xml_id',
                    'value' => $agency_xml_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);
        
        return !empty($agents) ? $agents[0]->ID : false;
    }
    
    /**
     * Create new agent
     * 
     * @param array $agency_data
     * @return array
     */
    private function create_new_agent($agency_data) {
        $agent_post = [
            'post_title' => $agency_data['ragione_sociale'],
            'post_content' => $this->generate_agent_description($agency_data),
            'post_status' => 'publish',
            'post_type' => 'estate_agent',
            'meta_input' => $this->prepare_agent_meta($agency_data)
        ];
        
        $agent_id = wp_insert_post($agent_post);
        
        if (is_wp_error($agent_id)) {
            $this->logger->log("Failed to create agent: " . $agent_id->get_error_message(), 'error');
            return ['success' => false, 'action' => 'error', 'agent_id' => null];
        }
        
        // Import logo if available
        $this->import_agent_logo($agent_id, $agency_data);
        
        $this->logger->log("Created new agent: {$agency_data['ragione_sociale']} (ID: {$agent_id})", 'success');
        
        return ['success' => true, 'action' => 'created', 'agent_id' => $agent_id];
    }
    
    /**
     * Update existing agent
     * 
     * @param int $agent_id
     * @param array $agency_data
     * @return array
     */
    private function update_existing_agent($agent_id, $agency_data) {
        // Check if update is needed
        if (!$this->needs_update($agent_id, $agency_data)) {
            $this->logger->log("Agent up to date, skipping: {$agency_data['ragione_sociale']} (ID: {$agent_id})", 'info');
            return ['success' => true, 'action' => 'skipped', 'agent_id' => $agent_id];
        }
        
        $agent_post = [
            'ID' => $agent_id,
            'post_title' => $agency_data['ragione_sociale'],
            'post_content' => $this->generate_agent_description($agency_data)
        ];
        
        $result = wp_update_post($agent_post);
        
        if (is_wp_error($result)) {
            $this->logger->log("Failed to update agent: " . $result->get_error_message(), 'error');
            return ['success' => false, 'action' => 'error', 'agent_id' => null];
        }
        
        // Update meta fields
        $this->update_agent_meta($agent_id, $agency_data);
        
        // Update logo if changed
        $this->import_agent_logo($agent_id, $agency_data);
        
        $this->logger->log("Updated agent: {$agency_data['ragione_sociale']} (ID: {$agent_id})", 'success');
        
        return ['success' => true, 'action' => 'updated', 'agent_id' => $agent_id];
    }
    
    /**
     * Prepare agent meta data for WpResidence
     * 
     * @param array $agency_data
     * @return array
     */
    private function prepare_agent_meta($agency_data) {
        return [
            // Core identification
            'agency_xml_id' => $agency_data['id'],
            'agency_xml_last_update' => current_time('mysql'),
            
            // Contact information
            'agent_email' => $agency_data['email'],
            'agent_phone' => $this->format_phone($agency_data['telefono']),
            'agent_mobile' => $this->format_phone($agency_data['cellulare']),
            'agent_website' => $agency_data['url'],
            
            // Address information
            'agent_address' => $agency_data['indirizzo'],
            'agent_city' => $agency_data['comune'],
            'agent_state' => $agency_data['provincia'],
            
            // Business information
            'agent_vat' => $agency_data['iva'],
            'agent_contact_person' => $agency_data['referente'],
            
            // Import tracking
            'agent_import_source' => 'gestionaleimmobiliare',
            'agent_import_date' => current_time('mysql')
        ];
    }
    
    /**
     * Update agent meta data
     * 
     * @param int $agent_id
     * @param array $agency_data
     */
    private function update_agent_meta($agent_id, $agency_data) {
        $meta_data = $this->prepare_agent_meta($agency_data);
        
        foreach ($meta_data as $key => $value) {
            update_post_meta($agent_id, $key, $value);
        }
    }
    
    /**
     * Check if agent needs update
     * 
     * @param int $agent_id
     * @param array $agency_data
     * @return bool
     */
    private function needs_update($agent_id, $agency_data) {
        // Always update if email or phone changed (key contact info)
        $current_email = get_post_meta($agent_id, 'agent_email', true);
        $current_phone = get_post_meta($agent_id, 'agent_phone', true);
        $current_title = get_the_title($agent_id);
        
        return (
            $current_title !== $agency_data['ragione_sociale'] ||
            $current_email !== $agency_data['email'] ||
            $current_phone !== $this->format_phone($agency_data['telefono'])
        );
    }
    
    /**
     * Format phone number
     * 
     * @param string $phone
     * @return string
     */
    private function format_phone($phone) {
        if (empty($phone)) {
            return '';
        }
        
        // Clean phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Add +39 if missing for Italian numbers
        if (!empty($phone) && !str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '39')) {
                $phone = '+' . $phone;
            } elseif (str_starts_with($phone, '0') || str_starts_with($phone, '3')) {
                $phone = '+39' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Generate agent description
     * 
     * @param array $agency_data
     * @return string
     */
    private function generate_agent_description($agency_data) {
        $description = "Agenzia immobiliare: {$agency_data['ragione_sociale']}";
        
        if (!empty($agency_data['referente'])) {
            $description .= "\nReferente: {$agency_data['referente']}";
        }
        
        if (!empty($agency_data['indirizzo']) && !empty($agency_data['comune'])) {
            $description .= "\nIndirizzo: {$agency_data['indirizzo']}, {$agency_data['comune']}";
            if (!empty($agency_data['provincia'])) {
                $description .= " ({$agency_data['provincia']})";
            }
        }
        
        $contacts = [];
        if (!empty($agency_data['telefono'])) {
            $contacts[] = "Tel: {$agency_data['telefono']}";
        }
        if (!empty($agency_data['cellulare'])) {
            $contacts[] = "Cell: {$agency_data['cellulare']}";
        }
        if (!empty($agency_data['email'])) {
            $contacts[] = "Email: {$agency_data['email']}";
        }
        
        if (!empty($contacts)) {
            $description .= "\n" . implode(' - ', $contacts);
        }
        
        return $description;
    }
    
    /**
     * Import agent logo
     * 
     * @param int $agent_id
     * @param array $agency_data
     */
    private function import_agent_logo($agent_id, $agency_data) {
        if (empty($agency_data['logo'])) {
            return;
        }
        
        // Import logo using media deduplicator
        $logo_attachment_id = $this->media_deduplicator->import_media_without_duplication(
            $agency_data['logo'],
            $agent_id,
            'logo'
        );
        
        if ($logo_attachment_id) {
            // Set as agent logo in WpResidence format
            update_post_meta($agent_id, '_agent_logo_attachment_id', $logo_attachment_id);
            
            // Also store for WpResidence compatibility
            $logo_url = wp_get_attachment_url($logo_attachment_id);
            if ($logo_url) {
                update_post_meta($agent_id, 'agent_logo', $logo_url);
            }
            
            $this->logger->log("Logo imported for agent {$agent_id}: {$agency_data['logo']}", 'success');
        }
    }
    
    /**
     * Get agent ID by XML ID
     * 
     * @param string $agency_xml_id
     * @return int|false
     */
    public function get_agent_id_by_xml_id($agency_xml_id) {
        return $this->find_existing_agent($agency_xml_id);
    }
    
    /**
     * Get all imported agents
     * 
     * @return array Array of agent IDs with XML IDs
     */
    public function get_all_imported_agents() {
        $agents = get_posts([
            'post_type' => 'estate_agent',
            'meta_query' => [
                [
                    'key' => 'agency_xml_id',
                    'compare' => 'EXISTS'
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);
        
        $result = [];
        foreach ($agents as $agent) {
            $xml_id = get_post_meta($agent->ID, 'agency_xml_id', true);
            $result[$xml_id] = $agent->ID;
        }
        
        return $result;
    }
    
    /**
     * Clean up deleted agencies
     * 
     * @param array $current_agency_ids Array of XML IDs currently in feed
     * @return int Number of agencies deleted
     */
    public function cleanup_deleted_agencies($current_agency_ids) {
        $imported_agents = $this->get_all_imported_agents();
        $deleted_count = 0;
        
        foreach ($imported_agents as $xml_id => $agent_id) {
            if (!in_array($xml_id, $current_agency_ids)) {
                // Agency no longer in feed, mark as deleted or remove
                wp_update_post([
                    'ID' => $agent_id,
                    'post_status' => 'draft'
                ]);
                
                update_post_meta($agent_id, 'agency_xml_deleted', current_time('mysql'));
                
                $this->logger->log("Marked agent as deleted: XML ID {$xml_id}, WP ID {$agent_id}", 'info');
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Get import statistics
     * 
     * @return array
     */
    public function get_import_statistics() {
        global $wpdb;
        
        $total_agents = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = 'agency_xml_id'"
        );
        
        $with_logo = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_agent_logo_attachment_id' 
             AND meta_value != ''"
        );
        
        return [
            'total_imported_agents' => (int) $total_agents,
            'agents_with_logo' => (int) $with_logo,
            'logo_percentage' => $total_agents > 0 ? round($with_logo / $total_agents * 100, 1) : 0
        ];
    }
}
