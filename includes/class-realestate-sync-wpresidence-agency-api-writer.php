<?php
/**
 * WPResidence Agency REST API Writer
 *
 * Handles agency creation/update operations via WPResidence REST API.
 * Mirrors the property API writer but for agencies.
 *
 * @package    RealEstate_Sync
 * @subpackage RealEstate_Sync/includes
 * @since      1.4.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class RealEstate_Sync_WPResidence_Agency_API_Writer {

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
	}

	/**
	 * Get or generate JWT authentication token
	 *
	 * Reuses existing token from property API writer if available,
	 * or generates new one. Caches token for 9 minutes.
	 *
	 * @return string|false JWT token on success, false on failure
	 */
	private function get_jwt_token() {
		// Check if current token is still valid (with 1 minute safety margin)
		if ($this->jwt_token && $this->jwt_expiration && time() < ($this->jwt_expiration - 60)) {
			return $this->jwt_token;
		}

		$this->logger->log('Generating new JWT token for agency API', 'INFO');

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
		if (!isset($body['data']['token'])) {
			$this->logger->log('JWT token not found in authentication response', 'ERROR');
			return false;
		}

		// Store token and expiration (9 minutes from now for safety)
		$this->jwt_token = $body['data']['token'];
		$this->jwt_expiration = time() + (9 * 60);

		$this->logger->log('JWT token generated successfully for agency API', 'INFO');

		return $this->jwt_token;
	}

	/**
	 * Format agency data to API body format
	 *
	 * Converts internal agency data structure to WPResidence Agency API format.
	 *
	 * @param array $agency_data Agency data from Agency Manager
	 * @return array API-formatted body ready for create/update requests
	 */
	public function format_api_body($agency_data) {
		$api_body = array();

		// 🔍 DEBUG: Log input data from Agency Manager
		$this->logger->log('🏢 [AGENCY API WRITER - STEP 5] Formatting agency data for WPResidence API', 'info');
		$this->logger->log('   Input fields received: ' . implode(', ', array_keys($agency_data)), 'debug');
		$this->logger->log('   name: ' . ($agency_data['name'] ?? 'MISSING'), 'debug');
		$this->logger->log('   email: ' . ($agency_data['email'] ?? 'MISSING'), 'debug');
		$this->logger->log('   phone: ' . ($agency_data['phone'] ?? 'MISSING'), 'debug');
		$this->logger->log('   website: ' . ($agency_data['website'] ?? 'MISSING'), 'debug');

		// 1. Required fields
		$api_body['agency_name'] = $agency_data['name'] ?? '';

		// 🔧 FIX: WPResidence API requires agency_email (mandatory field)
		// Use fallback email if not provided in XML
		$email = $agency_data['email'] ?? '';
		if (empty($email)) {
			// Generate fallback email using agency name or domain
			$site_domain = parse_url(get_site_url(), PHP_URL_HOST);
			$email = 'info@' . $site_domain;
			$this->logger->log('⚠️ Agency email missing - using fallback: ' . $email, 'warning');
		}
		$api_body['agency_email'] = $email;

		// 2. Contact information
		if (!empty($agency_data['address'])) {
			$api_body['agency_address'] = $agency_data['address'];
		}

		if (!empty($agency_data['phone'])) {
			$api_body['agency_phone'] = $agency_data['phone'];
		}

		if (!empty($agency_data['mobile'])) {
			$api_body['agency_mobile'] = $agency_data['mobile'];
		}

		// 3. Website - REMOVE http:// or https://
		if (!empty($agency_data['website'])) {
			$website = $agency_data['website'];
			// Remove protocol if present
			$website = preg_replace('#^https?://#', '', $website);
			// Remove trailing slash
			$website = rtrim($website, '/');
			$api_body['agency_website'] = $website;

			$this->logger->log('Agency website formatted (protocol removed)', 'INFO', array(
				'original' => $agency_data['website'],
				'formatted' => $website
			));
		}

		// 4. Location information
		if (!empty($agency_data['city'])) {
			$api_body['agency_city'] = $agency_data['city'];
		}

		if (!empty($agency_data['province'])) {
			$api_body['agency_state'] = $agency_data['province'];
		}

		if (!empty($agency_data['zip_code'])) {
			$api_body['agency_zip'] = $agency_data['zip_code'];
		}

		// 5. Business information
		if (!empty($agency_data['license']) || !empty($agency_data['vat_number'])) {
			$api_body['agency_license'] = $agency_data['license'] ?? $agency_data['vat_number'] ?? '';
		}

		// 6. Featured image (agency logo) - FULL URL
		if (!empty($agency_data['logo_url'])) {
			// Ensure HTTPS
			$logo_url = $agency_data['logo_url'];
			if (strpos($logo_url, 'http://') === 0) {
				$logo_url = str_replace('http://', 'https://', $logo_url);
			}
			$api_body['featured_image'] = $logo_url;

			$this->logger->log('Agency logo added to API body', 'INFO', array(
				'logo_url' => $logo_url
			));
		}

		// 7. Default fields
		$api_body['agency_languages'] = 'Italiano';

		// 8. XML Agency ID (CRITICAL for PHASE 2 lookup!)
		// This meta field is used to link properties to agencies during import
		if (!empty($agency_data['xml_agency_id'])) {
			$api_body['xml_agency_id'] = $agency_data['xml_agency_id'];
			$this->logger->log('✅ XML Agency ID added to API body: ' . $agency_data['xml_agency_id'], 'info');
		} else {
			$this->logger->log('⚠️ WARNING: xml_agency_id is MISSING! PHASE 2 lookup will FAIL!', 'warning');
		}

		// 🔍 DEBUG: Log formatted API body
		$this->logger->log('🏢 [AGENCY API WRITER - STEP 6] API body formatted', 'info');
		$this->logger->log('   API fields to send: ' . implode(', ', array_keys($api_body)), 'debug');
		$this->logger->log('   agency_name: ' . ($api_body['agency_name'] ?? 'MISSING'), 'debug');
		$this->logger->log('   agency_email: ' . ($api_body['agency_email'] ?? 'MISSING'), 'debug');
		$this->logger->log('   agency_phone: ' . ($api_body['agency_phone'] ?? 'not set'), 'debug');
		$this->logger->log('   agency_website: ' . ($api_body['agency_website'] ?? 'not set'), 'debug');
		$this->logger->log('   xml_agency_id: ' . ($api_body['xml_agency_id'] ?? 'MISSING!'), 'debug');
		$this->logger->log('   Total fields: ' . count($api_body), 'debug');

		return $api_body;
	}

	/**
	 * Create new agency via WPResidence API
	 *
	 * Calls POST /wpresidence/v1/agency/add endpoint.
	 *
	 * @param array $api_body Formatted API body from format_api_body()
	 * @return array Result array with success status, agency_id, action, and message
	 */
	public function create_agency($api_body) {
		// 🔍 DEBUG: Log API call start
		$this->logger->log('🏢 [AGENCY API WRITER - STEP 7] Calling WPResidence API to create agency', 'info');
		$this->logger->log('   Agency name: ' . ($api_body['agency_name'] ?? 'unknown'), 'debug');

		// Get JWT token
		$token = $this->get_jwt_token();
		if (!$token) {
			$this->logger->log('🏢 [AGENCY API WRITER - STEP 7] ❌ JWT token authentication FAILED', 'error');
			return array(
				'success' => false,
				'error'   => 'Failed to obtain JWT authentication token',
			);
		}
		$this->logger->log('   JWT token obtained: ✅', 'debug');

		// Make API request
		$endpoint = $this->api_base_url . '/agency/add';
		$this->logger->log('   API endpoint: ' . $endpoint, 'debug');

		$response = $this->make_api_request('POST', $endpoint, $api_body, $token);

		// Handle errors
		if (!$response['success']) {
			$this->logger->log('🏢 [AGENCY API WRITER - STEP 7] ❌ API request FAILED', 'error');
			$this->logger->log('   Error: ' . $response['error'], 'error');
			return $response;
		}

		$body = $response['body'];

		// 🔍 DEBUG: Log API response
		$this->logger->log('🏢 [AGENCY API WRITER - STEP 7] API response received', 'info');
		$this->logger->log('   Response status: ' . ($body['status'] ?? 'not set'), 'debug');
		$this->logger->log('   Response message: ' . ($body['message'] ?? 'not set'), 'debug');
		if (isset($body['agency_id'])) {
			$this->logger->log('   Agency ID: ' . $body['agency_id'], 'debug');
		}

		// Check API response status
		if (isset($body['status']) && $body['status'] === 'success') {
			$agency_id = isset($body['agency_id']) ? $body['agency_id'] : null;

			if ($agency_id) {
				$this->logger->log("Agency created successfully via API (ID: $agency_id)", 'SUCCESS');

				return array(
					'success'   => true,
					'action'    => 'created',
					'agency_id' => $agency_id,
					'message'   => 'Agency created via WPResidence API',
				);
			}
		}

		// API returned success but no agency_id
		$error_msg = isset($body['message']) ? $body['message'] : 'Unknown API error';
		$this->logger->log('API create response missing agency_id: ' . $error_msg, 'ERROR');

		return array(
			'success' => false,
			'error'   => $error_msg,
		);
	}

	/**
	 * Update existing agency via WPResidence API
	 *
	 * Calls PUT /wpresidence/v1/agency/edit/{id} endpoint.
	 *
	 * @param int   $agency_id Agency post ID
	 * @param array $api_body  Formatted API body from format_api_body()
	 * @return array Result array with success status, agency_id, action, and message
	 */
	public function update_agency($agency_id, $api_body) {
		$this->logger->log("Updating agency $agency_id via API", 'INFO');

		// Get JWT token
		$token = $this->get_jwt_token();
		if (!$token) {
			return array(
				'success' => false,
				'error'   => 'Failed to obtain JWT authentication token',
			);
		}

		// Make API request
		$endpoint = $this->api_base_url . '/agency/edit/' . $agency_id;
		$response = $this->make_api_request('PUT', $endpoint, $api_body, $token);

		// Handle errors
		if (!$response['success']) {
			$this->logger->log("API update_agency failed for agency $agency_id: " . $response['error'], 'ERROR');
			return $response;
		}

		$body = $response['body'];

		// Check API response status
		if (isset($body['status']) && $body['status'] === 'success') {
			$this->logger->log("Agency $agency_id updated successfully via API", 'SUCCESS');

			return array(
				'success'   => true,
				'action'    => 'updated',
				'agency_id' => $agency_id,
				'message'   => 'Agency updated via WPResidence API',
			);
		}

		// API returned error
		$error_msg = isset($body['message']) ? $body['message'] : 'Unknown API error';
		$this->logger->log("API update response error for agency $agency_id: $error_msg", 'ERROR');

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
		$this->logger->log("Agency API Request: $method $endpoint (attempt " . ($retry + 1) . ")", 'DEBUG');

		// Make request
		$response = wp_remote_request($endpoint, $request_args);

		// Handle WP_Error (network issues, timeouts, etc.)
		if (is_wp_error($response)) {
			$error_msg = $response->get_error_message();
			$this->logger->log("Agency API request failed: $error_msg", 'ERROR');

			// Retry on network errors
			if ($retry < $this->max_retries && $this->should_retry_error($error_msg)) {
				$wait_time = pow(2, $retry); // Exponential backoff: 1s, 2s, 4s
				$this->logger->log("Retrying agency API request in {$wait_time}s...", 'WARNING');
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

		$this->logger->log("Agency API Response: HTTP $http_code", 'DEBUG');

		// Handle JWT token expiration (403)
		if ($http_code === 403 && isset($parsed_body['code']) && $parsed_body['code'] === 'jwt_auth_invalid_token') {
			$this->logger->log('JWT token expired, refreshing...', 'WARNING');
			$this->jwt_token = null;
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
	 * @return array Test result with success status and details
	 */
	public function test_connection() {
		$this->logger->log('Testing Agency API connection...', 'INFO');

		// Test JWT authentication
		$token = $this->get_jwt_token();
		if (!$token) {
			return array(
				'success' => false,
				'message' => 'JWT authentication failed',
			);
		}

		return array(
			'success' => true,
			'message' => 'Agency API authentication successful',
		);
	}
}
