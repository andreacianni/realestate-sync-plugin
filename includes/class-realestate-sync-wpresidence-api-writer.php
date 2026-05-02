<?php
/**
 * WPResidence REST API Writer
 *
 * Handles all property creation/update operations via WPResidence REST API.
 * This class replaces direct meta field manipulation with official API calls,
 * ensuring proper gallery handling and WPResidence hook execution.
 *
 * @package    RealEstate_Sync
 * @subpackage RealEstate_Sync/includes
 * @since      1.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once dirname(__FILE__) . '/class-realestate-sync-jwt-auth-helper.php';

class RealEstate_Sync_WPResidence_API_Writer {

	/**
	 * Logger instance
	 *
	 * @var RealEstate_Sync_Logger
	 */
	private $logger;

	/**
	 * JWT authentication token
	 *
	 * @var string|null
	 */
	private $jwt_token;

	/**
	 * JWT token expiration timestamp
	 *
	 * @var int|null
	 */
	private $jwt_expiration;

	/**
	 * WPResidence API base URL
	 *
	 * @var string
	 */
	private $api_base_url;

	/**
	 * JWT authentication endpoint
	 *
	 * @var string
	 */
	private $jwt_auth_url;

	/**
	 * API request timeout in seconds
	 * Kept at 120s for image-heavy properties (up to 100 images)
	 *
	 * @var int
	 */
	private $api_timeout = 120;

	/**
	 * Maximum retry attempts for API requests
	 * DIAGNOSTIC: Reduced to 2 for faster failure detection
	 *
	 * @var int
	 */
	private $max_retries = 2;

	/**
	 * Constructor
	 *
	 * @param RealEstate_Sync_Logger|null $logger Optional logger instance
	 */
	public function __construct($logger = null) {
		$this->logger = $logger ?: RealEstate_Sync_Logger::get_instance();
		$this->api_base_url = get_site_url() . '/wp-json/wpresidence/v1';
		$this->jwt_auth_url = get_site_url() . '/wp-json/jwt-auth/v1/token';

		// Removed verbose init log - floods log in batch processing
		// $this->logger->log('API Writer initialized', 'INFO');
	}

	/**
	 * Get or generate JWT authentication token
	 *
	 * Caches token for 9 minutes (JWT expires at 10 minutes)
	 * to avoid edge cases during long operations.
	 *
	 * @return string|false JWT token on success, false on failure
	 */
	private function get_jwt_token() {
		// Check if current token is still valid (with 1 minute safety margin)
		if ($this->jwt_token && $this->jwt_expiration && time() < ($this->jwt_expiration - 60)) {
			return $this->jwt_token;
		}

		$this->logger->log('Generating new JWT token', 'INFO');

		// Get credentials from WordPress options
		$username = get_option('realestate_sync_api_username', '');
		$password = get_option('realestate_sync_api_password', '');

		if (empty($username) || empty($password)) {
			$this->logger->log('API credentials not configured in WordPress options', 'ERROR');
			return false;
		}

		$max_auth_attempts = 2; // initial + one retry for transient failures

		for ($attempt = 1; $attempt <= $max_auth_attempts; $attempt++) {
			$response = wp_remote_post($this->jwt_auth_url, array(
				'body'    => json_encode(array(
					'username' => $username,
					'password' => $password,
				)),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			));

			// Handle request errors
			if (is_wp_error($response)) {
				$this->logger->log('JWT token request failed: ' . $response->get_error_message(), 'ERROR', array(
					'endpoint' => $this->jwt_auth_url,
					'error_data' => $response->get_error_data(),
					'attempt' => $attempt
				));

				if ($attempt < $max_auth_attempts && $this->should_retry_auth($response->get_error_message(), null, null)) {
					usleep(300000); // 300ms
					continue;
				}
				return false;
			}

			$status_code = wp_remote_retrieve_response_code($response);
			$headers = wp_remote_retrieve_headers($response);
			$raw_body = wp_remote_retrieve_body($response);
			$body_len = strlen($raw_body);
			$body_preview = substr($raw_body, 0, 400);

			$decoded = RealEstate_Sync_JWT_Auth_Helper::decode_body($raw_body);
			$this->logger->log('JWT auth response received', 'DEBUG', array(
				'endpoint' => $this->jwt_auth_url,
				'http_code' => $status_code,
				'content_type' => $headers['content-type'] ?? null,
				'server' => $headers['server'] ?? null,
				'body_length' => $body_len,
				'body_preview' => $body_preview,
				'bom_removed' => $decoded['bom_removed'],
				'attempt' => $attempt
			));

			if (!$decoded['success']) {
				$this->logger->log('JWT auth response parse failed: ' . $decoded['json_error'], 'ERROR', array(
					'http_code' => $status_code,
					'body_length' => $body_len,
					'bom_removed' => $decoded['bom_removed'],
					'attempt' => $attempt
				));

				if ($attempt < $max_auth_attempts && $this->should_retry_auth('parse_error', $status_code, $body_len)) {
					usleep(300000); // 300ms
					continue;
				}
				return false;
			}
			$body = $decoded['body'];

			// Check response status
			if ($status_code !== 200) {
				$error_msg = isset($body['message']) ? $body['message'] : 'Unknown error';
				$this->logger->log("JWT authentication failed (HTTP $status_code): $error_msg", 'ERROR', array(
					'attempt' => $attempt
				));

				if ($attempt < $max_auth_attempts && $this->should_retry_auth($error_msg, $status_code, $body_len)) {
					usleep(300000); // 300ms
					continue;
				}
				return false;
			}

			$token_result = RealEstate_Sync_JWT_Auth_Helper::extract_token($body);
			if (!$token_result['success']) {
				$this->logger->log('JWT token not found in authentication response', 'ERROR', array(
					'http_code' => $status_code,
					'body_length' => $body_len,
					'body_preview' => $body_preview,
					'bom_removed' => $decoded['bom_removed'],
					'top_level_keys' => $token_result['top_level_keys'],
					'data_keys' => $token_result['data_keys'],
					'attempt' => $attempt
				));

				if ($attempt < $max_auth_attempts && $this->should_retry_auth('missing_token', $status_code, $body_len)) {
					usleep(300000); // 300ms
					continue;
				}
				return false;
			}

			// Store token and expiration (9 minutes from now for safety)
			$this->jwt_token = $token_result['token'];
			$this->jwt_expiration = time() + (9 * 60);

			$this->logger->log('JWT token generated successfully (expires in 9 minutes)', 'INFO');

			return $this->jwt_token;
		}

		return false;
	}

	/**
	 * Format mapped property data to API body format
	 *
	 * Converts internal mapped property structure to WPResidence API format.
	 * Handles core fields, meta fields, taxonomies, features, gallery, and custom fields.
	 *
	 * @param array $mapped_property Mapped property data from Property Mapper
	 * @return array API-formatted body ready for create/update requests
	 */
	public function format_api_body($mapped_property) {
		$api_body = array();

		$this->logger->log('Formatting property data for API', 'DEBUG');

		// 1. Core post fields
		if (isset($mapped_property['post_data']['post_title'])) {
			$api_body['title'] = $mapped_property['post_data']['post_title'];
		}

		if (isset($mapped_property['post_data']['post_content'])) {
			$api_body['property_description'] = $mapped_property['post_data']['post_content'];
		}

		// 2. All meta fields become API parameters
		if (!empty($mapped_property['meta_fields']) && is_array($mapped_property['meta_fields'])) {
			foreach ($mapped_property['meta_fields'] as $key => $value) {
				// Skip empty values to avoid overwriting with blanks
				if ($value !== '' && $value !== null) {
					$api_body[$key] = $value;
				}
			}
		}

		// Force prop_featured to 0 to keep properties visible in theme queries.
		$api_body['prop_featured'] = '0';
		$this->logger->log('Forcing prop_featured=0 in API payload', 'DEBUG');

		// 3. Taxonomies (as arrays)
		if (!empty($mapped_property['taxonomies']) && is_array($mapped_property['taxonomies'])) {
			foreach ($mapped_property['taxonomies'] as $taxonomy => $terms) {
				if (!empty($terms) && is_array($terms)) {
					$api_body[$taxonomy] = $terms;
				}
			}
		}

		// 4. Property features
		if (!empty($mapped_property['features']) && is_array($mapped_property['features'])) {
			$api_body['property_features'] = $mapped_property['features'];
		}

		// 5. Gallery images (convert to API format)
		if (!empty($mapped_property['gallery']) && is_array($mapped_property['gallery'])) {
			$api_body['images'] = $this->format_gallery_for_api($mapped_property['gallery']);
			$this->logger->log('Formatted ' . count($api_body['images']) . ' gallery images for API', 'DEBUG');
		}

		// 6. Agency/Agent assignment
		if (!empty($mapped_property['source_data']['agency_id'])) {
			$api_body['property_agent'] = (string) $mapped_property['source_data']['agency_id'];
			$this->logger->log('Agency/Agent ID: ' . $api_body['property_agent'], 'DEBUG');

			// Set sidebar_agent_option to 'global' to enable agency sidebar display
			// This follows WPResidence default behavior for property creation
			$api_body['sidebar_agent_option'] = 'global';
		}

		// 6b. Property owner (post_author) assignment
		// If configured, assign property to specific WordPress user
		// Otherwise, WPResidence API defaults to JWT authenticated user
		$property_user_id = get_option('realestate_sync_property_user_id', '');
		if (!empty($property_user_id)) {
			$api_body['property_user'] = (string) $property_user_id;
				$this->logger->log('Property User ID: ' . $api_body['property_user'], 'DEBUG');
		}

		// 7. Catasto data as custom fields
		if (!empty($mapped_property['catasto']) && is_array($mapped_property['catasto'])) {
			if (!isset($api_body['custom_fields'])) {
				$api_body['custom_fields'] = array();
			}

			foreach ($mapped_property['catasto'] as $key => $value) {
				if ($value !== '' && $value !== null) {
					$api_body['custom_fields'][] = array(
						'slug'  => $key,
						'value' => $value,
					);
				}
			}
		}

		// 8. Additional custom fields from mapped data
		if (!empty($mapped_property['custom_fields']) && is_array($mapped_property['custom_fields'])) {
			if (!isset($api_body['custom_fields'])) {
				$api_body['custom_fields'] = array();
			}

			foreach ($mapped_property['custom_fields'] as $field) {
				if (isset($field['slug']) && isset($field['value']) && $field['value'] !== '') {
					$api_body['custom_fields'][] = $field;
				}
			}
		}

		$this->logger->log('API body formatted with ' . count($api_body) . ' top-level fields', 'DEBUG');

		return $api_body;
	}

	/**
	 * Format gallery array to API format
	 *
	 * Converts internal gallery structure to WPResidence API format.
	 * API expects: [{"id": "img00", "url": "https://..."}]
	 *
	 * @param array $gallery Gallery array from mapped property
	 * @return array API-formatted gallery array
	 */
	private function format_gallery_for_api($gallery) {
		$api_images = array();

		foreach ($gallery as $index => $image) {
			// API requires HTTPS URLs
			$url = isset($image['url']) ? $image['url'] : '';

			if (empty($url)) {
				continue;
			}

			// Convert HTTP to HTTPS if needed
			if (strpos($url, 'http://') === 0) {
				$url = str_replace('http://', 'https://', $url);
				$this->logger->log("Converted image URL to HTTPS: $url", 'WARNING');
			}

			// Validate HTTPS
			if (strpos($url, 'https://') !== 0) {
				$this->logger->log("Skipping non-HTTPS image URL: $url", 'WARNING');
				continue;
			}

			// Format image ID (img00, img01, etc.)
			$image_id = 'img' . str_pad($index, 2, '0', STR_PAD_LEFT);

			$api_images[] = array(
				'id'  => $image_id,
				'url' => $url,
			);
		}

		return $api_images;
	}

	/**
	 * Create new property via WPResidence API
	 *
	 * Calls POST /wpresidence/v1/property/add endpoint.
	 * API handles all property creation, meta fields, taxonomies, and gallery processing.
	 *
	 * @param array $api_body Formatted API body from format_api_body()
	 * @return array Result array with success status, post_id, action, and message
	 */
	public function create_property($api_body) {
		$this->logger->log('Creating property via API', 'DEBUG');

		// Get JWT token
		$token = $this->get_jwt_token();
		if (!$token) {
			return array(
				'success' => false,
				'error'   => 'Failed to obtain JWT authentication token',
			);
		}

		// Make API request
		$endpoint = $this->api_base_url . '/property/add';
		$response = $this->make_api_request('POST', $endpoint, $api_body, $token);

		// Handle errors
		if (!$response['success']) {
			$this->logger->log('API create_property failed: ' . $response['error'], 'ERROR');
			return $response;
		}

		$body = $response['body'];

		// Check API response status
		if (isset($body['status']) && $body['status'] === 'success') {
			$post_id = isset($body['property_id']) ? $body['property_id'] : null;

			if ($post_id) {
					$this->logger->log("Property created successfully via API (ID: $post_id)", 'DEBUG');

				return array(
					'success' => true,
					'action'  => 'created',
					'post_id' => $post_id,
					'message' => 'Property created via WPResidence API',
				);
			}
		}

		// API returned success but no property_id
		$error_msg = isset($body['message']) ? $body['message'] : 'Unknown API error';
		$this->logger->log('API create response missing property_id: ' . $error_msg, 'ERROR');

		return array(
			'success' => false,
			'error'   => $error_msg,
		);
	}

	/**
	 * Update existing property via WPResidence API
	 *
	 * Calls PUT /wpresidence/v1/property/edit/{id} endpoint.
	 * Note: Endpoint is /edit, NOT /update (tested and verified).
	 *
	 * @param int   $post_id  WordPress post ID of property to update
	 * @param array $api_body Formatted API body from format_api_body()
	 * @return array Result array with success status, post_id, action, and message
	 */
	public function update_property($post_id, $api_body) {
		$this->logger->log("Updating property $post_id via API", 'DEBUG');

		// Get JWT token
		$token = $this->get_jwt_token();
		if (!$token) {
			return array(
				'success' => false,
				'error'   => 'Failed to obtain JWT authentication token',
			);
		}

		// Make API request (using /edit endpoint, NOT /update)
		$endpoint = $this->api_base_url . '/property/edit/' . $post_id;
		$response = $this->make_api_request('PUT', $endpoint, $api_body, $token);

		// Handle errors
		if (!$response['success']) {
			$this->logger->log("API update_property failed for post $post_id: " . $response['error'], 'ERROR');
			return $response;
		}

		$body = $response['body'];

		// Check API response status
		if (isset($body['status']) && $body['status'] === 'success') {
			$this->logger->log("Property $post_id updated successfully via API", 'DEBUG');

			return array(
				'success' => true,
				'action'  => 'updated',
				'post_id' => $post_id,
				'message' => 'Property updated via WPResidence API',
			);
		}

		// API returned error
		$error_msg = isset($body['message']) ? $body['message'] : 'Unknown API error';
		$this->logger->log("API update response error for post $post_id: $error_msg", 'ERROR');

		return array(
			'success' => false,
			'error'   => $error_msg,
		);
	}

	/**
	 * Make API request with error handling and retry logic
	 *
	 * @param string $method   HTTP method (GET, POST, PUT, DELETE)
	 * @param string $endpoint Full API endpoint URL
	 * @param array  $body     Request body data
	 * @param string $token    JWT authentication token
	 * @param int    $retry    Current retry attempt (0-based)
	 * @return array Response array with success status, body, error, and http_code
	 */
	private function make_api_request($method, $endpoint, $body, $token, $retry = 0) {
		$request_args = array(
			'method'  => $method,
			'body'    => json_encode($body),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
			'timeout' => $this->api_timeout,
		);

		// Log request details (sanitized)
		$this->logger->log("API Request: $method $endpoint (attempt " . ($retry + 1) . ")", 'DEBUG');

		// Make request
		$response = wp_remote_request($endpoint, $request_args);

		// Handle WP_Error (network issues, timeouts, etc.)
		if (is_wp_error($response)) {
			$error_msg = $response->get_error_message();
			$this->logger->log("API request failed: $error_msg", 'ERROR');

			// Retry on network errors
			if ($retry < $this->max_retries && $this->should_retry_error($error_msg)) {
				$wait_time = pow(2, $retry); // Exponential backoff: 1s, 2s, 4s
				$this->logger->log("Retrying API request in {$wait_time}s...", 'WARNING');
				sleep($wait_time);
				return $this->make_api_request($method, $endpoint, $body, $token, $retry + 1);
			}

			return array(
				'success'   => false,
				'error'     => $error_msg,
				'http_code' => null,
			);
		}

		// Parse response
		$http_code = wp_remote_retrieve_response_code($response);
		$headers = wp_remote_retrieve_headers($response);
		$content_type = is_array($headers) ? ($headers['content-type'] ?? null) : ($headers->offsetGet('content-type') ?? null);
		$content_encoding = is_array($headers) ? ($headers['content-encoding'] ?? null) : ($headers->offsetGet('content-encoding') ?? null);
		$raw_body = wp_remote_retrieve_body($response);
		$body_for_decode = $raw_body;

		// Handle gzip-encoded responses that arrive undecoded
		if ($content_encoding && stripos($content_encoding, 'gzip') !== false) {
			$gz_decoded = function_exists('gzdecode') ? @gzdecode($raw_body) : false;
			if ($gz_decoded !== false && $gz_decoded !== null) {
				$body_for_decode = $gz_decoded;
			} else {
				$this->logger->log('DEBUG gzip decode failed, using raw body', 'DEBUG', array(
					'content_encoding' => $content_encoding,
					'body_length_raw' => strlen($raw_body),
				));
			}
		}

		$parsed_body = json_decode($body_for_decode, true);
		$json_error = json_last_error_msg();

		// Fallback: strip any leading non-JSON bytes (e.g., stray gzip/control chars)
		if (!is_array($parsed_body)) {
			$brace_pos = strpos($body_for_decode, '{');
			$bracket_pos = strpos($body_for_decode, '[');
			$start_pos_candidates = array_filter(array($brace_pos, $bracket_pos), function ($pos) {
				return $pos !== false;
			});
			$start_pos = !empty($start_pos_candidates) ? min($start_pos_candidates) : false;

			if ($start_pos !== false && $start_pos > 0) {
				$clean_body = substr($body_for_decode, $start_pos);
				$parsed_body = json_decode($clean_body, true);
				$clean_error = json_last_error_msg();

				$this->logger->log('DEBUG cleaned API body before decode', 'DEBUG', array(
					'content_encoding' => $content_encoding,
					'body_length_raw' => strlen($raw_body),
					'body_length_cleaned' => strlen($clean_body),
					'json_last_error_before' => $json_error,
					'json_last_error_after' => $clean_error,
				));

				$json_error = $clean_error;
			} else {
				$this->logger->log('DEBUG json decode failed without recoverable prefix', 'DEBUG', array(
					'content_encoding' => $content_encoding,
					'body_length_raw' => strlen($raw_body),
					'json_last_error' => $json_error,
				));
			}
		}

		$this->logger->log("API Response: HTTP $http_code", 'DEBUG');

		// Handle JWT token expiration (403)
		if ($http_code === 403 && isset($parsed_body['code']) && $parsed_body['code'] === 'jwt_auth_invalid_token') {
			$this->logger->log('JWT token expired, refreshing...', 'WARNING');
			$this->jwt_token = null; // Force token refresh
			$this->jwt_expiration = null;

			// Retry with new token (don't count towards max_retries)
			if ($retry === 0) {
				$new_token = $this->get_jwt_token();
				if ($new_token) {
					return $this->make_api_request($method, $endpoint, $body, $new_token, 0);
				}
			}
		}

		// Handle other HTTP errors
		if ($http_code >= 400) {
			$error_msg = isset($parsed_body['message']) ? $parsed_body['message'] : "HTTP $http_code error";

			// Retry on server errors (500-599)
			if ($http_code >= 500 && $retry < $this->max_retries) {
				$wait_time = pow(2, $retry);
				$this->logger->log("Server error (HTTP $http_code), retrying in {$wait_time}s...", 'WARNING');
				sleep($wait_time);
				return $this->make_api_request($method, $endpoint, $body, $token, $retry + 1);
			}

			return array(
				'success'   => false,
				'error'     => $error_msg,
				'http_code' => $http_code,
			);
		}

		// Success
		return array(
			'success'   => true,
			'body'      => $parsed_body,
			'http_code' => $http_code,
		);
	}

	/**
	 * Determine if error should trigger retry
	 *
	 * @param string $error_message Error message from wp_remote_request
	 * @return bool True if should retry, false otherwise
	 */
	private function should_retry_error($error_message) {
		$retryable_errors = array(
			'timeout',
			'timed out',
			'connection',
			'temporarily unavailable',
			'network',
		);

		$error_lower = strtolower($error_message);

		foreach ($retryable_errors as $pattern) {
			if (strpos($error_lower, $pattern) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retry auth only for timeout/5xx/empty-body cases
	 */
	private function should_retry_auth($error_msg, $http_code = null, $body_len = null) {
		$msg = strtolower((string) $error_msg);

		if (strpos($msg, 'timeout') !== false || strpos($msg, 'timed out') !== false) {
			return true;
		}

		if ($http_code !== null) {
			if ($http_code === 0) {
				return true;
			}
			if ($http_code >= 500) {
				return true;
			}
		}

		if ($body_len !== null && $body_len === 0) {
			return true;
		}

		return false;
	}

	/**
	 * Test API connectivity and authentication
	 *
	 * Useful for diagnostics and setup verification.
	 *
	 * @return array Test result with success status and details
	 */
	public function test_connection() {
		$this->logger->log('Testing API connection...', 'INFO');

		// Test JWT authentication
		$token = $this->get_jwt_token();
		if (!$token) {
			return array(
				'success' => false,
				'message' => 'JWT authentication failed',
			);
		}

		// Test API availability (list routes)
		$endpoint = $this->api_base_url . '/';
		$response = wp_remote_get($endpoint, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
			'timeout' => 30,
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'message' => 'API endpoint not reachable: ' . $response->get_error_message(),
			);
		}

		$http_code = wp_remote_retrieve_response_code($response);

		if ($http_code === 200) {
			$this->logger->log('API connection test successful', 'SUCCESS');
			return array(
				'success' => true,
				'message' => 'API connection and authentication successful',
			);
		}

		return array(
			'success' => false,
			'message' => "API returned HTTP $http_code",
		);
	}

	/**
	 * Filter gallery to include only new/changed images
	 *
	 * Compares new gallery URLs with existing attachment URLs
	 * to avoid re-uploading unchanged images.
	 *
	 * @param int $property_id WordPress post ID of property
	 * @param array $new_gallery New gallery array from mapper
	 * @return array Filtered gallery with only new/changed images
	 */
	public function filter_unchanged_gallery_images($property_id, $new_gallery) {
		// 🔍 Debug tracker
		$tracker = RealEstate_Sync_Debug_Tracker::get_instance();

		if (empty($new_gallery) || !is_array($new_gallery)) {
			return $new_gallery;
		}

		$original_count = count($new_gallery);

		// Get existing attachments for this property
		$existing_attachments = get_attached_media('image', $property_id);

		if (empty($existing_attachments)) {
			// No existing images → all are new
			$tracker->log_event('DEBUG', 'PROPERTY_API_WRITER', 'No existing gallery, will upload all images', array(
				'property_id' => $property_id,
				'new_images_count' => $original_count
			));
			return $new_gallery;
		}

		// Build array of existing attachment URLs (normalized)
		$existing_urls = array();
		foreach ($existing_attachments as $attachment) {
			$url = wp_get_attachment_url($attachment->ID);
			if ($url) {
				$existing_urls[] = $this->normalize_image_url($url);
			}
		}

		// Filter gallery: keep only images not already attached
		$filtered_gallery = array();
		$skipped_count = 0;

		foreach ($new_gallery as $image) {
			$new_url = isset($image['url']) ? $image['url'] : '';
			if (empty($new_url)) {
				continue;
			}

			$normalized_new = $this->normalize_image_url($new_url);

			// Check if this URL already exists
			if (in_array($normalized_new, $existing_urls)) {
				$skipped_count++;
				continue; // Skip this image
			}

			// New image → include in filtered gallery
			$filtered_gallery[] = $image;
		}

		if ($skipped_count > 0) {
			$tracker->log_event('INFO', 'PROPERTY_API_WRITER', 'Gallery images filtered', array(
				'property_id' => $property_id,
				'original_count' => $original_count,
				'existing_count' => count($existing_attachments),
				'skipped_count' => $skipped_count,
				'new_to_upload' => count($filtered_gallery)
			));
		} else {
			$tracker->log_event('DEBUG', 'PROPERTY_API_WRITER', 'No matching images found, will upload all', array(
				'property_id' => $property_id,
				'images_count' => count($filtered_gallery)
			));
		}

		return $filtered_gallery;
	}

	/**
	 * Normalize image URL for comparison
	 *
	 * Removes protocol and trailing slashes to avoid false positives
	 * when comparing http vs https or URLs with/without trailing slashes.
	 *
	 * @param string $url Image URL
	 * @return string Normalized URL
	 */
	private function normalize_image_url($url) {
		// Extract filename from URL (works for both local and remote URLs)
		$filename = basename(parse_url($url, PHP_URL_PATH));

		// Lowercase for case-insensitive comparison
		$filename = strtolower($filename);

		return $filename;
	}
}
