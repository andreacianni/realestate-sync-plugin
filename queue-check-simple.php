<?php
// Simple queue check - outputs plain text
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

header('Content-Type: text/plain');

global $wpdb;
$table = $wpdb->prefix . 'realestate_import_queue';

echo "QUEUE TABLE CHECK\n";
echo "=================\n\n";

// Check table exists
$exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");

if (!$exists) {
    echo "ERROR: Table '{$table}' does NOT exist!\n";
    echo "\nYou need to run create-queue-table.php to create it.\n";
    exit;
}

echo "Table: {$table} EXISTS\n\n";

// Count total
$total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
echo "Total items: {$total}\n\n";

if ($total == 0) {
    echo "Queue is EMPTY - no data to display.\n";
    exit;
}

// Get sessions summary
echo "SESSIONS:\n";
echo "---------\n";
$sessions = $wpdb->get_results("
    SELECT
        session_id,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        MIN(created_at) as first_item,
        MAX(updated_at) as last_update
    FROM {$table}
    GROUP BY session_id
    ORDER BY MAX(created_at) DESC
    LIMIT 5
");

foreach ($sessions as $s) {
    echo "\nSession: {$s->session_id}\n";
    echo "  Total: {$s->total}, Pending: {$s->pending}, Processing: {$s->processing}, Completed: {$s->completed}, Failed: {$s->failed}\n";
    echo "  First: {$s->first_item}, Last: {$s->last_update}\n";
}
