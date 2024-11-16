<?php

$foo = function( $args, $assoc_args ) {
	list( $new_value ) = $args;

	global $wpdb;
	var_dump( $wpdb->get_results( $wpdb->prepare( "update wp_options set option_value = %s where option_name = 'home'", $new_value ) ) );
};
WP_CLI::add_command( 'home', $foo );

