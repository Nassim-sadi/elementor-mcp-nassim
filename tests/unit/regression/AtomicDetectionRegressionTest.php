<?php
/**
 * Regression tests — atomic (V4) detection.
 *
 * Elementor ships the atomic / V4 editor as opt-in experiments while the core
 * `ELEMENTOR_VERSION` constant can still report a 3.x value (observed in the
 * wild: 3.31.5 with the `e_opt_in_v4_page` experiment active even though the
 * plugin markets itself as 4.x). The previous detection —
 *   version_compare( ELEMENTOR_VERSION, '4.0.0', '>=' )
 * returned false on exactly those sites, so the atomic tools never registered
 * and content writes silently fell back to the classic format (which does not
 * persist on an atomic page).
 *
 * Elementor_MCP_Atomic_Props::is_atomic_supported() now also treats atomic as
 * supported when an atomic experiment is active (or the AtomicWidgets module is
 * loaded). These tests lock that behaviour in.
 *
 * @group regression
 * @group atomic
 * @package Elementor_MCP\Tests\Regression
 */

namespace Elementor_MCP\Tests\Regression;

use PHPUnit\Framework\TestCase;

class AtomicDetectionRegressionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_active_experiments'] = [];
	}

	protected function tearDown(): void {
		$GLOBALS['_active_experiments'] = [];
		parent::tearDown();
	}

	/**
	 * @test
	 *
	 * No atomic experiment active + a 3.x ELEMENTOR_VERSION (the bootstrap sets
	 * 3.25.0) → classic site → atomic NOT supported.
	 */
	public function classic_site_without_experiment_is_not_atomic(): void {
		$this->assertFalse( \Elementor_MCP_Atomic_Props::is_atomic_supported() );
	}

	/**
	 * @test
	 *
	 * The exact real-world breakage: 3.x version string but the V4 page
	 * experiment is active → MUST be detected as atomic.
	 */
	public function v4_page_experiment_marks_atomic_supported(): void {
		$GLOBALS['_active_experiments'] = [ 'e_opt_in_v4_page' ];
		$this->assertTrue( \Elementor_MCP_Atomic_Props::is_atomic_supported() );
	}

	/**
	 * @test
	 *
	 * Any of the recognised atomic experiment slugs flips detection on.
	 *
	 * @dataProvider atomic_experiment_provider
	 */
	public function each_atomic_experiment_marks_atomic_supported( string $slug ): void {
		$GLOBALS['_active_experiments'] = [ $slug ];
		$this->assertTrue( \Elementor_MCP_Atomic_Props::is_atomic_supported(), "Experiment '$slug' should enable atomic." );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function atomic_experiment_provider(): array {
		return [
			'e_opt_in_v4_page'  => [ 'e_opt_in_v4_page' ],
			'e_atomic_elements' => [ 'e_atomic_elements' ],
			'atomic_widgets'    => [ 'atomic_widgets' ],
			'editor_v4'         => [ 'editor_v4' ],
		];
	}

	/**
	 * @test
	 *
	 * An unrelated active experiment must NOT be mistaken for atomic.
	 */
	public function unrelated_experiment_does_not_enable_atomic(): void {
		$GLOBALS['_active_experiments'] = [ 'some_other_feature' ];
		$this->assertFalse( \Elementor_MCP_Atomic_Props::is_atomic_supported() );
	}
}
