<?php
/**
 * Admin settings for Finch Form (Turnstile keys, recipient, rate limit).
 *
 * @package Finch_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Finch_Forms_Settings
 */
class Finch_Forms_Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'finch_forms_settings';

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_' . FINCH_FORMS_PLUGIN_BASENAME, array( __CLASS__, 'settings_link' ) );
	}

	/**
	 * Add top-level admin menu.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'Finch Form', 'finch-forms' ),
			__( 'Finch Form', 'finch-forms' ),
			'manage_options',
			'finch-forms',
			array( __CLASS__, 'render_page' ),
			'dashicons-twitter', // Bird icon (dashicons code f301).
			56
		);
	}

	/**
	 * Register settings and sections.
	 */
	public static function register_settings() {
		register_setting(
			'finch_forms_options',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'finch_forms_turnstile',
			__( 'Cloudflare Turnstile', 'finch-forms' ),
			array( __CLASS__, 'section_turnstile_desc' ),
			'finch-forms',
			array( 'before_section' => '<div class="finch-forms-section">' )
		);

		add_settings_field(
			'turnstile_site_key',
			__( 'Site key', 'finch-forms' ),
			array( __CLASS__, 'field_text' ),
			'finch-forms',
			'finch_forms_turnstile',
			array(
				'label_for' => 'finch_forms_turnstile_site_key',
				'name'      => 'turnstile_site_key',
				'class'     => 'regular-text',
				'desc'      => __( 'Public key for the Turnstile widget (client-side).', 'finch-forms' ),
			)
		);

		add_settings_field(
			'turnstile_secret_key',
			__( 'Secret key', 'finch-forms' ),
			array( __CLASS__, 'field_text' ),
			'finch-forms',
			'finch_forms_turnstile',
			array(
				'label_for' => 'finch_forms_turnstile_secret_key',
				'name'      => 'turnstile_secret_key',
				'class'     => 'regular-text',
				'type'      => 'password',
				'desc'      => __( 'Private key for server-side token verification. Keep this secret.', 'finch-forms' ),
			)
		);

		add_settings_section(
			'finch_forms_email',
			__( 'Email', 'finch-forms' ),
			array( __CLASS__, 'section_email_desc' ),
			'finch-forms'
		);

		add_settings_field(
			'recipient_email',
			__( 'Recipient email', 'finch-forms' ),
			array( __CLASS__, 'field_text' ),
			'finch-forms',
			'finch_forms_email',
			array(
				'label_for' => 'finch_forms_recipient_email',
				'name'      => 'recipient_email',
				'class'     => 'regular-text',
				'type'      => 'email',
				'desc'      => __( 'Company email address that will receive form submissions. Sent via Zoho Mail if the Zoho Mail plugin is configured.', 'finch-forms' ),
			)
		);

		add_settings_section(
			'finch_forms_security',
			__( 'Security & rate limiting', 'finch-forms' ),
			array( __CLASS__, 'section_security_desc' ),
			'finch-forms'
		);

		add_settings_field(
			'rate_limit_per_min',
			__( 'Max submissions per minute (per IP)', 'finch-forms' ),
			array( __CLASS__, 'field_number' ),
			'finch-forms',
			'finch_forms_security',
			array(
				'label_for' => 'finch_forms_rate_limit_per_min',
				'name'      => 'rate_limit_per_min',
				'min'       => 1,
				'max'       => 30,
				'step'      => 1,
				'default'   => 3,
				'desc'      => __( 'Limit how many times one IP can submit the form per minute.', 'finch-forms' ),
			)
		);

		add_settings_section(
			'finch_forms_subjects',
			__( 'Subject options', 'finch-forms' ),
			array( __CLASS__, 'section_subjects_desc' ),
			'finch-forms'
		);

		add_settings_field(
			'subjects',
			__( 'Subject dropdown items', 'finch-forms' ),
			array( __CLASS__, 'field_subjects' ),
			'finch-forms',
			'finch_forms_subjects'
		);
	}

	/**
	 * Sanitize and validate settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return self::get_options();
		}

		$out = self::get_options();

		if ( isset( $input['turnstile_site_key'] ) ) {
			$out['turnstile_site_key'] = sanitize_text_field( $input['turnstile_site_key'] );
		}
		if ( isset( $input['turnstile_secret_key'] ) ) {
			$out['turnstile_secret_key'] = sanitize_text_field( $input['turnstile_secret_key'] );
		}
		if ( isset( $input['recipient_email'] ) ) {
			$email = sanitize_email( $input['recipient_email'] );
			$out['recipient_email'] = is_email( $email ) ? $email : $out['recipient_email'];
		}
		if ( isset( $input['rate_limit_per_min'] ) ) {
			$n = absint( $input['rate_limit_per_min'] );
			$out['rate_limit_per_min'] = max( 1, min( 30, $n ) );
		}

		// Subject dropdown items: up to 10 sentences, 10–100 characters each.
		if ( isset( $input['subjects'] ) && is_array( $input['subjects'] ) ) {
			$subjects = array();
			foreach ( $input['subjects'] as $subject ) {
				$subject = trim( wp_strip_all_tags( (string) $subject ) );
				$len     = strlen( $subject );
				if ( $len >= 10 && $len <= 100 ) {
					$subjects[] = $subject;
				}
				if ( count( $subjects ) >= 10 ) {
					break;
				}
			}
			$out['subjects'] = $subjects;
		}

		return $out;
	}

	/**
	 * Get current options with defaults.
	 *
	 * @return array
	 */
	public static function get_options() {
		$defaults = array(
			'turnstile_site_key'   => '',
			'turnstile_secret_key' => '',
			'recipient_email'      => get_option( 'admin_email', '' ),
			'rate_limit_per_min'   => 3,
			'subjects'             => array(),
		);
		$opts = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $opts, $defaults );
	}

	/**
	 * Section description for Turnstile.
	 */
	public static function section_turnstile_desc() {
		echo '<p>' . esc_html__( 'Configure Cloudflare Turnstile to protect the form from bots. Create a widget at dashboard.cloudflare.com and use the site key and secret key here.', 'finch-forms' ) . '</p>';
		echo '<p><a href="https://developers.cloudflare.com/turnstile/get-started/" target="_blank" rel="noopener">' . esc_html__( 'Turnstile documentation', 'finch-forms' ) . '</a></p>';
	}

	/**
	 * Section description for email.
	 */
	public static function section_email_desc() {
		echo '<p>' . esc_html__( 'Form submissions are sent using WordPress\'s wp_mail(). If you use the Zoho Mail plugin, emails will be sent through your Zoho Mail account.', 'finch-forms' ) . '</p>';
	}

	/**
	 * Section description for security.
	 */
	public static function section_security_desc() {
		echo '<p>' . esc_html__( 'Rate limiting and security options.', 'finch-forms' ) . '</p>';
	}

	/**
	 * Section description for subjects.
	 */
	public static function section_subjects_desc() {
		echo '<p>' . esc_html__( 'Configure up to 10 subject lines (10–100 characters each). When at least one subject is defined, the subject field on the form is shown as a dropdown instead of a free-text input.', 'finch-forms' ) . '</p>';
	}

	/**
	 * Render a text/password/email field.
	 *
	 * @param array $args Field args (name, class, type, desc).
	 */
	public static function field_text( $args ) {
		$opts   = self::get_options();
		$name   = $args['name'];
		$value  = isset( $opts[ $name ] ) ? $opts[ $name ] : '';
		$type   = isset( $args['type'] ) ? $args['type'] : 'text';
		$id     = isset( $args['label_for'] ) ? $args['label_for'] : 'finch_forms_' . $name;
		$class  = isset( $args['class'] ) ? $args['class'] : 'regular-text';
		$opt_name = self::OPTION_NAME;
		?>
		<input type="<?php echo esc_attr( $type ); ?>"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $opt_name ); ?>[<?php echo esc_attr( $name ); ?>]"
			value="<?php echo esc_attr( $value ); ?>"
			class="<?php echo esc_attr( $class ); ?>"
			autocomplete="<?php echo ( 'password' === $type ) ? 'off' : 'on'; ?>"
		/>
		<?php if ( ! empty( $args['desc'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
		<?php endif;
	}

	/**
	 * Render a number field.
	 *
	 * @param array $args Field args (name, min, max, step, default, desc).
	 */
	public static function field_number( $args ) {
		$opts    = self::get_options();
		$name    = $args['name'];
		$value   = isset( $opts[ $name ] ) ? $opts[ $name ] : ( isset( $args['default'] ) ? $args['default'] : '' );
		$id      = isset( $args['label_for'] ) ? $args['label_for'] : 'finch_forms_' . $name;
		$min     = isset( $args['min'] ) ? (int) $args['min'] : 0;
		$max     = isset( $args['max'] ) ? (int) $args['max'] : 999;
		$step    = isset( $args['step'] ) ? (int) $args['step'] : 1;
		$opt_name = self::OPTION_NAME;
		?>
		<input type="number"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $opt_name ); ?>[<?php echo esc_attr( $name ); ?>]"
			value="<?php echo esc_attr( $value ); ?>"
			min="<?php echo esc_attr( $min ); ?>"
			max="<?php echo esc_attr( $max ); ?>"
			step="<?php echo esc_attr( $step ); ?>"
			class="small-text"
		/>
		<?php if ( ! empty( $args['desc'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
		<?php endif;
	}

	/**
	 * Render the subjects field (list of sentences).
	 */
	public static function field_subjects() {
		$opts     = self::get_options();
		$subjects = isset( $opts['subjects'] ) && is_array( $opts['subjects'] ) ? $opts['subjects'] : array();
		$opt_name = self::OPTION_NAME;

		// Normalize to at most 10 entries.
		$subjects = array_slice( $subjects, 0, 10 );

		$max = 10;

		echo '<div class="finch-forms-subjects-wrapper" data-max="' . esc_attr( $max ) . '">';
		echo '<p class="description">' . esc_html__( 'Add preset subject lines. When at least one subject is defined, the subject field on the form is shown as a dropdown instead of a free-text input.', 'finch-forms' ) . '</p>';

		echo '<div class="finch-forms-subjects-input-row">';
		echo '<input type="text" class="regular-text finch-forms-subject-input" maxlength="100" />';
		echo ' <button type="button" class="button button-secondary finch-forms-subject-add">' . esc_html__( 'Add', 'finch-forms' ) . '</button>';
		echo '</div>';

		echo '<p class="description finch-forms-subject-help">' . esc_html__( 'Each subject must be between 10 and 100 characters. Up to 10 subjects can be added.', 'finch-forms' ) . '</p>';
		echo '<div class="finch-forms-subject-error" style="display:none;"></div>';

		echo '<p class="description"><strong>' . esc_html__( 'List of subject lines that will appear on the form:', 'finch-forms' ) . '</strong></p>';
		echo '<ul class="finch-forms-subject-list">';
		foreach ( $subjects as $subject ) {
			echo '<li class="finch-forms-subject-item">';
			echo '<span class="finch-forms-subject-text">' . esc_html( $subject ) . '</span> ';
			echo '<button type="button" class="button-link finch-forms-subject-remove" aria-label="' . esc_attr__( 'Remove subject', 'finch-forms' ) . '">×</button>';
			printf(
				'<input type="hidden" name="%1$s[subjects][]" value="%2$s" />',
				esc_attr( $opt_name ),
				esc_attr( $subject )
			);
			echo '</li>';
		}
		echo '</ul>';

		echo '</div>';
	}

	/**
	 * Enqueue admin assets for the settings screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		// The settings screen for add_menu_page with slug 'finch-forms' uses this hook.
		if ( 'toplevel_page_finch-forms' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'finch-forms-admin',
			FINCH_FORMS_PLUGIN_URL . 'assets/css/finch-forms-admin.css',
			array(),
			FINCH_FORMS_VERSION
		);

		wp_enqueue_script(
			'finch-forms-admin',
			FINCH_FORMS_PLUGIN_URL . 'assets/js/finch-forms-admin.js',
			array( 'jquery' ),
			FINCH_FORMS_VERSION,
			true
		);

		wp_localize_script(
			'finch-forms-admin',
			'finchFormsAdmin',
			array(
				'maxSubjects'        => 10,
				'subjectLengthError' => __( 'Each subject must be between 10 and 100 characters.', 'finch-forms' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap finch-forms-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'finch_forms_options' );
				do_settings_sections( 'finch-forms' );
				submit_button( __( 'Save settings', 'finch-forms' ) );
				?>
			</form>
			<hr />
			<h2><?php esc_html_e( 'Shortcode', 'finch-forms' ); ?></h2>
			<p><?php esc_html_e( 'Add the contact form to any page or post (including WPBakery) using this shortcode:', 'finch-forms' ); ?></p>
			<code>[finch_contact_form]</code>
		</div>
		<?php
	}

	/**
	 * Add settings link on plugins list.
	 *
	 * @param array $links Plugin row links.
	 * @return array
	 */
	public static function settings_link( $links ) {
		$url = admin_url( 'admin.php?page=finch-forms' );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'finch-forms' ) . '</a>';
		return $links;
	}
}
