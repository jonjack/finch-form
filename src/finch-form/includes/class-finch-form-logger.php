<?php
/**
 * Logging utility for plugin.
 *
 * @package Finch_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Finch_Form_Logger
 */
final class Finch_Form_Logger {

	private const DIR_NAME       = 'finch-form-plugin';
	private const FILE_NAME      = 'finch-form.log';
	private const URI_MAX_LENGTH = 200;

	/** @var string|null Empty string means "path unresolved (e.g. mkdir failed)". */
	private static $log_file = null;

	private static function enabled(): bool {
		$opts = Finch_Form_Settings::get_options();
		return ! empty( $opts['logging_enabled'] );
	}

	/**
	 * Get path to log file. Returns empty string if directory could not be created.
	 *
	 * @return string Full path to log file, or '' if logging is not possible.
	 */
	private static function log_file_path(): string {
		if ( self::$log_file !== null ) {
			return self::$log_file;
		}

		$uploads = wp_get_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::DIR_NAME;

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			self::$log_file = '';
			return '';
		}

		self::protect_dir( $dir );

		self::$log_file = trailingslashit( $dir ) . self::FILE_NAME;
		return self::$log_file;
	}

	private static function protect_dir( string $dir ): void {
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return;
		}

		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX );
		}

		// Apache 2.2 and 2.4; no harm on Nginx.
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$content = "Deny from all\nRequire all denied\n";
			@file_put_contents( $htaccess, $content, LOCK_EX );
		}
	}

	/**
	 * Truncate REQUEST_URI for log meta to avoid huge lines.
	 *
	 * @param string $uri Raw REQUEST_URI.
	 * @return string Truncated string.
	 */
	private static function truncate_uri( string $uri ): string {
		if ( strlen( $uri ) <= self::URI_MAX_LENGTH ) {
			return $uri;
		}
		return substr( $uri, 0, self::URI_MAX_LENGTH ) . '...';
	}

	private static function stringify( $value, bool $pretty = false ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

		if ( $pretty ) {
			$flags |= JSON_PRETTY_PRINT;
		}

		$json = wp_json_encode( $value, $flags );

		if ( $json !== false ) {
			return $json;
		}

		return print_r( $value, true );
	}

	/**
	 * Write to finch-form.log if logging is enabled in plugin settings.
	 *
	 * @param string $message Message to log.
	 * @param mixed  $context Optional context (array/object/string).
	 * @param bool   $pretty Optional. Whether to pretty-print context (e.g. JSON). Default false.
	 */
	public static function log( string $message, $context = null, bool $pretty = false ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$file = self::log_file_path();
		if ( $file === '' ) {
			return;
		}

		$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );
		$meta     = array();

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$meta[] = 'AJAX';
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$meta[] = 'ip=' . sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		if ( ! empty( $_SERVER['REQUEST_METHOD'] ) ) {
			$meta[] = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) );
		}
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$meta[] = self::truncate_uri( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}

		$line = '[' . $timestamp . '] [Finch Form]' . ( $meta ? ' [' . implode( ' ', $meta ) . ']' : '' ) . ' ' . $message;

		if ( $context !== null ) {
			$line .= ' | context=' . self::stringify( $context, $pretty );
		}

		$line .= PHP_EOL;

		@file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
	}
}
