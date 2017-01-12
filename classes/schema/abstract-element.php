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

	public static function get_elements( Schema $schema ) {
		Mergebot_Schema_Generator::log( static::$element, '%' . static::$colour );

		$elements       = static::find_elements( $schema );
		$total_elements = static::get_total_elements( $elements );
		$message        = sprintf( 'Processing %s %s', $total_elements, static::$element );
		$progress_bar   = \WP_CLI\Utils\make_progress_bar( \WP_CLI::colorize( '%' . static::$colour . $message . ':%n' ), $total_elements );

		$elements = static::ask_elements( $schema, $elements, $progress_bar );
		$progress_bar->finish();

		return $elements;
	}

	protected static function find_elements( Schema $schema ) {}

	protected static function ask_elements( Schema $schema, $elements, $progress_bar ) {}

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
	 * @return array|mixed|object
	 */
	protected static function read_data_file( $filename ) {
		if ( ! file_exists( $filename ) ) {
			self::write_data_file( $filename );

			return array();
		}

		$contents = file_get_contents( $filename );
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
		$content = json_encode( $content, JSON_PRETTY_PRINT );

		return file_put_contents( $filename, $content );
	}
}