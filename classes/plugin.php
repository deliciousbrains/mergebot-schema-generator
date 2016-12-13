<?php

/**
 * The main plugin class.
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes
 */

namespace DeliciousBrains\MergebotSchemaGenerator;

use DeliciousBrains\MergebotSchemaGenerator\Schema\Primary_Keys;

class Mergebot_Schema_Generator {

	/**
	 * @var Mergebot_Schema_Generator
	 */
	protected static $instance;

	/**
	 * @var string
	 */
	public $file_path;

	/**
	 * @var
	 */
	protected $prefix;

	/**
	 * @var
	 */
	public $schema_path;
	
	/**
	 * @var
	 */
	public $wp_tables;

	/**
	 * @var
	 */
	public $wp_primary_keys;

	/**
	 * Make this class a singleton
	 *
	 * Use this instead of __construct()
	 *
	 * @param string $plugin_file_path
	 *
	 * @return Mergebot_Schema_Generator
	 */
	public static function get_instance( $plugin_file_path ) {
		if ( ! isset( static::$instance ) && ! ( self::$instance instanceof Mergebot_Schema_Generator ) ) {
			static::$instance = new Mergebot_Schema_Generator();
			// Initialize the class
			static::$instance->init( $plugin_file_path );
		}

		return static::$instance;
	}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * class via the `new` operator from outside of this class.
	 */
	protected function __construct() {
		// Singleton
	}

	/**
	 * As this class is a singleton it should not be clone-able
	 */
	protected function __clone() {
		// Singleton
	}

	/**
	 * As this class is a singleton it should not be able to be unserialized
	 */
	protected function __wakeup() {
		// Singleton
	}

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_file_path
	 */
	protected function init( $plugin_file_path ) {
		$this->file_path = $plugin_file_path;
		$this->prefix    = 'MergebotSchemaGenerator';

		spl_autoload_register( array( self::$instance, 'autoloader' ) );

		$this->set_schema_path();
		$this->set_wp_data();
	}

	protected function set_schema_path() {
		$upload_dir = wp_upload_dir();
		$path       = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'mergebot-schemas';
		wp_mkdir_p( $path );
		$this->schema_path = $path;
	}

	protected function set_wp_data() {
		$wp_tables = array(
			'posts',
			'comments',
			'links',
			'options',
			'postmeta',
			'terms',
			'term_taxonomy',
			'term_relationships',
			'termmeta',
			'commentmeta',
			'users',
			'usermeta',
		);

		$this->wp_tables = $this->get_table_columns( $wp_tables );
		$this->wp_primary_keys = Primary_Keys::get_primary_keys( $this->wp_tables );
	}

	public function get_table_columns( $tables ) {
		global $wpdb;
		$all_tables = array();
		$excluded   = array(
			'blogs',
			'blog_versions',
			'registration_log',
			'site',
			'sitemeta',
			'signups',
			'sitecategories',
		);

		foreach ( $tables as $table ) {
			$table = str_ireplace( array( '{', '}', '`', '([^', '$wpdb->prefix', '$wpdb->' ), '', $table );

			if ( in_array( $table, $excluded ) ) {
				continue;
			}

			if ( empty( $table ) ) {
				continue;
			}

			$all_tables[ $table ] = $wpdb->get_results( 'DESCRIBE ' . $wpdb->prefix . $table );
		}

		return $all_tables;
	}

	/**
	 * Autoload the class files
	 *
	 * @param string $class_name
	 */
	public function autoloader( $class_name ) {
		if ( class_exists( $class_name ) ) {
			return;
		}

		if ( false === stripos( $class_name, 'DeliciousBrains' ) ) {
			return;
		}

		if ( false === stripos( $class_name, $this->prefix ) ) {
			return;
		}

		$class_name = str_replace( '_', '-', strtolower( $class_name ) );

		$parts = explode( '\\', $class_name );
		$parts = array_slice( $parts, 2 );
		$class = array_pop( $parts );

		$dir_parts  = array_merge( array( dirname( $this->file_path ), 'classes' ), $parts );
		$dir        = implode( DIRECTORY_SEPARATOR, $dir_parts );
		$class_file = $dir . DIRECTORY_SEPARATOR . $class . '.php';

		if ( file_exists( $class_file ) ) {
			require_once $class_file;
		}
	}

	/**
	 * Log helper
	 * 
	 * @param $message
	 */
	public static function log( $message ) {
		if( is_array( $message ) || is_object( $message ) ){
			$message = print_r( $message, true );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::log( $message );
		} else {
			error_log( $message );
		}
	}
}