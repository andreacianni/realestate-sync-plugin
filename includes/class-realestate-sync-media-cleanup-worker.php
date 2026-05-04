<?php
/**
 * Media Cleanup Worker
 *
 * Dry-run worker for the media cleanup queue.
 *
 * @package RealEstate_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Media_Cleanup_Worker {

    /**
     * Queue manager.
     *
     * @var RealEstate_Sync_Media_Cleanup_Queue_Manager
     */
    private $queue_manager;

    /**
     * Command helper.
     *
     * @var RealEstate_Sync_Media_Cleanup_Command
     */
    private $command;

    /**
     * Lock option name.
     */
    const LOCK_OPTION = 'realestate_sync_media_cleanup_worker_lock';

    /**
     * Lock TTL in seconds.
     */
    const LOCK_TTL = 60;

    /**
     * Constructor.
     *
     * @param RealEstate_Sync_Media_Cleanup_Queue_Manager|null $queue_manager Queue manager.
     * @param RealEstate_Sync_Media_Cleanup_Command|null        $command       Command helper.
     */
    public function __construct($queue_manager = null, $command = null) {
        $this->queue_manager = $queue_manager ?: new RealEstate_Sync_Media_Cleanup_Queue_Manager();
        $this->command = $command ?: new RealEstate_Sync_Media_Cleanup_Command();
    }

    /**
     * Run the worker in dry-run mode.
     *
     * @param array $options Run options.
     * @return array
     */
    public function run(array $options) {
        $stats = array(
            'processed' => 0,
            'would_delete' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'errors' => 0,
            'attachment_id' => 0,
        );

        if (!$this->acquire_lock()) {
            $this->cli_log('Worker lock active. Exiting.');
            return $stats;
        }

        $started_at = microtime(true);
        $max_runtime = isset($options['max_runtime_seconds']) ? max(1, (int) $options['max_runtime_seconds']) : 30;

        try {
            $this->command->build_global_sets();
            $items = $this->queue_manager->get_pending_items(isset($options['limit']) ? (int) $options['limit'] : 5);

            foreach ($items as $item) {
                if ((microtime(true) - $started_at) >= $max_runtime) {
                    $this->cli_log('Max runtime reached. Stopping.');
                    break;
                }

                $stats['processed']++;

                $attachment_id = isset($item['attachment_id']) ? (int) $item['attachment_id'] : 0;
                $stats['attachment_id'] = $attachment_id;

                try {
                    if (empty($options['execute'])) {
                        $evaluation = $this->command->evaluate_attachment($attachment_id);

                        if (is_array($evaluation) && ($evaluation['action'] ?? '') === 'dry_run') {
                            $stats['would_delete']++;
                            continue;
                        }

                        $stats['skipped']++;
                        continue;
                    }

                    $result = $this->command->execute_attachment($attachment_id);

                    if (is_array($result) && ($result['action'] ?? '') === 'deleted') {
                        $stats['deleted']++;
                        $this->queue_manager->update_item_status((int) $item['id'], 'done');
                        $this->cli_log('Attachment ID: ' . $attachment_id);
                        continue;
                    }

                    if (is_array($result) && ($result['action'] ?? '') === 'skipped') {
                        $stats['skipped']++;
                        $this->queue_manager->update_item_status((int) $item['id'], 'skipped');
                        $this->cli_log('Attachment ID: ' . $attachment_id);
                        continue;
                    }

                    $stats['errors']++;
                    $this->queue_manager->update_item_status((int) $item['id'], 'error');
                    $this->cli_log('Attachment ID: ' . $attachment_id);
                } catch (Exception $e) {
                    $stats['errors']++;
                    $this->queue_manager->update_item_status((int) $item['id'], 'error');
                    $this->cli_log('Attachment ID: ' . $attachment_id);
                }
            }
        } catch (Exception $e) {
            $stats['errors']++;
        } finally {
            $this->release_lock();
        }

        return $stats;
    }

    /**
     * Acquire the worker lock.
     *
     * @return bool
     */
    private function acquire_lock() {
        $lock = get_option(self::LOCK_OPTION, array());
        $now = time();

        if (is_array($lock) && !empty($lock['expires_at']) && (int) $lock['expires_at'] > $now) {
            return false;
        }

        update_option(self::LOCK_OPTION, array(
            'token' => wp_generate_uuid4(),
            'expires_at' => $now + self::LOCK_TTL,
        ), false);

        return true;
    }

    /**
     * Release the worker lock.
     *
     * @return void
     */
    private function release_lock() {
        delete_option(self::LOCK_OPTION);
    }

    /**
     * Log a CLI line if WP-CLI is available.
     *
     * @param string $message Message.
     * @return void
     */
    private function cli_log($message) {
        if (class_exists('WP_CLI')) {
            WP_CLI::log($message);
            return;
        }

        echo $message . PHP_EOL;
    }
}
