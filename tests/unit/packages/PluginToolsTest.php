<?php
/**
 * Execute-path + guard tests for the Plugins tools.
 * @group packages
 * @package EMCP_Tools\Tests\Packages
 */
namespace EMCP_Tools\Tests\Packages;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class PluginToolsTest extends Ability_Test_Case {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_plugins'] = array(
			'akismet/akismet.php'           => array( 'Name' => 'Akismet', 'Version' => '5.0', 'Author' => 'Automattic' ),
			'elementor/elementor.php'       => array( 'Name' => 'Elementor', 'Version' => '4.1', 'Author' => 'Elementor' ),
			'elementor-mcp/emcp-tools.php'  => array( 'Name' => 'EMCP Tools', 'Version' => '3.0.0', 'Author' => 'MSR' ),
			'hello-dolly/hello.php'         => array( 'Name' => 'Hello Dolly', 'Version' => '1.7', 'Author' => 'Matt' ),
		);
		$GLOBALS['_wp_active_plugins']     = array( 'elementor/elementor.php', 'elementor-mcp/emcp-tools.php', 'akismet/akismet.php' );
		$GLOBALS['_wp_fs_method']          = 'direct';
		$GLOBALS['_wp_deactivated_plugins'] = array();
		$GLOBALS['_wp_deleted_plugins']     = array();
		$GLOBALS['_wp_installed_packages']  = array();
		$GLOBALS['_wp_upgraded']            = array();
		if ( ! defined( 'EMCP_TOOLS_BASENAME' ) ) {
			define( 'EMCP_TOOLS_BASENAME', 'elementor-mcp/emcp-tools.php' );
		}
	}

	/** @test */
	public function test_guard_protects_emcp_and_elementor(): void {
		$this->assertTrue( \EMCP_Tools_Package_Guard::is_protected_plugin( 'elementor-mcp/emcp-tools.php' ) );
		$this->assertTrue( \EMCP_Tools_Package_Guard::is_protected_plugin( 'elementor/elementor.php' ) );
		$this->assertTrue( \EMCP_Tools_Package_Guard::is_protected_plugin( 'elementor-pro/elementor-pro.php' ) );
		$this->assertFalse( \EMCP_Tools_Package_Guard::is_protected_plugin( 'akismet/akismet.php' ) );
	}

	/** @test */
	public function test_guard_filesystem_ready_ok_when_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'direct';
		$this->assertTrue( \EMCP_Tools_Package_Guard::filesystem_ready() );
	}

	/** @test */
	public function test_guard_filesystem_error_when_not_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'ftpext';
		$this->assertWPError( \EMCP_Tools_Package_Guard::filesystem_ready(), 'filesystem_unavailable' );
	}
}
