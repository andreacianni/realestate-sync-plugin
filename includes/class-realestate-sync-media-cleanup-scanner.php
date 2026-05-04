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
        $attachment_ids = $this->command->resolve_target_attachments();

        $stats = array(
            'candidates_found' => 0,
            'inserted' => 0,
            'duplicates' => 0,
            'excluded' => 0,
        );

        $session_id = !empty($options['session_id']) ? (string) $options['session_id'] : 'scan';

        foreach ($attachment_ids as $attachment_id) {
            $result = $this->command->evaluate_attachment((int) $attachment_id);

            if (!is_array($result) || ($result['action'] ?? '') !== 'dry_run') {
                $stats['excluded']++;
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
            WP_CLI::log('Excluded: ' . (int) $stats['excluded']);
            if (!$execute) {
                WP_CLI::log('Mode: dry-run');
            }
            return;
        }

        echo 'Candidates found: ' . (int) $stats['candidates_found'] . PHP_EOL;
        echo 'Inserted: ' . (int) $stats['inserted'] . PHP_EOL;
        echo 'Duplicates: ' . (int) $stats['duplicates'] . PHP_EOL;
        echo 'Excluded: ' . (int) $stats['excluded'] . PHP_EOL;
    }
}
