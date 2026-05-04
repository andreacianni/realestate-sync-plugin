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

        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS count_items
             FROM {$this->table_name}
             GROUP BY status"
        );

        if (!is_array($rows)) {
            return $counts;
        }

        foreach ($rows as $row) {
            $count = isset($row->count_items) ? (int) $row->count_items : 0;
            $status = isset($row->status) ? (string) $row->status : '';

            $counts['total'] += $count;

            if ($status === 'pending') {
                $counts['pending'] = $count;
            }
        }

        return $counts;
    }
}
