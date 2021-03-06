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
	public $basename;

	/**
	 * @var string
	 */
	public $slug_key;

	/**
	 * @var string
	 */
	public $version;

	/**
	 * @var string
	 */
	public $tested_up_to;

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
	 * @var array
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
	public $meta_tables;

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
	 * @param string $slug_key
	 */
	public function __construct( $slug = 'wordpress', $version = '', $type = 'plugin', $slug_key = '' ) {
		if ( 'wordpress' === $type ) {
			$slug = $type;
		}

		$this->slug     = $slug;
		$this->version  = $version;
		$this->type     = $type;
		$this->slug_key = $slug_key;
	}

	public function init() {
		$this->files  = $this->list_files();
		$this->tables = $this->get_tables();
		$this->custom_prefix = $this->get_custom_table_prefix_from_tables();
	}

	/**
	 * @return string
	 */
	protected function get_basename() {
		if ( empty( $this->basename ) ) {
			$this->basename = Installer::get_plugin_basename( $this->slug );
		}

		return $this->basename;
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
			$filename = $this->get_basename();
			$filename = str_replace( '/', '-', $filename );
			$filename = str_replace( '.php', '', $filename );
		}

		if ( $version ) {
			$filename .= '-' . $this->version;
		}

		if ( $ext ) {
			$filename .= '.json';
		}

		return $filename;
	}

	/**
	 * Get the file path of schema
	 *
	 * @param string $action
	 *
	 * @param bool   $ext
	 * @param bool   $version
	 *
	 * @return string
	 */
	public function file_path( $action = 'read', $ext = true, $version = true ) {
		$base_path = Mergebot_Schema_Generator()->schema_path;
		$type      = 'wordpress' === $this->type ? 'core' : $this->type . 's';
		$filename  = $this->filename( $ext, $version );

		$path = $base_path . '/' . $type . '/' .$filename;

		return apply_filters( 'mergebot_schema_generator_path', $path, $base_path , $this->type, $this->filename(), $action ) ;
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
	 * Get the latest version of a schema.
	 *
	 * @param bool $max_version
	 *
	 * @return bool|Schema
	 */
	public function get_latest_schema( $max_version = false ) {
		$path = $this->file_path( 'read', false, false );

		$latest_schema = false;
		$schemas       = glob( $path . '-*.json' );
		if ( empty( $schemas ) ) {
			return $latest_schema;
		}

		$all_schemas = array();
		foreach( $schemas as $schema ) {
			$filename = str_replace( '.json', '', basename( $schema ) );
			$parts    = explode( '-', $filename );
			$version  = array_pop( $parts );

			if ( ! $max_version || version_compare( $version, $max_version, '<') ) {
				$all_schemas[] = $schema;
			}
		}

		if ( empty( $all_schemas ) ) {
			return $latest_schema;
		}

		$schema_path = array_pop( $all_schemas );

		$filename = str_replace( '.json', '', basename( $schema_path ) );
		$parts    = explode( '-', $filename );
		$version  = array_pop( $parts );

		return new Schema( $this->slug, $version, 'plugin', $filename );
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
		$this->name         = $this->init_property( $data, 'name', '' );
		$this->url          = $this->init_property( $data, 'url', '' );
		$this->tested_up_to = $this->init_property( $data, 'testedUpTo', $this->version );

		// Data
		$this->primary_keys   = $this->init_property( $data, 'primaryKeys' );
		$this->foreign_keys   = $this->init_property( $data, 'foreignKeys' );
		$this->meta_tables    = $this->init_property( $data, 'metaTables' );
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

		if ( 'theme' === $this->type ) {
			$data       = wp_get_theme( $this->slug );
			$this->name = $data->get( 'Name' );
			$this->url  = $data->get( 'ThemeURI' );

			return;
		}

		$data = Installer::get_latest_plugin_data( $this->slug );
		if ( false === $data ) {
			// get info from plugin header
			$data = Installer::get_installed_plugin_data( $this->basename );

			if ( false !== $data && ! empty( $data['Name'] ) && ( ! empty( $data['PluginURI'] ) || ! empty( $data['AuthorURI'] )) ) {
				$this->name = $data['Name'];
				$this->url  = ! empty( $data['PluginURI'] ) ? $data['PluginURI'] : $data['AuthorURI'];

				return;
			}

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
		$this->set_property( 'primary_keys', $primary_keys, $primary_keys );

		$foreign_keys        = Foreign_Keys::get_foreign_keys( $this );
		$this->set_property( 'foreign_keys', $foreign_keys, $foreign_keys );

		$all_shortcodes = Shortcodes::find_elements( $this );
		$shortcodes     = Shortcodes::get_elements( $this, $all_shortcodes );
		$this->set_property( 'shortcodes', $shortcodes, $all_shortcodes );

		$all_relationships    = Relationships::find_elements( $this );
		$relationships       = Relationships::get_elements( $this, $all_relationships );
		$this->set_property( 'relationships', $relationships, $all_relationships, true );
	}

	/**
	 * @param string $key
	 * @param mixed  $new_values
	 * @param mixed  $all_values
	 * @param bool   $recursive
	 */
	protected function set_property( $key, $new_values, $all_values = null, $recursive = false ) {
		$existing = $this->{$key};
		if ( is_null( $all_values ) ) {
			$all_values = $new_values;
		}

		if ( $recursive ) {
			$existing = $this->elements_diff_recursive( $key, $existing, $all_values );
			$merged = $this->array_merge_recursive( $key, $existing, $new_values );
			ksort( $merged );
			$this->{$key} = $merged;
			return;
		}

		$existing = $this->elements_diff( $key, $existing, $all_values );
		$merged   = array_merge( $existing, $new_values );
		ksort( $merged );
		$this->{$key} = $merged;
	}

	protected function elements_diff( $key, $existing, $current ) {
		$existing_not_found = array_diff_key( $existing, $current );
		foreach ( $existing_not_found as $element_key => $element_value ) {
			// This is the schema but doesn't exist in Object Version
			// Remove or keep?
			$keep = Command::keep_element( $this->slug, $this->version, $key, $element_key );
			if ( false === $keep ) {
				unset( $existing[ $element_key ] );
			}
		}

		return $existing;
	}

	protected function elements_diff_recursive( $key, $existing, $current ) {
		foreach ( $existing as $existing_key => $values ) {
			$current_values = isset( $current[ $existing_key ] ) ? $current[ $existing_key ] : array();
			$values = $this->add_array_keys( $values );

			$existing[ $existing_key ] = $values;

			$existing_not_found = array_diff_key( $values, $current_values );
			foreach ( $existing_not_found as $element_key => $element_value ) {
				if ( 'relationships' == $key && Relationships::is_key_translated( $this, $existing_key, $element_key ) ) {
					continue;
				}

				// This is the schema but doesn't exist in Object Version
				// Remove or keep?
				$keep = Command::keep_element( $this->slug, $this->version, $key . ' - ' . $existing_key, $element_key );
				if ( false === $keep ) {
					unset( $existing[ $existing_key ] [ $element_key ] );
				}
			}
		}

		return $existing;
	}

	protected function array_merge_recursive( $section, $array_1, $array_2 ) {
		$merged = array();
		foreach ( $array_2 as $key => $value ) {
			if ( ! isset( $array_1[ $key ] ) ) {
				$merged[ $key ] = $value;
				continue;
			}

			if ( 'relationships' === $section ) {
				$merged[ $key ] = Relationships::merge_relationships( $this, $array_1[ $key ], $value, $key );
				continue;
			}

			$merged[ $key ] = array_merge( $array_1[ $key ], $value );
		}

		foreach ( $array_1 as $key => $value ) {
			if ( isset( $array_2[ $key ] ) ) {
				continue;
			}
			$merged[ $key ] = $value;
		}

		foreach ( $merged as $key => $value ) {
			ksort( $value );
			$merged[ $key ] = $value;
		}

		return $merged;
	}

	public static function is_assoc_array( array $arr ) {
		if ( array() === $arr ) {
			return false;
		}

		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	protected function add_array_keys( $array ) {
		if ( self::is_assoc_array( $array ) ) {
			return $array;
		}

		$new = array();
		foreach ( $array as $data ) {
			$key         = $this->get_key_rel_data( $data );
			$new[ $key ] = $data;
		}

		return $new;
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

	protected function get_keys_from_nested_arrays( $data ) {
		$new_data = array();
		foreach ( $data as $element_key => $values ) {
			if ( ! is_array( $values ) ) {
				$new_data[ $element_key ] = $values;
			}

			$new_data[ $element_key ] = $this->add_keys_to_array( $values, false );
		}

		return $new_data;
	}

	protected function get_key_rel_data( $data ) {
		$key = key( $data );

		return $data[ $key ];
	}

	protected function add_keys_to_array( $data, $whole_array = true ) {
		$new_data = array();
		foreach ( $data as $value ) {
			if ( $whole_array ) {
				$keys = $value;
				unset( $keys['serialized'] );
				$new_key = sha1( serialize( $keys ) );
			} else {
				$key = key( $value );
				$new_key = $value[ $key ];
			}

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
				'name'       => $this->name,
				'version'    => $this->version,
				'testedUpTo' => $this->tested_up_to,
				'url'        => $this->url,
			);
		}

		if ( 'plugin' === $this->type ) {
			$info['basename'] = $this->get_basename();
		}

		$file_contents = array(
			'primaryKeys'   => $this->primary_keys,
			'foreignKeys'   => $this->foreign_keys,
			'metaTables'    => $this->meta_tables,
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

		self::write_slug_schema_prefix( $this, $this->slug );
	}

	/**
	 * Get the slug from a schema file
	 *
	 * @param Schema $schema
	 *
	 * @return string|bool
	 */
	public static function get_slug_from_schema( $schema ) {
		$content = $schema->read_data_file();

		if ( isset( $content['slug'] ) ) {
			return $content['slug'];
		}

		return false;
	}

	/**
	 * @param Schema $schema
	 * @param string $slug
	 *
	 * @return int
	 */
	public static function write_slug_schema_prefix( $schema, $slug ) {
		$content = $schema->read_data_file();

		$content['slug'] = $slug;

		return $schema->write_data_file( $content );
	}

	/**
	 * Read schema file
	 *
	 * @param null $path
	 *
	 * @return string
	 */
	protected function read( $path = null ) {
		if ( is_null( $path ) ) {
			$path = $this->file_path();
		}

		return file_get_contents( $path );
	}

	public function contents() {
		return $this->read();
	}

	public function json() {
		return json_decode( $this->contents(), true );
	}

	/**
	 * Write schema file
	 *
	 * @param string $content
	 */
	protected function write( $content = '{}' ) {
		$schema_file = $this->file_path( 'write' );

		if ( file_exists( $schema_file ) ) {
			// TODO duplicate or merge?
			//return;
		}

		wp_mkdir_p( dirname( $schema_file ) );

		file_put_contents( $schema_file, $content );
	}

	/**
	 * @param $contents
	 * @param $version
	 *
	 * @return string
	 */
	public function update_tested_up_to( $contents, $version ) {
		$contents['testedUpTo'] = $version;
		$this->write( json_encode( $contents, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Duplicate an existing version schema to the new version.
	 *
	 * @param $version
	 */
	public function duplicate( $version ) {
		$path     = $this->file_path( 'read', false, false);
		$path     .= '-' . $version . '.json';
		$contents = $this->read( $path );
		$contents = str_replace( $version, $this->version, $contents );

		$this->write( $contents );
	}

	/**
	 * Get the path of the files to be analyzyed
	 *
	 * @return string
	 */
	protected function get_files_path() {
		if ( 'wordpress' === $this->type ) {
			return ABSPATH;
		}

		$path = Installer::dir( $this->slug, $this->type );

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
		$all_tables             = array();
		$search                 = 'create table';
		$has_custom_tables      = false;
		$force_use_table_prefix = false;

		foreach ( $this->files as $file ) {
			$content = strtolower( file_get_contents( $file->getRealPath() ) );
			if ( false === stripos( $content, $search ) ) {
				continue;
			}
			$has_custom_tables = true;
			$pattern           = '/(CREATE TABLE(?: IF NOT EXISTS)?\s)([\S]+)/is';
			preg_match_all( $pattern, $content, $matches );

			if ( $matches && is_array( $matches[2] ) && ! empty( $matches[2] ) ) {
				if ( in_array( $matches[2][0], array( '"', "'" ) ) || ( false !== strpos( $matches[2][0], '$' ) && false === strpos( $matches[2][0], '$wpdb->' ) ) || false !== strpos( $matches[2][0], '%' ) ) {
					// Tables names defined in variables.
					$force_use_table_prefix = true;
					continue;
				}

				$tables = $matches[2];

				foreach ( $tables as $key => $table ) {
					if ( ! preg_match_all( '/[A-Za-z0-9_]+/', $table ) ) {
						unset( $tables[ $key ] );
						continue;
					}
					$tables[ $key ] = str_replace( '$wpdb->', '', $table );
				}

				$all_tables = array_merge( $all_tables, $tables );
			}
		}

		if ( $force_use_table_prefix || ( empty( $all_tables ) && $has_custom_tables ) ) {
			$prefixed_tables = $this->get_prefixed_tables();

			if ( is_array( $prefixed_tables ) ) {
				$all_tables = array_merge( $all_tables, $prefixed_tables );
			}

		}

		return array_unique( $all_tables );
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

		$prefixes = $this->format_custom_prefixes( $prefix );

		$all_tables = array();
		foreach ( $prefixes as $prefix ) {
			$tables     = Mergebot_Schema_Generator()->get_tables_by_prefix( $prefix );
			$all_tables = array_merge( $all_tables, $tables );
		}

		if ( empty( $all_tables ) ) {
			// TODO handle warning - try again with a prefix?
			return false;
		}


		return $all_tables;
	}

	/**
	 * Return array of custom table prefixes including trailing underscore.
	 *
	 * @param mixed $prefix
	 *
	 * @return array
	 */
	protected function format_custom_prefixes( $prefix ) {
		if ( empty ( $prefix ) ) {
			return array();
		}

		$all_prefixes = array();
		$prefixes     = explode( ',', $prefix );

		foreach ( $prefixes as $prefix ) {
			$all_prefixes[] = rtrim( $prefix, '_' ) . '_';
		}

		return $all_prefixes;
	}

	/**
	 * Get custom prefixes for the tables created by the plugin.
	 *
	 * @return array
	 */
	protected function get_custom_table_prefix_from_tables() {
		if ( 'wordpress' === $this->type ) {
			return array();
		}

		if ( empty( $this->tables ) ) {
			return array();
		}

		$filename = $this->filename( false, false );
		$prefix = Primary_Keys::get_custom_table_prefix( $this );

		if ( false !== $prefix ) {
			return $this->format_custom_prefixes( $prefix );
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
			Primary_Keys::write_custom_table_prefix( $this, $prefix );
		} else {
			$prefix = $this->ask_for_custom_prefix( $filename );
		}

		if ( false === $prefix ) {
			$prefix = '';
		}

		return $this->format_custom_prefixes( $prefix );
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
		$filename = $this->filename( false, false );

		// Check if prefix is saved
		$saved_prefix = Primary_Keys::get_custom_table_prefix( $this );
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
		Primary_Keys::write_custom_table_prefix( $this, $result );

		return $result;
	}

	/**
	 * @return array|mixed|object
	 */
	public function read_data_file() {
		$file = $this->get_schema_data_file_path();
		if ( ! file_exists( $file ) ) {
			$this->write_data_file();

			return array();
		}

		$contents = file_get_contents( $file );
		if ( empty( $contents ) ) {
			return array();
		}

		return json_decode( $contents, true );
	}

	/**
	 * @param array $content
	 *
	 * @return int
	 */
	public function write_data_file( $content = array() ) {
		$content = json_encode( $content, JSON_PRETTY_PRINT );

		return file_put_contents( $this->get_schema_data_file_path(), $content );
	}

	/**
	 * @return string
	 */
	protected function get_schema_data_file_path() {
		$filename = $this->slug_key ? $this->slug_key : $this->filename( false, false );

		return dirname( Mergebot_Schema_Generator()->file_path ) . '/data/' . $filename . '.json';
	}
}