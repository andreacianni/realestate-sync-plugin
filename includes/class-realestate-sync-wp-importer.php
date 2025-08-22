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
     * Initialize WordPress importer
     */
    private function init_importer() {
        $this->load_config();
        $this->reset_stats();
        
        $this->logger->log('WordPress Importer initialized', 'debug', [
            'post_type' => $this->post_type,
            'duplicate_action' => $this->config['duplicate_action'],
            'batch_size' => $this->config['batch_size']
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
     * Process gallery v3.0 - ENHANCED with Image Importer v1.0
     */
    private function process_gallery_v3($post_id, $gallery) {
        if (empty($gallery)) {
            return;
        }
        
        $this->logger->log('Processing gallery v3.0 with Image Importer', 'info', [
            'post_id' => $post_id,
            'image_count' => count($gallery)
        ]);
        
        // 🖼️ ENHANCED: Use Image Importer v1.0 for complete image processing
        $image_result = $this->image_importer->process_property_images($post_id, $gallery);
        
        if ($image_result['success']) {
            $stats = $image_result['stats'];
            
            // 🆕 GALLERY FIX: Set wpestate_property_gallery for frontend display
            $attachment_ids = $image_result['attachment_ids'] ?? [];
            if (!empty($attachment_ids)) {
                // Set both formats for maximum compatibility
                update_post_meta($post_id, 'wpestate_property_gallery', implode(',', $attachment_ids));
                
                $this->logger->log('✅ WpResidence gallery field set for frontend', 'info', [
                    'post_id' => $post_id,
                    'attachment_ids' => implode(',', $attachment_ids),
                    'count' => count($attachment_ids)
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
                'target_page_compliance' => true
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
}
