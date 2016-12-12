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

		foreach ( $schema->table_columns as $table => $columns ) {
			foreach ( $columns as $column ) {
				if ( false === stripos( $column->Type, 'int' ) ) {
					continue;
				}

				if ( 'PRI' === $column->Key || 'auto_increment' === $column->Extra ) {
					continue;
				}

				if ( false === stripos( $column->Field, 'id' ) ) {
					continue;
				}

				$entity = str_replace( 'id', '', $column->Field );
				$entity = rtrim( $entity, '_' );

				// Single entity a WordPress one
				if ( isset( $wp_tables[ $entity ] ) ) {
					$pk = $wp_primary_keys[ $entity ];

					$foreign_keys[ $table . ':' . $column->Field ] = $entity . ':' . $pk;

					continue;
				}

				// Plural entity a WordPress one
				$plural_entity = $entity . 's';
				if ( isset( $wp_tables[ $plural_entity ] ) ) {
					$pk = $wp_primary_keys[ $plural_entity ];

					$foreign_keys[ $table . ':' . $column->Field ] = $plural_entity . ':' . $pk;

					continue;
				}

				// Single entity a Post Type
				if ( isset( $post_types[ $entity ] ) ) {
					$pk = $wp_primary_keys['posts'];

					$foreign_keys[ $table . ':' . $column->Field ] = 'posts:' . $pk;

					continue;
				}

				// Single entity a Post Type match = eg, order = shop_order
				$match = self::post_type_match( $post_types, $entity );
				if ( $match ) {
					$pk = $wp_primary_keys['posts'];

					$foreign_keys[ $table . ':' . $column->Field ] = 'posts:' . $pk;

					continue;
				}

				// Single entity a plugin one
				$plugin_tables = array_keys( $schema->table_columns );
				$match         = self::table_match( $plugin_tables, $entity, $table );
				if ( $match ) {
					$pk = $schema->primary_keys[ $match ];

					$foreign_keys[ $table . ':' . $column->Field ] = $match . ':' . $pk;

					continue;
				}

				// Plural entity a plugin one
				$match = self::table_match( $plugin_tables, $plural_entity, $table );
				if ( $match ) {
					$pk = $schema->primary_keys[ $match ];

					$foreign_keys[ $table . ':' . $column->Field ] = $match . ':' . $pk;

					continue;
				}

				// Remove any type of prefix from the entity
				// TODO make this more intelligent
				$entity_parts = explode( '_', $entity );
				$entity       = array_pop( $entity_parts );
				// Single entity a WordPress one
				if ( isset( $wp_tables[ $entity ] ) ) {
					$pk = $wp_primary_keys[ $entity ];

					$foreign_keys[ $table . ':' . $column->Field ] = $entity . ':' . $pk;

					continue;
				}

				// Plural entity a WordPress one
				$plural_entity = $entity . 's';
				if ( isset( $wp_tables[ $plural_entity ] ) ) {
					$pk = $wp_primary_keys[ $plural_entity ];

					$foreign_keys[ $table . ':' . $column->Field ] = $plural_entity . ':' . $pk;

					continue;
				}

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
		foreach ( $tables as $table ) {
			if ( $table === $current_table ) {
				continue;
			}

			if ( false !== stripos( $table, $partial ) ) {
				return $table;
			}
		}

		return false;
	}
}
