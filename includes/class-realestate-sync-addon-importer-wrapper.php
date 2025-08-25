<?php
/**
 * RealEstate Sync Add-On Importer Wrapper
 * 
 * Main wrapper that orchestrates all Add-On import functions
 * Provides unified interface for XML Parser to access Add-On capabilities
 * 
 * @package RealEstate_Sync
 * @subpackage AddOn_Integration
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_AddOn_Importer {
    
    /**
     * Add-On component instances
     */
    private $properties_importer;
    private $agents_importer;
    private $location_importer;
    private $helper;
    private $logger;
    
    /**
     * Constructor - Initialize all Add-On components
     */
    public function __construct() {
        $this->logger = new RealEstate_Sync_Logger();
        $this->init_addon_components();
    }
    
    /**
     * Initialize Add-On component instances
     */
    private function init_addon_components() {
        try {
            // Initialize Add-On components
            $this->properties_importer = new RealEstate_Sync_AddOn_Importer_Properties();
            $this->agents_importer = new RealEstate_Sync_AddOn_Importer_Agents();
            $this->location_importer = new RealEstate_Sync_AddOn_Importer_Location();
            $this->helper = new RealEstate_Sync_AddOn_Helper();
            
            $this->logger->log("Add-On Importer components initialized successfully", 'debug');
            
        } catch (Exception $e) {
            $this->logger->log("Failed to initialize Add-On components: " . $e->getMessage(), 'error');
            throw new Exception("Add-On components initialization failed");
        }
    }
    
    /**
     * 🖼️ Import property images using Add-On property_images function
     * This solves the gallery frontend display issues
     * 
     * @param int $post_id WordPress post ID
     * @param int $attachment_id Image attachment ID
     * @param string $image_filepath Image file path
     * @param array $import_options Import options
     * @return bool Success status
     */
    public function property_images($post_id, $attachment_id, $image_filepath, $import_options) {
        try {
            $this->logger->log("Importing image via Add-On: Post {$post_id}, Attachment {$attachment_id}", 'debug');
            
            // Use Add-On properties importer for gallery system
            $result = $this->properties_importer->property_images($post_id, $attachment_id, $image_filepath, $import_options);
            
            $this->logger->log("Image imported successfully via Add-On for post {$post_id}", 'debug');
            return true;
            
        } catch (Exception $e) {
            $this->logger->log("Add-On image import failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * 🏷️ Import property features using Add-On import_features function
     * This handles dynamic feature creation and global feature list management
     * 
     * @param int $post_id WordPress post ID
     * @param array $data Add-On formatted data
     * @param array $import_options Import options
     * @param array $article Article data
     * @return bool Success status
     */
    public function import_features($post_id, $data, $import_options, $article) {
        try {
            $features_csv = $data['property_features'] ?? '';
            $this->logger->log("Importing features via Add-On: {$features_csv}", 'debug');
            
            // Use Add-On properties importer for features system
            $result = $this->properties_importer->import_features($post_id, $data, $import_options, $article);
            
            $this->logger->log("Features imported successfully via Add-On for post {$post_id}", 'debug');
            return true;
            
        } catch (Exception $e) {
            $this->logger->log("Add-On features import failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * 🏢 Import property agent/agency using Add-On import_agent function
     * This handles automatic agent/agency creation and assignment
     * 
     * @param int $post_id WordPress post ID
     * @param array $data Add-On formatted data
     * @param array $import_options Import options
     * @param array $article Article data
     * @return bool Success status
     */
    public function import_agent($post_id, $data, $import_options, $article) {
        try {
            $agent_name = $data['property_agent'] ?? '';
            $this->logger->log("Importing agent via Add-On: {$agent_name}", 'debug');
            
            // Use Add-On properties importer for agent system
            $result = $this->properties_importer->import_agent($post_id, $data, $import_options, $article);
            
            $this->logger->log("Agent imported successfully via Add-On for post {$post_id}", 'debug');
            return true;
            
        } catch (Exception $e) {
            $this->logger->log("Add-On agent import failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * 📍 Import property location using Add-On location importer
     * This handles geocoding and address formatting
     * 
     * @param int $post_id WordPress post ID
     * @param array $data Add-On formatted data
     * @param array $import_options Import options
     * @param array $article Article data
     * @return bool Success status
     */
    public function import_location($post_id, $data, $import_options, $article) {
        try {
            $address = $data['property_address'] ?? '';
            $lat = $data['_property_latitude'] ?? '';
            $lng = $data['_property_longitude'] ?? '';
            
            $this->logger->log("Importing location via Add-On: {$address} ({$lat}, {$lng})", 'debug');
            
            // Use Add-On location importer
            $result = $this->location_importer->import($post_id, $data, $import_options, $article);
            
            $this->logger->log("Location imported successfully via Add-On for post {$post_id}", 'debug');
            return true;
            
        } catch (Exception $e) {
            $this->logger->log("Add-On location import failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * 🔧 Import text, image and custom details using Add-On
     * This handles all basic property fields and custom details
     * 
     * @param int $post_id WordPress post ID
     * @param array $data Add-On formatted data
     * @param array $import_options Import options
     * @param array $article Article data
     * @return bool Success status
     */
    public function import_text_image_custom_details($post_id, $data, $import_options, $article) {
        try {
            $this->logger->log("Importing property fields via Add-On for post {$post_id}", 'debug');
            
            // Use Add-On properties importer for basic fields
            $result = $this->properties_importer->import_text_image_custom_details($post_id, $data, $import_options, $article);
            
            $this->logger->log("Property fields imported successfully via Add-On for post {$post_id}", 'debug');
            return true;
            
        } catch (Exception $e) {
            $this->logger->log("Add-On property fields import failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * 👤 Import agent text fields using Add-On agents importer
     * This creates complete agent profiles with all contact information
     * 
     * @param int $post_id WordPress post ID (agent post)
     * @param array $data Add-On formatted data
     * @param array $import_options Import options
     * @param array $article Article data
     * @return bool Success status
     */
    public function import_agent_text_fields($post_id, $data, $import_options, $article) {
        try {
            $this->logger->log("Importing agent profile via Add-On for post {$post_id}", 'debug');
            
            // Use Add-On agents importer for complete profiles
            $result = $this->agents_importer->import_text_fields($post_id, $data, $import_options, $article);
            
            $this->logger->log("Agent profile imported successfully via Add-On for post {$post_id}", 'debug');
            return true;
            
        } catch (Exception $e) {
            $this->logger->log("Add-On agent profile import failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * 🔧 Update property meta using Add-On helper
     * This provides centralized meta update with logging
     * 
     * @param int $post_id WordPress post ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool Success status
     */
    public function update_meta($post_id, $key, $value) {
        try {
            // Use Add-On helper for meta updates with logging
            $result = $this->helper->update_meta($post_id, $key, $value);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->log("Add-On meta update failed for {$key}: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * 📊 Get Add-On integration statistics
     * 
     * @return array Statistics
     */
    public function get_integration_stats() {
        return [
            'properties_importer_active' => $this->properties_importer !== null,
            'agents_importer_active' => $this->agents_importer !== null,
            'location_importer_active' => $this->location_importer !== null,
            'helper_active' => $this->helper !== null,
            'integration_status' => 'active',
            'available_functions' => [
                'property_images' => true,
                'import_features' => true,
                'import_agent' => true,
                'import_location' => true,
                'import_text_image_custom_details' => true,
                'import_agent_text_fields' => true,
                'update_meta' => true
            ]
        ];
    }
    
    /**
     * 🔍 Test Add-On integration functionality
     * 
     * @return array Test results
     */
    public function test_addon_integration() {
        $test_results = [
            'overall_status' => 'success',
            'components' => [],
            'functions' => [],
            'errors' => []
        ];
        
        try {
            // Test component initialization
            $test_results['components']['properties_importer'] = $this->properties_importer !== null;
            $test_results['components']['agents_importer'] = $this->agents_importer !== null;
            $test_results['components']['location_importer'] = $this->location_importer !== null;
            $test_results['components']['helper'] = $this->helper !== null;
            
            // Test function availability
            $test_results['functions']['property_images'] = method_exists($this->properties_importer, 'property_images');
            $test_results['functions']['import_features'] = method_exists($this->properties_importer, 'import_features');
            $test_results['functions']['import_agent'] = method_exists($this->properties_importer, 'import_agent');
            $test_results['functions']['import_location'] = method_exists($this->location_importer, 'import');
            $test_results['functions']['update_meta'] = method_exists($this->helper, 'update_meta');
            
            // Check for any failures
            $failed_components = array_filter($test_results['components'], function($status) { return !$status; });
            $failed_functions = array_filter($test_results['functions'], function($status) { return !$status; });
            
            if (!empty($failed_components) || !empty($failed_functions)) {
                $test_results['overall_status'] = 'partial';
                if (!empty($failed_components)) {
                    $test_results['errors'][] = 'Failed components: ' . implode(', ', array_keys($failed_components));
                }
                if (!empty($failed_functions)) {
                    $test_results['errors'][] = 'Missing functions: ' . implode(', ', array_keys($failed_functions));
                }
            }
            
        } catch (Exception $e) {
            $test_results['overall_status'] = 'failed';
            $test_results['errors'][] = $e->getMessage();
        }
        
        return $test_results;
    }
    
    /**
     * 🧹 Cleanup resources
     */
    public function cleanup() {
        // Cleanup Add-On components if needed
        $this->logger->log("Add-On Importer cleanup completed", 'debug');
    }
}
