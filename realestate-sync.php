<?php
/**
 * Plugin Name: RealEstate Sync
 * Plugin URI: https://www.novacomitalia.com/plugins/realestate-sync
 * Description: Professional WordPress plugin for automated XML import of real estate properties from GestionaleImmobiliare.it. Features chunked processing, automated scheduling, and comprehensive admin interface.
 * Version: 1.5.6
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
 * GitHub Plugin URI: andreacianni/realestate-sync-plugin
 * Primary Branch: main
 * Release Asset: true
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
define('REALESTATE_SYNC_VERSION', '1.5.6');
define('REALESTATE_SYNC_PLUGIN_FILE', __FILE__);
define('REALESTATE_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('REALESTATE_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('REALESTATE_SYNC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// "freno al legacy importer"
if (!defined('REALESTATE_SYNC_ENABLE_LEGACY_IMPORTER')) {
    define('REALESTATE_SYNC_ENABLE_LEGACY_IMPORTER', false);
};

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
            add_action('admin_post_realestate_sync_send_test_email', [$this, 'handle_send_test_email']);
        }
        
        // AJAX hooks
        // NOTE: Manual import and clear logs handlers are in Admin class
        add_action('wp_ajax_realestate_sync_get_import_status', [$this, 'get_import_status']);
        add_action('wp_ajax_realestate_sync_test_sample_xml', [$this, 'handle_test_sample_xml']); // 🆕 NEW: Test with sample XML
        
        // Cron hooks
        add_action('realestate_sync_daily_import', [$this, 'run_scheduled_import']);
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-logger.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-debug-tracker.php'; // 🔍 NEW: Debug Tracker v1.0 (Onion Logging)
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-xml-downloader.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-xml-parser.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-property-mapper.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-image-importer.php'; // 🖼️ NEW: Image Importer v1.0
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-agency-manager.php'; // 🏢 NEW: Agency Manager v2.0
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-agency-parser.php'; // 🏢 Agency Parser
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-agency-importer.php'; // 🏢 Agency Importer
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-media-deduplicator.php'; // 🖼️ Media Deduplicator
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-property-agent-linker.php'; // 🔗 Property-Agent Linker
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-wpresidence-api-writer.php'; // 🚀 NEW: WPResidence Property API Writer v1.0 (v1.4.0)
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-wpresidence-agency-api-writer.php'; // 🏢 NEW: WPResidence Agency API Writer v1.0 (v1.4.0)
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-wp-importer.php'; // Legacy importer (meta fields)
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-wp-importer-api.php'; // 🌟 NEW: API-based importer (v1.4.0)
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-import-engine.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-cron-manager.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-tracking-manager.php';

        // Batch System classes
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-queue-manager.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-delete-queue-manager.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-batch-processor.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-batch-orchestrator.php';

        // Deletion System classes (v1.7.1+)
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-deletion-manager.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-attachment-cleanup.php'; // 🗑️ Auto-cleanup attachments on delete

        // require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-github-updater.php'; // DISABLED: Using external Git Updater plugin instead

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

        // ✅ USE API IMPORTER (GOLDEN methods) instead of legacy
        $this->instances['wp_importer'] = new RealEstate_Sync_WP_Importer_API($this->instances['logger']);
        
        // 🛡️ DEPENDENCY INJECTION: Pass shared instances to Import Engine
        $this->instances['import_engine'] = new RealEstate_Sync_Import_Engine(
            $this->instances['property_mapper'],
            $this->instances['wp_importer'],
            $this->instances['logger']
        );
        
        $this->instances['cron_manager'] = new RealEstate_Sync_Cron_Manager();
        $this->instances['tracking_manager'] = new RealEstate_Sync_Tracking_Manager();

        // Initialize Attachment Cleanup hooks (v1.7.2+)
        RealEstate_Sync_Attachment_Cleanup::init();

        // Initialize GitHub updater - DISABLED: Using external Git Updater plugin
        // if (is_admin()) {
        //     $this->instances['github_updater'] = new RealEstate_Sync_GitHub_Updater(REALESTATE_SYNC_PLUGIN_FILE);
        // }
        
        // Initialize admin interface
        if (is_admin()) {
            $this->instances['admin'] = new RealEstate_Sync_Admin();
        }
        
        // Log plugin initialization (ONE TIME ONLY) - now in debug mode to reduce noise
        $this->instances['logger']->log('Plugin initialized successfully', 'debug');
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

        // ✅ IMPORT SESSIONS: Create import sessions table for history tracking
        $sessions_table = $wpdb->prefix . 'realestate_import_sessions';

        $sql_sessions = "CREATE TABLE $sessions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(50) NOT NULL,
            started_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            type varchar(20) DEFAULT 'manual',
            total_items int(11) DEFAULT 0,
            processed_items int(11) DEFAULT 0,
            new_properties int(11) DEFAULT 0,
            updated_properties int(11) DEFAULT 0,
            failed_properties int(11) DEFAULT 0,
            error_log text DEFAULT NULL,
            marked_as_test tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY status (status),
            KEY type (type),
            KEY started_at (started_at)
        ) $charset_collate;";

        $result_sessions = dbDelta($sql_sessions);

        // ✅ BATCH SYSTEM: Create import queue table
        require_once plugin_dir_path(__FILE__) . 'includes/class-realestate-sync-queue-manager.php';
        $queue_manager = new RealEstate_Sync_Queue_Manager();
        $queue_manager->create_table();

        $delete_queue_manager = new RealEstate_Sync_Delete_Queue_Manager();
        $delete_queue_manager->create_table();

        // ✅ AGENCY TRACKING: Create agency tracking table
        require_once plugin_dir_path(__FILE__) . 'includes/class-realestate-sync-tracking-manager.php';
        $tracking_manager = new RealEstate_Sync_Tracking_Manager();
        $tracking_manager->create_agency_tracking_table();

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

            // Log sessions table creation
            $logger->log("Import sessions table creation attempted: $sessions_table", 'info');
            $sessions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$sessions_table'") === $sessions_table;
            $logger->log("Sessions table exists after creation: " . ($sessions_table_exists ? 'YES' : 'NO'), 'info');
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
        // Custom SVG icon (commented for future use)
        // $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M14 7c.55 0 1 .45 1 1 0 .17-.04.33-.12.47C15.47 8.88 16 9.86 16 11c0 1.66-1.34 3-3 3H6c-1.66 0-3-1.34-3-3 0-1.53 1.14-2.79 2.62-2.97C5.87 6.84 7.29 6 9 6c1.4 0 2.6.71 3.32 1.78.23-.11.49-.17.76-.17h.92z"/><path d="M10 9l-3 3h2v3h2v-3h2l-3-3z"/><circle cx="15.5" cy="15.5" r="3" fill="currentColor"/><circle cx="15.5" cy="15.5" r="2.5" fill="none" stroke="white" stroke-width="0.5"/><path d="M15.5 13.5v2h1.5" stroke="white" stroke-width="0.5" fill="none"/></svg>';
        // $icon = 'data:image/svg+xml;base64,' . base64_encode($icon_svg);

        add_menu_page(
            __('Import dal Gestionale', 'realestate-sync'),
            __('Import dal Gestionale', 'realestate-sync'),
            'manage_options',
            'realestate-sync',
            [$this->instances['admin'], 'display_admin_page'],
            'dashicons-download',
            25
        );

        // Debug DB page (only available when WP_DEBUG is enabled)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_management_page(
                __('RealEstate Debug DB', 'realestate-sync'),
                __('Debug DB', 'realestate-sync'),
                'manage_options',
                'realestate-sync-debug-db',
                [$this->instances['admin'], 'display_debug_metafields_page']
            );
        }
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
     * 🆕 Test with sample XML AJAX
     */
    public function handle_test_sample_xml() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'realestate_sync_nonce') || 
            !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            // Use sample XML for testing
            $sample_xml_path = 'C:\\Users\\Andrea\\OneDrive\\Lavori\\novacom\\Trentino-immobiliare\\lavoro\\sample-con-agenzie.xml';
            
            if (!file_exists($sample_xml_path)) {
                throw new Exception('Sample XML file not found: ' . $sample_xml_path);
            }
            
            $this->instances['logger']->log('🆕 TESTING: Using sample XML with agencies: ' . basename($sample_xml_path), 'info');
            
            // Configure for testing
            $settings = array(
                'chunk_size' => 10, // Smaller chunks for testing
                'sleep_seconds' => 0 // No sleep for testing
            );
            
            // Configure and run import with sample XML
            $this->instances['import_engine']->configure($settings);
            $result = $this->instances['import_engine']->execute_chunked_import($sample_xml_path);
            
            $this->instances['logger']->log('🆕 TESTING: Sample XML import completed', 'success');
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            $this->instances['logger']->log('🆕 TESTING: Sample XML import failed: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Send test email via admin-post action
     */
    public function handle_send_test_email() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('realestate_sync_send_test_email');

        $redirect = $_POST['redirect_to'] ?? wp_get_referer();
        if (empty($redirect)) {
            $redirect = admin_url('admin.php?page=realestate-sync');
        }

        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-email-report.php';

        $sent = RealEstate_Sync_Email_Report::send_test_email();
        $status = $sent ? 'success' : 'error';

        $redirect = add_query_arg('rs_email_test', $status, $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Run scheduled import
     */
    public function run_scheduled_import() {
        try {
            // Get credential source
            $credential_source = get_option('realestate_sync_credential_source', 'hardcoded');

            if ($credential_source === 'database') {
                // Use credentials from database
                $settings = array(
                    'xml_url' => get_option('realestate_sync_xml_url', ''),
                    'username' => get_option('realestate_sync_xml_user', ''),
                    'password' => get_option('realestate_sync_xml_pass', ''),
                    'chunk_size' => 25,
                    'sleep_seconds' => 1
                );

                if (empty($settings['xml_url']) || empty($settings['username']) || empty($settings['password'])) {
                    throw new Exception('XML credentials not configured in database');
                }

                $this->instances['logger']->log('Scheduled import: Using XML credentials from database', 'info');

            } else {
                // Use hardcoded credentials
                $settings = array(
                    'xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
                    'username' => 'trentinoimmobiliare_it',
                    'password' => 'dget6g52',
                    'chunk_size' => 25,
                    'sleep_seconds' => 1
                );

                $this->instances['logger']->log('Scheduled import: Using hardcoded XML credentials', 'info');
            }
            
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
