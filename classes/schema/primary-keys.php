<?php
/**
 * The class for a primary keys
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

use DeliciousBrains\MergebotSchemaGenerator\Command;
use DeliciousBrains\MergebotSchemaGenerator\Mergebot_Schema_Generator;

class Primary_Keys {

	public static function get_elements( $tables ) {
		Mergebot_Schema_Generator::log( 'Primary Keys' , '%R');
		$primary_keys = self::get_primary_keys( $tables );

		return $primary_keys;
	}
	/**
	 * Get the Primary keys for an array of tables
	 * 
	 * @param array $tables
	 *
	 * @return array
	 */
	public static function get_primary_keys( $tables ) {
		$primary_keys = array();
		foreach ( $tables as $table => $columns ) {
			foreach ( $columns as $column ) {
				if ( false === stripos( $column->Type, 'int' ) ) {
					continue;
				}

				if ( self::is_pk_column( $column ) ) {
					$primary_keys[ $table ] = $column->Field;
				}
			}
		}

		return $primary_keys;
	}

	/**
	 * Is a MySQL table column a Primary Key?
	 *
	 * @param object $column
	 *
	 * @return bool
	 */
	public static function is_pk_column( $column ) {
		return 'auto_increment' === $column->Extra;
	}
}
