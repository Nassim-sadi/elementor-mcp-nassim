# Optional Elementor Dependency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Elementor optional — the plugin and all beyond-Elementor MCP tools load and work without Elementor; the Elementor tool family stops registering and the admin shows a non-blocking warning (plus notices on the Brand Kits / Templates tabs).

**Architecture:** A single `EMCP_Tools_Bootstrap::elementor_active()` gate. The bootstrap dependency check drops Elementor from the hard-blocker list (PHP/Abilities-API/MCP-Adapter still bail) and emits a warning notice instead. `register_all()` takes an `$elementor_active` flag and registers the 17 Elementor-dependent groups only when true; the 11 pure-WordPress groups always register. The admin reads the same gate to disable the Elementor sub-tab's toggles, keep the stats counts truthful, and notice the two Pro tabs.

**Tech Stack:** PHP 8.1+, WordPress (admin notices, `did_action`), the WordPress Abilities API + bundled MCP Adapter, PHPUnit with the `tests/bootstrap.php` stub harness.

**Reference spec:** `docs/superpowers/specs/2026-06-28-elementor-optional-dependency-design.md`

## Conventions every task follows
- PHP for tests/lint: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe`. Full suite: `"$PHP" vendor/bin/phpunit`. Lint: `"$PHP" -l <file>`.
- Stage ONLY the files named in each task's commit. The working tree has unrelated pre-existing modified files (`bin/*`, `includes/admin/class-pro-*.php`, etc.) — never stage them.
- Match existing code style (tabs, WP conventions, translatable strings).

## File Structure

| File | Change |
|---|---|
| `tests/bootstrap.php` | add a `did_action()` stub + autoload `$map` entry for `EMCP_Tools_Bootstrap` |
| `includes/class-bootstrap.php` | add `elementor_active()`; drop Elementor from hard blockers; add warning notice |
| `includes/abilities/class-ability-registrar.php` | `register_all( bool $elementor_active )`; gate the 17 Elementor groups |
| `includes/class-plugin.php` | pass `EMCP_Tools_Bootstrap::elementor_active()` into `register_all()` |
| `includes/admin/class-admin.php` | pure `is_elementor_category()` + `filter_out_elementor()`; exclude Elementor categories from counts when inactive |
| `includes/admin/views/page-tools.php` | Elementor sub-tab warning banner + disabled toggles when inactive |
| `includes/admin/views/page-brand-kits.php` | notice when inactive |
| `includes/admin/views/page-templates.php` | notice when inactive |
| `tests/unit/...` | new tests for the gate helper + admin filters |
| docs | CLAUDE.md, readme.txt, CHANGELOG.md |

---

## Task 1: The `elementor_active()` gate + test seam

**Files:**
- Modify: `tests/bootstrap.php` (add `did_action` stub near the other WP stubs; add autoload entry)
- Modify: `includes/class-bootstrap.php` (add the helper)
- Test: `tests/unit/BootstrapElementorActiveTest.php`

- [ ] **Step 1: Add a `did_action()` stub** to `tests/bootstrap.php`, inside the global `namespace { ... }` stub block (near `do_action`):

```php
		if ( ! function_exists( 'did_action' ) ) {
			function did_action( string $hook_name ): int {
				return (int) ( $GLOBALS['_did_actions'][ $hook_name ] ?? 0 );
			}
		}
```

- [ ] **Step 2: Add the autoload entry** for `EMCP_Tools_Bootstrap` in the `tests/bootstrap.php` `$map` (after the `EMCP_Tools_Site_Context`/`EMCP_Tools_Admin` entries):

```php
			'EMCP_Tools_Bootstrap'              => 'includes/class-bootstrap.php',
```

- [ ] **Step 3: Write the failing test** — `tests/unit/BootstrapElementorActiveTest.php`:

```php
<?php
/**
 * @group bootstrap
 * @package EMCP_Tools\Tests
 */
namespace EMCP_Tools\Tests;

use PHPUnit\Framework\TestCase;

class BootstrapElementorActiveTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_did_actions'] = array();
	}

	/** @test */
	public function elementor_active_is_false_when_loaded_action_never_fired(): void {
		$this->assertFalse( \EMCP_Tools_Bootstrap::elementor_active() );
	}

	/** @test */
	public function elementor_active_is_true_after_loaded_action(): void {
		$GLOBALS['_did_actions']['elementor/loaded'] = 1;
		$this->assertTrue( \EMCP_Tools_Bootstrap::elementor_active() );
	}
}
```

- [ ] **Step 4: Run it to verify it FAILS**

Run: `"$PHP" vendor/bin/phpunit --filter BootstrapElementorActiveTest`
Expected: FAIL — `EMCP_Tools_Bootstrap::elementor_active()` not defined.

- [ ] **Step 5: Implement** — add to `includes/class-bootstrap.php` (a `public static` method on the class, e.g. directly after `boot()`):

```php
	/**
	 * Whether Elementor is loaded/active in this request.
	 *
	 * Single source of truth for the optional-Elementor gate: the tool registrar,
	 * the admin Tools page, and the Brand Kits / Templates tabs all read this.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public static function elementor_active(): bool {
		return (bool) did_action( 'elementor/loaded' );
	}
```

- [ ] **Step 6: Run to verify it PASSES**

Run: `"$PHP" vendor/bin/phpunit --filter BootstrapElementorActiveTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add includes/class-bootstrap.php tests/bootstrap.php tests/unit/BootstrapElementorActiveTest.php
git commit -m "feat(deps): add EMCP_Tools_Bootstrap::elementor_active() gate"
```

---

## Task 2: Drop Elementor from hard dependencies; warn instead

**Files:**
- Modify: `includes/class-bootstrap.php` (`check_dependencies()`)

This is WP-context glue (no unit test — verified live in Task 8). Lint after.

- [ ] **Step 1: Remove the Elementor hard-blocker.** In `check_dependencies()`, delete this block:

```php
		// Elementor must be active.
		if ( ! did_action( 'elementor/loaded' ) ) {
			$missing[] = 'Elementor';
		}

```

(Leave the WordPress Abilities API and MCP Adapter checks intact — they remain hard blockers.)

- [ ] **Step 2: Emit a warning when Elementor is inactive.** Still inside `check_dependencies()`, immediately **before** the final `return true;`, add:

```php
		// Elementor is OPTIONAL. When absent, the plugin still loads and every
		// beyond-Elementor tool works; only the Elementor tool family + the
		// Elementor admin areas are unavailable. Surface a non-blocking warning.
		if ( ! self::elementor_active() ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return;
					}
					$install = self_admin_url( 'plugin-install.php?s=Elementor&tab=search&type=term' );
					printf(
						'<div class="notice notice-warning"><p>%s</p><p><a class="button button-secondary" href="%s">%s</a></p></div>',
						esc_html__( 'EMCP Tools is active. Install and activate Elementor to enable the Elementor page-building tools (widgets, layout, templates, brand kits). All other tools — WordPress content, plugins & themes, users, media, performance, security, filesystem, and database — work without it.', 'emcp-tools' ),
						esc_url( $install ),
						esc_html__( 'Install Elementor', 'emcp-tools' )
					);
				}
			);
		}

```

- [ ] **Step 2b: Confirm `boot()` no longer bails on Elementor.** Verify `boot()` still has `if ( ! self::check_dependencies() ) { return; }` — that's correct and unchanged: now `check_dependencies()` only returns false for the remaining hard deps, so a missing Elementor no longer stops the plugin. No edit needed; just confirm.

- [ ] **Step 3: Lint**

Run: `"$PHP" -l includes/class-bootstrap.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Run the full suite** (nothing should regress)

Run: `"$PHP" vendor/bin/phpunit`
Expected: 0 failures.

- [ ] **Step 5: Commit**

```bash
git add includes/class-bootstrap.php
git commit -m "feat(deps): Elementor optional — warn instead of bailing the plugin"
```

---

## Task 3: Gate the Elementor ability groups in the registrar

**Files:**
- Modify: `includes/abilities/class-ability-registrar.php`

Change `register_all()` to take `bool $elementor_active` and register the 17 Elementor-dependent groups only when true. The 11 pure-WordPress groups always register. Gating is verified live (Task 8) + by the full suite still passing.

- [ ] **Step 1: Change the signature and gate the groups.** Replace the whole `register_all()` method body. The method currently registers every group unconditionally; restructure so the WordPress groups register first (always), then the Elementor groups register inside `if ( $elementor_active )`. Use this exact method:

```php
	public function register_all( bool $elementor_active = true ): array {
		// ---- Always-on: pure-WordPress tool groups (no Elementor needed) ----

		// Media Library query ability (list/search the site's own uploads).
		$media_library = new EMCP_Tools_Media_Library_Abilities( $this->data );
		$media_library->register();
		$this->ability_names = array_merge( $this->ability_names, $media_library->get_ability_names() );

		// WordPress Content abilities (posts/pages/CPT CRUD + taxonomy + meta).
		$content = new EMCP_Tools_Content_Abilities();
		$content->register();
		$this->ability_names = array_merge( $this->ability_names, $content->get_ability_names() );

		// WordPress Settings abilities (curated site-settings read/update).
		$settings = new EMCP_Tools_Settings_Abilities();
		$settings->register();
		$this->ability_names = array_merge( $this->ability_names, $settings->get_ability_names() );

		// WordPress Plugins & Themes abilities.
		$plugins = new EMCP_Tools_Plugin_Abilities();
		$plugins->register();
		$this->ability_names = array_merge( $this->ability_names, $plugins->get_ability_names() );

		$themes = new EMCP_Tools_Theme_Abilities();
		$themes->register();
		$this->ability_names = array_merge( $this->ability_names, $themes->get_ability_names() );

		// WordPress Users abilities.
		$users = new EMCP_Tools_User_Abilities();
		$users->register();
		$this->ability_names = array_merge( $this->ability_names, $users->get_ability_names() );

		// Performance Analyzer (read-only).
		$performance = new EMCP_Tools_Performance_Abilities();
		$performance->register();
		$this->ability_names = array_merge( $this->ability_names, $performance->get_ability_names() );

		// Filesystem abilities (writes disabled-by-default).
		$filesystem = new EMCP_Tools_Filesystem_Abilities();
		$filesystem->register();
		$this->ability_names = array_merge( $this->ability_names, $filesystem->get_ability_names() );

		// Database abilities (writes disabled-by-default).
		$database = new EMCP_Tools_Database_Abilities();
		$database->register();
		$this->ability_names = array_merge( $this->ability_names, $database->get_ability_names() );

		// Security & Malware Scanner (read-only).
		$security = new EMCP_Tools_Security_Abilities();
		$security->register();
		$this->ability_names = array_merge( $this->ability_names, $security->get_ability_names() );

		// PHP Snippet abilities (Sandbox) — free, capability-gated, no Elementor.
		if ( class_exists( 'EMCP_Tools_PHP_Snippet_Abilities' ) ) {
			$php_snippets = new EMCP_Tools_PHP_Snippet_Abilities();
			$php_snippets->register();
			$this->ability_names = array_merge( $this->ability_names, $php_snippets->get_ability_names() );
		}

		// ---- Elementor-dependent groups: only when Elementor is active ----
		if ( $elementor_active ) {
			// P0 query/discovery.
			$query = new EMCP_Tools_Query_Abilities( $this->data, $this->schema_generator );
			$query->register();
			$this->ability_names = array_merge( $this->ability_names, $query->get_ability_names() );

			// P1 page CRUD.
			$pages = new EMCP_Tools_Page_Abilities( $this->data, $this->factory );
			$pages->register();
			$this->ability_names = array_merge( $this->ability_names, $pages->get_ability_names() );

			// P1 layout/container.
			$layout = new EMCP_Tools_Layout_Abilities( $this->data, $this->factory );
			$layout->register();
			$this->ability_names = array_merge( $this->ability_names, $layout->get_ability_names() );

			// Widgets (catalog-backed).
			$widgets = new EMCP_Tools_Widget_Abilities( $this->data, $this->factory, $this->schema_generator, $this->validator );
			$widgets->register();
			$this->ability_names = array_merge( $this->ability_names, $widgets->get_ability_names() );

			// Templates.
			$templates = new EMCP_Tools_Template_Abilities( $this->data, $this->factory );
			$templates->register();
			$this->ability_names = array_merge( $this->ability_names, $templates->get_ability_names() );

			// Global settings.
			$globals = new EMCP_Tools_Global_Abilities( $this->data );
			$globals->register();
			$this->ability_names = array_merge( $this->ability_names, $globals->get_ability_names() );

			// Composite build-page.
			$composite = new EMCP_Tools_Composite_Abilities( $this->data, $this->factory );
			$composite->register();
			$this->ability_names = array_merge( $this->ability_names, $composite->get_ability_names() );

			// Stock images.
			$stock_images = new EMCP_Tools_Stock_Image_Abilities( $this->data, $this->factory );
			$stock_images->register();
			$this->ability_names = array_merge( $this->ability_names, $stock_images->get_ability_names() );

			// SVG icons.
			$svg_icons = new EMCP_Tools_Svg_Icon_Abilities( $this->data, $this->factory );
			$svg_icons->register();
			$this->ability_names = array_merge( $this->ability_names, $svg_icons->get_ability_names() );

			// Custom code (CSS, JS, snippets).
			$custom_code = new EMCP_Tools_Custom_Code_Abilities( $this->data, $this->factory );
			$custom_code->register();
			$this->ability_names = array_merge( $this->ability_names, $custom_code->get_ability_names() );

			// Atomic widgets (Elementor 4.0+; self-guards on version).
			$atomic_widgets = new EMCP_Tools_Atomic_Widget_Abilities( $this->data, $this->factory );
			$atomic_widgets->register();
			$this->ability_names = array_merge( $this->ability_names, $atomic_widgets->get_ability_names() );

			// Atomic layout (Elementor 4.0+; includes detect-elementor-version).
			$atomic_layout = new EMCP_Tools_Atomic_Layout_Abilities( $this->data, $this->factory );
			$atomic_layout->register();
			$this->ability_names = array_merge( $this->ability_names, $atomic_layout->get_ability_names() );

			// Global Classes reader — self-gates on Elementor 4.0+.
			if ( class_exists( 'EMCP_Tools_Global_Classes_Abilities' ) ) {
				$global_classes = new EMCP_Tools_Global_Classes_Abilities();
				$global_classes->register();
				$this->ability_names = array_merge( $this->ability_names, $global_classes->get_ability_names() );
			}

			// Brand kit / system-kit (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_System_Kit_Abilities' ) ) {
				$brand_kits = new EMCP_Tools_System_Kit_Abilities();
				$brand_kits->register();
				$this->ability_names = array_merge( $this->ability_names, $brand_kits->get_ability_names() );
			}

			// SEO toolkit (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_Seo_Abilities' ) ) {
				$seo = new EMCP_Tools_Seo_Abilities( $this->data );
				$seo->register();
				$this->ability_names = array_merge( $this->ability_names, $seo->get_ability_names() );
			}

			// Accessibility toolkit (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_A11y_Abilities' ) ) {
				$a11y = new EMCP_Tools_A11y_Abilities( $this->data );
				$a11y->register();
				$this->ability_names = array_merge( $this->ability_names, $a11y->get_ability_names() );
			}

			// Widget Builder (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_Widget_Builder_Abilities' ) ) {
				$widget_builder = new EMCP_Tools_Widget_Builder_Abilities();
				$widget_builder->register();
				$this->ability_names = array_merge( $this->ability_names, $widget_builder->get_ability_names() );
			}
		}

		/**
		 * Filters the registered ability names.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $ability_names The registered ability names.
		 */
		$this->ability_names = apply_filters( 'emcp_tools_ability_names', $this->ability_names );

		return $this->ability_names;
	}
```

> Cross-check while editing: every group present in the original `register_all()` must appear exactly once in the new version — 11 always-on + 17 gated. The original order within each bucket is preserved where practical. Do not drop the `class_exists()` self-guards on the Pro/atomic/global-classes groups.

- [ ] **Step 2: Lint**

Run: `"$PHP" -l includes/abilities/class-ability-registrar.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add includes/abilities/class-ability-registrar.php
git commit -m "feat(deps): gate Elementor ability groups on elementor_active in register_all"
```

---

## Task 4: Pass the gate from the plugin singleton

**Files:**
- Modify: `includes/class-plugin.php` (`register_abilities()`, around line 310-312)

- [ ] **Step 1: Pass the flag.** Replace:

```php
	public function register_abilities(): void {
		$this->ability_names = $this->registrar->register_all();
	}
```

with:

```php
	public function register_abilities(): void {
		$this->ability_names = $this->registrar->register_all( EMCP_Tools_Bootstrap::elementor_active() );
	}
```

- [ ] **Step 2: Lint**

Run: `"$PHP" -l includes/class-plugin.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Full suite**

Run: `"$PHP" vendor/bin/phpunit`
Expected: 0 failures (the default `register_all()` arg is `true`, so any existing caller/test is unaffected).

- [ ] **Step 4: Commit**

```bash
git add includes/class-plugin.php
git commit -m "feat(deps): plugin passes elementor_active into register_all"
```

---

## Task 5: Keep the admin stats truthful when Elementor is inactive

**Files:**
- Modify: `includes/admin/class-admin.php` (add two helpers; use them in `get_all_tool_slugs()`)
- Test: `tests/unit/admin/ElementorAvailabilityTest.php`

- [ ] **Step 1: Write the failing test** — `tests/unit/admin/ElementorAvailabilityTest.php`:

```php
<?php
/**
 * @group admin
 * @package EMCP_Tools\Tests\Admin
 */
namespace EMCP_Tools\Tests\Admin;

use PHPUnit\Framework\TestCase;

class ElementorAvailabilityTest extends TestCase {

	/** @test */
	public function is_elementor_category_defaults_missing_platform_to_elementor(): void {
		$this->assertTrue( \EMCP_Tools_Admin::is_elementor_category( array( 'tools' => array() ) ) );
		$this->assertTrue( \EMCP_Tools_Admin::is_elementor_category( array( 'platform' => 'elementor', 'tools' => array() ) ) );
		$this->assertFalse( \EMCP_Tools_Admin::is_elementor_category( array( 'platform' => 'wordpress', 'tools' => array() ) ) );
	}

	/** @test */
	public function filter_out_elementor_drops_only_elementor_platform_categories(): void {
		$cats = array(
			'query'      => array( 'platform' => 'elementor', 'tools' => array() ),
			'content'    => array( 'platform' => 'wordpress', 'tools' => array() ),
			'no_platform'=> array( 'tools' => array() ), // defaults to elementor
			'security'   => array( 'platform' => 'wordpress', 'tools' => array() ),
		);
		$kept = \EMCP_Tools_Admin::filter_out_elementor( $cats );
		$this->assertSame( array( 'content', 'security' ), array_keys( $kept ) );
	}
}
```

- [ ] **Step 2: Run it to verify it FAILS**

Run: `"$PHP" vendor/bin/phpunit --filter ElementorAvailabilityTest`
Expected: FAIL — methods not defined.

- [ ] **Step 3: Add the two pure static helpers** to `EMCP_Tools_Admin` (place them next to `partition_by_platform()`):

```php
	/**
	 * Whether a tool category belongs to the Elementor platform (the default
	 * when no platform is set), i.e. it is unavailable when Elementor is inactive.
	 *
	 * @since 3.0.0
	 *
	 * @param array $category A get_all_tools() category.
	 * @return bool
	 */
	public static function is_elementor_category( array $category ): bool {
		return 'elementor' === ( $category['platform'] ?? 'elementor' );
	}

	/**
	 * Returns the categories with the Elementor-platform ones removed. Used for
	 * truthful tool counts when Elementor is inactive (those tools never register).
	 *
	 * @since 3.0.0
	 *
	 * @param array $categories get_all_tools() output.
	 * @return array
	 */
	public static function filter_out_elementor( array $categories ): array {
		return array_filter(
			$categories,
			static function ( $cat ) {
				return ! self::is_elementor_category( $cat );
			}
		);
	}
```

- [ ] **Step 4: Use the filter in `get_all_tool_slugs()`** so counts exclude Elementor tools when inactive. Replace:

```php
	public function get_all_tool_slugs(): array {
		$slugs = array();

		foreach ( $this->get_all_tools() as $category ) {
			foreach ( $category['tools'] as $slug => $tool ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}
```

with:

```php
	public function get_all_tool_slugs(): array {
		$slugs      = array();
		$categories = $this->get_all_tools();

		// When Elementor is inactive its tool groups never register, so they must
		// not inflate the "X of Y enabled" stats. Drop Elementor-platform categories.
		if ( ! EMCP_Tools_Bootstrap::elementor_active() ) {
			$categories = self::filter_out_elementor( $categories );
		}

		foreach ( $categories as $category ) {
			foreach ( $category['tools'] as $slug => $tool ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}
```

- [ ] **Step 5: Run to verify the new test PASSES + nothing regresses**

Run: `"$PHP" vendor/bin/phpunit --filter ElementorAvailabilityTest` → PASS (2 tests).
Then: `"$PHP" vendor/bin/phpunit` → 0 failures. (Existing admin tests don't set `$GLOBALS['_did_actions']`, so `elementor_active()` is false there; confirm none assert an exact total tool count that would shift. If one does, set `$GLOBALS['_did_actions']['elementor/loaded'] = 1;` in that test's `setUp` to preserve its assumption, and note it in the commit.)

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-admin.php tests/unit/admin/ElementorAvailabilityTest.php
git commit -m "feat(admin): exclude Elementor tools from stats counts when Elementor inactive"
```

---

## Task 6: Tools page — warning banner + disabled Elementor toggles

**Files:**
- Modify: `includes/admin/views/page-tools.php`

The view already builds `$emcp_tools_buckets = EMCP_Tools_Admin::partition_by_platform( $emcp_tools_all_tools )` and renders per-platform tabs. Add the gate at the top and use it to (a) show a banner inside the Elementor tab and (b) disable the Elementor toggles.

- [ ] **Step 1: Read the view** to find where the Elementor (`$tab_id === 'elementor'`) bucket renders its category list and where each tool's toggle `<input type="checkbox">` is emitted. (Run `grep -n "elementor\|checkbox\|data-platform\|foreach.*buckets\|category" includes/admin/views/page-tools.php`.)

- [ ] **Step 2: Define the gate** near the top of the view (after the `$emcp_tools_buckets` line, ~line 25):

```php
$emcp_tools_elementor_active = EMCP_Tools_Bootstrap::elementor_active();
```

- [ ] **Step 3: Render a warning banner at the top of the Elementor tab panel.** In the loop that renders each platform tab's panel, when the tab is `elementor` and `! $emcp_tools_elementor_active`, output (place it just inside the Elementor tab panel, before its categories):

```php
<?php if ( 'elementor' === $emcp_tools_tab_id && ! $emcp_tools_elementor_active ) : ?>
	<div class="notice notice-warning inline elementor-mcp-elementor-inactive">
		<p>
			<?php esc_html_e( 'Elementor is not active, so these tools are unavailable. Install and activate Elementor to use them.', 'emcp-tools' ); ?>
			<a href="<?php echo esc_url( self_admin_url( 'plugin-install.php?s=Elementor&tab=search&type=term' ) ); ?>"><?php esc_html_e( 'Install Elementor', 'emcp-tools' ); ?></a>
		</p>
	</div>
<?php endif; ?>
```

(Use the actual loop variable name for the current tab id found in Step 1 — the snippet assumes `$emcp_tools_tab_id`; rename to match.)

- [ ] **Step 4: Disable the Elementor toggles + grey the cards.** Where each tool toggle `<input>` is rendered, add `disabled` and an `is-unavailable` class when the tool's category is an Elementor-platform category and `! $emcp_tools_elementor_active`. Compute a per-category flag in the category loop:

```php
$emcp_tools_cat_unavailable = ( ! $emcp_tools_elementor_active && EMCP_Tools_Admin::is_elementor_category( $emcp_tools_category ) );
```

Add `<?php disabled( $emcp_tools_cat_unavailable ); ?>` to the checkbox `<input>` tag, and append `is-unavailable` to the tool card's class list when `$emcp_tools_cat_unavailable` is true (mirror however the view already conditionally adds classes, e.g. the `is-danger` pattern).

- [ ] **Step 5: Add the greyed-out CSS.** Append to `assets/css/admin.css`:

```css
.elementor-mcp-tool-card.is-unavailable { opacity: 0.55; }
.elementor-mcp-tool-card.is-unavailable .elementor-mcp-toggle-track { cursor: not-allowed; }
.elementor-mcp-elementor-inactive { margin: 0 0 16px; }
```

- [ ] **Step 6: Lint the view**

Run: `"$PHP" -l includes/admin/views/page-tools.php`
Expected: `No syntax errors detected`.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/views/page-tools.php assets/css/admin.css
git commit -m "feat(admin): Elementor sub-tab shows warning + disabled toggles when inactive"
```

---

## Task 7: Brand Kits & Templates tabs — notice when Elementor inactive

**Files:**
- Modify: `includes/admin/views/page-brand-kits.php`
- Modify: `includes/admin/views/page-templates.php`

- [ ] **Step 1: Add the notice to Brand Kits.** Near the very top of the rendered output in `includes/admin/views/page-brand-kits.php` (after the opening wrapper `<div>`, before the existing content), add:

```php
<?php if ( ! EMCP_Tools_Bootstrap::elementor_active() ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<?php esc_html_e( 'Brand Kits apply colors and typography to your Elementor kit. Install and activate Elementor to use this feature.', 'emcp-tools' ); ?>
			<a href="<?php echo esc_url( self_admin_url( 'plugin-install.php?s=Elementor&tab=search&type=term' ) ); ?>"><?php esc_html_e( 'Install Elementor', 'emcp-tools' ); ?></a>
		</p>
	</div>
<?php endif; ?>
```

(Find the opening wrapper with `grep -n "elementor-mcp-pro-prompts\|class=\"wrap\|<div" includes/admin/views/page-brand-kits.php | head` and place it just inside.)

- [ ] **Step 2: Add the notice to Templates.** Same snippet, wording adjusted, near the top of `includes/admin/views/page-templates.php`:

```php
<?php if ( ! EMCP_Tools_Bootstrap::elementor_active() ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<?php esc_html_e( 'Templates import ready-made designs into Elementor. Install and activate Elementor to use this feature.', 'emcp-tools' ); ?>
			<a href="<?php echo esc_url( self_admin_url( 'plugin-install.php?s=Elementor&tab=search&type=term' ) ); ?>"><?php esc_html_e( 'Install Elementor', 'emcp-tools' ); ?></a>
		</p>
	</div>
<?php endif; ?>
```

- [ ] **Step 3: Lint both views**

Run: `"$PHP" -l includes/admin/views/page-brand-kits.php && "$PHP" -l includes/admin/views/page-templates.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add includes/admin/views/page-brand-kits.php includes/admin/views/page-templates.php
git commit -m "feat(admin): Brand Kits + Templates tabs notice when Elementor inactive"
```

---

## Task 8: Live end-to-end verification (WP-CLI MCP)

**Files:** none (verification only)

This is the real acceptance gate for the registrar/bootstrap gating, which isn't fully unit-testable.

- [ ] **Step 1: Baseline (Elementor active).** With Elementor + Pro active, capture the current tool count: call `tools/list` on `emcp-tools-server` and record the number of `emcp-tools-*` tools (expect the full ~128).

- [ ] **Step 2: Deactivate Elementor.**

Run: `php /c/wp-cli/wp-cli.phar plugin deactivate elementor elementor-pro --path=f:/laragon/www/msrplugins`

- [ ] **Step 3: Confirm the plugin still loads and the surface shrank.** Confirm EMCP Tools is still active (`wp plugin list --status=active`), there is no fatal in the log, and `tools/list` now shows **only** the WordPress + core tools — none of `list-widgets`, `add-free-widget`, `add-container`, `save-as-template`, `get-global-settings`, `search-images`, etc. Verify a WordPress tool still works end-to-end (e.g. invoke `emcp-tools-list-plugins` or `emcp-tools-scan-security` and confirm a valid response).

- [ ] **Step 4: Confirm the admin.** Load EMCP Tools admin: the site-wide warning notice renders; the Tools page Elementor sub-tab is visible with the warning banner and disabled toggles; the "X of Y enabled" count reflects only WordPress tools; the Brand Kits and Templates tabs show their notices; Connection/Prompts/Context/Skills/Changelog work.

- [ ] **Step 5: Reactivate Elementor and confirm parity.**

Run: `php /c/wp-cli/wp-cli.phar plugin activate elementor elementor-pro --path=f:/laragon/www/msrplugins`
Confirm `tools/list` returns to the full count and the admin returns to normal (no warning, toggles enabled).

- [ ] **Step 6: Full suite green**

Run: `"$PHP" vendor/bin/phpunit`
Expected: 0 failures.

---

## Task 9: Docs

**Files:**
- Modify: `CLAUDE.md`, `readme.txt`, `CHANGELOG.md`

- [ ] **Step 1: CLAUDE.md — Dependencies & Requirements.** Change the Elementor line from a hard dependency to optional. Replace the existing Elementor requirement bullet with:

```markdown
- Elementor — **optional**. The plugin and every beyond-Elementor tool (WordPress Content, Settings, Plugins & Themes, Users, Media, Performance, Security, Filesystem, Database, PHP Snippets) load and work without it. Installing/activating Elementor (>= 3.20; >= 4.0 for atomic elements) enables the Elementor tool family (query, pages, layout, widgets, templates, globals, composite, stock images, SVG icons, custom code, atomic, global classes, brand kits, widget builder, SEO/A11y). When Elementor is inactive those groups don't register and the admin shows a warning. The only hard dependencies are PHP 8.1+, the WordPress Abilities API (core in WP 6.9+), and the bundled MCP Adapter.
```

- [ ] **Step 2: readme.txt.** Update the requirements/description so Elementor reads as recommended/optional (enables the Elementor tools) rather than required, and add a Changelog line:

```
* Changed: Elementor is now OPTIONAL. The plugin and all beyond-Elementor tools (WordPress content, plugins & themes, users, media, performance, security, filesystem, database) work without Elementor. The Elementor tool family registers only when Elementor is active; otherwise the admin shows a warning, and the Brand Kits / Templates tabs show a notice.
```

- [ ] **Step 3: CHANGELOG.md.** Under `## [3.0.0]` → `### Changed`, add:

```markdown
- **Elementor is now optional.** Previously the plugin refused to load without Elementor; now it loads and every WordPress tool (Content, Settings, Plugins & Themes, Users, Media, Performance, Security, Filesystem, Database, PHP Snippets) works on its own. The Elementor tool family (widgets, layout, templates, globals, brand kits, SEO/A11y, atomic, …) registers only when Elementor is active; otherwise a non-blocking admin warning explains how to enable it, and the Brand Kits and Templates tabs show a notice. Hard dependencies are now just PHP 8.1+, the WordPress Abilities API, and the bundled MCP Adapter.
```

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md readme.txt CHANGELOG.md
git commit -m "docs(deps): document Elementor as an optional dependency"
```

---

## Self-Review

**1. Spec coverage:**
- Dependency model split + `elementor_active()` helper + warning notice → Tasks 1, 2. ✓
- Boot no longer bails on missing Elementor → Task 2 (Step 2b confirms). ✓
- Registrar group-level gating (11 always-on / 17 Elementor) → Task 3; plugin passes the flag → Task 4. ✓
- Admin stats truthful (exclude Elementor categories when inactive) → Task 5. ✓
- Tools page: Elementor sub-tab visible + warning banner + disabled toggles → Task 6. ✓
- Brand Kits + Templates notices → Task 7. ✓
- Testing: unit (gate helper Task 1, admin filters Task 5) + live WP-CLI acceptance (Task 8). ✓
- Docs → Task 9. ✓
- Spec "out of scope" (per-tool gating, wizard, auto-install, header change) → correctly absent. ✓

**2. Placeholder scan:** No TBD/TODO. Tasks 3 carries the full method; Task 6's Steps 1/3/4 require reading the view to match its exact loop var/class-toggle idiom (explicitly instructed, with the precise snippet + how to adapt) — that's a read-then-apply instruction, not a placeholder. Every code step shows the code.

**3. Consistency:** `EMCP_Tools_Bootstrap::elementor_active()` is defined in Task 1 and consumed identically in Tasks 4, 5, 6, 7. `register_all( bool $elementor_active = true )` defined in Task 3, called with the gate in Task 4 (default `true` keeps existing tests green). `is_elementor_category()` / `filter_out_elementor()` defined and tested in Task 5, reused in Task 6. The 11-always / 17-gated split is identical between the spec and Task 3. `did_action` stub (Task 1) backs both the bootstrap test and any admin test that needs the active state.
