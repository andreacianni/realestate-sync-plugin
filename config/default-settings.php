<?php
/**
 * RealEstate Sync Settings
 */

return array(
    // XML Source Configuration
    'xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
    'username' => '',
    'password' => '',
    
    // Import Configuration
    'enabled_provinces' => array('TN', 'BZ'),
    'chunk_size' => 25,
    'sleep_seconds' => 1,
    'max_memory_mb' => 256,
    'max_errors' => 10,
    'max_execution_time' => 3600,
    
    // WordPress Integration
    'duplicate_action' => 'update',
    'cleanup_deleted_posts' => true,
    'backup_before_import' => false,
    'create_missing_terms' => true,
    
    // Notifications
    'notification_email' => get_option('admin_email'),
    'notify_on_success' => true,
    'notify_on_error' => true,
    
    // Performance
    'memory_limit' => '512M',
    'time_limit' => 0
);
