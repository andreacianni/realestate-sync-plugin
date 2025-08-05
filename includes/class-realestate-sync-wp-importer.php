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
        $this->init_importer();
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
}
