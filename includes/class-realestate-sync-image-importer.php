<?php
/**
 * RealEstate Sync Plugin - Image Importer
 * 
 * Handles downloading and importing images from external URLs into WordPress Media Library.
 * Manages property galleries, featured images, and planimetrie with WpResidence compatibility.
 * 
 * @package RealEstateSync
 * @version 1.0.0
 * @author Andrea Cianni - Novacom
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

/**
 * RealEstate_Sync_Image_Importer Class
 * 
 * Manages image import operations including:
 * - Download images from external URLs
 * - WordPress Media Library integration
 * - WpResidence gallery compatibility
 * - Featured image setting
 * - Duplicate detection and management
 * - Image optimization and resizing
 */
class RealEstate_Sync_Image_Importer {
    
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
     * Allowed image types
     */
    private $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg', 
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    /**
     * Constructor
     *
     * @param RealEstate_Sync_Logger $logger Logger instance
     */
    public function __construct($logger = null) {
        $this->logger = $logger ?: RealEstate_Sync_Logger::get_instance();
        $this->init_importer();
    }
    
    /**
     * Initialize image importer
     */
    private function init_importer() {
        $this->load_config();
        $this->reset_stats();
        
        $this->logger->log('Image Importer initialized', 'debug', [
            'max_file_size_mb' => $this->config['max_file_size_mb'],
            'enable_optimization' => $this->config['enable_optimization'],
            'allowed_types' => array_keys($this->allowed_types)
        ]);
    }
    
    /**
     * Load importer configuration
     */
    private function load_config() {
        $defaults = [
            'max_file_size_mb' => 10,
            'enable_optimization' => true,
            'max_width' => 1920,
            'max_height' => 1080,
            'jpeg_quality' => 85,
            'create_thumbnails' => true,
            'timeout_seconds' => 30,
            'retry_attempts' => 3,
            'skip_existing' => true,
            'validate_ssl' => false, // For development
            'chunk_size' => 5, // Images per batch
            'sleep_between_downloads' => 1 // Seconds
        ];
        
        $this->config = get_option('realestate_sync_image_importer_config', $defaults);
    }
    
    /**
     * Reset import statistics
     */
    private function reset_stats() {
        $this->stats = [
            'total_images' => 0,
            'downloaded_images' => 0,
            'skipped_images' => 0,
            'failed_images' => 0,
            'created_attachments' => 0,
            'featured_images_set' => 0,
            'galleries_processed' => 0,
            'total_size_mb' => 0,
            'errors' => []
        ];
    }
    
    /**
     * Process property gallery and featured image import
     *
     * @param int $post_id WordPress post ID
     * @param array $gallery Gallery data from mapped property
     * @return array Processing result
     */
    public function process_property_images($post_id, $gallery) {
        if (empty($gallery)) {
            return [
                'success' => true,
                'message' => 'No images to process',
                'stats' => $this->stats
            ];
        }
        
        $this->logger->log('Processing property images', 'info', [
            'post_id' => $post_id,
            'image_count' => count($gallery)
        ]);
        
        try {
            $attachment_ids = [];
            $featured_attachment_id = null;
            
            foreach ($gallery as $image_data) {
                if (empty($image_data['url'])) {
                    continue;
                }
                
                $this->stats['total_images']++;
                
                // Check if attachment already exists
                if ($this->config['skip_existing']) {
                    $existing_id = $this->find_existing_attachment($image_data['url'], $post_id);
                    if ($existing_id) {
                        $attachment_ids[] = $existing_id;
                        $this->stats['skipped_images']++;
                        
                        if ($image_data['is_featured'] ?? false) {
                            $featured_attachment_id = $existing_id;
                        }
                        
                        continue;
                    }
                }
                
                // Download and import image
                $result = $this->download_and_import_image($image_data, $post_id);
                
                if ($result['success']) {
                    $attachment_ids[] = $result['attachment_id'];
                    $this->stats['downloaded_images']++;
                    $this->stats['created_attachments']++;
                    
                    if ($image_data['is_featured'] ?? false) {
                        $featured_attachment_id = $result['attachment_id'];
                    }
                    
                    $this->logger->log('Image imported successfully', 'debug', [
                        'url' => $image_data['url'],
                        'attachment_id' => $result['attachment_id'],
                        'post_id' => $post_id
                    ]);
                } else {
                    $this->stats['failed_images']++;
                    $this->stats['errors'][] = [
                        'url' => $image_data['url'],
                        'error' => $result['error'],
                        'post_id' => $post_id
                    ];
                    
                    $this->logger->log('Image import failed', 'warning', [
                        'url' => $image_data['url'],
                        'error' => $result['error'],
                        'post_id' => $post_id
                    ]);
                }
                
                // Sleep between downloads to avoid overwhelming server
                if ($this->config['sleep_between_downloads'] > 0) {
                    sleep($this->config['sleep_between_downloads']);
                }
            }
            
            // Set featured image
            if ($featured_attachment_id) {
                $this->set_featured_image($post_id, $featured_attachment_id);
                $this->stats['featured_images_set']++;
            }
            
            // Update WpResidence gallery
            if (!empty($attachment_ids)) {
                $this->update_wpresidence_gallery($post_id, $attachment_ids);
                $this->stats['galleries_processed']++;
            }
            
            $this->logger->log('Property images processed successfully', 'info', [
                'post_id' => $post_id,
                'attachments_created' => count($attachment_ids),
                'featured_set' => !empty($featured_attachment_id)
            ]);
            
            return [
                'success' => true,
                'attachment_ids' => $attachment_ids,
                'featured_attachment_id' => $featured_attachment_id,
                'stats' => $this->stats
            ];
            
        } catch (Exception $e) {
            $this->logger->log('Property images processing failed', 'error', [
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->stats
            ];
        }
    }
    
    /**
     * Download and import single image
     *
     * @param array $image_data Image data
     * @param int $post_id Parent post ID
     * @return array Import result
     */
    private function download_and_import_image($image_data, $post_id) {
        $url = $image_data['url'];
        $type = $image_data['type'] ?? 'image';
        
        try {
            // Download image
            $download_result = $this->download_image($url);
            
            if (!$download_result['success']) {
                return $download_result;
            }
            
            $temp_file = $download_result['file_path'];
            $file_info = $download_result['file_info'];
            
            // Create WordPress attachment
            $attachment_result = $this->create_wordpress_attachment($temp_file, $file_info, $post_id, $image_data);
            
            // Cleanup temp file
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            return $attachment_result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Download image from URL to temporary file
     *
     * @param string $url Image URL
     * @return array Download result
     */
    private function download_image($url) {
        // Create temporary file
        $temp_dir = wp_upload_dir()['basedir'] . '/temp-images/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $temp_file = $temp_dir . 'img_' . time() . '_' . uniqid() . '.tmp';
        
        // Setup HTTP request
        $args = [
            'timeout' => $this->config['timeout_seconds'],
            'redirection' => 5,
            'blocking' => true,
            'headers' => [
                'User-Agent' => 'RealEstate Sync Plugin/1.0'
            ],
            'sslverify' => $this->config['validate_ssl']
        ];
        
        // Download image
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Download failed: ' . $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP ' . $response_code . ' error'
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return [
                'success' => false,
                'error' => 'Empty response body'
            ];
        }
        
        // Check file size
        $size_mb = strlen($body) / 1024 / 1024;
        if ($size_mb > $this->config['max_file_size_mb']) {
            return [
                'success' => false,
                'error' => 'File too large: ' . round($size_mb, 2) . 'MB'
            ];
        }
        
        // Save to temp file
        if (file_put_contents($temp_file, $body) === false) {
            return [
                'success' => false,
                'error' => 'Failed to save temporary file'
            ];
        }
        
        // Get file info
        $file_info = $this->get_image_info($temp_file, $url);
        
        if (!$file_info['is_valid']) {
            unlink($temp_file);
            return [
                'success' => false,
                'error' => 'Invalid image file: ' . $file_info['error']
            ];
        }
        
        $this->stats['total_size_mb'] += $size_mb;
        
        return [
            'success' => true,
            'file_path' => $temp_file,
            'file_info' => $file_info,
            'size_mb' => $size_mb
        ];
    }
    
    /**
     * Get image information and validate
     *
     * @param string $file_path File path
     * @param string $original_url Original URL
     * @return array Image info
     */
    private function get_image_info($file_path, $original_url) {
        $image_info = @getimagesize($file_path);
        
        if ($image_info === false) {
            return [
                'is_valid' => false,
                'error' => 'Not a valid image file'
            ];
        }
        
        $mime_type = $image_info['mime'];
        
        if (!isset($this->allowed_types[$mime_type])) {
            return [
                'is_valid' => false,
                'error' => 'Unsupported image type: ' . $mime_type
            ];
        }
        
        // Generate filename
        $extension = $this->allowed_types[$mime_type];
        $basename = sanitize_file_name(basename(parse_url($original_url, PHP_URL_PATH)));
        
        // Remove existing extension and add correct one
        $basename = preg_replace('/\.[^.]+$/', '', $basename);
        if (empty($basename)) {
            $basename = 'property_image_' . time();
        }
        
        $filename = $basename . '.' . $extension;
        
        return [
            'is_valid' => true,
            'mime_type' => $mime_type,
            'extension' => $extension,
            'width' => $image_info[0],
            'height' => $image_info[1],
            'filename' => $filename,
            'original_url' => $original_url
        ];
    }
    
    /**
     * Create WordPress attachment from downloaded file
     *
     * @param string $temp_file Temporary file path
     * @param array $file_info File information
     * @param int $post_id Parent post ID
     * @param array $image_data Original image data
     * @return array Creation result
     */
    private function create_wordpress_attachment($temp_file, $file_info, $post_id, $image_data) {
        // Optimize image if enabled
        if ($this->config['enable_optimization']) {
            $this->optimize_image($temp_file, $file_info);
        }
        
        // Setup upload directory
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['path'] . '/';
        $target_url = $upload_dir['url'] . '/';
        
        // Ensure unique filename
        $filename = wp_unique_filename($upload_dir['path'], $file_info['filename']);
        $target_file = $target_dir . $filename;
        
        // Move file to uploads directory
        if (!copy($temp_file, $target_file)) {
            return [
                'success' => false,
                'error' => 'Failed to move file to uploads directory'
            ];
        }
        
        // Prepare attachment data
        $attachment_data = [
            'guid' => $target_url . $filename,
            'post_mime_type' => $file_info['mime_type'],
            'post_title' => $this->generate_image_title($image_data, $post_id),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $post_id
        ];
        
        // Insert attachment
        $attachment_id = wp_insert_attachment($attachment_data, $target_file, $post_id);
        
        if (is_wp_error($attachment_id)) {
            unlink($target_file);
            return [
                'success' => false,
                'error' => 'Failed to create attachment: ' . $attachment_id->get_error_message()
            ];
        }
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $target_file);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        
        // Add custom meta
        update_post_meta($attachment_id, '_realestate_sync_original_url', $file_info['original_url']);
        update_post_meta($attachment_id, '_realestate_sync_image_type', $image_data['type'] ?? 'image');
        update_post_meta($attachment_id, '_realestate_sync_import_date', current_time('mysql'));
        
        return [
            'success' => true,
            'attachment_id' => $attachment_id,
            'file_path' => $target_file,
            'url' => $target_url . $filename
        ];
    }
    
    /**
     * Optimize image if needed
     *
     * @param string $file_path File path
     * @param array $file_info File info
     */
    private function optimize_image($file_path, $file_info) {
        $max_width = $this->config['max_width'];
        $max_height = $this->config['max_height'];
        
        if ($file_info['width'] <= $max_width && $file_info['height'] <= $max_height) {
            return; // No optimization needed
        }
        
        // Calculate new dimensions
        $ratio = min($max_width / $file_info['width'], $max_height / $file_info['height']);
        $new_width = (int)($file_info['width'] * $ratio);
        $new_height = (int)($file_info['height'] * $ratio);
        
        // Load and resize image
        $image_editor = wp_get_image_editor($file_path);
        
        if (!is_wp_error($image_editor)) {
            $image_editor->resize($new_width, $new_height, false);
            
            if ($file_info['mime_type'] === 'image/jpeg') {
                $image_editor->set_quality($this->config['jpeg_quality']);
            }
            
            $image_editor->save($file_path);
            
            $this->logger->log('Image optimized', 'debug', [
                'original_size' => $file_info['width'] . 'x' . $file_info['height'],
                'new_size' => $new_width . 'x' . $new_height,
                'file' => basename($file_path)
            ]);
        }
    }
    
    /**
     * Generate image title based on property and image data
     *
     * @param array $image_data Image data
     * @param int $post_id Post ID
     * @return string Image title
     */
    private function generate_image_title($image_data, $post_id) {
        $post_title = get_the_title($post_id);
        $type = $image_data['type'] ?? 'image';
        
        $titles = [
            'image' => $post_title . ' - Foto',
            'planimetria' => $post_title . ' - Planimetria',
            'floor_plan' => $post_title . ' - Planimetria',
            'exterior' => $post_title . ' - Esterno',
            'interior' => $post_title . ' - Interno'
        ];
        
        return $titles[$type] ?? $post_title . ' - Immagine';
    }
    
    /**
     * Set featured image for property
     *
     * @param int $post_id Post ID
     * @param int $attachment_id Attachment ID
     */
    private function set_featured_image($post_id, $attachment_id) {
        set_post_thumbnail($post_id, $attachment_id);
        
        $this->logger->log('Featured image set', 'debug', [
            'post_id' => $post_id,
            'attachment_id' => $attachment_id
        ]);
    }
    
    /**
     * Update WpResidence gallery format
     *
     * @param int $post_id Post ID
     * @param array $attachment_ids Attachment IDs
     */
    private function update_wpresidence_gallery($post_id, $attachment_ids) {
        // 🔧 FIX: WpResidence uses serialized array format for property_gallery
        $gallery_serialized = serialize(array_map('strval', $attachment_ids));
        update_post_meta($post_id, 'property_gallery', $gallery_serialized);
        
        // BACKUP: Also store comma-separated for compatibility
        $gallery_string = implode(',', $attachment_ids);
        update_post_meta($post_id, 'property_gallery_backup', $gallery_string);
        
        // Store individual images for flexibility
        foreach ($attachment_ids as $index => $attachment_id) {
            update_post_meta($post_id, 'property_image_' . $index, $attachment_id);
        }
        
        $this->logger->log('WpResidence gallery updated - FIXED FORMAT', 'info', [
            'post_id' => $post_id,
            'gallery_serialized' => $gallery_serialized,
            'gallery_string_backup' => $gallery_string,
            'image_count' => count($attachment_ids)
        ]);
    }
    
    /**
     * Find existing attachment by URL
     *
     * @param string $url Original URL
     * @param int $post_id Post ID
     * @return int|null Attachment ID or null
     */
    private function find_existing_attachment($url, $post_id) {
        global $wpdb;
        
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_realestate_sync_original_url' 
             AND meta_value = %s 
             AND post_id IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' 
                AND (post_parent = %d OR post_parent = 0)
             )
             LIMIT 1",
            $url,
            $post_id
        ));
        
        return $attachment_id ? (int)$attachment_id : null;
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
     * Get configuration
     *
     * @return array Configuration
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Update configuration
     *
     * @param array $config New configuration
     */
    public function update_config($config) {
        $this->config = array_merge($this->config, $config);
        update_option('realestate_sync_image_importer_config', $this->config);
    }
    
    /**
     * Cleanup old temporary files
     */
    public function cleanup_temp_files() {
        $temp_dir = wp_upload_dir()['basedir'] . '/temp-images/';
        
        if (!file_exists($temp_dir)) {
            return;
        }
        
        $files = glob($temp_dir . '*');
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 3600) { // 1 hour old
                unlink($file);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            $this->logger->log('Temporary files cleaned', 'debug', [
                'cleaned_files' => $cleaned
            ]);
        }
    }
    
    /**
     * Get version and capabilities
     */
    public function get_version_info() {
        return [
            'version' => '1.0.0',
            'capabilities' => [
                'download_images' => true,
                'wordpress_integration' => true,
                'wpresidence_compatibility' => true,
                'image_optimization' => true,
                'duplicate_detection' => true,
                'featured_image_support' => true,
                'gallery_management' => true,
                'planimetrie_support' => true
            ],
            'supported_formats' => array_keys($this->allowed_types),
            'max_file_size_mb' => $this->config['max_file_size_mb']
        ];
    }
}
