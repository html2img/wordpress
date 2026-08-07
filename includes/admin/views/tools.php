<?php
/**
 * Bulk regeneration screen markup.
 *
 * @package Html2Img
 *
 * @var array|null $account     Cached account data or null.
 * @var int        $stale_count Posts with a stale design fingerprint.
 * @var int        $all_count   All eligible posts.
 * @var array|null $state       Bulk run checkpoint, when one exists.
 */

use Html2Img\WordPress\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap html2img-tools">
	<h1><?php esc_html_e( 'OG Images', 'html2img' ); ?></h1>

	<?php if ( ! Options::has_api_key() ) : ?>
		<p>
			<?php esc_html_e( 'Connect your HTML to Image account before regenerating images.', 'html2img' ); ?>
			<a href="<?php echo esc_url( \Html2Img\WordPress\Admin\Settings_Page::url() ); ?>"><?php esc_html_e( 'Open the settings', 'html2img' ); ?></a>
		</p>
	<?php else : ?>

		<p>
			<?php
			printf(
				/* translators: 1: stale count, 2: total count. */
				esc_html__( '%1$s of %2$s posts have an OG image made with an older design or settings.', 'html2img' ),
				'<strong>' . esc_html( number_format_i18n( $stale_count ) ) . '</strong>',
				esc_html( number_format_i18n( $all_count ) )
			);
			?>
			<?php if ( null !== $account ) : ?>
				<?php
				printf(
					/* translators: %s: credits remaining. */
					esc_html__( 'You have %s credits remaining.', 'html2img' ),
					'<strong>' . esc_html( number_format_i18n( $account['credits_remaining'] ) ) . '</strong>'
				);
				?>
			<?php endif; ?>
		</p>

		<?php if ( is_array( $state ) && ! empty( $state['ids'] ) ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php
					printf(
						/* translators: 1: done count, 2: total count. */
						esc_html__( 'A previous run stopped at %1$s of %2$s. It can be resumed or discarded.', 'html2img' ),
						esc_html( number_format_i18n( (int) $state['done'] ) ),
						esc_html( number_format_i18n( (int) $state['total'] ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<p>
			<button type="button" class="button button-primary" id="html2img-bulk-stale"
				data-count="<?php echo esc_attr( $stale_count ); ?>" <?php disabled( 0 === $stale_count && ! is_array( $state ) ); ?>>
				<?php
				printf(
					/* translators: %s: number of posts. */
					esc_html__( 'Regenerate %s stale images', 'html2img' ),
					esc_html( number_format_i18n( $stale_count ) )
				);
				?>
			</button>
			<button type="button" class="button" id="html2img-bulk-all" data-count="<?php echo esc_attr( $all_count ); ?>">
				<?php
				printf(
					/* translators: %s: number of posts. */
					esc_html__( 'Regenerate all %s images', 'html2img' ),
					esc_html( number_format_i18n( $all_count ) )
				);
				?>
			</button>
			<?php if ( is_array( $state ) && ! empty( $state['ids'] ) ) : ?>
				<button type="button" class="button" id="html2img-bulk-resume"><?php esc_html_e( 'Resume previous run', 'html2img' ); ?></button>
				<button type="button" class="button-link-delete" id="html2img-bulk-discard"><?php esc_html_e( 'Discard it', 'html2img' ); ?></button>
			<?php endif; ?>
		</p>

		<div id="html2img-bulk-progress" hidden>
			<p id="html2img-bulk-message"></p>
			<div class="html2img-progress-track"><div class="html2img-progress-bar" style="width:0"></div></div>
			<p>
				<button type="button" class="button" id="html2img-bulk-stop"><?php esc_html_e( 'Stop after this batch', 'html2img' ); ?></button>
			</p>
		</div>

		<p class="description">
			<?php esc_html_e( 'Each image costs one credit. Runs happen in small batches, progress is saved and a run stops cleanly if credits run out.', 'html2img' ); ?>
		</p>
	<?php endif; ?>
</div>
