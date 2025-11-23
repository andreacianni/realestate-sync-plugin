<?php
/**
 * Agency Parser Class
 * 
 * Extracts and validates agency data from XML <agenzia> section
 * 
 * @package RealEstate_Sync
 * @version 1.3.0
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RealEstate_Sync_Agency_Parser {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
    }
    
    /**
     * Extract agencies from XML dataset
     * 
     * @param SimpleXMLElement $xml_data
     * @return array Array of unique agencies data
     */
    public function extract_agencies_from_xml($xml_data) {
        $this->logger->log('Starting agency extraction from XML', 'info');
        
        $agencies = [];
        $agency_ids_seen = [];
        
        try {
            // Parse through all annuncio elements
            foreach ($xml_data->annuncio as $annuncio) {
                if (isset($annuncio->agenzia)) {
                    $agency_data = $this->parse_agency_data($annuncio->agenzia);
                    
                    if ($agency_data && !in_array($agency_data['id'], $agency_ids_seen)) {
                        $agencies[] = $agency_data;
                        $agency_ids_seen[] = $agency_data['id'];
                        
                        $this->logger->log("Extracted unique agency: {$agency_data['ragione_sociale']} (ID: {$agency_data['id']})", 'info');
                    }
                }
            }
            
            $this->logger->log('Agency extraction completed: ' . count($agencies) . ' unique agencies found', 'success');
            return $agencies;
            
        } catch (Exception $e) {
            $this->logger->log('Agency extraction failed: ' . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Parse single agency data from XML element
     * 
     * @param SimpleXMLElement $agenzia_xml
     * @return array|false Agency data array or false if invalid
     */
    public function parse_agency_data($agenzia_xml) {
        try {
            // Required fields validation
            $agency_id = (string) $agenzia_xml->id;
            $ragione_sociale = (string) $agenzia_xml->ragione_sociale;
            
            if (empty($agency_id) || empty($ragione_sociale)) {
                $this->logger->log('Agency missing required fields (id or ragione_sociale)', 'warning');
                return false;
            }
            
            // Check if agency is deleted
            $deleted = (string) $agenzia_xml->deleted;
            if ($deleted === '1') {
                $this->logger->log("Skipping deleted agency: {$ragione_sociale} (ID: {$agency_id})", 'info');
                return false;
            }
            
            // Extract all agency data with safe casting
            $agency_data = [
                'id' => $agency_id,
                'ragione_sociale' => $this->clean_cdata($ragione_sociale),
                'referente' => $this->clean_cdata((string) $agenzia_xml->referente),
                'iva' => (string) $agenzia_xml->iva,
                'comune' => $this->clean_cdata((string) $agenzia_xml->comune),
                'provincia' => (string) $agenzia_xml->provincia,
                'indirizzo' => $this->clean_cdata((string) $agenzia_xml->indirizzo),
                'email' => (string) $agenzia_xml->email,
                'url' => $this->clean_url((string) $agenzia_xml->url),
                'logo' => $this->clean_url((string) $agenzia_xml->logo),
                'telefono' => (string) $agenzia_xml->telefono,
                'cellulare' => (string) $agenzia_xml->cellulare,
                'deleted' => $deleted
            ];
            
            // Validate required data
            if ($this->validate_agency_data($agency_data)) {
                return $agency_data;
            } else {
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->log('Agency parsing failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Clean CDATA content
     * 
     * @param string $value
     * @return string
     */
    private function clean_cdata($value) {
        $value = trim($value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        return sanitize_text_field($value);
    }
    
    /**
     * Clean and validate URL
     * 
     * @param string $url
     * @return string
     */
    private function clean_url($url) {
        $url = trim($url);
        
        // Add http if missing
        if (!empty($url) && !preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }
        
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
    
    /**
     * Validate agency data integrity
     * 
     * @param array $agency_data
     * @return bool
     */
    private function validate_agency_data($agency_data) {
        // Required fields check
        if (empty($agency_data['id']) || empty($agency_data['ragione_sociale'])) {
            $this->logger->log('Agency validation failed: missing id or ragione_sociale', 'warning');
            return false;
        }
        
        // ID must be numeric
        if (!is_numeric($agency_data['id'])) {
            $this->logger->log('Agency validation failed: non-numeric ID', 'warning');
            return false;
        }
        
        // Email validation if provided
        if (!empty($agency_data['email']) && !filter_var($agency_data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->logger->log("Invalid email for agency {$agency_data['ragione_sociale']}: {$agency_data['email']}", 'warning');
            // Don't fail validation, just clear invalid email
            $agency_data['email'] = '';
        }
        
        return true;
    }
    
    /**
     * Get agency from property XML element
     * 
     * @param SimpleXMLElement $property_xml
     * @return array|false Agency data for the property
     */
    public function get_agency_for_property($property_xml) {
        if (isset($property_xml->agenzia)) {
            return $this->parse_agency_data($property_xml->agenzia);
        }
        
        return false;
    }
    
    /**
     * Extract agency ID from property
     * 
     * @param SimpleXMLElement $property_xml  
     * @return string|false Agency ID or false if not found
     */
    public function get_agency_id_for_property($property_xml) {
        if (isset($property_xml->agenzia->id)) {
            return (string) $property_xml->agenzia->id;
        }
        
        return false;
    }
    
    /**
     * Log agency statistics
     * 
     * @param array $agencies
     */
    public function log_agency_statistics($agencies) {
        $stats = [
            'total_agencies' => count($agencies),
            'with_logo' => 0,
            'with_email' => 0,
            'with_website' => 0,
            'provinces' => []
        ];
        
        foreach ($agencies as $agency) {
            if (!empty($agency['logo'])) $stats['with_logo']++;
            if (!empty($agency['email'])) $stats['with_email']++;
            if (!empty($agency['url'])) $stats['with_website']++;
            
            if (!empty($agency['provincia']) && !in_array($agency['provincia'], $stats['provinces'])) {
                $stats['provinces'][] = $agency['provincia'];
            }
        }
        
        $this->logger->log('Agency Statistics: ' . json_encode($stats), 'info');
    }
}
