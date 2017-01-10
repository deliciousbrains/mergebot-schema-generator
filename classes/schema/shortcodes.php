<?php
/**
 * The class for shortcodes
 *
 * @since      0.1
 * @package    mergbot-schema-generator
 * @subpackage mergbot-schema-generator/classes/schema
 */

namespace DeliciousBrains\MergebotSchemaGenerator\Schema;

use DeliciousBrains\MergebotSchemaGenerator\Command;
use DeliciousBrains\MergebotSchemaGenerator\Mergebot_Schema_Generator;

class Shortcodes extends Abstract_Element {

	protected static $element = 'Shortcode';
	protected static $colour = 'B';

	protected static function find_elements( Schema $schema ) {
		global $shortcode_tags;

		$shortcodes = array();
		// Shortcodes
		$search = 'add_shortcode';
		foreach ( $schema->files as $file ) {
			$content = strtolower( file_get_contents( $file->getRealPath() ) );
			if ( false === strpos( $content, $search ) ) {
				continue;
			}
			
			foreach ( $shortcode_tags as $tag => $callback ) {
				if ( isset( $schema->shortcodes[ $tag ] ) ) {
					// Shortcode already defined in schema, ignore
					continue;
				}

				if ( ! self::shortcode_search( $content, $tag ) ) {
					continue;
				}

				$body = self::get_callback_code( $file, $callback );

				if ( false === stripos( $body, '$atts[' ) ) {
					// Shortcode callback must use an $atts parameter
					continue;
				}

				$shortcodes[ $tag ] = array( 'body' => $body, 'file' => $file->getRealPath() );
			}
		}

		return $shortcodes;
	}

	public static function ask_elements( $all_shortcodes, $progress_bar ) {
		$shortcodes = array();

		foreach( $all_shortcodes as $tag => $shortcode ) {
			$progress_bar->tick();

			$body = $shortcode['body'];
			Mergebot_Schema_Generator::log( \WP_CLI::colorize(  "\n" . '%B' . 'Shortcode' . ':%n' . '[' . $tag . ']' ) );
			Mergebot_Schema_Generator::log( \WP_CLI::colorize(  "\n" . '%B' . 'File' . ':%n' . $shortcode['file'] ) );
			Mergebot_Schema_Generator::log_code( $body );

			$result = Command::shortcode( $tag );

			if ( 'exit' === $result ) {
				return $shortcodes;
			}

			if ( ! $result ) {
				continue;
			}

			$parameters = array( 'parameters' => array() );

			$attributes = explode( ',', $result );
			foreach ( $attributes as $attribute ) {
				$parts = explode( ':', $attribute );
				$data  = $parts[0];
				if ( isset( $parts[1] ) ) {
					$data = array( 'name' => $parts[1], 'table' => $parts[0] );
				}
				$parameters['parameters'][] = $data;
			}

			$shortcodes[ $tag ] = $parameters;
		}

		return $shortcodes;
	}

	protected static function shortcode_search( $content, $tag ) {
		$compressed_content = str_replace( ' ', '', $content );
		if ( false !== strpos( $compressed_content, "add_shortcode('" . $tag . "')" ) ) {
			return true;
		}

		if ( false !== strpos( $compressed_content, 'add_shortcode("' . $tag . '")' ) ) {
			return true;
		}

		if ( false !== strpos( $compressed_content, "'" . $tag . "'" ) ) {
			return true;
		}

		if ( false !== strpos( $compressed_content, '"' . $tag . '"' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the code of the callback
	 *
	 * @param string $file
	 * @param string $callback
	 *
	 * @return string
	 */
	protected static function get_callback_code( $file, $callback ) {
		if ( is_array( $callback ) ) {
			$func = new \ReflectionMethod( $callback[0], $callback[1] );
		} else if ( false !== strpos( $callback, '::' ) ) {
			$callback_parts = explode( '::', $callback );
			$func           = new \ReflectionMethod( $callback_parts[0], $callback_parts[1] );
		} else {
			$func = new \ReflectionFunction( $callback );
		}

		$start_line = $func->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
		$end_line   = $func->getEndLine();
		$length     = $end_line - $start_line;

		$source = file( $file );
		$body   = implode( "", array_slice( $source, $start_line, $length ) );

		return $body;
	}
}