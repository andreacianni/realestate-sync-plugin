<?php
/**
 * RealEstate Sync Plugin - Property Mapper v3.1
 * 
 * MAPPING COMPLETO basato su database analysis reale
 * ENHANCED: Custom Fields Property Details Integration
 * 
 * @package RealEstateSync
 * @version 3.1.0
 * @author Andrea Cianni - Novacom
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

class RealEstate_Sync_Property_Mapper {
    
    private $logger;
    private $gi_categories;
    private $gi_features;
    private $energy_class_mapping;
    private $agency_manager;
    
    public function __construct($logger = null) {
        $this->logger = $logger ?: RealEstate_Sync_Logger::get_instance();
        
        // Initialize Agency Manager for direct property→agency mapping
        require_once dirname(__FILE__) . '/class-realestate-sync-agency-manager.php';
        $this->agency_manager = new RealEstate_Sync_Agency_Manager();
        
        $this->init_mappings();
        $this->logger->log('Property Mapper v3.1 initialized with Agency Manager integration + Custom Fields', 'info');
    }
    
    private function init_mappings() {
        // Official GI Categories mapping - Dynamic taxonomy creation
        $this->gi_categories = $this->get_category_mapping();
        
        // GI Features → WpResidence Features
        $this->gi_features = [
            17 => 'giardino',
            66 => 'piscina',
            15 => 'arredato',
            16 => 'riscaldamento-autonomo-centralizzato',
            62 => 'vista-panoramica',
            5 => 'box-o-garage',
            20 => 'box-o-garage',
            13 => 'ascensore',
            14 => 'aria-condizionata',
            21 => 'riscaldamento-a-pavimento',
            23 => 'allarme',
            46 => 'camino',
            8 => 'cantina',
            36 => 'montagna',
            37 => 'lago',
            88 => 'domotica',
            90 => 'porta-blindata'
        ];
        
        // Energy class mapping
        $this->energy_class_mapping = [
            1 => 'A+', 2 => 'A', 3 => 'B', 4 => 'C',
            5 => 'D', 6 => 'E', 7 => 'F', 8 => 'G',
            10 => 'A4', 11 => 'A3', 12 => 'A2', 13 => 'A1'
        ];
    }
    
    /**
     * Check if property is in enabled provinces
     */
    public function is_property_in_enabled_provinces($xml_property, $enabled_provinces = null) {
        $comune_istat = $xml_property['comune_istat'] ?? '';
        
        if (empty($comune_istat)) {
            return false;
        }
        
        if ($enabled_provinces === null) {
            $settings = get_option('realestate_sync_settings', []);
            $enabled_provinces = $settings['enabled_provinces'] ?? ['TN', 'BZ'];
        }
        
        $is_trento = (substr($comune_istat, 0, 3) === '022');
        $is_bolzano = (substr($comune_istat, 0, 3) === '021');
        
        return ($is_trento && in_array('TN', $enabled_provinces)) || 
               ($is_bolzano && in_array('BZ', $enabled_provinces));
    }
    
    /**
     * Map properties v3.1 - ENHANCED: Custom Fields Integration
     */
    public function map_properties($xml_properties) {
        $this->logger->log('Starting Property Mapper v3.1', 'info', [
            'input_count' => count($xml_properties)
        ]);
        
        $mapped_properties = [];
        $stats = ['success' => 0, 'skipped' => 0, 'errors' => 0];
        
        foreach ($xml_properties as $xml_property) {
            try {
                $mapped = $this->map_single_property_v3($xml_property);
                if ($mapped) {
                    $mapped_properties[] = $mapped;
                    $stats['success']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (Exception $e) {
                $stats['errors']++;
                $this->logger->log('Mapping error', 'error', [
                    'property_id' => $xml_property['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->log('Property Mapper v3.1 completed', 'info', $stats);
        
        return [
            'success' => true,
            'properties' => $mapped_properties,
            'stats' => $stats
        ];
    }
    
    /**
     * Map single property v3.0
     */
    private function map_single_property_v3($xml_property) {
        if (empty($xml_property['id'])) {
            return null;
        }
        
        // 🏢 AGENCY MANAGER v3.0: Process agency and get agency ID for direct association
        $agency_id = $this->process_agency_for_property($xml_property);
        $source_data = $xml_property;
        
        if ($agency_id) {
            $source_data['agency_id'] = $agency_id;
            $this->logger->log('🏢 Property Mapper: Agency processed and ID assigned', 'debug', [
                'property_id' => $xml_property['id'] ?? 'unknown',
                'agency_id' => $agency_id
            ]);
        } else {
            // No agency processed - property will not be linked to any agency
            $this->logger->log('🏢 Property Mapper: No agency processed for property', 'debug', [
                'property_id' => $xml_property['id'] ?? 'unknown'
            ]);
        }
        
        return [
            'post_data' => $this->map_post_data_v3($xml_property),
            'meta_fields' => $this->map_meta_fields_v3($xml_property),
            'taxonomies' => $this->map_taxonomies_v3($xml_property),
            'features' => $this->map_features_v3($xml_property),
            'gallery' => $this->map_gallery_v3($xml_property),
            'catasto' => $this->map_catasto_v3($xml_property),
            'source_data' => $source_data,
            'content_hash_v3' => $this->generate_content_hash_v3($xml_property)
        ];
    }
    
    /**
     * Map post data v3.0 - ENHANCED: Use XML <title> field
     */
    private function map_post_data_v3($xml_property) {
        $title = $this->get_xml_title_or_fallback($xml_property);
        $description = $this->get_best_description($xml_property);
        
        return [
            'post_type' => 'estate_property',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_title' => $title,
            'post_content' => $this->clean_html_content($description),
            'post_excerpt' => $this->generate_excerpt($xml_property['abstract'] ?? $description),
            'post_name' => $this->generate_slug($title, $xml_property['id']),
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ];
    }
    
    /**
     * Map meta fields v3.1 - ENHANCED: Custom Fields Integration
     */
    private function map_meta_fields_v3($xml_property) {
        $meta = [];
        
        // Core property data
        $meta['property_price'] = floatval($xml_property['price'] ?? 0);
        $meta['property_size'] = $this->get_best_surface_area($xml_property);
        $meta['property_address'] = $this->build_full_address($xml_property);
        
        // Coordinates
        if (!empty($xml_property['latitude']) && !empty($xml_property['longitude'])) {
            $meta['property_latitude'] = floatval($xml_property['latitude']);
            $meta['property_longitude'] = floatval($xml_property['longitude']);
        }
        
        // Room data
        $this->map_rooms_data_v3($xml_property, $meta);
        
        // Building details
        $meta['piano'] = $this->get_piano_info_v3($xml_property);
        $meta['energy_class'] = $this->map_energy_class_v3($xml_property);
        
        // Extended dimensions
        $this->map_extended_dimensions($xml_property, $meta);
        
        // 🆕 CUSTOM FIELDS v3.1: Property Details misurabili
        $this->map_custom_fields_v31($xml_property, $meta);
        
        // Reference and tracking
        $meta['property_ref'] = 'TI-' . $xml_property['id'];
        $meta['property_import_id'] = $xml_property['id'];
        $meta['property_import_source'] = 'GestionaleImmobiliare';
        $meta['property_import_date'] = current_time('mysql');
        $meta['property_content_hash_v3'] = $this->generate_content_hash_v3($xml_property);
        
        // 🎯 FRONTEND DISPLAY: XML ID for frontend templates
        $meta['property_xml_id'] = $xml_property['id'];
        $meta['property_display_id'] = $xml_property['id'];
        
        // 🏢 AGENCY ASSOCIATION: Will be set by WP Importer if agency_id exists in source_data
        // property_agent field will be populated by WP Importer using source_data['agency_id']
        
        return $meta;
    }
    
    /**
     * Map taxonomies v3.0
     */
    private function map_taxonomies_v3($xml_property) {
        $taxonomies = [];
        
        // Property action category
        $action = $this->determine_action_category($xml_property);
        $taxonomies['property_action_category'] = [$action];
        
        // Property category - WordPress native handling
        $categoria_id = strval($xml_property['categorie_id'] ?? 0);
        if ($categoria_id && $categoria_id !== '0') {
            $category_name = $this->gi_categories[$categoria_id] ?? null;
            if ($category_name) {
                $taxonomies['property_category'] = [$category_name]; // WordPress creates term if needed
                $this->logger->log('✅ Property category assigned', 'debug', [
                    'property_id' => $xml_property['id'] ?? 'unknown',
                    'xml_category_id' => $categoria_id,
                    'category_name' => $category_name
                ]);
            }
        }
        
        // Geographic taxonomies
        $city = $this->derive_city_from_comune_istat($xml_property['comune_istat'] ?? '');
        if ($city) {
            $taxonomies['property_city'] = [$city];
        }
        
        $county = $this->derive_county_from_comune_istat($xml_property['comune_istat'] ?? '');
        if ($county) {
            $taxonomies['property_county_state'] = [$county];
        }
        
        return $taxonomies;
    }
    
    /**
     * Map features v3.0
     */
    private function map_features_v3($xml_property) {
        $features = [];
        
        if (isset($xml_property['info_inserite']) && is_array($xml_property['info_inserite'])) {
            foreach ($xml_property['info_inserite'] as $feature_id => $value) {
                if ($this->is_feature_active($value) && isset($this->gi_features[$feature_id])) {
                    $feature_slug = $this->gi_features[$feature_id];
                    if (!in_array($feature_slug, $features)) {
                        $features[] = $feature_slug;
                    }
                }
            }
        }
        
        // Add special computed features
        $this->add_computed_features($xml_property, $features);
        
        return array_unique($features);
    }
    
    /**
     * Map gallery v3.0 - ENHANCED for Image Importer v1.0
     */
    private function map_gallery_v3($xml_property) {
        $gallery = [];
        
        if (isset($xml_property['file_allegati']) && is_array($xml_property['file_allegati'])) {
            $image_index = 0;
            
            foreach ($xml_property['file_allegati'] as $file) {
                if (empty($file['url'])) {
                    continue;
                }
                
                $file_type = isset($file['type']) && $file['type'] === 'planimetria' ? 'planimetria' : 'image';
                
                // 🖼️ ENHANCED: Create complete gallery item for Image Importer v1.0
                $gallery_item = [
                    'url' => $file['url'],
                    'type' => $file_type,
                    'is_featured' => ($image_index === 0 && $file_type === 'image'), // First image is featured
                    'alt_text' => $this->generate_image_alt_text_v3($xml_property, $file_type, $image_index),
                    'caption' => $this->generate_image_caption_v3($xml_property, $file_type, $image_index),
                    'order' => $image_index
                ];
                
                $gallery[] = $gallery_item;
                
                if ($file_type === 'image') {
                    $image_index++;
                }
            }
            
            $this->logger->log('Gallery v3.0 mapped with Image Importer structure', 'debug', [
                'property_id' => $xml_property['id'] ?? 'unknown',
                'total_files' => count($xml_property['file_allegati']),
                'gallery_items' => count($gallery),
                'has_featured' => !empty(array_filter($gallery, function($item) { return $item['is_featured']; }))
            ]);
        }
        
        return $gallery;
    }
    
    /**
     * Generate alt text for images v3.0
     */
    private function generate_image_alt_text_v3($xml_property, $file_type, $index) {
        $title = $this->generate_smart_title_v3($xml_property);
        
        $alt_texts = [
            'image' => $title . ($index > 0 ? ' - Foto ' . ($index + 1) : ''),
            'planimetria' => $title . ' - Planimetria'
        ];
        
        return $alt_texts[$file_type] ?? $title;
    }
    
    /**
     * Generate caption for images v3.0
     */
    private function generate_image_caption_v3($xml_property, $file_type, $index) {
        $city = $this->derive_city_from_comune_istat($xml_property['comune_istat'] ?? '');
        
        $captions = [
            'image' => $index === 0 ? 'Foto principale' : 'Foto aggiuntiva',
            'planimetria' => 'Planimetria della proprietà'
        ];
        
        $caption = $captions[$file_type] ?? 'Immagine';
        
        if ($city) {
            $caption .= ' - ' . $city;
        }
        
        return $caption;
    }
    
    /**
     * Map catasto v3.0
     */
    private function map_catasto_v3($xml_property) {
        $catasto = [];
        
        if (isset($xml_property['catasto']) && is_array($xml_property['catasto'])) {
            $catasto_data = $xml_property['catasto'];
            
            $catasto['destinazione'] = $catasto_data['destinazione_uso'] ?? '';
            $catasto['rendita'] = $catasto_data['rendita_catastale'] ?? '';
            $catasto['foglio'] = $catasto_data['foglio'] ?? '';
            $catasto['particella'] = $catasto_data['particella'] ?? '';
            $catasto['subalterno'] = $catasto_data['subalterno'] ?? '';
        }
        
        return $catasto;
    }
    
    /**
     * Map custom fields v3.1 - Property Details Implementation
     * 
     * Maps XML data to WpResidence custom fields based on KB Field Mapping v3.1
     * Implements 9 custom fields for Property Details (misurabili)
     * 
     * @param array $xml_property XML property data
     * @param array &$meta Meta fields array (passed by reference)
     */
    private function map_custom_fields_v31($xml_property, &$meta) {
        try {
            $custom_fields_mapped = 0;
            
            // 1. Superficie giardino (m²)
            if (!empty($xml_property['dati_inseriti'][4]) && $xml_property['dati_inseriti'][4] > 0) {
                $meta['superficie-giardino'] = intval($xml_property['dati_inseriti'][4]);
                $custom_fields_mapped++;
            }
            
            // 2. Aree esterne (m²)
            if (!empty($xml_property['dati_inseriti'][5]) && $xml_property['dati_inseriti'][5] > 0) {
                $meta['aree-esterne'] = intval($xml_property['dati_inseriti'][5]);
                $custom_fields_mapped++;
            }
            
            // 3. Superficie commerciale (m²)
            if (!empty($xml_property['dati_inseriti'][20]) && $xml_property['dati_inseriti'][20] > 0) {
                $meta['superficie-commerciale'] = intval($xml_property['dati_inseriti'][20]);
                $custom_fields_mapped++;
            }
            
            // 4. Superficie utile (m²)
            if (!empty($xml_property['dati_inseriti'][21]) && $xml_property['dati_inseriti'][21] > 0) {
                $meta['superficie-utile'] = intval($xml_property['dati_inseriti'][21]);
                $custom_fields_mapped++;
            }
            
            // 5. Totale piani edificio
            if (!empty($xml_property['info_inserite'][32]) && $xml_property['info_inserite'][32] > 0) {
                $meta['totale-piani-edificio'] = intval($xml_property['info_inserite'][32]);
                $custom_fields_mapped++;
            }
            
            // 6. Deposito cauzionale (€) - Solo per affitti
            $is_affitto = $this->get_feature_value($xml_property, 10) > 0;
            if ($is_affitto && !empty($xml_property['dati_inseriti'][27]) && $xml_property['dati_inseriti'][27] > 0) {
                $meta['deposito-cauzionale'] = intval($xml_property['dati_inseriti'][27]);
                $custom_fields_mapped++;
            }
            
            // 7. Distanza dal mare (m)
            if (!empty($xml_property['dati_inseriti'][8]) && $xml_property['dati_inseriti'][8] > 0) {
                $meta['distanza-mare'] = intval($xml_property['dati_inseriti'][8]);
                $custom_fields_mapped++;
            }
            
            // 8. Rendita catastale (€)
            if (!empty($xml_property['catasto']['rendita'])) {
                $rendita = $xml_property['catasto']['rendita'];
                // Gestione formato numerico (può essere stringa con virgola)
                if (is_string($rendita)) {
                    $rendita = str_replace(',', '.', $rendita);
                    $rendita = floatval($rendita);
                }
                if ($rendita > 0) {
                    $meta['rendita-catastale'] = $rendita;
                    $custom_fields_mapped++;
                }
            }
            
            // 9. Destinazione catastale
            if (!empty($xml_property['catasto']['destinazione'])) {
                $destinazione = trim($xml_property['catasto']['destinazione']);
                if (!empty($destinazione)) {
                    $meta['destinazione-catastale'] = $destinazione;
                    $custom_fields_mapped++;
                }
            }
            
            // Logging per debug e monitoring
            $this->logger->log('🆕 Custom Fields v3.1 mapping completed', 'debug', [
                'property_id' => $xml_property['id'] ?? 'unknown',
                'custom_fields_mapped' => $custom_fields_mapped,
                'total_possible' => 9,
                'is_affitto' => $is_affitto ?? false,
                'has_catasto_data' => !empty($xml_property['catasto'])
            ]);
            
        } catch (Exception $e) {
            $this->logger->log('ERROR: Custom Fields v3.1 mapping failed', 'error', [
                'property_id' => $xml_property['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    // DYNAMIC TAXONOMY METHODS v3.2
    
    /**
     * Get official category mapping from GestionaleImmobiliare specifications
     * Maps XML category IDs to proper Italian names with capital first letter
     * 
     * @return array Mapping of XML category ID => Category name
     */
    private function get_category_mapping() {
        return [
            '1' => 'Casa singola',
            '2' => 'Bifamiliare',
            '3' => 'Trifamiliare',
            '4' => 'Casa a schiera',
            '5' => 'Monolocale',
            '7' => 'Cantina',
            '8' => 'Garage',
            '9' => 'Magazzino',
            '10' => 'Attivita commerciale',
            '11' => 'Appartamento',
            '12' => 'Attico',
            '13' => 'Rustico',
            '14' => 'Negozio',
            '15' => 'Quadrifamiliare',
            '16' => 'Capannone',
            '17' => 'Ufficio',
            '18' => 'Villa',
            '19' => 'Terreno',
            '20' => 'Laboratorio',
            '21' => 'Posto auto',
            '22' => 'Bed and breakfast',
            '23' => 'Loft',
            '24' => 'Multiproprietà',
            '25' => 'Agriturismo',
            '26' => 'Palazzo',
            '27' => 'Hotel - albergo',
            '28' => 'Stanze'
        ];
    }
    
    // HELPER METHODS
    
    /**
     * Get XML title or generate smart fallback v3.1
     * 🎯 PRIORITY: Use XML <title> field first, fallback to smart generation
     */
    private function get_xml_title_or_fallback($xml_property) {
        // 🎯 PRIORITY: Use XML <title> field if available
        if (!empty($xml_property['title'])) {
            $xml_title = trim(wp_strip_all_tags($xml_property['title']));
            if (!empty($xml_title)) {
                $this->logger->log('🎯 Using XML title field', 'debug', [
                    'property_id' => $xml_property['id'] ?? 'unknown',
                    'xml_title' => $xml_title
                ]);
                return $xml_title;
            }
        }
        
        // Fallback to smart generation if no XML title
        $this->logger->log('🎯 XML title empty, using smart generation fallback', 'debug', [
            'property_id' => $xml_property['id'] ?? 'unknown'
        ]);
        
        return $this->generate_smart_title_v3($xml_property);
    }
    
    private function generate_smart_title_v3($xml_property) {
        $parts = [];
        
        $categoria_id = intval($xml_property['categorie_id'] ?? 0);
        if ($categoria_id == 12) {
            $parts[] = 'Attico';
        } elseif ($categoria_id == 18) {
            $parts[] = 'Villa';
        } elseif ($categoria_id == 11) {
            $parts[] = 'Appartamento';
        } elseif (isset($this->gi_categories[$categoria_id])) {
            $category = $this->gi_categories[$categoria_id];
            $parts[] = substr($category, 0, -1);
        }
        
        $city = $this->derive_city_from_comune_istat($xml_property['comune_istat'] ?? '');
        if ($city) {
            $parts[] = 'a ' . $city;
        }
        
        if ($this->get_feature_value($xml_property, 66)) {
            $parts[] = 'con Piscina';
        }
        if ($this->get_feature_value($xml_property, 17)) {
            $parts[] = 'con Giardino';
        }
        if ($this->get_feature_value($xml_property, 62) > 0) {
            $parts[] = 'Vista Panoramica';
        }
        
        if (empty($parts)) {
            return !empty($xml_property['seo_title']) ? 
                wp_strip_all_tags($xml_property['seo_title']) : 'Proprietà in Trentino';
        }
        
        return implode(' ', $parts);
    }
    
    private function get_best_description($xml_property) {
        if (!empty($xml_property['description'])) {
            return $xml_property['description'];
        }
        if (!empty($xml_property['abstract'])) {
            return $xml_property['abstract'];
        }
        return 'Proprietà immobiliare in Trentino Alto Adige.';
    }
    
    private function get_best_surface_area($xml_property) {
        if (isset($xml_property['dati_inseriti'][21]) && $xml_property['dati_inseriti'][21] > 0) {
            return intval($xml_property['dati_inseriti'][21]);
        }
        if (isset($xml_property['dati_inseriti'][20]) && $xml_property['dati_inseriti'][20] > 0) {
            return intval($xml_property['dati_inseriti'][20]);
        }
        return intval($xml_property['mq'] ?? 0);
    }
    
    private function map_rooms_data_v3($xml_property, &$meta) {
        $bathrooms = $this->get_feature_value($xml_property, 1);
        if ($bathrooms > 0) {
            $meta['property_bathrooms'] = $bathrooms == -1 ? 4 : $bathrooms;
        }
        
        $bedrooms = $this->get_feature_value($xml_property, 2);
        if ($bedrooms > 0) {
            $meta['property_bedrooms'] = $bedrooms == -1 ? 4 : $bedrooms;
        }
        
        $rooms = $this->get_feature_value($xml_property, 65);
        if ($rooms > 0) {
            $meta['property_rooms'] = $rooms;
        }
    }
    
    private function get_piano_info_v3($xml_property) {
        $piano = $this->get_feature_value($xml_property, 33);
        
        if ($piano == -2) return 'Interrato';
        if ($piano == 0) return 'Piano Terra';
        if ($piano == -1) return 'Oltre 30';
        if ($piano > 0) return strval($piano);
        
        return '';
    }
    
    private function map_energy_class_v3($xml_property) {
        $classe = $this->get_feature_value($xml_property, 55);
        return $this->energy_class_mapping[$classe] ?? '';
    }
    
    private function map_extended_dimensions($xml_property, &$meta) {
        if (isset($xml_property['dati_inseriti'])) {
            $dati = $xml_property['dati_inseriti'];
            
            if (isset($dati[20]) && $dati[20] > 0) {
                $meta['property_commercial_size'] = intval($dati[20]);
            }
            if (isset($dati[21]) && $dati[21] > 0) {
                $meta['property_useful_size'] = intval($dati[21]);
            }
            if (isset($dati[4]) && $dati[4] > 0) {
                $meta['property_garden_size'] = intval($dati[4]);
            }
            if (isset($dati[6]) && $dati[6] > 0) {
                $meta['property_ceiling_height'] = floatval($dati[6]);
            }
        }
    }
    
    private function build_full_address($xml_property) {
        $parts = [];
        if (!empty($xml_property['indirizzo'])) {
            $parts[] = $xml_property['indirizzo'];
        }
        if (!empty($xml_property['civico'])) {
            $parts[] = $xml_property['civico'];
        }
        return implode(' ', $parts);
    }
    
    private function determine_action_category($xml_property) {
        $is_vendita = $this->get_feature_value($xml_property, 9);
        $is_affitto = $this->get_feature_value($xml_property, 10);
        
        if ($is_vendita) return 'Vendita';
        if ($is_affitto) return 'Affitto';
        
        $price = floatval($xml_property['price'] ?? 0);
        return $price > 50000 ? 'Vendita' : 'Affitto';
    }
    
    private function add_computed_features($xml_property, &$features) {
        if ($this->get_feature_value($xml_property, 62) > 0) {
            $features[] = 'vista-panoramica';
        }
        
        if ($this->get_feature_value($xml_property, 36)) {
            $features[] = 'montagna';
        }
        if ($this->get_feature_value($xml_property, 37)) {
            $features[] = 'lago';
        }
    }
    
    private function get_feature_value($xml_property, $feature_id) {
        if (!isset($xml_property['info_inserite']) || !is_array($xml_property['info_inserite'])) {
            return 0;
        }
        return intval($xml_property['info_inserite'][$feature_id] ?? 0);
    }
    
    private function is_feature_active($value) {
        return intval($value) > 0;
    }
    
    private function derive_city_from_comune_istat($comune_istat) {
        if (empty($comune_istat)) return '';
        
        if (substr($comune_istat, 0, 3) === '022') return 'Trento';
        if (substr($comune_istat, 0, 3) === '021') return 'Bolzano';
        
        return '';
    }
    
    private function derive_county_from_comune_istat($comune_istat) {
        if (empty($comune_istat)) return '';
        
        if (substr($comune_istat, 0, 3) === '022') return 'Trentino-Alto Adige';
        if (substr($comune_istat, 0, 3) === '021') return 'Trentino-Alto Adige';
        
        return '';
    }
    
    private function generate_excerpt($content) {
        $content = wp_strip_all_tags($content);
        if (strlen($content) > 150) {
            $content = substr($content, 0, 150);
            $last_space = strrpos($content, ' ');
            if ($last_space !== false) {
                $content = substr($content, 0, $last_space);
            }
            $content .= '...';
        }
        return trim($content);
    }
    
    private function generate_slug($title, $id) {
        $slug = sanitize_title($title);
        if (empty($slug)) {
            $slug = 'proprieta-' . $id;
        }
        return $slug;
    }
    
    private function clean_html_content($content) {
        $allowed_tags = '<p><br><strong><b><em><i><ul><li><ol>';
        return strip_tags(trim($content), $allowed_tags);
    }
    
    private function generate_content_hash_v3($xml_property) {
        $hash_fields = ['id', 'price', 'description', 'abstract', 'mq', 'indirizzo'];
        $hash_data = [];
        
        foreach ($hash_fields as $field) {
            $hash_data[$field] = $xml_property[$field] ?? '';
        }
        
        if (isset($xml_property['info_inserite'])) {
            $hash_data['info_inserite'] = serialize($xml_property['info_inserite']);
        }
        if (isset($xml_property['dati_inseriti'])) {
            $hash_data['dati_inseriti'] = serialize($xml_property['dati_inseriti']);
        }
        if (isset($xml_property['file_allegati'])) {
            $hash_data['file_allegati'] = serialize($xml_property['file_allegati']);
        }
        if (isset($xml_property['catasto'])) {
            $hash_data['catasto'] = serialize($xml_property['catasto']);
        }
        
        return md5(serialize($hash_data));
    }
    
    /**
     * Process agency for property using Agency Manager
     * Direct integration with Agency Manager for property→agency mapping
     * 
     * @param array $xml_property XML property containing agency data
     * @return int|false Agency ID for property_agent association, false if no agency
     */
    private function process_agency_for_property($xml_property) {
        try {
            $this->logger->log('INFO', '🏢 PROPERTY MAPPER: process_agency_for_property called', array(
                'property_id' => isset($xml_property['id']) ? $xml_property['id'] : 'unknown',
                'agency_manager_exists' => isset($this->agency_manager),
                'agency_manager_class' => isset($this->agency_manager) ? get_class($this->agency_manager) : 'NOT_SET'
            ));
            
            // Check if agency_data exists
            if (empty($xml_property['agency_data'])) {
                $this->logger->log('WARNING', 'No agency_data found in XML property', array(
                    'property_id' => isset($xml_property['id']) ? $xml_property['id'] : 'unknown'
                ));
                return false;
            }
            
            // Use Agency Manager to create/update agency from XML agency data
            $agency_id = $this->agency_manager->create_or_update_agency_from_xml($xml_property['agency_data']);
            
            if ($agency_id) {
                $this->logger->log('SUCCESS', 'Agency processed for property via Agency Manager', array(
                    'property_id' => isset($xml_property['id']) ? $xml_property['id'] : 'unknown',
                    'agency_id' => $agency_id
                ));
                return $agency_id;
            } else {
                $this->logger->log('WARNING', 'No agency processed for property', array(
                    'property_id' => isset($xml_property['id']) ? $xml_property['id'] : 'unknown'
                ));
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->log('ERROR', 'Error processing agency for property: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validation methods
     */
    public function validate_mapping() {
        $validation = [
            'categories_count' => count($this->gi_categories),
            'features_count' => count($this->gi_features),
            'energy_classes_count' => count($this->energy_class_mapping)
        ];
        
        $this->logger->log('Property Mapper v3.1 validation', 'info', $validation);
        
        return [
            'success' => true,
            'version' => '3.1.0',
            'mapping_stats' => $validation,
            'features' => [
                'database_analysis_based' => true,
                'auto_feature_creation' => true,
                'gallery_support' => true,
                'catasto_support' => true,
                'target_page_compliance' => true,
                'custom_fields_property_details' => true
            ]
        ];
    }
    
    public function get_mapping_stats() {
        return [
            'version' => '3.1.0',
            'total_categories' => count($this->gi_categories),
            'total_features' => count($this->gi_features),
            'supported_provinces' => ['TN', 'BZ'],
            'energy_classes' => array_values($this->energy_class_mapping),
            'target_compliance' => true
        ];
    }
}
