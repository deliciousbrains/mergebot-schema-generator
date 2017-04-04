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
		$data = array();
		foreach ( $tables as $table => $columns ) {
			$auto_increment = false;
			$compound_pks = array();
			$primary_keys = false;
			foreach ( $columns as $column ) {
				if ( self::is_auto_increment_column( $column ) ) {
					$auto_increment         = true;
					$primary_keys = $column->Field;

					break;
				}

				if ( self::is_maybe_compound_pk_column( $column ) ) {
					$compound_pks[] = $column->Field;

					continue;
				}
			}

			if ( count( $compound_pks ) > 1 ) {
				$primary_keys = $compound_pks;
			}

			if ( false === $primary_keys ) {
				continue;
			}

			if ( ! is_array( $primary_keys ) ) {
				$primary_keys = array( $primary_keys );
			}

			$data[ $table ] = array( 'key' => $primary_keys );
			if ( false === $auto_increment ) {
				$data[ $table ] ['auto_increment'] = false;
			}
		}

		return $data;
	}

	/**
	 * Is the column already defined as a Primary Key in the schema
	 *
	 * @param object $column
	 *
	 * @return bool
	 */
	public static function is_pk_column( $schema, $table, $column ) {
		$pk_data = $schema->primary_keys;

		if ( ! isset( $pk_data[ $table ] ) || ! isset( $pk_data[ $table ]['key'] ) ) {
			return false;
		}

		$key = $pk_data[ $table ]['key'];

		if ( ! is_array( $key ) ) {
			$key = array( $key );
		}

		return in_array( $column, $key );
	}

	/**
	 * Is a MySQL table column an auto increment column?
	 *
	 * @param object $column
	 *
	 * @return bool
	 */
	public static function is_auto_increment_column( $column ) {
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
