<?php
/**
 * WordPress Property Importer - API-Based Version
 *
 * ⚠️ CRITICAL FILE - PROTECTED - DO NOT MODIFY
 * This file is part of the WORKING import system (commit cbbc9c0 / tag: working-import-cbbc9c0)
 * Verified working: 30-Nov-2025 - Creates properties via API and links to agencies
 *
 * Any batch system modifications must go through wrapper/adapter pattern
 * DO NOT modify the core process_property() method
 *
 * NEW implementation using WPResidence REST API instead of direct meta field manipulation.
 * This class replaces the legacy WP_Importer approach with API Writer integration,
 * ensuring proper gallery handling and WPResidence compatibility.
 *
 * Key differences from legacy class:
 * - Uses RealEstate_Sync_WPResidence_API_Writer for all create/update operations
 * - No manual gallery processing (handled by API)
 * - No manual taxonomy assignment (handled by API)
 * - No manual meta field updates (handled by API)
 * - ~60% less code (300 lines vs 1700 lines)
 *
 * Maintained features:
 * - Duplicate detection
 * - Import tracking
 * - Statistics
 * - Logging
 * - Error handling
 *
 * @package    RealEstate_Sync
 * @subpackage RealEstate_Sync/includes
 * @since      1.4.0
 * @protected-since 30-Nov-2025
 */

if (!defined('ABSPATH')) {
	exit;
}

class RealEstate_Sync_WP_Importer_API {

	/**
	 * Logger instance
	 *
	 * @var RealEstate_Sync_Logger
	 */
	private $logger;

	/**
	 * Debug Tracker instance
	 *
	 * @var RealEstate_Sync_Debug_Tracker
	 */
	private $tracker;

	/**
	 * API Writer instance
	 *
	 * @var RealEstate_Sync_WPResidence_API_Writer
	 */
	private $api_writer;

	/**
	 * Import statistics
	 *
	 * @var array
	 */
	private $stats;

	/**
	 * Current session ID
	 *
	 * @var string
	 */
	private $session_id;

	/**
	 * Constructor
	 *
	 * @param RealEstate_Sync_Logger|null $logger Optional logger instance
	 */
	public function __construct($logger = null) {
		$this->logger = $logger ?: RealEstate_Sync_Logger::get_instance();
		$this->tracker = RealEstate_Sync_Debug_Tracker::get_instance();  // 🔍 Debug Tracker
		$this->api_writer = new RealEstate_Sync_WPResidence_API_Writer($this->logger);

		$this->reset_stats();

		// Removed verbose init log - floods log in batch processing
		// $this->logger->log('WP Importer API initialized', 'INFO');
	}

	/**
	 * Reset import statistics
	 */
	private function reset_stats() {
		$this->stats = array(
			'imported_properties' => 0,
			'updated_properties'  => 0,
			'skipped_properties'  => 0,
			'failed_properties'   => 0,
			'errors'              => array(),
		);
	}

	/**
	 * Set session ID for tracking
	 *
	 * @param string $session_id Session identifier
	 */
	public function set_session_id($session_id) {
		$this->session_id = $session_id;
	}

	/**
	 * Get import statistics
	 *
	 * @return array Statistics array
	 */
	public function get_stats() {
		return $this->stats;
	}

	/**
	 * Process a single property using API Writer
	 *
	 * This is the main entry point for property import/update.
	 * Handles duplicate detection, taxonomy pre-creation, and API-based property creation/update.
	 *
	 * @param array $mapped_property Mapped property data from Property Mapper
	 * @return array Result array with success status, action, post_id, and message
	 */
	public function process_property($mapped_property) {
		$import_id = $mapped_property['source_data']['import_id'] ?? null;

		if (!$import_id) {
			$this->logger->log('Missing import_id in mapped property', 'ERROR');
			$this->stats['failed_properties']++;
			return array(
				'success' => false,
				'error'   => 'Missing import_id',
			);
		}

		$this->logger->log("Processing property via API: {$import_id}", 'DEBUG');

		// Item-level lock per import_id (re-entrant per session)
		$lock_key = 'realestate_sync_import_lock_' . $import_id;
		$lock_owner = get_transient($lock_key);
		$lock_acquired = false;

		if ($lock_owner && $lock_owner !== $this->session_id) {
			$this->logger->log("Import lock hit for {$import_id}, owned by {$lock_owner} - skipping create/update", 'WARNING', array(
				'import_id' => $import_id,
				'session_id' => $this->session_id,
			));
			return array(
				'success' => false,
				'error' => 'import_id locked',
			);
		}

		if (!$lock_owner) {
			set_transient($lock_key, $this->session_id, 15 * MINUTE_IN_SECONDS);
			$lock_acquired = true;
		} else {
			// Re-entrant for same session
			$lock_acquired = true;
		}

		try {
			// 1. Check for existing property (duplicate detection)
			$existing_post_id = $this->find_existing_property_strict($import_id);
			$converted_from_create = false;

			if ($existing_post_id) {
			$this->logger->log("Existing property found (ID: {$existing_post_id}) - will UPDATE", 'DEBUG');

				// ✅ CHANGE DETECTION DELEGATED TO TRACKING MANAGER
				// Import_Engine + Tracking_Manager already handle change detection via hash comparison
				// This ensures we use a single, unified hash calculation system on ALL property fields
				// Old duplicate hash check removed to prevent conflicts
			}

			// 2. Ensure taxonomies and features exist BEFORE API call
			// API doesn't create missing terms, so we must pre-create them
			$this->ensure_terms_exist($mapped_property);
			$this->ensure_features_exist($mapped_property);

			// 3. Format data for API
			$api_body = $this->api_writer->format_api_body($mapped_property);

			// 4. Create or update property via API
			if ($existing_post_id) {
				// 🔧 FIX IMAGE DUPLICATION: Filter unchanged gallery images for UPDATE
				if (!empty($mapped_property['gallery']) && is_array($mapped_property['gallery'])) {
					$original_count = count($mapped_property['gallery']);
					$filtered_gallery = $this->api_writer->filter_unchanged_gallery_images($existing_post_id, $mapped_property['gallery']);

					if (count($filtered_gallery) < $original_count) {
						// Re-format API body with filtered gallery
						$mapped_property['gallery'] = $filtered_gallery;
						$api_body = $this->api_writer->format_api_body($mapped_property);

				$this->tracker->log_event('DEBUG', 'WP_IMPORTER_API', 'Gallery filtered for UPDATE', array(
							'import_id' => $import_id,
							'original_images' => $original_count,
							'new_images' => count($filtered_gallery),
							'skipped_images' => $original_count - count($filtered_gallery)
						));
					}
				}

				// Update existing property
				$this->tracker->log_event('DEBUG', 'WP_IMPORTER_API', 'Calling API to UPDATE property', array(
					'import_id' => $import_id,
					'wp_post_id' => $existing_post_id,
					'price' => $api_body['property_price'] ?? null
				));

				$result = $this->api_writer->update_property($existing_post_id, $api_body);

				$this->tracker->log_event('DEBUG', 'WP_IMPORTER_API', 'API UPDATE result', array(
					'import_id' => $import_id,
					'success' => $result['success'],
					'action' => $result['action'] ?? null,
					'wp_post_id' => $result['post_id'] ?? null
				));

				if ($result['success']) {
					$this->stats['updated_properties']++;
				}
			} else {
				// Create new property
				$this->tracker->log_event('DEBUG', 'WP_IMPORTER_API', 'Calling API to CREATE property', array(
					'import_id' => $import_id,
					'price' => $api_body['property_price'] ?? null
				));

				$result = $this->api_writer->create_property($api_body);

				// Recover from CREATE timeout/network failure by verifying existence
				$recovered = $this->attempt_recover_create_failure($import_id, $api_body, $result);
				if ($recovered) {
					$converted_from_create = true;
					$result = $recovered;
				}

				$this->tracker->log_event('DEBUG', 'WP_IMPORTER_API', 'API CREATE result', array(
					'import_id' => $import_id,
					'success' => $result['success'],
					'action' => $result['action'] ?? null,
					'wp_post_id' => $result['post_id'] ?? null
				));

				if ($result['success']) {
					if ($converted_from_create) {
						$this->stats['updated_properties']++;
					} else {
						$this->stats['imported_properties']++;
					}
				}
			}

			// ✅ Micro-categories handled by API via property_category array
			// API accepts ["parent-slug", "child-slug"] and assigns both terms correctly

			// 6. Update tracking metadata (only if API call succeeded)
			if ($result['success']) {
				$this->update_tracking_metadata($result['post_id'], $mapped_property, $import_id);

					$this->logger->log("Property {$import_id} processed successfully via API", 'DEBUG', array(
					'action'  => $result['action'],
					'post_id' => $result['post_id'],
				));
			} else {
				$this->logger->log("Property {$import_id} failed via API", 'ERROR', array(
					'error' => $result['error'] ?? 'Unknown error',
				));
				$this->stats['failed_properties']++;
				$this->stats['errors'][] = $result['error'] ?? 'Unknown error';
			}

			return $result;

		} catch (Exception $e) {
			$this->logger->log("Exception processing property {$import_id}: " . $e->getMessage(), 'ERROR');
			$this->stats['failed_properties']++;
			$this->stats['errors'][] = $e->getMessage();

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		} finally {
			if ($lock_acquired) {
				delete_transient($lock_key);
			}
		}
	}

	/**
	 * Find existing property by import ID (strict: deterministic order, exclude trash/auto-draft)
	 *
	 * @param string $import_id Import identifier from source data
	 * @return int|null Post ID if found, null otherwise
	 */
	private function find_existing_property_strict($import_id) {
		if (!$import_id) {
			return null;
		}

		global $wpdb;
		$sql = $wpdb->prepare("
			SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE pm.meta_key = 'property_import_id'
			  AND pm.meta_value = %s
			  AND p.post_type = 'estate_property'
			  AND p.post_status NOT IN ('trash','auto-draft','inherit')
			ORDER BY p.ID ASC
			LIMIT 1
		", $import_id);

		$post_id = $wpdb->get_var($sql);

		return $post_id ? intval($post_id) : null;
	}

	/**
	 * Attempt to recover from CREATE failure (timeout/network) by checking if the property exists
	 * and switching to UPDATE when found.
	 *
	 * @param string $import_id Import identifier
	 * @param array  $api_body  Prepared API payload
	 * @param array  $create_result Result array from create_property()
	 * @return array|null Recovered result array or null if no recovery performed
	 */
	private function attempt_recover_create_failure($import_id, $api_body, $create_result) {
		$error_msg = $create_result['error'] ?? 'Unknown error';
		$http_code = $create_result['http_code'] ?? null;

		if (!$this->is_retryable_create_error($error_msg, $http_code)) {
			return null;
		}

		$existing_post_id = $this->find_existing_property_strict($import_id);

		$this->logger->log(
			"Verify-exists after CREATE failure for {$import_id}: found_post_id=" . ($existing_post_id ?: 'none'),
			'WARNING',
			array(
				'error' => $error_msg,
				'http_code' => $http_code,
				'session_id' => $this->session_id,
				'import_id' => $import_id,
			)
		);

		if ($existing_post_id) {
			$this->tracker->log_event('WARNING', 'WP_IMPORTER_API', 'CREATE failure -> switching to UPDATE', array(
				'import_id' => $import_id,
				'wp_post_id' => $existing_post_id,
				'error' => $error_msg,
				'session_id' => $this->session_id,
			));

			$update_result = $this->api_writer->update_property($existing_post_id, $api_body);

			if ($update_result['success']) {
				$update_result['action'] = 'updated';
			}

			return $update_result;
		}

		return null;
	}

	/**
	 * Determine if a CREATE error is retryable/timeout-like
	 *
	 * @param string $error_msg Error message
	 * @param int|null $http_code HTTP status code if available
	 * @return bool
	 */
	private function is_retryable_create_error($error_msg, $http_code) {
		$patterns = array('timeout', 'timed out', 'cURL error 28', 'connection reset', 'Operation timed out', 'Resolving timed out');
		foreach ($patterns as $pattern) {
			if (stripos($error_msg, $pattern) !== false) {
				return true;
			}
		}

		if ($http_code === null) {
			return true; // network/HTTP 0
		}

		if (in_array($http_code, array(408, 429), true)) {
			return true;
		}

		if ($http_code >= 500) {
			return true;
		}

		// Do not retry hard 4xx (400/401/403/404 etc.)
		return false;
	}

	/**
	 * Ensure taxonomy terms exist before API call
	 *
	 * WPResidence API doesn't create missing taxonomy terms automatically.
	 * We must pre-create them to ensure proper assignment.
	 *
	 * @param array $mapped_property Mapped property data
	 */
	private function ensure_terms_exist($mapped_property) {
		if (empty($mapped_property['taxonomies']) || !is_array($mapped_property['taxonomies'])) {
			return;
		}

		$source_data = $mapped_property['source_data'] ?? array();
		$original_name_map = array(
			// Use original names for display; slug remains the identifier.
			'property_city' => $source_data['city'] ?? '',
			'property_area' => $source_data['zone'] ?? '',
		);

		foreach ($mapped_property['taxonomies'] as $taxonomy => $terms) {
			if (empty($terms) || !is_array($terms)) {
				continue;
			}

			// Create all terms (flat taxonomy - API handles assignment)
			foreach ($terms as $term_slug) {
				$term = term_exists($term_slug, $taxonomy);

				if (!$term) {
					$original_name = $original_name_map[$taxonomy] ?? '';
					$term_name = $original_name !== '' ? $original_name : $this->humanize_term_name($term_slug);
					$result = wp_insert_term($term_name, $taxonomy, array(
						'slug' => $term_slug,
					));

					if (!is_wp_error($result)) {
						$this->logger->log("Created missing term: {$term_name} ({$term_slug}) in {$taxonomy}", 'INFO');
					} else {
						$this->logger->log("Failed to create term {$term_slug} in {$taxonomy}: " . $result->get_error_message(), 'WARNING');
					}
				}
			}
		}
	}

	/**
	 * Ensure property features exist before API call
	 *
	 * Similar to ensure_terms_exist, but specifically for property_features taxonomy.
	 *
	 * @param array $mapped_property Mapped property data
	 */
	private function ensure_features_exist($mapped_property) {
		if (empty($mapped_property['features']) || !is_array($mapped_property['features'])) {
			return;
		}

		$taxonomy = 'property_features';

		foreach ($mapped_property['features'] as $feature_slug) {
			// Check if feature exists
			$term = term_exists($feature_slug, $taxonomy);

			if (!$term) {
				// Create feature with humanized name
				$feature_name = $this->humanize_term_name($feature_slug);
				$result = wp_insert_term($feature_name, $taxonomy, array(
					'slug' => $feature_slug,
				));

				if (!is_wp_error($result)) {
					$this->logger->log("Created missing feature: {$feature_name} ({$feature_slug})", 'INFO');
				} else {
					$this->logger->log("Failed to create feature {$feature_slug}: " . $result->get_error_message(), 'WARNING');
				}
			}
		}
	}

	/**
	 * Humanize term/feature slug to readable name
	 *
	 * Converts slugs like "air-conditioning" to "Air Conditioning"
	 *
	 * @param string $slug Term slug
	 * @return string Humanized name
	 */
	private function humanize_term_name($slug) {
		// Replace hyphens and underscores with spaces
		$name = str_replace(array('-', '_'), ' ', $slug);

		// Capitalize each word
		$name = ucwords($name);

		return $name;
	}

	/**
	 * Update tracking metadata for imported property
	 *
	 * Stores import tracking information for duplicate detection and change tracking.
	 *
	 * @param int    $post_id         Post ID
	 * @param array  $mapped_property Mapped property data
	 * @param string $import_id       Import identifier
	 */
	private function update_tracking_metadata($post_id, $mapped_property, $import_id) {
		// Core tracking fields
		update_post_meta($post_id, 'property_import_id', $import_id);
		update_post_meta($post_id, 'property_import_hash', $mapped_property['content_hash'] ?? '');
		update_post_meta($post_id, 'property_last_sync', current_time('mysql'));
		update_post_meta($post_id, 'property_import_version', '4.0-api');

		// Session tracking (if available)
		if ($this->session_id) {
			update_post_meta($post_id, 'property_import_session', $this->session_id);
		}

		$this->logger->log("Updated tracking metadata for post {$post_id}", 'DEBUG');
	}

	/**
	 * Batch process multiple properties
	 *
	 * Convenience method for processing multiple properties in sequence.
	 *
	 * @param array $mapped_properties Array of mapped property data
	 * @return array Results array with statistics
	 */
	public function batch_process($mapped_properties) {
		$this->reset_stats();
		$this->logger->log('Starting batch property processing via API', 'INFO', array(
			'count' => count($mapped_properties),
		));

		$results = array();

		foreach ($mapped_properties as $index => $mapped_property) {
			$this->logger->log("Processing property " . ($index + 1) . " of " . count($mapped_properties), 'INFO');

			$result = $this->process_property($mapped_property);
			$results[] = $result;

			// Small delay to avoid API rate limiting
			usleep(100000); // 0.1 seconds
		}

		$this->logger->log('Batch processing completed', 'SUCCESS', $this->stats);

		return array(
			'success' => true,
			'stats'   => $this->stats,
			'results' => $results,
		);
	}
}
