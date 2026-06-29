<?php
/**
 * MCPB bundle manifest builder.
 * @group admin
 * @package EMCP_Tools\Tests\Admin
 */
namespace EMCP_Tools\Tests\Admin;

use PHPUnit\Framework\TestCase;

class McpbBuilderTest extends TestCase {

	private function manifest(): array {
		return \EMCP_Tools_Mcpb_Builder::build_manifest(
			'https://example.com',
			'admin',
			'abcd efgh ijkl mnop qrst uvwx'
		);
	}

	/** @test */
	public function manifest_names_the_server_emcp_tools(): void {
		$m = $this->manifest();
		$this->assertSame( 'emcp-tools', $m['name'] );
		$this->assertArrayHasKey( 'manifest_version', $m );
		$this->assertArrayHasKey( 'version', $m );
	}

	/** @test */
	public function manifest_uses_current_schema_with_entry_point(): void {
		$m = $this->manifest();
		$this->assertSame( '0.3', $m['manifest_version'] );
		$this->assertSame( 'node', $m['server']['type'] );
		$this->assertSame( 'server/index.js', $m['server']['entry_point'] );
		$this->assertArrayHasKey( 'name', $m['author'] );
	}

	/** @test */
	public function manifest_runs_the_bundled_proxy_via_node(): void {
		$cfg = $this->manifest()['server']['mcp_config'];
		// mcp_config now runs the bundled entry-point via node (no npx needed).
		$this->assertSame( 'node', $cfg['command'] );
		$this->assertContains( 'server/index.js', $cfg['args'] );
	}

	/** @test */
	public function manifest_bakes_in_the_credentials(): void {
		$env = $this->manifest()['server']['mcp_config']['env'];
		$this->assertSame( 'https://example.com', $env['WP_URL'] );
		$this->assertSame( 'admin', $env['WP_USERNAME'] );
		$this->assertSame( 'abcd efgh ijkl mnop qrst uvwx', $env['WP_APP_PASSWORD'] );
		$this->assertSame( '2024-11-05', $env['MCP_PROTOCOL_VERSION'] );
	}

	/** @test */
	public function display_name_includes_the_site_host(): void {
		$this->assertStringContainsString( 'example.com', (string) $this->manifest()['display_name'] );
	}
}
