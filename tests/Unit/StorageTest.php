<?php

namespace Vsoa\Tests\Unit;

use Brain\Monkey\Functions;
use Vsoa\Storage\Storage;
use Vsoa\Tests\TestCase;

class StorageTest extends TestCase {
private array $options;

protected function setUp(): void {
parent::setUp();

$this->options = [];

Functions\when( 'get_option' )->alias( function ( $name, $default = false ) {
return $this->options[ $name ] ?? $default;
} );

Functions\when( 'update_option' )->alias( function ( $name, $value ) {
$this->options[ $name ] = $value;
return true;
} );

Functions\when( 'add_option' )->alias( function ( $name, $value ) {
if ( isset( $this->options[ $name ] ) ) {
return false;
}
$this->options[ $name ] = $value;
return true;
} );

Functions\when( 'delete_option' )->alias( function ( $name ) {
unset( $this->options[ $name ] );
return true;
} );
}

public function test_replace_and_export(): void {
$storage = new Storage();
$rows    = [ [ 'id' => 'a', 'label' => 'A' ], [ 'id' => 'b', 'label' => 'B' ] ];

$storage->replace_all( $rows );

$this->assertSame( $rows, $storage->get_rows() );
$this->assertSame( wp_json_encode( $rows ), $storage->export_json() );
}

public function test_import_merge_and_delete(): void {
$storage = new Storage();

$storage->import_json( wp_json_encode( [ [ 'id' => 'a', 'label' => 'A' ] ] ) );

$result = $storage->import_json( wp_json_encode( [ [ 'id' => 'b', 'label' => 'B' ] ] ), 'merge' );

$this->assertSame( 1, $result['inserted'] );
$this->assertSame( 0, $result['updated'] );

$result = $storage->import_json( wp_json_encode( [ [ 'id' => 'a', 'label' => 'A+' ] ] ), 'merge' );
$this->assertSame( 1, $result['updated'] );

$result = $storage->import_json( wp_json_encode( [ [ 'id' => 'b' ] ] ), 'delete' );
$this->assertSame( 1, $result['deleted'] );
}
}
