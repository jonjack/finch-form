<?php
/**
 * Cloudflare Turnstile server-side token verification via SiteVerify API.
 *
 * @package Finch_Form
 * @see https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Finch_Form_Turnstile
 */
class Finch_Form_Turnstile {

	const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Validate a Turnstile response token with Cloudflare SiteVerify API.
	 *
	 * @param string $token  The cf-turnstile-response token from the client.
	 * @param string $secret Secret key (from plugin settings).
	 * @param string $remote_ip Optional. Visitor IP for Cloudflare.
	 * @return array{ success: bool, error_codes: string[] } Parsed API response; success true only when token is valid.
	 */
	public static function verify( $token, $secret, $remote_ip = '' ) {
		$default = array(
			'success'      => false,
			'error_codes'  => array( 'internal-error' ),
		);

		if ( empty( $secret ) ) {
			return array( 'success' => false, 'error_codes' => array( 'missing-input-secret' ) );
		}

		$token = is_string( $token ) ? trim( $token ) : '';
		if ( strlen( $token ) === 0 ) {
			return array( 'success' => false, 'error_codes' => array( 'missing-input-response' ) );
		}
		if ( strlen( $token ) > 2048 ) {
			return array( 'success' => false, 'error_codes' => array( 'invalid-input-response' ) );
		}

		$body = array(
			'secret'   => $secret,
			'response' => $token,
		);
		if ( ! empty( $remote_ip ) && self::is_valid_ip( $remote_ip ) ) {
			$body['remoteip'] = $remote_ip;
		}

		// DEBUG ↓↓↓
		Finch_Form_Logger::log( "Calling Cloudflare to verify token:", $token, true );
		// DEBUG ↑↑↑

		$response = wp_remote_post(
			self::SITEVERIFY_URL,
			array(
				'timeout' => 15,
				'body'    => $body,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
			)
		);

		// DEBUG ↓↓↓
		Finch_Form_Logger::log( "Cloudflare response:", $response, true );
		// DEBUG ↑↑↑

		if ( is_wp_error( $response ) ) {
			return $default;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_raw, true );

		if ( ! is_array( $data ) ) {
			return $default;
		}

		$success = ! empty( $data['success'] );
		$error_codes = isset( $data['error-codes'] ) && is_array( $data['error-codes'] )
			? $data['error-codes']
			: array();

		return array(
			'success'     => $success,
			'error_codes' => $error_codes,
		);
	}

	/**
	 * Check if string looks like a valid IP (IPv4 or IPv6).
	 *
	 * @param string $ip IP string.
	 * @return bool
	 */
	private static function is_valid_ip( $ip ) {
		return false !== filter_var( $ip, FILTER_VALIDATE_IP );
	}
}
