<?php

/**
 * The class for the WP CLI mergebot command.
 *
 * This is used to control aspects of the plugin with the CLI.
 *
 * @since      0.1
 * @package    Mergebot
 * @subpackage Mergebot/classes/cli
 */


namespace DeliciousBrains\MergebotSchemaGenerator;

class Command extends \WP_CLI_Command {

	/**
	 * @var string Type of thing we are generating a schema for.
	 */
	protected $type = 'plugin';

	/**
	 * Generates a schema for a plugin
	 *
	 * ## OPTIONS
	 *
	 * [--plugin]
	 * : Plugin slug.
	 *
	 * [--version]
	 * : Version of plugin. Defaults to latest if not already installed, or version of installation
	 *
	 * [--scratch]
	 * : Create schema from scratch even if one exists
	 *
	 * [--skip-install]
	 * : Generate the schema from an already installed plugin
	 *
	 * [--load-admin]
	 * : Load admin hooks for plugin, eg. for table installing
	 *
	 * ## EXAMPLES
	 *
	 *     wp mergebot-schema generate
	 *     wp mergebot-schema generate --version=4.1
	 *     wp mergebot-schema generate --plugin=woocommerce
	 *     wp mergebot-schema generate --plugin=woocommerce --version=2.5.5
	 *     wp mergebot-schema generate --plugin=woocommerce --version=2.5.5 --scratch
	 *
	 */
	public function generate( $args, $assoc_args ) {
		if ( isset( $assoc_args['load-admin'] ) && ( ! defined( 'WP_ADMIN' ) || false === WP_ADMIN ) ) {
			$path = WP_CONTENT_DIR . '/' . basename( mergebot_schema_generator()->file_path, '.php' );
			\WP_CLI::error( sprintf( 'This command must be run from inside %s', $path ) );
		}

		$slug    = $this->get_slug( $assoc_args );
		$version = $this->get_version( $assoc_args, $slug );

		$create_from_scratch = isset( $assoc_args['scratch'] );

		// Handle installing the thing we want to generate a schema for.
		$installer = new Installer( $slug, $version, $this->type );
		$installer->init( isset( $assoc_args['skip-install'] ) );

		if ( isset( $assoc_args['load-admin'] ) ) {
			// Bootstrap admin hooks where plugins might install tables
			do_action( 'admin_init' );
			do_action( 'admin_menu' );
		}

		// Generate the schema.
		$generator = new Generator( $slug, $version, $this->type );
		$generator->generate( $create_from_scratch );

		// Clean up the install
		$installer->clean_up();

		\WP_CLI::success( ucfirst( $slug ) . ' schema generated!' );
	}

	/**
	 * Get the slug of the object we are creating a schema for.
	 *
	 * @param array $assoc_args
	 *
	 * @return string
	 */
	protected function get_slug( $assoc_args ) {
		if ( isset( $assoc_args['plugin'] ) && ! empty( $assoc_args['plugin'] ) ) {
			return $assoc_args['plugin'];
		}

		$slug       = 'wordpress';
		$this->type = $slug;

		return $slug;
	}

	/**
	 * Get the version of the object we are creating a schema for.
	 *
	 * @param array $assoc_args
	 * @param array $slug
	 *
	 * @return bool|string
	 */
	protected function get_version( $assoc_args, $slug ) {
		if ( isset( $assoc_args['version'] ) ) {
			return $assoc_args['version'];
		}

		if ( 'wordpress' === $this->type ) {
			return Installer::get_latest_core_version();
		}

		$latest_version = Installer::get_latest_plugin_version( $slug );
		if ( false === $latest_version ) {
			$latest_version = 'latest';
		}

		return $latest_version;
	}

	/**
	 * Asks for ID related attributes for a shortcode.
	 * Comma separated list of attributes. [table]:[attribute] format for non-post tables
	 * eg. id,ids
	 * eg. comments:id,comments:ids
	 *
	 * @param string $tag
	 * @param array  $assoc_args
	 *
	 * @return bool|string
	 */
	public static function shortcode( $tag, $attribute_name, $attributes, $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			$tag = \WP_CLI::colorize( '%B[' . $tag . ']%n' );

			$attributes = array_map( function ( $attr ) use ( $attribute_name ) {
				return $attribute_name . "[" . $attr . "]";
			}, $attributes );

			$atts = implode( ', ' , $attributes );
			fwrite( STDOUT, 'Does the shortcode ' . $tag . " contain ID attributes?\nUsed Attributes: {$atts}\n[attributes/n] Comma separated list of attributes. [table]:[attribute] format for non-post tables\n" );

			$answer = trim( fgets( STDIN ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

	/**
	 * Asks if the a piece of meta data contains IDs in the value
	 *
	 * @param array  $assoc_args
	 *
	 * @return bool|string
	 */
	public static function meta( $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {

			fwrite( STDOUT, "Does the meta contain ID data?\n[Y/n] " );

			$answer = trim( fgets( STDIN ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

	/**
	 * Asks if the a piece of meta data contains IDs in the value
	 *
	 * @param       $key
	 * @param array $assoc_args
	 *
	 * @return bool|string
	 */
	public static function meta_key( $key, $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {

			$key = \WP_CLI::colorize( '%B' . $key . '%n' );
			fwrite( STDOUT, "The meta key $key contains a variable, enter the string: " );

			$answer = trim( fgets( STDIN ) );

			if ( empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

	/**
	 * Asks if the a piece of meta data contains IDs in the value
	 *
	 * @param bool  $simple
	 * @param array $assoc_args
	 *
	 * @return bool|string
	 */
	public static function meta_table( $simple = true, $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {

			if ( $simple ) {
				fwrite( STDOUT, "\n" . "Is it a simple reference to a table ID? Which table (without prefix)?\n[table/n/ignore] " );
			} else {
				fwrite( STDOUT, "\n" . "Which table does the serialized value relate to (without prefix)?\n[table] " );
			}

			$answer = trim( fgets( STDIN ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

	/**
	 * Asks if the a piece of meta data contains IDs in the value
	 *
	 * @param array $assoc_args
	 *
	 * @return bool|string
	 */
	public static function meta_serialized_key( $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {

			fwrite( STDOUT, "\n" . "Is the serialized array keyed?\n[n/key/table:column] " );


			$answer = trim( fgets( STDIN ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

	/**
	 * Asks if the a piece of meta data contains IDs in the value
	 *
	 * @param array $assoc_args
	 *
	 * @return bool|string
	 */
	public static function meta_serialized_value( $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {

			fwrite( STDOUT, "\n" . "What is the serialized array value ?\n[key|table:column] or [table:column] when no key\n" );


			$answer = trim( fgets( STDIN ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

	/**
	 * Asks for the custom prefix of a plugin's tables
	 *
	 * @param array  $assoc_args
	 *
	 * @return bool|string
	 */
	public static function table_prefix( $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, 'Tables cannot be parsed, but the plugin has custom tables. Do you know the custom prefix?' . "\n" );

			$answer = trim( fgets( STDIN ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

	/**
	 * Asks if we want to overwrite the saved schema property.
	 *
	 * @param array  $assoc_args
	 *
	 * @return bool|string
	 */
	public static function overwrite_property( $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			$question = \WP_CLI::colorize( '%rThis is already defined in the schema, edit it?%n [Y/n]' );
			fwrite( STDOUT, $question . "\n" );

			$answer = strtolower( trim( fgets( STDIN ) ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

}

\WP_CLI::add_command( 'mergebot-schema', 'DeliciousBrains\MergebotSchemaGenerator\Command' );
