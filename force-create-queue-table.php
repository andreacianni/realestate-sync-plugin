<?php
/**
 * Force create queue table - Safe to run multiple times
 */
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

header('Content-Type: text/plain; charset=utf-8');

global $wpdb;
$table_name = $wpdb->prefix . 'realestate_import_queue';

echo "FORCE CREATE QUEUE TABLE\n";
echo "========================\n\n";

// Check if exists
$exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

if ($exists) {
    echo "Table '{$table_name}' already EXISTS\n\n";

    // Show current content
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "Current items in table: {$count}\n";

    if ($count > 0) {
        echo "\nLast 5 sessions:\n";
        $sessions = $wpdb->get_results("
            SELECT session_id, COUNT(*) as items, MAX(created_at) as created
            FROM {$table_name}
            GROUP BY session_id
            ORDER BY MAX(created_at) DESC
            LIMIT 5
        ");
        foreach ($sessions as $s) {
            echo "  - {$s->session_id}: {$s->items} items (created: {$s->created})\n";
        }
    }
} else {
    echo "Table '{$table_name}' DOES NOT EXIST\n";
    echo "Creating table...\n\n";

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id varchar(100) NOT NULL,
        item_type varchar(20) NOT NULL,
        item_id varchar(100) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        retry_count int(11) DEFAULT 0,
        error_message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY session_id (session_id),
        KEY status (status),
        KEY item_type (item_type)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Verify creation
    $exists_now = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

    if ($exists_now) {
        echo "SUCCESS! Table created.\n";
    } else {
        echo "ERROR! Table creation failed.\n";
        echo "SQL used:\n{$sql}\n";
    }
}

echo "\nDone!\n";
