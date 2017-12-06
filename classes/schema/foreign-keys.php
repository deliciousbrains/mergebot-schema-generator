<?php
/**
 * The class for a foreign keys
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

class Foreign_Keys {

	public static function get_foreign_keys( Schema $schema ) {
		$foreign_keys    = array();
		$wp_tables       = Mergebot_Schema_Generator()->wp_tables;
		$wp_primary_keys = Mergebot_Schema_Generator()->wp_primary_keys;
		$post_types      = get_post_types();

		$content = $schema->read_data_file();
		$persist = isset( $content['foreignKeys']['persist'] ) ? $content['foreignKeys']['persist'] : array();
		$entity_translation = isset( $content['foreignKeys']['entityTranslation'] ) ? $content['foreignKeys']['entityTranslation'] : array();

		foreach ( $schema->table_columns as $table => $columns ) {
			foreach ( $columns as $column ) {
				if ( false === stripos( $column->Type, 'int' ) ) {
					continue;
				}

				if ( Primary_Keys::is_pk_column( $schema, $table, $column->Field ) ) {
					// Column is the PK so can't be a Fk.
					continue;
				}

				if ( false === stripos( $column->Field, 'id' ) ) {
					continue;
				}

				$entity = false;
				if ( isset( $entity_translation[$column->Field] ) ) {
					$entity = $entity_translation[$column->Field];
				}

				if ( empty( $entity ) ) {
					$entity = str_replace( array( '_ID', '_Id', '_id' ), '', $column->Field );
					$entity = rtrim( $entity, '_' );
				}
				
				$foreign_key = $table . ':' . $column->Field;

				if ( isset( $persist[ $foreign_key ] ) ) {
					// Persist FKs stored in schema data for FKs that can't be detected
					$foreign_keys[ $foreign_key ] = $persist[ $foreign_key ];

					continue;
				}

				if ( false !== ( $match = self::handle_parent_id( $schema, $entity, $table ) ) ) {
					$foreign_keys[ $foreign_key ] = $match;

					continue;
				}

				// Single entity a WordPress one
				if ( isset( $wp_tables[ $entity ] ) ) {
					$pk = $wp_primary_keys[ $entity ]['key'][0];

					$foreign_keys[ $foreign_key ] = $entity . ':' . $pk;

					continue;
				}

				// Plural entity a WordPress one
				$plural_entity = $entity . 's';
				if ( isset( $wp_tables[ $plural_entity ] ) ) {
					$pk = $wp_primary_keys[ $plural_entity ]['key'][0];

					$foreign_keys[ $foreign_key ] = $plural_entity . ':' . $pk;

					continue;
				}

				// Single entity a Post Type
				if ( isset( $post_types[ $entity ] ) ) {
					$pk = $wp_primary_keys['posts']['key'][0];

					$foreign_keys[ $foreign_key ] = 'posts:' . $pk;

					continue;
				}

				// Single entity a plugin one
				if ( false !== ( $match = self::is_plugin_table_fk( $schema, $entity, $table ) ) ) {
					$foreign_keys[ $foreign_key ] = $match;

					continue;
				}

				// Plural entity a plugin one
				if ( false !== ( $match = self::is_plugin_table_fk( $schema, $plural_entity, $table ) ) ) {
					$foreign_keys[ $foreign_key ] = $match;

					continue;
				}

				// Single entity a Post Type match = eg, order = shop_order
				$match = self::post_type_match( $post_types, $entity );
				if ( $match ) {
					$pk = $wp_primary_keys['posts']['key'][0];

					$foreign_keys[ $foreign_key ] = 'posts:' . $pk;

					continue;
				}

				// Remove any type of prefix from the entity
				// TODO make this more intelligent
				$entity_parts = explode( '_', $entity );
				$entity       = array_pop( $entity_parts );
				// Single entity a WordPress one
				if ( isset( $wp_tables[ $entity ] ) ) {
					$pk = $wp_primary_keys[ $entity ]['key'][0];

					$foreign_keys[ $foreign_key ] = $entity . ':' . $pk;

					continue;
				}

				// Plural entity a WordPress one
				$plural_entity = $entity . 's';
				if ( isset( $wp_tables[ $plural_entity ] ) ) {
					$pk = $wp_primary_keys[ $plural_entity ]['key'][0];

					$foreign_keys[ $foreign_key ] = $plural_entity . ':' . $pk;

					continue;
				}

				// TODO ask user

			}
		}

		return $foreign_keys;
	}

	protected static function post_type_match( $post_types, $partial ) {
		foreach ( $post_types as $type ) {
			if ( false !== stripos( $type, $partial ) ) {
				return $type;
			}
		}

		return false;
	}

	protected static function table_match( $tables, $partial, $current_table ) {
		$length = strlen( $partial );
		foreach ( $tables as $table ) {
			if ( $table === $current_table ) {
				continue;
			}

			if ( $partial === substr( $table, strlen( $table ) - $length ) ) {
				return $table;
			}
		}

		return false;
	}

	protected static function is_plugin_table_fk( $schema, $entity, $table ) {
		$plugin_tables = array_keys( $schema->table_columns );
		$match         = self::table_match( $plugin_tables, $entity, $table );

		if ( $match && isset( $schema->primary_keys[ $match ] ) ) {
			$pk = $schema->primary_keys[ $match ]['key'][0];

			return $match . ':' . $pk;
		}

		return false;
	}

	protected static function handle_parent_id( $schema, $entity, $table ) {
		if ( 'parent' !== $entity ) {
			return false;
		}

		$search_table = str_replace( '_meta', '', $table );
		$search_table = rtrim( $search_table, 's' );

		if ( false !== ( $match = self::is_plugin_table_fk( $schema, $search_table, $table ) ) ) {
			return $match;
		}

		$search_table .= 's';

		if ( false !== ( $match = self::is_plugin_table_fk( $schema, $search_table, $table ) ) ) {
			return $match;
		}

		return false;
	}
}
