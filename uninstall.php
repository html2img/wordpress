<?php
/**
 * Uninstall cleanup.
 *
 * Removes options, transients, cron events and post meta. Generated
 * attachments are only deleted when the user ticked "Delete generated
 * images and data when the plugin is uninstalled"; the default keeps them
 * so og:image URLs in cached pages keep resolving.
 *
 * @package Html2Img
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$html2img_settings = get_option( 'html2img_settings', [] );
$html2img_purge    = is_array( $html2img_settings ) && ! empty( $html2img_settings['delete_on_uninstall'] );

if ( $html2img_purge ) {
	$html2img_attachments = get_posts(
		[
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_html2img_generated', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time uninstall pass.
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-time uninstall pass.
		]
	);

	foreach ( $html2img_attachments as $html2img_attachment_id ) {
		wp_delete_attachment( $html2img_attachment_id, true );
	}
}

$html2img_meta_keys = [
	'_html2img_image_id',
	'_html2img_cdn_url',
	'_html2img_render_id',
	'_html2img_expires_at',
	'_html2img_content_hash',
	'_html2img_fingerprint',
	'_html2img_generated_at',
	'_html2img_status',
	'_html2img_error',
	'_html2img_disabled',
	'_html2img_queued_at',
	'_html2img_generated',
];

foreach ( $html2img_meta_keys as $html2img_meta_key ) {
	delete_post_meta_by_key( $html2img_meta_key );
}

delete_option( 'html2img_settings' );
delete_option( 'html2img_render_log' );
delete_option( 'html2img_bulk_state' );

delete_transient( 'html2img_account' );
delete_transient( 'html2img_paused' );
delete_transient( 'html2img_waiting_count' );

delete_metadata( 'user', 0, 'html2img_notice_dismissed', '', true );

wp_unschedule_hook( 'html2img_render_post' );
