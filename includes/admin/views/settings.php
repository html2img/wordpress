<?php
/**
 * Settings screen markup.
 *
 * @package Html2Img
 *
 * @var array      $settings Current settings.
 * @var array|null $account  Cached account data or null.
 * @var array      $designs  Available designs.
 * @var array      $log      Render log entries.
 */

use Html2Img\WordPress\Integrations\Seo;
use Html2Img\WordPress\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$html2img_option = Html2Img\WordPress\Options::OPTION;
$html2img_signup = 'https://html2img.com/?utm_source=wordpress-plugin&utm_medium=settings&utm_campaign=onboarding';
?>
<div class="wrap html2img-settings">
	<h1><?php esc_html_e( 'OG Images', 'html2img' ); ?></h1>

	<p class="html2img-seo-line"><?php echo esc_html( Seo::controller_label() ); ?></p>

	<form method="post" action="options.php">
		<?php settings_fields( 'html2img' ); ?>

		<h2><?php esc_html_e( 'Connection', 'html2img' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="html2img-api-key"><?php esc_html_e( 'API key', 'html2img' ); ?></label>
				</th>
				<td>
					<?php if ( Options::has_api_key() ) : ?>
						<code><?php echo esc_html( '••••••••' . substr( Options::api_key(), -4 ) ); ?></code>
						<label class="html2img-inline-after">
							<input type="checkbox" name="<?php echo esc_attr( $html2img_option ); ?>[remove_key]" value="1" />
							<?php esc_html_e( 'Remove this key', 'html2img' ); ?>
						</label>
						<br /><br />
					<?php endif; ?>
					<input type="password" id="html2img-api-key" class="regular-text" autocomplete="off"
						name="<?php echo esc_attr( $html2img_option ); ?>[api_key]" value=""
						placeholder="<?php echo Options::has_api_key() ? esc_attr__( 'Enter a new key to replace it', 'html2img' ) : 'htim_'; ?>" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to html2img.com. */
							esc_html__( 'Find your key in the %s dashboard. New accounts get 50 free credits.', 'html2img' ),
							'<a href="' . esc_url( $html2img_signup ) . '" target="_blank" rel="noopener">HTML to Image</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<?php if ( null !== $account ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Account', 'html2img' ); ?></th>
				<td>
					<p>
						<strong><?php echo esc_html( $account['plan_name'] ? $account['plan_name'] : $account['plan'] ); ?></strong>
						<?php if ( $account['free_plan'] ) : ?>
							<em>(<?php esc_html_e( 'free plan', 'html2img' ); ?>)</em>
						<?php endif; ?>
						&mdash;
						<?php
						printf(
							/* translators: %s: number of credits. */
							esc_html__( '%s credits remaining', 'html2img' ),
							esc_html( number_format_i18n( $account['credits_remaining'] ) )
						);
						?>
						<?php if ( '' !== $account['credits_reset_at'] ) : ?>
							<?php
							printf(
								/* translators: %s: renewal date. */
								esc_html__( ', renews %s', 'html2img' ),
								esc_html( date_i18n( get_option( 'date_format' ), strtotime( $account['credits_reset_at'] ) ) )
							);
							?>
						<?php endif; ?>
					</p>
					<p>
						<a href="https://app.html2img.com/dashboard" target="_blank" rel="noopener"><?php esc_html_e( 'Open the account dashboard', 'html2img' ); ?></a>
					</p>
				</td>
			</tr>
			<?php elseif ( Options::has_api_key() ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Account', 'html2img' ); ?></th>
				<td><p><?php esc_html_e( 'The account could not be reached just now. The key is saved.', 'html2img' ); ?></p></td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Generate for', 'html2img' ); ?></th>
				<td>
					<?php
					$html2img_types = get_post_types( [ 'public' => true ], 'objects' );
					unset( $html2img_types['attachment'] );

					foreach ( $html2img_types as $html2img_type ) :
						?>
						<label class="html2img-inline-option">
							<input type="checkbox"
								name="<?php echo esc_attr( $html2img_option ); ?>[post_types][]"
								value="<?php echo esc_attr( $html2img_type->name ); ?>"
								<?php checked( in_array( $html2img_type->name, $settings['post_types'], true ) ); ?> />
							<?php echo esc_html( $html2img_type->labels->name ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'New and updated posts of these types get an OG image on publish.', 'html2img' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Design', 'html2img' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Design', 'html2img' ); ?></th>
				<td>
					<fieldset id="html2img-designs">
						<?php foreach ( $designs as $html2img_slug => $html2img_design ) : ?>
							<label class="html2img-inline-option">
								<input type="radio" name="<?php echo esc_attr( $html2img_option ); ?>[design]"
									value="<?php echo esc_attr( $html2img_slug ); ?>"
									<?php checked( $settings['design'], $html2img_slug ); ?> />
								<?php echo esc_html( $html2img_design['name'] ); ?>
							</label>
						<?php endforeach; ?>
						<label>
							<input type="radio" name="<?php echo esc_attr( $html2img_option ); ?>[design]" value="custom"
								<?php checked( $settings['design'], 'custom' ); ?> />
							<?php esc_html_e( 'Custom template', 'html2img' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Preview', 'html2img' ); ?></th>
				<td>
					<div class="html2img-preview-frame">
						<iframe id="html2img-preview" title="<?php esc_attr_e( 'Design preview', 'html2img' ); ?>"
							width="1200" height="630" sandbox=""></iframe>
					</div>
					<p>
						<button type="button" class="button" id="html2img-preview-refresh">
							<?php esc_html_e( 'Preview with a recent post', 'html2img' ); ?>
						</button>
						<button type="button" class="button" id="html2img-test-render">
							<?php esc_html_e( 'Test render through the API (uses one credit)', 'html2img' ); ?>
						</button>
					</p>
					<div id="html2img-test-result" hidden>
						<p><?php esc_html_e( 'Rendered by the API:', 'html2img' ); ?></p>
						<img src="" alt="<?php esc_attr_e( 'API test render', 'html2img' ); ?>" />
					</div>
					<p class="description"><?php esc_html_e( 'The preview is drawn by your browser and costs nothing. Unsaved changes on this screen are included.', 'html2img' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="html2img-accent"><?php esc_html_e( 'Accent colour', 'html2img' ); ?></label>
				</th>
				<td>
					<input type="text" id="html2img-accent" class="html2img-color"
						name="<?php echo esc_attr( $html2img_option ); ?>[accent_color]"
						value="<?php echo esc_attr( $settings['accent_color'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="html2img-background"><?php esc_html_e( 'Background colour', 'html2img' ); ?></label>
				</th>
				<td>
					<input type="text" id="html2img-background" class="html2img-color"
						name="<?php echo esc_attr( $html2img_option ); ?>[background_color]"
						value="<?php echo esc_attr( $settings['background_color'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Logo', 'html2img' ); ?></th>
				<td>
					<div id="html2img-logo-preview">
						<?php if ( $settings['logo_id'] ) : ?>
							<?php echo wp_get_attachment_image( $settings['logo_id'], 'medium' ); ?>
						<?php endif; ?>
					</div>
					<input type="hidden" id="html2img-logo-id"
						name="<?php echo esc_attr( $html2img_option ); ?>[logo_id]"
						value="<?php echo esc_attr( $settings['logo_id'] ); ?>" />
					<button type="button" class="button" id="html2img-logo-select"><?php esc_html_e( 'Choose logo', 'html2img' ); ?></button>
					<button type="button" class="button" id="html2img-logo-remove" <?php echo $settings['logo_id'] ? '' : 'hidden'; ?>>
						<?php esc_html_e( 'Remove', 'html2img' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'Shown by designs that place a logo. A transparent PNG or SVG works best.', 'html2img' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Details', 'html2img' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $html2img_option ); ?>[show_author]" value="1"
							<?php checked( $settings['show_author'] ); ?> />
						<?php esc_html_e( 'Show the author name', 'html2img' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $html2img_option ); ?>[show_site_name]" value="1"
							<?php checked( $settings['show_site_name'] ); ?> />
						<?php esc_html_e( 'Show the site name', 'html2img' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Advanced', 'html2img' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="html2img-custom-template"><?php esc_html_e( 'Custom template', 'html2img' ); ?></label>
				</th>
				<td>
					<textarea id="html2img-custom-template" rows="12" class="large-text code"
						name="<?php echo esc_attr( $html2img_option ); ?>[custom_template]"><?php echo esc_textarea( $settings['custom_template'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'A complete HTML document. Placeholders: {{title}}, {{site_name}}, {{tagline}}, {{author}}, {{excerpt}}, {{date}}, {{featured_image}}, {{logo}}, {{accent_color}}, {{background_color}}. Wrap optional blocks in {{#author}}…{{/author}} to hide them when empty. Used when the design above is set to Custom template.', 'html2img' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Image storage', 'html2img' ); ?></th>
				<td>
					<label>
						<input type="radio" name="<?php echo esc_attr( $html2img_option ); ?>[storage]" value="media"
							<?php checked( $settings['storage'], 'media' ); ?> />
						<?php esc_html_e( 'Save to the media library (recommended)', 'html2img' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="<?php echo esc_attr( $html2img_option ); ?>[storage]" value="cdn"
							<?php checked( $settings['storage'], 'cdn' ); ?> />
						<?php esc_html_e( 'Serve directly from the html2img CDN', 'html2img' ); ?>
					</label>
					<?php if ( null !== $account && $account['free_plan'] ) : ?>
						<p class="html2img-warning">
							<?php esc_html_e( 'Your account is on the free plan. Free plan renders are deleted from the CDN after 7 days, so hotlinked images will stop working. Keep media library storage, or upgrade to a paid plan where renders are kept permanently.', 'html2img' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Behaviour', 'html2img' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $html2img_option ); ?>[always_override]" value="1"
							<?php checked( $settings['always_override'] ); ?> />
						<?php esc_html_e( 'Use the generated image even when a social image was set manually in the SEO plugin', 'html2img' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $html2img_option ); ?>[show_in_media]" value="1"
							<?php checked( $settings['show_in_media'] ); ?> />
						<?php esc_html_e( 'Show generated images in the media library grid', 'html2img' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $html2img_option ); ?>[delete_on_uninstall]" value="1"
							<?php checked( $settings['delete_on_uninstall'] ); ?> />
						<?php esc_html_e( 'Delete generated images and data when the plugin is uninstalled', 'html2img' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<?php if ( ! empty( $log ) ) : ?>
		<h2><?php esc_html_e( 'Recent renders', 'html2img' ); ?></h2>
		<table class="widefat striped html2img-log">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'html2img' ); ?></th>
					<th><?php esc_html_e( 'Post', 'html2img' ); ?></th>
					<th><?php esc_html_e( 'Outcome', 'html2img' ); ?></th>
					<th><?php esc_html_e( 'Credits left', 'html2img' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $html2img_entry ) : ?>
					<tr>
						<td>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: human readable time difference. */
									__( '%s ago', 'html2img' ),
									human_time_diff( (int) $html2img_entry['time'] )
								)
							);
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $html2img_entry['post_id'] ) ); ?>">
								<?php echo esc_html( $html2img_entry['title'] ? $html2img_entry['title'] : '#' . $html2img_entry['post_id'] ); ?>
							</a>
						</td>
						<td>
							<?php if ( 'ok' === $html2img_entry['outcome'] ) : ?>
								<?php esc_html_e( 'Generated', 'html2img' ); ?>
							<?php elseif ( 'failed_credits' === $html2img_entry['outcome'] ) : ?>
								<?php esc_html_e( 'Out of credits', 'html2img' ); ?>
							<?php else : ?>
								<?php echo esc_html( $html2img_entry['message'] ? $html2img_entry['message'] : __( 'Failed', 'html2img' ) ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo null === $html2img_entry['credits'] ? '&mdash;' : esc_html( number_format_i18n( $html2img_entry['credits'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
