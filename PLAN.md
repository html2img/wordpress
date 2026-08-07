# Plan: HTML to Image WordPress plugin

Findings from the Laravel app, the public docs and the sibling integrations, followed by the resolved position on every decision in the brief. Written before any plugin code.

## 1. What the API actually offers

Verified against `~/sites/html-to-image` and https://html2img.com/docs, then confirmed live with the test key.

### Auth and account status

- Base URL `https://app.html2img.com/api`. Direct keys go in an `X-API-Key` header. The `X-RapidAPI-Key` scheme in `openapi.yaml` is the RapidAPI channel only and does not apply to us.
- `GET /me` (routes/api.php, MeController) returns `email`, `plan`, `plan_name`, `active`, `free_plan`, `credits_remaining`, `credits_reset_at`. It is free to call, sends `Cache-Control: no-store` and stays reachable with zero credits because it sits behind `api.key` rather than `api.credits`. The docs at /docs/account/ say outright that it exists for key validation and pre-batch checks. **The credits endpoint gap the brief anticipated does not exist.** Credit management can be built fully.

### Render endpoints

- `POST /html` renders a complete HTML document. Fields: `html` (required), `css`, `width`, `height` (1 to 5000), `fullpage`, `dpi` (1 to 4), `webhook_url`, `ms_delay`, `wait_for_selector`, `format` (png or pdf), `scale_to_fit`.
- `POST /v1/templates/{slug}` renders hosted templates. The `open-graph-image` template takes `title`, `subtitle`, `author_name`, `author_avatar_url`, `logo_url`, `background_color`, `accent_color`. No featured image input, which rules it out as our primary path (see decision 3.5).
- `POST /screenshot` captures live URLs. Not usable here: it cannot reach local or private sites and it captures the page, not a card.
- Success envelope: `success`, `id` (uuid), `url` (i.html2img.com PNG), `expires_at`, and `credits_remaining` on direct-key calls. Confirmed live.
- Errors: 400 `validation_error` (details map), 401 `missing_api_key` / `invalid_api_key`, 402 `insufficient_credits` (with `credits_remaining`, plus `upgrade_url` on free and `credits_reset_at` on paid), 403 `not_subscribed`, 504 `timeout_error` / `api_timeout_error`, 500 `service_error`. Every failure path in the client maps to one of these codes.
- No free preview or dry-run mode exists (/docs/testing/ is explicit: test in your own browser first). Every successful render costs one credit. This settles the preview design (decision 3.5).
- Payload limits: no application-level cap on `html` length. The renderer is a Lambda behind HTTP with the usual 6 MB invoke ceiling, plus whatever the web server allows. We stay conservative and cap inlined images well below that.

### Free tier and expiry

- Free tier is 50 one-time credits (`config/settings.php`, `free_tier_credits`). No renewal, so `credits_reset_at` is null on free.
- Free-plan renders get `expires_at` seven days out and an hourly purge job deletes them from R2 and the CDN edge. Enforcement starts 2026-08-17 (`expiry_enforcement_from`), so renders made before that date carry `expires_at: null` even on free. Paid renders persist, and upgrading clears expiry on everything not yet purged.
- Pricing for copy: free 50 credits one time, then $9/month for 1,000 credits up to $300/month for 100,000.

### Test key caveat

The key Mike provided belongs to an account on the app's admin list, so renders bypass billing and credit checks entirely. Good: unlimited live testing. Bad: 402 and low-credit paths can never fire on this key, so those are covered by mocked unit tests and manual verification of the code paths.

## 2. What the siblings do

### Craft plugin (html2img/html2img-craft)

Read in full. The relevant architecture:

- Raw `/html` endpoint only, payload `html`, `width`, `height`, `dpi`, `fullpage: false`, `format: png`, auth `X-API-Key`. No hosted templates.
- Regeneration decision: `sha256(json_encode([$html, $width, $height, $dpi]))` stored per entry. Hashing the fully rendered HTML means design edits are caught implicitly. The skip check also verifies the stored image still exists, so a deleted asset forces a re-render despite a matching hash.
- One bundled design, deliberately self-contained (Google Fonts only, no local assets), all sizes in vw units so one template scales to any dimensions, headline font size stepped down by title length with line clamps as backstop.
- Storage: CDN hotlink by default or download into an asset volume, replacing the existing asset file in place.
- Preview costs nothing: the design HTML is rendered by the browser in an iframe, since the API renders in real Chrome anyway. The per-entry Generate button always forces a real render, as a parity check.
- A re-entrancy guard (`$suppressListeners` in try/finally) around SEO field writes stops save loops.
- Gaps it never closed, all confirmed absent: no `/me` key validation, no credit surfacing, no 402 handling in the queue, no free-plan expiry handling. CDN mode would rot silently on a free account. The WordPress plugin closes all four.

### Statamic addon (html2img/statamic-og-images)

Read in full. Same shape as Craft with a few differences that matter:

- Also raw `/html` only, payload `html`, `width`, `height`, `dpi`, default dpi 2. The SDK supports hosted templates and screenshots; the addon uses neither.
- Hash: `sha1` of resolved template name, dimensions string and the full rendered HTML. No separate design fingerprint, so "how many posts are stale" is unanswerable there. Our two-hash split exists precisely to answer it.
- Skips generation when the editor filled a custom social image field. Same stance we take with SEO plugin manual images.
- Preview is a free server-rendered HTML page in an iframe, generation is the only credit spend. Confirms the preview design.
- Known race: consecutive saves queue duplicate jobs and only the input hash prevents double spend, and only if the first job finished before the second reads state. We add a per-post lock (see 3.4).
- Same four gaps as Craft: no key validation, no credit surfacing, no differentiated error handling (a 402 fails silently in the queue) and no expiry handling. All four are closed here.

### The WordPress article on html2img.com

/articles/wordpress-dynamic-og-images/ already teaches the manual version of exactly this plugin: render on publish through `/html`, store the URL in post meta, output og tags. It uses a dark 1200x630 card with an accent bar and recommends inlining logos as data URIs. Our first bundled design matches it and the docs page draft will link the article and the plugin together.

## 3. Decisions

### 3.1 Naming and identity

- Slug and text domain: `html2img`. Permanent, matches the repo.
- Display name: **"HTML to Image: Dynamic Open Graph Images"**. The brand leads, the searched-for phrase follows. "OG image" and "social share image" variants go in the short description and tags where they count for search without cluttering the title. "for WordPress" adds nothing inside the WordPress directory.
- Namespace `Html2Img\WordPress`, PSR-4 autoloaded from `includes/`. Function, option, meta, hook and transient prefix `html2img_`. No Composer runtime dependencies (the PHP SDK exists but stays out), no Node build step, Gutenberg via plain JS and `wp.*` globals.
- Requires PHP 7.4 (still 17.7 percent of WordPress installs; the code gains nothing from 8.0-only syntax). Requires at least WordPress 6.2, tested up to 7.0 (current release 7.0.3).

### 3.2 API client and onboarding

- `Api\Client` wraps `wp_remote_post`/`wp_remote_get` with the `X-API-Key` header, 45 second timeout on renders, and returns typed results carrying the API error `code` so callers can branch on `insufficient_credits` without string matching.
- Key is validated on save with a real `GET /me`. The settings screen then shows plan name, credits remaining, reset date on paid plans and a dashboard link. The response is cached in a 10 minute transient (`html2img_account`) and the cached `credits_remaining` is overwritten from every render response, which the API returns on every call.
- With no key saved: dismissable admin notice on all admin screens that returns on the next page load until a key exists. Copy: "Connect your HTML to Image account to start generating OG images automatically. New accounts get 50 free credits." Links carry `utm_source=wordpress-plugin`, `utm_medium=admin-notice`, `utm_campaign=onboarding`.
- The key lives in the `html2img_settings` option, is masked on redisplay and never reaches the front end or any JS context. All API calls are server side.

### 3.3 Storage and the expiry problem

- Default: sideload the PNG into the media library, attach to the post, serve og:image from the local URL. The CDN URL and render id are kept in post meta as provenance. A local copy survives API outages and the free-plan purge.
- On regeneration the old attachment is deleted and a fresh one sideloaded. A new URL on every render is a feature here: Facebook and friends cache og:image by URL, so a changed URL is what actually busts their cache. (The Craft plugin replaces in place; for OG specifically, replacement in place preserves a stale scrape.)
- Generated attachments carry `_html2img_generated` meta, are hidden from the media grid and picker by default via `ajax_query_attachments_args`, with a setting to show them.
- Optional "Serve from the html2img CDN" setting for people who want zero local storage. When enabled on a free-plan account the setting shows a plain warning that free renders are deleted after 7 days and links the pricing page. Stored `expires_at` is checked in CDN mode: an expired image counts as missing and triggers regeneration on the next save.

### 3.4 Generate and regenerate

- Hook: `wp_after_insert_post`, which fires after terms and featured image are persisted on both REST (Gutenberg) and classic saves. Skip when: not a selected post type (default posts and pages), status not `publish`, autosave, revision, `wp_is_post_autosave`/`wp_is_post_revision`, per-post disable meta set, or no API key.
- Two stored hashes decide everything:
  - Content hash: `sha256` of the JSON-encoded variables array (title, site name, author, excerpt, date, featured image identifier, the lot). Any change to a mapped field changes the hash, nothing else does. That is the precise answer to "should a title change trigger an update".
  - Design fingerprint: `sha256` of design slug, the design template file contents, the customisation settings and the render dimensions. Posts whose stored fingerprint differs from the current one are the "stale" set, countable with one meta query, which powers the bulk screen.
- A save re-renders when either hash differs or the stored attachment has been deleted. A design settings save never renders anything by itself; it makes the stale count non-zero and the settings screen offers the bulk tool with the estimate. Never silently mass-render.
- The render is queued as an immediately scheduled single cron event, never inline with the editor save. The editor panel shows a generating state and polls over admin-ajax. If a queued job has not started within 2 minutes (wp-cron off or broken), the panel offers "Run now", which renders synchronously through the same code path.
- A short per-post transient lock around queue and render stops the duplicate-job race both siblings have, where two quick saves each queue a render and both spend a credit.
- After a 402 the plugin sets a paused flag and queued renders skip the API entirely until a `/me` refresh or a render response shows credits again. No point sending requests that will fail, and it keeps the log readable.
- Featured images: the API's Chrome cannot fetch from private or local hosts, so the featured image is inlined as a data URI built from the largest sub-1200px size. Cap: skip inlining when the encoded URI would exceed 1.5 MB and fall back to the public URL (fine for public production sites, the common case at scale). The site logo in designs is inlined the same way. Both filterable.

### 3.5 Designs

- Bundled designs are plain HTML files with `{{placeholder}}` tokens, rendered server side into a complete document and sent to `POST /html` at 1200x630, dpi 2 (both siblings settled on dpi 2; the output PNG is 2400x1260 and the meta tags report those output pixels). Hosted templates are not used: the `open-graph-image` template cannot take a featured image and bundled files keep design changes shippable in plugin updates without API coupling. This mirrors how both siblings ended up working.
- Five designs at launch, all self-contained (system fonts or Google Fonts via link tags, no local asset references), sized in vw units following the Craft trick so custom dimensions keep working:
  1. **Classic**: dark panel, accent bar, title, site name and author. Matches the site's own article and default cards.
  2. **Split**: featured image on the right half, text left. Falls back to a tinted panel when no featured image exists.
  3. **Photo**: full-bleed featured image behind a dark scrim and bottom-anchored title.
  4. **Editorial**: light background, serif title, thin rules, byline.
  5. **Minimal**: white, logo top left, large title, accent underline.
- Customisation kept deliberately small: accent colour, background colour, logo upload, show or hide author, show or hide site name. Each design ignores tokens it does not use.
- Advanced tab: custom HTML template textarea with documented placeholders `{{title}}`, `{{site_name}}`, `{{tagline}}`, `{{author}}`, `{{excerpt}}`, `{{date}}`, `{{featured_image}}`, `{{logo}}`, `{{accent_color}}`, `{{background_color}}`. Text values are escaped before substitution.
- Preview: free and instant, in the browser. The settings screen renders the chosen design with sample post data into a scaled sandboxed iframe, exactly what the API's Chrome would receive. Since no API preview mode exists, an explicit "Test render through the API" button does one real render, states that it costs one credit and shows the returned PNG next to the browser preview as a parity check. No renders on keystroke, ever.

### 3.6 Manual and bulk controls

- Gutenberg: a plugin sidebar panel (plain JS, `wp.plugins` + `wp.editPost`) showing the current image, generated time, status, a Regenerate button and the per-post "Don't generate for this post" toggle. Classic editor gets a metabox with the same contents driven by the same ajax endpoints.
- Posts list: "Regenerate OG image" row action and bulk action. The bulk action queues, it does not render inline.
- Tools screen (`Tools > OG Images`): "Regenerate stale" and "Regenerate all". Pre-flight shows "This will re-render N images. You have M credits remaining." from the meta query count and the cached balance, refreshed live before starting. Runs in ajax-driven batches of five, shows progress, stores a checkpoint option so it resumes after interruption, and stops cleanly with an admin notice the moment a render returns `insufficient_credits`.

### 3.7 Credit management

- Balance from the `/me` transient, refreshed after every render from the render response.
- Low credit notice when the balance drops below max(20, 10 percent of the last known full allowance), linking the upgrade page with UTM parameters.
- Render failure for credits: previous image and meta stay untouched, post status meta set to `failed_credits`, one aggregated dismissable notice ("OG image generation is paused: your HTML to Image account is out of credits. N posts are waiting.") rather than per-post noise. Queued posts render on their next save once credits exist, or via the bulk tool.
- Settings screen shows a status log of the last 20 renders (time, post, outcome, credits remaining after) kept in a bounded option. This is the support-request killer.

### 3.8 SEO plugin compatibility

- Detection order Yoast, Rank Math, AIOSEO, SEOPress; first active plugin controls the head and the settings screen says so ("Yoast SEO is outputting your social tags; HTML to Image feeds images to it.").
- Integration points, verified against current source or docs:
  - Yoast: `wpseo_opengraph_image`, `wpseo_opengraph_image_width`, `wpseo_opengraph_image_height`, `wpseo_twitter_image`.
  - Rank Math: `rank_math/opengraph/facebook/image`, `rank_math/opengraph/twitter/image`.
  - AIOSEO: `aioseo_facebook_tags` and `aioseo_twitter_tags` (full tag arrays, keys `og:image`, `og:image:width`, `og:image:height`, `twitter:image`, `twitter:card`).
  - SEOPress: `seopress_social_og_thumb`, `seopress_social_twitter_card_thumbnail`.
- Default stance: fill the gap. If the user manually set a social image for the post in their SEO plugin (checked via that plugin's own meta: `_yoast_wpseo_opengraph-image`, `rank_math_facebook_image`, AIOSEO's post record, `_seopress_social_fb_img`), ours stays out of the way. An "Always use the generated image" setting flips that for people who want it everywhere. Documented in the FAQ.
- No SEO plugin active: output `og:image`, `og:image:width`, `og:image:height`, `og:image:alt`, `twitter:card` (`summary_large_image`) and `twitter:image` on `wp_head`. Never both paths at once; the integration class owns the decision in one place.

### 3.9 Compliance

- GPL-2.0-or-later, full headers, ABSPATH guards everywhere, nonces and capability checks (`manage_options` for settings and bulk, `edit_post` for per-post actions), sanitised in, escaped out, every string translatable in the `html2img` domain.
- readme.txt includes an "External services" section naming HTML to Image, listing exactly what leaves the site (post title, excerpt, author display name, site name, date, the featured image or its URL, the design HTML) and when (publish, content changes, manual or bulk regeneration), linking terms and privacy pages.
- `uninstall.php` removes options, transients, cron events and post meta. Generated attachments are deleted only when the "Delete generated data on uninstall" setting was ticked; it defaults to keeping images so og tags in cached pages keep resolving.
- PHPCS with WordPress Coding Standards and the official Plugin Check both run clean before done (composer dev dependencies only).

### 3.10 Extensibility (documented in README.md)

- `html2img_variables` filter: the payload array per post.
- `html2img_should_generate` filter: final say on the generate decision.
- `html2img_render_args` filter: width, height, dpi, html before the API call.
- `html2img_designs` filter: register or replace designs.
- `html2img_after_generate` action: post id, attachment id, API response.

### 3.11 Test plan

- ddev site `~/sites/html2img-wp-site` (WordPress 7.0.3, plugin bind-mounted at `wp-content/plugins/html2img`), seeded with 18 posts across 4 authors and 3 categories with varied title and excerpt lengths, most with featured images and some deliberately without, plus 2 pages.
- Matrix: no SEO plugin and Yoast active, Gutenberg and classic editor, key present and absent, out of credits (mocked; the live key cannot run dry), free plan expiry messaging in CDN mode, bulk regeneration end to end including resume.
- PHPUnit (dev dependency, no WP test suite spin-up where avoidable): hash and regeneration decision logic, API client error mapping against canned responses for every error code, meta tag output and double-output protection. Pragmatic, not exhaustive.

### 3.12 Assets and distribution

- Banner 772x250 and 1544x500 and icon 128 and 256 rendered through the html2img API itself, which is its own proof point and gets noted in the readme.
- Screenshots (captured from the ddev site if headless Chrome cooperates, listed with captions regardless): settings connection screen with plan and credits, design picker with preview, Gutenberg panel with generated image, Tools bulk screen mid-run with estimate, posts list row action, the resulting share card in a social debugger.
- readme.txt tags (five max): `open graph`, `og image`, `social share image`, `twitter card`, `social image`.
- Docs draft for html2img.com/docs/integrations/wordpress goes in `handover/` as markdown, cross-linking the existing article.
