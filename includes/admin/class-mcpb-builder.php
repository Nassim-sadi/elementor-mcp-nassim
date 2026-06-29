<?php
/**
 * Builds a Claude Desktop .mcpb bundle that installs the EMCP MCP server
 * using the bundled proxy — no npx, no internet, no PATH issues.
 *
 * The bundle contains:
 *   manifest.json      — MCPB 0.3 manifest
 *   server/index.js    — entry-point: runs the bundled proxy via process.execPath
 *   server/proxy.mjs   — the self-contained stdio↔HTTP proxy (copied from bin/)
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

	/** Relative path of the entry-point inside the bundle. */
	const ENTRY_POINT = 'server/index.js';

	/** Relative path of the bundled proxy inside the bundle. */
	const PROXY_PATH = 'server/proxy.mjs';

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
				// mcp_config is included as a hint for hosts that support it;
				// server/index.js is the authoritative execution path and does
				// not rely on mcp_config being injected.
				'mcp_config'  => array(
					'command' => 'node',
					'args'    => array( self::ENTRY_POINT ),
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
	 * Write the manifest + entry-point + bundled proxy into a temp .mcpb (zip)
	 * file and return its path, or WP_Error on failure.
	 *
	 * @param array $manifest
	 * @return string|\WP_Error Temp file path.
	 */
	public static function build_zip( array $manifest ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'no_zip', __( 'The ZipArchive PHP extension is required to build the bundle.', 'emcp-tools' ) );
		}

		// Read the bundled proxy source that ships with the plugin.
		$proxy_source_path = EMCP_TOOLS_DIR . 'bin/mcp-proxy.mjs';
		$proxy_source      = @file_get_contents( $proxy_source_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( false === $proxy_source ) {
			return new \WP_Error( 'no_proxy', __( 'Could not read the bundled proxy file (bin/mcp-proxy.mjs). Please reinstall the plugin.', 'emcp-tools' ) );
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

		$env = $manifest['server']['mcp_config']['env'] ?? array();

		$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		// Entry-point: runs the bundled proxy.mjs via the same Node binary.
		// Credentials are embedded so this works whether the host runs it via
		// entry_point or mcp_config — no npx, no network, no PATH dependency.
		$zip->addFromString( self::ENTRY_POINT, self::launcher_js( $env ) );
		// The self-contained stdio↔HTTP proxy (pure Node.js built-ins only).
		$zip->addFromString( self::PROXY_PATH, $proxy_source );

		$zip->close();
		return $tmp;
	}

	/**
	 * The entry-point launcher (CJS). Runs server/proxy.mjs via process.execPath
	 * (the Node binary Claude Desktop already has) with credentials embedded.
	 *
	 * Why not npx? When Claude Desktop uses its built-in Node.js to run entry_point,
	 * the child process environment has no user PATH, so npx is unreachable.
	 * Running the bundled proxy.mjs directly via process.execPath needs nothing
	 * beyond the Node binary that is already executing this file.
	 *
	 * @param array $env Key→value credentials from mcp_config.env.
	 * @return string
	 */
	private static function launcher_js( array $env = array() ): string {
		// Build a JS object literal of credentials to overlay on process.env.
		$entries = array();
		foreach ( $env as $key => $value ) {
			$entries[] = sprintf(
				'  %s: %s',
				wp_json_encode( (string) $key ),
				wp_json_encode( (string) $value )
			);
		}
		$env_object = "{\n" . implode( ",\n", $entries ) . "\n}";

		// CJS wrapper (require/module.exports available in any Node.js version).
		// Uses --input-type=module via execFileSync to run the ESM proxy.mjs.
		return "'use strict';\n"
			. "// EMCP Tools MCPB entry-point — runs the bundled proxy via Node.\n"
			. "// No npx, no npm, no internet connection needed.\n"
			. "const path = require('path');\n"
			. "const { spawnSync } = require('child_process');\n"
			. "const proxyPath = path.join(__dirname, 'proxy.mjs');\n"
			. "const injected = " . $env_object . ";\n"
			. "const env = Object.assign({}, process.env, injected);\n"
			. "const result = spawnSync(process.execPath, [proxyPath], {\n"
			. "  stdio: 'inherit',\n"
			. "  env: env,\n"
			. "});\n"
			. "process.exit(result.status == null ? 0 : result.status);\n";
	}
}
