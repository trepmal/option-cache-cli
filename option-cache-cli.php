<?php

if ( ! defined( 'WP_CLI' ) ) return;

require_once __DIR__ . '/inc/class.option-cache.php';

WP_CLI::add_command( 'option-cache', 'Option_Cache' );
