<?php
/**
 * Template for generating HTML-based mails.
 *
 * @package FINCH_FORM
 */

defined( 'ABSPATH' ) || exit;

class Finch_Form_Email_Template {

	/**
	 * Build a professional HTML email (card style) and return:
	 *  - subject
	 *  - html body
	 *  - text body (fallback)
	 *  - headers
	 */
	public static function build( array $args ) : array {
		$defaults = array(
			'title'        => 'Finch Form Submission',
			'intro'        => '',               // optional small paragraph
			'fields'       => array(),          // array of rows: [ ['label'=>'Name','value'=>'Jon'], ... ]
			'footer'       => 'Sent from your website contact form',
			'brand_color'  => '#111111',
			'width'        => 600,
			'from_name'    => get_bloginfo( 'name' ),
			'from_email'   => self::default_from_email(),
			'reply_to'     => '',               // optional (e.g., submitter email)
			'charset'      => get_bloginfo( 'charset' ) ?: 'UTF-8',
		);

		$a = wp_parse_args( $args, $defaults );

		$html  = self::render_html( $a );
		$text  = self::render_text( $a );

		$headers = array(
			'Content-Type: text/html; charset=' . $a['charset'],
			sprintf( 'From: %s <%s>', self::sanitize_header_text( $a['from_name'] ), sanitize_email( $a['from_email'] ) ),
		);

		if ( ! empty( $a['reply_to'] ) && is_email( $a['reply_to'] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $a['reply_to'] );
		}

		return array(
			'html'    => $html,
			'text'    => $text,
			'headers' => $headers,
		);
	}

	private static function render_html( array $a ) : string {
		$title = esc_html( $a['title'] );
		$intro = $a['intro'] !== '' ? '<p style="margin:0 0 18px 0; font-size:14px; color:#333; line-height:1.5;">' . esc_html( $a['intro'] ) . '</p>' : '';

		$rows_html = '';
        foreach ( (array) $a['fields'] as $row ) {

            $type  = isset( $row['type'] ) ? (string) $row['type'] : 'row';
            $label = isset( $row['label'] ) ? esc_html( $row['label'] ) : '';
            $value = $row['value'] ?? '';

            // Convert arrays/objects to readable text safely.
            if ( is_array( $value ) || is_object( $value ) ) {
                $value = wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            }

            // Escape and preserve line breaks
            $value_html = nl2br( esc_html( (string) $value ) );

            if ( $type === 'block' ) {
                // Full-width “label + big content below” row (ideal for long message)
                $rows_html .= '
                    <tr>
                        <td colspan="2" style="padding:10px 0;">
                            <div style="font-weight:bold; color:#111; font-size:14px; margin:0 0 6px 0;">' . $label . '</div>
                            <div style="color:#333; font-size:14px; line-height:1.5; white-space:normal;">' . $value_html . '</div>
                        </td>
                    </tr>
                ';
                continue;
            }

            // Default 2-column row
            $rows_html .= '
                <tr>
                    <td width="140" valign="top" style="padding:10px 0; font-weight:bold; color:#111; font-size:14px;">' . $label . '</td>
                    <td style="padding:10px 0; color:#333; font-size:14px; line-height:1.5;">' . $value_html . '</td>
                </tr>
            ';
        }

		$width = (int) $a['width'];
		$brand = esc_attr( $a['brand_color'] );
		$footer = esc_html( $a['footer'] );

		return '
<!doctype html>
<html>
<head>
  <meta charset="' . esc_attr( $a['charset'] ) . '">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
      <td align="center" style="padding:40px 12px;">
        <table width="' . $width . '" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border:1px solid #e5e5e5;border-radius:8px; overflow:hidden;">
          <tr>
            <td style="background:' . $brand . ';color:#fff;padding:20px;">
              <h1 style="margin:0;font-size:18px;line-height:1.3;">' . $title . '</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              ' . $intro . '
              <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;">
                ' . $rows_html . '
              </table>
            </td>
          </tr>
          <tr>
            <td style="background:#f0f0f0;padding:14px 18px;font-size:12px;color:#777;">
              ' . $footer . '
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
';
	}

	private static function render_text( array $a ) : string {
		$lines   = array();
		$lines[] = $a['title'];
		$lines[] = str_repeat( '-', 40 );

		if ( $a['intro'] !== '' ) {
			$lines[] = $a['intro'];
			$lines[] = '';
		}

		foreach ( (array) $a['fields'] as $row ) {
            $type  = isset( $row['type'] ) ? (string) $row['type'] : 'row';
            $label = isset( $row['label'] ) ? (string) $row['label'] : '';
            $value = $row['value'] ?? '';
        
            if ( is_array( $value ) || is_object( $value ) ) {
                $value = wp_json_encode( $value, JSON_UNESCAPED_SLASHES );
            }
        
            if ( $type === 'block' ) {
                $lines[] = '';
                $lines[] = $label . ':';
                $lines[] = (string) $value;
                continue;
            }
        
            $lines[] = $label . ': ' . (string) $value;
        }

		$lines[] = '';
		$lines[] = $a['footer'];

		return implode( "\n", $lines );
	}

	private static function default_from_email() : string {
		// Use WordPress default convention: wordpress@yourdomain
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		$domain = $domain ? preg_replace( '/^www\./', '', $domain ) : 'example.com';
		return 'wordpress@' . $domain;
	}

	private static function sanitize_header_text( string $text ) : string {
		// Prevent header injection/newlines
		$text = wp_strip_all_tags( $text );
		return trim( preg_replace( "/[\r\n]+/", ' ', $text ) );
	}
}