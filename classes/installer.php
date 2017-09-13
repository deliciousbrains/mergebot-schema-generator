<?php
/**
 * The installer class for the object we are creating the schema for.
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes
 */

namespace DeliciousBrains\MergebotSchemaGenerator;

use WP_CLI;

class Installer {

	/**
	 * @var string
	 */
	protected $slug;

	/**
	 * @var string
	 */
	protected $version;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var bool Mergebot plugin was active, activate later.
	 */
	protected $mergebot_activated = false;

	/**
	 * @var string Slug of Mergebot plugin.
	 */
	protected $mergebot_slug = 'mergebot';

	/**
	 * @var bool Plugin was originally deactivated, deactivate after process.
	 */
	protected $deactivate = false;

	/**
	 * @var bool Plugin was originally not installed, uninstall after process.
	 */
	protected $uninstall = false;

	/**
	 * @var bool Plugin was already installed at a different version
	 */
	protected $rollback;

	/**
	 * @var string Existing version of WordPress core.
	 */
	protected $wp_version;

	/**
	 * Installer constructor.
	 *
	 * @param string $slug
	 * @param string $version
	 * @param string $type
	 */
	public function __construct( $slug, $version, $type ) {
		$this->slug    = $slug;
		$this->version = $version;
		$this->type    = $type;
	}

	/**
	 * Run the installer.
	 *
	 * @return bool
	 */
	public function init() {
		$this->pre_clean();

		if ( false !== $this->needs_installing() ) {
			$this->install();
		}

		$this->post_clean();

		return $this->is_installed();
	}

	/**
	 * Rollback the plugin directories in case the command was aborted previously.
	 */
	protected function pre_clean() {
		$this->disable_mergebot();

		if ( ! file_exists( $this->get_plugin_bk_dir() ) ) {
			return;
		}

		if ( file_exists( $this->get_plugin_dir() ) ) {
			WP_CLI::runcommand( 'plugin uninstall ' . $this->slug . ' --deactivate' );
		}

		rename( $this->get_plugin_bk_dir(), $this->get_plugin_dir() );
	}

	/**
	 * Clean up after install. Remove uninstall file so we don't have issues when bulk generating.
	 */
	protected function post_clean() {
		if ( file_exists( WP_PLUGIN_DIR . '/' . $this->slug . '/uninstall.php' ) ) {
			unlink( WP_PLUGIN_DIR . '/' . $this->slug . '/uninstall.php' );
		}
	}

	protected function disable_mergebot() {
		if ( false === $this->is_plugin_installed( $this->mergebot_slug ) ) {
			// Mergebot plugin not installed
			return;
		}

		if ( false === ( $basename = self::get_plugin_basename( $this->mergebot_slug ) ) ) {
			// Mergebot plugin not activated
			return;
		}

		WP_CLI::runcommand( 'plugin deactivate ' . $this->mergebot_slug );
		$this->mergebot_activated = true;
	}

	/**
	 * Reset anything that the installer does, to keep the WP install in tact after a schema generation.
	 */
	public function clean_up() {
		if ( $this->mergebot_activated ) {
			WP_CLI::runcommand( 'plugin activate ' . $this->mergebot_slug );
		}

		if ( $this->wp_version ) {
			$this->install_wp( $this->wp_version );

			return;
		}

		if ( $this->uninstall ) {
			WP_CLI::runcommand( 'plugin uninstall ' . $this->slug .' --deactivate' );

			return;
		}

		if ( $this->deactivate ) {
			WP_CLI::runcommand( 'plugin deactivate ' . $this->slug );
		}

		if ( $this->rollback && file_exists( $this->get_plugin_bk_dir() ) ) {
			// Rollback to backup
			WP_CLI::runcommand( 'plugin uninstall ' . $this->slug . ' --deactivate' );
			rename( $this->get_plugin_bk_dir(), $this->get_plugin_dir() );
			WP_CLI::success( 'Plugin rolled back.' );
			if ( ! $this->deactivate ) {
				WP_CLI::runcommand( 'plugin activate ' . $this->slug );
			}
		}
	}

	/**
	 * Does the thing need installing, or does it already exist as the correct version.
	 *
	 * @return bool
	 */
	protected function needs_installing() {
		if ( 'wordpress' === $this->type ) {
			return $this->get_installed_core_version() != $this->version;
		}

		if ( false === $this->is_plugin_installed( $this->slug ) ) {
			$this->uninstall = true;

			return true;
		}

		if ( false === ( $basename = self::get_plugin_basename( $this->slug ) ) ) {
			$this->deactivate = true;
			WP_CLI::runcommand( 'plugin activate ' . $this->slug );
			$basename = self::get_plugin_basename( $this->slug );
		}

		$existing_version = self::get_installed_plugin_version( $basename );

		if ( 'latest' === $this->version ) {
			// Premium plugin, installed already
			return false;
		}

		if ( $existing_version === $this->version ) {
			return false;
		}

		$this->rollback = true;

		// Backup existing plugin
		rename( $this->get_plugin_dir(), $this->get_plugin_bk_dir() );

		return true;
	}

	/**
	 * Install the thing we need to create a schema for.
	 */
	protected function install() {
		if ( 'wordpress' === $this->type ) {
			$this->wp_version = $this->get_installed_core_version();

			$this->install_wp( $this->wp_version );

			return;
		}

		// Install plugin version
		$args = '--force';
		if ( 'latest' !== $this->version ) {
			$args .= ' --version=' . $this->version;
		}

		if ( false === $this->deactivate ) {
			// Activate if not already
			$args .= ' --activate';
		}

		WP_CLI::runcommand( 'plugin install ' . $this->slug . ' ' . $args );
	}

	/**
	 * Install WordPress core to a specific version.
	 *
	 * @param string $version
	 */
	protected function install_wp( $version ) {
		$args = '--force --path=' . ABSPATH;

		if ( 'latest' !== $version ) {
			$args .= ' --version=' . $version;
		}

		WP_CLI::runcommand( 'core download ' . $args );
	}

	/**
	 * Is a object installed?
	 *
	 * @return bool
	 */
	protected function is_installed() {
		if ( 'wordpress' === $this->type ) {
			return true;
		}

		return $this->is_plugin_installed( $this->slug );
	}

	/**
	 * Is a plugin installed?
	 *
	 * @param string $slug
	 *
	 * @return bool
	 */
	protected function is_plugin_installed( $slug ) {
		return file_exists( self::dir( $slug ) );
	}

	/**
	 * Get plugin basenmae from slug, but has to be activated.
	 *
	 * @param string $slug
	 *
	 * @return bool
	 */
	public static function get_plugin_basename( $slug ) {
		$active_plugins = get_option( 'active_plugins' );
		$length         = strlen( $slug );
		$basename       = false;
		foreach ( $active_plugins as $active_plugin ) {
			if ( $slug . '/' === substr( $active_plugin, 0, $length + 1 ) ) {
				$basename = $active_plugin;
				break;
			}
		}

		return $basename;
	}

	/**
	 * Get the installed version of WordPress core.
	 *
	 * @return string
	 */
	protected function get_installed_core_version() {
		global $wp_version;

		return $wp_version;
	}

	/**
	 * Get the installed version of a plugin
	 *
	 * @param string $basename
	 *
	 * @return bool|string
	 */
	public static function get_installed_plugin_version( $basename ) {
		$data = get_plugin_data( self::dir( $basename ) );

		if ( ! isset( $data['Version'] ) || empty( $data['Version'] ) ) {
			return false;
		}

		return $data['Version'];
	}

	/**
	 * Get the latest version number of a plugin.
	 *
	 * @param string $slug
	 *
	 * @return string
	 */
	public static function get_latest_plugin_version( $slug ) {
		$url = 'https://api.wordpress.org/plugins/info/1.0/' . $slug . '.json';

		return self::get_object_version( $url );
	}

	public static function get_latest_plugin_data( $slug ) {
		$url = 'https://api.wordpress.org/plugins/info/1.0/' . $slug . '.json';

		return self::get_object( $url );
	}

	/**
	 * Get the latest version number of WordPress core.
	 *
	 * @return string
	 */
	public static function get_latest_core_version() {
		$url = 'https://api.wordpress.org/core/version-check/1.7/';

		$data = self::get_object( $url );
		if ( false === $data ) {
			return $data;
		}

		if ( isset( $data->offers ) ) {
			return $data->offers[0]->version;
		}

		return false;
	}

	/**
	 * Get the version of a thing from an api.wordpress.org url.
	 *
	 * @param string $url
	 *
	 * @return bool|string
	 */
	protected static function get_object_version( $url ) {
		$data = self::get_object( $url );
		if ( false === $data ) {
			return $data;
		}

		if ( empty( $data->version ) ) {
			return false;
		}


		if ( self::is_valid_version( $data->version ) ) {
			// Latest version is a major/minor version
			return $data->version;
		}

		$all_versions = $data->versions;
		unset( $all_versions->trunk );
		$all_versions = get_object_vars( $all_versions );

		$decending = function ( $a, $b ) {
			return -version_compare( $a, $b );

		};

		uksort($all_versions, $decending );

		foreach ( $all_versions as $version => $url ) {
			if ( self::is_valid_version( $version ) ) {
				// Latest version is a major/minor version
				return $version;
			}
		}

		return false;
	}

	/**
	 * Is the version a major or minor version only?
	 *
	 * @param string $version
	 *
	 * @return bool
	 */
	public static function is_valid_version( $version ) {
		$clean_version       = self::normalize_version( $version );
		if ( $clean_version !== $version ) {
			return false;
		}

		$version_parts = explode( '.', $version );
		if ( isset( $version_parts[2] ) && '0' != $version_parts[2] ) {
			return false;
		}

		// Version is a major/minor version
		return true;
	}

	/**
	 * Normalize software versions by removing superfluous characters.
	 *
	 * @param string $version
	 *
	 * @return string|null
	 */
	protected static function normalize_version( $version ) {
		if ( preg_match( '/^\d+.\d+(?:.\d+)?/', $version, $matches ) ) {
			return $matches[0];
		}

		return null;
	}

	protected static function get_object( $url ) {
		$json = file_get_contents( $url );
		if ( empty( $json ) ) {
			return false;
		}

		$data = json_decode( $json );
		if ( is_null( $data ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * Get the plugin directory.
	 *
	 * @return string
	 */
	protected function get_plugin_dir() {
		return self::dir( $this->slug );
	}

	/**
	 * Get a plugin directory.
	 *
	 * @param $slug
	 *
	 * @return string
	 */
	public static function dir( $slug ) {
		return WP_PLUGIN_DIR . '/' . $slug;
	}

	/**
	 * Get the plugin backup directory.
	 *
	 * @return string
	 */
	protected function get_plugin_bk_dir() {
		return self::dir( $this->slug ) . '.msg_bak';
	}
}