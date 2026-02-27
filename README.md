# Finch Form

A secure WordPress contact form plugin with **Cloudflare Turnstile**, **rate limiting**, **CSRF protection**, and **Zoho Mail** integration. Submissions are sent via AJAX (no full page refresh) and can be edited in **WPBakery** (Visual Composer).

## Requirements

- WordPress 5.0+
- PHP 7.4+
- (Optional) [Zoho Mail for WordPress](https://wordpress.org/plugins/zoho-mail/) – to send emails through your Zoho Mail account
- (Optional) WPBakery Page Builder – to add the form via the page builder

## Installation

1. **Build the plugin zip** (from repo root):
   ```bash
   npm run build
   ```
   This creates a zip file in `dist/`, for example `dist/finch-form-1.0.0.zip`.

2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the generated zip from `dist/` (for example `dist/finch-form-1.0.0.zip`), then **Install Now** and **Activate**.

3. Go to the **Finch Form** menu in the WordPress admin sidebar and configure:
   - **Cloudflare Turnstile**: Site key and Secret key from [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/get-started/) (create a widget in the dashboard).
   - **Recipient email**: Your company email that will receive form messages.
   - **Rate limit**: Max submissions per minute per IP (default 3).

4. Add the form to a page:
   - **Shortcode**: `[finch_contact_form]`
   - **WPBakery**: Add element **“Finch Contact Form”** from the content elements.

## Security

- **CSRF**: WordPress nonce on every submission.
- **Sanitization & validation**: All inputs sanitized and validated (name, email, subject, message length limits).
- **Rate limiting**: Per-IP limit (configurable) to reduce abuse.
- **Spam tripwire**: Hidden field that must stay empty (bots often fill it).
- **Turnstile**: Server-side token verification via Cloudflare SiteVerify API; tokens are single-use and expire after 5 minutes.
- **Email**: No “From” spoofing – mail is sent with the site’s default From and the sender’s address in **Reply-To**, so it works with Zoho Mail and reduces spoofing risk.

## Email (Zoho Mail)

Finch Form uses WordPress’s `wp_mail()`. If the **Zoho Mail** plugin is installed and configured, all outgoing mail (including form submissions) is sent through your Zoho Mail account. No extra configuration is needed in Finch Form beyond setting the **Recipient email** in the Finch Form settings page.

## Project structure

- **`src/finch-forms/`** – Plugin source (this is what gets zipped).
- **`dist/`** – Build output; contains versioned plugin zips such as `finch-form-1.0.0.zip`.
- **`scripts/build.sh`** – Build script that zips `src/finch-forms` → `dist/finch-form-x.y.z.zip`.

## Build

```bash
npm run build
# or
./scripts/build.sh
```

## Development

- Main plugin file: `src/finch-forms/finch-forms.php`
- Settings: `includes/class-finch-forms-settings.php`
- Turnstile verification: `includes/class-finch-forms-turnstile.php`
- Form handler (AJAX, validation, email): `includes/class-finch-forms-form-handler.php`
- Shortcode & WPBakery: `includes/class-finch-forms-shortcode.php`
- Front-end: `assets/js/finch-forms.js`, `assets/css/finch-forms.css`

## License

GPL v2 or later.
