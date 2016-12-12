<?php
/**
 * The class for relationships defined in meta key/value tables
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

use DeliciousBrains\MergebotSchemaGenerator\Command;

class Relationships {

	public static function get_relationships( Schema $schema ) {
		$meta_tables = self::get_meta_tables();

		$entities = self::get_meta_data( $meta_tables );

		$relationships  = array();
		$processed_meta = array();

		foreach ( $schema->files as $file ) {
			$content = strtolower( file_get_contents( $file->getRealPath() ) );

			foreach ( $entities as $entity => $data ) {
				foreach ( $data['functions'] as $function ) {
					if ( false === strpos( $content, $function ) ) {
						continue;
					}

					$pattern = '/' . $function . '\((.*)(?=[\n|\r]*\)[\s]*;)/i';
					preg_match_all( $pattern, $content, $matches );

					if ( ! $matches || ! isset( $matches[1] ) || ! is_array( $matches[1] ) || empty( $matches[1][0] ) ) {
						continue;
					}

					foreach ( $matches[1] as $arguments ) {
						$args = explode( ',', $arguments );

						$key_pos   = 'options' === $entity ? 0 : 1;
						$value_pos = 'options' === $entity ? 1 : 2;
						// get meta key and value from code
						$key = str_replace( array( '"', "'" ), '', trim( $args[ $key_pos ] ) );
						if ( ! isset( $args[ $value_pos ] ) ) {
							error_log( 'Could not get arguments for ' . $args );
						}
						$value = trim( $args[$value_pos] );

						if ( isset( $processed_meta[ $entity ][ $key ] ) ) {
							// Already processed this piece of meta
							continue;
						}

						// record the key so we ignore it if used in other places
						$processed_meta[ $entity ][ $key ] = $value;

						// ask if we are interested in the key/value
						$result = Command::meta( $entity, $key, $value );

						if ( ! $result ) {
							continue;
						}

						$result_parts = explode( ',', $result );
						$target_table = $result_parts[0];

						$relationship_data = array(
							$data['columns']['key']   => $key,
							$data['columns']['value'] => $target_table,
						);

						if ( isset( $result_parts[1]) ) {
							$serialized_data = array(
								'key' => 'ignore',
								'val' => 'ignore',
							);

							// Used for serialized data
							$serialized_parts = explode( '|', $result_parts[1] );
							foreach ( $serialized_parts as $serialized_part ) {
								$row = explode( ':', $serialized_part );
								if ( isset( $row[0] ) && isset( $row[1] ) ) {
									$serialized_data[ $row[0] ] = $row[1];
								}
							}

							$relationship_data['serialized'] = $serialized_data;
						}

						// If so, record the response in the format for the json
						$relationships[ $entity ][] = $relationship_data;
					}
				}
			}

		}
		
		// TODO deal with custom plugin meta tables
		// update_metadata( 'woocommerce_term',

		return $relationships;
	}

	/**
	 * Get all meta tables
	 *
	 * @return array
	 */
	protected static function get_meta_tables() {
		$meta_tables = array();
		foreach ( Mergebot_Schema_Generator()->wp_tables as $table => $columns ) {
			if ( 'options' === $table || 'meta' === substr( $table, strlen( $table ) - 4, 4 ) ) {
				$meta_tables[] = $table;
			}
		}

		return $meta_tables;
	}

	/**
	 * Get associated data about the meta table
	 *
	 * @param array $tables
	 *
	 * @return array
	 */
	protected static function get_meta_data( $tables ) {
		$entities = array();
		foreach ( $tables as $table ) {
			$entity = str_replace( 'meta', '', $table );

			$functions = self::get_meta_functions( $entity );

			$entities[ $table ]['functions'] = $functions;

			$meta_columns = self::get_meta_columns( $table );
			if ( ! $meta_columns ) {
				continue;
			}

			$entities[ $table ]['columns'] = $meta_columns;
		}

		return $entities;
	}

	/**
	 * Get the methods used for writing data for a piece of meta
	 *
	 * @param string $entity
	 *
	 * @return array
	 */
	protected static function get_meta_functions( $entity ) {
		$suffix    = ( 'options' === $entity ) ? 'option' : $entity . '_meta';
		$functions = array();

		$function = 'add_' . $suffix;
		if ( ! function_exists( $function ) ) {
			$function = 'add_metadata(' . $entity;
		}
		$functions[] = $function;

		$function = 'update_' . $suffix;
		if ( ! function_exists( $function ) ) {
			$function = 'update_metadata(' . $entity;
		}
		$functions[] = $function;

		return $functions;
	}

	/**
	 * Get the key/value columns of the meta table
	 *
	 * @param string $table
	 *
	 * @return array|bool
	 */
	protected static function get_meta_columns( $table ) {
		$all_tables = Mergebot_Schema_Generator()->wp_tables;
		$columns    = $all_tables[ $table ];

		$key   = null;
		$value = null;
		foreach ( $columns as $column ) {
			if ( false !== stripos( $column->Type, 'int' ) ) {
				continue;
			}

			if ( empty( $key ) ) {
				$key = $column->Field;
			} else {
				$value = $column->Field;

				break;
			}
		}

		if ( ! $key || ! $value ) {
			return false;
		}

		return array( 'key' => $key, 'value' => $value );
	}
}