<?php
/**
 * Gestisce l'eliminazione di properties e agencies marcate come deleted=1
 *
 * Features:
 * - Hard delete (bypass trash) - always LIVE
 * - Eliminazione attachments + thumbnails (properties)
 * - Eliminazione featured image (agencies)
 * - Logging dettagliato
 * - Email notification (opzionale)
 *
 * @package RealEstate_Sync
 * @since 1.8.0
 */

class RealEstate_Sync_Deletion_Manager {

	const SINGLE_DELETE_SUCCESS   = 'success';
	const SINGLE_DELETE_NOT_FOUND = 'not_found';
	const SINGLE_DELETE_ERROR     = 'error';

	/**
	 * Debug tracker instance
	 *
	 * @var RealEstate_Sync_Debug_Tracker
	 */
	private $tracker;

	/**
	 * Constructor
	 * Always performs LIVE deletion (no dry-run mode)
	 */
	public function __construct() {
		$this->tracker = RealEstate_Sync_Debug_Tracker::get_instance();
	}

	// ========================================================================
	// PROPERTIES DELETION
	// ========================================================================

	/**
	 * Handle deleted properties from XML
	 *
	 * @param array $deleted_property_ids Array of property IDs to delete
	 * @param string $session_id Import session ID
	 * @return array Statistics
	 */
	public function handle_deleted_properties($deleted_property_ids, $session_id) {
		$this->tracker->log_event('INFO', 'DELETION_MANAGER', 'Processing deleted properties', array(
			'count' => count($deleted_property_ids),
			'session_id' => $session_id
		));

		error_log("[DELETION-MANAGER] Starting property deletion process");
		error_log("[DELETION-MANAGER] Properties to process: " . count($deleted_property_ids));

		$stats = array(
			'properties_found' => count($deleted_property_ids),
			'properties_deleted' => 0,
			'properties_not_found' => 0,
			'attachments_deleted' => 0,
			'disk_space_freed' => 0,
			'errors' => 0
		);

		foreach ($deleted_property_ids as $property_id) {
			$result = $this->delete_single_property($property_id);

			if ($result['outcome'] === self::SINGLE_DELETE_SUCCESS) {
				$stats['properties_deleted']++;
				$stats['attachments_deleted'] += $result['attachments_deleted'];
				$stats['disk_space_freed'] += $result['disk_space_freed'];
			} elseif ($result['outcome'] === self::SINGLE_DELETE_NOT_FOUND) {
				$stats['properties_not_found']++;
			} else {
				$stats['errors']++;
			}
		}

		// Log final statistics
		error_log("[DELETION-MANAGER] ========== PROPERTY DELETION SUMMARY ==========");
		error_log("[DELETION-MANAGER]   Properties to delete: {$stats['properties_found']}");
		error_log("[DELETION-MANAGER]   Properties deleted: {$stats['properties_deleted']}");
		error_log("[DELETION-MANAGER]   Properties not found: {$stats['properties_not_found']}");
		error_log("[DELETION-MANAGER]   Attachments deleted: {$stats['attachments_deleted']}");
		error_log("[DELETION-MANAGER]   Disk space freed: " . round($stats['disk_space_freed'] / 1024 / 1024, 2) . " MB");
		error_log("[DELETION-MANAGER]   Errors: {$stats['errors']}");
		error_log("[DELETION-MANAGER] " . str_repeat("=", 60));

		$this->tracker->log_event('INFO', 'DELETION_MANAGER', 'Property deletion complete', $stats);

		return $stats;
	}

	/**
	 * Delete single property with an explicit outcome contract.
	 *
	 * @param string $property_id Property import ID
	 * @return array Result with explicit outcome and stats
	 */
	public function delete_single_property($property_id) {
		$result = array(
			'outcome' => self::SINGLE_DELETE_ERROR,
			'property_id' => $property_id,
			'attachments_deleted' => 0,
			'disk_space_freed' => 0,
			'wp_post_id' => null,
			'error_message' => null
		);

		try {
			return $this->perform_property_deletion($property_id, $result);
		} catch (Exception $e) {
			$result['error_message'] = $e->getMessage();
			error_log("[DELETION-MANAGER] ERROR: Property $property_id - " . $e->getMessage());

			$this->tracker->log_event('ERROR', 'DELETION_MANAGER', 'Single property deletion failed', array(
				'property_id' => $property_id,
				'error' => $e->getMessage()
			));

			return $result;
		}
	}

	/**
	 * Execute the property deletion workflow.
	 *
	 * @param string $property_id Property import ID.
	 * @param array  $result      Result payload.
	 * @return array
	 */
	private function perform_property_deletion($property_id, array $result) {
		$wp_post_id = $this->find_post_by_property_id($property_id, 'estate_property');

		if (!$wp_post_id) {
			error_log("[DELETION-MANAGER] Property $property_id not found in WP - skipping");
			$result['outcome'] = self::SINGLE_DELETE_NOT_FOUND;
			return $result;
		}

		$result['wp_post_id'] = $wp_post_id;
		error_log("[DELETION-MANAGER] Deleting property $property_id (WP ID: $wp_post_id)");

		// Get and delete all attachments (images)
		$attachments = get_attached_media('image', $wp_post_id);

		if (!empty($attachments)) {
			error_log("[DELETION-MANAGER]   Found " . count($attachments) . " attachments");

			foreach ($attachments as $attachment) {
				$file_path = get_attached_file($attachment->ID);
				$file_size = file_exists($file_path) ? filesize($file_path) : 0;

				// wp_delete_attachment($id, $force_delete = true) → elimina fisicamente file + thumbnails
				$deleted = wp_delete_attachment($attachment->ID, true);

				if ($deleted) {
					error_log("[DELETION-MANAGER]   ✅ Deleted attachment {$attachment->ID}: {$attachment->post_title}");
					$result['attachments_deleted']++;
					$result['disk_space_freed'] += $file_size;
				} else {
					error_log("[DELETION-MANAGER]   ❌ Failed to delete attachment {$attachment->ID}");
				}
			}
		}

		// Delete the property post
		// wp_delete_post($id, $force_delete = true) → elimina definitivamente (bypass trash)
		$deleted_post = wp_delete_post($wp_post_id, true);

		if ($deleted_post) {
			error_log("[DELETION-MANAGER]   ✅ Deleted property post $wp_post_id");
			$result['outcome'] = self::SINGLE_DELETE_SUCCESS;
		} else {
			error_log("[DELETION-MANAGER]   ❌ Failed to delete property post $wp_post_id");
			return $result;
		}

		// Update tracking table
		$this->update_tracking_deleted($property_id, 'property');

		return $result;
	}

	// ========================================================================
	// AGENCIES DELETION
	// ========================================================================

	/**
	 * Handle deleted agencies from XML
	 *
	 * @param array $deleted_agency_ids Array of agency IDs to delete
	 * @param string $session_id Import session ID
	 * @return array Statistics
	 */
	public function handle_deleted_agencies($deleted_agency_ids, $session_id) {
		$this->tracker->log_event('INFO', 'DELETION_MANAGER', 'Processing deleted agencies', array(
			'count' => count($deleted_agency_ids),
			'session_id' => $session_id
		));

		error_log("[DELETION-MANAGER] Starting agency deletion process");
		error_log("[DELETION-MANAGER] Agencies to process: " . count($deleted_agency_ids));

		$stats = array(
			'agencies_found' => count($deleted_agency_ids),
			'agencies_deleted' => 0,
			'agencies_not_found' => 0,
			'featured_images_deleted' => 0,
			'disk_space_freed' => 0,
			'errors' => 0
		);

		foreach ($deleted_agency_ids as $agency_id) {
			try {
				$result = $this->delete_agency($agency_id);

				if ($result['success']) {
					$stats['agencies_deleted']++;
					$stats['featured_images_deleted'] += $result['featured_image_deleted'] ? 1 : 0;
					$stats['disk_space_freed'] += $result['disk_space_freed'];
				} elseif ($result['not_found']) {
					$stats['agencies_not_found']++;
				} else {
					$stats['errors']++;
				}

			} catch (Exception $e) {
				error_log("[DELETION-MANAGER] ERROR: Agency $agency_id - " . $e->getMessage());
				$stats['errors']++;

				$this->tracker->log_event('ERROR', 'DELETION_MANAGER', 'Agency deletion failed', array(
					'agency_id' => $agency_id,
					'error' => $e->getMessage()
				));
			}
		}

		// Log final statistics
		error_log("[DELETION-MANAGER] ========== AGENCY DELETION SUMMARY ==========");
		error_log("[DELETION-MANAGER]   Agencies to delete: {$stats['agencies_found']}");
		error_log("[DELETION-MANAGER]   Agencies deleted: {$stats['agencies_deleted']}");
		error_log("[DELETION-MANAGER]   Agencies not found: {$stats['agencies_not_found']}");
		error_log("[DELETION-MANAGER]   Featured images deleted: {$stats['featured_images_deleted']}");
		error_log("[DELETION-MANAGER]   Disk space freed: " . round($stats['disk_space_freed'] / 1024 / 1024, 2) . " MB");
		error_log("[DELETION-MANAGER]   Errors: {$stats['errors']}");
		error_log("[DELETION-MANAGER] " . str_repeat("=", 60));

		$this->tracker->log_event('INFO', 'DELETION_MANAGER', 'Agency deletion complete', $stats);

		return $stats;
	}

	/**
	 * Delete single agency with featured image
	 *
	 * @param string $agency_id Agency import ID
	 * @return array Result with success flag and stats
	 */
	private function delete_agency($agency_id) {
		$result = array(
			'success' => false,
			'not_found' => false,
			'featured_image_deleted' => false,
			'disk_space_freed' => 0
		);

		// Find WordPress post by agency_import_id
		$wp_post_id = $this->find_post_by_property_id($agency_id, 'estate_agency');

		if (!$wp_post_id) {
			error_log("[DELETION-MANAGER] Agency $agency_id not found in WP - skipping");
			$result['not_found'] = true;
			return $result;
		}

		error_log("[DELETION-MANAGER] Deleting agency $agency_id (WP ID: $wp_post_id)");

		// Get and delete featured image
		$thumbnail_id = get_post_thumbnail_id($wp_post_id);

		if ($thumbnail_id) {
			$file_path = get_attached_file($thumbnail_id);
			$file_size = file_exists($file_path) ? filesize($file_path) : 0;

			$deleted = wp_delete_attachment($thumbnail_id, true);

			if ($deleted) {
				error_log("[DELETION-MANAGER]   ✅ Deleted featured image $thumbnail_id");
				$result['featured_image_deleted'] = true;
				$result['disk_space_freed'] += $file_size;
			} else {
				error_log("[DELETION-MANAGER]   ❌ Failed to delete featured image $thumbnail_id");
			}
		}

		// Delete the agency post
		$deleted_post = wp_delete_post($wp_post_id, true);

		if ($deleted_post) {
			error_log("[DELETION-MANAGER]   ✅ Deleted agency post $wp_post_id");
			$result['success'] = true;
		} else {
			error_log("[DELETION-MANAGER]   ❌ Failed to delete agency post $wp_post_id");
			return $result;
		}

		// Update tracking table
		$this->update_tracking_deleted($agency_id, 'agency');

		return $result;
	}

	// ========================================================================
	// HELPER METHODS
	// ========================================================================

	/**
	 * Find WordPress post by property_import_id or agency_xml_id
	 *
	 * @param string $import_id Property or Agency import ID
	 * @param string $post_type Post type (estate_property or estate_agency)
	 * @return int|false Post ID or false if not found
	 */
	private function find_post_by_property_id($import_id, $post_type) {
		global $wpdb;

		$meta_key = ($post_type === 'estate_property') ? 'property_import_id' : 'agency_xml_id';

		error_log("[DELETION-MANAGER] Searching for $post_type with $meta_key = '$import_id'");

		$post_id = $wpdb->get_var($wpdb->prepare(
			"SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND pm.meta_key = %s
			AND pm.meta_value = %s
			LIMIT 1",
			$post_type,
			$meta_key,
			$import_id
		));

		if ($post_id) {
			error_log("[DELETION-MANAGER]   ✅ Found WP post ID: $post_id");
		} else {
			error_log("[DELETION-MANAGER]   ❌ NOT FOUND - no post with $meta_key = '$import_id'");
		}

		return $post_id ? (int) $post_id : false;
	}

	/**
	 * Update tracking table with deleted status
	 *
	 * @param string $import_id Property or Agency ID
	 * @param string $type 'property' or 'agency'
	 */
	private function update_tracking_deleted($import_id, $type) {
		global $wpdb;

		$table_name = ($type === 'property')
			? $wpdb->prefix . 'realestate_sync_tracking'
			: $wpdb->prefix . 'realestate_sync_agency_tracking';

		$id_column = ($type === 'property') ? 'property_id' : 'agency_id';

		$wpdb->update(
			$table_name,
			array(
				'status' => 'deleted',
				'wp_post_id' => null,
				'last_import_date' => current_time('mysql')
			),
			array($id_column => $import_id),
			array('%s', '%d', '%s'),
			array('%s')
		);
	}

	/**
	 * Send email notification about deletions
	 *
	 * @param string $type 'properties' or 'agencies'
	 * @param array $stats Statistics array
	 */
	private function send_deletion_notification($type, $stats) {
		$admin_email = get_option('admin_email');

		$deleted_count = ($type === 'properties') ? $stats['properties_deleted'] : $stats['agencies_deleted'];

		$subject = "[RealEstate Sync] Import: $deleted_count $type deleted";

		$message = "Import completato con eliminazioni significative:\n\n";
		$message .= "Tipo: " . ucfirst($type) . "\n";
		$message .= "Eliminati: $deleted_count\n";

		if ($type === 'properties') {
			$message .= "Attachments eliminati: {$stats['attachments_deleted']}\n";
		} else {
			$message .= "Featured images eliminate: {$stats['featured_images_deleted']}\n";
		}

		$message .= "Spazio liberato: " . round($stats['disk_space_freed'] / 1024 / 1024, 2) . " MB\n";
		$message .= "Errori: {$stats['errors']}\n";
		$message .= "\nTimestamp: " . current_time('mysql');

		wp_mail($admin_email, $subject, $message);

		error_log("[DELETION-MANAGER] Email notification sent to $admin_email");
	}
}
