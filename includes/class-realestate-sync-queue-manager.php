<?php
/**
 * Queue Manager Class
 *
 * Handles database operations for the import queue table.
 * NO IMPORT LOGIC - only queue management.
 *
 * @package RealEstate_Sync
 * @version 1.5.0
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Queue_Manager {

    /**
     * Maximum retry attempts per item
     */
    const MAX_RETRIES = 3;

    /**
     * Queue table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'realestate_import_queue';
    }

    /**
     * Create queue table if it doesn't exist
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
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
    }

    /**
     * Clear queue for session
     *
     * @param string $session_id Session ID
     */
    public function clear_session_queue($session_id) {
        global $wpdb;

        $wpdb->delete(
            $this->table_name,
            array('session_id' => $session_id),
            array('%s')
        );
    }

    /**
     * Add agency to queue
     *
     * @param string $session_id Session ID
     * @param string $agency_id  Agency ID from XML
     * @return int|false Insert ID or false on failure
     */
    public function add_agency($session_id, $agency_id) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'session_id' => $session_id,
                'item_type'  => 'agency',
                'item_id'    => $agency_id,
                'status'     => 'pending'
            ),
            array('%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Add property to queue
     *
     * @param string $session_id  Session ID
     * @param string $property_id Property ID from XML
     * @return int|false Insert ID or false on failure
     */
    public function add_property($session_id, $property_id) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'session_id' => $session_id,
                'item_type'  => 'property',
                'item_id'    => $property_id,
                'status'     => 'pending'
            ),
            array('%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get next batch of pending items
     *
     * @param string $session_id Session ID
     * @param int    $limit      Number of items to retrieve
     * @return array Array of queue items
     */
    public function get_next_batch($session_id, $limit = 10) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE session_id = %s
             AND status = 'pending'
             ORDER BY id ASC
             LIMIT %d",
            $session_id,
            $limit
        ));
    }

    /**
     * Update item status
     *
     * @param int    $id           Queue item ID
     * @param string $status       New status
     * @param string $error_msg    Error message (optional)
     * @return bool Success
     */
    public function update_item_status($id, $status, $error_msg = null) {
        global $wpdb;

        $data = array('status' => $status);
        $format = array('%s');

        if ($error_msg !== null) {
            $data['error_message'] = $error_msg;
            $format[] = '%s';
        }

        if ($status === 'error') {
            // Increment retry count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name}
                 SET retry_count = retry_count + 1
                 WHERE id = %d",
                $id
            ));
        }

        return $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }

    /**
     * Mark item as done
     *
     * @param int $id Queue item ID
     * @return bool Success
     */
    public function mark_done($id) {
        return $this->update_item_status($id, 'done');
    }

    /**
     * Mark item as processing
     *
     * @param int $id Queue item ID
     * @return bool Success
     */
    public function mark_processing($id) {
        return $this->update_item_status($id, 'processing');
    }

    /**
     * Mark item as error
     *
     * @param int    $id        Queue item ID
     * @param string $error_msg Error message
     * @return bool Success
     */
    public function mark_error($id, $error_msg) {
        return $this->update_item_status($id, 'error', $error_msg);
    }

    /**
     * Update wp_post_id for queue item
     *
     * 🔧 FIX PRIORITÀ 3: Update wp_post_id immediately after post creation
     * This ensures wp_post_id is saved even if batch fails/timeouts later
     *
     * @param int $id         Queue item ID
     * @param int $wp_post_id WordPress Post ID
     * @return bool Success
     */
    public function update_wp_post_id($id, $wp_post_id) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            array('wp_post_id' => $wp_post_id),
            array('id' => $id),
            array('%d'),  // wp_post_id is bigint
            array('%d')   // id is int
        );

        return $result !== false;
    }

    /**
     * Get session statistics
     *
     * @param string $session_id Session ID
     * @return array Stats by status
     */
    public function get_session_stats($session_id) {
        global $wpdb;

        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count
             FROM {$this->table_name}
             WHERE session_id = %s
             GROUP BY status",
            $session_id
        ), ARRAY_A);

        $result = array(
            'pending'    => 0,
            'processing' => 0,
            'done'       => 0,
            'error'      => 0,
            'total'      => 0
        );

        foreach ($stats as $stat) {
            $result[$stat['status']] = (int)$stat['count'];
            $result['total'] += (int)$stat['count'];
        }

        return $result;
    }

    /**
     * Check if session is complete
     *
     * @param string $session_id Session ID
     * @return bool True if all items processed
     */
    public function is_session_complete($session_id) {
        global $wpdb;

        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE session_id = %s
             AND status = 'pending'",
            $session_id
        ));

        return (int)$pending === 0;
    }

    /**
     * Get items by status
     *
     * @param string $session_id Session ID
     * @param string $status     Status to filter
     * @return array Queue items
     */
    public function get_items_by_status($session_id, $status) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE session_id = %s
             AND status = %s
             ORDER BY id ASC",
            $session_id,
            $status
        ));
    }

    /**
     * Get retry successes (items that succeeded after retry)
     *
     * @param string $session_id Session ID
     * @return array Items that succeeded after retry
     */
    public function get_retry_successes($session_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE session_id = %s
             AND status = 'done'
             AND retry_count > 0
             ORDER BY id ASC",
            $session_id
        ));
    }

    /**
     * Fetch single queue item by ID
     *
     * @param int $id Queue item ID
     * @return object|null Queue item or null if not found
     */
    public function get_item($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }
}
