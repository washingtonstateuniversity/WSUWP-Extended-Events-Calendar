<?php
/*
Plugin Name: WSU Extended Events Calendar
Version: 0.2.0
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
		add_action( 'admin_init', array( $this, 'remove_events_calendar_actions' ), 9 );
		add_action( 'plugins_loaded', array( $this, 'remove_events_calendar_app_shop' ), 11 );
		add_action( 'init', array( $this, 'add_university_taxonomies' ), 12 );
		add_filter( 'rest_tribe_events_query', array( $this, 'filter_rest_query' ), 10, 1 );
		add_action( 'tribe_settings_do_tabs', array( $this, 'add_title_fields' ), 14 );
		add_filter( 'spine_sub_header_default', array( $this, 'spine_sub_header' ) );
		add_filter( 'tribe_events_show_licenses_tab', '__return_false' );
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

		if ( 'event_organizer' === $field ) {
			return esc_html( tribe_get_organizer( $object['id'] ) );
		}

		if ( 'event_organizer_email' === $field ) {
			return esc_html( tribe_get_organizer_email( $object['id'] ) );
		}

		if ( 'event_organizer_phone' === $field ) {
			return esc_html( tribe_get_organizer_phone( $object['id'] ) );
		}

		if ( 'event_organizer_website' === $field ) {
			return esc_url( tribe_get_organizer_website_url( $object['id'] ) );
		}

		if ( 'event_website' === $field ) {
			return esc_url( tribe_get_event_website_url( $object['id'] ) );
		}

		if ( 'event_cost' === $field ) {
			return esc_html( tribe_get_cost( $object['id'], true ) );
		}

		if ( 'event_excerpt' === $field ) {
			$content = wp_strip_all_tags( $object['content']['rendered'] );
			return $content;
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
		register_rest_field( 'tribe_events', 'event_organizer', $args );
		register_rest_field( 'tribe_events', 'event_organizer_email', $args );
		register_rest_field( 'tribe_events', 'event_organizer_phone', $args );
		register_rest_field( 'tribe_events', 'event_organizer_website', $args );
		register_rest_field( 'tribe_events', 'event_website', $args );
		register_rest_field( 'tribe_events', 'event_cost', $args );
		register_rest_field( 'tribe_events', 'event_excerpt', $args );
		register_rest_field( 'tribe_events', 'start_date', $args );
		register_rest_field( 'tribe_events', 'end_date', $args );
	}

	/**
	 * The Events Calendar Pro offers geolocation for venues. While we'll use that, we don't want
	 * to show a notice on every page of the admin when geopoints need to be generated.
	 */
	public function remove_events_calendar_actions() {
		if ( class_exists( 'Tribe__Events__Pro__Geo_Loc' ) ) {
			$tribe_events =  Tribe__Events__Pro__Geo_Loc::instance();
			remove_action( 'admin_init', array( $tribe_events, 'maybe_generate_geopoints_for_all_venues' ) );
			remove_action( 'admin_init', array( $tribe_events, 'maybe_offer_generate_geopoints' ) );
		}

		if ( class_exists( 'Tribe__Events__Main' ) ) {
			$tribe_events = Tribe__Events__Main::instance();
			remove_action( 'tribe_settings_do_tabs', array( $tribe_events, 'do_addons_api_settings_tab' ) );
		}
	}

	/**
	 * Removes the app shop page from the Events Calendar sub menu.
	 */
	public function remove_events_calendar_app_shop() {
		if ( class_exists( 'Tribe__App_Shop' ) ) {
			$tribe_app_shop = Tribe__App_Shop::instance();
			remove_action( 'admin_menu', array( $tribe_app_shop, 'add_menu_page' ), 100 );
			remove_action( 'wp_before_admin_bar_render', array( $tribe_app_shop, 'add_toolbar_item' ), 20 );
		}
	}

	/**
	 * Add University Taxonomies to The Events Calendar post types.
	 */
	public function add_university_taxonomies() {
		$taxonomies = array( 'wsuwp_university_category', 'wsuwp_university_location', 'wsuwp_university_org' );
		$post_types = array( 'tribe_events', 'tribe_venue', 'tribe_organizer' );

		foreach ( $taxonomies as $taxonomy ) {
			foreach ( $post_types as $post_type ) {
				register_taxonomy_for_object_type( $taxonomy, $post_type );
			}
		}
	}

	/**
	 * Filter the REST API query to remove any default order and orderby arguments. This allows
	 * The Events Calendar to have control over the ordering of the response.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function filter_rest_query( $args ) {
		unset( $args['orderby'] );
		unset( $args['order'] );

		return $args;
	}

	/**
	 * Add Spine Header fields to the General tab on the Events Settings page.
	 */
	public function add_title_fields() {
		$generalTab = array(
			'priority' => 11,
			'fields' => array(
				'wsuwp-spine-theme-headers-open' => array(
					'type' => 'html',
					'html' => '<div class="tribe-settings-form-wrap"><h3>Spine Theme Header</h3>',
				),
				'events-header' => array(
					'type' => 'text',
					'label' => 'All events page header',
					'tooltip' => 'The bottom Spine Header text to display when viewing the All Events page',
					'default' => 'Events',
					'validation_type' => 'html',
				),
				'event-header' => array(
					'type' => 'text',
					'label' => 'Single event header',
					'tooltip' => 'The bottom Spine Header text to display when viewing an individual event. Checking the "Use article title in main header" customizer option will override this.',
					'default' => 'Upcoming Events',
					'validation_type' => 'html',
				),
				'wsuwp-spine-theme-headers-close' => array(
					'type' => 'html',
					'html' => '</div>',
				),
			),
		);
		new Tribe__Settings_Tab( 'general', __('General', 'tribe-events-calendar'), $generalTab);
	}

	/**
	 * Filter bottom Spine Header text.
	 */
	public function spine_sub_header( $sub_header_default ) {

		// The Events Calendar archive.
		if ( is_post_type_archive( 'tribe_events' ) ) {
			// Use  what The Events Calendar's `tribe_get_events_title()` function returns as the default.
			$sub_header_default = tribe_get_events_title();

			// If the events header calendar option has a value, use it as the bottom header text.
			$events_calendar_options = get_option( 'tribe_events_calendar_options' );
			if ( is_array( $events_calendar_options ) && array_key_exists( 'events-header', $events_calendar_options ) && '' !== $events_calendar_options['events-header']  ) {
				$sub_header_default = esc_html( $events_calendar_options['events-header'] );
			}
		}

		// Single events from The Events Calendar.
		if ( is_singular( 'tribe_events' ) ) {
			// Manually set the default.
			$sub_header_default = 'Upcoming Events';

			// If the event header calendar option has a value, use it as the bottom header text.
			$events_calendar_options = get_option( 'tribe_events_calendar_options' );
			if ( is_array( $events_calendar_options ) && array_key_exists( 'event-header', $events_calendar_options ) && '' !== $events_calendar_options['event-header']  ) {
				$sub_header_default = esc_html( $events_calendar_options['event-header'] );
			}

			// For some reason, `get_the_title()` returns empty in this context.
			// `single_post_title()` does the trick, though.
			if ( true === spine_get_option( 'articletitle_header' ) ) {
				$sub_header_default = single_post_title( '', false );
			}
		}

		return $sub_header_default;
	}
}
new WSU_Extended_Events_Calendar();
