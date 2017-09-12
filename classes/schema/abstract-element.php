<?php
/**
 * The abstarct class for schema elements
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

use DeliciousBrains\MergebotSchemaGenerator\Mergebot_Schema_Generator;
use PhpParser\Error;
use PhpParser\ParserFactory;

abstract class Abstract_Element {

	protected static $element;
	protected static $colour;

	public static function get_elements( Schema $schema, $elements ) {
		Mergebot_Schema_Generator::log( rtrim( static::$element, 's' ) . 's', '%' . static::$colour );
		$total_elements = static::get_total_elements( $elements );
		$message        = sprintf( 'Processing %s %s', $total_elements, static::$element );
		$progress_bar   = \WP_CLI\Utils\make_progress_bar( \WP_CLI::colorize( '%' . static::$colour . $message . ':%n' ), $total_elements );

		$elements = static::ask_elements( $schema, $elements, $progress_bar );
		$progress_bar->finish();

		return $elements;
	}

	public static function find_elements( Schema $schema ) {
	}

	protected static function ask_elements( Schema $schema, $elements, $progress_bar ) {
	}

	protected static function get_total_elements( $elements ) {
		return count( $elements );
	}

	/**
	 * Get the function arguments by spoofing the PHP code and parsing it.
	 * Regex and explode too unreliable.
	 *
	 * @param string $string
	 *
	 * @return array
	 */
	protected static function get_function_args_from_string( $string ) {
		$code   = '<?php test( ' . $string . ' );';
		$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );

		try {
			$statements = $parser->parse( $code );

			$printer = new Php_Parser_Printer();

			$args = $printer->get_args_from_node( $statements[0]->args );
		} catch ( Error $e ) {
			$args = explode( ',', $string );
		}

		return $args;
	}

	/**
	 * @param $filename
	 *
	 * @return array|mixed|object
	 */
	protected static function read_data_file( $filename ) {
		$file = dirname( Mergebot_Schema_Generator()->file_path ) . '/data/' . $filename . '.json';
		if ( ! file_exists( $file ) ) {
			self::write_data_file( $file );

			return array();
		}

		$contents = file_get_contents( $file );
		if ( empty( $contents ) ) {
			return array();
		}

		return json_decode( $contents, true );
	}

	/**
	 * @param string $filename
	 * @param array  $content
	 *
	 * @return int
	 */
	protected static function write_data_file( $filename, $content = array() ) {
		$file    = dirname( Mergebot_Schema_Generator()->file_path ) . '/data/' . $filename . '.json';
		$content = json_encode( $content, JSON_PRETTY_PRINT );

		return file_put_contents( $file, $content );
	}

	protected static function get_class_for_method( $files, $contents, $method ) {
		$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );

		try {
			$statements = $parser->parse( $contents );

			foreach ( $statements as $section ) {
				if ( ! is_a( $section, 'PhpParser\Node\Stmt\Class_' ) ) {
					continue;
				}

				if ( ! isset( $section->stmts ) ) {
					continue;
				}

//				if ( $section->isAbstract() ) {
//						// Class is abstract. Shit.
//						$child_class = self::get_child_class_of_abstract( $files, $section->name );
//
//						if ( $child_class ) {
//							return $child_class;
//						}
//				}

				foreach ( $section->stmts as $class_section ) {
					if ( ! is_a( $class_section, 'PhpParser\Node\Stmt\ClassMethod' ) ) {
						continue;
					}

					if ( $method === $class_section->name ) {
						return array( 'name' => $section->name );
					}
				}
			}
		} catch ( Error $e ) {
			return false;
		}

		return false;
	}

	protected static function get_child_class_of_abstract( $files, $abstract_class ) {
		foreach ( $files as $file ) {
			$content = strtolower( file_get_contents( $file->getRealPath() ) );

			$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );

			try {
				$statements = $parser->parse( $content );
				foreach ( $statements as $section ) {
					if ( ! is_a( $section, 'PhpParser\Node\Stmt\Class_' ) ) {
						continue;
					}

					if ( ! isset( $section->extends ) || empty( $section->extends ) ) {
						continue;
					}

					$parent_class = $section->extends->parts[0];

					if ( strtolower( $abstract_class ) !== strtolower( $parent_class ) ) {
						continue;
					}

					return array( 'name' => $section->name, 'file' => $file->getRealPath() );

				}
			} catch ( Error $e ) {
			}
		}

		return false;
	}


	protected static function get_method_code_from_file( $file, $class, $method ) {
		$code   = file_get_contents( $file );
		$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );

		try {
			$statements = $parser->parse( $code );
		} catch ( Error $e ) {
			return false;
		}

		foreach ( $statements as $section ) {
			if ( ! is_a( $section, 'PhpParser\Node\Stmt\Class_' ) ) {
				continue;
			}

			if ( ! isset( $section->stmts ) ) {
				continue;
			}

			if ( strtolower( $class ) !== strtolower( $section->name ) ) {
				continue;
			}

			foreach ( $section->stmts as $class_section ) {
				if ( ! is_a( $class_section, 'PhpParser\Node\Stmt\ClassMethod' ) ) {
					continue;
				}

				if ( $method !== $class_section->name ) {
					continue;
				}

				$printer = new Php_Parser_Printer();

				return $printer->get_method_code( $class_section );
			}
		}

		return false;
	}

	protected static function get_function_code_from_file( $file, $function ) {
		$code   = file_get_contents( $file );
		$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );

		try {
			$statements = $parser->parse( $code );
		} catch ( Error $e ) {
			return false;
		}

		foreach ( $statements as $section ) {
			if ( ! is_a( $section, 'PhpParser\Node\Stmt\Function_' ) ) {
				continue;
			}

			if ( strtolower( $function ) !== strtolower( $section->name ) ) {
				continue;
			}

			$printer = new Php_Parser_Printer();

			return $printer->get_function_code( $section );
		}

		return false;
	}
}