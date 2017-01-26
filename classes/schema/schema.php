<?php
/**
 * The class for a schema object
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

use DeliciousBrains\MergebotSchemaGenerator\Command;
use DeliciousBrains\MergebotSchemaGenerator\Installer;

class Schema extends Abstract_Element {

	/**
	 * @var
	 */
	public $name;

	/**
	 * @var
	 */
	public $url;

	/**
	 * @var string
	 */
	public $slug;

	/**
	 * @var string
	 */
	public $version;

	/**
	 * @var string
	 */
	public $type;

	/**
	 * @var array
	 */
	public $files;

	/**
	 * @var array
	 */
	public $tables;

	/**
	 * @var bool|string
	 */
	public $custom_prefix;

	/**
	 * @var array
	 */
	public $table_columns;

	/**
	 * @var array
	 */
	public $primary_keys;

	/**
	 * @var array
	 */
	public $foreign_keys;

	/**
	 * @var array
	 */
	public $shortcodes;

	/**
	 * @var array
	 */
	public $relationships;

	/**
	 * @var array
	 */
	public $content;

	/**
	 * @var array
	 */
	public $table_prefixes;

	/**
	 * @var array
	 */
	public $ignore;

	/**
	 * @var array
	 */
	public $file_types;

	/**
	 * @var bool
	 */
	public $from_scratch = false;

	/**
	 * Schema constructor.
	 *
	 * @param string $slug
	 * @param string $version
	 * @param string $type
	 */
	public function __construct( $slug = 'wordpress', $version = '', $type = 'plugin' ) {
		if ( 'wordpress' === $type ) {
			$slug = $type;
		}

		$this->slug    = $slug;
		$this->version = $version;
		$this->type    = $type;

		$this->files  = $this->list_files();
		$this->tables = $this->get_tables();
		$this->custom_prefix = $this->get_custom_table_prefix_from_tables();
	}

	/**
	 * Get schema filename
	 *
	 * @param bool $ext
	 *
	 * @param bool $version
	 *
	 * @return string
	 */
	public function filename( $ext = true, $version = true ) {
		$filename = $this->slug;
		if ( 'plugin' === $this->type ) {
			$filename = Installer::get_plugin_basename( $this->slug );
			$filename = str_replace( '/', '-', $filename );
			$filename = str_replace( '.php', '', $filename );
		}

		if ( $version) {
			$filename = $filename . '-' . $this->version;
		}
		if ( $ext ) {
			$filename .= '.json';
		}

		return $filename;
	}

	/**
	 * Get the file path of schema
	 *
	 * @return string
	 */
	public function file_path() {
		$base_path = Mergebot_Schema_Generator()->schema_path;
		$type      = 'wordpress' === $this->type ? 'core' : $this->type . 's';
		$filename  = $this->filename();

		$path = $base_path . '/' . $type . '/' .$filename;

		return apply_filters( 'mergebot_schema_generator_path', $path, $base_path , $this->type, $this->filename() ) ;
	}

	/**
	 * Does schema exist
	 *
	 * @return bool
	 */
	public function exists() {
		return file_exists( $this->file_path() );
	}

	/**
	 * Load existing schema
	 */
	public function load() {
		$contents = $this->read();
		$data     = json_decode( $contents, true );

		$this->init_properties( $data );
	}

	protected function init_properties( $data = array() ) {
		// Info
		$this->name = $this->init_property( $data, 'name', '' );
		$this->url  = $this->init_property( $data, 'url', '' );

		// Data
		$this->primary_keys   = $this->init_property( $data, 'primaryKeys' );
		$this->foreign_keys   = $this->init_property( $data, 'foreignKeys' );
		$this->shortcodes     = $this->init_property( $data, 'shortcodes' );
		$this->relationships  = $this->init_property( $data, 'relationships' );
		$this->content        = $this->init_property( $data, 'content' );
		$this->table_prefixes = $this->init_property( $data, 'tablePrefixes' );
		$this->ignore         = $this->init_property( $data, 'ignore' );
		$this->file_types     = $this->init_property( $data, 'files' );
	}

	protected function init_property( $data, $key, $default = array() ) {
		return isset( $data[$key] ) ? $data[$key] : $default;
	}

	/**
	 * Create schema
	 */
	public function create() {
		$this->from_scratch = true;
		$this->write();
		$this->init_properties();
		$this->set_properties();
		$this->save();
	}

	/**
	 * Update existing schema
	 */
	public function update() {
		$this->load();
		$this->set_properties();
		$this->save();
	}

	protected function set_info() {
		if ( 'wordpress' === $this->type ) {
			return;
		}

		if ( ! empty( $this->name ) && ! empty( $this->url ) ) {
			return;
		}

		$data = Installer::get_latest_plugin_data( $this->slug );
		if ( false === $data ) {
			// Ask for name and URL
			$info       = Info::ask();
			$this->name = $info->name;
			$this->url  = $info->url;

			return;
		}

		$this->name = $data->name;
		$this->url  = 'https://wordpress.org/plugins/' . $this->slug;
	}

	/**
	 * Set the schema properties
	 */
	protected function set_properties() {
		$this->set_info();
		$this->table_columns = Mergebot_Schema_Generator()->get_table_columns( $this->tables );
		$primary_keys        = $this->primary_keys();
		$this->set_property( 'primary_keys', $primary_keys );

		$foreign_keys        = Foreign_Keys::get_foreign_keys( $this );
		$this->set_property( 'foreign_keys', $foreign_keys );

		$shortcodes          = Shortcodes::get_elements( $this );
		$this->set_property( 'shortcodes', $shortcodes );

		$relationships       = Relationships::get_elements( $this );
		$this->set_property( 'relationships', $relationships, true );
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 * @param bool   $recursive
	 */
	protected function set_property( $key, $value, $recursive = false ) {
		if ( $recursive ) {
			$this->{$key} = $this->array_merge_recursive( $this->{$key}, $value );

			return;
		}

		$this->{$key} = array_merge( $this->{$key}, $value );
	}

	protected function array_merge_recursive( $array_1, $array_2 ) {
		$array_1 = $this->add_keys_to_nested_arrays( $array_1 );
		$array_2 = $this->add_keys_to_nested_arrays( $array_2 );

		$merged = array_replace_recursive( $array_1, $array_2 );
		$merged = $this->remove_keys_to_nested_arrays( $merged );

		return $merged;
	}

	protected function remove_keys_to_nested_arrays( $data ) {
		$new_data = array();
		foreach ( $data as $element_key => $values ) {
			if ( ! is_array( $values ) ) {
				$new_data[ $element_key ] = $values;
			}

			$new_data[ $element_key ] = array_values( $values );
		}

		return $new_data;
	}

	protected function add_keys_to_nested_arrays( $data ) {
		$new_data = array();
		foreach ( $data as $element_key => $values ) {
			if ( ! is_array( $values ) ) {
				$new_data[ $element_key ] = $values;
			}

			$new_data[ $element_key ] = $this->add_keys_to_array( $values );
		}

		return $new_data;
	}

	protected function add_keys_to_array( $data ) {
		$new_data = array();
		foreach ( $data as $value ) {
			$new_key              = sha1( serialize( $value ) );
			$new_data[ $new_key ] = $value;
		}

		return $new_data;
	}

	/**
	 * Get primary keys
	 *
	 * @return array
	 */
	protected function primary_keys() {
		if ( empty( $this->table_columns ) ) {
			return array();
		}

		$primary_keys = Primary_Keys::get_pk_elements( $this->table_columns );
		if ( ! empty( $primary_keys ) ) {
			return $primary_keys;
		}

		$prefixed_tables = $this->get_prefixed_tables();
		if ( false === $prefixed_tables ) {
			return array();
		}

		$this->table_columns = Mergebot_Schema_Generator()->get_table_columns( $prefixed_tables );

		return Primary_Keys::get_primary_keys( $this->table_columns );
	}

	/**
	 * Save schema data
	 */
	protected function save() {
		$info = array();
		if ( 'wordpress' !== $this->type ) {
			$info = array(
				'name'    => $this->name,
				'version' => $this->version,
				'url'     => $this->url,
			);
		}

		$file_contents = array(
			'primaryKeys'   => $this->primary_keys,
			'foreignKeys'   => $this->foreign_keys,
			'shortcodes'    => $this->shortcodes,
			'relationships' => $this->relationships,
			'content'       => $this->content,
			'tablePrefixes' => $this->table_prefixes,
			'ignore'        => $this->ignore,
			'files'         => $this->file_types,
		);

		$file_contents = array_merge( $info, $file_contents );

		foreach ( $file_contents as $key => $value ) {
			if ( empty( $value ) ) {
				unset( $file_contents[ $key ] );
			}
		}

		if ( empty( $file_contents ) ) {
			// Ensure an empty schema is in correct JSON format
			$file_contents = new \stdClass();
		}

		$this->write( json_encode( $file_contents, JSON_PRETTY_PRINT ) );

		if ( 'wordpress' === $this->type ) {
			return;
		}

		$schema = $this->filename( false, false );

		self::write_slug_schema_prefix( $schema, $this->slug );
	}

	/**
	 * @return string
	 */
	protected static function get_schema_slug_file() {
		return dirname( Mergebot_Schema_Generator()->file_path ) . '/data/schema-slug.json';
	}

	/**
	 * Get the slug from a schema file
	 *
	 * @param string $schema
	 *
	 * @return string|bool
	 */
	public static function get_slug_from_schema( $schema ) {
		$content = self::read_data_file( self::get_schema_slug_file() );

		if ( isset( $content[ $schema ] ) ) {
			return $content[ $schema ];
		}

		return false;
	}

	/**
	 * @param string $schema
	 * @param string $slug
	 *
	 * @return int
	 */
	public static function write_slug_schema_prefix( $schema, $slug ) {
		$file    = self::get_schema_slug_file();
		$content = self::read_data_file( $file );

		$content[ $schema ] = $slug;

		return self::write_data_file( $file, $content );
	}

	/**
	 * Read schema file
	 *
	 * @return string
	 */
	protected function read() {
		return file_get_contents( $this->file_path() );
	}

	/**
	 * Write schema file
	 *
	 * @param string $content
	 */
	protected function write( $content = '{}' ) {
		$schema_file = $this->file_path();

		if ( file_exists( $schema_file ) ) {
			// TODO duplicate or merge?
			//return;
		}

		file_put_contents( $schema_file, $content );
	}

	/**
	 * Get the path of the files to be analyzyed
	 *
	 * @return string
	 */
	protected function get_files_path() {
		$path = Installer::dir( $this->slug );

		if ( 'wordpress' === $this->type ) {
			$path = ABSPATH;
		}

		return $path;
	}

	/**
	 * Get excluded directories
	 *
	 * @return array
	 */
	protected function get_excluded_dirs() {
		$dirs = array();
		if ( 'wordpress' === $this->type ) {
			$dirs[] = 'wp-content';
		}

		return $dirs;
	}

	/**
	 * Get all PHP files in scope for analyzing
	 *
	 * @return array
	 */
	protected function list_files() {
		$plugin_dir = $this->get_files_path();
		$exclude    = $this->get_excluded_dirs();
		$return     = array();

		$filter = function ( $file, $key, $iterator ) use ( $exclude ) {
			if ( $iterator->hasChildren() && ! in_array( $file->getFilename(), $exclude ) ) {
				return true;
			}

			return $file->isFile();
		};

		$innerIterator = new \RecursiveDirectoryIterator( $plugin_dir, \RecursiveDirectoryIterator::SKIP_DOTS );
		$iterator      = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator( $innerIterator, $filter )
		);
		foreach ( $iterator as $file ) {
			$parts     = explode( '.', $file );
			$extension = array_pop( $parts );
			if ( in_array( strtolower( $extension ), array( 'php' ) ) ) {
				$return[] = $file;
			}
		}

		return $return;
	}

	/**
	 * Get all the tables created
	 *
	 * @return array
	 */
	protected function get_tables() {
		$all_tables = array();
		$search     = 'create table';

		foreach ( $this->files as $file ) {
			$content = strtolower( file_get_contents( $file->getRealPath() ) );
			if ( false === strpos( $content, $search ) ) {
				continue;
			}

			$pattern = '/(?<=\b' . $search . '\s)([\S]+)/is';
			preg_match_all( $pattern, $content, $matches );

			if ( $matches && is_array( $matches[0] ) ) {
				$tables     = $matches[0];
				$all_tables = array_merge( $all_tables, $tables );
			}
		}

		return $all_tables;
	}

	/**
	 * @return array|bool
	 */
	protected function get_prefixed_tables() {
		$prefix = $this->get_custom_table_prefix();

		if ( false === $prefix ) {
			// TODO handle warning - try again with a prefix?
			return false;
		}

		$tables = Mergebot_Schema_Generator()->get_tables_by_prefix( $prefix );
		if ( empty( $tables ) ) {
			// TODO handle warning - try again with a prefix?
			return false;
		}

		return $tables;
	}

	protected function get_custom_table_prefix_from_tables() {
		if ( 'wordpress' === $this->type ) {
			return '';
		}

		if ( empty( $this->tables ) ) {
			return '';
		}

		$filename = $this->filename( false );
		$prefix = Primary_Keys::get_custom_table_prefix( $filename );

		if ( false !== $prefix ) {
			return $prefix;
		}

		$table_parts = array();
		$first_parts = false;
		foreach ( $this->tables as $table ) {
			$table                 = Mergebot_Schema_Generator()->strip_prefix_from_table( $table );
			$parts                 = explode( '_', $table );
			$table_parts[ $table ] = $parts;
			if ( false === $first_parts ) {
				$first_parts = $parts;
			}
		}

		$prefix = '';
		foreach ( $first_parts as $key => $part ) {
			if ( $this->part_appears_in_all_tables( $part, $table_parts, $key ) ) {
				$prefix .= $part;
			}
		}


		if ( ! empty( $prefix ) ) {
			// Save custom prefix
			$prefix .= '_';
			Primary_Keys::write_custom_table_prefix( $filename, $prefix );
		} else {
			$prefix = $this->ask_for_custom_prefix( $filename );
		}

		return $prefix;
	}

	protected function part_appears_in_all_tables( $part, $tables, $pos ) {
		foreach( $tables as $table => $parts ) {
			if ( false === $this->part_appears_in_table_parts( $part, $parts, $pos ) ) {
				return false;
			}
		}

		return true;
	}

	protected function part_appears_in_table_parts( $part, $parts, $pos = 0 ) {
		return isset( $parts[ $pos ] ) && $part === $parts[ $pos ];
	}

	protected function get_custom_table_prefix() {
		$filename = $this->filename( false );

		// Check if prefix is saved
		$saved_prefix = Primary_Keys::get_custom_table_prefix( $filename );
		if ( $saved_prefix ) {
			return $saved_prefix;
		}

		return $this->ask_for_custom_prefix( $filename );
	}

	protected function ask_for_custom_prefix( $filename ) {
		// Ask for a prefix
		$result = Command::table_prefix();

		if ( ! $result ) {
			return false;
		}

		// Save custom prefix
		Primary_Keys::write_custom_table_prefix( $filename, $result );
	}
}