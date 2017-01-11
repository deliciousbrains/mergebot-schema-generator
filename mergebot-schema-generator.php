<?php
/*
Plugin Name: Mergebot Schema Generator
Plugin URI: http://deliciousbrains.com
Description: Plugin schema generator for Mergebot
Author: Delicious Brains
Version: 0.1
Author URI: http://deliciousbrains.com/

// Copyright (c) 2016 Delicious Brains. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
//
*/

use DeliciousBrains\MergebotSchemaGenerator\Mergebot_Schema_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/classes/command.php';
}

/**
 * The main function responsible for returning the one true instance to functions everywhere.
 */
function mergebot_schema_generator() {
	// Load the main class
	require_once dirname( __FILE__ ) . '/classes/plugin.php';
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';

	return Mergebot_Schema_Generator::get_instance( __FILE__ );
}

// Initialize the plugin
mergebot_schema_generator();