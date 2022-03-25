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
				case ( $cache == $optval ) :
					$note = 'NOTE: Cache is loose (==) match';
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

	/**
	 * Compare cache and db value for given option
	 *
	 * ## OPTIONS
	 *
	 * <option-name>
	 * : Option name to compare
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
	 *     $ wp option-cache compare home
	 *     +-----------------------------+-----------------+-----------------------------+-------------------------+---------------+--------------------------+------------------+
	 *     | database value              | should autoload | alloptions cache            | alloptions cache health | options cache | options cache health     | notoptions cache |
	 *     +-----------------------------+-----------------+-----------------------------+-------------------------+---------------+--------------------------+------------------+
	 *     | http://local.wordpress.test | 1               | http://local.wordpress.test | ✅ match                | asdf          | ❓ should not be present | ✅ not present   |
	 *     +-----------------------------+-----------------+-----------------------------+-------------------------+---------------+--------------------------+------------------+
	 *
	 */
	function compare( $args, $assoc_args ) {

		list( $option_name ) = $args;

		global $wpdb;

		$db_row = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$option_name
			)
		);

		if ( empty( $db_row ) || ! isset( $db_row[0] ) ) {
			$db_row = new stdClass();
		} else {
			$db_row = $db_row[0];
		}

		$db_value = ( isset( $db_row->option_value ) ? $db_row->option_value : null );
		$should_autoload = ( isset( $db_row->autoload ) && 'yes' === $db_row->autoload );

		$data = [];

		$data['database value']  = $db_value;
		$data['should autoload'] = $should_autoload;

		$alloptions_cache       = wp_cache_get( 'alloptions', 'options' );
		if ( isset( $alloptions_cache[ $option_name ] ) ) {
			$alloptions_cache_value = $alloptions_cache[ $option_name ];

			$data['alloptions cache']  = $alloptions_cache_value;

			if ( $should_autoload && $db_value === $alloptions_cache_value ) {
				$data['alloptions cache health']  = '✅ match';
			} elseif ( $should_autoload && $db_value !== $alloptions_cache_value ) {
				$data['alloptions cache health']  = '❌ no match';
			} elseif ( ! $should_autoload ) {
				$data['alloptions cache health']  = '❓ should not be present';
			}
		} else {
			$data['alloptions cache']  = 'unset';
			$data['alloptions cache health']  = '✅ cache unset';
		}

		$options_cache       = wp_cache_get( $option_name, 'options' );
		if ( $options_cache ) {
			$data['options cache']  = $options_cache;

			if ( $should_autoload && $db_value === $options_cache_value ) {
				$data['options cache health']  = '❌ no match';
			} elseif ( $should_autoload && $db_value !== $options_cache_value ) {
				$data['options cache health']  = '❓ should not be present';
			} elseif ( ! $should_autoload ) {
				$data['options cache health']  = '✅ match';
			}
		} else {
			$data['options cache']  = 'unset';
			$data['options cache health']  = '✅ cache unset';
		}

		$notoptions_cache       = wp_cache_get( 'notoptions', 'options' ) ?? [];
		$notoptions_cache_value = isset( $notoptions_cache[ $option_name ] ) ?? false;
		if ( ! $notoptions_cache_value ) {
			$data['notoptions cache']  = '✅ not present';
		} elseif ( $db_value && $notoptions_cache_value ) {
			$data['notoptions cache']  = '❌ should not be present';
		} elseif ( is_null( $db_value ) && $notoptions_cache_value ) {
			$data['notoptions cache']  = '✅ present, not in db';
		} else {
			$data['notoptions cache']  = var_export( $notoptions_cache_value, true );
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array_keys( $data ), 'options' );
		$formatter->display_items( [ $data ] );

	}

}
