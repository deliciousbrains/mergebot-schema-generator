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
use DeliciousBrains\MergebotSchemaGenerator\Mergebot_Schema_Generator;

class Relationships extends Abstract_Element {

	protected static $element = 'Relationships';
	protected static $colour = 'G';

	protected static function find_elements( Schema $schema ) {
		$meta_tables = self::get_meta_tables();

		$entities       = self::get_meta_data( $meta_tables );
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
						$value = trim( $args[ $value_pos ] );

						if ( isset( $processed_meta[ $entity ][ $key ] ) ) {
							// Already processed this piece of meta
							continue;
						}

						// record the key so we ignore it if used in other places
						$processed_meta[ $entity ][ $key ] = array( 'value' => $value, 'file' => $file->getRealPath() );
					}
				}
			}

		}
		// TODO deal with custom plugin meta tables
		// update_metadata( 'woocommerce_term',

		return $processed_meta;
	}

	protected static function ask_elements( $elements, $progress_bar ) {
		$relationships = array();
		$meta_tables = self::get_meta_tables();

		$entities       = self::get_meta_data( $meta_tables );

		foreach ( $elements as $entity => $data ) {
			foreach ( $data as $key => $relationship ) {
				$value = $relationship['value'];

				$progress_bar->tick();
				// ask if we are interested in the key/value
				Mergebot_Schema_Generator::log( \WP_CLI::colorize(  "\n" . '%G' . 'File' . ':%n' . $relationship['file'] ) );
				$result = Command::meta( $entity, $key, $value );

				if ( 'exit' === $result ) {
					return $relationships;
				}

				if ( ! $result ) {
					continue;
				}

				if ( 'y' !== strtolower( $result ) ) {
					continue;
				}

				// Ask if simple reference to a table
				// eg. add_post_meta( '_image_id', $id );
				// which links to to the posts table
				$target_table = Command::meta_table();
				if ( $target_table ) {
					$relationship_data = array(
						$entities[$entity]['columns']['key']   => $key,
						$entities[$entity]['columns']['value'] => $target_table,
					);

					$relationships[ $entity ][] = $relationship_data;

					continue;
				}

				$target_table = Command::meta_table( false );
				$relationship_data = array(
					$entities[$entity]['columns']['key']   => $key,
					$entities[$entity]['columns']['value'] => $target_table,
				);

				$serialized_data = array(
					'key' => 'ignore',
					'val' => 'ignore',
				);

				$serialized_key = Command::meta_serialized_key();
				if ( $serialized_key ) {
					$serialized_data['key'] = $serialized_key;
				}

				$serialized_value = Command::meta_serialized_value();
				$serialized_parts = explode( '|', $serialized_value );

				$serialized = array(
					'key' => 'ignore',
					'val' => 'ignore',
				);
				if ( 1 === count( $serialized_parts ) ) {
					$serialized['val'] = $serialized_parts[0];
				}

				if ( 2 === count( $serialized_parts ) ) {
					$serialized['key'] = $serialized_parts[0];
					$serialized['val'] = $serialized_parts[1];
				}

				$serialized_data['val'] = $serialized;

				$relationship_data['serialized'] = $serialized_data;

				// If so, record the response in the format for the json
				$relationships[ $entity ][] = $relationship_data;
			}
		}

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

	protected static function get_total_elements( $elements ) {
		$count = 0;
		foreach ( $elements as $entity => $data ) {
			$count += count( $data );
		}

		return $count;
	}
}