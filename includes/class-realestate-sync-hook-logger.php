<?php
/**
 * Hook Logger - Debug System for WordPress Hooks
 *
 * Logs ALL WordPress hooks executed during property processing
 * to compare programmatic import vs manual save from editor.
 *
 * @package RealEstateSync
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Hook_Logger {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Hooks log storage
     */
    private $hooks_log = [];

    /**
     * Monitoring active flag
     */
    private $is_monitoring = false;

    /**
     * Target post ID to monitor
     */
    private $target_post_id = null;

    /**
     * Log file path
     */
    private $log_file_path = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();

        // Set log file path
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/realestate-sync-logs/hook-logs';

        // 🔧 DEBUG: Log directory creation
        $this->logger->log('🔍 Hook Logger: Initializing', 'info', [
            'upload_basedir' => $upload_dir['basedir'],
            'log_dir' => $log_dir,
            'dir_exists' => file_exists($log_dir),
            'dir_writable' => is_writable($log_dir)
        ]);

        if (!file_exists($log_dir)) {
            $result = wp_mkdir_p($log_dir);
            $this->logger->log('🔍 Hook Logger: Creating directory', 'info', [
                'log_dir' => $log_dir,
                'mkdir_result' => $result,
                'dir_exists_after' => file_exists($log_dir)
            ]);
        }

        $this->log_file_path = $log_dir . '/hooks-' . date('Y-m-d_H-i-s') . '.log';

        $this->logger->log('🔍 Hook Logger: Log file path set', 'info', [
            'log_file_path' => $this->log_file_path
        ]);
    }

    /**
     * Start monitoring hooks for specific post
     *
     * @param int $post_id Post ID to monitor
     * @param string $context Context label (e.g., 'programmatic_import', 'manual_save')
     */
    public function start_monitoring($post_id, $context = 'unknown') {
        $this->target_post_id = $post_id;
        $this->is_monitoring = true;
        $this->hooks_log = [];

        $this->log_to_file("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->log_to_file("🔍 HOOK MONITORING STARTED");
        $this->log_to_file("Context: {$context}");
        $this->log_to_file("Post ID: {$post_id}");
        $this->log_to_file("Timestamp: " . date('Y-m-d H:i:s'));
        $this->log_to_file("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Register universal hook interceptor
        $this->register_hook_interceptors();

        $this->logger->log("🔍 Hook monitoring started for post {$post_id}", 'info', [
            'context' => $context,
            'log_file' => $this->log_file_path
        ]);
    }

    /**
     * Stop monitoring and save results
     *
     * @return array Hooks log
     */
    public function stop_monitoring() {
        if (!$this->is_monitoring) {
            return [];
        }

        $this->is_monitoring = false;

        $this->log_to_file("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->log_to_file("✅ HOOK MONITORING STOPPED");
        $this->log_to_file("Total hooks logged: " . count($this->hooks_log));
        $this->log_to_file("Timestamp: " . date('Y-m-d H:i:s'));
        $this->log_to_file("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->log_to_file("");

        // Generate summary
        $this->generate_summary();

        $this->logger->log("✅ Hook monitoring stopped for post {$this->target_post_id}", 'info', [
            'total_hooks' => count($this->hooks_log),
            'log_file' => $this->log_file_path
        ]);

        return $this->hooks_log;
    }

    /**
     * Register hook interceptors for common WordPress hooks
     */
    private function register_hook_interceptors() {
        // Post-related hooks
        $post_hooks = [
            'save_post',
            'save_post_estate_property',
            'wp_insert_post',
            'edit_post',
            'post_updated',
            'wp_after_insert_post',
            'before_delete_post',
            'deleted_post',
            'transition_post_status',
            'set_object_terms',
            'created_term',
            'edited_term'
        ];

        foreach ($post_hooks as $hook) {
            add_action($hook, function() use ($hook) {
                if ($this->is_monitoring) {
                    $args = func_get_args();
                    $this->log_hook($hook, $args);
                }
            }, 1, 10);
        }

        // Meta-related hooks
        $meta_hooks = [
            'added_post_meta',
            'updated_post_meta',
            'deleted_post_meta',
            'update_post_metadata',
            'add_post_metadata'
        ];

        foreach ($meta_hooks as $hook) {
            add_action($hook, function() use ($hook) {
                if ($this->is_monitoring) {
                    $args = func_get_args();
                    $this->log_hook($hook, $args);
                }
            }, 1, 10);
        }

        // Taxonomy hooks
        $taxonomy_hooks = [
            'wp_set_object_terms',
            'set_object_terms'
        ];

        foreach ($taxonomy_hooks as $hook) {
            add_action($hook, function() use ($hook) {
                if ($this->is_monitoring) {
                    $args = func_get_args();
                    $this->log_hook($hook, $args);
                }
            }, 1, 10);
        }

        // Attachment hooks (for gallery)
        $attachment_hooks = [
            'add_attachment',
            'edit_attachment',
            'delete_attachment',
            'wp_update_attachment_metadata'
        ];

        foreach ($attachment_hooks as $hook) {
            add_action($hook, function() use ($hook) {
                if ($this->is_monitoring) {
                    $args = func_get_args();
                    $this->log_hook($hook, $args);
                }
            }, 1, 10);
        }
    }

    /**
     * Log a hook execution
     *
     * @param string $hook_name Hook name
     * @param array $args Hook arguments
     */
    private function log_hook($hook_name, $args = []) {
        if (!$this->is_monitoring) {
            return;
        }

        // Check if hook is related to our target post
        $is_relevant = $this->is_hook_relevant($hook_name, $args);

        if (!$is_relevant && $this->target_post_id !== null) {
            return; // Skip irrelevant hooks
        }

        $timestamp = microtime(true);
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);

        $hook_entry = [
            'timestamp' => $timestamp,
            'hook' => $hook_name,
            'args' => $this->sanitize_args($args),
            'backtrace' => $this->format_backtrace($backtrace)
        ];

        $this->hooks_log[] = $hook_entry;

        // Log to file
        $this->log_hook_to_file($hook_entry);
    }

    /**
     * Check if hook is relevant to target post
     *
     * @param string $hook_name Hook name
     * @param array $args Hook arguments
     * @return bool
     */
    private function is_hook_relevant($hook_name, $args) {
        if ($this->target_post_id === null) {
            return true; // Log all if no specific target
        }

        // Check first argument for post ID
        if (isset($args[0])) {
            if (is_numeric($args[0]) && intval($args[0]) === $this->target_post_id) {
                return true;
            }

            // Check if it's a WP_Post object
            if (is_object($args[0]) && isset($args[0]->ID)) {
                if (intval($args[0]->ID) === $this->target_post_id) {
                    return true;
                }
            }
        }

        // Check second argument (some hooks have it there)
        if (isset($args[1])) {
            if (is_numeric($args[1]) && intval($args[1]) === $this->target_post_id) {
                return true;
            }
        }

        // For meta hooks, check meta_id to post_id mapping
        if (strpos($hook_name, '_post_meta') !== false) {
            // These hooks don't directly have post_id, but we can check meta_key
            if (isset($args[1])) {
                // args[1] is usually object_id (post_id) for post_meta hooks
                if (is_numeric($args[1]) && intval($args[1]) === $this->target_post_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Sanitize hook arguments for logging
     *
     * @param array $args Arguments
     * @return array Sanitized arguments
     */
    private function sanitize_args($args) {
        $sanitized = [];

        foreach ($args as $index => $arg) {
            if (is_object($arg)) {
                if ($arg instanceof WP_Post) {
                    $sanitized[$index] = [
                        'type' => 'WP_Post',
                        'ID' => $arg->ID,
                        'post_type' => $arg->post_type,
                        'post_status' => $arg->post_status,
                        'post_title' => $arg->post_title
                    ];
                } else {
                    $sanitized[$index] = [
                        'type' => get_class($arg),
                        'properties' => get_object_vars($arg)
                    ];
                }
            } elseif (is_array($arg)) {
                // Limit array depth
                $sanitized[$index] = $this->limit_array_depth($arg, 2);
            } else {
                $sanitized[$index] = $arg;
            }
        }

        return $sanitized;
    }

    /**
     * Limit array depth for logging
     *
     * @param array $array Array to limit
     * @param int $max_depth Maximum depth
     * @param int $current_depth Current depth
     * @return mixed
     */
    private function limit_array_depth($array, $max_depth, $current_depth = 0) {
        if ($current_depth >= $max_depth) {
            return '[...]';
        }

        if (!is_array($array)) {
            return $array;
        }

        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->limit_array_depth($value, $max_depth, $current_depth + 1);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Format backtrace for logging
     *
     * @param array $backtrace Backtrace
     * @return array Formatted backtrace
     */
    private function format_backtrace($backtrace) {
        $formatted = [];

        foreach ($backtrace as $trace) {
            $formatted[] = [
                'file' => isset($trace['file']) ? basename($trace['file']) : 'unknown',
                'line' => $trace['line'] ?? 0,
                'function' => $trace['function'] ?? 'unknown',
                'class' => $trace['class'] ?? null
            ];
        }

        return $formatted;
    }

    /**
     * Log hook to file
     *
     * @param array $hook_entry Hook entry
     */
    private function log_hook_to_file($hook_entry) {
        $output = sprintf(
            "[%s] %s\n",
            date('H:i:s', $hook_entry['timestamp']),
            $hook_entry['hook']
        );

        // Add args
        if (!empty($hook_entry['args'])) {
            $output .= "  Args: " . json_encode($hook_entry['args'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        // Add first backtrace entry (caller)
        if (!empty($hook_entry['backtrace'][0])) {
            $bt = $hook_entry['backtrace'][0];
            $output .= sprintf(
                "  Called from: %s:%d in %s%s%s\n",
                $bt['file'],
                $bt['line'],
                $bt['class'] ?? '',
                $bt['class'] ? '::' : '',
                $bt['function']
            );
        }

        $output .= "\n";

        file_put_contents($this->log_file_path, $output, FILE_APPEND);
    }

    /**
     * Log message to file
     *
     * @param string $message Message
     */
    private function log_to_file($message) {
        file_put_contents($this->log_file_path, $message . "\n", FILE_APPEND);
    }

    /**
     * Generate summary of hooks
     */
    private function generate_summary() {
        $this->log_to_file("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->log_to_file("📊 HOOKS SUMMARY");
        $this->log_to_file("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Count hooks by name
        $hook_counts = [];
        foreach ($this->hooks_log as $entry) {
            $hook_name = $entry['hook'];
            if (!isset($hook_counts[$hook_name])) {
                $hook_counts[$hook_name] = 0;
            }
            $hook_counts[$hook_name]++;
        }

        // Sort by count
        arsort($hook_counts);

        $this->log_to_file("\nHooks executed (sorted by frequency):\n");
        foreach ($hook_counts as $hook_name => $count) {
            $this->log_to_file(sprintf("  %3d × %s", $count, $hook_name));
        }

        $this->log_to_file("\nTotal unique hooks: " . count($hook_counts));
        $this->log_to_file("Total hook executions: " . count($this->hooks_log));

        // Meta keys updated
        $meta_keys = [];
        foreach ($this->hooks_log as $entry) {
            if (strpos($entry['hook'], '_post_meta') !== false) {
                if (isset($entry['args'][2])) { // meta_key is usually 3rd arg
                    $meta_keys[] = $entry['args'][2];
                }
            }
        }

        if (!empty($meta_keys)) {
            $meta_keys = array_unique($meta_keys);
            $this->log_to_file("\nMeta keys affected: " . count($meta_keys));
            foreach ($meta_keys as $key) {
                $this->log_to_file("  - {$key}");
            }
        }

        $this->log_to_file("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    /**
     * Get log file path
     *
     * @return string Log file path
     */
    public function get_log_file_path() {
        return $this->log_file_path;
    }
}
