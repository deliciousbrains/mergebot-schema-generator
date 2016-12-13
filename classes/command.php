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
		$slug    = $this->get_slug( $assoc_args );
		$version = $this->get_version( $assoc_args, $slug );

		$create_from_scratch = isset( $assoc_args['scratch'] );

		// Handle installing the thing we want to generate a schema for.
		$installer = new Installer( $slug, $version, $this->type );
		$installer->init();

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
	public static function shortcode( $tag, $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, 'Does [' . $tag . "] contain ID attributes? [attributes/n] " );

			$answer = trim( fgets( STDIN ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}

	/**
	 * Asks for ID related data stored in key/value meta data.
	 * Comma separated list of attributes. [table]:[attribute] format for non-post tables
	 * eg. id,ids
	 * eg. comments:id,comments:ids
	 * n
	 *
	 * @param string $entity
	 * @param string $key
	 * @param string $value
	 * @param array  $assoc_args
	 *
	 * @return bool|string
	 */
	public static function meta( $entity, $key, $value, $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, 'Does the ' . $entity . ' with key: ' . $key . ' and value: ' . $value . " contain ID attributes? [attributes/n] " );

			$answer = trim( fgets( STDIN ) );

			if ( 'n' == $answer || empty( $answer ) ) {
				return false;
			}

			return $answer;
		}
	}
}

\WP_CLI::add_command( 'mergebot-schema', 'DeliciousBrains\MergebotSchemaGenerator\Command' );
