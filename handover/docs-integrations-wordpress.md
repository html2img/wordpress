# WordPress

Draft for html2img.com/docs/integrations/wordpress. Written for the docs section, same register as the existing integration and usage pages.

---

The official WordPress plugin generates an Open Graph image for every post and page through the HTML to Image API. It is built for people who do not want to write code: install it, connect your account, pick a design and publish.

If you would rather wire the API into WordPress yourself, the [dynamic OG images in WordPress article](/articles/wordpress-dynamic-og-images/) walks through a hand-rolled version of the same idea.

## Install

Install "Auto OG Images - Open Graph & Social Image Generator by html2img" from the WordPress plugin directory, or search for `html2img` on the Add Plugins screen. Activate it, open Settings, then OG Images, and paste an API key from your [dashboard](https://app.html2img.com/dashboard).

The plugin validates the key with a call to [`GET /api/me`](/docs/account/), which costs nothing, and then shows your plan and remaining credits on the settings screen.

## What it does

On publish, the plugin builds a card from the post title, author, excerpt, date and featured image, renders it through [`POST /api/html`](/docs/parameters/html/) and saves the PNG into the media library. The correct `og:image` and `twitter:image` tags are output on the post, or handed to Yoast SEO, Rank Math, All in One SEO or SEOPress when one of those is active.

Five designs ship with the plugin, each taking an accent colour, an optional logo and toggles for the author and site name. A custom template option accepts your own HTML with placeholders such as `{{title}}` and `{{featured_image}}`.

## How it spends credits

One render is one credit, the same as any API call. The plugin is deliberately tight with them:

- A post is only rendered when something on the card changed. Saving a post without touching the title, excerpt, author or featured image costs nothing.
- Changing the design re-renders nothing by itself. The Tools screen counts how many images are out of date and tells you what a full regeneration will cost before it starts.
- Previews on the settings screen are drawn by your browser, not the API, so trying designs is free.
- When an account runs out of credits the plugin pauses, keeps every existing image in place and shows one admin notice rather than failing post by post.

## Free plan notes

Free accounts get 50 one-time credits. Renders made on the free plan stay on the CDN for 7 days, but the plugin stores every image in your media library by default, so nothing on your site expires. If you switch on the "serve from CDN" option on a free account, the plugin warns you about the 7 day lifetime. Paid plan renders are kept permanently.

## For developers

The [GitHub repository](https://github.com/html2img/wordpress) documents the filters and actions: `html2img_variables` for the payload, `html2img_should_generate` for the generate decision, `html2img_render_args` for the API arguments, `html2img_designs` for registering designs and `html2img_after_generate` after each render. The template placeholder reference lives there too.

## Requirements

WordPress 6.2 or newer and PHP 7.4 or newer. The plugin makes no front end requests to the API and never exposes your key to the browser.
