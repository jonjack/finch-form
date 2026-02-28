<?php
/**
 * Shortcode [finch_contact_form] and WPBakery integration.
 *
 * @package Finch_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Finch_Form_Shortcode
 */
class Finch_Form_Shortcode {

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
			'finch-form-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			array(),
			null,
			true
		);
		wp_register_script(
			'finch-form',
			FINCH_FORM_PLUGIN_URL . 'assets/js/finch-form.js',
			array( 'jquery' ),
			FINCH_FORM_VERSION,
			true
		);
		wp_register_style(
			'finch-form',
			FINCH_FORM_PLUGIN_URL . 'assets/css/finch-form.css',
			array(),
			FINCH_FORM_VERSION
		);
	}

	/**
	 * Enqueue form and Turnstile assets and localize script.
	 */
	public static function enqueue_assets() {
		$opts = Finch_Form_Settings::get_options();
		if ( ! empty( $opts['turnstile_site_key'] ) ) {
			wp_enqueue_script( 'finch-form-turnstile' );
		}
		wp_enqueue_script( 'finch-form' );
		wp_enqueue_style( 'finch-form' );
		wp_localize_script( 'finch-form', 'finchForm', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => Finch_Form_Handler::AJAX_ACTION,
			'nonce'   => wp_create_nonce( Finch_Form_Handler::NONCE_ACTION ),
			'limits'  => array(
				'nameMin'    => Finch_Form_Handler::NAME_MIN,
				'nameMax'    => Finch_Form_Handler::NAME_MAX,
				'emailMin'   => Finch_Form_Handler::EMAIL_MIN,
				'emailMax'   => Finch_Form_Handler::EMAIL_MAX,
				'subjectMin' => Finch_Form_Handler::SUBJECT_MIN,
				'subjectMax' => Finch_Form_Handler::SUBJECT_MAX,
				'messageMin' => Finch_Form_Handler::MESSAGE_MIN,
				'messageMax' => Finch_Form_Handler::MESSAGE_MAX,
			),
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

		$opts = Finch_Form_Settings::get_options();
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
		<div class="finch-form-wrapper">
			<div class="finch-form-feedback-block" id="finch-form-feedback-block" aria-live="polite" role="status"></div>
			<form class="finch-form-form" id="finch-form-form" method="post" action="" novalidate>
				<?php wp_nonce_field( Finch_Form_Handler::NONCE_ACTION, 'finch_form_nonce', true ); ?>
				<!-- Spam tripwire: leave empty; bots often fill hidden fields -->
				<div class="finch-form-field finch-form-tripwire" aria-hidden="true">
					<label for="finch_website_url"><?php esc_html_e( 'Website', 'finch-form' ); ?></label>
					<input type="text" name="<?php echo esc_attr( Finch_Form_Handler::TRIPWIRE_FIELD ); ?>" id="finch_website_url" value="" tabindex="-1" autocomplete="off" />
				</div>

				<div class="finch-form-field">
					<label for="finch_name"><?php esc_html_e( 'Name', 'finch-form' ); ?> <span class="required">*</span></label>
					<input type="text" name="finch_name" id="finch_name" required minlength="<?php echo esc_attr( (string) Finch_Form_Handler::NAME_MIN ); ?>" maxlength="<?php echo esc_attr( (string) Finch_Form_Handler::NAME_MAX ); ?>" value="" />
				</div>

				<div class="finch-form-field">
					<label for="finch_email"><?php esc_html_e( 'Email', 'finch-form' ); ?> <span class="required">*</span></label>
					<input type="email" name="finch_email" id="finch_email" required minlength="<?php echo esc_attr( (string) Finch_Form_Handler::EMAIL_MIN ); ?>" maxlength="<?php echo esc_attr( (string) Finch_Form_Handler::EMAIL_MAX ); ?>" value="" />
				</div>

				<div class="finch-form-field">
					<label for="finch_subject"><?php esc_html_e( 'Subject', 'finch-form' ); ?></label>
					<?php if ( ! empty( $subjects ) ) : ?>
						<select name="finch_subject" id="finch_subject" class="finch-form-select">
							<option value=""><?php esc_html_e( 'Please select…', 'finch-form' ); ?></option>
							<?php foreach ( $subjects as $subject ) : ?>
								<option value="<?php echo esc_attr( $subject ); ?>"><?php echo esc_html( $subject ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="text" name="finch_subject" id="finch_subject" minlength="<?php echo esc_attr( (string) Finch_Form_Handler::SUBJECT_MIN ); ?>" maxlength="<?php echo esc_attr( (string) Finch_Form_Handler::SUBJECT_MAX ); ?>" value="" />
					<?php endif; ?>
				</div>

				<div class="finch-form-field">
					<label for="finch_message"><?php esc_html_e( 'Message', 'finch-form' ); ?> <span class="required">*</span></label>
					<textarea name="finch_message" id="finch_message" required minlength="<?php echo esc_attr( (string) Finch_Form_Handler::MESSAGE_MIN ); ?>" maxlength="<?php echo esc_attr( (string) Finch_Form_Handler::MESSAGE_MAX ); ?>" rows="5"></textarea>
				</div>

				<?php if ( $site_key ) : ?>
					<div class="finch-form-field finch-form-turnstile-wrap">
						<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-theme="light" data-size="normal"></div>
					</div>
				<?php endif; ?>

				<div class="finch-form-field finch-form-actions">
					<button type="submit" class="finch-form-submit"><?php esc_html_e( 'Send message', 'finch-form' ); ?></button>
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
			'name'        => __( 'Finch Contact Form', 'finch-form' ),
			'base'       => self::SHORTCODE,
			'icon'       => 'dashicons-email-alt',
			'category'   => __( 'Content', 'finch-form' ),
			'description' => __( 'Secure contact form with Turnstile and AJAX submission.', 'finch-form' ),
			'params'     => array(),
		) );
	}
}
