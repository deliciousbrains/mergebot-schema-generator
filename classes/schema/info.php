<?php
/**
 * The class for a primary keys
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

use DeliciousBrains\MergebotSchemaGenerator\Command;

class Info {

	public static function ask() {
		$info = new \stdClass();
		$info->name = Command::info( 'name' );
		$info->url = Command::info( 'url' );

		return $info;
	}
}
