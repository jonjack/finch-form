<?php
/**
 * AJAX form submission handler: CSRF, sanitization, validation, rate limiting, tripwire, Turnstile, email.
 *
 * @package FINCH_FORM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Finch_Form_Handler
 */
class Finch_Form_Handler {

	const AJAX_ACTION = 'finch-form_submit';
	const NONCE_ACTION = 'finch-form_submit';
	const RATE_LIMIT_TRANSIENT_PREFIX = 'finch-form_rate_';
	const TRIPWIRE_FIELD = 'finch_website_url'; // Hidden field; bots often fill it.

	/** Name field length limits. */
	const NAME_MIN = 2;
	const NAME_MAX = 50;

	/** Email field: max length for browser input only (no server-side length validation). */
	const EMAIL_MAX = 254;

	/** Subject field length limits (form and admin subject dropdown items). */
	const SUBJECT_MIN = 10;
	const SUBJECT_MAX = 50;

	/** Message field length limits. */
	const MESSAGE_MIN = 20;
	const MESSAGE_MAX = 1000;

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

		// DEBUG ↓↓↓
		Finch_Form_Logger::log( "\n\nSTART OF NEW FORM REQUEST ↓↓↓\n" );
		Finch_Form_Logger::log( 'POST payload', $_POST, true );
		// DEBUG ↑↑↑
		
		$out = array( 'success' => false, 'message' => '' );

		// 1. Nonce (CSRF)
		$nonce = isset( $_POST['finch-form_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['finch-form_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$out['message'] = __( 'Security check failed. Please refresh and try again.', 'finch-form' );
			wp_send_json( $out );
		}

		// 2. Spam tripwire: hidden field must be empty
		$tripwire = isset( $_POST[ self::TRIPWIRE_FIELD ] ) ? trim( wp_unslash( $_POST[ self::TRIPWIRE_FIELD ] ) ) : '';
		if ( $tripwire !== '' ) {
			$out['message'] = __( 'Submission not accepted.', 'finch-form' );
			wp_send_json( $out );
		}

		// 3. Rate limiting
		$ip = self::get_client_ip();
		if ( ! self::check_rate_limit( $ip ) ) {
			$out['message'] = __( 'Too many submissions. Please try again later.', 'finch-form' );
			wp_send_json( $out );
		}

		// 4. Sanitize and validate form fields (before Turnstile to avoid consuming token on invalid data)
		$name    = isset( $_POST['finch_name'] ) ? sanitize_text_field( wp_unslash( $_POST['finch_name'] ) ) : '';
		$email   = isset( $_POST['finch_email'] ) ? sanitize_email( wp_unslash( $_POST['finch_email'] ) ) : '';
		$subject = isset( $_POST['finch_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['finch_subject'] ) ) : '';
		$message = isset( $_POST['finch_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['finch_message'] ) ) : '';

		$errors = array();
		if ( strlen( $name ) < self::NAME_MIN || strlen( $name ) > self::NAME_MAX ) {
			$errors[] = __( 'Please enter a Name.', 'finch-form' );
		}
		if ( ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid Email address.', 'finch-form' );
		}
		if ( strlen( $subject ) < self::SUBJECT_MIN || strlen( $subject ) > self::SUBJECT_MAX ) {
			$errors[] = sprintf(
				/* translators: 1: min length, 2: max length */
				__( 'Please enter a Subject.', 'finch-form' ),
				self::SUBJECT_MIN,
				self::SUBJECT_MAX
			);
		}
		if ( strlen( $message ) < self::MESSAGE_MIN || strlen( $message ) > self::MESSAGE_MAX ) {
			$errors[] = sprintf(
				/* translators: 1: min length, 2: max length */
				__( 'Please enter a Message between %1$d and %2$d characters in length', 'finch-form' ),
				self::MESSAGE_MIN,
				self::MESSAGE_MAX
			);
		}

		if ( ! empty( $errors ) ) {

			// DEBUG ↓↓↓
			Finch_Form_Logger::log( "Validation errors:", $errors, true );
			// DEBUG ↑↑↑

			$out['message'] = implode( ' ', $errors );
			$out['errors']  = $errors;
			wp_send_json( $out );
		}

		// 5. Turnstile (only after request looks like a valid submission)
		$opts = FINCH_FORM_Settings::get_options();
		if ( ! empty( $opts['turnstile_secret_key'] ) ) {
			$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			$result = FINCH_FORM_Turnstile::verify( $token, $opts['turnstile_secret_key'], $ip );
			if ( ! $result['success'] ) {
				$out['message'] = __( 'Verification failed. Please complete the challenge and try again.', 'finch-form' );
				wp_send_json( $out );
			}
		}

		// 6. Send email via wp_mail (uses whatever mail transport is configured: PHP mail(), or an SMTP/transactional plugin if installed)
		$recipient = ! empty( $opts['recipient_email'] ) ? $opts['recipient_email'] : get_option( 'admin_email' );
		$recipient = sanitize_email( $recipient );
		if ( ! is_email( $recipient ) ) {
			$out['message'] = __( 'Server configuration error. Please try again later.', 'finch-form' );
			wp_send_json( $out );
		}

		// Safe email: do not spoof From. Use site default From and put customer in Reply-To.
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject_line = sprintf(
			/* translators: 1: site name, 2: subject line from form */
			__( '[%1$s] Contact: %2$s', 'finch-form' ),
			$site_name,
			$subject ? $subject : __( '(No subject)', 'finch-form' )
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
			$out['message'] = __( 'Your message has been sent 🚀. We aim to respond within 24 hours.', 'finch-form' );
		} else {
			$out['message'] = __( 'Sorry, we could not send your message at the moment. Please try again later.', 'finch-form' );
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
		$opts = FINCH_FORM_Settings::get_options();
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
		$opts = FINCH_FORM_Settings::get_options();
		$max  = isset( $opts['rate_limit_per_min'] ) ? (int) $opts['rate_limit_per_min'] : 3;
		$key  = self::RATE_LIMIT_TRANSIENT_PREFIX . md5( $ip );
		$count = (int) get_transient( $key );
		$count++;
		set_transient( $key, $count, MINUTE_IN_SECONDS );
	}
}
