<?php
/**
 * Variable payload and hashing.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Generation;

use Html2Img\WordPress\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the variables a design consumes and the content hash that decides
 * whether a save re-renders.
 */
class Payload {

	/**
	 * Variables for a post, ready for the template renderer.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string>
	 */
	public static function variables( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return [];
		}

		$author  = Options::get( 'show_author' ) ? get_the_author_meta( 'display_name', (int) $post->post_author ) : '';
		$excerpt = '' !== $post->post_excerpt
			? $post->post_excerpt
			: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 28 );

		$logo_id = (int) Options::get( 'logo_id', 0 );

		$title = wp_specialchars_decode( get_the_title( $post ), ENT_QUOTES );

		$variables = [
			'title'            => $title,
			'title_class'      => self::title_class( $title ),
			'site_name'        => Options::get( 'show_site_name' ) ? get_bloginfo( 'name' ) : '',
			'tagline'          => get_bloginfo( 'description' ),
			'author'           => (string) $author,
			'excerpt'          => wp_specialchars_decode( (string) $excerpt, ENT_QUOTES ),
			'date'             => date_i18n( get_option( 'date_format' ), (int) get_post_timestamp( $post ) ),
			'featured_image'   => self::image_source( (int) get_post_thumbnail_id( $post ) ),
			'logo'             => self::image_source( $logo_id ),
			'accent_color'     => (string) Options::get( 'accent_color' ),
			'background_color' => (string) Options::get( 'background_color' ),
		];

		/**
		 * Filters the variable payload a design receives for a post.
		 *
		 * Adding or changing values here changes the content hash, so edits
		 * to a filtered field re-render exactly like edits to a core one.
		 *
		 * @param array $variables Variable name to value.
		 * @param int   $post_id   Post ID.
		 */
		return (array) apply_filters( 'html2img_variables', $variables, $post_id );
	}

	/**
	 * Content hash for a post.
	 *
	 * Hashes the same variables the design consumes, with inlined images
	 * replaced by stable identifiers so megabytes of data URI never enter
	 * the hash input. Any change to a mapped field changes the hash and
	 * nothing else does.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function content_hash( $post_id ) {
		$variables = self::variables( $post_id );

		$variables['featured_image'] = self::image_identity( (int) get_post_thumbnail_id( $post_id ) );
		$variables['logo']           = self::image_identity( (int) Options::get( 'logo_id', 0 ) );

		return hash( 'sha256', (string) wp_json_encode( $variables ) );
	}

	/**
	 * Size class for the title so designs can step the type down as
	 * headlines get longer. Templates have no logic of their own.
	 *
	 * @param string $title Post title.
	 * @return string title-xs, title-s, title-m or title-l.
	 */
	public static function title_class( $title ) {
		$length = mb_strlen( $title );

		if ( $length > 95 ) {
			return 'title-xs';
		}

		if ( $length > 65 ) {
			return 'title-s';
		}

		if ( $length > 38 ) {
			return 'title-m';
		}

		return 'title-l';
	}

	/**
	 * Image source for the render: a data URI when the file is small enough,
	 * the public URL otherwise.
	 *
	 * The API renders on its own servers, which cannot reach local or
	 * private hosts, so inlining is the default and the URL is the
	 * fallback for oversized files on public sites.
	 *
	 * @param int $attachment_id Attachment ID, 0 for none.
	 * @return string Empty string when there is no usable image.
	 */
	public static function image_source( $attachment_id ) {
		if ( ! $attachment_id ) {
			return '';
		}

		$path = self::sized_file_path( $attachment_id );

		if ( '' === $path ) {
			return (string) wp_get_attachment_url( $attachment_id );
		}

		/**
		 * Filters the largest file size, in bytes, inlined as a data URI.
		 *
		 * @param int $max_bytes Default 1100000, roughly 1.5 MB once encoded.
		 */
		$max_bytes = (int) apply_filters( 'html2img_inline_image_max_bytes', 1100000 );
		$size      = filesize( $path );

		if ( false === $size || $size > $max_bytes ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );

			return $url ? $url : '';
		}

		$type = wp_check_filetype( $path );
		$mime = ! empty( $type['type'] ) ? $type['type'] : 'image/jpeg';
		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload file.

		if ( false === $data ) {
			return (string) wp_get_attachment_url( $attachment_id );
		}

		return 'data:' . $mime . ';base64,' . base64_encode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Building a data URI.
	}

	/**
	 * Path of the largest generated size no wider than 1600px, falling back
	 * to the original file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Empty string when no local file exists.
	 */
	private static function sized_file_path( $attachment_id ) {
		$original = get_attached_file( $attachment_id );

		if ( ! $original || ! file_exists( $original ) ) {
			return '';
		}

		$meta = wp_get_attachment_metadata( $attachment_id );
		$best = '';
		$max  = 0;

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( empty( $size['file'] ) || empty( $size['width'] ) ) {
					continue;
				}

				if ( $size['width'] <= 1600 && $size['width'] > $max ) {
					$candidate = path_join( dirname( $original ), $size['file'] );

					if ( file_exists( $candidate ) ) {
						$best = $candidate;
						$max  = (int) $size['width'];
					}
				}
			}
		}

		// A small original beats an upscaled thumbnail; a sized file only
		// wins when the original is wider than the render needs.
		$original_width = ! empty( $meta['width'] ) ? (int) $meta['width'] : 0;

		if ( '' === $best || ( $original_width > 0 && $original_width <= 1600 ) ) {
			return $original;
		}

		return $best;
	}

	/**
	 * Stable identity of an image for hashing: ID, file and modified time.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function image_identity( $attachment_id ) {
		if ( ! $attachment_id ) {
			return '';
		}

		$path = get_attached_file( $attachment_id );
		$time = $path && file_exists( $path ) ? (string) filemtime( $path ) : '';

		return $attachment_id . ':' . basename( (string) $path ) . ':' . $time;
	}
}
