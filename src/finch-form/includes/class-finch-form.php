<?php
/**
 * Main plugin class for Finch Form.
 *
 * @package Finch_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Finch_Form
 */
final class Finch_Form {

	/**
	 * Single instance.
	 *
	 * @var Finch_Form|null
	 */
	private static $instance = null;

	/**
	 * Get the single instance.
	 *
	 * @return Finch_Form
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		require_once FINCH_FORM_PLUGIN_DIR . 'includes/class-finch-form-settings.php';
		require_once FINCH_FORM_PLUGIN_DIR . 'includes/class-finch-form-logger.php';
		require_once FINCH_FORM_PLUGIN_DIR . 'includes/class-finch-form-turnstile.php';
		require_once FINCH_FORM_PLUGIN_DIR . 'includes/class-finch-form-handler.php';
		require_once FINCH_FORM_PLUGIN_DIR . 'includes/class-finch-form-shortcode.php';
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			Finch_Form_Settings::init();
		}

		Finch_Form_Handler::init();
		Finch_Form_Shortcode::init();
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'finch-form',
			false,
			dirname( FINCH_FORM_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		// Set default options if needed.
		$opts = get_option( 'finch_form_settings', array() );
		if ( empty( $opts ) ) {
			add_option( 'finch_form_settings', array(
				'turnstile_site_key'   => '',
				'turnstile_secret_key' => '',
				'recipient_email'      => get_option( 'admin_email' ),
				'rate_limit_per_min'   => 3,
				'subjects'             => array(),
			) );
		}
		// Flush rewrite rules if we add any.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
