# Handover

State of the plugin, what needs your input and what to do next. Written 7 August 2026.

## What exists and works

The plugin is feature complete against the brief and verified end to end on the ddev site with the live key:

- Publish to image in about 8 seconds, hands free: save queues a single cron event, `spawn_cron` kicks it immediately, the render sideloads into the media library and the tags appear on the front end.
- Unchanged saves spend nothing (content hash), design changes make posts countable as stale (fingerprint) and the Tools screen regenerated 22 of 22 with zero failures in testing.
- Yoast integration verified live in both stances: manual social image wins by default, "always override" flips it. Tags are never double output.
- PHPCS (WordPress ruleset) clean, Plugin Check clean on the distribution file set, 29 unit tests passing.
- Test renders of all five designs against real seeded content are in `assets-wporg/` as screenshots.

## Decisions you may want to revisit

- **Display name**: "Auto OG Images - Open Graph & Social Image Generator by html2img". Keywords lead for directory search, the brand sits at the end. The slug stays `html2img`: it is permanent, it claims the brand namespace on wordpress.org and it marks this as the official companion plugin.
- **dpi 2 by default** (2400x1260 output), matching the Statamic and Craft integrations. `html2img_dimensions` filters it.
- **Regeneration replaces the attachment** (new file, new URL) rather than overwriting in place. Deliberate: Facebook caches og:image by URL, so a new URL is what busts a stale scrape.
- **Requires PHP 7.4**, still 17.7 percent of WordPress installs. The code uses no 8.0-only syntax.

## Needs your input before submission

1. **wordpress.org account**: readme.txt says `Contributors: html2img`. That wp.org username needs to exist and be the committing account, or swap in your own username.
2. **Terms and privacy URLs**: the readme's external services section links html2img.com/terms and /privacy. The terms page exists; confirm /privacy resolves (I did not find it in the sitemap).
3. **Author URI / support**: currently html2img.com. Add a support email or forum plan; directory reviewers look for one.
4. **The test key is in the ddev site's database** (`html2img_settings` option) and in my session memory, never in the repo. Rotate it if you consider it burnt.

## Required API additions (nice to have, nothing blocking)

The `/me` endpoint closed the big anticipated gap, so nothing here blocks the plugin. Two additions would still improve it:

1. **Plan allowance in `/me`** (e.g. `credits_allowance: 1000`). The low credit threshold is max(20, 10 percent of allowance) and the plugin currently parses the allowance out of `plan_name` ("1,000 Credits"), which breaks if plan names ever stop embedding the number. One integer field makes it robust.
2. **A free or watermarked preview render mode**. The settings screen uses a browser preview (faithful, since the API renders in Chrome anyway), but a true API preview that does not spend a credit would let the "test render" button be free.

Also worth knowing: accounts on the app's admin list bypass billing entirely. The test account is one of them, so out of credits paths can never fire live with it; they are covered by unit tests against the documented 402 envelope.

## wordpress.org submission steps

1. Create the plugin zip: `git archive` or rsync the tree minus `.distignore` entries, folder name `html2img`.
2. Submit at wordpress.org/plugins/developers/add/ under the account that will own the plugin. The slug request is `html2img`; the review queue currently runs a few weeks.
3. On approval you get SVN access. `trunk/` takes the plugin files, `tags/1.0.0/` the release copy, `assets/` takes everything in `assets-wporg/` (banner-772x250.png, banner-1544x500.png, icon-128x128.png, icon-256x256.png, screenshot-1.png through screenshot-6.png).
4. Screenshot captions live in readme.txt under == Screenshots == and map by number.
5. After it is live, tag releases by copying trunk to `tags/x.y.z` and bumping `Stable tag`.

## Known limitations

- **AIOSEO and SEOPress integrations are filter-verified against their current source but not exercised in a browser** the way Yoast was. Worth a smoke test each before claiming them loudly in marketing copy.
- **Classic editor metabox** drives the same ajax endpoints as the Gutenberg panel; tested with the classic-editor plugin active but less battle hardened.
- **Multisite**: works per site, no network admin, untested on an actual network.
- **The featured image data URI cap is 1.1 MB** of source file. Bigger files fall back to the public URL, which the API cannot fetch from local or intranet hosts, so posts with very large featured images on local sites render without the image. Public production sites are unaffected.
- **wp-cron off**: sites with `DISABLE_WP_CRON` and no system cron fall back to the "Run it now" button in the editor panel after 2 minutes. A site health check nudge could be added later.

## Repo and test site

- Repo: this directory, committed in coherent steps on `main`. The GitHub repo html2img/wordpress exists and is empty; push when ready.
- Test site: `~/sites/html2img-wp-site`, ddev project `html2img-wp`, WordPress 7.0.3 at https://html2img-wp.ddev.site (admin/admin). The plugin is bind-mounted via `.ddev/docker-compose.plugin.yaml`. Seeded with 19 posts across 4 authors with picsum featured images, plus 2 pages. Yoast and Plugin Check installed and active. `seed.sh` and `backfill.sh` in the site root rebuild the content.
