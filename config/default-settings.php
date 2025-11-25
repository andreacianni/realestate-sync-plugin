<?php
/**
 * RealEstate Sync Settings
 */

return array(
    // XML Source Configuration
    'realestate_sync_xml_url' => 'https://www.gestionaleimmobiliare.it/export/xml/trentinoimmobiliare_it/export_gi_full_merge_multilevel.xml.tar.gz',
    'realestate_sync_username' => '',
    'realestate_sync_password' => '',

    // WPResidence API Configuration (v1.4.0)
    'realestate_sync_api_username' => 'accessi@prioloweb.it',
    'realestate_sync_api_password' => '2#&211`%#5+z',
    'realestate_sync_use_api_importer' => true, // Use API-based importer (v1.4.0+)
    'realestate_sync_property_user_id' => '', // WordPress User ID for property ownership (optional, defaults to JWT user)

    // Import Configuration
    'realestate_sync_enabled_provinces' => array('TN', 'BZ'),
    'realestate_sync_chunk_size' => 25,
    'realestate_sync_sleep_seconds' => 1,
    'realestate_sync_max_memory_mb' => 256,
    'realestate_sync_max_errors' => 10,
    'realestate_sync_max_execution_time' => 3600,

    // WordPress Integration
    'realestate_sync_duplicate_action' => 'update',
    'realestate_sync_cleanup_deleted_posts' => true,
    'realestate_sync_backup_before_import' => false,
    'realestate_sync_create_missing_terms' => true,

    // Notifications
    'realestate_sync_notification_email' => get_option('admin_email'),
    'realestate_sync_notify_on_success' => true,
    'realestate_sync_notify_on_error' => true,

    // Performance
    'realestate_sync_memory_limit' => '512M',
    'realestate_sync_time_limit' => 0
);
