<?php
/**
 * Shortcode [finch_contact_form] and WPBakery integration.
 *
 * @package Finch_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Finch_Forms_Shortcode
 */
class Finch_Forms_Shortcode {

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	const SHORTCODE = 'finch_contact_form';

	/**
	 * Initialize shortcode and front-end assets.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_enqueue_assets' ), 5 );
		add_action( 'vc_before_init', array( __CLASS__, 'register_wpbakery_element' ) );
	}

	/**
	 * Register scripts and styles (call early so other code can enqueue).
	 */
	public static function register_assets() {
		wp_register_script(
			'finch-forms-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			array(),
			null,
			true
		);
		wp_register_script(
			'finch-forms',
			FINCH_FORMS_PLUGIN_URL . 'assets/js/finch-forms.js',
			array( 'jquery' ),
			FINCH_FORMS_VERSION,
			true
		);
		wp_register_style(
			'finch-forms',
			FINCH_FORMS_PLUGIN_URL . 'assets/css/finch-forms.css',
			array(),
			FINCH_FORMS_VERSION
		);
	}

	/**
	 * Enqueue form and Turnstile assets and localize script.
	 */
	public static function enqueue_assets() {
		$opts = Finch_Forms_Settings::get_options();
		if ( ! empty( $opts['turnstile_site_key'] ) ) {
			wp_enqueue_script( 'finch-forms-turnstile' );
		}
		wp_enqueue_script( 'finch-forms' );
		wp_enqueue_style( 'finch-forms' );
		wp_localize_script( 'finch-forms', 'finchForms', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => Finch_Forms_Form_Handler::AJAX_ACTION,
			'nonce'   => wp_create_nonce( Finch_Forms_Form_Handler::NONCE_ACTION ),
		) );
	}

	/**
	 * Enqueue assets in footer only when shortcode was used (for late-bound content).
	 */
	public static function maybe_enqueue_assets() {
		// No-op: we enqueue directly in render() when shortcode is output.
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array $atts Shortcode attributes (unused for now).
	 * @return string
	 */
	public static function render( $atts = array() ) {
		// Enqueue assets when shortcode is actually rendered.
		self::enqueue_assets();

		$opts = Finch_Forms_Settings::get_options();
		$site_key = ! empty( $opts['turnstile_site_key'] ) ? $opts['turnstile_site_key'] : '';
		$subjects = array();
		if ( ! empty( $opts['subjects'] ) && is_array( $opts['subjects'] ) ) {
			foreach ( $opts['subjects'] as $subject ) {
				$subject = trim( (string) $subject );
				if ( $subject !== '' ) {
					$subjects[] = $subject;
				}
			}
		}

		ob_start();
		?>
		<div class="finch-forms-wrapper">
			<div class="finch-forms-feedback-block" id="finch-forms-feedback-block" aria-live="polite" role="status"></div>
			<form class="finch-forms-form" id="finch-forms-form" method="post" action="" novalidate>
				<?php wp_nonce_field( Finch_Forms_Form_Handler::NONCE_ACTION, 'finch_forms_nonce', true ); ?>
				<!-- Spam tripwire: leave empty; bots often fill hidden fields -->
				<div class="finch-forms-field finch-forms-tripwire" aria-hidden="true">
					<label for="finch_website_url"><?php esc_html_e( 'Website', 'finch-forms' ); ?></label>
					<input type="text" name="<?php echo esc_attr( Finch_Forms_Form_Handler::TRIPWIRE_FIELD ); ?>" id="finch_website_url" value="" tabindex="-1" autocomplete="off" />
				</div>

				<div class="finch-forms-field">
					<label for="finch_name"><?php esc_html_e( 'Name', 'finch-forms' ); ?> <span class="required">*</span></label>
					<input type="text" name="finch_name" id="finch_name" required maxlength="200" value="" />
				</div>

				<div class="finch-forms-field">
					<label for="finch_email"><?php esc_html_e( 'Email', 'finch-forms' ); ?> <span class="required">*</span></label>
					<input type="email" name="finch_email" id="finch_email" required value="" />
				</div>

				<div class="finch-forms-field">
					<label for="finch_subject"><?php esc_html_e( 'Subject', 'finch-forms' ); ?></label>
					<?php if ( ! empty( $subjects ) ) : ?>
						<select name="finch_subject" id="finch_subject" class="finch-forms-select">
							<option value=""><?php esc_html_e( 'Please select…', 'finch-forms' ); ?></option>
							<?php foreach ( $subjects as $subject ) : ?>
								<option value="<?php echo esc_attr( $subject ); ?>"><?php echo esc_html( $subject ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="text" name="finch_subject" id="finch_subject" maxlength="500" value="" />
					<?php endif; ?>
				</div>

				<div class="finch-forms-field">
					<label for="finch_message"><?php esc_html_e( 'Message', 'finch-forms' ); ?> <span class="required">*</span></label>
					<textarea name="finch_message" id="finch_message" required rows="5" maxlength="10000"></textarea>
				</div>

				<?php if ( $site_key ) : ?>
					<div class="finch-forms-field finch-forms-turnstile-wrap">
						<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-theme="light" data-size="normal"></div>
					</div>
				<?php endif; ?>

				<div class="finch-forms-field finch-forms-actions">
					<button type="submit" class="finch-forms-submit"><?php esc_html_e( 'Send message', 'finch-forms' ); ?></button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Register WPBakery (Visual Composer) element so the shortcode appears in the page builder.
	 */
	public static function register_wpbakery_element() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}
		vc_map( array(
			'name'        => __( 'Finch Contact Form', 'finch-forms' ),
			'base'       => self::SHORTCODE,
			'icon'       => 'dashicons-email-alt',
			'category'   => __( 'Content', 'finch-forms' ),
			'description' => __( 'Secure contact form with Turnstile and AJAX submission.', 'finch-forms' ),
			'params'     => array(),
		) );
	}
}
