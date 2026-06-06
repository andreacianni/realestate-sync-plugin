<?php
/**
 * WP-CLI gallery pending command.
 *
 * Read-only listing of properties with pending gallery changes.
 *
 * @package RealEstate_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Gallery_Pending_Command {

    /**
     * Execute command.
     *
     * @param array $args Positional args.
     * @param array $assoc_args Associative args.
     * @return void
     */
    public function __invoke($args, $assoc_args) {
        $options = $this->parse_options($assoc_args);
        $rows = $this->fetch_pending_rows($options['limit']);

        if (empty($rows)) {
            $this->cli_log('No gallery pending items found');
            return;
        }

        $format = $options['format'];
        $fields = array(
            'post_id',
            'property_import_id',
            'property_gallery_signature',
            'property_gallery_signature_pending',
            'property_gallery_changed_pending',
            'post_modified',
        );

        \WP_CLI\Utils\format_items($format, $rows, $fields);
    }

    /**
     * Parse options.
     *
     * @param array $assoc_args Associative args.
     * @return array
     */
    private function parse_options($assoc_args) {
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 50;
        if ($limit < 1) {
            $limit = 50;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $format = isset($assoc_args['format']) ? strtolower((string) $assoc_args['format']) : 'table';
        if (!in_array($format, array('table', 'csv', 'json'), true)) {
            $format = 'table';
        }

        return array(
            'limit' => $limit,
            'format' => $format,
        );
    }

    /**
     * Fetch pending rows.
     *
     * @param int $limit Max rows.
     * @return array<int, array<string, mixed>>
     */
    private function fetch_pending_rows($limit) {
        global $wpdb;

        $sql = "
            SELECT
                p.ID AS post_id,
                COALESCE(pm_import.meta_value, '') AS property_import_id,
                COALESCE(pm_signature.meta_value, '') AS property_gallery_signature,
                COALESCE(pm_pending.meta_value, '') AS property_gallery_signature_pending,
                COALESCE(pm_changed.meta_value, '0') AS property_gallery_changed_pending,
                p.post_modified AS post_modified
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_changed
                ON pm_changed.post_id = p.ID
               AND pm_changed.meta_key = 'property_gallery_changed_pending'
               AND pm_changed.meta_value = '1'
            LEFT JOIN {$wpdb->postmeta} pm_import
                ON pm_import.post_id = p.ID
               AND pm_import.meta_key = 'property_import_id'
            LEFT JOIN {$wpdb->postmeta} pm_signature
                ON pm_signature.post_id = p.ID
               AND pm_signature.meta_key = 'property_gallery_signature'
            LEFT JOIN {$wpdb->postmeta} pm_pending
                ON pm_pending.post_id = p.ID
               AND pm_pending.meta_key = 'property_gallery_signature_pending'
            WHERE p.post_type = 'estate_property'
              AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
            ORDER BY p.post_modified DESC, p.ID DESC
            LIMIT %d
        ";

        $prepared = $wpdb->prepare($sql, array((int) $limit));
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        if (!is_array($rows)) {
            return array();
        }

        return array_map(array($this, 'normalize_row'), $rows);
    }

    /**
     * Normalize row values.
     *
     * @param array $row Raw row.
     * @return array
     */
    private function normalize_row($row) {
        return array(
            'post_id' => isset($row['post_id']) ? (int) $row['post_id'] : 0,
            'property_import_id' => (string) ($row['property_import_id'] ?? ''),
            'property_gallery_signature' => (string) ($row['property_gallery_signature'] ?? ''),
            'property_gallery_signature_pending' => (string) ($row['property_gallery_signature_pending'] ?? ''),
            'property_gallery_changed_pending' => isset($row['property_gallery_changed_pending']) ? (int) $row['property_gallery_changed_pending'] : 0,
            'post_modified' => (string) ($row['post_modified'] ?? ''),
        );
    }

    /**
     * CLI log helper.
     *
     * @param string $message Message.
     * @return void
     */
    private function cli_log($message) {
        if (class_exists('WP_CLI')) {
            \WP_CLI::log($message);
            return;
        }

        echo $message . PHP_EOL;
    }
}
