<?php
/**
 * @group database
 * @package EMCP_Tools\Tests\Database
 */
namespace EMCP_Tools\Tests\Database;

use PHPUnit\Framework\TestCase;

class DatabaseGuardTest extends TestCase {

	private function ok( string $sql ): bool {
		return true === \EMCP_Tools_Database_Guard::is_read_only_sql( $sql );
	}
	private function code( string $sql ): string {
		$r = \EMCP_Tools_Database_Guard::is_read_only_sql( $sql );
		return ( $r instanceof \WP_Error ) ? $r->get_error_code() : 'OK';
	}

	/** @test */
	public function allows_read_statements(): void {
		$this->assertTrue( $this->ok( 'SELECT * FROM wp_options' ) );
		$this->assertTrue( $this->ok( '  select 1' ) );
		$this->assertTrue( $this->ok( 'SeLeCt 1' ) );
		$this->assertTrue( $this->ok( 'SHOW TABLES' ) );
		$this->assertTrue( $this->ok( 'DESCRIBE wp_posts' ) );
		$this->assertTrue( $this->ok( 'EXPLAIN SELECT 1' ) );
		$this->assertTrue( $this->ok( "WITH t AS (SELECT 1 AS n) SELECT n FROM t" ) );
		$this->assertTrue( $this->ok( 'SELECT 1;' ) ); // single trailing ; allowed
	}

	/** @test */
	public function rejects_write_and_ddl(): void {
		foreach ( array(
			'INSERT INTO x VALUES (1)',
			'UPDATE x SET a=1',
			'DELETE FROM x',
			'DROP TABLE x',
			'TRUNCATE x',
			'ALTER TABLE x ADD c INT',
			'CREATE TABLE x (a int)',
			'GRANT ALL ON *.* TO a',
			'REPLACE INTO x VALUES (1)',
		) as $sql ) {
			$this->assertSame( 'not_read_only', $this->code( $sql ), $sql );
		}
	}

	/** @test */
	public function rejects_stacked_statements(): void {
		$this->assertSame( 'multi_statement', $this->code( 'SELECT 1; DROP TABLE x' ) );
		$this->assertSame( 'multi_statement', $this->code( 'SELECT 1; SELECT 2' ) );
	}

	/** @test */
	public function rejects_file_access_selects(): void {
		$this->assertSame( 'file_access_blocked', $this->code( "SELECT * FROM x INTO OUTFILE '/tmp/x'" ) );
		$this->assertSame( 'file_access_blocked', $this->code( "SELECT * INTO DUMPFILE '/tmp/x' FROM x" ) );
		$this->assertSame( 'file_access_blocked', $this->code( "SELECT LOAD_FILE('/etc/passwd')" ) );
		$this->assertSame( 'file_access_blocked', $this->code( "select load_file ('/etc/passwd')" ) );
	}

	/** @test */
	public function rejects_writes_smuggled_behind_comments(): void {
		$this->assertSame( 'not_read_only', $this->code( "/* hi */ DELETE FROM x" ) );
		$this->assertSame( 'not_read_only', $this->code( "-- comment\nDROP TABLE x" ) );
		$this->assertSame( 'not_read_only', $this->code( "# c\nUPDATE x SET a=1" ) );
	}

	/** @test */
	public function rejects_empty(): void {
		$this->assertSame( 'empty_sql', $this->code( '   ' ) );
		$this->assertSame( 'empty_sql', $this->code( '/* only a comment */' ) );
	}

	/** @test */
	public function strip_leading_comments_is_pure(): void {
		$this->assertSame( 'SELECT 1', trim( \EMCP_Tools_Database_Guard::strip_leading_comments( "/* a */ -- b\n SELECT 1" ) ) );
	}

	/** @test */
	public function table_is_protected_matches_case_insensitively(): void {
		$prot = array( 'wp_users', 'wp_usermeta' );
		$this->assertTrue( \EMCP_Tools_Database_Guard::table_is_protected( 'wp_users', $prot ) );
		$this->assertTrue( \EMCP_Tools_Database_Guard::table_is_protected( 'WP_UserMeta', $prot ) );
		$this->assertFalse( \EMCP_Tools_Database_Guard::table_is_protected( 'wp_posts', $prot ) );
	}
}
