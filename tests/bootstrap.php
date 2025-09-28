<?php
require_once __DIR__ . '/../vendor/autoload.php';

if ( ! class_exists( 'WP_REST_Request' ) ) {
class WP_REST_Request {
private array $params;
private array $json;
private string $body;

public function __construct( array $params = [], array $json = [], string $body = '' ) {
$this->params = $params;
$this->json   = $json;
$this->body   = $body;
}

public function get_param( string $key ) {
return $this->params[ $key ] ?? null;
}

public function get_json_params(): array {
return $this->json;
}

public function get_body(): string {
return $this->body;
}

public function offsetGet( $offset ) {
return $this->params[ $offset ] ?? null;
}
}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
class WP_REST_Response {
public function __construct( public $data = null, public int $status = 200 ) {}
}
}

if ( ! class_exists( 'WP_Error' ) ) {
class WP_Error {
public function __construct( public string $code, public string $message, public array $data = [] ) {}
}
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 2592000 );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0 ) {
		return json_encode( $data, $options );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return is_string( $value ) ? trim( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return '00000000-0000-4000-8000-000000000000';
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		echo $text;
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return $text;
	}
}
