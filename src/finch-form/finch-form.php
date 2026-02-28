<?php
/**
 * Plugin Name: Finch Form
 * Plugin URI: https://github.com/jonjack/finch-form
 * Description: Secure contact form with Cloudflare Turnstile, rate limiting, and all Email integration plugins (Zoho, WP Mail SMTP, SendGrid, etc.).
 * Version: 1.0.5
 * Author: Jon Jackson
 * Text Domain: finch-form
 */

defined( 'ABSPATH' ) || exit;

define( 'FINCH_FORM_VERSION', '1.0.5' );
define( 'FINCH_FORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FINCH_FORM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FINCH_FORM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once FINCH_FORM_PLUGIN_DIR . 'includes/class-finch-form.php';

add_action( 'plugins_loaded', array( 'FINCH_FORM', 'init' ) );

register_activation_hook( __FILE__, array( 'FINCH_FORM', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FINCH_FORM', 'deactivate' ) );
