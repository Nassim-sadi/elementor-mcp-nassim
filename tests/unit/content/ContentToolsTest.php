<?php
/**
 * Execute-path + validation tests for the WordPress Content tools.
 * @group content
 * @package EMCP_Tools\Tests\Content
 */
namespace EMCP_Tools\Tests\Content;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ContentToolsTest extends Ability_Test_Case {
	private \EMCP_Tools_Content_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_posts']             = array();
		$GLOBALS['_wp_trashed_posts']     = array();
		$GLOBALS['_wp_term_calls']        = array();
		$GLOBALS['_wp_thumbnail_calls']   = array();
		$GLOBALS['_wp_current_user_id']   = 1;
		$GLOBALS['_wp_post_type_objects'] = array(
			'post' => (object) array( 'name' => 'post', 'label' => 'Posts', 'hierarchical' => false, 'public' => true, '_builtin' => true ),
			'page' => (object) array( 'name' => 'page', 'label' => 'Pages', 'hierarchical' => true, 'public' => true, '_builtin' => true ),
		);
		$GLOBALS['_wp_taxonomy_objects'] = array(
			'category' => (object) array( 'name' => 'category', 'label' => 'Categories', 'hierarchical' => true, 'object_type' => array( 'post' ) ),
			'post_tag' => (object) array( 'name' => 'post_tag', 'label' => 'Tags', 'hierarchical' => false, 'object_type' => array( 'post' ) ),
		);
		$this->ability = new \EMCP_Tools_Content_Abilities();
		$this->ability->register();
	}

	/** @test */
	public function test_registers_discovery_tools(): void {
		$names = $this->ability->get_ability_names();
		$this->assertContains( 'emcp-tools/list-post-types', $names );
		$this->assertContains( 'emcp-tools/list-taxonomies', $names );
		$this->assertContains( 'emcp-tools/create-post', $names );
		$this->assertContains( 'emcp-tools/get-post', $names );
	}

	/** @test */
	public function test_list_post_types_returns_public_types(): void {
		$out = $this->ability->execute_list_post_types( array() );
		$this->assertResultHasKey( $out, 'post_types' );
		$names = array_column( $out['post_types'], 'name' );
		$this->assertContains( 'post', $names );
		$this->assertContains( 'page', $names );
	}

	/** @test */
	public function test_list_taxonomies_returns_category(): void {
		$out = $this->ability->execute_list_taxonomies( array() );
		$this->assertResultHasKey( $out, 'taxonomies' );
		$this->assertContains( 'category', array_column( $out['taxonomies'], 'name' ) );
	}

	/** @test */
	public function test_create_post_returns_id_and_permalink(): void {
		$out = $this->ability->execute_create_post( array(
			'post_type' => 'post', 'title' => 'Hello', 'content' => '<p>Hi</p>', 'status' => 'draft',
		) );
		$this->assertNotWPError( $out );
		$this->assertResultHasKey( $out, 'post_id' );
		$this->assertArrayHasKey( 'permalink', $out );
		$this->assertGreaterThan( 100, $out['post_id'] );
	}

	/** @test */
	public function test_create_post_rejects_internal_type(): void {
		$out = $this->ability->execute_create_post( array( 'post_type' => 'revision', 'title' => 'x' ) );
		$this->assertWPError( $out, 'invalid_post_type' );
	}

	/** @test */
	public function test_create_post_rejects_unknown_type(): void {
		$out = $this->ability->execute_create_post( array( 'post_type' => 'no_such_type', 'title' => 'x' ) );
		$this->assertWPError( $out, 'invalid_post_type' );
	}

	/** @test */
	public function test_create_post_rejects_protected_meta(): void {
		$out = $this->ability->execute_create_post( array(
			'post_type' => 'post', 'title' => 'x',
			'meta' => array( '_elementor_data' => '[]' ),
		) );
		$this->assertWPError( $out, 'protected_meta' );
	}

	/** @test */
	public function test_create_post_applies_terms_and_meta(): void {
		$out = $this->ability->execute_create_post( array(
			'post_type' => 'post', 'title' => 'x',
			'terms' => array( 'category' => array( 5 ) ),
			'meta'  => array( 'my_field' => 'v' ),
		) );
		$this->assertNotWPError( $out );
		$this->assertNotEmpty( $GLOBALS['_wp_term_calls'], 'wp_set_object_terms should have been called' );
	}

	/** @test */
	public function test_get_post_returns_shape_and_is_elementor_flag(): void {
		$GLOBALS['_wp_posts'][555] = (object) array(
			'ID' => 555, 'post_type' => 'page', 'post_title' => 'P', 'post_name' => 'p',
			'post_status' => 'publish', 'post_content' => '<p>c</p>', 'post_excerpt' => '',
			'post_date' => '2026-01-01 00:00:00', 'post_modified' => '2026-01-02 00:00:00',
			'post_parent' => 0, 'menu_order' => 0, 'comment_status' => 'open', 'post_author' => 1,
		);
		$out = $this->ability->execute_get_post( array( 'post_id' => 555 ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 555, $out['post_id'] );
		$this->assertSame( 'page', $out['post_type'] );
		$this->assertArrayHasKey( 'is_elementor', $out );
		$this->assertFalse( $out['is_elementor'] );
	}

	/** @test */
	public function test_get_post_not_found(): void {
		$out = $this->ability->execute_get_post( array( 'post_id' => 99999 ) );
		$this->assertWPError( $out, 'post_not_found' );
	}
}
