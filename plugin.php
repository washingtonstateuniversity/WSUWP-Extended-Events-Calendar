<?php
/*
Plugin Name: WSUWP Extended Events Calendar
Version: 0.5.0
Plugin URI: https://web.wsu.edu/
Description: Extends and modifies default functionality in The Events Calendar.
Author: washingtonstateuniversity, jeremyfelt
Author URI: https://web.wsu.edu/
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'TRIBE_HIDE_UPSELL' ) ) {
	define( 'TRIBE_HIDE_UPSELL', true );
}

if ( ! defined( 'TRIBE_DISABLE_PUE' ) ) {
	define( 'TRIBE_DISABLE_PUE', true );
}

// This plugin uses namespaces and requires PHP 5.3 or greater.
if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
	add_action( 'admin_notices', create_function( '',
		"echo '<div class=\"error\"><p>" . __( 'WSUWP Extended Events Calendar requires PHP 5.3 to function properly. Please upgrade PHP or deactivate the plugin.', 'wsuwp-extended-events-calendar' ) . "</p></div>';" ) );
	return;
} else {
	include_once __DIR__ . '/includes/class-wsuwp-extended-events-calendar.php';
	include_once __DIR__ . '/includes/extended-events-calendar.php';
}

