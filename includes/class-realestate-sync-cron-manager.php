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
            $block_message = RealEstate_Sync_Batch_Orchestrator::get_import_start_block_message();

            if (null !== $block_message) {
                $this->logger->log($block_message, 'warning');
                return;
            }

            // Get credential source
            $credential_source = get_option('realestate_sync_credential_source', 'hardcoded');

            if ($credential_source === 'database') {
                // Use credentials from database
                $settings = array(
                    'xml_url' => get_option('realestate_sync_xml_url', ''),
                    'username' => get_option('realestate_sync_xml_user', ''),
                    'password' => get_option('realestate_sync_xml_pass', '')
                );

                if (empty($settings['xml_url']) || empty($settings['username']) || empty($settings['password'])) {
                    throw new Exception('XML credentials not configured in database');
                }

                $this->logger->log('Cron: Using XML credentials from database', 'info');

            } else {
                // Use hardcoded credentials
                $settings = array(
                    'xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
                    'username' => 'trentinoimmobiliare_it',
                    'password' => 'dget6g52'
                );

                $this->logger->log('Cron: Using hardcoded XML credentials', 'info');
            }

            // Download XML
            $downloader = new RealEstate_Sync_XML_Downloader();
            $xml_file = $downloader->download_xml($settings['xml_url'], $settings['username'], $settings['password']);

            if (!$xml_file) {
                throw new Exception('Failed to download XML file');
            }

            // Get test mode setting from schedule configuration
            $mark_as_test = get_option('realestate_sync_schedule_mark_test', false);

            // ✅ BATCH ORCHESTRATOR: Process using batch system (same as manual import)
            $this->logger->log('🎯 Scheduled import: Calling Batch Orchestrator with downloaded XML', 'info');

            $result = RealEstate_Sync_Batch_Orchestrator::process_xml_batch($xml_file, $mark_as_test);

            if (!$result['success']) {
                throw new Exception('Batch processing failed: ' . ($result['error'] ?? 'Unknown error'));
            }

            $this->logger->log('🎯 Batch orchestration complete: ' . $result['total_queued'] . ' items queued, ' . $result['first_batch_processed'] . ' processed in first batch', 'success');

            // Prepare results for email notification
            $results = array(
                'session_id' => $result['session_id'],
                'total_queued' => $result['total_queued'],
                'agencies_queued' => $result['agencies_queued'],
                'properties_queued' => $result['properties_queued'],
                'first_batch_processed' => $result['first_batch_processed'],
                'complete' => $result['complete'],
                'remaining' => $result['remaining']
            );

            // Send success email
            $this->send_import_notification($results, 'success');

            $this->logger->log("Scheduled import batch started successfully - processing will continue via batch-continuation.php", 'info');
            
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
     * Check if import is scheduled
     * 
     * @return bool
     */
    public function is_scheduled() {
        return wp_next_scheduled(self::DAILY_IMPORT_HOOK) !== false;
    }
    
    /**
     * Manually trigger import
     */
    public function trigger_manual_import() {
        wp_schedule_single_event(time(), self::DAILY_IMPORT_HOOK);
        wp_cron();
    }

    /**
     * Reschedule import with custom time and frequency
     *
     * @param string $time Time in HH:MM format (24h)
     * @param string $frequency daily|weekly|custom_days|custom_months
     * @param int $weekday Day of week (0=Sunday, 6=Saturday) for weekly
     * @param int $custom_days Number of days for custom_days
     * @param int $custom_months Number of months for custom_months
     * @return array Result with success status and next_run timestamp
     */
    public function reschedule_import($time, $frequency, $weekday = 1, $custom_days = 1, $custom_months = 1) {
        // Clear existing schedule first
        $this->unschedule_imports();

        // Calculate next run timestamp
        $next_run = $this->calculate_next_run_time($time, $frequency, $weekday, $custom_days, $custom_months);

        if (!$next_run) {
            return array('success' => false, 'error' => 'Invalid schedule configuration');
        }

        // Register custom intervals if needed
        add_filter('cron_schedules', array($this, 'register_custom_intervals'));

        // Determine recurrence
        $recurrence = $this->get_recurrence_key($frequency, $custom_days, $custom_months);

        // Schedule the event
        $scheduled = wp_schedule_event($next_run, $recurrence, self::DAILY_IMPORT_HOOK);

        if ($scheduled === false) {
            $this->logger->log("Failed to schedule import", 'error', array(
                'next_run' => date('Y-m-d H:i:s', $next_run),
                'recurrence' => $recurrence
            ));
            return array('success' => false, 'error' => 'Failed to schedule event');
        }

        $this->logger->log("Import rescheduled successfully", 'info', array(
            'time' => $time,
            'frequency' => $frequency,
            'next_run' => date('Y-m-d H:i:s', $next_run),
            'recurrence' => $recurrence
        ));

        return array(
            'success' => true,
            'next_run' => $next_run
        );
    }

    /**
     * Unschedule all import cron jobs
     */
    public function unschedule_imports() {
        wp_clear_scheduled_hook(self::DAILY_IMPORT_HOOK);
        $this->logger->log("All scheduled imports cleared", 'info');
    }

    /**
     * Register custom cron intervals
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function register_custom_intervals($schedules) {
        // Get custom intervals from options
        $custom_days = get_option('realestate_sync_schedule_custom_days', 1);
        $custom_months = get_option('realestate_sync_schedule_custom_months', 1);

        // Custom days interval
        $schedules['realestate_custom_days'] = array(
            'interval' => $custom_days * DAY_IN_SECONDS,
            'display' => sprintf(__('Every %d days'), $custom_days)
        );

        // Custom months interval (approximate: 30 days per month)
        $schedules['realestate_custom_months'] = array(
            'interval' => $custom_months * 30 * DAY_IN_SECONDS,
            'display' => sprintf(__('Every %d months'), $custom_months)
        );

        return $schedules;
    }

    /**
     * Get recurrence key based on frequency type
     *
     * @param string $frequency Frequency type
     * @param int $custom_days Custom days value
     * @param int $custom_months Custom months value
     * @return string Recurrence key
     */
    private function get_recurrence_key($frequency, $custom_days, $custom_months) {
        switch ($frequency) {
            case 'daily':
                return 'daily';
            case 'weekly':
                return 'weekly';
            case 'custom_days':
                return 'realestate_custom_days';
            case 'custom_months':
                return 'realestate_custom_months';
            default:
                return 'daily';
        }
    }

    /**
     * Calculate next run timestamp based on configuration
     *
     * @param string $time Time in HH:MM format
     * @param string $frequency Frequency type
     * @param int $weekday Day of week for weekly
     * @param int $custom_days Days for custom
     * @param int $custom_months Months for custom
     * @return int|false Timestamp or false on error
     */
    private function calculate_next_run_time($time, $frequency, $weekday, $custom_days, $custom_months) {
        // Parse time (HH:MM)
        list($hour, $minute) = explode(':', $time);
        $hour = intval($hour);
        $minute = intval($minute);

        // Get current server time
        $now = current_time('timestamp');
        $today = strtotime(current_time('Y-m-d'));

        // Calculate base time today at specified hour:minute
        $base_time = $today + ($hour * HOUR_IN_SECONDS) + ($minute * MINUTE_IN_SECONDS);

        switch ($frequency) {
            case 'daily':
                // If time has passed today, schedule for tomorrow
                if ($base_time <= $now) {
                    return $base_time + DAY_IN_SECONDS;
                }
                return $base_time;

            case 'weekly':
                // Calculate next occurrence of specified weekday
                $current_weekday = intval(date('w', $now));
                $days_until = ($weekday - $current_weekday + 7) % 7;

                // If today is the day but time has passed, add 7 days
                if ($days_until === 0 && $base_time <= $now) {
                    $days_until = 7;
                }

                return $base_time + ($days_until * DAY_IN_SECONDS);

            case 'custom_days':
                // Schedule for today if time hasn't passed, otherwise tomorrow
                if ($base_time <= $now) {
                    return $base_time + DAY_IN_SECONDS;
                }
                return $base_time;

            case 'custom_months':
                // Schedule for today if time hasn't passed, otherwise tomorrow
                // WordPress cron will handle the monthly recurrence
                if ($base_time <= $now) {
                    return $base_time + DAY_IN_SECONDS;
                }
                return $base_time;

            default:
                return false;
        }
    }
}
