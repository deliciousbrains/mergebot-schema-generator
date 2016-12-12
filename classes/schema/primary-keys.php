<?php
/**
 * The class for a primary keys
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

class Primary_Keys {

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

				if ( 'PRI' === $column->Key || 'auto_increment' === $column->Extra ) {
					$primary_keys[ $table ] = $column->Field;
				}
			}
		}

		return $primary_keys;
	}
}
