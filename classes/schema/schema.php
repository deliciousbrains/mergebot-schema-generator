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

class Schema {

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
	}

	/**
	 * Get schema filename
	 *
	 * @param bool $ext
	 *
	 * @return string
	 */
	public function filename( $ext = true ) {
		$filename = $this->slug;
		if ( 'plugin' === $this->type ) {
			$filename = Installer::get_plugin_basename( $this->slug );
			$filename = str_replace( '/', '-', $filename );
			$filename = str_replace( '.php', '', $filename );
		}

		$filename = $filename . '-' . $this->version;
		if ( $ext ) {
			$filename .= '.json';
		}

		return $filename;
	}

	/**
	 * Get the file path of scheme
	 * @return string
	 */
	public function file_path() {
		return apply_filters( 'mergebot_schema_generator_path', Mergebot_Schema_Generator()->schema_path . '/' . $this->filename(), $this->filename(), $this->type ) ;
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
		$data     = json_decode( $contents );

		$this->init_properties( $data );
	}

	protected function init_properties( $data ) {
		$this->primary_keys   = isset( $data->primaryKeys ) ? (array) $data->primaryKeys : array();
		$this->foreign_keys   = isset( $data->foreignKeys ) ? (array) $data->foreignKeys : array();
		$this->shortcodes     = isset( $data->shortcodes ) ? (array) $data->shortcodes : array();
		$this->relationships  = isset( $data->relationships ) ? (array) $data->relationships : array();
		$this->content        = isset( $data->content ) ? (array) $data->content : array();
		$this->table_prefixes = isset( $data->tablePrefixes ) ? (array) $data->tablePrefixes : array();
		$this->ignore         = isset( $data->ignore ) ? (array) $data->ignore : array();
		$this->file_types     = isset( $data->files ) ? (array) $data->files : array();
	}

	/**
	 * Create schema
	 */
	public function create() {
		$this->from_scratch = true;
		$this->write();
		$this->init_properties( new \stdClass() );
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

	/**
	 * Set the schema properties
	 */
	protected function set_properties() {
		$this->table_columns = Mergebot_Schema_Generator()->get_table_columns( $this->tables );
		$primary_keys        = $this->primary_keys();
		$this->set_property( 'primary_keys', $primary_keys );

		$foreign_keys        = Foreign_Keys::get_foreign_keys( $this );
		$this->set_property( 'foreign_keys', $foreign_keys );

		$shortcodes          = Shortcodes::get_elements( $this );
		$this->set_property( 'shortcodes', $shortcodes );

		$relationships       = Relationships::get_elements( $this );
		$this->set_property( 'relationships', $relationships );
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	protected function set_property( $key, $value ) {
		$this->{$key} = array_merge( $this->{$key}, $value );
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

	protected function get_custom_table_prefix() {
		$filename = $this->filename( false );

		// Check if prefix is saved
		$saved_prefix = Primary_Keys::get_custom_table_prefix($filename  ) ;
		if ( $saved_prefix ) {
			return $saved_prefix;
		}

		// Ask for a prefix
		$result = Command::table_prefix();

		if ( ! $result ) {
			return false;
		}

		// Save custom prefix
		Primary_Keys::write_custom_table_prefix( $filename, $result );

		return $result;
	}
}