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

	protected static function find_all_shortcode_registrations( Schema $schema ) {
		$shortcodes = array();

		// Shortcodes
		$search = 'add_shortcode';
		foreach ( $schema->files as $file ) {
			$content = strtolower( file_get_contents( $file->getRealPath() ) );
			preg_match_all( '/(?<!function)\s+' . $search . '\s*\(\s*(.*)(?=\)\s*\;)/', $content, $shortcode_matches );
			if ( empty( $shortcode_matches ) || empty( $shortcode_matches[0] ) ) {
				continue;
			}

			foreach ( $shortcode_matches[1] as $shortcode_args ) {
				$shortcode_args = self::get_function_args_from_string( $shortcode_args );
				$shortcode_args = array_map( function ( $arg ) {
					$arg = ltrim( $arg, '\'"' );
					$arg = rtrim( $arg, '\'"' );

					return $arg;
				}, $shortcode_args );

				if ( false !== strpos( $shortcode_args[0], '$' ) || false !== strpos( $shortcode_args[0], '::' ) ) {
					// Shortcode tag is dynamic, rely on it being in wp global.
					continue;
				}

				$tag      = $shortcode_args[0];
				$callback = $shortcode_args[1];

				if ( false !== strpos( $callback, 'array' ) ) {
					// Convert array string callback to actual array of class instance and method.
					$method     = self::get_method_from_array_string( $callback );
					$class = self::get_class_for_method( $schema->files, $content, $method );

					$callback_data             = new \stdClass();
					$callback_data->method     = $method;
					$callback_data->class_name = $class['name'];
					$callback_data->file       = $file->getRealPath();
					if ( isset( $class['file'] ) ) {
						$callback_data->file = $class['file'];
					}

					$callback = $callback_data;
				}

				// $method = self::get_method_from_callback( $callback );
				$shortcodes[$tag] = $callback;
			}
		}

		return $shortcodes;
	}

	public static function find_elements( Schema $schema ) {
		$shortcodes = array();
		$shortcodes_registered = self::find_all_shortcode_registrations( $schema );

		// Merge with all regsitered shortcodes available in WP global.
		global $shortcode_tags;
		$shortcodes_registered = array_merge( $shortcode_tags, $shortcodes_registered );

		foreach ( $schema->files as $file ) {
			$content = strtolower( file_get_contents( $file->getRealPath() ) );

			foreach ( $shortcodes_registered as $registered_tag => $registered_callback ) {
				$method = self::get_method_from_callback( $registered_callback );
				preg_match_all( '/function\s+' . $method . '\s*\(\s*(.*)(?=\)\s*[\;|\{])/i', $content, $shortcode_matches );
				if ( empty( $shortcode_matches ) || empty( $shortcode_matches[1][0] ) ) {
					continue;
				}

				$code = self::get_callback_code( $file, $registered_callback );

				$callback_args  = self::get_function_args_from_string( $shortcode_matches[1][0] );
				$attribute_name = explode( ' ', $callback_args[0] );
				$attribute_name = str_replace( '$', '\$', $attribute_name[0] );

				preg_match_all( '/(extract\s*\()|' . $attribute_name . '\[\s*(.*?)\s*\]/', $code['body'], $matches );
				if ( ! $matches || ! isset( $matches[1] ) || empty( $matches[1] ) ) {
					// Shortcode callback must use an $atts parameter or use the extract() method
					continue;
				}

				$attributes = array();
				if ( 'extract(' !== str_replace( ' ', '', $matches[1][0] ) ) {
					$attributes = array_unique( $matches[0] );
				}

				$shortcodes[ $registered_tag ] = array(
					'body'       => $code['body'],
					'line'       => isset( $code['line'] ) ? $code['line'] : '',
					'file'       => $file->getRealPath(),
					'attributes' => $attributes,
				);
			}
		}


		return $shortcodes;
	}

	public static function ask_elements( Schema $schema, $all_shortcodes, $progress_bar ) {
		$shortcodes = array();

		$exit = false;
		foreach( $all_shortcodes as $tag => $shortcode ) {
			$progress_bar->tick();

			Mergebot_Schema_Generator::log( \WP_CLI::colorize(  "\n" . '%B' . 'Shortcode' . ':%n' . '[' . $tag . ']' ) );
			if ( false === $schema->from_scratch && isset( $schema->shortcodes[ $tag ] ) ) {
				// Shortcode already defined in schema, ask to overwrite
				$result = true;
				if ( ! $exit ) {
					$result = Command::overwrite_property();
				}

				if ( $exit || false === $result || 'exit' === $result ) {
					$shortcodes[ $tag ] = $schema->shortcodes[ $tag ];

					if ( ! $exit && 'exit' === $result ) {
						$exit = true;
					}

					continue;
				}
			}

			if ( $exit ) {
				continue;
			}

			$body = $shortcode['body'];
			Mergebot_Schema_Generator::log( 'Line: ' . $shortcode['line'] . ' - ' . $shortcode['file'] );
			Mergebot_Schema_Generator::log_code( $body );

			$result = Command::shortcode( $tag, $shortcode['attributes'] );

			if ( 'exit' === $result ) {
				$exit = true;
				continue;
			}

			if ( ! $result ) {
				continue;
			}

			$parameters = array( 'parameters' => array() );

			$attributes = explode( ',', $result );
			foreach ( $attributes as $attribute ) {
				$attribute = trim( $attribute );
				if ( empty( $attribute ) ) {
					continue;
				}
				$parts     = explode( ':', $attribute );
				$data      = $parts[0];
				if ( isset( $parts[1] ) ) {
					$data = array( 'name' => $parts[1], 'table' => $parts[0] );
				}
				$parameters['parameters'][] = $data;
			}

			$shortcodes[ $tag ] = $parameters;
		}

		return $shortcodes;
	}

	/**
	 * Get the code of the callback
	 *
	 * @param string $file
	 * @param string $callback
	 *
	 * @return array
	 */
	protected static function get_callback_code( $file, $callback ) {
		if ( is_array( $callback ) ) {
			$func = new \ReflectionMethod( $callback[0], $callback[1] );
		} else if ( is_object( $callback ) ) {
			$body = self::get_method_code_from_file( $callback->file, $callback->class_name, $callback->method );

			return array(
				'body' => $body,
			);
		} else if ( false !== strpos( $callback, '::' ) ) {
			$callback_parts = explode( '::', $callback );
			$func           = new \ReflectionMethod( $callback_parts[0], $callback_parts[1] );
		} else {
			if ( ! function_exists( $callback) ) {
				$body = self::get_function_code_from_file( $file->getRealPath(), $callback );

				return array(
					'body' => $body,
				);
			}
			$func = new \ReflectionFunction( $callback );
		}

		$start_line = $func->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
		$end_line   = $func->getEndLine();
		$length     = $end_line - $start_line;

		$source = file( $file );
		$body   = implode( "", array_slice( $source, $start_line, $length ) );

		return array(
			'body' => $body,
			'line' => $start_line
		);
	}

	/**
	 * Get method name from a callback
	 *
	 * @param string $callback
	 *
	 * @return string
	 */
	protected static function get_method_from_callback( $callback ) {
		if ( is_array( $callback ) ) {
			return $callback[1];
		}

		if ( is_object( $callback ) ) {
			return $callback->method;
		}

		if ( false !== strpos( $callback, '::' ) ) {
			$parts = explode( '::', $callback );

			return $parts[1];
		}

		return $callback;
	}

	protected static function get_method_from_array_string( $string ) {
		preg_match_all( '/array\s*\(\s*(.*)\)/', $string, $matches );
		if ( $matches && isset( $matches[1] ) && ! empty( $matches[1] ) ) {
			$parts = explode( ',', $matches[1][0] );

			return trim( str_replace( array( '\'', '"' ), '', $parts[1] ) );
		}

		return false;
	}
}