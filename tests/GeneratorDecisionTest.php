<?php
/**
 * Regeneration decision tests.
 *
 * @package Html2Img
 */

use Html2Img\WordPress\Generation\Generator;
use Html2Img\WordPress\Generation\Payload;
use PHPUnit\Framework\TestCase;

/**
 * The two-hash decision that guards every credit.
 */
class GeneratorDecisionTest extends TestCase {

	private function hash_of( $value ) {
		return function () use ( $value ) {
			return $value;
		};
	}

	public function test_first_render_when_nothing_stored() {
		$this->assertTrue( Generator::decide( '', '', $this->hash_of( 'a' ), $this->hash_of( 'f' ), false ) );
	}

	public function test_no_render_when_everything_matches() {
		$this->assertFalse( Generator::decide( 'a', 'f', $this->hash_of( 'a' ), $this->hash_of( 'f' ), true ) );
	}

	public function test_render_when_image_was_deleted() {
		$this->assertTrue( Generator::decide( 'a', 'f', $this->hash_of( 'a' ), $this->hash_of( 'f' ), false ) );
	}

	public function test_render_when_content_changed() {
		$this->assertTrue( Generator::decide( 'a', 'f', $this->hash_of( 'b' ), $this->hash_of( 'f' ), true ) );
	}

	public function test_render_when_design_changed() {
		$this->assertTrue( Generator::decide( 'a', 'f', $this->hash_of( 'a' ), $this->hash_of( 'g' ), true ) );
	}

	public function test_content_hash_not_computed_when_design_already_stale() {
		$computed = false;

		Generator::decide(
			'a',
			'f',
			function () use ( &$computed ) {
				$computed = true;

				return 'a';
			},
			$this->hash_of( 'g' ),
			true
		);

		$this->assertFalse( $computed, 'The expensive content hash ran although the fingerprint already decided.' );
	}

	public function test_title_class_boundaries() {
		$this->assertSame( 'title-l', Payload::title_class( str_repeat( 'a', 38 ) ) );
		$this->assertSame( 'title-m', Payload::title_class( str_repeat( 'a', 39 ) ) );
		$this->assertSame( 'title-m', Payload::title_class( str_repeat( 'a', 65 ) ) );
		$this->assertSame( 'title-s', Payload::title_class( str_repeat( 'a', 66 ) ) );
		$this->assertSame( 'title-s', Payload::title_class( str_repeat( 'a', 95 ) ) );
		$this->assertSame( 'title-xs', Payload::title_class( str_repeat( 'a', 96 ) ) );
	}
}
