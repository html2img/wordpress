<?php
/**
 * Template renderer tests.
 *
 * @package Html2Img
 */

use Html2Img\WordPress\Designs\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Placeholder substitution, sections and escaping.
 */
class RendererTest extends TestCase {

	protected function setUp(): void {
		h2i_test_reset();
	}

	public function test_substitutes_tokens() {
		$html = Renderer::render( '<h1>{{title}}</h1><p>{{site_name}}</p>', [
			'title'     => 'Hello',
			'site_name' => 'My Site',
		] );

		$this->assertSame( '<h1>Hello</h1><p>My Site</p>', $html );
	}

	public function test_escapes_text_tokens() {
		$html = Renderer::render( '<h1>{{title}}</h1>', [
			'title' => '<script>alert(1)</script>',
		] );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_keeps_section_when_value_present() {
		$html = Renderer::render( '{{#author}}<span>{{author}}</span>{{/author}}', [
			'author' => 'Elena',
		] );

		$this->assertSame( '<span>Elena</span>', $html );
	}

	public function test_drops_section_when_value_empty() {
		$html = Renderer::render( 'A{{#author}}<span>{{author}}</span>{{/author}}B', [
			'author' => '',
		] );

		$this->assertSame( 'AB', $html );
	}

	public function test_inverted_section_shows_fallback() {
		$template = '{{#featured_image}}<img src="{{featured_image}}">{{/featured_image}}{{^featured_image}}<div class="fallback"></div>{{/featured_image}}';

		$with = Renderer::render( $template, [ 'featured_image' => 'https://example.test/a.jpg' ] );
		$this->assertStringContainsString( '<img', $with );
		$this->assertStringNotContainsString( 'fallback', $with );

		$without = Renderer::render( $template, [ 'featured_image' => '' ] );
		$this->assertStringNotContainsString( '<img', $without );
		$this->assertStringContainsString( 'fallback', $without );
	}

	public function test_unknown_tokens_are_removed() {
		$html = Renderer::render( '<p>{{title}} {{mystery_token}}</p>', [ 'title' => 'A' ] );

		$this->assertSame( '<p>A </p>', $html );
	}

	public function test_data_uri_survives_url_escaping() {
		$uri  = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==';
		$html = Renderer::render( '<img src="{{featured_image}}">', [ 'featured_image' => $uri ] );

		$this->assertStringContainsString( $uri, $html );
	}

	public function test_invalid_colour_is_dropped() {
		$html = Renderer::render( 'color: {{accent_color}};', [ 'accent_color' => 'red; } body { display:none' ] );

		$this->assertSame( 'color: ;', $html );
	}

	public function test_valid_colour_passes() {
		$html = Renderer::render( 'color: {{accent_color}};', [ 'accent_color' => '#e11d48' ] );

		$this->assertSame( 'color: #e11d48;', $html );
	}

	public function test_every_bundled_design_renders() {
		$vars = [
			'title'            => 'A title',
			'title_class'      => 'title-l',
			'site_name'        => 'Site',
			'tagline'          => 'Tagline',
			'author'           => 'Author',
			'excerpt'          => 'Excerpt',
			'date'             => '7 August 2026',
			'featured_image'   => '',
			'logo'             => '',
			'accent_color'     => '#6366f1',
			'background_color' => '#0b1220',
		];

		foreach ( glob( HTML2IMG_DIR . 'designs/*.html' ) as $file ) {
			$html = Renderer::render( file_get_contents( $file ), $vars );

			$this->assertStringNotContainsString( '{{', $html, basename( $file ) . ' left unresolved tokens' );
			$this->assertStringContainsString( 'A title', $html, basename( $file ) . ' lost the title' );
		}
	}
}
