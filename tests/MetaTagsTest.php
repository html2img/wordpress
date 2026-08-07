<?php
/**
 * Meta tag output tests.
 *
 * @package Html2Img
 */

use Html2Img\WordPress\Frontend\Meta_Tags;
use PHPUnit\Framework\TestCase;

/**
 * The direct output path used when no SEO plugin is active.
 */
class MetaTagsTest extends TestCase {

	private function image() {
		return [
			'url'    => 'https://example.test/wp-content/uploads/og-post-1.png',
			'width'  => 2400,
			'height' => 1260,
		];
	}

	public function test_outputs_all_expected_tags() {
		$markup = Meta_Tags::markup( $this->image(), 'A post title' );

		$this->assertStringContainsString( '<meta property="og:image" content="https://example.test/wp-content/uploads/og-post-1.png" />', $markup );
		$this->assertStringContainsString( '<meta property="og:image:width" content="2400" />', $markup );
		$this->assertStringContainsString( '<meta property="og:image:height" content="1260" />', $markup );
		$this->assertStringContainsString( '<meta property="og:image:type" content="image/png" />', $markup );
		$this->assertStringContainsString( '<meta property="og:image:alt" content="A post title" />', $markup );
		$this->assertStringContainsString( '<meta name="twitter:card" content="summary_large_image" />', $markup );
		$this->assertStringContainsString( '<meta name="twitter:image" content="https://example.test/wp-content/uploads/og-post-1.png" />', $markup );
	}

	public function test_alt_is_escaped() {
		$markup = Meta_Tags::markup( $this->image(), 'He said "hi" & left' );

		$this->assertStringContainsString( 'He said &quot;hi&quot; &amp; left', $markup );
		$this->assertStringNotContainsString( 'content="He said "hi"', $markup );
	}

	public function test_alt_tag_omitted_when_empty() {
		$markup = Meta_Tags::markup( $this->image(), '' );

		$this->assertStringNotContainsString( 'og:image:alt', $markup );
	}

	public function test_only_one_og_image_tag() {
		$markup = Meta_Tags::markup( $this->image(), 'Title' );

		$this->assertSame( 1, substr_count( $markup, 'property="og:image"' ) );
		$this->assertSame( 1, substr_count( $markup, 'name="twitter:image"' ) );
	}
}
