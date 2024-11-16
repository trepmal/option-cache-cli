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
	 * [--per-page=<per-page>]
	 * : Number of options from database to list. 'notoptions' are not counted
	 * and are always displayed. Default: 1000
	 *
	 * [--page=<page>]
	 * : Page of results
	 *
	 * [--hide-notoptions]
	 * : Hide notoptions output. Default: Hidden after page 2
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

		$limit = absint( WP_CLI\Utils\get_flag_value( $assoc_args, 'per-page', 1000 ) );
		$page  = absint( WP_CLI\Utils\get_flag_value( $assoc_args, 'page', 1 ) );
		$page_for_math = $page - 1;
		$hide_notoptions= WP_CLI\Utils\get_flag_value( $assoc_args, 'hide-notoptions', ( $page > 1 ) );

		global $wpdb;

		$offset = $limit * $page_for_math;

		$options_list = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name NOT LIKE '_transient%' ORDER BY autoload DESC, option_id ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		$total_rows = $wpdb->get_var('SELECT FOUND_ROWS()');

		$output = [];

		$alloptions_cache = wp_cache_get( 'alloptions', 'options' );
		$notoptions_cache = wp_cache_get( 'notoptions', 'options' ) ?? [];

		foreach ( $options_list as $option ) {
			$optnam = $option->option_name;
			$optval = $option->option_value;
			$should_autoload = in_array( $option->autoload, wp_autoload_values_to_autoload() );

			$a_cache = $alloptions_cache[ $optnam ] ?? false;  // Alloptions
			$i_cache = wp_cache_get( $optnam, 'options' );     // Independent
			$e_cache = $should_autoload ? $a_cache : $i_cache; // Expected
			$u_cache = $should_autoload ? $i_cache : $a_cache; // Unexpected

			$match = ( false === $e_cache || $optval === $e_cache );

			switch ( true ) {
				case ( isset( $notoptions_cache[ $optnam ] ) ) : // first, because it can still be found in cache
					$note = '🚨 Found in NOTOPTIONS';
				break;
				case ( false === $e_cache ) :
					$note = 'OK: Cache is unset';
				break;
				case ( $e_cache === $optval ) :
					$note = 'OK: Cache is match';
				break;
				case ( $e_cache == $optval ) :
					$note = 'NOTE: Cache is loose (==) match';
				break;
				case ( is_float( $e_cache ) && (string) $e_cache == $optval ) :
					$note = 'NOTE: Cache is loose (==) match';
				break;
				default :
					$note = '🚨 CACHE MISMATCH';
			}

			if ( false !== $u_cache ) {
				$note = '🚨 WRONG CACHE';
			}

			$a_cache = $a_cache === '' ? '*empty*' : $a_cache;
			$i_cache = $i_cache === '' ? '*empty*' : $i_cache;

			$output[ $optnam ] = [
				'option_name' => $optnam,
				'autoload'    => $option->autoload,
				'db'          => $this->maybe_truncate_value( $optval ),
				'alloptions_cache' => $this->maybe_truncate_value( $a_cache ),
				'options_cache'    => $this->maybe_truncate_value( $i_cache ),
				'note'        => $note,
			];

		}

		if ( ! $hide_notoptions && is_array( $notoptions_cache ) ) { // may sometimes be 'false'
			foreach( $notoptions_cache as $name => $val ) {
				$note = '';
				if ( isset( $output[ $name ] ) ) {
					$note = '🚨 NOTOPTION is real option';
				}
				$output[ 'NOTOPTION-' . $name ] = [
					'option_name' => $name,
					'autoload'    => 'NOTOPTION',
					'db'          => '--',
					'alloptions_cache' => '--',
					'options_cache'    => '--',
					'note'        => $note,
				];
			}
		}

		$format = $assoc_args['format'];
		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'option_name', 'autoload', 'db', 'alloptions_cache', 'options_cache', 'note' ), 'options' );
		$formatter->display_items( $output );

		if (
			// don't show footer for strict formats (csv, json...)
			$format == 'table' &&
			$total_rows > ( $offset + $limit )
		) {
			WP_CLI::line( sprintf(
				'Page %d/%d database options shown. Use `--per-page=%d --page=%d` for next set.',
				$page,
				ceil( $total_rows / $limit ),
				$limit,
				$page + 1
			) );
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
			$in_db = false;
			$db_row = new stdClass();
		} else {
			$in_db = true;
			$db_row = $db_row[0];
		}

		$db_value = ( isset( $db_row->option_value ) ? $db_row->option_value : null );
		$should_autoload = ( isset( $db_row->autoload ) && in_array( $db_row->autoload, wp_autoload_values_to_autoload() ) );

		$data = [];

		$data['database value']  = $in_db ? $db_value : 'not in db';
		$data['should autoload'] = $should_autoload;

		$alloptions_cache       = wp_cache_get( 'alloptions', 'options' );
		$alloptions_cache_value = $alloptions_cache[ $option_name ] ?? false;
		if ( $alloptions_cache_value ) {
			$data['alloptions cache']  = $alloptions_cache_value;

			if ( ! $in_db && $alloptions_cache_value ) {
				// not in db && in cache = orphaned
				$data['alloptions cache health']  = '❌ should not be present';
			} elseif ( ! $should_autoload && $alloptions_cache_value ) {
				// not in db && in cache = bad cache
				$data['alloptions cache health']  = '❌ should not be present';
			} elseif ( $should_autoload && $db_value === $alloptions_cache_value ) {
				$data['alloptions cache health']  = '✅ match';
			} elseif ( $should_autoload && $db_value == $alloptions_cache_value ) {
				$data['alloptions cache health']  = '✅ match (loose)';
			} elseif ( $should_autoload && (string) $db_value == $alloptions_cache_value ) {
				$data['alloptions cache health']  = '✅ match (loose)';
			} else {
				$data['alloptions cache health']  = '❌ mismatch';
			}
		} else {
			$data['alloptions cache']  = 'unset';
			$data['alloptions cache health']  = '✅ cache unset';
		}

		$options_cache_value = wp_cache_get( $option_name, 'options' );
		if ( $options_cache_value ) {
			$data['options cache']  = $options_cache_value;

			if ( ! $in_db && $options_cache_value ) {
				// not in db && in cache = orphaned
				$data['options cache health']  = '❌ should not be present';
			} elseif ( $should_autoload && $options_cache_value ) {
				// should auto && in opt cache = bad cache
				$data['options cache health']  = '❌ should not be present';
			} elseif ( ! $should_autoload && $db_value === $options_cache_value ) {
				$data['options cache health']  = '✅ match';
			} elseif ( ! $should_autoload && $db_value == $options_cache_value ) {
				$data['options cache health']  = '✅ match (loose)';
			} elseif ( ! $should_autoload && (string) $db_value == $options_cache_value ) {
				$data['options cache health']  = '✅ match (loose)';
			} else {
				$data['options cache health']  = '❌ mismatch';
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

	private function maybe_truncate_value( $value, $max=200 ) {

		if ( strlen( $value ) > 200 ) {
			return substr( $value, 0, 200 ) . ' ...[truncated]';
		}
		return $value;
	}
}
