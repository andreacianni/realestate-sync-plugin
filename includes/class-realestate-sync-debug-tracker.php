<?php
/**
 * Debug Tracker - Unified Debugging System with Onion Logging
 *
 * Traces entire import flow from entry point to completion with configurable verbosity.
 * Provides structured logging with trace IDs, timestamps, and context.
 *
 * @package RealEstateSync
 * @version 1.0.0
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Debug_Tracker {

    /**
     * Log levels (Onion layers)
     */
    const LEVEL_CRITICAL = 0;  // Fatal errors only
    const LEVEL_ERROR = 1;     // Errors + Critical
    const LEVEL_WARNING = 2;   // Warnings + Error + Critical
    const LEVEL_INFO = 3;      // Info + Warning + Error + Critical
    const LEVEL_DEBUG = 4;     // Debug (queries, meta) + all above
    const LEVEL_TRACE = 5;     // Trace (stack, perf) + all above

    /**
     * Current log level (configured)
     */
    private $log_level = self::LEVEL_INFO;

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Current trace ID
     */
    private $trace_id = null;

    /**
     * Start timestamp
     */
    private $start_time = null;

    /**
     * Events log
     */
    private $events = array();

    /**
     * Context data
     */
    private $context = array();

    /**
     * Log file handle
     */
    private $log_file = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor (singleton)
     */
    private function __construct() {
        // Load log level from configuration
        $this->load_log_level();
    }

    /**
     * Load log level from configuration
     */
    private function load_log_level() {
        // Priority 1: wp-config.php constant
        if (defined('REALESTATE_SYNC_LOG_LEVEL')) {
            $this->log_level = REALESTATE_SYNC_LOG_LEVEL;
            return;
        }

        // Priority 2: Admin settings
        $settings = get_option('realestate_sync_settings', array());
        if (isset($settings['log_level'])) {
            $this->log_level = (int) $settings['log_level'];
            return;
        }

        // Priority 3: Default based on WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log_level = self::LEVEL_DEBUG;  // Debug mode
        } else {
            $this->log_level = self::LEVEL_WARNING;  // Production mode
        }
    }

    /**
     * Set log level programmatically
     *
     * @param int $level Log level constant
     */
    public function set_log_level($level) {
        $this->log_level = $level;
    }

    /**
     * Get current log level
     *
     * @return int
     */
    public function get_log_level() {
        return $this->log_level;
    }

    /**
     * Start new trace session
     *
     * @param string $entry_point Entry point identifier
     * @param array $context Initial context data
     * @return string Trace ID
     */
    public function start_trace($entry_point, $context = array()) {
        // Generate unique trace ID
        $this->trace_id = 'import_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
        $this->start_time = microtime(true);
        $this->context = array_merge(array(
            'entry_point' => $entry_point,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ), $context);

        // Open log file
        $log_dir = plugin_dir_path(dirname(__FILE__)) . 'logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_file_path = $log_dir . '/import-' . $this->trace_id . '.log';
        $this->log_file = fopen($log_file_path, 'a');

        // Log start event
        $this->log_event('START', 'SYSTEM', "Trace started from {$entry_point}", $this->context);

        return $this->trace_id;
    }

    /**
     * Log event (with level filtering)
     *
     * @param string $level Log level (CRITICAL, ERROR, WARNING, INFO, DEBUG, TRACE)
     * @param string $component Component name (ORCHESTRATOR, AGENCY_MANAGER, etc.)
     * @param string $message Log message
     * @param array $data Additional context data
     */
    public function log_event($level, $component, $message, $data = array()) {
        if (!$this->trace_id) {
            // No active trace, skip logging
            return;
        }

        // Convert string level to numeric
        $numeric_level = $this->string_to_level($level);

        // Check if this event should be logged based on current log level
        if ($numeric_level > $this->log_level) {
            // Event level is more verbose than configured level, skip
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $elapsed = microtime(true) - $this->start_time;

        $event = array(
            'trace_id' => $this->trace_id,
            'timestamp' => $timestamp,
            'elapsed' => round($elapsed, 3),
            'level' => $level,
            'component' => $component,
            'message' => $message,
            'data' => $data
        );

        $this->events[] = $event;

        // Format log line
        $log_line = sprintf(
            "[%s] [%s] [+%.3fs] [%s] [%s] %s",
            $this->trace_id,
            $timestamp,
            $elapsed,
            $level,
            $component,
            $message
        );

        // Add context data if present
        if (!empty($data)) {
            $log_line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $log_line .= "\n";

        // Write to file
        if ($this->log_file) {
            fwrite($this->log_file, $log_line);
        }

        // Also log to WordPress debug.log if WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($log_line);
        }
    }

    /**
     * Convert string level to numeric
     *
     * @param string $level String level (CRITICAL, ERROR, WARNING, INFO, DEBUG, TRACE, SUCCESS)
     * @return int Numeric level
     */
    private function string_to_level($level) {
        switch (strtoupper($level)) {
            case 'CRITICAL':
                return self::LEVEL_CRITICAL;
            case 'ERROR':
                return self::LEVEL_ERROR;
            case 'WARNING':
                return self::LEVEL_WARNING;
            case 'SUCCESS':
            case 'INFO':
                return self::LEVEL_INFO;
            case 'DEBUG':
                return self::LEVEL_DEBUG;
            case 'TRACE':
                return self::LEVEL_TRACE;
            case 'START':
            case 'END':
                return self::LEVEL_INFO;  // Always log start/end at INFO level
            default:
                return self::LEVEL_INFO;
        }
    }

    /**
     * Log database query (LEVEL_DEBUG)
     *
     * @param string $component Component executing query
     * @param array $args WP_Query arguments
     * @param array $results Query results
     */
    public function log_query($component, $args, $results) {
        // Only logged at DEBUG level or higher
        $this->log_event('DEBUG', $component, 'Database query executed', array(
            'query_args' => $args,
            'found_posts' => isset($results['found_posts']) ? $results['found_posts'] : 0,
            'post_ids' => isset($results['posts']) ? $results['posts'] : array()
        ));
    }

    /**
     * Log API call (LEVEL_INFO for success/error, LEVEL_DEBUG for details)
     *
     * @param string $component Component making API call
     * @param string $method HTTP method (POST, PUT, GET)
     * @param string $endpoint API endpoint
     * @param array $body Request body
     * @param array $response Response data
     */
    public function log_api_call($component, $method, $endpoint, $body, $response) {
        $success = isset($response['success']) ? $response['success'] : false;

        // Always log API calls at INFO level (summary)
        $this->log_event('INFO', $component, "API {$method} {$endpoint}", array(
            'success' => $success,
            'agency_id' => isset($response['agency_id']) ? $response['agency_id'] : null,
            'property_id' => isset($response['property_id']) ? $response['property_id'] : null
        ));

        // Log full details at DEBUG level
        $this->log_event('DEBUG', $component, "API {$method} {$endpoint} [FULL]", array(
            'request_body' => $body,
            'response' => $response
        ));
    }

    /**
     * Log meta field operation (LEVEL_DEBUG)
     *
     * @param string $component Component performing operation
     * @param string $operation Operation (save, search, delete)
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     * @param mixed $result Operation result
     */
    public function log_meta_operation($component, $operation, $post_id, $meta_key, $meta_value, $result = null) {
        // Only logged at DEBUG level
        $this->log_event('DEBUG', $component, "Meta {$operation}", array(
            'post_id' => $post_id,
            'meta_key' => $meta_key,
            'meta_value' => $meta_value,
            'result' => $result
        ));
    }

    /**
     * End trace session
     *
     * @param string $status Final status (completed, error, interrupted)
     * @param array $summary Summary statistics
     */
    public function end_trace($status, $summary = array()) {
        if (!$this->trace_id) {
            return;
        }

        $elapsed = microtime(true) - $this->start_time;

        $this->log_event('END', 'SYSTEM', "Trace ended with status: {$status}", array_merge(array(
            'total_elapsed' => round($elapsed, 3),
            'total_events' => count($this->events)
        ), $summary));

        // Close log file
        if ($this->log_file) {
            fclose($this->log_file);
            $this->log_file = null;
        }

        // Reset state
        $this->trace_id = null;
        $this->start_time = null;
        $this->events = array();
        $this->context = array();
    }

    /**
     * Get current trace ID
     *
     * @return string|null
     */
    public function get_trace_id() {
        return $this->trace_id;
    }

    /**
     * Get all events
     *
     * @return array
     */
    public function get_events() {
        return $this->events;
    }

    /**
     * Check if trace is active
     *
     * @return bool
     */
    public function is_active() {
        return $this->trace_id !== null;
    }
}
