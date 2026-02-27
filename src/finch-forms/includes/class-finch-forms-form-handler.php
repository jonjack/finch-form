<?php
/**
 * AJAX form submission handler: CSRF, sanitization, validation, rate limiting, tripwire, Turnstile, email.
 *
 * @package Finch_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Finch_Forms_Form_Handler
 */
class Finch_Forms_Form_Handler {

	const AJAX_ACTION = 'finch_forms_submit';
	const NONCE_ACTION = 'finch_forms_submit';
	const RATE_LIMIT_TRANSIENT_PREFIX = 'finch_forms_rate_';
	const TRIPWIRE_FIELD = 'finch_website_url'; // Hidden field; bots often fill it.

	/**
	 * Initialize AJAX handlers.
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * Handle AJAX form submission.
	 */
	public static function handle_submit() {
		$out = array( 'success' => false, 'message' => '' );

		// 1. Nonce (CSRF)
		$nonce = isset( $_POST['finch_forms_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['finch_forms_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$out['message'] = __( 'Security check failed. Please refresh and try again.', 'finch-forms' );
			wp_send_json( $out );
		}

		// 2. Spam tripwire: hidden field must be empty
		$tripwire = isset( $_POST[ self::TRIPWIRE_FIELD ] ) ? trim( wp_unslash( $_POST[ self::TRIPWIRE_FIELD ] ) ) : '';
		if ( $tripwire !== '' ) {
			$out['message'] = __( 'Submission not accepted.', 'finch-forms' );
			wp_send_json( $out );
		}

		// 3. Rate limiting
		$ip = self::get_client_ip();
		if ( ! self::check_rate_limit( $ip ) ) {
			$out['message'] = __( 'Too many submissions. Please try again later.', 'finch-forms' );
			wp_send_json( $out );
		}

		// 4. Turnstile
		$opts = Finch_Forms_Settings::get_options();
		if ( ! empty( $opts['turnstile_secret_key'] ) ) {
			$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			$result = Finch_Forms_Turnstile::verify( $token, $opts['turnstile_secret_key'], $ip );
			if ( ! $result['success'] ) {
				$out['message'] = __( 'Verification failed. Please complete the challenge and try again.', 'finch-forms' );
				wp_send_json( $out );
			}
		}

		// 5. Sanitize and validate fields
		$name    = isset( $_POST['finch_name'] ) ? sanitize_text_field( wp_unslash( $_POST['finch_name'] ) ) : '';
		$email   = isset( $_POST['finch_email'] ) ? sanitize_email( wp_unslash( $_POST['finch_email'] ) ) : '';
		$subject = isset( $_POST['finch_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['finch_subject'] ) ) : '';
		$message = isset( $_POST['finch_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['finch_message'] ) ) : '';

		$errors = array();
		if ( strlen( $name ) < 1 || strlen( $name ) > 200 ) {
			$errors[] = __( 'Please enter a valid name.', 'finch-forms' );
		}
		if ( ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'finch-forms' );
		}
		if ( strlen( $subject ) > 500 ) {
			$errors[] = __( 'Subject is too long.', 'finch-forms' );
		}
		if ( strlen( $message ) < 1 || strlen( $message ) > 10000 ) {
			$errors[] = __( 'Please enter a message (max 10000 characters).', 'finch-forms' );
		}

		if ( ! empty( $errors ) ) {
			$out['message'] = implode( ' ', $errors );
			$out['errors']  = $errors;
			wp_send_json( $out );
		}

		// 6. Send email via wp_mail (Zoho Mail plugin will handle if configured)
		$recipient = ! empty( $opts['recipient_email'] ) ? $opts['recipient_email'] : get_option( 'admin_email' );
		$recipient = sanitize_email( $recipient );
		if ( ! is_email( $recipient ) ) {
			$out['message'] = __( 'Server configuration error. Please try again later.', 'finch-forms' );
			wp_send_json( $out );
		}

		// Safe email: do not spoof From. Use site default From and put customer in Reply-To.
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject_line = sprintf(
			/* translators: 1: site name, 2: subject line from form */
			__( '[%1$s] Contact: %2$s', 'finch-forms' ),
			$site_name,
			$subject ? $subject : __( '(No subject)', 'finch-forms' )
		);
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . $email,
		);
		$body = sprintf(
			"Name: %s\nEmail: %s\nSubject: %s\n\nMessage:\n%s",
			$name,
			$email,
			$subject,
			$message
		);

		$sent = wp_mail( $recipient, $subject_line, $body, $headers );

		if ( $sent ) {
			self::record_rate_limit( $ip );
			$out['success'] = true;
			$out['message'] = __( 'Thank you. Your message has been sent.', 'finch-forms' );
		} else {
			$out['message'] = __( 'Sorry, we could not send your message. Please try again later.', 'finch-forms' );
		}

		wp_send_json( $out );
	}

	/**
	 * Get client IP for rate limiting and Turnstile.
	 *
	 * @return string
	 */
	private static function get_client_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// X-Forwarded-For can be comma-separated list; use first.
				if ( strpos( $value, ',' ) !== false ) {
					$value = trim( explode( ',', $value )[0] );
				}
				if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
					return $value;
				}
			}
		}
		return '0.0.0.0';
	}

	/**
	 * Check if IP is within rate limit.
	 *
	 * @param string $ip Client IP.
	 * @return bool True if allowed.
	 */
	private static function check_rate_limit( $ip ) {
		$opts = Finch_Forms_Settings::get_options();
		$max  = isset( $opts['rate_limit_per_min'] ) ? (int) $opts['rate_limit_per_min'] : 3;
		$key  = self::RATE_LIMIT_TRANSIENT_PREFIX . md5( $ip );
		$count = (int) get_transient( $key );
		return $count < $max;
	}

	/**
	 * Record one submission for rate limiting.
	 *
	 * @param string $ip Client IP.
	 */
	private static function record_rate_limit( $ip ) {
		$opts = Finch_Forms_Settings::get_options();
		$max  = isset( $opts['rate_limit_per_min'] ) ? (int) $opts['rate_limit_per_min'] : 3;
		$key  = self::RATE_LIMIT_TRANSIENT_PREFIX . md5( $ip );
		$count = (int) get_transient( $key );
		$count++;
		set_transient( $key, $count, MINUTE_IN_SECONDS );
	}
}
