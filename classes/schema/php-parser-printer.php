<?php
namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

use PhpParser\Error;
use PhpParser\PrettyPrinter\Standard;

class Php_Parser_Printer extends Standard {

	public function get_args_from_node( $arguments ) {
		$args = array();
		foreach ( $arguments as $arg ) {
			$class = get_class( $arg->value );
			$class = str_replace( 'PhpParser\\Node\\', '', $class );
			$class = str_replace( '\\', '_', $class );

			$method = 'p' . rtrim( $class, '_' );

			if ( ! method_exists( $this, $method) ) {
				throw new Error();
			}

			$args[] = $this->$method( $arg->value );
		}

		return $args;
	}
}