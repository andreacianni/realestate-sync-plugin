<?php
/**
 * RealEstate Sync Plugin - Main Admin Interface
 * 
 * Core admin interface controller - REFACTORED CLEAN VERSION
 *
 * @package RealEstateSync
 * @subpackage Admin
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Admin {
    
    private $logger;
    private $plugin_slug = 'realestate-sync';
    
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        $this->init_hooks();
        $this->load_admin_modules();
    }
    
    private function init_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_realestate_sync_manual_import', array($this, 'handle_manual_import'));
        add_action('wp_ajax_realestate_sync_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_realestate_sync_save_settings', array($this, 'handle_save_settings'));
        add_action('wp_ajax_realestate_sync_get_progress', array($this, 'handle_get_progress'));
        add_action('wp_ajax_realestate_sync_get_logs', array($this, 'handle_get_logs'));
        add_action('wp_ajax_realestate_sync_download_logs', array($this, 'handle_download_logs'));
        add_action('wp_ajax_realestate_sync_clear_logs', array($this, 'handle_clear_logs'));
        add_action('wp_ajax_realestate_sync_system_check', array($this, 'handle_system_check'));
        add_action('wp_ajax_realestate_sync_toggle_automation', array($this, 'handle_toggle_automation'));
        add_action('wp_ajax_realestate_sync_force_database_creation', array($this, 'handle_force_database_creation'));
        add_action('wp_ajax_realestate_sync_create_properties_from_sample', array($this, 'handle_create_properties_from_sample'));
    }
    
    private function load_admin_modules() {
        require_once plugin_dir_path(__FILE__) . 'class-realestate-sync-admin-comparison.php';
        require_once plugin_dir_path(__FILE__) . 'class-realestate-sync-admin-investigation.php';
        require_once plugin_dir_path(__FILE__) . 'class-realestate-sync-admin-testing.php';
    }
    
    public function add_admin_menu() {
        add_submenu_page('tools.php', 'RealEstate Sync', 'RealEstate Sync', 'manage_options', $this->plugin_slug, array($this, 'display_admin_page'));
    }
    
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_' . $this->plugin_slug) return;
        
        wp_enqueue_script('realestate-sync-admin', plugin_dir_url(__FILE__) . '../admin/assets/admin.js', array('jquery'), '1.2.0', true);
        wp_enqueue_style('realestate-sync-admin', plugin_dir_url(__FILE__) . '../admin/assets/admin.css', array(), '1.2.0');
        wp_localize_script('realestate-sync-admin', 'realestateSync', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('realestate_sync_nonce')
        ));
    }
    
    public function display_admin_page() {
        $settings = get_option('realestate_sync_settings', array());
        $last_import = RealEstate_Sync_Import_Engine::get_last_import_results();
        $tracking_stats = $this->get_tracking_statistics();
        $next_scheduled = wp_next_scheduled('realestate_sync_daily_import');
        include plugin_dir_path(__FILE__) . '../admin/views/dashboard.php';
    }
    
    public function handle_manual_import() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        try {
            $settings = array(
                'xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
                'username' => 'trentinoimmobiliare_it',
                'password' => 'dget6g52'
            );
            
            $downloader = new RealEstate_Sync_XML_Downloader();
            $xml_file = $downloader->download_xml($settings['xml_url'], $settings['username'], $settings['password']);
            
            if (!$xml_file) throw new Exception('Impossibile scaricare il file XML');
            
            $import_engine = new RealEstate_Sync_Import_Engine();
            $import_engine->configure($settings);
            $results = $import_engine->execute_chunked_import($xml_file);
            
            if (file_exists($xml_file)) unlink($xml_file);
            
            wp_send_json_success(array('message' => 'Import completato con successo', 'results' => $results));
        } catch (Exception $e) {
            $this->logger->log("Manual import failed: " . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function handle_test_connection() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $url = 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz';
        $username = 'trentinoimmobiliare_it';
        $password = 'dget6g52';
        
        $downloader = new RealEstate_Sync_XML_Downloader();
        $result = $downloader->test_connection($url, $username, $password);
        
        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }
    
    public function handle_save_settings() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $settings = array(
            'xml_url' => sanitize_url($_POST['xml_url'] ?? ''),
            'username' => sanitize_text_field($_POST['username'] ?? ''),
            'password' => sanitize_text_field($_POST['password'] ?? ''),
            'enabled_provinces' => isset($_POST['enabled_provinces']) ? array_map('sanitize_text_field', $_POST['enabled_provinces']) : array(),
            'chunk_size' => isset($_POST['chunk_size']) ? intval($_POST['chunk_size']) : 50
        );
        
        $result = update_option('realestate_sync_settings', $settings);
        $result !== false ? wp_send_json_success('Impostazioni salvate') : wp_send_json_error('Errore salvataggio');
    }
    
    public function handle_get_progress() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        $progress = RealEstate_Sync_Import_Engine::get_current_progress();
        $progress ? wp_send_json_success($progress) : wp_send_json_error('Nessun import in corso');
    }
    
    private function get_tracking_statistics() {
        $tracking_manager = new RealEstate_Sync_Tracking_Manager();
        return $tracking_manager->get_import_statistics();
    }
    
    public function handle_get_logs() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $logs = $this->logger->get_recent_logs(100);
        if ($logs && is_array($logs)) {
            $formatted_logs = array();
            foreach ($logs as $log) {
                $formatted_logs[] = is_array($log) ? 
                    '[' . $log['timestamp'] . '] [' . strtoupper($log['level']) . '] ' . $log['message'] : $log;
            }
            wp_send_json_success(array('logs' => implode("\n", $formatted_logs)));
        } else {
            wp_send_json_success(array('logs' => 'Nessun log disponibile'));
        }
    }
    
    public function handle_download_logs() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $logs = $this->logger->get_recent_logs(1000);
        $filename = 'realestate-sync-logs-' . date('Y-m-d-H-i-s') . '.txt';
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo implode("\n", $logs);
        exit;
    }
    
    public function handle_clear_logs() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $result = $this->logger->clear_logs();
        $result ? wp_send_json_success('Log cancellati') : wp_send_json_error('Errore cancellazione log');
    }
    
    public function handle_system_check() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $checks = array(
            'PHP Version' => PHP_VERSION,
            'WordPress Version' => get_bloginfo('version'),
            'Memory Limit' => ini_get('memory_limit'),
            'cURL Extension' => function_exists('curl_init') ? 'Available' : 'Missing'
        );
        
        $html = '<table class="rs-form-table">';
        foreach ($checks as $check => $value) {
            $html .= "<tr><th>$check</th><td>$value</td></tr>";
        }
        $html .= '</table>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function handle_toggle_automation() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        $cron_manager = new RealEstate_Sync_Cron_Manager();
        $message = $cron_manager->is_scheduled() ? 
            ($cron_manager->unschedule_cron_jobs() ? 'Automazione disabilitata' : 'Errore disabilitazione') :
            ($cron_manager->schedule_cron_jobs() ? 'Automazione abilitata' : 'Errore abilitazione');
            
        wp_send_json_success($message);
    }
    
    public function handle_force_database_creation() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'realestate_sync_tracking';
        $charset_collate = $wpdb->get_charset_collate();
        
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            property_id varchar(50) NOT NULL,
            property_hash varchar(32) NOT NULL,
            wp_post_id bigint(20) DEFAULT NULL,
            last_import_date datetime DEFAULT NULL,
            import_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY property_id (property_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            wp_send_json_success(array('message' => 'Tabella database creata con successo!'));
        } else {
            wp_send_json_error(array('message' => 'Errore nella creazione tabella'));
        }
    }
    
    public function handle_create_properties_from_sample() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        
        try {
            // Path to sample XML file with agencies
            $sample_xml_path = 'C:\\Users\\Andrea\\OneDrive\\Lavori\\novacom\\Trentino-immobiliare\\lavoro\\sample-con-agenzie.xml';
            
            if (!file_exists($sample_xml_path)) {
                throw new Exception('Sample XML file not found: ' . $sample_xml_path);
            }
            
            $this->logger->log("Starting test import with sample XML: $sample_xml_path", 'info');
            
            // Configure import engine for test
            $import_engine = new RealEstate_Sync_Import_Engine();
            $import_engine->configure(array(
                'chunk_size' => 10,
                'enabled_provinces' => array('TN', 'BZ'),
                'cleanup_deleted_posts' => false
            ));
            
            // Execute import with sample XML
            $results = $import_engine->execute_chunked_import($sample_xml_path);
            
            // Format results for display
            $summary = sprintf(
                'Sample import completed! Agencies: %d, Properties: %d, Links: %d',
                $results['statistics']['new_agencies'] ?? 0,
                $results['statistics']['new_properties'] ?? 0,
                $results['statistics']['property_agent_links'] ?? 0
            );
            
            wp_send_json_success(array(
                'message' => 'Sample properties created successfully!',
                'summary' => $summary,
                'results' => $results
            ));
            
        } catch (Exception $e) {
            $this->logger->log("Sample import failed: " . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
}

// Initialize admin class
new RealEstate_Sync_Admin();
