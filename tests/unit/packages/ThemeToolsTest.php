<?php
/**
 * Execute-path tests for the Themes tools.
 * @group packages
 * @package EMCP_Tools\Tests\Packages
 */
namespace EMCP_Tools\Tests\Packages;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ThemeToolsTest extends Ability_Test_Case {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_themes'] = array(
			'twentytwentyfour' => new \WP_Theme( 'twentytwentyfour', array( 'Name' => 'Twenty Twenty-Four', 'Version' => '1.2' ) ),
			'astra'            => new \WP_Theme( 'astra', array( 'Name' => 'Astra', 'Version' => '4.6' ) ),
			'astra-child'      => new \WP_Theme( 'astra-child', array( 'Name' => 'Astra Child', 'Version' => '1.0', 'Template' => 'astra' ) ),
		);
		$GLOBALS['_wp_active_stylesheet'] = 'astra-child';
		$GLOBALS['_wp_active_template']   = 'astra';
		$GLOBALS['_wp_fs_method']         = 'direct';
		$GLOBALS['_wp_deleted_themes']    = array();
		$GLOBALS['_wp_installed_packages'] = array();
		$GLOBALS['_wp_upgraded']          = array();
		if ( ! defined( 'EMCP_TOOLS_BASENAME' ) ) {
			define( 'EMCP_TOOLS_BASENAME', 'elementor-mcp/emcp-tools.php' );
		}
	}

	private function themes(): \EMCP_Tools_Theme_Abilities {
		$a = new \EMCP_Tools_Theme_Abilities();
		$a->register();
		return $a;
	}

	/** @test */
	public function test_registers_six_tools(): void {
		$names = $this->themes()->get_ability_names();
		$this->assertContains( 'emcp-tools/list-themes', $names );
		$this->assertContains( 'emcp-tools/search-themes', $names );
	}

	/** @test */
	public function test_list_themes_marks_active_and_parent(): void {
		$out  = $this->themes()->execute_list_themes( array() );
		$this->assertResultHasKey( $out, 'themes' );
		$rows = array();
		foreach ( $out['themes'] as $r ) { $rows[ $r['stylesheet'] ] = $r; }
		$this->assertTrue( $rows['astra-child']['is_active'] );
		$this->assertSame( 'astra', $rows['astra-child']['parent'] );
		$this->assertFalse( $rows['astra']['is_active'] );
	}

	/** @test */
	public function test_search_themes_returns_rows(): void {
		$GLOBALS['_wp_themes_api_query'] = array(
			(object) array( 'slug' => 'hello-elementor', 'name' => 'Hello Elementor', 'version' => '3.0', 'rating' => 98, 'requires' => '6.0' ),
		);
		$out = $this->themes()->execute_search_themes( array( 'search' => 'elementor' ) );
		$this->assertSame( 'hello-elementor', $out['results'][0]['slug'] );
	}

	/** @test */
	public function test_search_themes_requires_query(): void {
		$this->assertWPError( $this->themes()->execute_search_themes( array() ), 'missing_params' );
	}
}
