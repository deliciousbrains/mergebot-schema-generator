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

	protected static $skip = false;

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
	 * [--load-admin]
	 * : Load admin hooks for plugin, eg. for table installing
	 *
	 * [--skip]
	 * : Don't ask any questions, ie. load existing schema as is, or create bare miniumum
	 *
	 * [--all]
	 * : Regenerate all core schemas
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

		if ( isset( $assoc_args['skip'] )  ) {
			self::$skip = true;
		}

		mergebot_schema_generator()->set_wp_data();

		if ( isset( $assoc_args['plugin'] ) && 'all' === $assoc_args['plugin'] ) {
			// Regenerate all plugin existing schemas.
			return $this->generate_all();
		}

		if ( ! isset( $assoc_args['plugin'] ) && isset( $assoc_args['all'] ) ) {
			// Regenerate all existing core schemas.
			return $this->generate_all( false );
		}

		$this->generate_one( $assoc_args );
	}

	protected function generate_all( $plugin = true ) {
		if ($plugin) {
			$existing_schemas = Generator::get_generated_plugins();
		} else {
			$existing_schemas = Generator::get_generated_core();
		}

		foreach ( $existing_schemas as $schema ) {
			$slug = Generator::get_slug_from_filename( $schema );
			if ( empty( $slug ) ) {
				$slug = Command::slug( $schema );
			}

			if ( empty( $slug ) ) {
				\WP_CLI::error( 'No slug for ' . $schema );
			}

			$version = Generator::get_version_from_filename( $schema );
			$args    = array( 'version' => $version );
			if ( $plugin ) {
				$args['plugin'] = $slug;
			}
			$this->generate_one( $args );
		}
	}

	protected function generate_one( $assoc_args ) {
		$slug    = $this->get_slug( $assoc_args );
		$version = $this->get_version( $assoc_args, $slug );

		$create_from_scratch = isset( $assoc_args['scratch'] );

		// Handle installing the thing we want to generate a schema for.
		$installer = new Installer( $slug, $version, $this->type );
		$result = $installer->init();

		if ( ! $result ) {
			\WP_CLI::error( sprintf( '%s not installed', $slug ) );
		}

		if ( isset( $assoc_args['load-admin'] ) ) {
			// Bootstrap admin hooks where plugins might install tables
			do_action( 'admin_init' );
			do_action( 'admin_menu' );
		}

		if ( 'plugin' === $this->type && 'latest' === $version ) {
			// Get installed version if we don't know it.
			$version = Installer::get_installed_plugin_version( Installer::get_plugin_basename( $slug ) );
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
			if ( ! Installer::is_valid_version( $assoc_args['version'] ) ) {
				\WP_CLI::error( 'Only major or minor versions of schemas please.' );
			}

			return $assoc_args['version'];
		}

		if ( 'wordpress' === $this->type ) {
			return Installer::get_latest_core_version();
		}

		$latest_version = Installer::get_latest_plugin_version( $slug );
		if ( false === $latest_version ) {
			// Premium plugin, use plugin header version
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
	 * @param array  $attributes
	 * @param array  $assoc_args
	 *
	 * @return bool|string
	 */
	public static function shortcode( $tag, $attributes, $assoc_args = array() ) {
		if ( self::$skip ) {
			return 'exit';
		}

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			$tag = \WP_CLI::colorize( '%B[' . $tag . ']%n' );


			$atts = \WP_CLI::colorize( '%B' . implode( ' ', $attributes ) . '%n' );

			fwrite( STDOUT, 'Does the shortcode ' . $tag . " contain ID attributes? {$atts}\n[attributes/n] Comma separated list of attributes. [table]:[attribute] format for non-post tables\n" );

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
		if ( self::$skip ) {
			return 'exit';
		}

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {

			fwrite( STDOUT, "Does the meta contain ID data? [Y/n] " );

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
			fwrite( STDOUT, 'Tables cannot be parsed, but the plugin has custom tables. Do you know the custom prefix? [prefix/n]' . "\n" );

			$answer = trim( fgets( STDIN ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

	/**
	 * Asks for plugin info
	 *
	 * @param string $key
	 * @param array  $assoc_args
	 *
	 * @return bool|string
	 */
	public static function info( $key = 'name', $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, 'Enter plugin ' . $key . ": " );

			$answer = trim( fgets( STDIN ) );

			return $answer;
		}
	}

	/**
	 * Asks for plugin info
	 *
	 * @param string $schema
	 * @param array  $assoc_args
	 *
	 * @return bool|string
	 */
	public static function slug( $schema, $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, 'Enter slug for schema file ' . $schema . ": " );

			$answer = trim( fgets( STDIN ) );

			return $answer;
		}
	}

	/**
	 * Ask if we should keep an element that exists in schema but no longer in plugin/core
	 *
	 * @param string $object
	 * @param string $version
	 * @param string $type
	 * @param string $key
	 * @param array  $assoc_args
	 *
	 * @return string
	 */
	public static function keep_element( $object, $version, $type, $key, $assoc_args = array() ) {
		if ( self::$skip ) {
			return 'y';
		}

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			$type = \WP_CLI::colorize( '%B' . $type . '%n' );
			$key  = \WP_CLI::colorize( '%G' . $key . '%n' );
			fwrite( STDOUT, "$type: $key exists in the schema but no longer exists in $object v$version, keep it? [Y/n]" );

			$answer = strtolower( trim( fgets( STDIN ) ) );

			if ( 'n' == $answer ) {
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
		if ( self::$skip ) {
			return 'exit';
		}

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
