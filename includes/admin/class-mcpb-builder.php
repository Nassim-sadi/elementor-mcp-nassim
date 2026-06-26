<?php
/**
 * Builds a Claude Desktop .mcpb bundle (a zip containing manifest.json) that
 * installs the EMCP MCP server via the npx proxy, with credentials baked in.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.0.0
 */
class EMCP_Tools_Mcpb_Builder {

	const MANIFEST_VERSION = '0.3';

	/**
	 * Relative path (inside the bundle) of the entry-point launcher. MCPB
	 * requires server.entry_point even when execution is driven by mcp_config;
	 * we ship a tiny launcher at this path so the manifest validates.
	 */
	const ENTRY_POINT = 'server/index.js';

	/**
	 * Build the MCPB manifest array. Pure — no I/O.
	 *
	 * @param string $site_url     home_url() of the WordPress site.
	 * @param string $username     WordPress login.
	 * @param string $app_password Application Password (baked into env).
	 * @return array
	 */
	public static function build_manifest( string $site_url, string $username, string $app_password ): array {
		$host    = (string) wp_parse_url( $site_url, PHP_URL_HOST );
		$version = defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '0.0.0';

		return array(
			'manifest_version' => self::MANIFEST_VERSION,
			'name'             => 'emcp-tools',
			'display_name'     => sprintf( 'EMCP Tools — %s', $host ),
			'version'          => $version,
			'description'      => sprintf( 'Connect Claude Desktop to %s for Elementor and WordPress management via MCP.', $host ),
			'author'           => array( 'name' => 'MSR Builds' ),
			'server'           => array(
				'type'        => 'node',
				'entry_point' => self::ENTRY_POINT,
				'mcp_config'  => array(
					'command' => 'npx',
					'args'    => array( '-y', '@msrbuilds/emcp-proxy@latest' ),
					'env'     => array(
						'WP_URL'               => $site_url,
						'WP_USERNAME'          => $username,
						'WP_APP_PASSWORD'      => $app_password,
						'MCP_PROTOCOL_VERSION' => '2024-11-05',
					),
				),
			),
		);
	}

	/**
	 * Write the manifest into a temp .mcpb (zip) file and return its path, or
	 * WP_Error on failure.
	 *
	 * @param array $manifest
	 * @return string|\WP_Error Temp file path.
	 */
	public static function build_zip( array $manifest ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'no_zip', __( 'The ZipArchive PHP extension is required to build the bundle.', 'emcp-tools' ) );
		}
		$tmp = wp_tempnam( 'emcp-tools.mcpb' );
		if ( ! $tmp ) {
			return new \WP_Error( 'no_tmp', __( 'Could not create a temporary file for the bundle.', 'emcp-tools' ) );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $tmp, \ZipArchive::OVERWRITE | \ZipArchive::CREATE ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new \WP_Error( 'no_open', __( 'Could not open the bundle archive for writing.', 'emcp-tools' ) );
		}
		$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		// Minimal launcher so server.entry_point resolves to a real file. The
		// host normally runs the mcp_config command (npx) directly; this is a
		// fallback that spawns the same proxy, inheriting the injected env.
		$zip->addFromString( self::ENTRY_POINT, self::launcher_js() );
		$zip->close();
		return $tmp;
	}

	/**
	 * The bundled entry-point launcher (Node). Spawns the npx proxy with the
	 * environment the host injects from mcp_config.env.
	 *
	 * @return string
	 */
	private static function launcher_js(): string {
		return "#!/usr/bin/env node\n"
			. "// EMCP Tools MCPB launcher. The host normally uses manifest.json's\n"
			. "// server.mcp_config (npx) directly; this fallback spawns the same proxy.\n"
			. "'use strict';\n"
			. "const { spawn } = require('child_process');\n"
			. "const child = spawn('npx', ['-y', '@msrbuilds/emcp-proxy@latest'], {\n"
			. "  stdio: 'inherit', env: process.env, shell: process.platform === 'win32',\n"
			. "});\n"
			. "child.on('exit', (code) => process.exit(code == null ? 0 : code));\n";
	}
}
