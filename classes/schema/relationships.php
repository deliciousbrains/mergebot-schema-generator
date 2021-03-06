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

	public static function find_elements( Schema $schema ) {
		$meta_tables = self::get_meta_tables( $schema );

		$entities       = self::get_meta_data( $meta_tables, $schema );
		$processed_meta = array();

		$ignored_values = self::get_ignored_values();

		foreach ( $schema->files as $file ) {
			$content = strtolower( file_get_contents( $file->getRealPath() ) );

			foreach ( $entities as $entity => $data ) {
				foreach ( $data['functions'] as $function_regex => $function ) {
					if ( false === strpos( $content, $function ) ) {
						continue;
					}

					$pattern = '/(?<!function)\s+' . $function_regex . '(.*)(?=[\n|\r]*\)[\s]*;)/i';
					preg_match_all( $pattern, $content, $matches );

					if ( ! $matches || ! isset( $matches[1] ) || ! is_array( $matches[1] ) || empty( $matches[1][0] ) ) {
						continue;
					}

					$key_pos   = self::get_key_pos( $entity, $function );
					$value_pos = self::get_value_pos( $entity, $function );

					foreach ( $matches[1] as $arguments ) {
						$args = self::get_function_args_from_string( $arguments );

						if ( empty( $args ) ) {
							\WP_CLI::error( 'Could not get args from string ' . $arguments );
						}

						// get meta key and value from code
						$key = trim( $args[ $key_pos ] );
						$key = ltrim( $key, '\'"' );
						$key = rtrim( $key, '\'"' );

						if ( ! isset( $args[ $value_pos ] ) && 'add_option' !== $function ) {
							\WP_CLI::warning( 'Could not get meta value from ' . $function . ' key: ' . $arguments );
							continue;
						}

						$value = '';
						if ( isset( $args[ $value_pos ] ) ) {
							$value = trim( $args[ $value_pos ] );
						}

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

		return $processed_meta;
	}

	protected static function meta_exists_in_schema( $schema, $entity, $key ) {
		if ( ! isset( $schema->relationships[ $entity ] ) ) {
			return false;
		}

		foreach ( $schema->relationships[ $entity ] as $data ) {
			foreach ( $data as $meta_key => $meta_value ) {
				// Translate key from saved data
				$key = self::get_key_translation( $schema, $entity, $key );
				if ( $key === $meta_value ) {
					return $data;
				} else {
					continue;
				}
			}
		}

		return false;
	}

	protected static function get_key_pos( $entity, $function ) {
		if ( 'options' === $entity || 'sitemeta' === $entity ) {
			return 0;
		}

		return 1;
	}

	protected static function get_value_pos( $entity, $function ) {
		if ( 'options' === $entity || 'sitemeta' === $entity ) {
			return 1;
		}

		return 2;
	}

	protected static function ask_elements( Schema $schema, $elements, $progress_bar ) {
		$relationships = array();
		$meta_tables   = self::get_meta_tables( $schema );

		$entities = self::get_meta_data( $meta_tables, $schema );
		$exit     = false;

		ksort( $elements );

		$content = $schema->read_data_file();
		$ignored = isset( $content['relationships']['ignore'] ) ? $content['relationships']['ignore'] : array();

		foreach ( $elements as $entity => $data ) {
			foreach ( $data as $key => $relationship ) {
				$value = $relationship['value'];

				if ( isset( $ignored[ $entity ] ) && in_array( $key, $ignored[ $entity ] ) ) {
					// We have already ignore this relationship before, ignore it again.
					continue;
				}

				$progress_bar->tick();
				// ask if we are interested in the key/value
				Mergebot_Schema_Generator::log( "\n" . $relationship['file'] );

				$_entity = \WP_CLI::colorize( '%G' . $entity . '%n' );
				$_key    = \WP_CLI::colorize( '%B' . $key . '%n' );
				$_value  = \WP_CLI::colorize( '%R' . $value . '%n' );

				fwrite( STDOUT, $_entity . ' with key: ' . $_key . ' and value: ' . $_value . "\n" );

				if ( false === $schema->from_scratch && ( bool) ( $existing_data = self::meta_exists_in_schema( $schema, $entity, $key ) ) ) {
					$key = self::get_key_translation( $schema, $entity, $key );
					$result = true;
					if ( ! $exit ) {
						// Relationship already defined in schema, ask to overwrite
						$result = Command::overwrite_property();
					}

					if ( $exit || false === $result || 'exit' === $result ) {
						$relationships = self::save_relationship( $relationships, $entities, $entity, $key, $existing_data );

						if ( ! $exit && 'exit' === $result ) {
							$exit = true;
						}

						continue;
					}
				}

				if ( $exit && ! Command::$headless ) {
					$ignored[ $entity ][] = $key;
					continue;
				}

				$result = Command::meta();

				if ( 'exit' === $result ) {
					$exit = true;
					$ignored[ $entity ][] = $key;
					continue;
				}

				if ( ! $result ) {
					$ignored[ $entity ][] = $key;
					continue;
				}

				if ( '?' === strtolower( $result ) ) {
					continue;
				}

				if ( 'y' !== strtolower( $result ) ) {
					$ignored[ $entity ][] = $key;
					continue;
				}

				if ( false !== strpos( $key, '$' ) ) {
					// Meta key contains a variable, need to ask for the key
					$new_key = Command::meta_key( $key );
					if ( $new_key ) {
						// Store original key and new key
						$content = self::write_key_translation( $content, $entity, $entities[ $entity ]['columns']['key'] , $key, $new_key );

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

					$relationships = self::save_relationship( $relationships, $entities, $entity, $key, $relationship_data );

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

				if ( 1 === count( $serialized_parts ) ) {
					// Single dimensional array
					$serialized_data['val'] = $serialized_parts[0];
				} else if ( 2 === count( $serialized_parts ) ) {
					// Multi dimensional array
					$serialized = array(
						'key' => 'ignore',
						'val' => 'ignore',
					);

					$serialized['key'] = $serialized_parts[0];
					$serialized['val'] = $serialized_parts[1];

					$serialized_data['val'] = $serialized;
				}

				$relationship_data['serialized'] = $serialized_data;

				// If so, record the response in the format for the json
				$relationships = self::save_relationship( $relationships, $entities, $entity, $key, $relationship_data );
			}
		}

		if ( ! empty( $ignored ) ) {
			$content['relationships']['ignore'] = $ignored;
		}

		$schema->write_data_file( $content );

		return $relationships;
	}

	public static function merge_relationships( $schema, $existing, $new, $entity ) {
		$merged      = array();
		$meta_tables = self::get_meta_tables( $schema );
		$entities    = self::get_meta_data( $meta_tables, $schema );

		foreach( $new as $key => $data ) {
			if ( ! isset( $existing[ $key ] ) ) {
				$merged[ $key ] = $data;

				continue;
			}

			$existing_data = $existing[ $key ];
			// Check if relationship is serialized
			if ( ! isset( $data['serialized'] ) && ! isset( $existing_data['serialized'] ) ) {
				$merged[ $key ] = $data;

				continue;
			}

			if ( ! isset( $data['serialized'] ) && isset( $existing_data['serialized'] ) ) {
				\WP_CLI::info( 'Existing data is serialized, new data for ' . $key . ' is not' );
				$merged[ $key ] = $data;

				continue;
			}

			if ( isset( $data['serialized'] ) && ! isset( $existing_data['serialized'] ) ) {
				\WP_CLI::info( 'New data is serialized, existing data for ' . $key . ' is not' );
				$merged[ $key ] = $data;

				continue;
			}

			if ( serialize( $data['serialized']) === serialize( $existing_data['serialized'] ) ) {
				$merged[ $key ] = $data;

				continue;
			}

			$merged[ $key ] = $data;
		}

		foreach ( $existing as $key => $value ) {
			if ( isset( $new[ $key ] ) ) {
				continue;
			}
			$merged[ $key ] = $value;
		}

		return $merged;
	}

	/**
	 * Add new serialized item to array or convert single serialized relationship to an array.
	 *
	 * @param string $value_name_key
	 * @param array $existing_item
	 * @param array $new_item
	 *
	 * @return array
	 */
	protected static function merge_relationship_serialized( $value_name_key, $existing_item, $new_item ) {
		$new_item_serialized = $new_item['serialized'];
		$new_item_serialized = array( $value_name_key => $new_item[ $value_name_key ] ) + $new_item_serialized;

		if ( Schema::is_assoc_array( $existing_item['serialized'] ) ) {
			$existing_item_serialized    = $existing_item['serialized'];
			$existing_item_serialized    = array( $value_name_key => $existing_item[ $value_name_key ] ) + $existing_item_serialized;
			$existing_item['serialized'] = array();
			if ( serialize( $new_item_serialized ) != serialize( $existing_item_serialized ) ) {
				unset( $existing_item_serialized[ $value_name_key ] );
				$existing_item['serialized'][] = $existing_item_serialized;
			}
		}

		unset( $new_item_serialized[ $value_name_key ] );
		if ( empty( $existing_item['serialized'] ) ) {
			$existing_item['serialized'] = $new_item_serialized;
		} else {
			$existing_item['serialized'][] = $new_item_serialized;
		}

		return $existing_item;
	}

	/**
	 * Add the relationship to the main array.
	 *
	 * @param array  $relationships
	 * @param string $entity Table name
	 * @param string $key Key name
	 * @param array  $data
	 *
	 * @return array
	 */
	protected static function save_relationship( $relationships, $entities, $entity, $key, $data ) {
		if ( ! isset( $relationships[ $entity ] ) ) {
			$relationships[ $entity ][$key] = $data;

			return $relationships;
		}

		if ( ! isset( $relationships[ $entity ][ $key ] ) ) {
			$relationships[ $entity ][ $key ] = $data;

			return $relationships;
		}

		$existing = $relationships[ $entity ][ $key ];
		// Check if existing relationship is serialized
		if ( ! isset( $existing['serialized'] ) ) {
			\WP_CLI::warning( "Relationship for $key already exists" );

			return $relationships;
		}

		if ( ! isset( $data['serialized'] ) ) {
			\WP_CLI::warning( "No serialized relationship for $key" );

			return $relationships;
		}

		$value_name = $entities[ $entity ]['columns']['value'];
		$existing   = self::merge_relationship_serialized( $value_name, $existing, $data );

		$relationships[ $entity ][ $key ] = $existing;

		return $relationships;
	}

	/**
	 * Get all meta tables
	 *
	 * @return array
	 */
	protected static function get_meta_tables( Schema $schema ) {
		$meta_tables = array();
		$tables      = array_merge( array_keys( Mergebot_Schema_Generator()->wp_tables ), array_keys( $schema->table_columns ) );

		foreach ( $tables as $table ) {
			if ( 'options' !== $table && 'meta' !== substr( $table, strlen( $table ) - 4, 4 ) ) {
				continue;
			}

			$meta_table = str_replace( $schema->custom_prefix, '', $table );

			if ( in_array( $meta_table, $meta_tables ) ) {
				continue;
			}

			$meta_tables[] = $meta_table;
		}

		return $meta_tables;
	}

	/**
	 * Get associated data about the meta table
	 *
	 * @param array  $tables
	 * @param Schema $schema
	 *
	 * @return array
	 */
	protected static function get_meta_data( $tables, Schema $schema ) {
		$entities = array();
		foreach ( $tables as $table ) {
			$entity = str_replace( 'meta', '', $table );

			$functions = self::get_meta_functions( $entity );

			$entities[ $table ]['functions'] = $functions;

			$meta_columns = self::get_meta_columns( $table, $schema->table_columns, $schema->custom_prefix );
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

		if ( 'site' === $entity ) {
			$functions['add_site_option\s*\(']    = 'add_site_option';
			$functions['update_site_option\s*\('] = 'update_site_option';

			return $functions;
		}

		$suffix = $entity . '_meta';

		if ( function_exists( 'add_' . $suffix ) ) {
			$functions[ 'add_' . $suffix . '\(' ] = 'add_' . $suffix;
		}

		if ( function_exists( 'update_' . $suffix ) ) {
			$functions[ 'update_' . $suffix . '\(' ] = 'update_' . $suffix;
		}

		$functions[ 'update_metadata\s*\(\s*(?:\'|")' . $entity . '(?:\'|")\s*,\s+' ] = 'update_metadata';

		return $functions;
	}

	protected static function find_table( $table, $tables, $prefix ) {
		if ( isset( $tables[ $table ] ) ) {
			return $tables[ $table ];
		}

		if ( ! is_array( $prefix ) ) {
			$prefix = array( $prefix );
		}

		foreach ( $prefix as $single_prefix ) {
			$table = $single_prefix . $table;
			if ( isset( $tables[ $table ] ) ) {
				return $tables[ $table ];
			}
		}

		return false;
	}

	/**
	 * Get the key/value columns of the meta table
	 *
	 * @param string $table
	 * @param array  $tables
	 * @param string $prefix
	 *
	 * @return array|bool
	 */
	protected static function get_meta_columns( $table, $tables = array(), $prefix = '' ) {
		$all_tables = array_merge( Mergebot_Schema_Generator()->wp_tables, $tables );

		$columns = self::find_table( $table, $all_tables, $prefix );
		if ( empty( $columns ) ) {
			return false;
		}

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
		if ( is_string( $value ) && false === strpos( $value, '$' ) ) {
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

	/**
	 * Is the meta key been translated?
	 *
	 * @param Schema $schema
	 * @param string $entity
	 * @param string $key
	 *
	 * @return bool
	 */
	public static function is_key_translated( Schema $schema, $entity, $key ) {
		$content = $schema->read_data_file();

		$columns = self::get_meta_columns( $entity, $schema->tables, $schema->custom_prefix );

		if ( ! isset( $content['relationships']['key_translation'][ $entity ][ $columns['key'] ] ) ) {
			return false;
		}

		foreach ( $content['relationships']['key_translation'][ $entity ][ $columns['key'] ] as $translated_key ) {
			if ( $translated_key == $key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $schema
	 * @param $entity
	 * @param $old_key
	 *
	 * @return mixed
	 */
	protected static function get_key_translation( Schema $schema, $entity, $old_key ) {
		$content = $schema->read_data_file();

		$columns = self::get_meta_columns( $entity, $schema->tables, $schema->custom_prefix );

		if ( isset( $content['relationships']['key_translation'][ $entity ][ $columns['key'] ][ $old_key ] ) ) {
			return $content['relationships']['key_translation'][ $entity ][ $columns['key'] ][ $old_key ];
		}

		return $old_key;
	}

	/**
	 * @param           $content
	 * @param           $entity
	 * @param           $key_name
	 * @param           $old_key
	 * @param           $new_key
	 *
	 * @return int
	 */
	protected static function write_key_translation( $content, $entity, $key_name, $old_key, $new_key ) {
		$content['relationships']['key_translation'][ $entity ][ $key_name ][ $old_key ] = $new_key;

		return $content;
	}
}