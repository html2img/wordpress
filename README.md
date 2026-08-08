# Auto OG Images - Open Graph & Social Image Generator by html2img

Generates a unique Open Graph image for every post and page through the [HTML to Image](https://html2img.com) API. Designs are rendered in a real Chrome browser, images land in the media library and the correct meta tags are output or handed to the active SEO plugin.

This is the source repository for the [html2img plugin on wordpress.org](https://wordpress.org/plugins/html2img/). For user documentation see the [WordPress integration guide](https://html2img.com/docs/integrations/wordpress/).

## How generation is decided

Every render stores two hashes in post meta:

- A **content hash** of the variable payload the design consumes (title, site name, author, excerpt, date, featured image identity). A save re-renders only when this hash changes, so unchanged saves never spend a credit.
- A **design fingerprint** of the active design, its template HTML, the customisation settings and the render dimensions. Posts whose stored fingerprint differs from the current one are stale. The Tools screen counts them and offers a bulk regeneration with a credit estimate before anything runs.

A render also happens when the stored attachment has been deleted. Nothing regenerates silently when the design changes.

## Template variables

Bundled designs and custom templates are plain HTML documents with placeholders:

| Placeholder | Value |
| --- | --- |
| `{{title}}` | Post title, entity decoded |
| `{{title_class}}` | `title-l`, `title-m`, `title-s` or `title-xs` by title length, for stepped font sizes |
| `{{site_name}}` | Site name, empty when hidden in settings |
| `{{tagline}}` | Site tagline |
| `{{author}}` | Author display name, empty when hidden in settings |
| `{{excerpt}}` | Manual excerpt or the first 28 words of the content |
| `{{date}}` | Post date in the site date format |
| `{{featured_image}}` | Data URI or URL of the featured image, empty when there is none |
| `{{logo}}` | Data URI or URL of the logo chosen in settings |
| `{{accent_color}}` | Validated hex colour |
| `{{background_color}}` | Validated hex colour |

Conditional sections show or hide markup by whether a value is empty:

```html
{{#author}}<span>{{author}}</span>{{/author}}
{{^featured_image}}<div class="fallback"></div>{{/featured_image}}
```

Text values are HTML escaped before substitution. Templates must be complete HTML documents, self contained apart from Google Fonts loaded via `link` tags. Size the page with `width: 100vw; height: 100vh` and use `vw` units throughout, and one template works at any configured dimensions.

## Hooks

### Filters

```php
// Change or extend the variables a design receives. Values added here
// join the content hash, so changes to them trigger re-renders exactly
// like core fields.
add_filter( 'html2img_variables', function ( array $variables, int $post_id ): array {
	$variables['price'] = get_post_meta( $post_id, '_price', true );
	return $variables;
}, 10, 2 );

// Final say on whether a post gets an image.
add_filter( 'html2img_should_generate', function ( bool $should, int $post_id, WP_Post $post ): bool {
	return $should && ! has_term( 'no-og', 'category', $post );
}, 10, 3 );

// Adjust the render arguments sent to the API: width, height, dpi, html.
add_filter( 'html2img_render_args', function ( array $args, int $post_id ): array {
	$args['dpi'] = 1;
	return $args;
}, 10, 2 );

// Register or replace designs. Each entry maps a slug to a name and the
// absolute path of a template file.
add_filter( 'html2img_designs', function ( array $designs ): array {
	$designs['brand'] = [
		'name' => 'Brand',
		'file' => get_stylesheet_directory() . '/og-designs/brand.html',
	];
	return $designs;
} );

// Change the render dimensions globally.
add_filter( 'html2img_dimensions', function ( array $dimensions ): array {
	return [ 'width' => 1200, 'height' => 630, 'dpi' => 1 ];
} );

// Cap for inlining images as data URIs, in bytes of the source file.
add_filter( 'html2img_inline_image_max_bytes', fn () => 2000000 );

// Provide the API key from configuration instead of the database.
add_filter( 'html2img_api_key', fn () => defined( 'HTML2IMG_API_KEY' ) ? HTML2IMG_API_KEY : '' );
```

### Actions

```php
// After an image was generated and stored.
add_action( 'html2img_after_generate', function ( int $post_id, int $attachment_id, array $response ): void {
	// $attachment_id is 0 in CDN storage mode.
	// $response is the full API response body.
}, 10, 3 );
```

## Post meta reference

All keys are prefixed `_html2img_` and hidden from custom fields: `image_id`, `cdn_url`, `render_id`, `expires_at`, `content_hash`, `fingerprint`, `generated_at`, `status`, `error`, `disabled`, `queued_at`. Generated attachments carry `_html2img_generated`.

## Development

```bash
composer install
vendor/bin/phpunit        # unit tests, no WordPress install needed
vendor/bin/phpcs          # WordPress Coding Standards
```

The test suite stubs the handful of WordPress functions the pure logic touches, so it runs in milliseconds without a database.

A local test site with ddev, mounting this directory as the plugin, is described in `PLAN.md`.

## API

The plugin talks to two endpoints of the [HTML to Image API](https://html2img.com/docs/): `GET /api/me` for account status and key validation and `POST /api/html` for renders. Authentication is an `X-API-Key` header. The key is stored in the `html2img_settings` option and never reaches the front end or any script context.

## Licence

GPL-2.0-or-later.
