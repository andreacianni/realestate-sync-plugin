<?php
/**
 * RealEstate Sync Plugin - Cron Manager
 * 
 * Gestisce l'automazione degli import giornalieri tramite WordPress cron
 * con email notifications e error handling.
 *
 * @package RealEstateSync
 * @subpackage Core
 * @since 0.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Cron_Manager {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Cron hook names
     */
    const DAILY_IMPORT_HOOK = 'realestate_sync_daily_import';
    const CLEANUP_HOOK = 'realestate_sync_cleanup';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        
        // Hook cron actions
        add_action(self::DAILY_IMPORT_HOOK, array($this, 'execute_daily_import'));
        add_action(self::CLEANUP_HOOK, array($this, 'execute_cleanup'));
        
        // Hook plugin activation/deactivation
        add_action('realestate_sync_activate', array($this, 'schedule_cron_jobs'));
        add_action('realestate_sync_deactivate', array($this, 'unschedule_cron_jobs'));
    }
    
    /**
     * Schedule cron jobs on plugin activation
     */
    public function schedule_cron_jobs() {
        // Schedule daily import
        if (!wp_next_scheduled(self::DAILY_IMPORT_HOOK)) {
            wp_schedule_event(time(), 'daily', self::DAILY_IMPORT_HOOK);
            $this->logger->log("Daily import cron scheduled", 'info');
        }
        
        // Schedule weekly cleanup
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), 'weekly', self::CLEANUP_HOOK);
            $this->logger->log("Weekly cleanup cron scheduled", 'info');
        }
    }
    
    /**
     * Unschedule cron jobs on plugin deactivation
     */
    public function unschedule_cron_jobs() {
        wp_clear_scheduled_hook(self::DAILY_IMPORT_HOOK);
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
        $this->logger->log("All cron jobs unscheduled", 'info');
    }
    
    /**
     * Execute daily import via cron
     */
    public function execute_daily_import() {
        $this->logger->log("Starting automated daily import", 'info');
        
        try {
            // Get settings
            $settings = get_option('realestate_sync_settings', array());
            
            if (empty($settings['xml_url']) || empty($settings['username']) || empty($settings['password'])) {
                throw new Exception('Missing XML credentials in settings');
            }
            
            // Download XML
            $downloader = new RealEstate_Sync_XML_Downloader();
            $xml_file = $downloader->download_xml($settings['xml_url'], $settings['username'], $settings['password']);
            
            if (!$xml_file) {
                throw new Exception('Failed to download XML file');
            }
            
            // Execute import
            $import_engine = new RealEstate_Sync_Import_Engine();
            $import_engine->configure($settings);
            
            $results = $import_engine->execute_chunked_import($xml_file);
            
            // Send success email
            $this->send_import_notification($results, 'success');
            
            // Cleanup XML file
            if (file_exists($xml_file)) {
                unlink($xml_file);
            }
            
            $this->logger->log("Automated daily import completed successfully", 'info');
            
        } catch (Exception $e) {
            $this->logger->log("Automated daily import failed: " . $e->getMessage(), 'error');
            
            // Send error email
            $this->send_import_notification(array('error' => $e->getMessage()), 'error');
        }
    }
    
    /**
     * Execute cleanup via cron
     */
    public function execute_cleanup() {
        $this->logger->log("Starting automated cleanup", 'info');
        
        try {
            // Cleanup tracking records
            $tracking_manager = new RealEstate_Sync_Tracking_Manager();
            $cleaned_records = $tracking_manager->cleanup_old_tracking_records(90);
            
            // Cleanup log files
            $cleaned_logs = $this->logger->cleanup_old_logs(30);
            
            $this->logger->log("Automated cleanup completed: $cleaned_records tracking records, $cleaned_logs log files", 'info');
            
        } catch (Exception $e) {
            $this->logger->log("Automated cleanup failed: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send import notification email
     * 
     * @param array $results Import results
     * @param string $type success|error
     */
    private function send_import_notification($results, $type = 'success') {
        $settings = get_option('realestate_sync_settings', array());
        
        if (empty($settings['notification_email'])) {
            return;
        }
        
        $to = $settings['notification_email'];
        $site_name = get_bloginfo('name');
        
        if ($type === 'success') {
            $subject = "[{$site_name}] Import automatico completato";
            $message = $this->format_success_email($results);
        } else {
            $subject = "[{$site_name}] Errore import automatico";
            $message = $this->format_error_email($results);
        }
        
        wp_mail($to, $subject, $message);
    }
    
    /**
     * Format success email message
     */
    private function format_success_email($results) {
        $stats = $results['statistics'];
        
        $message = "Import automatico completato con successo.\n\n";
        $message .= "Statistiche:\n";
        $message .= "- Proprietà elaborate: " . $stats['total_processed'] . "\n";
        $message .= "- Nuove proprietà: " . $stats['new_properties'] . "\n";
        $message .= "- Proprietà aggiornate: " . $stats['updated_properties'] . "\n";
        $message .= "- Proprietà eliminate: " . $stats['deleted_properties'] . "\n";
        $message .= "- Durata: " . $results['duration_formatted'] . "\n";
        $message .= "- Memoria picco: " . $results['memory_peak_mb'] . "MB\n\n";
        $message .= "Data/ora: " . current_time('mysql') . "\n";
        
        return $message;
    }
    
    /**
     * Format error email message
     */
    private function format_error_email($results) {
        $message = "ERRORE durante l'import automatico.\n\n";
        $message .= "Errore: " . $results['error'] . "\n\n";
        $message .= "Data/ora: " . current_time('mysql') . "\n";
        $message .= "Controllare i log per maggiori dettagli.\n";
        
        return $message;
    }
    
    /**
     * Get next scheduled import time
     */
    public function get_next_scheduled_import() {
        return wp_next_scheduled(self::DAILY_IMPORT_HOOK);
    }
    
    /**
     * Manually trigger import
     */
    public function trigger_manual_import() {
        wp_schedule_single_event(time(), self::DAILY_IMPORT_HOOK);
        wp_cron();
    }
}
