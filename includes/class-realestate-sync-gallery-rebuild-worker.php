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
