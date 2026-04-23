<?php
/**
 * Delete Batch Processor Class
 *
 * Processes one delete queue item at a time in isolation.
 *
 * @package RealEstate_Sync
 * @since 1.5.6
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Delete_Batch_Processor {

    /**
     * Delete queue manager.
     *
     * @var RealEstate_Sync_Delete_Queue_Manager
     */
    private $delete_queue_manager;

    /**
     * Deletion manager.
     *
     * @var RealEstate_Sync_Deletion_Manager
     */
    private $deletion_manager;

    /**
     * Constructor.
     *
     * @param RealEstate_Sync_Delete_Queue_Manager|null $delete_queue_manager Queue manager.
     * @param RealEstate_Sync_Deletion_Manager|null     $deletion_manager     Deletion manager.
     */
    public function __construct($delete_queue_manager = null, $deletion_manager = null) {
        $this->delete_queue_manager = $delete_queue_manager ?: new RealEstate_Sync_Delete_Queue_Manager();
        $this->deletion_manager = $deletion_manager ?: new RealEstate_Sync_Deletion_Manager();
    }

    /**
     * Process a single pending delete queue item for the session.
     *
     * @param string $session_id Session ID.
     * @return array
     */
    public function process_next_item($session_id) {
        $result = array(
            'success' => true,
            'session_id' => $session_id,
            'claimed_item' => null,
            'processed_item' => null,
            'queue_status' => null,
            'delete_outcome' => null,
            'stats' => array(
                'claimed' => 0,
                'processed' => 0,
                'done' => 0,
                'skipped' => 0,
                'error' => 0,
            ),
        );

        $item = $this->delete_queue_manager->claim_next_pending_item($session_id);

        if (!$item) {
            $result['stats']['remaining'] = 0;
            return $result;
        }

        $result['claimed_item'] = $item;
        $result['stats']['claimed'] = 1;

        try {
            $delete_result = $this->deletion_manager->delete_single_property($item->property_import_id);
            $queue_status = $this->map_delete_outcome_to_queue_status($delete_result['outcome']);
            $updated = $this->apply_queue_status($item->id, $queue_status);

            if (!$updated) {
                $this->delete_queue_manager->mark_error($item->id);
                $queue_status = 'error';
                $delete_result['outcome'] = RealEstate_Sync_Deletion_Manager::SINGLE_DELETE_ERROR;
                $delete_result['error_message'] = $delete_result['error_message'] ?? 'Failed to update queue item status';
            }

            $result['processed_item'] = $this->delete_queue_manager->get_item($item->id);
            $result['queue_status'] = $queue_status;
            $result['delete_outcome'] = $delete_result['outcome'];
            $result['delete_result'] = $delete_result;
            $result['stats']['processed'] = 1;
            $result['stats'][$queue_status] = 1;
            $result['stats']['remaining'] = $this->delete_queue_manager->get_stats($session_id)['pending'];

            return $result;
        } catch (Exception $e) {
            $this->delete_queue_manager->mark_error($item->id);

            $result['success'] = false;
            $result['processed_item'] = $this->delete_queue_manager->get_item($item->id);
            $result['queue_status'] = 'error';
            $result['delete_outcome'] = RealEstate_Sync_Deletion_Manager::SINGLE_DELETE_ERROR;
            $result['error'] = $e->getMessage();
            $result['stats']['processed'] = 1;
            $result['stats']['error'] = 1;
            $result['stats']['remaining'] = $this->delete_queue_manager->get_stats($session_id)['pending'];

            return $result;
        }
    }

    /**
     * Map deletion outcome to queue status.
     *
     * @param string $outcome Deletion outcome.
     * @return string
     */
    private function map_delete_outcome_to_queue_status($outcome) {
        if ($outcome === RealEstate_Sync_Deletion_Manager::SINGLE_DELETE_SUCCESS) {
            return 'done';
        }

        if ($outcome === RealEstate_Sync_Deletion_Manager::SINGLE_DELETE_NOT_FOUND) {
            return 'skipped';
        }

        return 'error';
    }

    /**
     * Apply the final queue status.
     *
     * @param int    $item_id      Queue item ID.
     * @param string $queue_status Queue status.
     * @return bool
     */
    private function apply_queue_status($item_id, $queue_status) {
        if ($queue_status === 'done') {
            return $this->delete_queue_manager->mark_done($item_id);
        }

        if ($queue_status === 'skipped') {
            return $this->delete_queue_manager->mark_skipped($item_id);
        }

        return $this->delete_queue_manager->mark_error($item_id);
    }
}
