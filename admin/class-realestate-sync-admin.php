<?php
/**
 * RealEstate Sync Plugin - Admin Interface
 * 
 * Main admin interface controller per gestione plugin.
 * Single-page design con status dashboard e emergency import.
 *
 * @package RealEstateSync
 * @subpackage Admin
 * @since 0.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Admin {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Plugin slug
     */
    private $plugin_slug = 'realestate-sync';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        
        // Admin hooks - No menu registration (handled by main class)
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
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'RealEstate Sync',
            'RealEstate Sync',
            'manage_options',
            $this->plugin_slug,
            array($this, 'display_admin_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_' . $this->plugin_slug) {
            return;
        }
        
        wp_enqueue_script(
            'realestate-sync-admin',
            plugin_dir_url(__FILE__) . '../admin/assets/admin.js',
            array('jquery'),
            '0.9.0',
            true
        );
        
        wp_enqueue_style(
            'realestate-sync-admin',
            plugin_dir_url(__FILE__) . '../admin/assets/admin.css',
            array(),
            '0.9.0'
        );
        
        wp_localize_script('realestate-sync-admin', 'realestateSync', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('realestate_sync_nonce'),
            'strings' => array(
                'importing' => 'Import in corso...',
                'success' => 'Import completato con successo',
                'error' => 'Errore durante l\'import',
                'confirm_import' => 'Sei sicuro di voler avviare l\'import manuale?'
            )
        ));
    }
    
    /**
     * Display admin page
     */
    public function display_admin_page() {
        $settings = get_option('realestate_sync_settings', array());
        $last_import = RealEstate_Sync_Import_Engine::get_last_import_results();
        $tracking_stats = $this->get_tracking_statistics();
        $next_scheduled = wp_next_scheduled('realestate_sync_daily_import');
        
        include plugin_dir_path(__FILE__) . '../admin/views/dashboard.php';
    }
    
    /**
     * Handle manual import AJAX
     */
    public function handle_manual_import() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $settings = get_option('realestate_sync_settings', array());
            
            if (empty($settings['xml_url']) || empty($settings['username']) || empty($settings['password'])) {
                throw new Exception('Configurazione mancante. Verifica le impostazioni.');
            }
            
            // Download XML
            $downloader = new RealEstate_Sync_XML_Downloader();
            $xml_file = $downloader->download_xml($settings['xml_url'], $settings['username'], $settings['password']);
            
            if (!$xml_file) {
                throw new Exception('Impossibile scaricare il file XML');
            }
            
            // Execute import
            $import_engine = new RealEstate_Sync_Import_Engine();
            $import_engine->configure($settings);
            
            $results = $import_engine->execute_chunked_import($xml_file);
            
            // Cleanup
            if (file_exists($xml_file)) {
                unlink($xml_file);
            }
            
            wp_send_json_success(array(
                'message' => 'Import completato con successo',
                'results' => $results
            ));
            
        } catch (Exception $e) {
            $this->logger->log("Manual import failed: " . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle test connection AJAX
     */
    public function handle_test_connection() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $url = sanitize_url($_POST['url']);
        $username = sanitize_text_field($_POST['username']);
        $password = sanitize_text_field($_POST['password']);
        
        $downloader = new RealEstate_Sync_XML_Downloader();
        $result = $downloader->test_connection($url, $username, $password);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle save settings AJAX
     */
    public function handle_save_settings() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $settings = array(
            'xml_url' => sanitize_url($_POST['xml_url']),
            'username' => sanitize_text_field($_POST['username']),
            'password' => sanitize_text_field($_POST['password']),
            'notification_email' => sanitize_email($_POST['notification_email']),
            'enabled_provinces' => isset($_POST['enabled_provinces']) ? array_map('sanitize_text_field', $_POST['enabled_provinces']) : array(),
            'chunk_size' => isset($_POST['chunk_size']) ? intval($_POST['chunk_size']) : 50,
            'sleep_seconds' => isset($_POST['sleep_seconds']) ? intval($_POST['sleep_seconds']) : 2
        );
        
        $result = update_option('realestate_sync_settings', $settings);
        
        if ($result !== false) {
            $this->logger->log('Settings saved successfully', 'info');
            wp_send_json_success('Impostazioni salvate con successo');
        } else {
            $this->logger->log('Failed to save settings', 'error');
            wp_send_json_error('Errore nel salvataggio delle impostazioni');
        }
    }
    
    /**
     * Handle get progress AJAX
     */
    public function handle_get_progress() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        $progress = RealEstate_Sync_Import_Engine::get_current_progress();
        
        if ($progress) {
            wp_send_json_success($progress);
        } else {
            wp_send_json_error('Nessun import in corso');
        }
    }
    
    /**
     * Get tracking statistics
     */
    private function get_tracking_statistics() {
        $tracking_manager = new RealEstate_Sync_Tracking_Manager();
        return $tracking_manager->get_import_statistics();
    }
    
    /**
     * Handle get logs AJAX
     */
    public function handle_get_logs() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $logs = $this->logger->get_recent_logs(100);
        
        if ($logs) {
            wp_send_json_success(array('logs' => implode("\n", $logs)));
        } else {
            wp_send_json_success(array('logs' => 'Nessun log disponibile'));
        }
    }
    
    /**
     * Handle download logs AJAX
     */
    public function handle_download_logs() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $logs = $this->logger->get_all_logs();
        $filename = 'realestate-sync-logs-' . date('Y-m-d-H-i-s') . '.txt';
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        echo implode("\n", $logs);
        exit;
    }
    
    /**
     * Handle clear logs AJAX
     */
    public function handle_clear_logs() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->logger->clear_logs();
        
        if ($result) {
            wp_send_json_success('Log cancellati con successo');
        } else {
            wp_send_json_error('Errore nella cancellazione dei log');
        }
    }
    
    /**
     * Handle system check AJAX
     */
    public function handle_system_check() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $checks = array(
            'PHP Version' => PHP_VERSION . ' (Required: 7.4+)',
            'WordPress Version' => get_bloginfo('version') . ' (Required: 5.0+)',
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . ' seconds',
            'Upload Max Filesize' => ini_get('upload_max_filesize'),
            'cURL Extension' => function_exists('curl_init') ? 'Available' : 'Missing',
            'SimpleXML Extension' => class_exists('SimpleXMLElement') ? 'Available' : 'Missing',
            'Writable Logs Directory' => is_writable(REALESTATE_SYNC_PLUGIN_DIR . 'logs/') ? 'Yes' : 'No'
        );
        
        $html = '<table class="rs-form-table">';
        foreach ($checks as $check => $value) {
            $status_class = 'rs-status-success';
            if (strpos($value, 'Missing') !== false || strpos($value, 'No') !== false) {
                $status_class = 'rs-status-error';
            }
            $html .= '<tr><th>' . $check . '</th><td><span class="rs-status-badge ' . $status_class . '">' . $value . '</span></td></tr>';
        }
        $html .= '</table>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Handle toggle automation AJAX
     */
    public function handle_toggle_automation() {
        check_ajax_referer('realestate_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $cron_manager = new RealEstate_Sync_Cron_Manager();
        
        if ($cron_manager->is_scheduled()) {
            $result = $cron_manager->unschedule();
            $message = $result ? 'Automazione disabilitata' : 'Errore nella disabilitazione';
        } else {
            $result = $cron_manager->schedule();
            $message = $result ? 'Automazione abilitata' : 'Errore nell\'abilitazione';
        }
        
        if ($result) {
            wp_send_json_success($message);
        } else {
            wp_send_json_error($message);
        }
    }
}

// Initialize admin
new RealEstate_Sync_Admin();
