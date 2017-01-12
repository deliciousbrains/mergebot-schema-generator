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
			$table = str_ireplace( array( '{', '}', '`', '([^', '$wpdb->prefix', '$wpdb->', $wpdb->prefix ), '', $table );
			$table = ltrim( $table, '\'"' );
			$table = rtrim( $table, '\'"' );

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
	 * Get all tables installed with a custom prefix
	 *
	 * @param string $prefix
	 *
	 * @return array
	 */
	public function get_tables_by_prefix( $prefix ) {
		global $wpdb;

		if ( false === strpos( $prefix, $wpdb->prefix ) ) {
			$prefix = $wpdb->prefix . $prefix;
		}

		$sql = $wpdb->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s', DB_NAME, $prefix . '%' );

		$tables = $wpdb->get_col( $sql );

		return $tables;
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
	 * @param string $message
	 * @param null $color
	 *
	 * @return bool
	 */
	public static function log( $message, $color = null ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true );
		}

		if ( ! defined( 'WP_CLI' ) && WP_CLI ) {
			return error_log( $message );
		}

		if ( $color ) {
			$message = \WP_CLI::colorize( $color . $message . '%n' );
		}

		\WP_CLI::log( $message );
	}

	/**
	 * Log PHP code
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	public static function log_code( $code, $highlight = true ) {
		// Strip leading tabs
		$code = ltrim( $code, "\t" );
		$code = rtrim( $code, "\t}" ) . '}';
		$code = str_replace( "\t\t", "\t", $code );

		if ( false === $highlight ) {
			return Mergebot_Schema_Generator::log( $code );
		}

		// Add opening PHP tag
		$code = '<?php' . "\n" . $code;

		$highlighted = highlight_string( $code, true );

		// Remove <code>
		$highlighted = str_replace( array( '<code>', '</code>' ), '', $highlighted );
		// Replace <br /> with \n
		$highlighted = str_replace( '<br />', "\n", $highlighted );
		// Replace &nbsp;&nbsp;&nbsp;&nbsp; with \t
		$highlighted = str_replace( '&nbsp;&nbsp;&nbsp;&nbsp;', "\t", $highlighted );
		// Replace &nbsp; with ' ';
		$highlighted = str_replace( '&nbsp;', ' ', $highlighted );
		// Replace </span> with  '%n'
		$highlighted = str_replace( '</span>', '%n', $highlighted );
		// Replace HTML characters
		$highlighted = htmlspecialchars_decode( $highlighted );

		$color_mapping = array(
			'0000BB' => 'B',
			'007700' => 'G',
			'DD0000' => 'R',
			'FF8000' => 'M',
			'000000' => 'n',
		);

		foreach ( $color_mapping as $find => $replace ) {
			// <span style="color: #0000BB"> with color %B
			$highlighted = str_replace( '<span style="color: #' . $find . '">', '%' . $replace, $highlighted );
		}

		$highlighted = \WP_CLI::colorize( $highlighted );

		Mergebot_Schema_Generator::log( $highlighted );
	}
}