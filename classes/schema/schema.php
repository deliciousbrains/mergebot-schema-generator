<?php
/**
 * The class for a schema object
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

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
	 * @return string
	 */
	public function filename() {
		return $this->slug . '-' . $this->version . '.json';
	}

	/**
	 * Get the file path of scheme
	 * @return string
	 */
	public function file_path() {
		return Mergebot_Schema_Generator()->schema_path . '/' . $this->filename();
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

		$this->primary_keys  = isset( $data->primaryKeys ) ? (array) $data->primaryKeys : array();
		$this->foreign_keys  = isset( $data->foreignKeys ) ? (array) $data->foreignKeys : array();
		$this->shortcodes    = isset( $data->shortcodes ) ? (array) $data->shortcodes : array();
		$this->relationships = isset( $data->relationships ) ? (array) $data->relationships : array();
	}

	/**
	 * Create schema
	 */
	public function create() {
		$this->write();

		$this->table_columns = Mergebot_Schema_Generator()->get_table_columns( $this->tables );
		$this->primary_keys  = Primary_Keys::get_primary_keys( $this->table_columns );
		$this->foreign_keys  = Foreign_Keys::get_foreign_keys( $this );
		$this->shortcodes    = Shortcodes::get_shortcodes( $this );
		$this->relationships = Relationships::get_relationships( $this );
		
		$this->save();
	}

	/**
	 * Update existing schema
	 */
	public function update() {
		$this->load();

		$this->table_columns = Mergebot_Schema_Generator()->get_table_columns( $this->tables );
		$primary_keys        = Primary_Keys::get_primary_keys( $this->table_columns );
		$foreign_keys        = Foreign_Keys::get_foreign_keys( $this );
		$shortcodes          = Shortcodes::get_shortcodes( $this );
		$relationships       = Relationships::get_relationships( $this );

		$this->primary_keys  = array_merge( $this->primary_keys, $primary_keys );
		$this->foreign_keys  = array_merge( $this->foreign_keys, $foreign_keys );
		$this->shortcodes    = array_merge( $this->shortcodes, $shortcodes );
		$this->relationships = array_merge( $this->relationships, $relationships );

		$this->save();
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
		);

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
		$path = WP_PLUGIN_DIR . '/' . $this->slug;

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
}