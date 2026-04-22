<?php
/**
 * Delete Queue Manager Class
 *
 * Handles database operations for the delete queue table.
 * No detection or deletion logic is executed here.
 *
 * @package RealEstate_Sync
 * @since 1.5.6
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Delete_Queue_Manager {

    /**
     * Base table name without WordPress prefix.
     */
    const TABLE_NAME = 'realestate_delete_queue';

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
     * Create delete queue table if it does not exist.
     *
     * @return void
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            property_import_id varchar(100) NOT NULL,
            reason varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_property_reason (session_id, property_import_id, reason),
            KEY session_id (session_id),
            KEY status (status),
            KEY property_import_id (property_import_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Insert a single delete queue item.
     *
     * The unique key makes this idempotent per session/property/reason.
     *
     * @param string $session_id         Import session ID.
     * @param string $property_import_id Property import ID.
     * @param string $reason             Queue reason.
     * @param string $status             Queue status.
     * @return int|false Inserted or existing row ID, false on failure.
     */
    public function add_item($session_id, $property_import_id, $reason, $status = 'pending') {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'session_id' => $session_id,
                'property_import_id' => $property_import_id,
                'reason' => $reason,
                'status' => $status,
                'attempts' => 0,
            ),
            array('%s', '%s', '%s', '%s', '%d')
        );

        if ($result) {
            return (int) $wpdb->insert_id;
        }

        if (strpos($wpdb->last_error, 'Duplicate entry') !== false) {
            return $this->get_item_id_by_unique_key($session_id, $property_import_id, $reason);
        }

        return false;
    }

    /**
     * Fetch queue items for a session.
     *
     * @param string      $session_id Session ID.
     * @param string|null $status     Optional status filter.
     * @param int         $limit      Maximum rows to fetch.
     * @return array
     */
    public function get_items($session_id, $status = null, $limit = 50) {
        global $wpdb;

        $limit = max(1, (int) $limit);

        if ($status === null) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE session_id = %s
                 ORDER BY id ASC
                 LIMIT %d",
                $session_id,
                $limit
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE session_id = %s
             AND status = %s
             ORDER BY id ASC
             LIMIT %d",
            $session_id,
            $status,
            $limit
        ));
    }

    /**
     * Fetch a single queue item by ID.
     *
     * @param int $id Queue item ID.
     * @return object|null
     */
    public function get_item($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }

    /**
     * Resolve an existing item ID from the unique key.
     *
     * @param string $session_id         Session ID.
     * @param string $property_import_id Property import ID.
     * @param string $reason             Queue reason.
     * @return int|false
     */
    private function get_item_id_by_unique_key($session_id, $property_import_id, $reason) {
        global $wpdb;

        $item_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name}
             WHERE session_id = %s
             AND property_import_id = %s
             AND reason = %s
             LIMIT 1",
            $session_id,
            $property_import_id,
            $reason
        ));

        return $item_id ? (int) $item_id : false;
    }
}
