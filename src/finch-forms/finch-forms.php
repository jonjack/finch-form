<?php
/**
 * Plugin Name: Finch Form
 * Plugin URI: https://github.com/your-repo/wp-finch-forms
 * Description: Secure contact form with Cloudflare Turnstile, rate limiting, and Zoho Mail integration.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: finch-forms
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'FINCH_FORMS_VERSION', '1.0.0' );
define( 'FINCH_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FINCH_FORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FINCH_FORMS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once FINCH_FORMS_PLUGIN_DIR . 'includes/class-finch-forms.php';

add_action( 'plugins_loaded', array( 'Finch_Forms', 'init' ) );

register_activation_hook( __FILE__, array( 'Finch_Forms', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Finch_Forms', 'deactivate' ) );
