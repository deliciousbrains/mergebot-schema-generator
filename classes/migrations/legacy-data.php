<?php
namespace DeliciousBrains\MergebotSchemaGenerator\Migrations;

class Legacy_Data {

	/**
	 * Legacy_Data constructor.
	 */
	public function __construct() {
		$this->schema_slug();
		$this->custom_table_prefix();
		$this->relationship_key_translation();
	}

	protected function schema_slug() {
		$legacy_file = dirname( Mergebot_Schema_Generator()->file_path ) . '/data/schema-slug.json';

		if ( ! file_exists( $legacy_file ) ) {
			return;
		}

		$data = $this->get_file_data( $legacy_file );

		if ( empty( $data ) ) {
			return;
		}

		$base_path = dirname( Mergebot_Schema_Generator()->file_path ) . '/data/';

		$total = count( $data );
		$count = 0;
		foreach ( $data as $filename => $slug ) {
			$file              = $base_path . $filename . '.json';
			$file_data         = $this->get_file_data( $file );
			$file_data['slug'] = $slug;
			$this->write_file_data( $file, $file_data );
			$count ++;
		}

		if ( $total == $count ) {
			unlink( $legacy_file );
		}
	}

	protected function custom_table_prefix() {
		$legacy_file = dirname( Mergebot_Schema_Generator()->file_path ) . '/data/table-custom-prefix.json';

		if ( ! file_exists( $legacy_file ) ) {
			return;
		}

		$data = $this->get_file_data( $legacy_file );

		if ( empty( $data ) ) {
			return;
		}

		$base_path = dirname( Mergebot_Schema_Generator()->file_path ) . '/data/';

		$total = count( $data );
		$count = 0;
		foreach ( $data as $filename => $prefix ) {
			$filename = preg_replace('/(-(?:[0-9]+\.?)+)$/', '', $filename);

			$file              = $base_path . $filename . '.json';
			$file_data         = $this->get_file_data( $file );
			$file_data['table_prefix'] = rtrim($prefix, '_' );
			$this->write_file_data( $file, $file_data );
			$count ++;
		}

		if ( $total == $count ) {
			unlink( $legacy_file );
		}
	}

	protected function relationship_key_translation() {
		$legacy_file = dirname( Mergebot_Schema_Generator()->file_path ) . '/data/relationship-key-translation.json';

		if ( ! file_exists( $legacy_file ) ) {
			return;
		}

		$data = $this->get_file_data( $legacy_file );

		if ( empty( $data ) ) {
			return;
		}

		$base_path = dirname( Mergebot_Schema_Generator()->file_path ) . '/data/';

		$total = count( $data );
		$count = 0;
		foreach ( $data as $filename => $mapping ) {
			$filename = preg_replace('/(-(?:[0-9]+\.?)+)$/', '', $filename);

			$file                                          = $base_path . $filename . '.json';
			$file_data                                     = $this->get_file_data( $file );
			$file_data['relationships']['key_translation'] = $mapping;
			$this->write_file_data( $file, $file_data );
			$count ++;
		}

		if ( $total == $count ) {
			unlink( $legacy_file );
		}
	}

	protected function get_file_data( $file ) {
		$contents = file_get_contents( $file );
		if ( empty( $contents ) ) {
			return array();
		}

		return json_decode( $contents, true );
	}

	protected function write_file_data( $filename, $content = array() ) {
		$content = json_encode( $content, JSON_PRETTY_PRINT );

		return file_put_contents( $filename, $content );
	}


}