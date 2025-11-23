<?php
/**
 * Media Deduplicator Class
 * 
 * Universal URL-based media deduplication system for all imports
 * Prevents duplicate downloads and optimizes storage
 * 
 * @package RealEstate_Sync
 * @version 1.3.0
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RealEstate_Sync_Media_Deduplicator {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Cache for URL lookups
     */
    private $url_cache = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
    }
    
    /**
     * Import media without duplication - UNIVERSAL METHOD
     * 
     * @param string $media_url Original media URL
     * @param int $post_id WordPress post ID to attach to
     * @param string $type Type of media ('logo', 'property_image', 'featured')
     * @return int|false Attachment ID or false on failure
     */
    public function import_media_without_duplication($media_url, $post_id = 0, $type = 'property_image') {
        if (empty($media_url)) {
            return false;
        }
        
        // Check if URL already exists in WordPress
        $existing_attachment_id = $this->find_attachment_by_url($media_url);
        
        if ($existing_attachment_id) {
            $this->logger->log("Media already exists, reusing: {$media_url} (ID: {$existing_attachment_id})", 'info');
            
            // Attach to post if needed
            if ($post_id > 0) {
                $this->attach_media_to_post($existing_attachment_id, $post_id, $type);
            }
            
            return $existing_attachment_id;
        }
        
        // Media doesn't exist, download and import
        $this->logger->log("Downloading new media: {$media_url}", 'info');
        return $this->download_and_import_media($media_url, $post_id, $type);
    }
    
    /**
     * Find existing attachment by URL
     * 
     * @param string $media_url
     * @return int|false Attachment ID or false if not found
     */
    private function find_attachment_by_url($media_url) {
        // Check cache first
        if (isset($this->url_cache[$media_url])) {
            return $this->url_cache[$media_url];
        }
        
        // Extract filename from URL
        $filename = basename(parse_url($media_url, PHP_URL_PATH));
        
        if (empty($filename)) {
            return false;
        }
        
        // Search by filename in uploads directory
        $attachment_id = $this->find_attachment_by_filename($filename);
        
        if ($attachment_id) {
            // Verify URL match in meta
            $existing_url = get_post_meta($attachment_id, '_source_url', true);
            if ($existing_url === $media_url) {
                $this->url_cache[$media_url] = $attachment_id;
                return $attachment_id;
            }
        }
        
        // Search by source URL meta
        $attachment_id = $this->find_attachment_by_source_url($media_url);
        
        if ($attachment_id) {
            $this->url_cache[$media_url] = $attachment_id;
            return $attachment_id;
        }
        
        // Cache negative result
        $this->url_cache[$media_url] = false;
        return false;
    }
    
    /**
     * Find attachment by filename
     * 
     * @param string $filename
     * @return int|false
     */
    private function find_attachment_by_filename($filename) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND post_title LIKE %s 
             OR guid LIKE %s 
             LIMIT 1",
            '%' . $wpdb->esc_like($filename) . '%',
            '%' . $wpdb->esc_like($filename) . '%'
        );
        
        $attachment_id = $wpdb->get_var($query);
        
        return $attachment_id ? (int) $attachment_id : false;
    }
    
    /**
     * Find attachment by source URL meta
     * 
     * @param string $source_url
     * @return int|false
     */
    private function find_attachment_by_source_url($source_url) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_source_url' 
             AND meta_value = %s 
             LIMIT 1",
            $source_url
        );
        
        $post_id = $wpdb->get_var($query);
        
        return $post_id ? (int) $post_id : false;
    }
    
    /**
     * Download and import new media
     * 
     * @param string $media_url
     * @param int $post_id
     * @param string $type
     * @return int|false Attachment ID or false on failure
     */
    private function download_and_import_media($media_url, $post_id = 0, $type = 'property_image') {
        try {
            // Include WordPress media functions
            if (!function_exists('media_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }
            
            // Download file to temp location
            $temp_file = download_url($media_url);
            
            if (is_wp_error($temp_file)) {
                $this->logger->log("Failed to download media: {$media_url} - " . $temp_file->get_error_message(), 'error');
                return false;
            }
            
            // Prepare file data
            $file_data = [
                'name' => $this->generate_filename($media_url, $type),
                'tmp_name' => $temp_file,
                'error' => 0,
                'size' => filesize($temp_file),
                'type' => $this->get_mime_type($temp_file)
            ];
            
            // Import file to WordPress media library
            $attachment_id = media_handle_sideload($file_data, $post_id);
            
            // Clean up temp file
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            if (is_wp_error($attachment_id)) {
                $this->logger->log("Failed to import media: {$media_url} - " . $attachment_id->get_error_message(), 'error');
                return false;
            }
            
            // Store source URL for future deduplication
            update_post_meta($attachment_id, '_source_url', $media_url);
            update_post_meta($attachment_id, '_import_type', $type);
            update_post_meta($attachment_id, '_import_date', current_time('mysql'));
            
            $this->logger->log("Media imported successfully: {$media_url} (ID: {$attachment_id})", 'success');
            
            // Cache the result
            $this->url_cache[$media_url] = $attachment_id;
            
            return $attachment_id;
            
        } catch (Exception $e) {
            $this->logger->log("Media import exception: {$media_url} - " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Generate appropriate filename
     * 
     * @param string $url
     * @param string $type
     * @return string
     */
    private function generate_filename($url, $type) {
        $filename = basename(parse_url($url, PHP_URL_PATH));
        
        // Add type prefix for organization
        $prefix_map = [
            'logo' => 'agency-logo-',
            'property_image' => 'property-',
            'featured' => 'featured-'
        ];
        
        $prefix = isset($prefix_map[$type]) ? $prefix_map[$type] : 'import-';
        
        // Ensure unique filename
        $info = pathinfo($filename);
        $name = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        
        return $prefix . sanitize_file_name($name) . $ext;
    }
    
    /**
     * Get MIME type of file
     * 
     * @param string $file_path
     * @return string
     */
    private function get_mime_type($file_path) {
        if (function_exists('mime_content_type')) {
            return mime_content_type($file_path);
        }
        
        // Fallback based on extension
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime_types = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        
        return isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';
    }
    
    /**
     * Attach media to post if not already attached
     * 
     * @param int $attachment_id
     * @param int $post_id
     * @param string $type
     */
    private function attach_media_to_post($attachment_id, $post_id, $type) {
        // Check if already attached
        $current_parent = wp_get_post_parent_id($attachment_id);
        
        if ($current_parent != $post_id) {
            // Update attachment parent
            wp_update_post([
                'ID' => $attachment_id,
                'post_parent' => $post_id
            ]);
            
            $this->logger->log("Attached media {$attachment_id} to post {$post_id}", 'info');
        }
        
        // Handle special attachment types
        if ($type === 'featured') {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }
    
    /**
     * Import multiple media URLs at once
     * 
     * @param array $media_urls Array of URLs
     * @param int $post_id Post to attach to
     * @param string $type Media type
     * @return array Array of attachment IDs
     */
    public function import_multiple_media($media_urls, $post_id = 0, $type = 'property_image') {
        $attachment_ids = [];
        
        foreach ($media_urls as $url) {
            $attachment_id = $this->import_media_without_duplication($url, $post_id, $type);
            if ($attachment_id) {
                $attachment_ids[] = $attachment_id;
            }
        }
        
        $this->logger->log("Imported " . count($attachment_ids) . " of " . count($media_urls) . " media files", 'info');
        
        return $attachment_ids;
    }
    
    /**
     * Get deduplication statistics
     * 
     * @return array Statistics about media deduplication
     */
    public function get_deduplication_stats() {
        global $wpdb;
        
        $total_imports = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_import_type'"
        );
        
        $unique_sources = $wpdb->get_var(
            "SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_source_url'"
        );
        
        return [
            'total_imported_media' => (int) $total_imports,
            'unique_source_urls' => (int) $unique_sources,
            'cache_hits' => count(array_filter($this->url_cache)),
            'duplicate_prevention_ratio' => $unique_sources > 0 ? round(($total_imports - $unique_sources) / $total_imports * 100, 2) : 0
        ];
    }
    
    /**
     * Clear URL cache
     */
    public function clear_cache() {
        $this->url_cache = [];
        $this->logger->log('Media deduplication cache cleared', 'info');
    }
}
