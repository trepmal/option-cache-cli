<?php
/**
 * Commands to help diagnose option issues
 *
 */

class Option_Cache extends WP_CLI_Command {


	/**
	 * Check cache values for all options, excluding transients
	 * Defaults to first 1000 options.
	 *
	 * ## OPTIONS
	 *
	 * [--show-all]
	 * : Show all options (still excluding transients)
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
	 *     $ wp option-cache diagnostic
	 *     # example output truncated
	 *     +-------------------+------------+------------------+--------------------+-----------------------------+
	 *     | option_name       | autoloaded | db               | cache              | note                        |
	 *     +-------------------+------------+------------------+--------------------+-----------------------------+
	 *     | siteurl           | yes        | https://test.com | https://test.com   | OK: Cache is match          |
	 *     | home              | yes        | https://test.com | https://test.com   | OK: Cache is match          |
	 *     | blogname          | yes        | Test Blog        | Test Blog          | OK: Cache is match          |
	 *     | testing_notoption | yes        | bacon            | bacon              | 🚨 Found in NOTOPTIONS      |
	 *     | moderation_keys   | no         |                  |                    | OK: Cache is unset          |
	 *     | recently_edited   | no         |                  |                    | OK: Cache is unset          |
	 *     | testing           | no         | somevalue        | somedifferentvalue | 🚨 CACHE MISMATCH           |
	 *     | site_logo         | NOTOPTION  | --               | --                 |                             |
	 *     | testing_notoption | NOTOPTION  | --               | --                 | 🚨 NOTOPTION is real option |
	 *     +-------------------+------------+------------------+--------------------+-----------------------------+
	 *
	 */
	function diagnostic( $args, $assoc_args ) {

		$show_all = WP_CLI\Utils\get_flag_value( $assoc_args, 'show-all', false );

		global $wpdb;

		$limit = $show_all ? 10000 : 1000;

		$options_list = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name NOT LIKE '_transient%' ORDER BY autoload DESC, option_id ASC LIMIT %d",
				$limit
			)
		);

		$total_rows = $wpdb->get_var('SELECT FOUND_ROWS()');

		$output = [];

		$alloptions_cache = wp_cache_get( 'alloptions', 'options' );
		$notoptions_cache = wp_cache_get( 'notoptions', 'options' ) ?? [];

		foreach ( $options_list as $option ) {
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

		if ( is_array( $notoptions_cache ) ) { // may sometimes be 'false'
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
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'option_name', 'autoloaded', 'db', 'cache', 'note' ), 'options' );
		$formatter->display_items( $output );

		if ( $total_rows > $limit ) {
			WP_CLI::warning( sprintf( '%d more options not listed. Use --show-all to retreive beyond the first %d', ($total_rows - $limit), $limit ) );
		}

	}

}
