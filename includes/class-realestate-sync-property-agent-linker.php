<?php
/**
 * Property Agent Linker Class
 * 
 * Links properties to agents using WpResidence property_agent meta field
 * Handles the association between imported properties and agencies
 * 
 * @package RealEstate_Sync
 * @version 1.3.0
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RealEstate_Sync_Property_Agent_Linker {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Agency importer instance
     */
    private $agency_importer;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        $this->agency_importer = new RealEstate_Sync_Agency_Importer();
    }
    
    /**
     * Link property to agent based on XML agency ID
     * 
     * @param int $property_id WordPress property post ID
     * @param string $agency_xml_id XML agency ID from feed
     * @return bool Success status
     */
    public function link_property_to_agent($property_id, $agency_xml_id) {
        if (empty($property_id) || empty($agency_xml_id)) {
            return false;
        }
        
        // Find WordPress agent ID by XML ID
        $agent_id = $this->agency_importer->get_agent_id_by_xml_id($agency_xml_id);
        
        if (!$agent_id) {
            $this->logger->log("Agent not found for XML ID {$agency_xml_id}, property {$property_id} not linked", 'warning');
            return false;
        }
        
        // Update property_agent meta field (WpResidence standard)
        $result = update_post_meta($property_id, 'property_agent', $agent_id);
        
        if ($result !== false) {
            // Also store the XML agency ID for reference
            update_post_meta($property_id, 'property_agency_xml_id', $agency_xml_id);
            update_post_meta($property_id, 'property_agent_link_date', current_time('mysql'));
            
            $this->logger->log("Property {$property_id} linked to agent {$agent_id} (XML ID: {$agency_xml_id})", 'success');
            return true;
        } else {
            $this->logger->log("Failed to link property {$property_id} to agent {$agent_id}", 'error');
            return false;
        }
    }
    
    /**
     * Link property using XML property data
     * 
     * @param int $property_id WordPress property post ID
     * @param SimpleXMLElement $property_xml XML property data
     * @return bool Success status
     */
    public function link_property_from_xml($property_id, $property_xml) {
        // Extract agency ID from XML
        if (!isset($property_xml->agenzia->id)) {
            $this->logger->log("No agency data in XML for property {$property_id}", 'info');
            return false;
        }
        
        $agency_xml_id = (string) $property_xml->agenzia->id;
        return $this->link_property_to_agent($property_id, $agency_xml_id);
    }
    
    /**
     * Bulk link properties to agents
     * 
     * @param array $property_agent_pairs Array of [property_id => agency_xml_id] pairs
     * @return array Results with statistics
     */
    public function bulk_link_properties($property_agent_pairs) {
        $this->logger->log('Starting bulk property-agent linking: ' . count($property_agent_pairs) . ' properties', 'info');
        
        $results = [
            'total_properties' => count($property_agent_pairs),
            'linked' => 0,
            'failed' => 0,
            'skipped' => 0
        ];
        
        foreach ($property_agent_pairs as $property_id => $agency_xml_id) {
            if ($this->link_property_to_agent($property_id, $agency_xml_id)) {
                $results['linked']++;
            } else {
                $results['failed']++;
            }
        }
        
        $this->logger->log('Bulk linking completed: ' . json_encode($results), 'success');
        return $results;
    }
    
    /**
     * Get agent ID for property
     * 
     * @param int $property_id
     * @return int|false Agent ID or false if not linked
     */
    public function get_agent_for_property($property_id) {
        $agent_id = get_post_meta($property_id, 'property_agent', true);
        return !empty($agent_id) ? (int) $agent_id : false;
    }
    
    /**
     * Get properties for agent
     * 
     * @param int $agent_id
     * @return array Array of property IDs
     */
    public function get_properties_for_agent($agent_id) {
        $properties = get_posts([
            'post_type' => 'estate_property',
            'meta_query' => [
                [
                    'key' => 'property_agent',
                    'value' => $agent_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        return $properties;
    }
    
    /**
     * Unlink property from agent
     * 
     * @param int $property_id
     * @return bool Success status
     */
    public function unlink_property($property_id) {
        $current_agent = $this->get_agent_for_property($property_id);
        
        if (!$current_agent) {
            return true; // Already unlinked
        }
        
        delete_post_meta($property_id, 'property_agent');
        delete_post_meta($property_id, 'property_agency_xml_id');
        update_post_meta($property_id, 'property_agent_unlink_date', current_time('mysql'));
        
        $this->logger->log("Property {$property_id} unlinked from agent {$current_agent}", 'info');
        return true;
    }
    
    /**
     * Re-link all properties based on current XML data
     * 
     * @param SimpleXMLElement $xml_data Full XML dataset
     * @return array Results with statistics
     */
    public function relink_all_properties($xml_data) {
        $this->logger->log('Starting complete property-agent relinking', 'info');
        
        $results = [
            'total_processed' => 0,
            'linked' => 0,
            'failed' => 0,
            'unchanged' => 0
        ];
        
        foreach ($xml_data->annuncio as $annuncio) {
            $results['total_processed']++;
            
            // Get property by XML ID
            $property_xml_id = (string) $annuncio->info->id;
            $property_id = $this->find_property_by_xml_id($property_xml_id);
            
            if (!$property_id) {
                continue; // Property not imported
            }
            
            // Get current and new agent links
            $current_agent = $this->get_agent_for_property($property_id);
            $new_agency_xml_id = isset($annuncio->agenzia->id) ? (string) $annuncio->agenzia->id : null;
            
            if (!$new_agency_xml_id) {
                // No agency in XML, unlink if currently linked
                if ($current_agent) {
                    $this->unlink_property($property_id);
                }
                continue;
            }
            
            // Find new agent ID
            $new_agent_id = $this->agency_importer->get_agent_id_by_xml_id($new_agency_xml_id);
            
            if (!$new_agent_id) {
                $results['failed']++;
                continue;
            }
            
            // Check if linking is needed
            if ($current_agent === $new_agent_id) {
                $results['unchanged']++;
                continue;
            }
            
            // Link to new agent
            if ($this->link_property_to_agent($property_id, $new_agency_xml_id)) {
                $results['linked']++;
            } else {
                $results['failed']++;
            }
        }
        
        $this->logger->log('Complete relinking finished: ' . json_encode($results), 'success');
        return $results;
    }
    
    /**
     * Find property by XML ID
     * 
     * @param string $property_xml_id
     * @return int|false Property post ID or false if not found
     */
    private function find_property_by_xml_id($property_xml_id) {
        $properties = get_posts([
            'post_type' => 'estate_property',
            'meta_query' => [
                [
                    'key' => 'property_xml_id',
                    'value' => $property_xml_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);
        
        return !empty($properties) ? $properties[0] : false;
    }
    
    /**
     * Update property-agent links for single import
     * 
     * @param array $imported_properties Array of property data with XML IDs
     * @param array $imported_agencies Array of agency data
     * @return array Linking results
     */
    public function update_links_for_import($imported_properties, $imported_agencies) {
        $this->logger->log('Updating property-agent links for import batch', 'info');
        
        $results = [
            'total_properties' => count($imported_properties),
            'linked' => 0,
            'failed' => 0,
            'no_agency' => 0
        ];
        
        foreach ($imported_properties as $property_data) {
            if (empty($property_data['agency_xml_id'])) {
                $results['no_agency']++;
                continue;
            }
            
            if ($this->link_property_to_agent($property_data['wordpress_id'], $property_data['agency_xml_id'])) {
                $results['linked']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Get linking statistics
     * 
     * @return array Statistics about property-agent links
     */
    public function get_linking_statistics() {
        global $wpdb;
        
        // Total properties
        $total_properties = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'estate_property'"
        );
        
        // Properties with agents
        $linked_properties = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = 'property_agent' AND meta_value != ''"
        );
        
        // Properties from XML import
        $xml_properties = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = 'property_xml_id'"
        );
        
        // XML properties with agents
        $xml_linked = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm1
             INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
             WHERE pm1.meta_key = 'property_xml_id' 
             AND pm2.meta_key = 'property_agent' 
             AND pm2.meta_value != ''"
        );
        
        return [
            'total_properties' => (int) $total_properties,
            'linked_properties' => (int) $linked_properties,
            'xml_properties' => (int) $xml_properties,
            'xml_linked_properties' => (int) $xml_linked,
            'link_percentage' => $total_properties > 0 ? round($linked_properties / $total_properties * 100, 1) : 0,
            'xml_link_percentage' => $xml_properties > 0 ? round($xml_linked / $xml_properties * 100, 1) : 0
        ];
    }
    
    /**
     * Validate all property-agent links
     * 
     * @return array Validation results
     */
    public function validate_all_links() {
        $this->logger->log('Validating all property-agent links', 'info');
        
        $results = [
            'total_checked' => 0,
            'valid_links' => 0,
            'invalid_links' => 0,
            'missing_agents' => 0,
            'orphaned_properties' => []
        ];
        
        // Get all linked properties
        global $wpdb;
        $linked_properties = $wpdb->get_results(
            "SELECT post_id, meta_value as agent_id FROM {$wpdb->postmeta} 
             WHERE meta_key = 'property_agent' AND meta_value != ''"
        );
        
        foreach ($linked_properties as $link) {
            $results['total_checked']++;
            
            // Check if agent exists
            $agent_exists = get_post_status($link->agent_id);
            
            if (!$agent_exists) {
                $results['missing_agents']++;
                $results['orphaned_properties'][] = $link->post_id;
                $this->logger->log("Property {$link->post_id} linked to non-existent agent {$link->agent_id}", 'warning');
            } else {
                $results['valid_links']++;
            }
        }
        
        $this->logger->log('Link validation completed: ' . json_encode($results), 'info');
        return $results;
    }
    
    /**
     * Clean orphaned property links
     * 
     * @return int Number of links cleaned
     */
    public function clean_orphaned_links() {
        $validation = $this->validate_all_links();
        $cleaned = 0;
        
        foreach ($validation['orphaned_properties'] as $property_id) {
            $this->unlink_property($property_id);
            $cleaned++;
        }
        
        $this->logger->log("Cleaned {$cleaned} orphaned property-agent links", 'info');
        return $cleaned;
    }
}
