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

	protected static function ask_elements( $schema, $elements, $progress_bar ) {}

	protected static function get_total_elements( $elements ) {
		return count( $elements );
	}
}