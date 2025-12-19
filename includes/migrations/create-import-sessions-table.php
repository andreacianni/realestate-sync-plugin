<?php
/**
 * Migration: Create wp_realestate_import_sessions table
 *
 * This migration creates the import_sessions table for tracking import history.
 * Run this file manually OR trigger via AJAX handler for existing installations.
 *
 * @package RealEstate_Sync
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If called directly (not via WordPress), load WordPress
    $wp_load_path = dirname(__FILE__) . '/../../../../../wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die('WordPress environment not found. Run this from WordPress admin or via wp-cli.');
    }
}

/**
 * Create the import_sessions table
 */
function realestate_sync_create_sessions_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'realestate_import_sessions';
    $charset_collate = $wpdb->get_charset_collate();

    // Check if table already exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if ($table_exists) {
        return [
            'success' => true,
            'message' => 'Table already exists',
            'table' => $table_name
        ];
    }

    $sql = "CREATE TABLE $table_name (
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);

    // Verify table was created
    $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if ($table_exists_after) {
        return [
            'success' => true,
            'message' => 'Table created successfully',
            'table' => $table_name,
            'result' => $result
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Table creation failed',
            'table' => $table_name,
            'result' => $result
        ];
    }
}

// If called directly, execute migration
if (defined('ABSPATH') && !isset($_REQUEST['action'])) {
    $result = realestate_sync_create_sessions_table();

    if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
        echo "Migration Result:\n";
        echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
        echo "Message: " . $result['message'] . "\n";
        echo "Table: " . $result['table'] . "\n";
        if (isset($result['result'])) {
            echo "dbDelta output:\n";
            print_r($result['result']);
        }
    }
}
