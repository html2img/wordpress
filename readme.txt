=== Auto OG Images - Open Graph & Social Image Generator by html2img ===
Contributors: html2img
Tags: open graph, og image, social share image, twitter card, social image
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate a unique Open Graph image for every post and page automatically. Pick a design, connect your account and publish.

== Description ==

Links shared without an image get scrolled past. This plugin gives every post and page its own share image, generated from the post title, author and featured image the moment you publish. No design work per post, no image editor, no template plugin to fight with.

Rendering happens through the [HTML to Image](https://html2img.com/?utm_source=wordpress-plugin&utm_medium=readme&utm_campaign=description) API, which draws each card in a real Chrome browser. Real browser rendering means proper fonts, emoji in titles and photographic featured images all come out right.

Full documentation is at [html2img.com/docs/integrations/wordpress](https://html2img.com/docs/integrations/wordpress/).

= How it works =

1. Connect your HTML to Image account. New accounts get 50 free credits and each generated image costs one credit.
2. Choose which post types get images and pick one of five bundled designs. Set your accent colour and logo.
3. Publish. The image is generated in the background, saved into your media library and served in your og:image and twitter:image tags.

The plugin is careful with your credits. An image is only regenerated when something it shows has changed: the title, the excerpt, the author, the featured image or the design itself. Saving a post without changing any of those costs nothing. When you change the design, nothing re-renders until you ask: the Tools screen shows how many images are out of date and what a full regeneration will cost before it starts.

= Features =

* Five bundled designs: Classic, Split, Photo, Editorial and Minimal. All take an accent colour, an optional logo and show or hide the author and site name.
* Free browser preview on the settings screen, so trying designs costs nothing.
* Works with Yoast SEO, Rank Math, All in One SEO and SEOPress through their own filters, so tags are never output twice. With no SEO plugin, the plugin outputs the tags itself.
* A social image chosen by hand in your SEO plugin is respected by default. The generated image fills the gap on every post that has none.
* Gutenberg sidebar panel and classic editor metabox with the current image, a regenerate button and a per post off switch.
* Regenerate from the posts list, one at a time or as a bulk action, or everything at once from the Tools screen with a cost estimate up front, progress, resume and a clean stop if credits run out.
* Images live in your media library by default, so they keep working whatever happens to your account. They are hidden from the media grid unless you want them shown.
* A custom HTML template option with documented placeholders, for developers who want full control.
* Filters and actions for the variable payload, the generate decision, the render arguments and post generation. See the developer documentation on GitHub.

= Credits and the free plan =

HTML to Image accounts start with 50 free credits, a one time allowance rather than a monthly one. One credit is one generated image. Paid plans start at $9 per month for 1,000 credits.

Renders made on the free plan are kept on the HTML to Image CDN for 7 days. This plugin saves each image into your media library, so your share images keep working even after the CDN copies expire. Paid plan renders are kept permanently, and upgrading makes every earlier render permanent too.

The plugin shows your remaining balance on its settings screen, warns you when it runs low and pauses cleanly when it runs out. Your existing images are never touched by a failed render.

= What this plugin is not =

It does not edit your theme, slow your public pages or add JavaScript to the front end. It outputs a handful of meta tags and everything else happens in the admin. If you deactivate it, your existing images stay in the media library.

== Installation ==

1. Install and activate the plugin from the Plugins screen.
2. Go to Settings, then OG Images.
3. Paste your API key from your [HTML to Image dashboard](https://app.html2img.com/dashboard). If you do not have an account, creating one takes a minute and includes 50 free credits.
4. Pick a design, choose your post types and save.

New posts get an image on publish. Existing posts can be generated in bulk from Tools, then OG Images.

== Frequently Asked Questions ==

= Does it work with Yoast SEO or Rank Math? =

Yes. When Yoast SEO, Rank Math, All in One SEO or SEOPress is active, the plugin feeds the generated image to it through its own filters and outputs nothing itself. The settings screen states which plugin currently controls your social tags. Tags are never output twice.

= What if I already set a social image on a post by hand? =

The plugin leaves it alone. A social image chosen manually in your SEO plugin wins by default, and the generated image only fills the gap on posts without one. If you want the generated image everywhere, there is an "always use the generated image" setting.

= What happens when my credits run out? =

Generation pauses. Existing images stay exactly as they are, and posts published in the meantime keep working without a generated image, falling back to whatever your SEO plugin or theme would do anyway. A single admin notice tells you how many posts are waiting, and they are generated when credits are available again, either on their next save or from the Tools screen.

= Do the images expire? =

Not the ones on your site. Images are saved into your media library by default and stay there. The copies on the HTML to Image CDN expire after 7 days on the free plan, which only matters if you switch on the "serve from CDN" option. The plugin warns you about exactly that if you try it on a free account. Paid plan renders never expire.

= Can I customise the design? =

Each bundled design takes an accent colour, a background colour, a logo and toggles for the author name and site name. Beyond that there is a custom template option that accepts your own HTML with placeholders like {{title}}, {{author}} and {{featured_image}}. The preview on the settings screen is drawn by your browser and costs nothing while you experiment.

= What data is sent to the API? =

Only what the image shows: the post title, the excerpt, the author display name, the site name and tagline, the date, the featured image and your design settings including the logo. Nothing else leaves your site, and nothing is sent for post types you have not enabled or posts you have switched off. See the external services section below.

= Does it work with WooCommerce products? =

Yes. Any public post type can be enabled on the settings screen, products included. The bundled designs show the product title and image. Prices and other product fields are not included out of the box, but the `html2img_variables` filter and a custom template can add them.

= I redesigned my site. Can I regenerate everything? =

Yes. Change the design or its settings, then go to Tools, OG Images. It shows how many images were made with the old design and how many credits a regeneration will use, and asks before spending anything. The run happens in batches, shows progress, can be resumed if interrupted and stops cleanly if credits run out.

= Does it support multisite? =

It works per site on a multisite network, each site with its own settings and API key. There is no network level configuration yet.

= Does publishing wait for the image? =

No. Publishing never waits on the API. The render runs in the background and typically finishes within seconds. The editor panel shows its progress, and if background tasks are disabled on your host the panel offers a manual run button.

== Screenshots ==

1. The settings screen with account status, design picker and live preview.
2. A generated share image using the Classic design.
3. The Gutenberg sidebar panel with the current image and regenerate button.
4. The bulk regeneration screen with the pre-flight credit estimate.
5. The Split design with a featured image.
6. The Editorial design.

== External services ==

This plugin sends data to the HTML to Image API at app.html2img.com to generate images. It sends the post title, the post excerpt, the author display name, the site name and tagline, the post date, the featured image (inlined or by URL) and the design HTML including your logo and colour settings. Data is sent when a post is published or updated with relevant changes, when you press regenerate, during bulk regeneration and when you request a test render from the settings screen. Your API key is sent with each request to authenticate it.

The generated images are stored on the HTML to Image CDN and downloaded into your media library. No visitor data is ever sent, and nothing is sent from your site's public pages.

HTML to Image is operated by html2img.com: [terms of service](https://html2img.com/terms), [privacy policy](https://html2img.com/privacy).

== Changelog ==

= 1.1.0 =
* Admin notices now appear only on the plugin's own screens and the plugins list, never on the rest of the dashboard.
* Dismissing the connect notice is permanent.
* Notices link to your account dashboard rather than the pricing page.
* Admin scripts and styles are enqueued rather than printed inline.

= 1.0.0 =
* First release.
* Five bundled designs with accent colour, logo and author toggles.
* Automatic generation on publish with change detection, so unchanged saves never spend a credit.
* Yoast SEO, Rank Math, All in One SEO and SEOPress integration.
* Gutenberg panel, classic metabox, row and bulk actions and a resumable bulk regeneration screen.
* Media library storage by default with an optional CDN mode.
* Custom HTML template support with documented placeholders.

== Upgrade Notice ==

= 1.1.0 =
Quieter admin notices, confined to the plugin's own screens.

= 1.0.0 =
First release.
