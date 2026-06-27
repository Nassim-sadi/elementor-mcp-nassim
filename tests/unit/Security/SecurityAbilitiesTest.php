<?php
/**
 * @group security
 * @package EMCP_Tools\Tests\Security
 */
namespace EMCP_Tools\Tests\Security;

use PHPUnit\Framework\TestCase;

class SecurityAbilitiesTest extends TestCase {

	/** @test */
	public function register_collects_the_ability_name(): void {
		$abilities = new \EMCP_Tools_Security_Abilities();
		$abilities->register();
		$this->assertSame( array( 'emcp-tools/scan-security' ), $abilities->get_ability_names() );
	}

	/** @test */
	public function permission_is_manage_options(): void {
		// Stub current_user_can() returns true by default; assert it is wired.
		$this->assertTrue( ( new \EMCP_Tools_Security_Abilities() )->check_permission() );
	}

	/** @test */
	public function execute_returns_a_report_shape(): void {
		// Inject a scanner double so no filesystem/HTTP is touched.
		$scanner = new class extends \EMCP_Tools_Security_Scanner {
			public function scan( array $input ): array {
				return array( 'summary' => array( 'score' => 100, 'grade' => 'A', 'counts' => array() ), 'sections' => array(), 'scan_meta' => array(), 'top_recommendations' => array() );
			}
		};
		$abilities = new \EMCP_Tools_Security_Abilities( $scanner );
		$report    = $abilities->execute_scan_security( array() );
		$this->assertSame( 'A', $report['summary']['grade'] );
	}
}
