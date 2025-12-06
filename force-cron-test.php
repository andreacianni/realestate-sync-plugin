<?php
/**
 * Force Cron Test
 *
 * Forza manualmente l'esecuzione del cron import per testare
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "\n";
echo "========================================\n";
echo "  FORCE CRON EXECUTION TEST\n";
echo "========================================\n";
echo "\n";

// Check if hook has callback
$hook = 'realestate_sync_daily_import';

echo "Hook: {$hook}\n";
echo "\n";

// Get all callbacks registered for this hook
global $wp_filter;

if (isset($wp_filter[$hook])) {
    echo "✓ Hook has callbacks registered:\n";
    echo "\n";

    foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
        echo "  Priority {$priority}:\n";
        foreach ($callbacks as $callback) {
            if (is_array($callback['function'])) {
                $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                echo "    - {$class}::{$callback['function'][1]}()\n";
            } else {
                echo "    - {$callback['function']}()\n";
            }
        }
    }

    echo "\n";
} else {
    echo "❌ NO CALLBACKS REGISTERED FOR THIS HOOK!\n";
    echo "\n";
    echo "This is the problem! The hook exists but has no function attached.\n";
    echo "\n";
    echo "Check if RealEstate_Sync_Cron_Manager is instantiated.\n";
    echo "\n";
    exit(1);
}

echo "========================================\n";
echo "  FORCING HOOK EXECUTION\n";
echo "========================================\n";
echo "\n";

echo "Executing do_action('{$hook}')...\n";
echo "\n";

// Execute the hook
do_action($hook);

echo "\n";
echo "========================================\n";
echo "  EXECUTION COMPLETE\n";
echo "========================================\n";
echo "\n";

echo "Check debug.log for import messages.\n";
echo "Expected log: \"Starting automated daily import\"\n";
echo "\n";

exit(0);
