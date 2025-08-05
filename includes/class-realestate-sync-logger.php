<?php
/**
 * RealEstate Sync Logger Class
 *
 * Handles comprehensive logging for the plugin with different severity levels,
 * automatic cleanup, and admin interface integration.
 *
 * @package RealEstateSync
 * @subpackage Core
 * @since 0.9.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RealEstate Sync Logger Class
 *
 * Provides comprehensive logging functionality with multiple severity levels,
 * file rotation, and admin interface integration.
 *
 * @since 0.9.0
 */
class RealEstate_Sync_Logger {
    
    /**
     * Log directory path
     *
     * @var string
     */
    private $log_dir;
    
    /**
     * Current log file path
     *
     * @var string
     */
    private $log_file;
    
    /**
     * Log levels
     *
     * @var array
     */
    private $log_levels = [
        'error'   => 0,
        'warning' => 1,
        'info'    => 2,
        'debug'   => 3
    ];
    
    /**
     * Maximum log file size in bytes (5MB)
     *
     * @var int
     */
    private $max_file_size = 5242880;
    
    /**
     * Maximum number of log files to keep
     *
     * @var int
     */
    private $max_files = 10;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setup_log_directory();
        $this->setup_log_file();
    }
    
    /**
     * Setup log directory
     */
    private function setup_log_directory() {
        $this->log_dir = REALESTATE_SYNC_PLUGIN_DIR . 'logs/import-logs/';
        
        // Create directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
        
        // Create .htaccess for security
        $htaccess_file = $this->log_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Order deny,allow\nDeny from all\n");
        }
        
        // Create index.php for security
        $index_file = $this->log_dir . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }
    
    /**
     * Setup current log file
     */
    private function setup_log_file() {
        $date = date('Y-m-d');
        $this->log_file = $this->log_dir . "realestate-sync-{$date}.log";
        
        // Rotate log file if it's too large
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_file_size) {
            $this->rotate_log_file();
        }
    }
    
    /**
     * Rotate log file when it gets too large
     */
    private function rotate_log_file() {
        $date = date('Y-m-d');
        $time = date('H-i-s');
        $rotated_file = $this->log_dir . "realestate-sync-{$date}-{$time}.log";
        
        // Rename current log file
        if (file_exists($this->log_file)) {
            rename($this->log_file, $rotated_file);
        }
        
        // Clean up old log files
        $this->cleanup_old_logs();
    }
    
    /**
     * Log a message
     *
     * @param string $message Log message
     * @param string $level Log level (error, warning, info, debug)
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function log($message, $level = 'info', $context = []) {
        // Validate log level
        if (!isset($this->log_levels[$level])) {
            $level = 'info';
        }
        
        // Check if we should log this level
        $min_level = get_option('realestate_sync_log_level', 'info');
        if ($this->log_levels[$level] > $this->log_levels[$min_level]) {
            return true; // Skip logging based on level
        }
        
        // Prepare log entry
        $timestamp = date('Y-m-d H:i:s');
        $memory_usage = $this->format_bytes(memory_get_usage(true));
        $memory_peak = $this->format_bytes(memory_get_peak_usage(true));
        
        // Format message
        $log_entry = sprintf(
            "[%s] [%s] [MEM: %s/%s] %s",
            $timestamp,
            strtoupper($level),
            $memory_usage,
            $memory_peak,
            $message
        );
        
        // Add context if provided
        if (!empty($context)) {
            $log_entry .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Add stack trace for errors
        if ($level === 'error') {
            $log_entry .= "\n" . $this->get_stack_trace();
        }
        
        $log_entry .= "\n";
        
        // Write to log file
        $result = file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Also log to WordPress debug log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("RealEstate Sync [{$level}]: {$message}");
        }
        
        // Store last error in options for quick access
        if ($level === 'error') {
            update_option('realestate_sync_last_error', [
                'message' => $message,
                'timestamp' => $timestamp,
                'context' => $context
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Log error message
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @return bool Success status
     */
    public function error($message, $context = []) {
        return $this->log($message, 'error', $context);
    }
    
    /**
     * Log warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context
     * @return bool Success status
     */
    public function warning($message, $context = []) {
        return $this->log($message, 'warning', $context);
    }
    
    /**
     * Log info message
     *
     * @param string $message Info message
     * @param array $context Additional context
     * @return bool Success status
     */
    public function info($message, $context = []) {
        return $this->log($message, 'info', $context);
    }
    
    /**
     * Log debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context
     * @return bool Success status
     */
    public function debug($message, $context = []) {
        return $this->log($message, 'debug', $context);
    }
    
    /**
     * Log import session start
     *
     * @param bool $is_manual Whether this is a manual import
     * @return string Session ID
     */
    public function log_import_start($is_manual = false) {
        $session_id = uniqid('import_', true);
        $type = $is_manual ? 'MANUAL' : 'SCHEDULED';
        
        $this->log("=== IMPORT SESSION START [{$type}] ===", 'info', [
            'session_id' => $session_id,
            'is_manual' => $is_manual,
            'user_id' => get_current_user_id(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);
        
        return $session_id;
    }
    
    /**
     * Log import session end
     *
     * @param string $session_id Session ID
     * @param array $results Import results
     */
    public function log_import_end($session_id, $results = []) {
        $this->log("=== IMPORT SESSION END ===", 'info', array_merge([
            'session_id' => $session_id,
            'duration' => $results['duration'] ?? 0,
            'processed' => $results['processed'] ?? 0,
            'imported' => $results['imported'] ?? 0,
            'updated' => $results['updated'] ?? 0,
            'errors' => $results['errors'] ?? 0
        ], $results));
    }
    
    /**
     * Get recent log entries
     *
     * @param int $limit Number of entries to retrieve
     * @param string $level Minimum log level
     * @return array Log entries
     */
    public function get_recent_logs($limit = 100, $level = 'info') {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $logs = [];
        $file = new SplFileObject($this->log_file);
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key() + 1;
        
        // Start from the end and work backwards
        $start_line = max(0, $total_lines - $limit * 2); // Get more lines to filter by level
        $file->seek($start_line);
        
        $min_level_value = $this->log_levels[$level] ?? 2;
        
        while (!$file->eof() && count($logs) < $limit) {
            $line = trim($file->fgets());
            if (empty($line)) {
                continue;
            }
            
            $parsed = $this->parse_log_line($line);
            if ($parsed && $this->log_levels[$parsed['level']] <= $min_level_value) {
                $logs[] = $parsed;
            }
        }
        
        return array_reverse(array_slice($logs, -$limit));
    }
    
    /**
     * Parse a log line into components
     *
     * @param string $line Log line
     * @return array|null Parsed components
     */
    private function parse_log_line($line) {
        // Pattern: [timestamp] [LEVEL] [MEM: usage/peak] message
        $pattern = '/^\[([^\]]+)\] \[([^\]]+)\] \[MEM: ([^\]]+)\] (.+)$/';
        
        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => strtolower($matches[2]),
                'memory' => $matches[3],
                'message' => $matches[4]
            ];
        }
        
        return null;
    }
    
    /**
     * Get log statistics
     *
     * @return array Statistics
     */
    public function get_log_statistics() {
        $stats = [
            'total_entries' => 0,
            'by_level' => array_fill_keys(array_keys($this->log_levels), 0),
            'file_size' => 0,
            'last_entry' => null
        ];
        
        if (!file_exists($this->log_file)) {
            return $stats;
        }
        
        $stats['file_size'] = filesize($this->log_file);
        
        $file = new SplFileObject($this->log_file);
        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (empty($line)) {
                continue;
            }
            
            $parsed = $this->parse_log_line($line);
            if ($parsed) {
                $stats['total_entries']++;
                $stats['by_level'][$parsed['level']]++;
                $stats['last_entry'] = $parsed;
            }
        }
        
        return $stats;
    }
    
    /**
     * Clear all log files
     *
     * @return bool Success status
     */
    public function clear_logs() {
        $cleared = 0;
        $files = glob($this->log_dir . '*.log');
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }
        
        $this->log("Cleared {$cleared} log files", 'info');
        
        return $cleared > 0;
    }
    
    /**
     * Clear logs by type
     *
     * @param string $type Log type (success, error, all)
     * @return bool Success status
     */
    public function clear_logs_by_type($type = 'all') {
        $files = glob($this->log_dir . '*.log');
        $cleared = 0;
        
        foreach ($files as $file) {
            if ($type === 'all') {
                if (unlink($file)) {
                    $cleared++;
                }
            } else {
                // For specific types, we need to filter content
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                $filtered_lines = [];
                
                foreach ($lines as $line) {
                    $parsed = $this->parse_log_line($line);
                    
                    if ($type === 'success') {
                        // Keep errors and warnings, remove info/debug
                        if ($parsed && in_array($parsed['level'], ['error', 'warning'])) {
                            $filtered_lines[] = $line;
                        } elseif (!$parsed && !empty(trim($line))) {
                            $filtered_lines[] = $line; // Keep non-log lines (stack traces, etc.)
                        }
                    } elseif ($type === 'error') {
                        // Keep only success logs (info/debug)
                        if ($parsed && in_array($parsed['level'], ['info', 'debug'])) {
                            $filtered_lines[] = $line;
                        }
                    }
                }
                
                if (count($filtered_lines) < count($lines)) {
                    file_put_contents($file, implode("\n", $filtered_lines));
                    $cleared++;
                }
            }
        }
        
        $this->log("Cleared {$type} logs from {$cleared} files", 'info');
        return $cleared > 0;
    }
    
    /**
     * Cleanup old log files
     */
    private function cleanup_old_logs() {
        $files = glob($this->log_dir . '*.log');
        
        if (count($files) <= $this->max_files) {
            return;
        }
        
        // Sort files by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Remove oldest files
        $files_to_remove = array_slice($files, 0, count($files) - $this->max_files);
        
        foreach ($files_to_remove as $file) {
            unlink($file);
        }
        
        $this->log("Cleaned up " . count($files_to_remove) . " old log files", 'info');
    }
    
    /**
     * Get stack trace for error logging
     *
     * @return string Formatted stack trace
     */
    private function get_stack_trace() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $stack = "Stack trace:\n";
        
        foreach ($trace as $i => $call) {
            if (isset($call['file']) && isset($call['line'])) {
                $file = str_replace(ABSPATH, '', $call['file']);
                $function = $call['function'] ?? 'unknown';
                $class = isset($call['class']) ? $call['class'] . '::' : '';
                
                $stack .= "  #{$i} {$file}({$call['line']}): {$class}{$function}()\n";
            }
        }
        
        return $stack;
    }
    
    /**
     * Format bytes to human readable format
     *
     * @param int $bytes Bytes value
     * @return string Formatted string
     */
    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get log files list for admin interface
     *
     * @return array List of log files with metadata
     */
    public function get_log_files() {
        $files = glob($this->log_dir . '*.log');
        $log_files = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            $log_files[] = [
                'name' => $filename,
                'path' => $file,
                'size' => filesize($file),
                'size_formatted' => $this->format_bytes(filesize($file)),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'is_current' => $file === $this->log_file
            ];
        }
        
        // Sort by modification time (newest first)
        usort($log_files, function($a, $b) {
            return strcmp($b['modified'], $a['modified']);
        });
        
        return $log_files;
    }
    
    /**
     * Get log file content for admin viewing
     *
     * @param string $filename Log file name
     * @param int $lines Number of lines to read (from end)
     * @return string|false File content or false on error
     */
    public function get_log_file_content($filename, $lines = 1000) {
        $file_path = $this->log_dir . $filename;
        
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }
        
        // Read last N lines efficiently
        $file = new SplFileObject($file_path);
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key() + 1;
        
        $start_line = max(0, $total_lines - $lines);
        $file->seek($start_line);
        
        $content = '';
        while (!$file->eof()) {
            $content .= $file->fgets();
        }
        
        return $content;
    }
    
    /**
     * Export logs as downloadable file
     *
     * @param string $filename Log file name
     * @return bool Success status
     */
    public function export_log_file($filename) {
        $file_path = $this->log_dir . $filename;
        
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }
        
        // Set headers for download
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        
        // Output file content
        readfile($file_path);
        
        return true;
    }
    
    /**
     * Test logging functionality
     *
     * @return array Test results
     */
    public function test_logging() {
        $results = [
            'directory_writable' => is_writable($this->log_dir),
            'log_file_writable' => is_writable($this->log_file) || is_writable($this->log_dir),
            'test_write' => false,
            'test_read' => false
        ];
        
        // Test write
        $test_message = 'Logger test - ' . date('Y-m-d H:i:s');
        $results['test_write'] = $this->log($test_message, 'debug', ['test' => true]);
        
        // Test read
        if ($results['test_write']) {
            $recent = $this->get_recent_logs(1, 'debug');
            $results['test_read'] = !empty($recent) && strpos($recent[0]['message'], 'Logger test') !== false;
        }
        
        return $results;
    }
}
