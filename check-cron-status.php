<?php
/**
 * Check Cron Status
 *
 * Verifica lo stato dei cron schedulati per il plugin
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "\n";
echo "========================================\n";
echo "  CRON STATUS CHECK\n";
echo "========================================\n";
echo "\n";

// Current time
$now = current_time('timestamp');
$now_mysql = current_time('mysql');
$timezone = wp_timezone_string();

echo "Server Time: {$now_mysql} ({$timezone})\n";
echo "Timestamp: {$now}\n";
echo "\n";

// Get all scheduled crons
$crons = _get_cron_array();

echo "========================================\n";
echo "  SCHEDULED CRONS\n";
echo "========================================\n";
echo "\n";

if (empty($crons)) {
    echo "⚠️  NO CRONS SCHEDULED!\n";
} else {
    $found_plugin_cron = false;

    foreach ($crons as $timestamp => $cron) {
        foreach ($cron as $hook => $details) {
            if (strpos($hook, 'realestate_sync') !== false) {
                $found_plugin_cron = true;

                $scheduled_time = date('Y-m-d H:i:s', $timestamp);
                $diff = $timestamp - $now;

                if ($diff > 0) {
                    $status = "⏰ FUTURE (in " . gmdate('H:i:s', $diff) . ")";
                } elseif ($diff > -300) {
                    $status = "⚠️  RECENTLY PASSED (" . abs($diff) . "s ago)";
                } else {
                    $status = "❌ MISSED (" . gmdate('H:i:s', abs($diff)) . " ago)";
                }

                echo "Hook: {$hook}\n";
                echo "  Scheduled: {$scheduled_time}\n";
                echo "  Status: {$status}\n";

                foreach ($details as $detail) {
                    if (isset($detail['schedule'])) {
                        echo "  Recurrence: {$detail['schedule']}\n";
                    }
                    if (isset($detail['args'])) {
                        echo "  Args: " . json_encode($detail['args']) . "\n";
                    }
                }

                echo "\n";
            }
        }
    }

    if (!$found_plugin_cron) {
        echo "⚠️  NO REALESTATE_SYNC CRONS FOUND!\n";
        echo "\n";
        echo "This means the cron was not scheduled properly.\n";
        echo "Check if schedule configuration was saved.\n";
    }
}

echo "\n";
echo "========================================\n";
echo "  CRON CONFIGURATION\n";
echo "========================================\n";
echo "\n";

// Check plugin configuration
$enabled = get_option('realestate_sync_schedule_enabled', false);
$time = get_option('realestate_sync_schedule_time', 'NOT SET');
$frequency = get_option('realestate_sync_schedule_frequency', 'NOT SET');
$weekday = get_option('realestate_sync_schedule_weekday', 'NOT SET');
$custom_days = get_option('realestate_sync_schedule_custom_days', 'NOT SET');
$custom_months = get_option('realestate_sync_schedule_custom_months', 'NOT SET');

echo "Enabled: " . ($enabled ? "YES" : "NO") . "\n";
echo "Time: {$time}\n";
echo "Frequency: {$frequency}\n";

if ($frequency === 'weekly') {
    echo "Weekday: {$weekday}\n";
} elseif ($frequency === 'custom_days') {
    echo "Every {$custom_days} days\n";
} elseif ($frequency === 'custom_months') {
    echo "Every {$custom_months} months\n";
}

echo "\n";
echo "========================================\n";
echo "  WP-CRON STATUS\n";
echo "========================================\n";
echo "\n";

// Check if DISABLE_WP_CRON is set
if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
    echo "⚠️  WP-CRON IS DISABLED!\n";
    echo "\n";
    echo "DISABLE_WP_CRON is set to true in wp-config.php\n";
    echo "This means WordPress cron won't run automatically.\n";
    echo "\n";
    echo "SOLUTIONS:\n";
    echo "1. Set up a real server cron to call wp-cron.php:\n";
    echo "   */5 * * * * curl https://trentinoimmobiliare.it/wp-cron.php\n";
    echo "\n";
    echo "2. OR remove DISABLE_WP_CRON from wp-config.php\n";
    echo "\n";
} else {
    echo "✓ WP-CRON is enabled (triggered on page visits)\n";
    echo "\n";
    echo "NOTE: WP-Cron runs when someone visits the site.\n";
    echo "For guaranteed execution, use a real server cron.\n";
    echo "\n";
}

echo "========================================\n";
echo "  REGISTERED SCHEDULES\n";
echo "========================================\n";
echo "\n";

$schedules = wp_get_schedules();

echo "Available schedules:\n";
foreach ($schedules as $key => $schedule) {
    if (strpos($key, 'realestate') !== false || in_array($key, ['daily', 'weekly', 'hourly'])) {
        echo "  - {$key}: {$schedule['display']} (every " . ($schedule['interval'] / 60) . " minutes)\n";
    }
}

echo "\n";
echo "========================================\n";
echo "  DIAGNOSTICS\n";
echo "========================================\n";
echo "\n";

// Run diagnostics
$issues = array();

if (!$enabled) {
    $issues[] = "Scheduled import is DISABLED in settings";
}

if (empty($crons)) {
    $issues[] = "No crons scheduled in WordPress";
}

$plugin_cron_exists = false;
if (!empty($crons)) {
    foreach ($crons as $cron) {
        foreach ($cron as $hook => $details) {
            if (strpos($hook, 'realestate_sync') !== false) {
                $plugin_cron_exists = true;
                break 2;
            }
        }
    }
}

if ($enabled && !$plugin_cron_exists) {
    $issues[] = "Import is ENABLED but no cron is scheduled!";
    $issues[] = "Try re-saving the schedule configuration";
}

if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
    $issues[] = "WP-Cron is disabled - need server cron setup";
}

if (empty($issues)) {
    echo "✓ No issues detected!\n";
    echo "\n";
    echo "If cron still doesn't run, check:\n";
    echo "  - Site has traffic (WP-Cron needs page visits)\n";
    echo "  - Server cron is configured (if DISABLE_WP_CRON)\n";
    echo "  - Check debug.log for errors\n";
} else {
    echo "⚠️  ISSUES FOUND:\n";
    echo "\n";
    foreach ($issues as $issue) {
        echo "  - {$issue}\n";
    }
}

echo "\n";
exit(0);
