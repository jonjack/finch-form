![](src/finch-form/assets/icon-256x256.png)

# Finch Form

A secure WordPress contact form plugin with **Cloudflare Turnstile** support, **rate limiting** and **CSRF protection**.

- Integrates with all standard email plugins such as Zoho, WP Mail SMTP, SendGrid. 
- Client side and server side validation.
- Configurable mail Subject list.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- A plugin to handle SMTP such as Zoho, WP Mail SMTP, SendGrid.
- A [Cloudflare account](https://dash.cloudflare.com/sign-up) and [Turnstile](https://developers.cloudflare.com/turnstile/) widget. Note that this is all [Free](https://www.cloudflare.com/en-gb/application-services/products/turnstile/#turnstile-pricing) unless you are a large enterprise.


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
   - **Rate limit**: Max email  submissions per minute per IP address (default 3).

4. Add the form to a page:
   - **Shortcode**: `[finch_contact_form]`
   - If using **WPBakery**: Add element **“Finch Contact Form”** from the content elements.

## Security

- **CSRF**: WordPress nonce on every submission.
- **Sanitization & validation**: All inputs sanitized and validated (name, email, subject, message length limits).
- **Rate limiting**: Per-IP limit (configurable) to reduce abuse.
- **Spam tripwire**: Hidden field that must stay empty (bots often fill it).
- **Turnstile**: Server-side token verification via Cloudflare SiteVerify API; tokens are single-use and expire after 5 minutes.
- **Email**: No “From” spoofing – mail is sent with the site’s default From and the sender’s address in **Reply-To**, so it works with Zoho Mail and reduces spoofing risk.

## Handling Email/SMTP

Finch Form uses WordPress’s generic `wp_mail()` interface. So, as long as you have another plugin installed (and configured) which handles SMTP (such as Zoho Mail, WP Mail SMTP, SendGrid etc) no extra configuration is needed in Finch Form beyond setting the **Recipient email** in the Finch Form settings page.

## Project structure

- **`src/finch-form/`** – Plugin source (this is what gets zipped).
- **`dist/`** – Build output; contains versioned plugin zips such as `finch-form-1.0.0.zip`.
- **`scripts/build.sh`** – Build script that zips `src/finch-form` → `dist/finch-form-x.y.z.zip`.

## Build

```bash
npm run build
# or
./scripts/build.sh
```

## Development

- Main plugin file: `src/finch-form/finch-form.php`
- Settings: `includes/class-finch-form-settings.php`
- Logging: `includes/class-finch-form-logger.php`
- Turnstile verification: `includes/class-finch-form-turnstile.php`
- Form handler (AJAX, validation, email): `includes/class-finch-form-form-handler.php`
- Shortcode & WPBakery: `includes/class-finch-form-shortcode.php`
- Front-end: `assets/js/finch-form.js`, `assets/css/finch-form.css`

