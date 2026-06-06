<?php
/**
 * Gallery Rebuild Worker (dry-run/read-only).
 *
 * Preview-only helper for future gallery rebuild flow.
 *
 * @package RealEstate_Sync
 */

if (!defined('ABSPATH')) {
	exit;
}

class RealEstate_Sync_Gallery_Rebuild_Worker {

	/**
	 * Rebuild one pending gallery.
	 *
	 * Manual-only entrypoint. Dry-run if execute=false.
	 *
	 * @param array $assoc_args Arguments.
	 * @return array<string, mixed>
	 */
	public function rebuild($assoc_args = array()) {
		$options = $this->parse_rebuild_options($assoc_args);

		if (empty($options['post_id'])) {
			return $this->build_result(0, '', 'missing_post_id', false, false, false, 'Missing required post-id');
		}

		$row = $this->fetch_pending_property_row($options['post_id']);
		if (empty($row)) {
			return $this->build_result($options['post_id'], '', 'missing_pending_property', false, false, false, 'Pending property not found');
		}

		$property_import_id = (string) ($row['property_import_id'] ?? '');
		$pending_signature = (string) ($row['property_gallery_signature_pending'] ?? '');
		$payload_json = (string) ($row['property_gallery_payload_pending_json'] ?? '');
		$changed_pending = (int) ($row['property_gallery_changed_pending'] ?? 0);

		if ($changed_pending !== 1 || $pending_signature === '') {
			return $this->build_result((int) $row['post_id'], $property_import_id, 'missing_pending_state', false, false, false, 'Pending state invalid');
		}

		$payload = $this->decode_pending_payload($payload_json);
		if (!$payload['valid']) {
			return $this->build_result((int) $row['post_id'], $property_import_id, $payload['status'], false, false, false, $payload['error']);
		}

		$images = $this->build_images_from_pending_gallery($payload['gallery']);
		if (empty($images)) {
			return $this->build_result((int) $row['post_id'], $property_import_id, 'empty_gallery', false, false, false, 'Pending gallery empty');
		}

		if (empty($options['execute'])) {
			return $this->build_result((int) $row['post_id'], $property_import_id, 'dry_run', false, false, false, '', $images, $pending_signature);
		}

		$api_body = array(
			'images' => $images,
		);

		$api_writer = $this->get_api_writer();
		$api_result = $api_writer->update_property((int) $row['post_id'], $api_body);
		$api_success = is_array($api_result) && !empty($api_result['success']) && (($api_result['action'] ?? '') === 'updated');

		if (!$api_success) {
			return $this->build_result((int) $row['post_id'], $property_import_id, 'api_failed', false, false, false, (string) ($api_result['error'] ?? 'API update failed'), $images, $pending_signature);
		}

		$scanner_result = null;
		$scanner_success = false;

		try {
			$scanner_result = $this->run_scanner((int) $row['post_id']);
			$scanner_errors = isset($scanner_result['errors']) ? (int) $scanner_result['errors'] : 0;
			$scanner_success = ($scanner_errors === 0);
		} catch (Exception $e) {
			return $this->build_result((int) $row['post_id'], $property_import_id, 'scanner_failed', true, false, false, $e->getMessage(), $images, $pending_signature);
		}

		if ($scanner_success) {
			update_post_meta((int) $row['post_id'], 'property_gallery_signature', $pending_signature);
			delete_post_meta((int) $row['post_id'], 'property_gallery_signature_pending');
			delete_post_meta((int) $row['post_id'], 'property_gallery_payload_pending_json');
			update_post_meta((int) $row['post_id'], 'property_gallery_changed_pending', 0);
		}

		return $this->build_result((int) $row['post_id'], $property_import_id, $scanner_success ? 'success' : 'scanner_failed', true, $scanner_success, $scanner_success, $scanner_success ? '' : 'Scanner returned errors', $images, $pending_signature);
	}

	/**
	 * Preview pending galleries.
	 *
	 * @param array $assoc_args Arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function preview($assoc_args = array()) {
		$options = $this->parse_options($assoc_args);
		$rows = $this->fetch_pending_properties($options);

		if (empty($rows)) {
			$this->cli_log('No gallery pending items found');
			return array();
		}

		$fields = array(
			'post_id',
			'property_import_id',
			'status',
			'images_count',
			'property_gallery_signature',
			'property_gallery_signature_pending',
			'images_preview',
		);

		if (class_exists('WP_CLI')) {
			\WP_CLI\Utils\format_items($options['format'], $rows, $fields);
			return $rows;
		}

		foreach ($rows as $row) {
			echo implode("\t", array(
				(string) $row['post_id'],
				(string) $row['property_import_id'],
				(string) $row['status'],
				(string) $row['images_count'],
				(string) $row['property_gallery_signature'],
				(string) $row['property_gallery_signature_pending'],
				(string) $row['images_preview'],
			)) . PHP_EOL;
		}

		return $rows;
	}

	/**
	 * Parse rebuild options.
	 *
	 * @param array $assoc_args Arguments.
	 * @return array<string, mixed>
	 */
	private function parse_rebuild_options($assoc_args) {
		return array(
			'post_id' => isset($assoc_args['post-id']) && is_numeric($assoc_args['post-id']) ? (int) $assoc_args['post-id'] : 0,
			'execute' => !empty($assoc_args['execute']),
		);
	}

	/**
	 * Parse preview options.
	 *
	 * @param array $assoc_args Arguments.
	 * @return array<string, mixed>
	 */
	private function parse_options($assoc_args) {
		$limit = isset($assoc_args['limit']) && is_numeric($assoc_args['limit']) ? (int) $assoc_args['limit'] : 1;
		if ($limit < 1) {
			$limit = 1;
		}

		$post_id = isset($assoc_args['post-id']) && is_numeric($assoc_args['post-id']) ? (int) $assoc_args['post-id'] : 0;
		$format = isset($assoc_args['format']) ? strtolower((string) $assoc_args['format']) : 'table';
		if (!in_array($format, array('table', 'csv', 'json'), true)) {
			$format = 'table';
		}

		return array(
			'limit' => $limit,
			'post_id' => $post_id,
			'format' => $format,
		);
	}

	/**
	 * Fetch candidate properties and build preview rows.
	 *
	 * @param array<string, mixed> $options Options.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_pending_properties(array $options) {
		global $wpdb;

		$sql = "
			SELECT
				p.ID AS post_id,
				COALESCE(pm_import.meta_value, '') AS property_import_id,
				COALESCE(pm_signature.meta_value, '') AS property_gallery_signature,
				COALESCE(pm_pending.meta_value, '') AS property_gallery_signature_pending,
				pm_payload.meta_value AS property_gallery_payload_pending_json
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
			LEFT JOIN {$wpdb->postmeta} pm_payload
				ON pm_payload.post_id = p.ID
			   AND pm_payload.meta_key = 'property_gallery_payload_pending_json'
			WHERE p.post_type = 'estate_property'
			  AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
		";

		$params = array();
		if (!empty($options['post_id'])) {
			$sql .= ' AND p.ID = %d';
			$params[] = (int) $options['post_id'];
		}

		$sql .= ' ORDER BY p.post_modified DESC, p.ID DESC LIMIT %d';
		$params[] = (int) $options['limit'];

		$prepared = $wpdb->prepare($sql, $params);
		$rows = $wpdb->get_results($prepared, ARRAY_A);

		if (!is_array($rows)) {
			return array();
		}

		$preview_rows = array();
		foreach ($rows as $row) {
			$post_id = isset($row['post_id']) ? (int) $row['post_id'] : 0;
			$payload = isset($row['property_gallery_payload_pending_json']) ? (string) $row['property_gallery_payload_pending_json'] : '';
			$gallery_preview = $this->build_gallery_preview_from_payload($payload);

			$preview_rows[] = array(
				'post_id' => $post_id,
				'property_import_id' => (string) ($row['property_import_id'] ?? ''),
				'status' => $gallery_preview['status'],
				'property_gallery_signature' => (string) ($row['property_gallery_signature'] ?? ''),
				'property_gallery_signature_pending' => (string) ($row['property_gallery_signature_pending'] ?? ''),
				'images_count' => count($gallery_preview['images']),
				'images' => $gallery_preview['images'],
				'images_preview' => $gallery_preview['images_preview'],
			);
		}

		return $preview_rows;
	}

	/**
	 * Fetch one pending property row.
	 *
	 * @param int $post_id Property post ID.
	 * @return array<string, mixed>
	 */
	private function fetch_pending_property_row($post_id) {
		global $wpdb;

		$sql = "
			SELECT
				p.ID AS post_id,
				COALESCE(pm_import.meta_value, '') AS property_import_id,
				COALESCE(pm_signature.meta_value, '') AS property_gallery_signature,
				COALESCE(pm_pending.meta_value, '') AS property_gallery_signature_pending,
				COALESCE(pm_changed.meta_value, '0') AS property_gallery_changed_pending,
				pm_payload.meta_value AS property_gallery_payload_pending_json
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
			LEFT JOIN {$wpdb->postmeta} pm_payload
				ON pm_payload.post_id = p.ID
			   AND pm_payload.meta_key = 'property_gallery_payload_pending_json'
			WHERE p.post_type = 'estate_property'
			  AND p.ID = %d
			  AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
			LIMIT 1
		";

		$prepared = $wpdb->prepare($sql, array((int) $post_id));
		$row = $wpdb->get_row($prepared, ARRAY_A);
		return is_array($row) ? $row : array();
	}

	/**
	 * Build pending gallery preview as API-style images array.
	 *
	 * @param string $payload_json Pending payload JSON.
	 * @return array{status:string,images:array<int,array<string,string>>,images_preview:string}
	 */
	private function build_gallery_preview_from_payload($payload_json) {
		if ($payload_json === '') {
			return array(
				'status' => 'missing_pending_payload',
				'images' => array(),
				'images_preview' => '',
			);
		}

		$decoded = json_decode($payload_json, true);
		if (!is_array($decoded)) {
			return array(
				'status' => 'invalid_pending_payload',
				'images' => array(),
				'images_preview' => '',
			);
		}

		$images = array();
		$parts = array();

		$pending_images = array();
		if (isset($decoded['gallery']) && is_array($decoded['gallery'])) {
			$pending_images = $decoded['gallery'];
		} elseif (isset($decoded['images']) && is_array($decoded['images'])) {
			$pending_images = $decoded['images'];
		} elseif (array_keys($decoded) === range(0, count($decoded) - 1)) {
			$pending_images = $decoded;
		}

		foreach ($pending_images as $index => $image) {
			if (!is_array($image) || empty($image['url'])) {
				continue;
			}

			$url = trim((string) $image['url']);
			if (strpos($url, 'http://') === 0) {
				$url = str_replace('http://', 'https://', $url);
			}

			if (strpos($url, 'https://') !== 0) {
				continue;
			}

			$image_id = 'img' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
			$images[] = array(
				'id' => $image_id,
				'url' => $url,
			);
			$parts[] = $image_id . '=' . $url;
		}

		return array(
			'status' => empty($images) ? 'missing_pending_payload' : 'ok',
			'images' => $images,
			'images_preview' => implode(' | ', $parts),
		);
	}

	/**
	 * Decode pending payload JSON.
	 *
	 * @param string $payload_json Pending payload JSON.
	 * @return array<string, mixed>
	 */
	private function decode_pending_payload($payload_json) {
		if ($payload_json === '') {
			return array(
				'valid' => false,
				'status' => 'missing_pending_payload',
				'error' => 'Missing property_gallery_payload_pending_json',
				'gallery' => array(),
			);
		}

		$decoded = json_decode($payload_json, true);
		if (!is_array($decoded) || empty($decoded['gallery']) || !is_array($decoded['gallery'])) {
			return array(
				'valid' => false,
				'status' => 'invalid_pending_payload',
				'error' => 'Invalid pending payload JSON',
				'gallery' => array(),
			);
		}

		return array(
			'valid' => true,
			'status' => 'ok',
			'error' => '',
			'gallery' => $decoded['gallery'],
		);
	}

	/**
	 * Build API images[] from pending gallery items.
	 *
	 * @param array $gallery Pending gallery items.
	 * @return array<int, array<string, string>>
	 */
	private function build_images_from_pending_gallery(array $gallery) {
		$images = array();

		foreach ($gallery as $index => $image) {
			if (!is_array($image) || empty($image['url'])) {
				continue;
			}

			$url = trim((string) $image['url']);
			if (strpos($url, 'http://') === 0) {
				$url = str_replace('http://', 'https://', $url);
			}

			if (strpos($url, 'https://') !== 0) {
				continue;
			}

			$images[] = array(
				'id' => 'img' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
				'url' => $url,
			);
		}

		return $images;
	}

	/**
	 * Get API writer instance.
	 *
	 * @return RealEstate_Sync_WPResidence_API_Writer
	 */
	private function get_api_writer() {
		return new RealEstate_Sync_WPResidence_API_Writer();
	}

	/**
	 * Run cleanup scanner scoped to one property.
	 *
	 * @param int $post_id Property post ID.
	 * @return array<string, mixed>
	 */
	private function run_scanner($post_id) {
		$command = new RealEstate_Sync_Media_Cleanup_Command();
		$scanner = new RealEstate_Sync_Media_Cleanup_Scanner($command, new RealEstate_Sync_Media_Cleanup_Queue_Manager());

		return $scanner->scan(array(
			'post-id' => $post_id,
			'execute' => true,
			'session-id' => 'gallery-rebuild',
		));
	}

	/**
	 * Build result row.
	 *
	 * @param int $post_id Post ID.
	 * @param string $property_import_id Import ID.
	 * @param string $status Status.
	 * @param bool $api_success API success flag.
	 * @param bool $scanner_success Scanner success flag.
	 * @param bool $pending_cleared Pending cleared flag.
	 * @param string $error Error message.
	 * @param array $images Images payload.
	 * @param string $pending_signature Pending signature.
	 * @return array<string, mixed>
	 */
	private function build_result($post_id, $property_import_id, $status, $api_success, $scanner_success, $pending_cleared, $error = '', array $images = array(), $pending_signature = '') {
		return array(
			'status' => $status,
			'post_id' => (int) $post_id,
			'property_import_id' => (string) $property_import_id,
			'images_count' => count($images),
			'images_preview' => $this->build_images_preview($images),
			'api_success' => $api_success ? true : false,
			'scanner_success' => $scanner_success ? true : false,
			'pending_cleared' => $pending_cleared ? true : false,
			'pending_signature' => (string) $pending_signature,
			'error' => (string) $error,
		);
	}

	/**
	 * Build images preview string.
	 *
	 * @param array $images Images payload.
	 * @return string
	 */
	private function build_images_preview(array $images) {
		$parts = array();
		foreach ($images as $image) {
			if (!is_array($image) || empty($image['id']) || empty($image['url'])) {
				continue;
			}

			$parts[] = $image['id'] . '=' . $image['url'];
		}

		return implode(' | ', $parts);
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
