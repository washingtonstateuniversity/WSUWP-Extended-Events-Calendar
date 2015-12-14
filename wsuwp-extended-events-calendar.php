<?php
/*
Plugin Name: WSU Extended Events Calendar
Version: 0.1.0
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
		add_action( 'rest_api_init', array( $this, 'register_api_fields' ) );
		add_filter( 'tribe_events_pro_recurrence_batch_size', array( $this, 'limit_recurring_batch_size' ), 10 );
		add_filter( 'tribe_events_register_event_type_args', array( $this, 'register_events_endpoint' ) );
	}

	/**
	 * Set the default size of a batch to process during cron when running through recurring
	 * event queues. The default is 100, which may or may not be the reason things are timing
	 * out at 30 seconds.
	 *
	 * @return int
	 */
	public function limit_recurring_batch_size() {
		return 5;
	}

	/**
	 * Update the `register_post_type()` arguments for The Events Calendar to support an /events/ endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function register_events_endpoint( $args ) {
		$args['show_in_rest'] = true;
		$args['rest_base'] = 'events';

		return $args;
	}

	/**
	 * Retrieve the data to use when returning an event through the REST API.
	 *
	 * @param $object
	 * @param $field
	 * @param $request
	 *
	 * @return string
	 */
	public function get_api_meta_data( $object, $field, $request ) {
		if ( 'event_city' === $field ) {
			return esc_html( tribe_get_city( $object['id'] ) );
		}

		if ( 'event_state' === $field ) {
			return esc_html( tribe_get_state( $object['id'] ) );
		}

		if ( 'event_venue' === $field ) {
			return esc_html( tribe_get_venue( $object['id'] ) );
		}

		if ( 'start_date' === $field ) {
			return esc_html( get_post_meta( $object['id'], '_EventStartDate', true ) );
		}

		if ( 'end_date' === $field ) {
			return esc_html( get_post_meta( $object['id'], '_EventEndDate', true ) );
		}

		return '';
	}

	/**
	 * Register the custom meta fields attached to a REST API response containing event data.
	 *
	 * @since 0.1.0
	 */
	public function register_api_fields() {
		$args = array(
			'get_callback' => array( $this, 'get_api_meta_data' ),
			'update_callback' => 'esc_html',
			'schema' => null,
		);
		register_rest_field( 'tribe_events', 'event_city', $args );
		register_rest_field( 'tribe_events', 'event_state', $args );
		register_rest_field( 'tribe_events', 'event_venue', $args );
		register_rest_field( 'tribe_events', 'start_date', $args );
		register_rest_field( 'tribe_events', 'end_date', $args );
	}
}
new WSU_Extended_Events_Calendar();