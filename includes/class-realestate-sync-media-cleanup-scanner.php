<?php
/**
 * Media Cleanup Scanner
 *
 * Reuses the existing media cleanup command evaluation logic to find candidates
 * and optionally persist them to the cleanup queue.
 *
 * @package RealEstate_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Media_Cleanup_Scanner {

    /**
     * Cleanup command helper.
     *
     * @var RealEstate_Sync_Media_Cleanup_Command
     */
    private $command;

    /**
     * Queue manager.
     *
     * @var RealEstate_Sync_Media_Cleanup_Queue_Manager
     */
    private $queue_manager;

    /**
     * Constructor.
     *
     * @param RealEstate_Sync_Media_Cleanup_Command|null      $command       Command helper.
     * @param RealEstate_Sync_Media_Cleanup_Queue_Manager|null $queue_manager Queue manager.
     */
    public function __construct($command = null, $queue_manager = null) {
        $this->command = $command ?: new RealEstate_Sync_Media_Cleanup_Command();
        $this->queue_manager = $queue_manager ?: new RealEstate_Sync_Media_Cleanup_Queue_Manager();
    }

    /**
     * Run the scanner.
     *
     * @param array $assoc_args CLI args.
     * @return array
     */
    public function scan($assoc_args) {
        $options = $this->command->prepare_scan_options($assoc_args);

        $this->command->build_global_sets();
        $attachment_ids = $this->resolve_scope_attachment_ids($options);

        $stats = array(
            'candidates_found' => 0,
            'inserted' => 0,
            'duplicates' => 0,
            'excluded' => 0,
            'errors' => 0,
        );

        $session_id = !empty($options['session_id']) ? (string) $options['session_id'] : 'scan';

        foreach ($attachment_ids as $attachment_id) {
            $result = $this->command->evaluate_attachment((int) $attachment_id);

            if (!is_array($result) || ($result['action'] ?? '') !== 'dry_run') {
                if (is_array($result) && in_array(($result['reason'] ?? ''), array('parent_missing', 'parent_not_found', 'parent_missing_property_import_id'), true)) {
                    $stats['excluded']++;
                } elseif (is_array($result) && ($result['action'] ?? '') === 'error') {
                    $stats['errors']++;
                } else {
                    $stats['excluded']++;
                }
                continue;
            }

            $stats['candidates_found']++;

            if (empty($options['execute'])) {
                continue;
            }

            $insert = $this->queue_manager->insert_item($session_id, (int) $attachment_id);

            if (!empty($insert['duplicate'])) {
                $stats['duplicates']++;
            } elseif (!empty($insert['inserted'])) {
                $stats['inserted']++;
            } else {
                $stats['excluded']++;
            }
        }

        $this->emit_summary($stats, !empty($options['execute']));

        return $stats;
    }

    /**
     * Emit a short summary.
     *
     * @param array $stats Stats.
     * @param bool  $execute Execute mode.
     * @return void
     */
    private function emit_summary(array $stats, $execute) {
        if (class_exists('WP_CLI')) {
            WP_CLI::log('Candidates found: ' . (int) $stats['candidates_found']);
            WP_CLI::log('Inserted: ' . (int) $stats['inserted']);
            WP_CLI::log('Duplicates: ' . (int) $stats['duplicates']);
            WP_CLI::log('Excluded / Out of scope: ' . (int) $stats['excluded']);
            WP_CLI::log('Errors: ' . (int) $stats['errors']);
            if (!$execute) {
                WP_CLI::log('Mode: dry-run');
            }
            return;
        }

        echo 'Candidates found: ' . (int) $stats['candidates_found'] . PHP_EOL;
        echo 'Inserted: ' . (int) $stats['inserted'] . PHP_EOL;
        echo 'Duplicates: ' . (int) $stats['duplicates'] . PHP_EOL;
        echo 'Excluded / Out of scope: ' . (int) $stats['excluded'] . PHP_EOL;
        echo 'Errors: ' . (int) $stats['errors'] . PHP_EOL;
    }

    /**
     * Resolve scoped attachment IDs.
     *
     * @param array $options Scan options.
     * @return array<int>
     */
    private function resolve_scope_attachment_ids(array $options) {
        if (!empty($options['attachment_id'])) {
            return array((int) $options['attachment_id']);
        }

        global $wpdb;

        $sql = "SELECT a.ID
                FROM {$wpdb->posts} a
                INNER JOIN {$wpdb->posts} p ON p.ID = a.post_parent
                INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'property_import_id' AND pm.meta_value <> ''
                WHERE a.post_type = 'attachment'
                  AND a.post_mime_type LIKE 'image/%'
                  AND a.post_parent > 0
                  AND p.post_type = 'estate_property'";
        $params = array();

        if (!empty($options['post_id'])) {
            $sql .= ' AND a.post_parent = %d';
            $params[] = (int) $options['post_id'];
        }

        if (!empty($options['after_id'])) {
            $sql .= ' AND a.ID > %d';
            $params[] = (int) $options['after_id'];
        }

        $sql .= ' GROUP BY a.ID ORDER BY a.ID ASC LIMIT %d OFFSET %d';
        $params[] = (int) $options['limit'];
        $params[] = (int) $options['offset'];

        $prepared = $wpdb->prepare($sql, $params);
        $ids = $wpdb->get_col($prepared);

        if ($ids === false) {
            throw new RuntimeException('Failed to resolve scoped candidate attachments.');
        }

        return array_map('intval', $ids);
    }
}
