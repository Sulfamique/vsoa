<?php

namespace Vsoa\Tests\Unit;

use Brain\Monkey\Functions;
use Vsoa\API\RestController;
use Vsoa\Tests\TestCase;
use WP_REST_Request;

class RestControllerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'sanitize_text_field' )->alias(
			fn ( $value ) => $value
		);

		Functions\when( 'apply_filters' )->alias(
			function ( $tag, $value ) {
				return func_num_args() >= 3 ? func_get_arg( 2 ) : $value;
			}
		);

		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'has_filter' )->justReturn( false );
	}

	public function test_resolve_offer_requires_value(): void {
		$controller = new RestController();
		$request    = new WP_REST_Request();

		$response = $controller->resolve_offer( $request );

		$this->assertInstanceOf( '\WP_Error', $response );
	}

	public function test_resolve_offer_returns_token(): void {
		$controller = new RestController();
		$request    = new WP_REST_Request( [ 'data_value' => 'offer1' ] );

		$response = $controller->resolve_offer( $request );

		$this->assertInstanceOf( '\WP_REST_Response', $response );
		$this->assertArrayHasKey( 'token', $response->data );
		$this->assertSame( MINUTE_IN_SECONDS, $response->data['ttl'] );
	}
}
