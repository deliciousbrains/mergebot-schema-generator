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
	 *     wp mergebot schema generate --plugin=woocommerce
	 *     wp mergebot schema generate --plugin=woocommerce --version=2.5.5
	 *     wp mergebot schema generate --plugin=woocommerce --version=2.5.5 --scratch
	 *
	 */
	public function generate( $args, $assoc_args ) {
		$type = 'plugin';
		if ( ! isset( $assoc_args['plugin'] ) ) {
			$type = $slug = 'wordpress';
		} else {
			$slug = $assoc_args['plugin'];
		}

		if ( isset( $assoc_args['version'] ) ) {
			$version = $assoc_args['version'];
		} else {
			if ( 'wordpress' === $type ) {
				global $wp_version;
				$version = $wp_version;
			} else {
				$version = $this->get_plugin_version_from_slug( $slug );
			}
		}

		if ( ! $version ) {
			$version = 'latest';
		}

		$create_from_scratch = isset( $assoc_args['scratch'] );

		$generator = new Generator( $slug, $version, $type );
		$generator->generate( $create_from_scratch );

		\WP_CLI::success( ucfirst( $slug ) . ' schema generated!' );
	}

	protected function get_plugin_version_from_slug( $slug ) {
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $slug ) ) {
			return false;
		}

		$active_plugins = get_option( 'active_plugins' );
		$length         = strlen( $slug );
		$basename       = false;
		foreach ( $active_plugins as $active_plugin ) {
			if ( $slug . '/' === substr( $active_plugin, 0, $length + 1 ) ) {
				$basename = $active_plugin;
				break;
			}
		}

		if ( $basename ) {
			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $basename );

			return $data['Version'];
		}

		return false;
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
	 * @param string   $entity
	 * @param string   $key
	 * @param string   $value
	 * @param array    $assoc_args
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
