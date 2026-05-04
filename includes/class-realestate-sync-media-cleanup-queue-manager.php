<?php
/**
 * Media Cleanup Queue Manager
 *
 * Minimal queue storage for the media cleanup infrastructure.
 *
 * @package RealEstate_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Media_Cleanup_Queue_Manager {

    /**
     * Base table name without WordPress prefix.
     */
    const TABLE_NAME = 'realestate_media_cleanup_queue';

    /**
     * Queue table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the queue table.
     *
     * @return void
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_attachment (session_id, attachment_id),
            KEY session_id (session_id),
            KEY status (status),
            KEY attachment_id (attachment_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Insert a queue item if it does not already exist for the session.
     *
     * @param string $session_id Session ID.
     * @param int    $attachment_id Attachment ID.
     * @return array{inserted:bool,duplicate:bool,item_id:int|false}
     */
    public function insert_item($session_id, $attachment_id) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'session_id' => $session_id,
                'attachment_id' => (int) $attachment_id,
                'status' => 'pending',
            ),
            array('%s', '%d', '%s')
        );

        if ($result === 1) {
            return array(
                'inserted' => true,
                'duplicate' => false,
                'item_id' => (int) $wpdb->insert_id,
            );
        }

        $last_error = (string) $wpdb->last_error;
        if ($last_error !== '' && (
            strpos($last_error, 'Duplicate entry') !== false ||
            strpos($last_error, '1062') !== false ||
            strpos(strtolower($last_error), 'duplicate') !== false
        )) {
            return array(
                'inserted' => false,
                'duplicate' => true,
                'item_id' => $this->get_item_id($session_id, $attachment_id),
            );
        }

        return array(
            'inserted' => false,
            'duplicate' => false,
            'item_id' => false,
        );
    }

    /**
     * Fetch pending attachment IDs.
     *
     * @param int $limit Maximum rows to return.
     * @return array<int, array{id:int,attachment_id:int}>
     */
    public function get_pending_items($limit) {
        global $wpdb;

        $limit = max(1, (int) $limit);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, attachment_id
             FROM {$this->table_name}
             WHERE status = %s
             ORDER BY id ASC
             LIMIT %d",
            'pending',
            $limit
        ), ARRAY_A);

        if (!is_array($rows)) {
            return array();
        }

        $items = array();
        foreach ($rows as $row) {
            $items[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'attachment_id' => isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0,
            );
        }

        return $items;
    }

    /**
     * Update a queue item's status.
     *
     * @param int    $item_id Item ID.
     * @param string $status New status.
     * @return bool
     */
    public function update_item_status($item_id, $status) {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table_name,
            array(
                'status' => (string) $status,
            ),
            array(
                'id' => (int) $item_id,
            ),
            array('%s'),
            array('%d')
        );

        return $updated !== false;
    }

    /**
     * Get queue totals.
     *
     * @return array
     */
    public function get_status_counts() {
        global $wpdb;

        $counts = array(
            'total' => 0,
            'pending' => 0,
        );

        $row = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count
             FROM {$this->table_name}"
        );

        if (!is_object($row)) {
            return $counts;
        }

        $counts['total'] = isset($row->total_count) ? (int) $row->total_count : 0;
        $counts['pending'] = isset($row->pending_count) ? (int) $row->pending_count : 0;

        return $counts;
    }

    /**
     * Reset the cleanup queue without touching attachments or posts.
     *
     * @return int|false Number of deleted rows or false on failure.
     */
    public function reset_queue() {
        global $wpdb;

        $result = $wpdb->query("DELETE FROM {$this->table_name}");

        return $result === false ? false : (int) $result;
    }

    /**
     * Resolve an item ID from session and attachment.
     *
     * @param string $session_id Session ID.
     * @param int    $attachment_id Attachment ID.
     * @return int|false
     */
    private function get_item_id($session_id, $attachment_id) {
        global $wpdb;

        $item_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name}
             WHERE session_id = %s
             AND attachment_id = %d
             LIMIT 1",
            $session_id,
            (int) $attachment_id
        ));

        return $item_id ? (int) $item_id : false;
    }
}
