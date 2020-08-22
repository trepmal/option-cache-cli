<?php
/**
 * Commands to help diagnose option issues
 *
 */

class Option_Cache extends WP_CLI_Command {


	/**
	 * Check cache values for all options, excluding transients
	 *
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Format to use for the output. One of table, csv or json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp option-cache diagnostic
	 *
	 */
	function diagnostic( $args, $assoc_args ) {

		global $wpdb;

		$autoloaded_options_list = $wpdb->get_results(
			"SELECT SQL_CALC_FOUND_ROWS option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name NOT LIKE '_transient%' ORDER BY autoload DESC, option_id ASC LIMIT 500",
		);

		if ( $wpdb->num_rows > 500 ) {
			WP_CLI::warning( 'More than 500 autoloaded options' );
		}

		$output = [];

		$alloptions_cache = wp_cache_get( 'alloptions', 'options' );
		$notoptions_cache = wp_cache_get( 'notoptions', 'options' ) ?? [];

		foreach ( $autoloaded_options_list as $option ) {
			$optnam = $option->option_name;
			$optval = $option->option_value;

			if ( 'yes' === $option->autoload ) {
				$cache  = $alloptions_cache[ $optnam ] ?? false;
			} else {
				$cache  = wp_cache_get( $optnam, 'options' );
			}

			$match = ( false === $cache || $optval === $cache );

			switch ( true ) {
				case ( isset( $notoptions_cache[ $optnam ] ) ) : // first, because it can still be found in cache
					$note = '🚨 Found in NOTOPTIONS';
				break;
				case ( false === $cache ) :
					$note = 'OK: Cache is unset';
				break;
				case ( $cache === $optval ) :
					$note = 'OK: Cache is match';
				break;
				default :
					$note = '🚨 CACHE MISMATCH';
			}

			$output[ $optnam ] = [
				'option_name' => $optnam,
				'autoloaded'  => $option->autoload,
				'db'          => ( $match ? substr( $optval, 0, 200 ) : $optval ),
				'cache'       => ( $match ? substr( $cache, 0, 200 ) : $cache ),
				'note'        => $note,
			];

		}

		foreach( $notoptions_cache as $name => $val ) {
			$note = '';
			if ( isset( $output[ $name ] ) ) {
				$note = '🚨 NOTOPTION is real option';
			}
			$output[ 'NOTOPTION-' . $name ] = [
				'option_name' => $name,
				'autoloaded'  => 'NOTOPTION',
				'db'          => '--',
				'cache'       => '--',
				'note'        => $note,
			];
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'option_name', 'autoloaded', 'db', 'cache', 'note' ), 'options' );
		$formatter->display_items( $output );

	}

}
