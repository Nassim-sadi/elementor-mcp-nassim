<?php
/**
 * @group filesystem
 * @package EMCP_Tools\Tests\Filesystem
 */
namespace EMCP_Tools\Tests\Filesystem;

use PHPUnit\Framework\TestCase;

class FilesystemAbilitiesTest extends TestCase {

	/** @test */
	public function registers_the_six_tools(): void {
		$a = new \EMCP_Tools_Filesystem_Abilities();
		$a->register();
		$this->assertSame(
			array(
				'emcp-tools/read-file',
				'emcp-tools/list-directory',
				'emcp-tools/search-files',
				'emcp-tools/write-file',
				'emcp-tools/edit-file',
				'emcp-tools/delete-file',
			),
			$a->get_ability_names()
		);
	}

	/** @test */
	public function delete_requires_confirm(): void {
		$a   = new \EMCP_Tools_Filesystem_Abilities();
		$res = $a->execute_delete_file( array( 'path' => 'wp-content/x.txt' ) ); // no confirm
		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'confirm_required', $res->get_error_code() );
	}

	/** @test */
	public function read_rejects_outside_root(): void {
		$res = ( new \EMCP_Tools_Filesystem_Abilities() )->execute_read_file( array( 'path' => '../../../../etc/hosts' ) );
		$this->assertInstanceOf( \WP_Error::class, $res );
		// outside_root (or parent_missing on odd layouts) — either is a safe refusal.
		$this->assertContains( $res->get_error_code(), array( 'outside_root', 'parent_missing', 'invalid_path' ) );
	}
}
