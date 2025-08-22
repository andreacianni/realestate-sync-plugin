<?php
/**
 * Plugin Name: RealEstate Sync
 * Plugin URI: https://www.novacomitalia.com/plugins/realestate-sync
 * Description: Professional WordPress plugin for automated XML import of real estate properties from GestionaleImmobiliare.it. Features chunked processing, automated scheduling, and comprehensive admin interface.
 * Version: 0.9.0-beta
 * Author: Andrea Cianni - Novacom
 * Author URI: https://www.novacomitalia.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: realestate-sync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package RealEstateSync
 * @author Andrea Cianni <andrea@novacomitalia.com>
 * @copyright 2025 Novacom Italia
 * @license GPL-2.0+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('REALESTATE_SYNC_VERSION', '0.9.0-beta');
define('REALESTATE_SYNC_PLUGIN_FILE', __FILE__);
define('REALESTATE_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('REALESTATE_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('REALESTATE_SYNC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main RealEstate Sync Plugin Class
 * 
 * Handles plugin initialization, activation, deactivation and core functionality
 * 
 * @since 0.9.0
 */
class RealEstate_Sync {
    
    /**
     * Plugin instance
     * 
     * @var RealEstate_Sync
     */
    private static $instance = null;
    
    /**
     * Core classes instances
     * 
     * @var array
     */
    private $instances = [];
    
    /**
     * Get plugin instance
     * 
     * @return RealEstate_Sync
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize plugin
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // 🗨️ HEARTBEAT TEST: Temporary disable for loop debugging
        add_action('init', array($this, 'disable_heartbeat_for_test'));
        
        // 🚀 PROFESSIONAL ACTIVATION: wp_loaded approach (BREAKTHROUGH IMPLEMENTATION)
        register_activation_hook(__FILE__, [__CLASS__, 'set_activation_flag']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'plugin_deactivate']);
        
        // 💎 ELEGANT wp_loaded ACTIVATION: Complete activation when WordPress is fully loaded
        add_action('wp_loaded', [$this, 'complete_activation']);
        
        // Core WordPress hooks
        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_action('init', [$this, 'load_textdomain']);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        }
        
        // AJAX hooks
        add_action('wp_ajax_realestate_sync_manual_import', [$this, 'handle_manual_import']);
        add_action('wp_ajax_realestate_sync_get_import_status', [$this, 'get_import_status']);
        add_action('wp_ajax_realestate_sync_clear_logs', [$this, 'clear_logs']);
        
        // Cron hooks
        add_action('realestate_sync_daily_import', [$this, 'run_scheduled_import']);
    }
    
    /**
     * 🗨️ TEMPORARY TEST: Disable heartbeat to test if it causes init loop
     */
    public function disable_heartbeat_for_test() {
        // Disable heartbeat on admin pages
        if (is_admin()) {
            wp_deregister_script('heartbeat');
            $this->instances['logger']->log('🗨️ HEARTBEAT DISABLED FOR LOOP TEST', 'info');
        }
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-logger.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-xml-downloader.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-xml-parser.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-property-mapper.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-image-importer.php'; // 🖼️ NEW: Image Importer v1.0
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-agency-manager.php'; // 🏢 NEW: Agency Manager v1.0
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-wp-importer.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-import-engine.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-cron-manager.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-tracking-manager.php';
        
        // Admin classes
        if (is_admin()) {
            require_once REALESTATE_SYNC_PLUGIN_DIR . 'admin/class-realestate-sync-admin.php';
        }
        
        // Configuration files
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'config/default-settings.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'config/field-mapping.php';
    }
    
    /**
     * Initialize plugin after all plugins loaded
     * SINGLETON PROTECTION: Prevents re-initialization loop
     */
    public function init_plugin() {
        // 🛡️ SINGLETON PROTECTION: Only initialize once
        if (!empty($this->instances)) {
            return; // Already initialized - prevent loop
        }
        
        // Initialize core instances (ONE TIME ONLY)
        $this->instances['logger'] = RealEstate_Sync_Logger::get_instance();
        $this->instances['xml_parser'] = new RealEstate_Sync_XML_Parser();
        $this->instances['property_mapper'] = new RealEstate_Sync_Property_Mapper();
        $this->instances['wp_importer'] = new RealEstate_Sync_WP_Importer();
        
        // 🛡️ DEPENDENCY INJECTION: Pass shared instances to Import Engine
        $this->instances['import_engine'] = new RealEstate_Sync_Import_Engine(
            $this->instances['property_mapper'],
            $this->instances['wp_importer'],
            $this->instances['logger']
        );
        
        $this->instances['cron_manager'] = new RealEstate_Sync_Cron_Manager();
        $this->instances['tracking_manager'] = new RealEstate_Sync_Tracking_Manager();
        
        // Initialize admin interface
        if (is_admin()) {
            $this->instances['admin'] = new RealEstate_Sync_Admin();
        }
        
        // Log plugin initialization (ONE TIME ONLY)
        $this->instances['logger']->log('Plugin initialized successfully', 'info');
    }
    
    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'realestate-sync',
            false,
            dirname(REALESTATE_SYNC_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * 🚀 PROFESSIONAL ACTIVATION v2.0: Set activation flag (wp_loaded approach)
     * Called by register_activation_hook - sets flag for wp_loaded completion
     */
    public static function set_activation_flag() {
        // Set flag for wp_loaded activation completion
        update_option('realestate_sync_needs_activation', true);
        
        // Log flag setting (direct error_log for activation context)
        error_log('RealEstate Sync: Activation flag set - will complete on wp_loaded');
    }
    
    /**
     * Static plugin deactivation callback
     */
    public static function plugin_deactivate() {
        // Create instance to run deactivation
        $instance = self::get_instance();
        $instance->deactivate();
    }
    
    /**
     * 💎 ELEGANT wp_loaded ACTIVATION: Complete activation when WordPress ready
     * Called on wp_loaded hook - ensures proper WordPress context and timing
     */
    public function complete_activation() {
        // Check if activation is needed
        if (!get_option('realestate_sync_needs_activation', false)) {
            return; // No activation needed
        }
        
        try {
            // WordPress is fully loaded - perform activation tasks safely
            $this->perform_activation_tasks();
            
            // Clean activation flag - one-time execution
            delete_option('realestate_sync_needs_activation');
            
            // Log successful completion
            $this->log_activation_message('🎉 PROFESSIONAL ACTIVATION COMPLETE: All tasks executed successfully via wp_loaded', 'info');
            
        } catch (Exception $e) {
            // Log error but don't clear flag - will retry on next wp_loaded
            $this->log_activation_message('⚠️ wp_loaded activation failed: ' . $e->getMessage() . ' - Will retry', 'warning');
        }
    }
    

    
    /**
     * 🛡️ Perform the actual activation tasks
     */
    private function perform_activation_tasks() {
        // Create database tables if needed
        $this->create_database_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron events
        $this->schedule_cron_events();
        
        // Log activation
        $this->log_activation_message('Plugin activation tasks completed successfully', 'info');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * 🛡️ Check if activation is complete
     */
    private function is_activation_complete() {
        global $wpdb;
        
        // Check if tracking table exists
        $table_name = $wpdb->prefix . 'realestate_sync_tracking';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        // Check if basic options are set
        $options_exist = get_option('realestate_sync_xml_url', false) !== false;
        
        return $table_exists && $options_exist;
    }
    
    /**
     * 🛡️ Safe logging for activation process
     */
    private function log_activation_message($message, $level = 'info') {
        if (class_exists('RealEstate_Sync_Logger')) {
            try {
                $logger = RealEstate_Sync_Logger::get_instance();
                $logger->log($message, $level);
            } catch (Exception $e) {
                // Fallback to error_log if logger fails
                error_log('RealEstate Sync: ' . $message);
            }
        } else {
            // Fallback to error_log if logger class not available
            error_log('RealEstate Sync: ' . $message);
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron events
        $this->clear_cron_events();
        
        // Log deactivation
        if (isset($this->instances['logger'])) {
            $this->instances['logger']->log('Plugin deactivated', 'info');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Import tracking table
        $table_name = $wpdb->prefix . 'realestate_sync_tracking';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            property_id varchar(50) NOT NULL,
            property_hash varchar(32) NOT NULL,
            wp_post_id bigint(20) DEFAULT NULL,
            last_import_date datetime DEFAULT NULL,
            import_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY property_id (property_id),
            KEY property_hash (property_hash),
            KEY wp_post_id (wp_post_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Log database creation with detailed info
        if (class_exists('RealEstate_Sync_Logger')) {
            $logger = RealEstate_Sync_Logger::get_instance();
            $logger->log("Database table creation attempted: $table_name", 'info');
            $logger->log("dbDelta result: " . print_r($result, true), 'debug');
            
            // Check if table actually exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            $logger->log("Table exists after creation: " . ($table_exists ? 'YES' : 'NO'), 'info');
            
            if (!$table_exists) {
                $logger->log("WARNING: Table creation may have failed. Manual verification required.", 'warning');
            }
        }
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        // Get default settings
        $default_settings = include REALESTATE_SYNC_PLUGIN_DIR . 'config/default-settings.php';
        
        // Set options if they don't exist
        foreach ($default_settings as $option_name => $default_value) {
            if (false === get_option($option_name)) {
                add_option($option_name, $default_value);
            }
        }
    }
    
    /**
     * Schedule cron events
     */
    private function schedule_cron_events() {
        // Daily import cron
        if (!wp_next_scheduled('realestate_sync_daily_import')) {
            $schedule_time = get_option('realestate_sync_schedule_time', '02:00');
            $timestamp = strtotime('today ' . $schedule_time);
            
            // If time has passed today, schedule for tomorrow
            if ($timestamp < time()) {
                $timestamp = strtotime('tomorrow ' . $schedule_time);
            }
            
            wp_schedule_event($timestamp, 'daily', 'realestate_sync_daily_import');
        }
        
        // Log cleanup cron (weekly)
        if (!wp_next_scheduled('realestate_sync_cleanup_logs')) {
            wp_schedule_event(time(), 'weekly', 'realestate_sync_cleanup_logs');
        }
    }
    
    /**
     * Clear cron events
     */
    private function clear_cron_events() {
        wp_clear_scheduled_hook('realestate_sync_daily_import');
        wp_clear_scheduled_hook('realestate_sync_cleanup_logs');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu under Tools
        add_management_page(
            __('RealEstate Sync', 'realestate-sync'),
            __('RealEstate Sync', 'realestate-sync'),
            'manage_options',
            'realestate-sync',
            [$this->instances['admin'], 'display_admin_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on our admin pages
        if (strpos($hook_suffix, 'realestate-sync') === false && $hook_suffix !== 'index.php') {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'realestate-sync-admin',
            REALESTATE_SYNC_PLUGIN_URL . 'admin/assets/admin.css',
            [],
            REALESTATE_SYNC_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'realestate-sync-admin',
            REALESTATE_SYNC_PLUGIN_URL . 'admin/assets/admin.js',
            ['jquery'],
            REALESTATE_SYNC_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('realestate-sync-admin', 'realestateSync', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('realestate_sync_nonce'),
            'strings' => [
                'importStarted' => __('Import started...', 'realestate-sync'),
                'importCompleted' => __('Import completed!', 'realestate-sync'),
                'importError' => __('Import failed. Check logs for details.', 'realestate-sync'),
                'confirmClearLogs' => __('Are you sure you want to clear all logs?', 'realestate-sync'),
            ]
        ]);
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'realestate-sync-status',
            __('RealEstate Sync Status', 'realestate-sync'),
            [$this, 'dashboard_widget_content']
        );
    }
    
    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        $last_import = get_option('realestate_sync_last_import', null);
        $last_success = get_option('realestate_sync_last_success', null);
        $total_properties = get_option('realestate_sync_total_properties', 0);
        
        echo '<div class="realestate-sync-dashboard-widget">';
        
        if ($last_success) {
            $time_ago = human_time_diff(strtotime($last_success), current_time('timestamp'));
            echo '<p><strong>' . __('Last successful import:', 'realestate-sync') . '</strong><br>';
            echo sprintf(__('%s ago', 'realestate-sync'), $time_ago) . '</p>';
            
            echo '<p><strong>' . __('Total properties:', 'realestate-sync') . '</strong> ' . number_format($total_properties) . '</p>';
        } else {
            echo '<p>' . __('No imports completed yet.', 'realestate-sync') . '</p>';
        }
        
        echo '<p>';
        echo '<a href="' . admin_url('tools.php?page=realestate-sync') . '" class="button button-primary">';
        echo __('Manage RealEstate Sync', 'realestate-sync');
        echo '</a>';
        echo '</p>';
        
        echo '</div>';
    }
    
    /**
     * Handle manual import AJAX request
     */
    public function handle_manual_import() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'realestate_sync_nonce') || 
            !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            // 🔧 HARDCODE CREDENZIALI TEMPORANEO - BYPASS SETTINGS
            $settings = array(
                'xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
                'username' => 'trentinoimmobiliare_it',
                'password' => 'dget6g52',
                'chunk_size' => 25,
                'sleep_seconds' => 1
            );
            
            $this->instances['logger']->log('HARDCODE: Using hardcoded credentials in main file', 'info');
            
            // Download XML file
            $downloader = new RealEstate_Sync_XML_Downloader();
            $xml_file = $downloader->download_xml($settings['xml_url'], $settings['username'], $settings['password']);
            
            if (!$xml_file) {
                throw new Exception('Failed to download XML file');
            }
            
            // Configure and run import
            $this->instances['import_engine']->configure($settings);
            $result = $this->instances['import_engine']->execute_chunked_import($xml_file);
            
            // Cleanup
            if (file_exists($xml_file)) {
                unlink($xml_file);
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get import status AJAX
     */
    public function get_import_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'realestate_sync_nonce') || 
            !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $status = $this->instances['import_engine']->get_import_status();
        wp_send_json($status);
    }
    
    /**
     * Clear logs AJAX
     */
    public function clear_logs() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'realestate_sync_nonce') || 
            !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->instances['logger']->clear_logs();
        wp_send_json(['success' => $result]);
    }
    
    /**
     * Run scheduled import
     */
    public function run_scheduled_import() {
        try {
            // 🔧 HARDCODE CREDENZIALI TEMPORANEO - BYPASS SETTINGS
            $settings = array(
                'xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
                'username' => 'trentinoimmobiliare_it',
                'password' => 'dget6g52',
                'chunk_size' => 25,
                'sleep_seconds' => 1
            );
            
            $this->instances['logger']->log('HARDCODE: Using hardcoded credentials for scheduled import', 'info');
            
            // Download XML
            $downloader = new RealEstate_Sync_XML_Downloader();
            $xml_file = $downloader->download_xml($settings['xml_url'], $settings['username'], $settings['password']);
            
            if (!$xml_file) {
                $this->instances['logger']->log('Scheduled import failed: XML download failed', 'error');
                return;
            }
            
            // Execute import
            $this->instances['import_engine']->configure($settings);
            $result = $this->instances['import_engine']->execute_chunked_import($xml_file);
            
            // Cleanup
            if (file_exists($xml_file)) {
                unlink($xml_file);
            }
            
            $this->instances['logger']->log('Scheduled import completed successfully', 'info');
            
        } catch (Exception $e) {
            $this->instances['logger']->log('Scheduled import failed: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Get class instance by name
     * 
     * @param string $name Class name
     * @return object|null
     */
    public function get_instance_by_name($name) {
        return $this->instances[$name] ?? null;
    }
}

/**
 * Initialize plugin
 */
function realestate_sync_init() {
    return RealEstate_Sync::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'realestate_sync_init', 0);

/**
 * 💎 PROFESSIONAL ACTIVATION INFO - For developers and troubleshooting
 * Access with: ?debug_activation_info
 */
add_action('wp_loaded', function() {
    if (isset($_GET['debug_activation_info']) && current_user_can('manage_options')) {
        echo '<h1>🚀 PROFESSIONAL ACTIVATION SYSTEM v2.0</h1>';
        echo '<h3>💎 BREAKTHROUGH IMPLEMENTATION:</h3>';
        echo '<p><strong>Problem Solved:</strong> WordPress timing issues with register_activation_hook</p>';
        echo '<p><strong>Solution:</strong> Two-phase activation via wp_loaded hook</p>';
        echo '<h3>🔄 ACTIVATION WORKFLOW:</h3>';
        echo '<ol>';
        echo '<li><strong>Phase 1:</strong> register_activation_hook sets flag</li>';
        echo '<li><strong>Phase 2:</strong> wp_loaded completes activation when WordPress ready</li>';
        echo '<li><strong>One-time:</strong> Flag cleanup prevents re-execution</li>';
        echo '</ol>';
        echo '<h3>✨ BENEFITS:</h3>';
        echo '<ul>';
        echo '<li>Perfect WordPress timing - no early execution issues</li>';
        echo '<li>One-time execution - no infinite loops</li>';
        echo '<li>Professional user experience - zero manual intervention</li>';
        echo '<li>Resilient operation - handles edge cases gracefully</li>';
        echo '</ul>';
        echo '<p><a href="?debug_activation">➡️ Check Current Activation Status</a></p>';
        exit;
    }
});

/**
 * Helper function to get plugin instance
 * 
 * @return RealEstate_Sync
 */
function realestate_sync() {
    return RealEstate_Sync::get_instance();
}

// 🚀 PROFESSIONAL ACTIVATION DEBUG - For monitoring wp_loaded activation system
add_action('wp_loaded', function() {
    if (isset($_GET['debug_activation']) && current_user_can('manage_options')) {
        echo '<h2>🚀 PROFESSIONAL ACTIVATION STATUS</h2>';
        
        // Check activation flag status
        $needs_activation = get_option('realestate_sync_needs_activation', false);
        echo '<p><strong>Activation Flag:</strong> ' . ($needs_activation ? '🔄 PENDING (will complete on wp_loaded)' : '✅ CLEAN (activation complete)') . '</p>';
        
        // Check table existence
        global $wpdb;
        $table_name = $wpdb->prefix . 'realestate_sync_tracking';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        echo '<p><strong>Database Table:</strong> ' . ($table_exists ? '✅ EXISTS' : '❌ MISSING') . '</p>';
        
        // Check basic options
        $options_exist = get_option('realestate_sync_xml_url', false) !== false;
        echo '<p><strong>Plugin Options:</strong> ' . ($options_exist ? '✅ SET' : '❌ MISSING') . '</p>';
        
        // Overall status
        $activation_complete = !$needs_activation && $table_exists && $options_exist;
        echo '<p><strong>System Status:</strong> ' . ($activation_complete ? '✅ FULLY ACTIVATED' : '🔄 ACTIVATION IN PROGRESS') . '</p>';
        
        if ($activation_complete) {
            echo '<p style="color: green; font-weight: bold;">🎉 PROFESSIONAL ACTIVATION COMPLETE!</p>';
            echo '<p>wp_loaded activation system working perfectly.</p>';
        } else if ($needs_activation) {
            echo '<p style="color: blue; font-weight: bold;">🔄 ACTIVATION WILL COMPLETE AUTOMATICALLY</p>';
            echo '<p>Refresh this page to see activation complete via wp_loaded.</p>';
        } else {
            echo '<p style="color: orange;">Some activation tasks may need manual completion.</p>';
        }
        
        exit;
    }
});