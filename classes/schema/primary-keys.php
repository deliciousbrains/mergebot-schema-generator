<?php
/**
 * The class for a primary keys
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

use DeliciousBrains\MergebotSchemaGenerator\Mergebot_Schema_Generator;

class Primary_Keys extends Abstract_Element {

	public static function get_pk_elements( $tables ) {
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
			$compound_pks = array();
			foreach ( $columns as $column ) {
				if ( false === stripos( $column->Type, 'int' ) ) {
					continue;
				}

				if ( self::is_pk_column( $column ) ) {
					$primary_keys[ $table ] = $column->Field;

					break;
				}

				if ( self::is_maybe_compound_pk_column( $column ) ) {
					$compound_pks[] = $column->Field;

					continue;
				}
			}

			if ( count( $compound_pks ) > 1 ) {
				$primary_keys[ $table ] = $compound_pks;
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

	/**
	 * Is a MySQL table column a Primary Key?
	 *
	 * @param object $column
	 *
	 * @return bool
	 */
	public static function is_maybe_compound_pk_column( $column ) {
		if ( 'PRI' !== $column->Key ) {
			return false;
		}

		return true;
	}

	/**
	 * @return string
	 */
	protected static function get_custom_prefix_file() {
		return dirname( Mergebot_Schema_Generator()->file_path ) . '/data/table-custom-prefix.json';
	}

	/**
	 * @param string $filename
	 *
	 * @return string|bool
	 */
	public static function get_custom_table_prefix( $filename ) {
		$content = self::read_data_file( self::get_custom_prefix_file() );

		if ( isset( $content[ $filename ] ) ) {
			return $content[ $filename ];
		}

		return false;
	}

	/**
	 * @param string $filename
	 * @param string $prefix
	 *
	 * @return int
	 */
	public static function write_custom_table_prefix( $filename, $prefix ) {
		$file    = self::get_custom_prefix_file();
		$content = self::read_data_file( $file );

		$content[ $filename ] = $prefix;

		return self::write_data_file( $file, $content );
	}
}
