<?php
/**
 * The factory class for a schema
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes
 */

namespace DeliciousBrains\MergebotSchemaGenerator;

use DeliciousBrains\MergebotSchemaGenerator\Schema\Schema;

class Generator {

	protected $schema;

	/**
	 * Generator constructor.
	 *
	 * @param string $slug
	 * @param string $version
	 * @param string $type
	 */
	public function __construct( $slug, $version, $type ) {
		$this->schema = new Schema( $slug, $version, $type );
	}

	/**
	 * Generate a schema
	 * 
	 * @param bool $create_from_scratch
	 */
	public function generate( $create_from_scratch = false ) {
		if ( ! $this->schema->exists() || $create_from_scratch ) {
			$this->schema->create();
		} else {
			$this->schema->update();
		}
	}

	public static function get_generated_plugins() {
		$schema_dir = apply_filters( 'mergebot_schema_generator_plugins_path', Mergebot_Schema_Generator()->schema_path . '/plugins' );

		$plugins = array_diff( scandir( $schema_dir), array( '..', '.' ) );

		return $plugins;
	}

	public static function get_version_from_filename( $filename ) {
		$parts = explode( '-', $filename );
		// Remove Version
		$version = array_pop( $parts );

		return rtrim( $version, '.json' );
	}

	/**
	 * @param $filename
	 *
	 * @return bool|string
	 */
	public static function get_slug_from_filename( $filename ) {
		$parts = explode( '-', $filename );
		// Remove Version
		array_pop( $parts );
		$slug       = implode( '-', $parts );

		$found = Schema::get_slug_from_schema( $slug );
		if ( $found ) {
			return $found;
		}

		$rev_parts = array_reverse( $parts );

 		foreach ( $rev_parts as $part ) {
		    if ( false !== strpos( $part, '_' ) ) {
			    array_pop( $parts );

			    return implode( '-', $parts );
		    }

		    $length = strlen( '-' . $part );
		    $slug   = substr( $slug, 0, strlen( $slug ) - $length );
		    if ( empty( $slug ) ) {
			    return false;
		    }

			if ( file_exists( WP_PLUGIN_DIR . '/' . $slug ) ) {
				return $slug;
			}

			$data = Installer::get_latest_plugin_data( $slug );
			if ( false !== $data ) {
				return $slug;
			}
		}

		return false;
	}
	
}