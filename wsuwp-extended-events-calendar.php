<?php
/*
Plugin Name: WSU Extended Events Calendar
Version: 0.0.2
Plugin URI: https://web.wsu.edu/
Description: Extends and modifies default functionality in The Events Calendar.
Author: washingtonstateuniversity, jeremyfelt
Author URI: https://web.wsu.edu/
*/

class WSU_Extended_Events_Calendar {
	/**
	 * Setup hooks.
	 */
	public function __construct() {
		add_filter( 'json_prepare_post', array( $this, 'prepare_calendar_event' ), 10, 2 );
	}

	/**
	 * Add specific meta information to a calendar item.
	 *
	 * @param array  $_post   Data eventually passed for the post to the API response.
	 * @param array  $post    Current post data.
	 *
	 * @return mixed
	 */
	public function prepare_calendar_event( $_post, $post ) {
		if ( ! isset( $post['post_type'] ) || 'tribe_events' !== $post['post_type'] ) {
			return $_post;
		}

		$_post['meta']['event_city'] = esc_html( tribe_get_city( $post['ID'] ) );
		$_post['meta']['event_state'] = esc_html( tribe_get_state( $post['ID'] ) );
		$_post['meta']['event_venue'] = esc_html( tribe_get_venue( $post['ID'] ) );
		$_post['meta']['start_date'] = esc_html( get_post_meta( $post['ID'], '_EventStartDate', true ) );
		$_post['meta']['end_date'] = esc_html( get_post_meta( $post['ID'], '_EventEndDate', true ) );

		return $_post;
	}
}
new WSU_Extended_Events_Calendar();