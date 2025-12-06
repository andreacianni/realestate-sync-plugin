<?php
// Temporary debug script - Check queue table
require_once('../../../wp-load.php');

global $wpdb;
$queue_table = $wpdb->prefix . 'realestate_import_queue';

echo "=== QUEUE TABLE DEBUG ===\n\n";

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$queue_table}'");
echo "Table exists: " . ($table_exists ? "YES ({$queue_table})" : "NO") . "\n\n";

if (!$table_exists) {
    echo "ERROR: Table does not exist!\n";
    echo "Expected table name: {$queue_table}\n";
    exit;
}

// Get total count
$total = $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table}");
echo "Total items in queue: {$total}\n\n";

if ($total > 0) {
    // Get session info
    echo "=== SESSIONS ===\n";
    $sessions = $wpdb->get_results("
        SELECT
            session_id,
            MIN(created_at) as start_time,
            MAX(updated_at) as last_activity,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM {$queue_table}
        GROUP BY session_id
        ORDER BY MAX(created_at) DESC
        LIMIT 5
    ");

    foreach ($sessions as $session) {
        echo "\nSession: {$session->session_id}\n";
        echo "  Start: {$session->start_time}\n";
        echo "  Last Activity: {$session->last_activity}\n";
        echo "  Total: {$session->total}\n";
        echo "  Pending: {$session->pending}\n";
        echo "  Processing: {$session->processing}\n";
        echo "  Completed: {$session->completed}\n";
        echo "  Failed: {$session->failed}\n";
    }

    // Get last session detailed items
    echo "\n=== LAST SESSION ITEMS (first 10) ===\n";
    $last_session_id = $sessions[0]->session_id;
    $items = $wpdb->get_results($wpdb->prepare("
        SELECT id, item_type, item_id, status, created_at, updated_at
        FROM {$queue_table}
        WHERE session_id = %s
        ORDER BY id ASC
        LIMIT 10
    ", $last_session_id));

    foreach ($items as $item) {
        echo "\nID: {$item->id} | Type: {$item->item_type} | Item ID: {$item->item_id} | Status: {$item->status}\n";
        echo "  Created: {$item->created_at} | Updated: {$item->updated_at}\n";
    }
}

echo "\n=== END DEBUG ===\n";
