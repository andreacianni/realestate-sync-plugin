<?php
/**
 * RealEstate Sync Add-On Adapter
 * 
 * Converts XML data from GestionaleImmobiliare.it to Add-On expected format
 * Bridge between our XML Parser and Add-On tested functions
 * 
 * @package RealEstate_Sync
 * @subpackage AddOn_Integration
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_AddOn_Adapter {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * GestionaleImmobiliare.it features mapping to Add-On feature names
     * Key: GI Feature ID, Value: Add-On feature name
     */
    private $gi_features_mapping = [
        // Core amenities
        17 => 'giardino',
        66 => 'piscina', 
        15 => 'arredato',
        16 => 'riscaldamento-autonomo',
        62 => 'vista-panoramica',
        5  => 'box-garage',
        13 => 'ascensore',
        14 => 'aria-condizionata',
        
        // Advanced features
        21 => 'riscaldamento-a-pavimento',
        23 => 'allarme',
        46 => 'camino',
        8  => 'cantina',
        36 => 'montagna',
        37 => 'lago',
        88 => 'domotica',
        90 => 'porta-blindata',
        
        // Property specific
        18 => 'balcone',
        19 => 'terrazza',
        20 => 'mansarda',
        22 => 'videocitofono',
        24 => 'posto-auto',
        25 => 'doppi-vetri',
        26 => 'parquet',
        27 => 'cotto',
        28 => 'marmo',
        
        // Location features
        38 => 'centro-storico',
        39 => 'zona-tranquilla',
        40 => 'vicino-servizi',
        41 => 'vicino-scuole',
        42 => 'vicino-trasporti',
        43 => 'parcheggio-pubblico',
        
        // Energy & utilities
        89 => 'pannelli-solari',
        91 => 'fibra-ottica',
        92 => 'citofono',
        93 => 'inferriate',
        94 => 'zanzariere'
    ];
    
    /**
     * Energy class mapping from GI to standard format
     */
    private $energy_class_mapping = [
        'nc'    => 'NC',
        'g'     => 'G',
        'f'     => 'F', 
        'e'     => 'E',
        'd'     => 'D',
        'c'     => 'C',
        'b'     => 'B',
        'a4'    => 'A4',
        'a3'    => 'A3', 
        'a2'    => 'A2',
        'a1'    => 'A1',
        'a'     => 'A'
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
    }
    
    /**
     * Convert XML property data to Add-On expected format
     * 
     * @param array|SimpleXMLElement $xml_data Complete XML annuncio data
     * @return array Add-On formatted data
     */
    public function convert_xml_to_addon_format($xml_data) {
        $this->logger->log("Converting XML property to Add-On format", 'debug');
        
        $addon_data = [];
        
        try {
            // Handle both array and XML input
            if ($xml_data instanceof SimpleXMLElement) {
                $info = $xml_data->info;
                $agency = $xml_data->agenzia;
                $features = $xml_data->info_inserite;
                $images = $xml_data->file_allegati;
            } else {
                // Test data array format
                $info = $xml_data;
                $agency = $xml_data['agency_data'] ?? [];
                $features = $xml_data['info_inserite'] ?? [];
                $images = $xml_data['file_allegati'] ?? [];
            }
            
            // ==========================================
            // BASIC PROPERTY FIELDS
            // ==========================================
            
            // Price - ensure numeric format
            $addon_data['property_price'] = $this->clean_price($info['price'] ?? 0);
            
            // Size - use mq field from info section
            $addon_data['property_size'] = floatval($info['mq'] ?? 0);
            
            // Property type - map GI category to WpResidence
            $addon_data['property_category'] = $this->map_property_category($info['categorie_id'] ?? 11);
            
            // Property status - default to sale unless rent
            $addon_data['property_status'] = 'sale'; // Default for now
            
            // ==========================================
            // ROOM COUNTS WITH SPECIAL HANDLING
            // ==========================================
            
            // Bedrooms (info id="1")
            $addon_data['property_bedrooms'] = $this->get_room_count($features, 1, 'bedrooms');
            
            // Bathrooms (info id="2")  
            $addon_data['property_bathrooms'] = $this->get_room_count($features, 2, 'bathrooms');
            
            // Total rooms (info id="65")
            $addon_data['property_rooms'] = $this->get_room_count($features, 65, 'rooms');
            
            // ==========================================
            // LOCATION DATA
            // ==========================================
            
            // Full address construction from info section
            $address_parts = [];
            if (!empty($info['indirizzo'])) $address_parts[] = (string)$info['indirizzo'];
            if (!empty($info['zona'])) $address_parts[] = (string)$info['zona'];
            $addon_data['property_address'] = implode(', ', $address_parts) ?: 'Trentino Alto Adige';
            
            // Location search preference
            $has_coords = !empty($info['latitude']) && !empty($info['longitude']);
            $addon_data['location_settings'] = $has_coords ? 'search_by_coordinates' : 'search_by_address';
            
            // Coordinates
            $addon_data['_property_latitude'] = floatval($info['latitude'] ?? 0);
            $addon_data['_property_longitude'] = floatval($info['longitude'] ?? 0);
            
            // Address components - map from comune_istat to readable names
            $addon_data['property_city'] = $this->map_comune_istat($info['comune_istat'] ?? '');
            $addon_data['property_state'] = 'Trentino Alto Adige';
            $addon_data['property_zip'] = '';
            $addon_data['property_country'] = 'Italy';
            
            // ==========================================
            // FEATURES CONVERSION (CRITICAL)
            // ==========================================
            
            // Convert XML features array to CSV string for Add-On
            $addon_data['property_features'] = $this->convert_features_to_csv($features);
            
            // ==========================================
            // AGENT/AGENCY SYSTEM
            // ==========================================
            
            // Agent name from agency data
            $addon_data['property_agent'] = (string)($agency['ragione_sociale'] ?? '');
            $addon_data['property_agent_or_agency'] = 'estate_agency';
            
            // Agent contact info for profile creation
            $addon_data['agent_email'] = (string)($agency['email'] ?? '');
            $addon_data['agent_phone'] = (string)($agency['telefono'] ?? '');
            $addon_data['agent_mobile'] = (string)($agency['cellulare'] ?? '');
            $addon_data['agent_website'] = (string)($agency['url'] ?? '');
            $addon_data['agent_address'] = $this->build_agency_address($agency);
            
            // ==========================================
            // CUSTOM FIELDS (_custom_details_ pattern)
            // ==========================================
            
            // Energy class from APE data
            $addon_data['_custom_details_energy_class'] = $this->map_energy_class($info);
            
            // Construction year from age field
            $addon_data['_custom_details_construction_year'] = (string)($info['age'] ?? '');
            $addon_data['_custom_details_renovation_year'] = '';
            $addon_data['_custom_details_floor'] = '';
            $addon_data['_custom_details_total_floors'] = '';
            $addon_data['_custom_details_heating'] = 'Centralizzato'; // Default
            $addon_data['_custom_details_orientation'] = '';
            $addon_data['_custom_details_view'] = '';
            
            // ==========================================
            // PROPERTY DESCRIPTION
            // ==========================================
            
            $addon_data['property_description'] = $this->clean_description((string)($info['description'] ?? ''));
            
            $this->logger->log("XML to Add-On conversion completed successfully", 'info');
            
            return $addon_data;
            
        } catch (Exception $e) {
            $this->logger->log("Error converting XML to Add-On format: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Clean and format price value
     */
    private function clean_price($price) {
        // Remove any non-numeric characters except decimals
        $price = preg_replace('/[^\d.,]/', '', $price);
        $price = str_replace(',', '.', $price);
        return floatval($price);
    }
    
    /**
     * Get best available surface area from XML data
     */
    private function get_best_surface_area($xml_property) {
        // Priority order: commercial area > total area > living area
        $areas = [
            'superficie_commerciale',
            'superficie_totale', 
            'superficie_abitabile',
            'superficie_catastale'
        ];
        
        foreach ($areas as $area_field) {
            if (!empty($xml_property[$area_field]) && $xml_property[$area_field] > 0) {
                return floatval($xml_property[$area_field]);
            }
        }
        
        return 0;
    }
    
    /**
     * Get room count with special handling for -1 values
     */
    private function get_room_count($features, $feature_id, $room_type) {
        $count = $this->get_feature_value($features, $feature_id);
        
        // Handle special case: -1 means "4 or more" in GI system
        if ($count == -1) {
            $this->logger->log("Property has 4+ {$room_type} (GI value: -1)", 'debug');
            return 4;
        }
        
        return max(0, intval($count));
    }
    
    /**
     * Get feature value from XML info_inserite section
     */
    private function get_feature_value($features, $feature_id) {
        if (empty($features)) {
            return 0;
        }
        
        // Handle SimpleXMLElement
        if ($features instanceof SimpleXMLElement) {
            foreach ($features->info as $info) {
                $id = (string)$info['id'];
                if ($id == $feature_id) {
                    return intval((string)$info->valore_assegnato);
                }
            }
        } else {
            // Handle array format
            foreach ($features as $feature) {
                if (isset($feature['id']) && $feature['id'] == $feature_id) {
                    return intval($feature['valore_assegnato'] ?? 0);
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Build full address string from XML components
     */
    private function build_full_address($xml_property) {
        $address_parts = [];
        
        if (!empty($xml_property['indirizzo'])) {
            $address_parts[] = $xml_property['indirizzo'];
        }
        
        if (!empty($xml_property['comune'])) {
            $address_parts[] = $xml_property['comune'];
        }
        
        if (!empty($xml_property['provincia'])) {
            $address_parts[] = $xml_property['provincia'];
        }
        
        return implode(', ', $address_parts);
    }
    
    /**
     * Map comune ISTAT code to readable name
     */
    private function map_comune_istat($istat_code) {
        // Sample mapping - can be expanded
        $comune_mapping = [
            '022034' => 'Caldonazzo',
            '022117' => 'Mezzolombardo', 
            '022183' => 'Storo',
            '022205' => 'Trento',
            '021008' => 'Bolzano'
        ];
        
        return $comune_mapping[$istat_code] ?? 'Trentino Alto Adige';
    }
    
    /**
     * Check if property has valid coordinates
     */
    private function has_coordinates($xml_property) {
        $lat = floatval($xml_property['latitude'] ?? 0);
        $lng = floatval($xml_property['longitude'] ?? 0);
        return ($lat !== 0.0 && $lng !== 0.0);
    }
    
    /**
     * Convert XML features array to CSV string for Add-On
     * This is CRITICAL for features system to work
     */
    private function convert_features_to_csv($features) {
        $feature_names = [];
        
        if (empty($features)) {
            return '';
        }
        
        // Handle SimpleXMLElement
        if ($features instanceof SimpleXMLElement) {
            foreach ($features->info as $info) {
                $feature_id = intval((string)$info['id']);
                $feature_value = intval((string)$info->valore_assegnato);
                
                // Only include features that are active (value >= 1)
                if ($feature_value >= 1 && isset($this->gi_features_mapping[$feature_id])) {
                    $feature_name = $this->gi_features_mapping[$feature_id];
                    $feature_names[] = $feature_name;
                    $this->logger->log("Added feature: {$feature_name} (GI ID: {$feature_id}, value: {$feature_value})", 'debug');
                }
            }
        } else {
            // Handle array format
            foreach ($features as $feature) {
                $feature_id = $feature['id'] ?? 0;
                $feature_value = $feature['valore_assegnato'] ?? 0;
                
                if ($feature_value >= 1 && isset($this->gi_features_mapping[$feature_id])) {
                    $feature_name = $this->gi_features_mapping[$feature_id];
                    $feature_names[] = $feature_name;
                    $this->logger->log("Added feature: {$feature_name} (GI ID: {$feature_id})", 'debug');
                }
            }
        }
        
        $csv_features = implode(',', $feature_names);
        $this->logger->log("Features CSV: {$csv_features}", 'debug');
        
        return $csv_features;
    }
    
    /**
     * Map GI property category to WpResidence category
     */
    private function map_property_category($gi_category) {
        $category_mapping = [
            1  => 'house',          // Casa singola
            2  => 'house',          // Bifamiliare  
            11 => 'apartment',      // Appartamento
            12 => 'apartment',      // Attico
            18 => 'villa',          // Villa
            19 => 'land',           // Terreno
            14 => 'commercial',     // Negozio
            17 => 'office',         // Ufficio
            8  => 'garage'          // Garage
        ];
        
        return $category_mapping[$gi_category] ?? 'apartment';
    }
    
    /**
     * Map property status (sale/rent)
     */
    private function map_property_status($xml_property) {
        $contratto = strtolower($xml_property['contratto'] ?? '');
        return ($contratto === 'affitto' || $contratto === 'rent') ? 'rent' : 'sale';
    }
    
    /**
     * Map province abbreviation to full name
     */
    private function map_province($provincia_abbr) {
        $province_mapping = [
            'TN' => 'Trento',
            'BZ' => 'Bolzano',
            'VR' => 'Verona', 
            'VI' => 'Vicenza',
            'PD' => 'Padova',
            'TV' => 'Treviso'
        ];
        
        return $province_mapping[$provincia_abbr] ?? $provincia_abbr;
    }
    
    /**
     * Map energy class from GI to standard format
     */
    private function map_energy_class($info) {
        // Get energy class from ape attribute
        if (isset($info['ape']) && isset($info['ape']['classe'])) {
            $gi_class = strtolower((string)$info['ape']['classe']);
        } else if (is_array($info) && isset($info['ape'])) {
            $gi_class = strtolower($info['ape']);
        } else {
            $gi_class = 'nd';
        }
        
        return $this->energy_class_mapping[$gi_class] ?? 'NC';
    }
    
    /**
     * Build agency address from agency data
     */
    private function build_agency_address($agency_data) {
        $address_parts = [];
        
        if (!empty($agency_data['indirizzo'])) {
            $address_parts[] = (string)$agency_data['indirizzo'];
        }
        
        if (!empty($agency_data['comune'])) {
            $address_parts[] = (string)$agency_data['comune'];
        }
        
        if (!empty($agency_data['provincia'])) {
            $address_parts[] = (string)$agency_data['provincia'];
        }
        
        return implode(', ', $address_parts);
    }
    
    /**
     * Format floor information
     */
    private function format_floor($xml_property) {
        $floor = $xml_property['piano'] ?? '';
        
        if (empty($floor)) {
            return '';
        }
        
        // Handle special floor values
        $floor_mapping = [
            'T' => 'Terra',
            'S' => 'Seminterrato', 
            'R' => 'Rialzato',
            'M' => 'Mezzanino'
        ];
        
        return $floor_mapping[$floor] ?? $floor;
    }
    
    /**
     * Map heating type
     */
    private function map_heating_type($xml_property) {
        $heating_id = $this->get_feature_value($xml_property, 16); // Riscaldamento autonomo
        
        if ($heating_id == 1) {
            return 'Autonomo';
        }
        
        // Check for floor heating
        $floor_heating = $this->get_feature_value($xml_property, 21);
        if ($floor_heating == 1) {
            return 'A pavimento';
        }
        
        return 'Centralizzato';
    }
    
    /**
     * Clean property description
     */
    private function clean_description($description) {
        // Remove HTML tags
        $description = strip_tags($description);
        
        // Clean up multiple spaces and newlines
        $description = preg_replace('/\s+/', ' ', $description);
        
        // Trim whitespace
        return trim($description);
    }
    
    /**
     * Get complete Add-On data array for property import
     * This method returns all fields needed for Add-On functions
     */
    public function get_complete_addon_data($xml_property) {
        $addon_data = $this->convert_xml_to_addon_format($xml_property);
        
        // Add any additional fields needed by specific Add-On functions
        $addon_data['import_id'] = $xml_property['id'] ?? '';
        $addon_data['original_xml'] = $xml_property; // Keep reference for debugging
        
        return $addon_data;
    }
}

