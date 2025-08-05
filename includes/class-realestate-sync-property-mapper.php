<?php
/**
 * Property Mapper Class for RealEstate Sync Plugin
 * 
 * Maps XML property data from GestionaleImmobiliare.it to WpResidence
 * theme format. Handles field transformations, data normalization,
 * and WordPress-compatible property creation.
 * 
 * @package RealEstateSync
 * @version 0.9.0
 * @author Andrea Cianni - Novacom
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

/**
 * RealEstate_Sync_Property_Mapper Class
 * 
 * Manages property data mapping including:
 * - XML to WpResidence field mapping
 * - Data type conversion and validation
 * - Custom field assignments
 * - Taxonomy and category mapping
 * - Duplicate detection and updates
 */
class RealEstate_Sync_Property_Mapper {
    
    private $logger;
    private $config;
    private $field_mappings;
    private $taxonomies;
    private $features_mapping;
    private $custom_fields;
    private $stats;
    
    public function __construct($logger = null) {
        $this->logger = $logger ?: RealEstate_Sync_Logger::get_instance();
        $this->init_mapper();
    }
    
    private function init_mapper() {
        $this->load_config();
        $this->load_field_mappings();
        $this->load_taxonomies_mapping();
        $this->load_features_mapping();
        $this->load_custom_fields_mapping();
        $this->reset_stats();
        
        $this->logger->log('Property Mapper initialized', 'info', [
            'field_mappings_count' => count($this->field_mappings),
            'features_count' => count($this->features_mapping),
            'custom_fields_count' => count($this->custom_fields)
        ]);
    }
    
    private function load_config() {
        $defaults = [
            'default_post_status' => 'publish',
            'default_post_author' => 1,
            'auto_generate_excerpt' => true,
            'max_excerpt_length' => 150,
            'slug_from_title' => true,
            'duplicate_action' => 'update',
            'price_currency' => 'EUR',
            'validate_required_fields' => true,
            'generate_property_code' => true
        ];
        
        $this->config = get_option('realestate_sync_mapper_config', $defaults);
    }
    
    private function load_field_mappings() {
        // Updated mappings for GestionaleImmobiliare XML structure
        $this->field_mappings = [
            // XML uses different field names - updated mapping
            'post_title' => 'title',              // <title> in <info>
            'post_content' => 'description',        // <description> in <info> 
            'property_price' => 'price',            // <price> in <info>
            'property_price_per_month' => 'price',  // Same field, different logic
            'property_size' => 'mq',               // <mq> in <info>
            'property_rooms' => 'numero_camere',     // If exists
            'property_bedrooms' => 'numero_camere',  // If exists
            'property_bathrooms' => 'numero_bagni', // If exists
            'property_address' => 'indirizzo',      // <indirizzo> in <info>
            'property_city' => 'citta',            // Derived from comune
            'property_zip' => 'cap',               // If exists
            'property_state' => 'provincia',        // Derived from provincia
            'property_country' => null,             // Default to Italy
            'property_year' => 'anno_costruzione',  // If exists
            'property_floors' => 'numero_piani',    // If exists
            'property_floor' => 'piano',            // If exists
            'property_energy_class' => 'ape',       // From <ape> classe attribute
            'property_id_gestionale' => 'id',       // <id> in <info>
            'property_ref' => null,                 // Generated
            'property_source' => null               // Default
        ];
    }
    
    private function load_taxonomies_mapping() {
        $this->taxonomies = [
            'property_category' => [
                'taxonomy' => 'property_category',
                'source_field' => 'categorie_id',      // Updated to categorie_id
                'mapping' => [
                    1 => 'Casa Singola',
                    2 => 'Bifamiliare',
                    11 => 'Appartamento',
                    12 => 'Attico',
                    18 => 'Villa',
                    19 => 'Terreno',
                    14 => 'Negozio',
                    17 => 'Ufficio',
                    8 => 'Garage'
                ],
                'default' => 'Proprietà'
            ],
            
            'property_action_category' => [
                'taxonomy' => 'property_action_category',
                'source_field' => null,
                'mapping' => [
                    'sale' => 'Vendita',
                    'rent' => 'Affitto'
                ],
                'default' => 'Vendita'
            ],
            
            'property_city' => [
                'taxonomy' => 'property_city',
                'source_field' => 'citta',
                'mapping' => null,
                'default' => null
            ],
            
            'property_county' => [
                'taxonomy' => 'property_county',
                'source_field' => 'provincia',
                'mapping' => [
                    'TN' => 'Trento',
                    'BZ' => 'Bolzano'
                ],
                'default' => null
            ]
        ];
    }
    
    private function load_features_mapping() {
        $this->features_mapping = [
            'elevator' => 'ascensore',
            'garden' => 'giardino',
            'swimming-pool' => 'piscina',
            'garage' => 'garage',
            'air-conditioning' => 'aria_condizionata',
            'heating' => 'riscaldamento',
            'balcony' => 'balcone',
            'terrace' => 'terrazzo',
            'furnished' => 'arredato',
            'alarm' => 'allarme'
        ];
    }
    
    private function load_custom_fields_mapping() {
        $this->custom_fields = [
            'property_import_source' => null,
            'property_import_id' => 'id',
            'property_import_date' => null,
            'property_import_hash' => null,
            'property_last_sync' => null
        ];
    }
    
    private function reset_stats() {
        $this->stats = [
            'total_properties' => 0,
            'mapped_properties' => 0,
            'skipped_properties' => 0,
            'error_properties' => 0,
            'errors' => []
        ];
    }
    
    public function map_properties($xml_properties) {
        $this->logger->log('Starting property mapping', 'info', [
            'input_count' => count($xml_properties)
        ]);
        
        $this->reset_stats();
        $this->stats['total_properties'] = count($xml_properties);
        
        $mapped_properties = [];
        
        foreach ($xml_properties as $index => $xml_property) {
            try {
                $mapped = $this->map_single_property($xml_property, $index);
                
                if ($mapped !== null) {
                    $mapped_properties[] = $mapped;
                    $this->stats['mapped_properties']++;
                } else {
                    $this->stats['skipped_properties']++;
                }
                
            } catch (Exception $e) {
                $this->stats['error_properties']++;
                $this->stats['errors'][] = [
                    'property_index' => $index,
                    'property_id' => $xml_property['id_immobile'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                
                $this->logger->log('Property mapping error', 'warning', [
                    'property_index' => $index,
                    'property_id' => $xml_property['id_immobile'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->log('Property mapping completed', 'info', [
            'total_properties' => $this->stats['total_properties'],
            'mapped_properties' => $this->stats['mapped_properties'],
            'skipped_properties' => $this->stats['skipped_properties'],
            'error_properties' => $this->stats['error_properties']
        ]);
        
        return [
            'success' => true,
            'properties' => $mapped_properties,
            'stats' => $this->stats
        ];
    }
    
    private function map_single_property($xml_property, $index = 0) {
        if (!$this->validate_required_fields($xml_property)) {
            return null;
        }
        
        $mapped = [
            'post_data' => [],
            'meta_fields' => [],
            'taxonomies' => [],
            'features' => [],
            'custom_fields' => [],
            'source_data' => $xml_property
        ];
        
        $mapped['post_data'] = $this->map_post_data($xml_property);
        $mapped['meta_fields'] = $this->map_meta_fields($xml_property);
        $mapped['taxonomies'] = $this->map_taxonomies($xml_property);
        $mapped['features'] = $this->map_features($xml_property);
        $mapped['custom_fields'] = $this->map_custom_fields($xml_property);
        $mapped = $this->add_import_metadata($mapped, $xml_property);
        $mapped['content_hash'] = $this->generate_content_hash($xml_property);
        
        return $mapped;
    }
    
    private function validate_required_fields($xml_property) {
        if (!$this->config['validate_required_fields']) {
            return true;
        }
        
        // Updated required fields for actual XML structure
        $required_fields = ['id'];
        
        foreach ($required_fields as $field) {
            if (!isset($xml_property[$field]) || empty($xml_property[$field])) {
                return false;
            }
        }
        
        // Check price exists (always in 'price' field)
        if (empty($xml_property['price'])) {
            return false;
        }
        
        return true;
    }
    
    private function map_post_data($xml_property) {
        $post_data = [
            'post_type' => 'estate_property',
            'post_status' => $this->config['default_post_status'],
            'post_author' => $this->config['default_post_author'],
            'post_title' => $this->sanitize_title($xml_property['abstract'] ?? $xml_property['seo_title'] ?? 'Proprietà'),
            'post_content' => $this->sanitize_content($xml_property['description'] ?? ''),
            'post_excerpt' => '',
            'post_name' => '',
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ];
        
        if ($this->config['auto_generate_excerpt'] && !empty($post_data['post_content'])) {
            $post_data['post_excerpt'] = $this->generate_excerpt($post_data['post_content']);
        }
        
        if ($this->config['slug_from_title']) {
            $post_data['post_name'] = $this->generate_slug($post_data['post_title'], $xml_property['id'] ?? 'unknown');
        }
        
        return $post_data;
    }
    
    private function map_meta_fields($xml_property) {
        $meta_fields = [];
        
        foreach ($this->field_mappings as $wp_field => $xml_field) {
            if (strpos($wp_field, 'post_') === 0) {
                continue;
            }
            
            $value = null;
            
            if ($xml_field === null) {
                $value = $this->get_default_value($wp_field, $xml_property);
            } else if (isset($xml_property[$xml_field])) {
                $value = $this->transform_field_value($wp_field, $xml_property[$xml_field], $xml_property);
            }
            
            if ($value !== null) {
                $meta_fields[$wp_field] = $value;
            }
        }
        
        return $meta_fields;
    }
    
    private function map_taxonomies($xml_property) {
        $taxonomies = [];
        
        foreach ($this->taxonomies as $taxonomy_key => $taxonomy_config) {
            $terms = [];
            
            if ($taxonomy_key === 'property_action_category') {
                if (!empty($xml_property['prezzo_vendita'])) {
                    $terms[] = $taxonomy_config['mapping']['sale'];
                } else if (!empty($xml_property['prezzo_affitto'])) {
                    $terms[] = $taxonomy_config['mapping']['rent'];
                }
            } else if ($taxonomy_config['source_field'] && isset($xml_property[$taxonomy_config['source_field']])) {
                $source_value = $xml_property[$taxonomy_config['source_field']];
                
                if ($taxonomy_config['mapping']) {
                    if (isset($taxonomy_config['mapping'][$source_value])) {
                        $terms[] = $taxonomy_config['mapping'][$source_value];
                    }
                } else {
                    $terms[] = $this->sanitize_term($source_value);
                }
            }
            
            if (empty($terms) && $taxonomy_config['default']) {
                $terms[] = $taxonomy_config['default'];
            }
            
            if (!empty($terms)) {
                $taxonomies[$taxonomy_config['taxonomy']] = $terms;
            }
        }
        
        return $taxonomies;
    }
    
    private function map_features($xml_property) {
        $features = [];
        
        foreach ($this->features_mapping as $feature_slug => $xml_field) {
            if (isset($xml_property[$xml_field]) && $this->is_feature_enabled($xml_property[$xml_field])) {
                $features[] = $feature_slug;
            }
        }
        
        return $features;
    }
    
    private function map_custom_fields($xml_property) {
        $custom_fields = [];
        
        foreach ($this->custom_fields as $custom_field => $xml_field) {
            $value = null;
            
            if ($xml_field === null) {
                $value = $this->get_custom_field_default($custom_field, $xml_property);
            } else if (isset($xml_property[$xml_field])) {
                $value = $xml_property[$xml_field];
            }
            
            if ($value !== null) {
                $custom_fields[$custom_field] = $value;
            }
        }
        
        return $custom_fields;
    }
    
    private function add_import_metadata($mapped, $xml_property) {
        $timestamp = current_time('mysql');
        
        $mapped['meta_fields']['property_import_source'] = 'GestionaleImmobiliare';
        $mapped['meta_fields']['property_import_date'] = $timestamp;
        $mapped['meta_fields']['property_last_sync'] = $timestamp;
        
        if (isset($xml_property['id'])) {
            $mapped['meta_fields']['property_import_id'] = $xml_property['id'];
            
            if ($this->config['generate_property_code']) {
                $mapped['meta_fields']['property_ref'] = 'RS-' . $xml_property['id'];
            }
        }
        
        return $mapped;
    }
    
    private function generate_content_hash($xml_property) {
        $hash_fields = ['id', 'abstract', 'price', 'description'];
        $hash_data = [];
        
        foreach ($hash_fields as $field) {
            $hash_data[$field] = $xml_property[$field] ?? '';
        }
        
        return md5(serialize($hash_data));
    }
    
    private function transform_field_value($wp_field, $value, $xml_property) {
        switch ($wp_field) {
            case 'property_price':
            case 'property_price_per_month':
                return $this->format_price($value);
                
            case 'property_size':
                return $this->format_area($value);
                
            case 'property_rooms':
            case 'property_bedrooms':
            case 'property_bathrooms':
                return max(0, intval($value));
                
            case 'property_year':
                return $this->format_year($value);
                
            case 'property_address':
                return $this->format_address($value, $xml_property);
                
            default:
                return $this->sanitize_text($value);
        }
    }
    
    private function get_default_value($wp_field, $xml_property) {
        switch ($wp_field) {
            case 'property_country':
                return 'Italy';
                
            case 'property_source':
                return 'GestionaleImmobiliare';
                
            case 'property_ref':
                return isset($xml_property['id']) ? 'RS-' . $xml_property['id'] : '';
                
            default:
                return null;
        }
    }
    
    private function get_custom_field_default($custom_field, $xml_property) {
        switch ($custom_field) {
            case 'property_import_source':
                return 'GestionaleImmobiliare';
                
            case 'property_import_date':
            case 'property_last_sync':
                return current_time('mysql');
                
            case 'property_import_hash':
                return $this->generate_content_hash($xml_property);
                
            default:
                return null;
        }
    }
    
    private function is_feature_enabled($value) {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return intval($value) > 0;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'si', 'sì']);
        }
        
        return false;
    }
    
    private function format_price($price) {
        if (empty($price)) {
            return null;
        }
        
        $cleaned = preg_replace('/[^\d.]/', '', $price);
        
        if (is_numeric($cleaned)) {
            return floatval($cleaned);
        }
        
        return null;
    }
    
    private function format_area($area) {
        if (empty($area)) {
            return null;
        }
        
        $cleaned = preg_replace('/[^\d.]/', '', $area);
        
        if (is_numeric($cleaned)) {
            return intval(floatval($cleaned));
        }
        
        return null;
    }
    
    private function format_year($year) {
        if (empty($year)) {
            return null;
        }
        
        $year = intval($year);
        
        if ($year >= 1800 && $year <= date('Y') + 5) {
            return $year;
        }
        
        return null;
    }
    
    private function format_address($address, $xml_property) {
        $address = trim($address);
        
        if (empty($address)) {
            $parts = [];
            
            if (!empty($xml_property['citta'])) {
                $parts[] = $xml_property['citta'];
            }
            
            if (!empty($xml_property['provincia'])) {
                $parts[] = $xml_property['provincia'];
            }
            
            $address = implode(', ', $parts);
        }
        
        return $this->sanitize_text($address);
    }
    
    private function sanitize_title($title) {
        return wp_strip_all_tags(trim($title));
    }
    
    private function sanitize_content($content) {
        $allowed_tags = '<p><br><strong><b><em><i><ul><li><ol>';
        return strip_tags(trim($content), $allowed_tags);
    }
    
    private function sanitize_text($text) {
        return wp_strip_all_tags(trim($text));
    }
    
    private function sanitize_term($term) {
        return ucwords(strtolower(trim($term)));
    }
    
    private function generate_excerpt($content) {
        $excerpt = wp_strip_all_tags($content);
        
        if (strlen($excerpt) > $this->config['max_excerpt_length']) {
            $excerpt = substr($excerpt, 0, $this->config['max_excerpt_length']);
            $last_space = strrpos($excerpt, ' ');
            if ($last_space !== false) {
                $excerpt = substr($excerpt, 0, $last_space);
            }
            $excerpt .= '...';
        }
        
        return trim($excerpt);
    }
    
    private function generate_slug($title, $id) {
        $slug = sanitize_title($title);
        
        if (empty($slug)) {
            $slug = 'property-' . $id;
        } else {
            $slug .= '-' . $id;
        }
        
        return $slug;
    }
    
    public function find_existing_property($import_id) {
        $query = new WP_Query([
            'post_type' => 'estate_property',
            'meta_query' => [
                [
                    'key' => 'property_import_id',
                    'value' => $import_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);
        
        return $query->have_posts() ? $query->posts[0] : null;
    }
    
    public function has_content_changed($post_id, $new_hash) {
        $stored_hash = get_post_meta($post_id, 'property_import_hash', true);
        return $stored_hash !== $new_hash;
    }
    
    public function get_stats() {
        return $this->stats;
    }
    
    public function validate_mapped_property($mapped_property) {
        $errors = [];
        
        if (empty($mapped_property['post_data']['post_title'])) {
            $errors[] = 'Missing property title';
        }
        
        $has_price = false;
        if (!empty($mapped_property['meta_fields']['property_price']) ||
            !empty($mapped_property['meta_fields']['property_price_per_month'])) {
            $has_price = true;
        }
        
        if (!$has_price) {
            $errors[] = 'Missing property price (sale or rent)';
        }
        
        if (empty($mapped_property['meta_fields']['property_city'])) {
            $errors[] = 'Missing property city';
        }
        
        if (empty($mapped_property['taxonomies']['property_category'])) {
            $errors[] = 'Missing property category';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

// End of file
