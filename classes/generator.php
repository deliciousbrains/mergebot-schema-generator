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
	 * @var Schema
	 */
	protected $latest_schema;

	/**
	 * Generator constructor.
	 *
	 * @param string $slug
	 * @param string $version
	 * @param string $type
	 */
	public function __construct( $slug, $version, $type ) {
		$this->schema = new Schema( $slug, $version, $type );
		$this->schema->init();
	}

	/**
	 * Generate a schema
	 * 
	 * @param bool $create_from_scratch
	 */
	public function generate( $create_from_scratch = false ) {
		if ( $create_from_scratch  ) {
			$this->schema->create();

			return;
		}

		if ( $this->schema->exists() ) {
			$this->schema->update();

			return;
		}

		// Look for the last version that exists
		$this->latest_schema = $this->schema->get_latest_schema( $this->schema->version );
		if ( false === $this->latest_schema ) {
			$this->schema->create();

			return;
		}

		$this->schema->duplicate( $this->latest_schema->version );
		$this->schema->update();
	}

	public function maybe_update_latest_schema() {
		if ( Command::$headless ) {
			return;
		}

		if ( false === $this->latest_schema ) {
			return;
		}

		if ( 'wordpress' === $this->schema->type ) {
			return;
		}

		// Get contents of schemas
		$schema_contents = $this->schema->json();
		$latest_contents = $this->latest_schema->json();

		if ( ! self::schemas_identical( $schema_contents, $latest_contents ) ) {
			$this->schema->update_tested_up_to( $schema_contents, $this->schema->version );

			return;
		}

		$this->latest_schema->update_tested_up_to( $latest_contents, $this->schema->version );
		unlink( $this->schema->file_path() );
	}

	/**
	 * Compare two schema file contents and check if the same, ignoring version differences.
	 *
	 * @param array $schema_a_contents
	 * @param array $schema_b_contents
	 *
	 * @return bool
	 */
	public static function schemas_identical( $schema_a_contents, $schema_b_contents ) {
		unset( $schema_a_contents['version'] );
		unset( $schema_a_contents['testedUpTo'] );
		unset( $schema_b_contents['version'] );
		unset( $schema_b_contents['testedUpTo'] );

		ksort( $schema_a_contents );
		ksort( $schema_b_contents );

		return json_encode( $schema_a_contents ) === json_encode( $schema_b_contents );
	}

	/**
	 * Get the JSON contents of a schema file.
	 *
	 * @param string $filename
	 *
	 * @return array|mixed|object
	 */
	protected function get_schema_json( $filename ) {
		$path = $this->getSchemaPath( $filename );

		$contents = file_get_contents( $path );

		return json_decode( $contents, true );
	}

	public static function get_generated_core() {
		$schema_dir = apply_filters( 'mergebot_schema_generator_core_path', Mergebot_Schema_Generator()->schema_path . '/core' );

		$plugins = array_diff( scandir( $schema_dir), array( '..', '.' ) );

		return self::filter_json_files( $plugins );
	}

	public static function get_generated_plugins() {
		$schema_dir = apply_filters( 'mergebot_schema_generator_plugins_path', Mergebot_Schema_Generator()->schema_path . '/plugins' );

		$plugins = array_diff( scandir( $schema_dir), array( '..', '.' ) );

		return self::filter_json_files( $plugins );
	}

	/**
	 * Ensure all files are JSON files.
	 *
	 * @param array $files
	 *
	 * @return array
	 */
	protected static function filter_json_files( $files ) {
		$all_files = array();
		foreach( $files as $file ) {
			$parts = explode('.', $file );
			if ( 'json' !== array_pop( $parts ) ) {
				continue;
			}

			$all_files[] = $file;
		}

		return $all_files;
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

		if ( 1 === count( $parts ) && 'wordpress' === $parts[0] ) {
			return $parts[0];
		}

		$schema = new Schema( '', '', '', $slug );
		$found  = Schema::get_slug_from_schema( $schema );
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