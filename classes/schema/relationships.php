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

		$ignored_values = self::get_ignored_values();

		foreach ( $schema->files as $file ) {
			$content = strtolower( file_get_contents( $file->getRealPath() ) );

			foreach ( $entities as $entity => $data ) {
				foreach ( $data['functions'] as $function_regex => $function ) {
					if ( false === strpos( $content, $function ) ) {
						continue;
					}

					$pattern = '/' . $function_regex . '(.*)(?=[\n|\r]*\)[\s]*;)/i';
					preg_match_all( $pattern, $content , $matches );

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

						if ( self::is_ignored_value( $value, $ignored_values ) ) {
							// Not a value that can contain ID data.
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

	protected static function meta_exists_in_schema( $schema, $entity, $key ) {
		if ( ! isset( $schema->relationships[ $entity ] ) ) {
			return false;
		}

		foreach ( $schema->relationships[ $entity ] as $data ) {
			foreach ( $data as $meta_key => $meta_value ) {
				if ( $key === $meta_value ) {
					return $data;
				} else {
					continue;
				}
			}
		}

		return false;
	}

	protected static function ask_elements( Schema $schema, $elements, $progress_bar ) {
		$relationships = array();
		$meta_tables   = self::get_meta_tables();

		$entities = self::get_meta_data( $meta_tables );
		$exit     = false;

		foreach ( $elements as $entity => $data ) {
			foreach ( $data as $key => $relationship ) {
				$value = $relationship['value'];

				$progress_bar->tick();
				// ask if we are interested in the key/value
				Mergebot_Schema_Generator::log( \WP_CLI::colorize( "\n" . '%G' . 'File' . ':%n' . $relationship['file'] ) );

				$_entity = \WP_CLI::colorize( '%G' . $entity . '%n' );
				$_key    = \WP_CLI::colorize( '%B' . $key . '%n' );
				$_value  = \WP_CLI::colorize( '%R' . $value . '%n' );

				fwrite( STDOUT, "\n" . $_entity . ' with key: ' . $_key . ' and value: ' . $_value . "\n" );

				if ( false === $schema->from_scratch && ( bool) ( $existing_data = self::meta_exists_in_schema( $schema, $entity, $key ) ) ) {
					$result = true;
					if ( ! $exit ) {
						// Relationship already defined in schema, ask to overwrite
						$result = Command::overwrite_property();
					}

					if ( $exit || false === $result || 'exit' === $result ) {
						$relationships[ $entity ][] = $existing_data;

						if ( ! $exit && 'exit' === $result ) {
							$exit = true;
						}

						continue;
					}
				}

				if ( $exit ) {
					continue;
				}

				$result = Command::meta();

				if ( 'exit' === $result ) {
					return $relationships;
				}

				if ( ! $result ) {
					continue;
				}

				if ( 'y' !== strtolower( $result ) ) {
					continue;
				}

				if ( false !== strpos( $key, '$' ) ) {
					// Meta key contains a variable, need to ask for the key
					$new_key = Command::meta_key( $key );
					if ( $new_key ) {
						$key = $new_key;
					}
				}

				// Ask if simple reference to a table
				// eg. add_post_meta( '_image_id', $id );
				// which links to to the posts table
				$target_table = Command::meta_table();
				if ( $target_table ) {
					$relationship_data = array(
						$entities[ $entity ]['columns']['key']   => $key,
						$entities[ $entity ]['columns']['value'] => $target_table,
					);

					$relationships[ $entity ][] = $relationship_data;

					continue;
				}

				$target_table      = Command::meta_table( false );
				$relationship_data = array(
					$entities[ $entity ]['columns']['key']   => $key,
					$entities[ $entity ]['columns']['value'] => $target_table,
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
		$functions = array();
		if ( 'options' === $entity ) {
			$functions['add_option\s*\(']    = 'add_option';
			$functions['update_option\s*\('] = 'update_option';

			return $functions;
		}

		$suffix = $entity . '_meta';

		$functions[ 'add_' . $suffix . '\(' ]                                         = 'add_' . $suffix;
		$functions[ 'update_' . $suffix . '\(' ]                                      = 'update_' . $suffix;
		$functions[ 'update_metadata\s*\(\s*(?:\'|")' . $entity . '(?:\'|")\s*,\s+' ] = 'update_metadata';

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

	/**
	 * @param $value
	 * @param $ignore_values
	 *
	 * @return bool
	 */
	protected static function is_ignored_value( $value, $ignore_values ) {
		if ( is_string( $value ) && '$' !== substr( $value, 0, 1 ) ) {
			return true;
		}

		if ( is_numeric( $value ) ) {
			return true;
		}

		foreach ( $ignore_values as $ignore_value ) {
			if ( $value === $ignore_value ) {
				return true;
			}
		}


		return false;
	}

	/**
	 * Get the values to ignore, ones that can't contain ID data.
	 *
	 * @return array
	 */
	protected static function get_ignored_values() {
		return array(
			'0',
			'1',
			true,
			false,
			'$counts',
			'$count',
		);
	}
}