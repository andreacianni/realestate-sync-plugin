<?php
/**
 * WordPress Importer Class for RealEstate Sync Plugin
 * 
 * Handles the actual import of mapped property data into WordPress
 * as WpResidence posts. Manages post creation, updates, taxonomies,
 * meta fields, and duplicate handling.
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
 * RealEstate_Sync_WP_Importer Class
 * 
 * Manages WordPress import operations including:
 * - Post creation and updates
 * - Meta fields assignment
 * - Taxonomy terms creation and assignment
 * - Property features handling
 * - Duplicate detection and management
 * - Import statistics and reporting
 */
class RealEstate_Sync_WP_Importer {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Import configuration
     */
    private $config;
    
    /**
     * Import statistics
     */
    private $stats;
    
    /**
     * Property mapper instance
     */
    private $property_mapper;
    
    /**
     * Image importer instance
     */
    private $image_importer;
    
    /**
     * Agency manager instance
     */
    private $agency_manager;
    
    /**
     * Current import session ID
     */
    private $session_id;
    
    /**
     * WordPress post type for properties
     */
    private $post_type = 'estate_property';
    
    /**
     * Constructor
     *
     * @param RealEstate_Sync_Logger $logger Logger instance
     * @param RealEstate_Sync_Property_Mapper $property_mapper Property mapper instance
     */
    public function __construct($logger = null, $property_mapper = null) {
        $this->logger = $logger ?: RealEstate_Sync_Logger::get_instance();
        $this->property_mapper = $property_mapper;
        
        // 🖼️ INITIALIZE IMAGE IMPORTER v1.0
        $this->image_importer = new RealEstate_Sync_Image_Importer($this->logger);
        
        // 🏢 INITIALIZE AGENCY MANAGER v1.0
        $this->agency_manager = new RealEstate_Sync_Agency_Manager();
        
        $this->init_importer();
    }
    
    /**
     * Process catasto data v3.0
     */
    private function process_catasto_v3($post_id, $catasto) {
        if (empty($catasto)) {
            return;
        }
        
        foreach ($catasto as $key => $value) {
            if ($value !== null && $value !== '') {
                update_post_meta($post_id, $key, $value);
            }
        }
        
        $this->logger->log('Catasto processed v3.0', 'debug', [
            'post_id' => $post_id,
            'catasto_fields' => count($catasto)
        ]);
    }
    
    /**
     * Debug verification of dual gallery system implementation
     * 
     * @param int $post_id Property post ID
     * @param array $expected_attachment_ids Expected attachment IDs
     */
    private function debug_verify_dual_gallery_system($post_id, $expected_attachment_ids) {
        $this->logger->log('🔍 DEBUG: Starting dual gallery system verification', 'info', [
            'post_id' => $post_id,
            'expected_attachments' => $expected_attachment_ids
        ]);
        
        // Verify wpestate_property_gallery
        $gallery_field = get_post_meta($post_id, 'wpestate_property_gallery', true);
        $gallery_ids = !empty($gallery_field) ? explode(',', $gallery_field) : [];
        
        // Verify image_to_attach
        $attach_field = get_post_meta($post_id, 'image_to_attach', true);
        $attach_ids = !empty($attach_field) ? explode(',', $attach_field) : [];
        
        // Check menu order for attachments
        $menu_order_check = [];
        foreach ($expected_attachment_ids as $attachment_id) {
            $attachment = get_post($attachment_id);
            if ($attachment) {
                $menu_order_check[] = [
                    'id' => $attachment_id,
                    'parent' => $attachment->post_parent,
                    'menu_order' => $attachment->menu_order
                ];
            }
        }
        
        // Check if WpResidence functions are available
        $wpresidence_functions = [
            'wpestate_listing_pins' => function_exists('wpestate_listing_pins'),
            'wpestate_generate_property_slider_image_ids' => function_exists('wpestate_generate_property_slider_image_ids')
        ];
        
        // Test native WpResidence function if available
        $native_function_test = null;
        if (function_exists('wpestate_generate_property_slider_image_ids')) {
            $native_function_test = wpestate_generate_property_slider_image_ids($post_id);
        }
        
        // Check transient cache status
        $cache_status = [
            'wpestate_markers_default_pins' => get_transient('wpestate_markers_default_pins'),
            'wpestate_markers_imported_properties' => get_transient('wpestate_markers_imported_properties'),
            'wpestate_pin_images' => get_transient('wpestate_pin_images')
        ];
        
        $verification_result = [
            'wpestate_property_gallery' => [
                'raw_value' => $gallery_field,
                'parsed_ids' => $gallery_ids,
                'count' => count($gallery_ids),
                'matches_expected' => (count(array_intersect($gallery_ids, $expected_attachment_ids)) === count($expected_attachment_ids))
            ],
            'image_to_attach' => [
                'raw_value' => $attach_field,
                'parsed_ids' => $attach_ids,
                'count' => count($attach_ids),
                'contains_expected' => (count(array_intersect($attach_ids, $expected_attachment_ids)) === count($expected_attachment_ids))
            ],
            'menu_order_status' => $menu_order_check,
            'wpresidence_functions' => $wpresidence_functions,
            'native_function_test' => $native_function_test,
            'cache_status' => [
                'markers_cleared' => ($cache_status['wpestate_markers_default_pins'] === false),
                'cache_details' => $cache_status
            ]
        ];
        
        // Overall success check
        $overall_success = (
            !empty($gallery_field) &&
            !empty($attach_field) &&
            $verification_result['wpestate_property_gallery']['matches_expected'] &&
            $verification_result['image_to_attach']['contains_expected']
        );
        
        $this->logger->log('✅ DEBUG: Dual gallery system verification complete', 'info', [
            'post_id' => $post_id,
            'overall_success' => $overall_success,
            'verification_details' => $verification_result
        ]);
        
        // Additional debug for troubleshooting
        if (!$overall_success) {
            $this->logger->log('⚠️ DEBUG: Dual gallery system verification FAILED', 'error', [
                'post_id' => $post_id,
                'issues' => [
                    'wpestate_property_gallery_empty' => empty($gallery_field),
                    'image_to_attach_empty' => empty($attach_field),
                    'gallery_mismatch' => !$verification_result['wpestate_property_gallery']['matches_expected'],
                    'attach_mismatch' => !$verification_result['image_to_attach']['contains_expected']
                ]
            ]);
        }
        
        return $verification_result;
    }
    
    /**
     * Initialize WordPress importer
     */
    private function init_importer() {
        $this->load_config();
        $this->reset_stats();
        
        // 🎯 WPRESIDENCE INTEGRATION: Hook JavaScript markers system
        $this->init_wpresidence_integration_hooks();
        
        $this->logger->log('WordPress Importer initialized', 'debug', [
            'post_type' => $this->post_type,
            'duplicate_action' => $this->config['duplicate_action'],
            'batch_size' => $this->config['batch_size'],
            'wpresidence_hooks' => 'enabled'
        ]);
    }
    
    /**
     * Initialize WpResidence integration hooks for JavaScript markers
     */
    private function init_wpresidence_integration_hooks() {
        // Hook to ensure imported properties appear in JavaScript markers
        add_action('wp_enqueue_scripts', array($this, 'enhance_wpresidence_markers'), 15);
        
        // Hook to refresh cache after property import
        add_action('realestate_sync_property_imported', array($this, 'refresh_property_cache'), 10, 2);
        
        $this->logger->log('WpResidence integration hooks initialized', 'debug');
    }
    
    /**
     * Enhance WpResidence markers for imported properties
     * 
     * Public method called by WordPress hook
     */
    public function enhance_wpresidence_markers() {
        $this->logger->log('🎯 DEBUG: enhance_wpresidence_markers hook triggered', 'debug', [
            'is_singular_estate_property' => is_singular('estate_property'),
            'current_post_id' => get_the_ID(),
            'function_exists_wpestate_listing_pins' => function_exists('wpestate_listing_pins')
        ]);
        
        if (is_singular('estate_property')) {
            global $post;
            
            $this->logger->log('🎯 DEBUG: Processing single estate property page', 'debug', [
                'post_id' => $post->ID,
                'post_title' => $post->post_title
            ]);
            
            // Check if this is an imported property
            $property_source = get_post_meta($post->ID, 'property_import_id', true);
            
            $this->logger->log('🎯 DEBUG: Checking property import status', 'debug', [
                'post_id' => $post->ID,
                'property_import_id' => $property_source,
                'is_imported_property' => !empty($property_source)
            ]);
            
            if (!empty($property_source)) {
                $this->logger->log('🎯 DEBUG: Processing imported property for JavaScript markers', 'info', [
                    'post_id' => $post->ID,
                    'import_id' => $property_source
                ]);
                
                // Force cache refresh for this specific property
                delete_transient('wpestate_markers_default_pins');
                
                // Ensure property is included in JavaScript markers
                if (function_exists('wpestate_listing_pins')) {
                    $this->logger->log('🎯 DEBUG: Calling wpestate_listing_pins function', 'debug', [
                        'post_id' => $post->ID,
                        'function_params' => [
                            'transient_appendix' => 'imported_single',
                            'with_cache' => 0,
                            'args' => '',
                            'jump' => 1,
                            'keyword' => '',
                            'id_array' => $post->ID
                        ]
                    ]);
                    
                    $selected_pins = wpestate_listing_pins('imported_single', 0, '', 1, '', $post->ID);
                    
                    $this->logger->log('🎯 DEBUG: wpestate_listing_pins result', 'debug', [
                        'post_id' => $post->ID,
                        'pins_generated' => !empty($selected_pins),
                        'pins_length' => strlen($selected_pins ?? ''),
                        'pins_preview' => substr($selected_pins ?? '', 0, 200) . '...'
                    ]);
                    
                    if (!empty($selected_pins)) {
                        wp_localize_script('googlecode_property', 'googlecode_property_vars2', array(
                            'markers2' => $selected_pins
                        ));
                        
                        $this->logger->log('✅ DEBUG: Enhanced JavaScript markers for imported property', 'info', [
                            'property_id' => $post->ID,
                            'markers_generated' => true,
                            'script_localized' => 'googlecode_property_vars2'
                        ]);
                    } else {
                        $this->logger->log('⚠️ DEBUG: No markers generated for imported property', 'warning', [
                            'property_id' => $post->ID,
                            'possible_issue' => 'Check property meta fields and coordinates'
                        ]);
                    }
                } else {
                    $this->logger->log('⚠️ DEBUG: wpestate_listing_pins function not available', 'warning', [
                        'post_id' => $post->ID,
                        'check' => 'WpResidence theme may not be active or function not loaded'
                    ]);
                }
            } else {
                $this->logger->log('🎯 DEBUG: Property is not imported - skipping marker enhancement', 'debug', [
                    'post_id' => $post->ID
                ]);
            }
        } else {
            $this->logger->log('🎯 DEBUG: Not a single estate property page - skipping', 'debug');
        }
    }
    
    /**
     * Refresh property cache after import
     * 
     * Public method called by WordPress action
     * 
     * @param int $post_id Property post ID
     * @param string $action Import action (created/updated)
     */
    public function refresh_property_cache($post_id, $action) {
        // Force refresh of JavaScript markers cache
        delete_transient('wpestate_markers_default_pins');
        delete_transient('wpestate_markers_imported_properties');
        
        $this->logger->log('Property cache refreshed after import', 'debug', [
            'property_id' => $post_id,
            'action' => $action
        ]);
    }
    
    /**
     * Load importer configuration
     */
    private function load_config() {
        $defaults = [
            'duplicate_action' => 'update', // update, skip, create_new
            'batch_size' => 50,
            'create_missing_terms' => true,
            'update_existing_terms' => false,
            'assign_property_features' => true,
            'validate_before_import' => true,
            'backup_before_update' => false,
            'generate_thumbnails' => false, // For future image import
            'notify_on_errors' => true,
            'max_execution_time' => 300 // 5 minutes
        ];
        
        $this->config = get_option('realestate_sync_wp_importer_config', $defaults);
    }
    
    /**
     * Reset import statistics
     */
    private function reset_stats() {
        $this->stats = [
            'start_time' => 0,
            'end_time' => 0,
            'duration' => 0,
            'total_properties' => 0,
            'imported_properties' => 0,
            'updated_properties' => 0,
            'skipped_properties' => 0,
            'failed_properties' => 0,
            'created_terms' => 0,
            'assigned_features' => 0,
            'errors' => [],
            'memory_peak' => 0
        ];
    }
    
    /**
     * Create property - Method required by Import Engine
     *
     * @param array $mapped_property Mapped property data
     * @return int|false WordPress post ID or false on failure
     */
    public function create_property($mapped_property) {
        try {
            $result = $this->create_new_property($mapped_property, $mapped_property['source_data']['id']);
            return $result['success'] ? $result['post_id'] : false;
        } catch (Exception $e) {
            $this->logger->log("Create property failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Update property - Method required by Import Engine
     *
     * @param int $post_id WordPress post ID
     * @param array $mapped_property Mapped property data
     * @return bool Success status
     */
    public function update_property($post_id, $mapped_property) {
        try {
            $result = $this->update_existing_property($post_id, $mapped_property, $mapped_property['source_data']['id']);
            return $result['success'];
        } catch (Exception $e) {
            $this->logger->log("Update property failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Create new property post
     *
     * @param array $mapped_property Mapped property data
     * @param string $import_id Import ID
     * @return array Creation result
     */
    private function create_new_property($mapped_property, $import_id) {
        $this->logger->log('Creating new property', 'debug', [
            'session_id' => $this->session_id,
            'import_id' => $import_id,
            'title' => $mapped_property['post_data']['post_title'] ?? 'Unknown'
        ]);
        
        // Insert post
        $post_id = wp_insert_post($mapped_property['post_data'], true);
        
        if (is_wp_error($post_id)) {
            return [
                'success' => false,
                'error' => 'Post creation failed: ' . $post_id->get_error_message()
            ];
        }
        
        // Add meta fields
        $this->assign_meta_fields($post_id, $mapped_property['meta_fields'] ?? []);
        
        // Assign taxonomies
        $this->assign_taxonomies($post_id, $mapped_property['taxonomies'] ?? []);
        
        // Assign property features
        if ($this->config['assign_property_features']) {
            $this->assign_property_features($post_id, $mapped_property['features'] ?? []);
        }
        
        // Add custom fields
        $this->assign_custom_fields($post_id, $mapped_property['custom_fields'] ?? []);
        
        $this->logger->log('Property created successfully', 'info', [
            'session_id' => $this->session_id,
            'import_id' => $import_id,
            'post_id' => $post_id,
            'title' => $mapped_property['post_data']['post_title'] ?? 'Unknown'
        ]);
        
        return [
            'success' => true,
            'action' => 'created',
            'post_id' => $post_id,
            'message' => 'Property created successfully'
        ];
    }
    
    /**
     * Update existing property post
     *
     * @param int $post_id Existing WordPress post ID
     * @param array $mapped_property New mapped property data
     * @param string $import_id Import ID
     * @return array Update result
     */
    private function update_existing_property($post_id, $mapped_property, $import_id) {
        // Check if content has changed
        if ($this->property_mapper && isset($mapped_property['content_hash'])) {
            if (!$this->property_mapper->has_content_changed($post_id, $mapped_property['content_hash'])) {
                $this->logger->log('Property content unchanged - skipping update', 'debug', [
                    'session_id' => $this->session_id,
                    'import_id' => $import_id,
                    'post_id' => $post_id
                ]);
                
                // Update only the sync timestamp
                update_post_meta($post_id, 'property_last_sync', current_time('mysql'));
                
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'post_id' => $post_id,
                    'message' => 'No changes detected'
                ];
            }
        }
        
        $this->logger->log('Updating existing property', 'debug', [
            'session_id' => $this->session_id,
            'import_id' => $import_id,
            'post_id' => $post_id,
            'title' => $mapped_property['post_data']['post_title'] ?? 'Unknown'
        ]);
        
        // Update post data
        $post_data = $mapped_property['post_data'];
        $post_data['ID'] = $post_id;
        
        $result = wp_update_post($post_data, true);
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Post update failed: ' . $result->get_error_message()
            ];
        }
        
        // Update meta fields
        $this->assign_meta_fields($post_id, $mapped_property['meta_fields'] ?? []);
        
        // Update taxonomies
        $this->assign_taxonomies($post_id, $mapped_property['taxonomies'] ?? []);
        
        // Update property features
        if ($this->config['assign_property_features']) {
            $this->assign_property_features($post_id, $mapped_property['features'] ?? []);
        }
        
        // Update custom fields
        $this->assign_custom_fields($post_id, $mapped_property['custom_fields'] ?? []);
        
        // Update content hash
        if (isset($mapped_property['content_hash'])) {
            update_post_meta($post_id, 'property_import_hash', $mapped_property['content_hash']);
        }
        
        $this->logger->log('Property updated successfully', 'info', [
            'session_id' => $this->session_id,
            'import_id' => $import_id,
            'post_id' => $post_id,
            'title' => $mapped_property['post_data']['post_title'] ?? 'Unknown'
        ]);
        
        return [
            'success' => true,
            'action' => 'updated',
            'post_id' => $post_id,
            'message' => 'Property updated successfully'
        ];
    }
    
    /**
     * Assign meta fields to post
     *
     * @param int $post_id WordPress post ID
     * @param array $meta_fields Meta fields array
     */
    private function assign_meta_fields($post_id, $meta_fields) {
        foreach ($meta_fields as $meta_key => $meta_value) {
            if ($meta_value !== null && $meta_value !== '') {
                update_post_meta($post_id, $meta_key, $meta_value);
            }
        }
    }
    
    /**
     * Assign taxonomies and terms to post
     *
     * @param int $post_id WordPress post ID
     * @param array $taxonomies Taxonomies array
     */
    private function assign_taxonomies($post_id, $taxonomies) {
        foreach ($taxonomies as $taxonomy => $terms) {
            if (empty($terms)) {
                continue;
            }
            
            $term_ids = [];
            
            foreach ($terms as $term_name) {
                $term_id = $this->get_or_create_term($term_name, $taxonomy);
                if ($term_id) {
                    $term_ids[] = $term_id;
                }
            }
            
            if (!empty($term_ids)) {
                wp_set_object_terms($post_id, $term_ids, $taxonomy);
            }
        }
    }
    
    /**
     * Get existing term ID or create new term
     *
     * @param string $term_name Term name
     * @param string $taxonomy Taxonomy name
     * @return int|null Term ID or null on failure
     */
    private function get_or_create_term($term_name, $taxonomy) {
        // Check if term exists
        $term = get_term_by('name', $term_name, $taxonomy);
        
        if ($term) {
            return $term->term_id;
        }
        
        // Create term if enabled
        if ($this->config['create_missing_terms']) {
            $result = wp_insert_term($term_name, $taxonomy);
            
            if (is_wp_error($result)) {
                $this->logger->log('Failed to create term', 'warning', [
                    'session_id' => $this->session_id,
                    'term_name' => $term_name,
                    'taxonomy' => $taxonomy,
                    'error' => $result->get_error_message()
                ]);
                return null;
            }
            
            $this->stats['created_terms']++;
            
            $this->logger->log('Term created', 'debug', [
                'session_id' => $this->session_id,
                'term_name' => $term_name,
                'taxonomy' => $taxonomy,
                'term_id' => $result['term_id']
            ]);
            
            return $result['term_id'];
        }
        
        return null;
    }
    
    /**
     * Assign property features to post
     *
     * @param int $post_id WordPress post ID
     * @param array $features Features array
     */
    private function assign_property_features($post_id, $features) {
        if (empty($features)) {
            return;
        }
        
        $feature_term_ids = [];
        
        foreach ($features as $feature_slug) {
            $term_id = $this->get_or_create_term($feature_slug, 'property_features');
            if ($term_id) {
                $feature_term_ids[] = $term_id;
            }
        }
        
        if (!empty($feature_term_ids)) {
            wp_set_object_terms($post_id, $feature_term_ids, 'property_features');
            $this->stats['assigned_features'] += count($feature_term_ids);
        }
    }
    
    /**
     * Assign custom fields to post
     *
     * @param int $post_id WordPress post ID
     * @param array $custom_fields Custom fields array
     */
    private function assign_custom_fields($post_id, $custom_fields) {
        foreach ($custom_fields as $field_key => $field_value) {
            if ($field_value !== null && $field_value !== '') {
                update_post_meta($post_id, $field_key, $field_value);
            }
        }
    }
    
    /**
     * ENHANCED v3.0 METHODS
     */
    
    /**
     * Process property with v3.0 structure including gallery and catasto
     *
     * @param array $mapped_property Complete mapped property from v3.0
     * @return array Processing result
     */
    public function process_property_v3($mapped_property) {
        $import_id = $mapped_property['source_data']['id'] ?? 'unknown';
        
        try {
            // Check for existing property
            $existing_post_id = $this->find_existing_property($import_id);
            
            if ($existing_post_id) {
                $result = $this->update_existing_property_v3($existing_post_id, $mapped_property, $import_id);
                $this->stats['updated_properties']++;
            } else {
                $result = $this->create_new_property_v3($mapped_property, $import_id);
                $this->stats['imported_properties']++;
            }
            
            if ($result['success']) {
                // 🏢 ENHANCED v3.0: Process agency data if present
                $agency_result = $this->process_agency_v3($mapped_property);
                
                // Process v3.0 specific features
                $this->process_gallery_v3($result['post_id'], $mapped_property['gallery'] ?? []);
                $this->process_catasto_v3($result['post_id'], $mapped_property['catasto'] ?? []);
                
                // 🔗 Agency association is handled by assign_agency_to_property in create/update methods
                if ($agency_result['success']) {
                    $this->logger->log('🔗 Agency association completed via property_agent field', 'info', [
                        'property_id' => $result['post_id'],
                        'agency_id' => $agency_result['agency_post_id'],
                        'agency_name' => $agency_result['agency_name'] ?? 'Unknown'
                    ]);
                } else {
                    $this->logger->log('🔗 No agency associated with property', 'debug', [
                        'property_id' => $result['post_id'],
                        'reason' => $agency_result['reason'] ?? 'unknown'
                    ]);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->stats['failed_properties']++;
            $this->stats['errors'][] = [
                'import_id' => $import_id,
                'error' => $e->getMessage(),
                'timestamp' => current_time('mysql')
            ];
            
            $this->logger->log('Property processing failed v3.0', 'error', [
                'import_id' => $import_id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create new property with v3.0 enhanced features
     */
    private function create_new_property_v3($mapped_property, $import_id) {
        $this->logger->log('Creating new property v3.0', 'debug', [
            'import_id' => $import_id,
            'title' => $mapped_property['post_data']['post_title'] ?? 'Unknown'
        ]);
        
        // Insert post
        $post_id = wp_insert_post($mapped_property['post_data'], true);
        
        if (is_wp_error($post_id)) {
            return [
                'success' => false,
                'error' => 'Post creation failed: ' . $post_id->get_error_message()
            ];
        }
        
        // Assign all property data
        $this->assign_meta_fields($post_id, $mapped_property['meta_fields'] ?? []);
        $this->assign_taxonomies($post_id, $mapped_property['taxonomies'] ?? []);
        $this->assign_property_features_v3($post_id, $mapped_property['features'] ?? []);
        
        // 🏢 AGENCY ASSOCIATION: Direct property→agency mapping
        $this->assign_agency_to_property($post_id, $mapped_property['source_data'] ?? []);
        
        // Set import metadata
        update_post_meta($post_id, 'property_import_id', $import_id);
        update_post_meta($post_id, 'property_import_hash', $mapped_property['content_hash'] ?? '');
        update_post_meta($post_id, 'property_last_sync', current_time('mysql'));
        update_post_meta($post_id, 'property_import_version', '3.0');
        
        $this->logger->log('Property created successfully v3.0', 'info', [
            'import_id' => $import_id,
            'post_id' => $post_id,
            'title' => $mapped_property['post_data']['post_title'] ?? 'Unknown'
        ]);
        
        // 🎯 TRIGGER: WordPress action for WpResidence integration
        do_action('realestate_sync_property_imported', $post_id, 'created');
        
        return [
            'success' => true,
            'action' => 'created',
            'post_id' => $post_id,
            'message' => 'Property created successfully'
        ];
    }
    
    /**
     * Update existing property with v3.0 enhanced features
     */
    private function update_existing_property_v3($post_id, $mapped_property, $import_id) {
        // Check if content has changed
        if (isset($mapped_property['content_hash'])) {
            $existing_hash = get_post_meta($post_id, 'property_import_hash', true);
            if ($existing_hash === $mapped_property['content_hash']) {
                $this->logger->log('Property content unchanged - skipping update v3.0', 'debug', [
                    'import_id' => $import_id,
                    'post_id' => $post_id
                ]);
                
                update_post_meta($post_id, 'property_last_sync', current_time('mysql'));
                
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'post_id' => $post_id,
                    'message' => 'No changes detected'
                ];
            }
        }
        
        $this->logger->log('Updating existing property v3.0', 'debug', [
            'import_id' => $import_id,
            'post_id' => $post_id,
            'title' => $mapped_property['post_data']['post_title'] ?? 'Unknown'
        ]);
        
        // Update post data
        $post_data = $mapped_property['post_data'];
        $post_data['ID'] = $post_id;
        
        $result = wp_update_post($post_data, true);
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Post update failed: ' . $result->get_error_message()
            ];
        }
        
        // Update all property data
        $this->assign_meta_fields($post_id, $mapped_property['meta_fields'] ?? []);
        $this->assign_taxonomies($post_id, $mapped_property['taxonomies'] ?? []);
        $this->assign_property_features_v3($post_id, $mapped_property['features'] ?? []);
        
        // 🏢 AGENCY ASSOCIATION: Update property→agency mapping
        $this->assign_agency_to_property($post_id, $mapped_property['source_data'] ?? []);
        
        // Update import metadata
        update_post_meta($post_id, 'property_import_hash', $mapped_property['content_hash'] ?? '');
        update_post_meta($post_id, 'property_last_sync', current_time('mysql'));
        update_post_meta($post_id, 'property_import_version', '3.0');
        
        $this->logger->log('Property updated successfully v3.0', 'info', [
            'import_id' => $import_id,
            'post_id' => $post_id,
            'title' => $mapped_property['post_data']['post_title'] ?? 'Unknown'
        ]);
        
        // 🎯 TRIGGER: WordPress action for WpResidence integration
        do_action('realestate_sync_property_imported', $post_id, 'updated');
        
        return [
            'success' => true,
            'action' => 'updated',
            'post_id' => $post_id,
            'message' => 'Property updated successfully'
        ];
    }
    
    /**
     * Assign property features with v3.0 enhanced feature creation
     */
    private function assign_property_features_v3($post_id, $features) {
        if (empty($features)) {
            return;
        }
        
        $feature_term_ids = [];
        
        foreach ($features as $feature_slug) {
            // Check if feature exists by slug
            $term = get_term_by('slug', $feature_slug, 'property_features');
            
            if (!$term && $this->config['create_missing_terms']) {
                // Create human-readable name from slug
                $feature_name = $this->humanize_feature_name($feature_slug);
                
                $result = wp_insert_term($feature_name, 'property_features', [
                    'slug' => $feature_slug
                ]);
                
                if (!is_wp_error($result)) {
                    $term_id = $result['term_id'];
                    $this->stats['created_terms']++;
                    
                    $this->logger->log('Feature created v3.0', 'debug', [
                        'feature_name' => $feature_name,
                        'feature_slug' => $feature_slug,
                        'term_id' => $term_id
                    ]);
                } else {
                    $this->logger->log('Failed to create feature v3.0', 'warning', [
                        'feature_slug' => $feature_slug,
                        'error' => $result->get_error_message()
                    ]);
                    continue;
                }
            } elseif ($term) {
                $term_id = $term->term_id;
            } else {
                continue;
            }
            
            $feature_term_ids[] = $term_id;
        }
        
        if (!empty($feature_term_ids)) {
            wp_set_object_terms($post_id, $feature_term_ids, 'property_features');
            $this->stats['assigned_features'] += count($feature_term_ids);
        }
    }
    
    /**
     * Process gallery v3.0 - ENHANCED with Dual WpResidence Gallery System
     */
    private function process_gallery_v3($post_id, $gallery) {
        if (empty($gallery)) {
            return;
        }
        
        $this->logger->log('Processing gallery v3.0 with Dual WpResidence System', 'info', [
            'post_id' => $post_id,
            'image_count' => count($gallery)
        ]);
        
        // 🖼️ ENHANCED: Use Image Importer v1.0 for complete image processing
        $image_result = $this->image_importer->process_property_images($post_id, $gallery);
        
        if ($image_result['success']) {
            $stats = $image_result['stats'];
            
            // 🎯 DUAL GALLERY SYSTEM: Complete WpResidence compatibility
            $attachment_ids = $image_result['attachment_ids'] ?? [];
            if (!empty($attachment_ids)) {
                $this->set_wpresidence_gallery_compatibility($post_id, $attachment_ids);
                
                $this->logger->log('✅ Dual WpResidence gallery system applied', 'info', [
                    'post_id' => $post_id,
                    'attachment_ids' => implode(',', $attachment_ids),
                    'count' => count($attachment_ids),
                    'systems' => 'wpestate_property_gallery + image_to_attach + menu_order'
                ]);
            }
            
            $this->logger->log('Gallery processed with Image Importer v1.0', 'info', [
                'post_id' => $post_id,
                'downloaded' => $stats['downloaded_images'],
                'skipped' => $stats['skipped_images'],
                'failed' => $stats['failed_images'],
                'featured_set' => $stats['featured_images_set'] > 0
            ]);
            
            // Update stats in main importer
            $this->stats['gallery_images_downloaded'] = ($this->stats['gallery_images_downloaded'] ?? 0) + $stats['downloaded_images'];
            $this->stats['gallery_images_failed'] = ($this->stats['gallery_images_failed'] ?? 0) + $stats['failed_images'];
            
        } else {
            $this->logger->log('Gallery processing failed with Image Importer', 'error', [
                'post_id' => $post_id,
                'error' => $image_result['error']
            ]);
            
            // 🔄 FALLBACK: Store URLs as before (for manual processing later)
            $this->process_gallery_v3_fallback($post_id, $gallery);
        }
    }
    
    /**
     * Fallback gallery processing - store URLs only
     */
    private function process_gallery_v3_fallback($post_id, $gallery) {
        $gallery_ids = [];
        $featured_image_id = null;
        
        foreach ($gallery as $image) {
            if (empty($image['url'])) {
                continue;
            }
            
            $gallery_ids[] = $image['url'];
            
            if ($image['is_featured'] ?? false) {
                $featured_image_id = $image['url'];
            }
        }
        
        if (!empty($gallery_ids)) {
            // Store gallery URLs for future image import
            update_post_meta($post_id, 'wpestate_property_gallery_urls', implode(',', $gallery_ids));
            
            if ($featured_image_id) {
                update_post_meta($post_id, 'property_featured_image_url', $featured_image_id);
            }
            
            $this->logger->log('Gallery URLs stored as fallback', 'debug', [
                'post_id' => $post_id,
                'gallery_count' => count($gallery_ids),
                'has_featured' => !empty($featured_image_id)
            ]);
        }
    }
    
    /**
     * Process agency data v3.0 - FIXED METHOD CALL
     * 
     * @param array $mapped_property Mapped property data with agency info
     * @return array Agency processing result
     */
    private function process_agency_v3($mapped_property) {
        // Check if agency data is present in source_data
        if (empty($mapped_property['source_data']['agency_id'])) {
            $this->logger->log('🏢 No agency ID in source_data for agency processing', 'debug', [
                'property_id' => $mapped_property['source_data']['id'] ?? 'unknown'
            ]);
            return [
                'success' => false,
                'reason' => 'no_agency_id_in_source',
                'agency_post_id' => null
            ];
        }
        
        // Agency ID is already available from Property Mapper - use for verification only
        $agency_id = $mapped_property['source_data']['agency_id'];
        
        $this->logger->log('🏢 Agency already processed by Property Mapper', 'info', [
            'agency_id' => $agency_id,
            'property_id' => $mapped_property['source_data']['id'] ?? 'unknown'
        ]);
        
        // Verify agency exists
        $agency_post = get_post($agency_id);
        if (!$agency_post || $agency_post->post_type !== 'estate_agency') {
            $this->logger->log('🏢 Agency ID not found in WordPress', 'error', [
                'agency_id' => $agency_id
            ]);
            return [
                'success' => false,
                'reason' => 'agency_not_found_in_wp',
                'agency_post_id' => null
            ];
        }
        
        $this->logger->log('🏢 Agency verified successfully', 'info', [
            'agency_id' => $agency_id,
            'agency_name' => $agency_post->post_title
        ]);
        
        return [
            'success' => true,
            'agency_post_id' => $agency_id,
            'agency_name' => $agency_post->post_title,
            'action' => 'verified'
        ];
    }
    
    /**
     * Find existing property by import ID
     */
    private function find_existing_property($import_id) {
        $posts = get_posts([
            'post_type' => $this->post_type,
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
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    /**
     * Assign agency to property using direct property_agent mapping
     * Uses unified dropdown approach - property_agent accepts agency IDs
     * 
     * @param int $post_id Property post ID
     * @param array $source_data Source data containing agency_id
     */
    private function assign_agency_to_property($post_id, $source_data) {
        if (empty($source_data['agency_id'])) {
            $this->logger->log('🏢 No agency ID found in source data', 'debug', [
                'property_id' => $post_id
            ]);
            return;
        }
        
        $agency_id = $source_data['agency_id'];
        
        // Verify agency exists
        $agency_post = get_post($agency_id);
        if (!$agency_post || $agency_post->post_type !== 'estate_agency') {
            $this->logger->log('🏢 Agency not found or invalid type', 'warning', [
                'property_id' => $post_id,
                'agency_id' => $agency_id
            ]);
            return;
        }
        
        // Set property_agent meta field (unified dropdown)
        update_post_meta($post_id, 'property_agent', $agency_id);
        
        $this->logger->log('🏢 Property associated with agency via property_agent', 'info', [
            'property_id' => $post_id,
            'agency_id' => $agency_id,
            'agency_name' => $agency_post->post_title
        ]);
    }
    
    /**
     * Set complete WpResidence gallery compatibility - Dual System
     * 
     * Implements both WpResidence native gallery and WP All Import Add-On compatibility
     * following patterns from KB wpresidence-integration-KB-Reference.md
     * 
     * @param int $post_id Property post ID
     * @param array $attachment_ids Array of attachment IDs
     */
    private function set_wpresidence_gallery_compatibility($post_id, $attachment_ids) {
        $this->logger->log('🎯 DEBUG: Starting Dual Gallery System implementation', 'info', [
            'post_id' => $post_id,
            'attachment_ids' => $attachment_ids,
            'attachment_count' => count($attachment_ids),
            'function' => __FUNCTION__,
            'line' => __LINE__
        ]);
        
        if (empty($attachment_ids)) {
            $this->logger->log('⚠️ DEBUG: No attachment IDs provided - skipping gallery setup', 'warning', [
                'post_id' => $post_id
            ]);
            return;
        }
        
        // SYSTEM 1: WpResidence Theme native gallery
        $this->logger->log('🔧 DEBUG: Setting wpestate_property_gallery (System 1)', 'debug', [
            'post_id' => $post_id,
            'value' => implode(',', $attachment_ids)
        ]);
        update_post_meta($post_id, 'wpestate_property_gallery', implode(',', $attachment_ids));
        $gallery_verify = get_post_meta($post_id, 'wpestate_property_gallery', true);
        $this->logger->log('✅ DEBUG: wpestate_property_gallery verification', 'debug', [
            'post_id' => $post_id,
            'saved_value' => $gallery_verify,
            'success' => !empty($gallery_verify)
        ]);
        
        // SYSTEM 2: WP All Import Add-On compatibility (frontend JavaScript markers)
        $this->logger->log('🔧 DEBUG: Starting image_to_attach setup (System 2)', 'debug', [
            'post_id' => $post_id
        ]);
        $this->update_image_to_attach_field($post_id, $attachment_ids);
        
        // SYSTEM 3: Gallery menu order for proper sorting
        $this->logger->log('🔧 DEBUG: Starting menu order setup (System 3)', 'debug', [
            'post_id' => $post_id
        ]);
        $this->set_gallery_menu_order($post_id, $attachment_ids);
        
        // SYSTEM 4: Force cache refresh for JavaScript markers
        $this->logger->log('🔧 DEBUG: Starting cache refresh (System 4)', 'debug', [
            'post_id' => $post_id
        ]);
        $this->refresh_wpresidence_cache($post_id);
        
        // FINAL VERIFICATION
        $this->debug_verify_dual_gallery_system($post_id, $attachment_ids);
        
        $this->logger->log('✅ DEBUG: Complete WpResidence gallery compatibility set', 'info', [
            'post_id' => $post_id,
            'attachment_count' => count($attachment_ids),
            'systems' => [
                'wpestate_property_gallery' => 'theme_native',
                'image_to_attach' => 'wp_all_import_compatibility',
                'menu_order' => 'gallery_sorting',
                'cache_refresh' => 'javascript_markers'
            ]
        ]);
    }
    
    /**
     * Update image_to_attach field for WP All Import Add-On compatibility
     * 
     * @param int $post_id Property post ID
     * @param array $attachment_ids Array of attachment IDs
     */
    private function update_image_to_attach_field($post_id, $attachment_ids) {
        $this->logger->log('🔍 DEBUG: Starting image_to_attach field update', 'debug', [
            'post_id' => $post_id,
            'new_attachment_ids' => $attachment_ids
        ]);
        
        // Get current images from image_to_attach field
        $current_images = get_post_meta($post_id, 'image_to_attach', true);
        $this->logger->log('🔍 DEBUG: Current image_to_attach value', 'debug', [
            'post_id' => $post_id,
            'current_value' => $current_images,
            'is_empty' => empty($current_images)
        ]);
        
        $current_images = !empty($current_images) ? explode(",", $current_images) : array();
        $original_count = count($current_images);
        
        // Add new images (avoid duplicates)
        $added_count = 0;
        foreach ($attachment_ids as $attachment_id) {
            if (!in_array($attachment_id, $current_images)) {
                $current_images[] = $attachment_id;
                $added_count++;
                $this->logger->log('🔍 DEBUG: Added attachment to image_to_attach', 'debug', [
                    'post_id' => $post_id,
                    'attachment_id' => $attachment_id,
                    'total_count' => count($current_images)
                ]);
            } else {
                $this->logger->log('🔍 DEBUG: Attachment already exists in image_to_attach', 'debug', [
                    'post_id' => $post_id,
                    'attachment_id' => $attachment_id
                ]);
            }
        }
        
        // Update field with clean comma-separated string
        $current_images_string = implode(",", array_filter($current_images));
        update_post_meta($post_id, 'image_to_attach', trim($current_images_string, ","));
        
        // Verify update
        $saved_value = get_post_meta($post_id, 'image_to_attach', true);
        
        $this->logger->log('✅ DEBUG: image_to_attach field updated for WP All Import compatibility', 'info', [
            'post_id' => $post_id,
            'original_count' => $original_count,
            'added_count' => $added_count,
            'final_count' => count($current_images),
            'image_to_attach' => $current_images_string,
            'saved_value' => $saved_value,
            'update_successful' => ($saved_value === trim($current_images_string, ","))
        ]);
    }
    
    /**
     * Set gallery menu order for proper image sorting
     * 
     * @param int $post_id Property post ID
     * @param array $attachment_ids Array of attachment IDs
     */
    private function set_gallery_menu_order($post_id, $attachment_ids) {
        $count = 1;
        foreach ($attachment_ids as $attachment_id) {
            $result = wp_update_post(array(
                'ID' => $attachment_id,
                'post_parent' => $post_id,
                'menu_order' => $count,
            ));
            
            if (is_wp_error($result)) {
                $this->logger->log('Failed to set menu_order for attachment', 'warning', [
                    'post_id' => $post_id,
                    'attachment_id' => $attachment_id,
                    'menu_order' => $count,
                    'error' => $result->get_error_message()
                ]);
            }
            
            $count++;
        }
        
        $this->logger->log('Gallery menu order set for proper sorting', 'debug', [
            'post_id' => $post_id,
            'attachments_ordered' => count($attachment_ids)
        ]);
    }
    
    /**
     * Refresh WpResidence cache for JavaScript markers integration
     * 
     * @param int $post_id Property post ID
     */
    private function refresh_wpresidence_cache($post_id) {
        // Clear transient caches for marker generation
        delete_transient('wpestate_markers_default_pins');
        delete_transient('wpestate_markers_imported_properties');
        delete_transient('wpestate_pin_images');
        
        // Clear taxonomy cache (needed for hidden_address generation)
        wp_cache_flush();
        
        // Clear specific property meta cache
        wp_cache_delete($post_id, 'post_meta');
        
        $this->logger->log('WpResidence cache refreshed for JavaScript integration', 'debug', [
            'post_id' => $post_id,
            'cleared_caches' => [
                'wpestate_markers_default_pins',
                'wpestate_markers_imported_properties', 
                'wpestate_pin_images',
                'post_meta_cache',
                'taxonomy_cache'
            ]
        ]);
    }
    
    /**
     * Convert feature slug to human-readable name
     */
    private function humanize_feature_name($slug) {
        // Feature name mapping
        $feature_names = [
            'ascensore' => 'Ascensore',
            'aria-condizionata' => 'Aria condizionata',
            'giardino' => 'Giardino',
            'piscina' => 'Piscina',
            'box-o-garage' => 'Box o garage',
            'vista-panoramica' => 'Vista panoramica',
            'riscaldamento-autonomo-centralizzato' => 'Riscaldamento autonomo',
            'vicinanza-ai-trasporti-pubblici' => 'Vicinanza ai trasporti pubblici',
            'prossimita-a-sentieri-escursionistici' => 'Sentieri escursionistici',
            'vicinanza-a-parchi-naturali' => 'Parchi naturali',
            'arredato' => 'Arredato',
            'non-arredato' => 'Non arredato',
            'riscaldamento-a-pavimento' => 'Riscaldamento a pavimento',
            'allarme' => 'Allarme',
            'camino' => 'Camino',
            'domotica' => 'Domotica',
            'porta-blindata' => 'Porta blindata',
            'cantina' => 'Cantina',
            'terrazza' => 'Terrazza',
            'balcone' => 'Balcone',
            'lavanderia' => 'Lavanderia',
            'mansarda' => 'Mansarda',
            'taverna' => 'Taverna',
            'soffitta' => 'Soffitta',
            'porticato' => 'Porticato',
            'soppalco' => 'Soppalco',
            'aree-esterne' => 'Aree esterne',
            'accesso-disabili' => 'Accesso disabili',
            'area-fitness' => 'Area fitness',
            'vasca-idromassaggio' => 'Vasca idromassaggio',
            'portineria' => 'Portineria',
            'tapparelle-motorizzate' => 'Tapparelle motorizzate',
            'zanzariere' => 'Zanzariere',
            'tende-da-sole' => 'Tende da sole',
            'negozi-e-servizi' => 'Negozi e servizi',
            'accesso-alle-piste-da-sci' => 'Accesso alle piste da sci'
        ];
        
        return $feature_names[$slug] ?? ucwords(str_replace('-', ' ', $slug));
    }
    
    /**
     * Get import statistics
     *
     * @return array Statistics
     */
    public function get_stats() {
        return $this->stats;
    }
    
    /**
     * Get import configuration
     *
     * @return array Configuration
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Get version and capabilities
     */
    public function get_version_info() {
        return [
            'version' => '3.0.0',
            'capabilities' => [
                'basic_import' => true,
                'enhanced_mapping' => true,
                'gallery_support' => true,
                'catasto_support' => true,
                'feature_creation' => true,
                'change_detection' => true,
                'target_page_compliance' => true,
                'dual_gallery_system' => true,
                'wpresidence_integration' => true
            ],
            'supported_post_type' => $this->post_type,
            'supported_taxonomies' => [
                'property_action_category',
                'property_category', 
                'property_city',
                'property_area',
                'property_county_state',
                'property_features'
            ]
        ];
    }
    
    /**
     * DEBUG METHOD: Test dual gallery system on specific property
     * 
     * @param int $post_id Property post ID to test
     * @return array Test results
     */
    public function debug_test_dual_gallery_on_property($post_id) {
        $this->logger->log('📋 DEBUG TEST: Starting dual gallery system test', 'info', [
            'post_id' => $post_id,
            'method' => __FUNCTION__
        ]);
        
        // Check if property exists
        $property = get_post($post_id);
        if (!$property || $property->post_type !== 'estate_property') {
            return [
                'success' => false,
                'error' => 'Property not found or not estate_property type',
                'post_id' => $post_id
            ];
        }
        
        // Get current gallery data
        $current_gallery = get_post_meta($post_id, 'wpestate_property_gallery', true);
        $current_attach = get_post_meta($post_id, 'image_to_attach', true);
        
        // Get property attachments
        $attachments = get_attached_media('image', $post_id);
        $attachment_ids = array_keys($attachments);
        
        $this->logger->log('📋 DEBUG TEST: Property gallery status', 'debug', [
            'post_id' => $post_id,
            'current_wpestate_gallery' => $current_gallery,
            'current_image_to_attach' => $current_attach,
            'found_attachments' => $attachment_ids,
            'attachment_count' => count($attachment_ids)
        ]);
        
        if (empty($attachment_ids)) {
            return [
                'success' => false,
                'error' => 'No image attachments found for this property',
                'post_id' => $post_id,
                'current_data' => [
                    'wpestate_property_gallery' => $current_gallery,
                    'image_to_attach' => $current_attach
                ]
            ];
        }
        
        // Test the dual gallery system
        $this->set_wpresidence_gallery_compatibility($post_id, $attachment_ids);
        
        // Verify results
        $verification = $this->debug_verify_dual_gallery_system($post_id, $attachment_ids);
        
        // Test JavaScript markers if function available
        $js_marker_test = null;
        if (function_exists('wpestate_listing_pins')) {
            try {
                $js_marker_test = wpestate_listing_pins('debug_test', 0, '', 1, '', $post_id);
            } catch (Exception $e) {
                $js_marker_test = 'ERROR: ' . $e->getMessage();
            }
        }
        
        $test_result = [
            'success' => true,
            'post_id' => $post_id,
            'property_title' => $property->post_title,
            'attachment_ids_processed' => $attachment_ids,
            'verification' => $verification,
            'javascript_marker_test' => [
                'function_available' => function_exists('wpestate_listing_pins'),
                'marker_result' => $js_marker_test,
                'marker_length' => strlen($js_marker_test ?? ''),
                'preview' => substr($js_marker_test ?? '', 0, 200) . '...'
            ]
        ];
        
        $this->logger->log('✅ DEBUG TEST: Dual gallery system test complete', 'info', [
            'post_id' => $post_id,
            'overall_success' => $verification['overall_success'] ?? false,
            'test_summary' => [
                'gallery_field_set' => !empty($verification['wpestate_property_gallery']['raw_value']),
                'attach_field_set' => !empty($verification['image_to_attach']['raw_value']),
                'js_function_available' => function_exists('wpestate_listing_pins'),
                'js_markers_generated' => !empty($js_marker_test)
            ]
        ]);
        
        return $test_result;
    }
}
