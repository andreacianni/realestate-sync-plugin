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
	 *
	 * @var int
	 */
	private $api_timeout = 120;

	/**
	 * Maximum retry attempts for API requests
	 *
	 * @var int
	 */
	private $max_retries = 3;

	/**
	 * Constructor
	 *
	 * @param RealEstate_Sync_Logger|null $logger Optional logger instance
	 */
	public function __construct($logger = null) {
		$this->logger = $logger ?: RealEstate_Sync_Logger::get_instance();
		$this->api_base_url = get_site_url() . '/wp-json/wpresidence/v1';
		$this->jwt_auth_url = get_site_url() . '/wp-json/jwt-auth/v1/token';

		$this->logger->log('API Writer initialized', 'INFO');
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

		// Call JWT authentication endpoint
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
			$this->logger->log('JWT token request failed: ' . $response->get_error_message(), 'ERROR');
			return false;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		// Check response status
		if ($status_code !== 200) {
			$error_msg = isset($body['message']) ? $body['message'] : 'Unknown error';
			$this->logger->log("JWT authentication failed (HTTP $status_code): $error_msg", 'ERROR');
			return false;
		}

		// Extract token from response
		if (!isset($body['token'])) {
			$this->logger->log('JWT token not found in authentication response', 'ERROR');
			return false;
		}

		// Store token and expiration (9 minutes from now for safety)
		$this->jwt_token = $body['token'];
		$this->jwt_expiration = time() + (9 * 60);

		$this->logger->log('JWT token generated successfully (expires in 9 minutes)', 'INFO');

		return $this->jwt_token;
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

		$this->logger->log('Formatting property data for API', 'INFO');

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
			$this->logger->log('Formatted ' . count($api_body['images']) . ' gallery images for API', 'INFO');
		}

		// 6. Agency/Agent assignment
		if (!empty($mapped_property['source_data']['agency_id'])) {
			$api_body['property_agent'] = (string) $mapped_property['source_data']['agency_id'];
			$this->logger->log('Agency/Agent ID: ' . $api_body['property_agent'], 'INFO');
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

		$this->logger->log('API body formatted with ' . count($api_body) . ' top-level fields', 'INFO');

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
		$this->logger->log('Creating property via API', 'INFO');

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
				$this->logger->log("Property created successfully via API (ID: $post_id)", 'SUCCESS');

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
		$this->logger->log("Updating property $post_id via API", 'INFO');

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
			$this->logger->log("Property $post_id updated successfully via API", 'SUCCESS');

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
		$response_body = wp_remote_retrieve_body($response);
		$parsed_body = json_decode($response_body, true);

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
}
