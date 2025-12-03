<?php
/**
 * Create Queue Table Manually
 * Run this once to create the batch queue table
 */

define('WP_USE_THEMES', false);
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "<h1>Create Batch Queue Table</h1>";
echo "<pre>";

// Load Queue Manager
require_once(dirname(__FILE__) . '/includes/class-realestate-sync-queue-manager.php');

$queue_manager = new RealEstate_Sync_Queue_Manager();

echo "Creating queue table...\n";
$queue_manager->create_table();

// Verify table exists
global $wpdb;
$table = $wpdb->prefix . 'realestate_import_queue';
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

if ($exists) {
    echo "✅ SUCCESS! Table created: $table\n\n";

    // Show table structure
    $columns = $wpdb->get_results("DESCRIBE $table");
    echo "Table structure:\n";
    foreach ($columns as $col) {
        echo "  - {$col->Field} ({$col->Type})\n";
    }
} else {
    echo "❌ ERROR: Table not created\n";
}

echo "\n========================================\n";
echo "✅ DONE! You can now test the batch system.\n";
echo "========================================\n";

echo "</pre>";
?>
