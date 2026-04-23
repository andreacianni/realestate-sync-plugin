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
     * Initial threshold for recovering stale processing items.
     */
    const STALE_PROCESSING_THRESHOLD_SECONDS = 900;

    /**
     * Number of allowed recoveries back to pending before marking error.
     */
    const MAX_STALE_RECOVERY_ATTEMPTS = 1;

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
        $result = $this->insert_item($session_id, $property_import_id, $reason, $status);

        return $result['item_id'];
    }

    /**
     * Insert multiple delete queue items idempotently.
     *
     * @param string $session_id           Import session ID.
     * @param array  $property_import_ids  Property import IDs.
     * @param string $reason               Queue reason.
     * @param string $status               Queue status.
     * @return array
     */
    public function add_items($session_id, array $property_import_ids, $reason, $status = 'pending') {
        $stats = array(
            'total'    => 0,
            'inserted' => 0,
            'existing' => 0,
            'failed'   => 0,
            'item_ids' => array(),
        );

        $property_import_ids = array_values(array_unique(array_filter($property_import_ids, static function ($property_import_id) {
            return $property_import_id !== null && $property_import_id !== '';
        })));

        $stats['total'] = count($property_import_ids);

        foreach ($property_import_ids as $property_import_id) {
            $result = $this->insert_item($session_id, $property_import_id, $reason, $status);

            if ($result['item_id']) {
                $stats['item_ids'][] = $result['item_id'];
            }

            if ($result['inserted']) {
                $stats['inserted']++;
            } elseif ($result['existing']) {
                $stats['existing']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Insert a queue item and expose whether it was newly inserted.
     *
     * @param string $session_id         Import session ID.
     * @param string $property_import_id Property import ID.
     * @param string $reason             Queue reason.
     * @param string $status             Queue status.
     * @return array
     */
    private function insert_item($session_id, $property_import_id, $reason, $status = 'pending') {
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
            return array(
                'item_id'  => (int) $wpdb->insert_id,
                'inserted' => true,
                'existing' => false,
            );
        }

        if (strpos($wpdb->last_error, 'Duplicate entry') !== false) {
            return array(
                'item_id'  => $this->get_item_id_by_unique_key($session_id, $property_import_id, $reason),
                'inserted' => false,
                'existing' => true,
            );
        }

        return array(
            'item_id'  => false,
            'inserted' => false,
            'existing' => false,
        );
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
     * Claim the next pending item for a session and mark it processing.
     *
     * @param string $session_id Session ID.
     * @return object|null
     */
    public function claim_next_pending_item($session_id) {
        global $wpdb;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE session_id = %s
                 AND status = %s
                 ORDER BY id ASC
                 LIMIT 1",
                $session_id,
                'pending'
            ));

            if (!$item) {
                return null;
            }

            $updated = $wpdb->update(
                $this->table_name,
                array(
                    'status' => 'processing',
                    'updated_at' => current_time('mysql', true),
                ),
                array(
                    'id' => (int) $item->id,
                    'status' => 'pending',
                ),
                array('%s', '%s'),
                array('%d', '%s')
            );

            if ($updated === 1) {
                return $this->get_item((int) $item->id);
            }
        }

        return null;
    }

    /**
     * Update queue item status.
     *
     * @param int         $id              Queue item ID.
     * @param string      $status          New status.
     * @param string|null $expected_status Optional current status guard.
     * @param bool        $increment_attempts Whether to increment attempts.
     * @return bool
     */
    public function update_item_status($id, $status, $expected_status = null, $increment_attempts = false) {
        global $wpdb;

        $data = array(
            'status' => $status,
            'updated_at' => current_time('mysql', true),
        );
        $format = array('%s', '%s');

        if ($increment_attempts) {
            $item = $this->get_item($id);
            if (!$item) {
                return false;
            }

            $data['attempts'] = ((int) $item->attempts) + 1;
            $format[] = '%d';
        }

        $where = array('id' => (int) $id);
        $where_format = array('%d');

        if ($expected_status !== null) {
            $where['status'] = $expected_status;
            $where_format[] = '%s';
        }

        $updated = $wpdb->update(
            $this->table_name,
            $data,
            $where,
            $format,
            $where_format
        );

        return $updated === 1;
    }

    /**
     * Mark an item as done.
     *
     * @param int $id Queue item ID.
     * @return bool
     */
    public function mark_done($id) {
        return $this->update_item_status($id, 'done', 'processing');
    }

    /**
     * Mark an item as skipped.
     *
     * @param int $id Queue item ID.
     * @return bool
     */
    public function mark_skipped($id) {
        return $this->update_item_status($id, 'skipped', 'processing');
    }

    /**
     * Mark an item as error.
     *
     * @param int $id Queue item ID.
     * @return bool
     */
    public function mark_error($id) {
        return $this->update_item_status($id, 'error', 'processing', true);
    }

    /**
     * Get status counters for a session.
     *
     * @param string $session_id Session ID.
     * @return array
     */
    public function get_stats($session_id) {
        global $wpdb;

        $stats = array(
            'total'      => 0,
            'pending'    => 0,
            'processing' => 0,
            'done'       => 0,
            'error'      => 0,
            'skipped'    => 0,
        );

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS item_count
             FROM {$this->table_name}
             WHERE session_id = %s
             GROUP BY status",
            $session_id
        ));

        foreach ($rows as $row) {
            $status = (string) $row->status;
            $count = (int) $row->item_count;

            if (array_key_exists($status, $stats)) {
                $stats[$status] = $count;
            }

            $stats['total'] += $count;
        }

        return $stats;
    }

    /**
     * Recover stale processing items deterministically.
     *
     * Items older than the threshold are moved back to pending once. If they are
     * stale again after the allowed recovery count, they are marked as error.
     *
     * @param int      $threshold_seconds       Stale threshold in seconds.
     * @param int      $max_recovery_attempts   Allowed recoveries back to pending.
     * @param int|null $now_timestamp           Optional timestamp for deterministic tests.
     * @return array
     */
    public function recover_stale_processing_items($threshold_seconds = self::STALE_PROCESSING_THRESHOLD_SECONDS, $max_recovery_attempts = self::MAX_STALE_RECOVERY_ATTEMPTS, $now_timestamp = null) {
        global $wpdb;

        $threshold_seconds = max(1, (int) $threshold_seconds);
        $max_recovery_attempts = max(0, (int) $max_recovery_attempts);
        $now_timestamp = $now_timestamp === null ? time() : (int) $now_timestamp;
        $cutoff = gmdate('Y-m-d H:i:s', $now_timestamp - $threshold_seconds);

        $stats = array(
            'stale_found' => 0,
            'requeued'    => 0,
            'errored'     => 0,
            'threshold_seconds' => $threshold_seconds,
            'max_recovery_attempts' => $max_recovery_attempts,
            'cutoff' => $cutoff,
        );

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, attempts
             FROM {$this->table_name}
             WHERE status = %s
             AND updated_at < %s
             ORDER BY updated_at ASC, id ASC",
            'processing',
            $cutoff
        ));

        $stats['stale_found'] = count($items);

        foreach ($items as $item) {
            $new_attempts = ((int) $item->attempts) + 1;
            $new_status = ((int) $item->attempts) < $max_recovery_attempts ? 'pending' : 'error';

            $updated = $wpdb->update(
                $this->table_name,
                array(
                    'status' => $new_status,
                    'attempts' => $new_attempts,
                    'updated_at' => gmdate('Y-m-d H:i:s', $now_timestamp),
                ),
                array(
                    'id' => (int) $item->id,
                    'status' => 'processing',
                ),
                array('%s', '%d', '%s'),
                array('%d', '%s')
            );

            if ($updated) {
                if ($new_status === 'pending') {
                    $stats['requeued']++;
                } else {
                    $stats['errored']++;
                }
            }
        }

        error_log(
            '[DELETE-QUEUE] Stale processing recovery: found=' . $stats['stale_found']
            . ', requeued=' . $stats['requeued']
            . ', errored=' . $stats['errored']
            . ', threshold_seconds=' . $stats['threshold_seconds']
        );

        return $stats;
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
