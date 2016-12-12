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
	
}