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
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
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
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-logger.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-xml-parser.php';
        require_once REALESTATE_SYNC_PLUGIN_DIR . 'includes/class-realestate-sync-property-mapper.php';
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
     */
    public function init_plugin() {
        // Initialize core instances
        $this->instances['logger'] = new RealEstate_Sync_Logger();
        $this->instances['xml_parser'] = new RealEstate_Sync_XML_Parser();
        $this->instances['property_mapper'] = new RealEstate_Sync_Property_Mapper();
        $this->instances['wp_importer'] = new RealEstate_Sync_WP_Importer();
        $this->instances['import_engine'] = new RealEstate_Sync_Import_Engine();
        $this->instances['cron_manager'] = new RealEstate_Sync_Cron_Manager();
        $this->instances['tracking_manager'] = new RealEstate_Sync_Tracking_Manager();
        
        // Initialize admin interface
        if (is_admin()) {
            $this->instances['admin'] = new RealEstate_Sync_Admin();
        }
        
        // Log plugin initialization
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
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_database_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron events
        $this->schedule_cron_events();
        
        // Log activation
        if (class_exists('RealEstate_Sync_Logger')) {
            $logger = new RealEstate_Sync_Logger();
            $logger->log('Plugin activated successfully', 'info');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
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
            last_import datetime DEFAULT NULL,
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
        dbDelta($sql);
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
        
        // Run import
        $result = $this->instances['import_engine']->run_import(true);
        
        wp_send_json($result);
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
        $this->instances['import_engine']->run_import(false);
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
 * Helper function to get plugin instance
 * 
 * @return RealEstate_Sync
 */
function realestate_sync() {
    return RealEstate_Sync::get_instance();
}
