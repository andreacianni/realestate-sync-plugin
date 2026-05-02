<?php
/**
 * JWT Auth Response Helper
 *
 * Shared helper for normalizing and parsing JWT auth responses.
 *
 * @package    RealEstate_Sync
 * @subpackage RealEstate_Sync/includes
 */

if (!defined('ABSPATH')) {
	exit;
}

class RealEstate_Sync_JWT_Auth_Helper {
	/**
	 * Normalize a raw JWT response body.
	 *
	 * Removes a UTF-8 BOM only if it is present at the beginning of the body.
	 *
	 * @param string $raw_body Raw response body.
	 * @return array{
	 *     body:string,
	 *     bom_removed:bool
	 * }
	 */
	public static function normalize_body($raw_body) {
		$body = (string) $raw_body;
		$bom_removed = false;

		if (strncmp($body, "\xEF\xBB\xBF", 3) === 0) {
			$body = substr($body, 3);
			$bom_removed = true;
		}

		return array(
			'body' => $body,
			'bom_removed' => $bom_removed,
		);
	}

	/**
	 * Decode a normalized JWT response body.
	 *
	 * @param string $raw_body Raw response body.
	 * @return array{
	 *     success:bool,
	 *     body:?array,
	 *     bom_removed:bool,
	 *     json_error:?string,
	 *     normalized_body:string
	 * }
	 */
	public static function decode_body($raw_body) {
		$normalized = self::normalize_body($raw_body);
		$decoded = json_decode($normalized['body'], true);

		if (!is_array($decoded)) {
			return array(
				'success' => false,
				'body' => null,
				'bom_removed' => $normalized['bom_removed'],
				'json_error' => json_last_error_msg(),
				'normalized_body' => $normalized['body'],
			);
		}

		return array(
			'success' => true,
			'body' => $decoded,
			'bom_removed' => $normalized['bom_removed'],
			'json_error' => null,
			'normalized_body' => $normalized['body'],
		);
	}

	/**
	 * Extract the JWT token from the top-level response body.
	 *
	 * @param array $body Decoded JSON body.
	 * @return array{
	 *     success:bool,
	 *     token:?string,
	 *     error:?string,
	 *     top_level_keys:array<int, string>,
	 *     data_keys:array<int, string>
	 * }
	 */
	public static function extract_token($body) {
		$top_level_keys = array();
		$data_keys = array();

		if (is_array($body)) {
			$top_level_keys = array_keys($body);

			if (isset($body['data']) && is_array($body['data'])) {
				$data_keys = array_keys($body['data']);
			}
		}

		$token = is_array($body) ? ($body['token'] ?? null) : null;
		if (!is_string($token) || $token === '') {
			return array(
				'success' => false,
				'token' => null,
				'error' => 'JWT token not found at top level',
				'top_level_keys' => $top_level_keys,
				'data_keys' => $data_keys,
			);
		}

		return array(
			'success' => true,
			'token' => $token,
			'error' => null,
			'top_level_keys' => $top_level_keys,
			'data_keys' => $data_keys,
		);
	}
}
