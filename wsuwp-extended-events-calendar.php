<?php
/*
Plugin Name: WSU Extended Events Calendar
Version: 0.6.1
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
		add_filter( 'tribe_aggregator_should_load', '__return_false' );
		add_filter( 'tribe_events_pro_recurrence_batch_size', array( $this, 'limit_recurring_batch_size' ), 10 );
		add_filter( 'tribe_events_pro_recurrence_processor_interval', array( $this, 'set_recurrence_cron_interval' ) );
		add_filter( 'tribe_events_register_event_type_args', array( $this, 'register_events_endpoint' ) );
		add_action( 'admin_init', array( $this, 'remove_events_calendar_actions' ), 9 );
		add_action( 'init', array( $this, 'add_university_taxonomies' ), 12 );
		add_filter( 'rest_tribe_events_query', array( $this, 'filter_rest_query' ), 10, 1 );
		add_action( 'tribe_settings_tab_fields', array( $this, 'add_title_fields' ), 10, 2 );
		add_filter( 'spine_sub_header_default', array( $this, 'spine_sub_header' ) );
		add_filter( 'tribe_events_show_licenses_tab', '__return_false' );
		add_filter( 'Tribe__Events__Pro__Recurrence_Meta_getRecurrenceMeta', array( $this, 'fix_missing_exclusions' ) );
		add_action( 'tribe_settings_tab_fields', array( $this, 'add_custom_community_settings' ), 10, 2 );
		add_action( 'tribe_community_events_form_errors', array( $this, 'community_events_submission_details' ) );
		add_filter( 'tribe_get_option', array( $this, 'filter_tribe_options' ), 10, 2 );

		// Don't load the Tribe App Shop.
		add_action( 'plugins_loaded', array( $this, 'remove_events_calendar_app_shop' ), 11 );
		add_filter( 'tribe_asset_pre_register', array( $this, 'filter_tribe_asset' ) );
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
	 * Sets the interval for processing recurrence crons at "twicedaily" instead of
	 * every 30 minutes.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function set_recurrence_cron_interval() {
		return 'twicedaily';
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
			$tribe_events = Tribe__Events__Pro__Geo_Loc::instance();
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
	 * Filter the events REST API query before it fires.
	 *
	 * Remove default order and orderby arguments. This allows The Events Calendar
	 * to have control over the ordering of the response.
	 *
	 * When `tribe_event_display` is passed as `past`, configure a query that
	 * pulls past events.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function filter_rest_query( $args ) {
		unset( $args['orderby'] );
		unset( $args['order'] );

		if ( isset( $_REQUEST['tribe_event_display'] ) && 'past' === $_REQUEST['tribe_event_display'] ) { // WPCS: CSRF Ok.

			// These are both required to trick The Events Calendar into a past events query.
			$args['tribe_is_past'] = true;
			$args['eventDisplay'] = 'past';

			// The Events Calendar uses whatever date is passed as the end date for past
			// events, so we choose yesterday.
			$args['eventDate'] = date( 'Y-m-d 23:59:59', strtotime( 'yesterday' ) );
		}

		return $args;
	}

	/**
	 * Add Spine Header fields to the General tab on the Events Settings page.
	 *
	 * @param  array  $settings Existing array of The Events Calendar settings fields.
	 * @param  string $id       The tab ID.
	 *
	 * @return array
	 */
	public function add_title_fields( $settings, $id ) {
		if ( 'general' === $id ) {
			$settings = Tribe__Main::array_insert_after_key(
				'tribe_events_timezones_show_zone',
				$settings,
				array(
					'wsuwp-spine-theme-headers-open' => array(
						'type' => 'html',
						'html' => '<h3>Spine Theme Header</h3>',
					),
					'events-spine-header' => array(
						'type' => 'text',
						'label' => 'All events page header',
						'tooltip' => 'The bottom Spine Header text to display when viewing the All Events page',
						'default' => 'Events',
						'validation_type' => 'html',
					),
					'event-spine-header' => array(
						'type' => 'text',
						'label' => 'Single event header',
						'tooltip' => 'The bottom Spine Header text to display when viewing an individual event. Checking the "Use article title in main header" customizer option will override this.',
						'default' => 'Upcoming Events',
						'validation_type' => 'html',
					),
				)
			);
		}

		return $settings;
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
			if ( is_array( $events_calendar_options ) && array_key_exists( 'events-header', $events_calendar_options ) && '' !== $events_calendar_options['events-header'] ) {
				$sub_header_default = esc_html( $events_calendar_options['events-header'] );
			}
		}

		// Single events from The Events Calendar.
		if ( is_singular( 'tribe_events' ) ) {
			// Manually set the default.
			$sub_header_default = 'Upcoming Events';

			// If the event header calendar option has a value, use it as the bottom header text.
			$events_calendar_options = get_option( 'tribe_events_calendar_options' );
			if ( is_array( $events_calendar_options ) && array_key_exists( 'event-header', $events_calendar_options ) && '' !== $events_calendar_options['event-header'] ) {
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

	/**
	 * Fixes a PHP Warning when the `exclusions` key is missing from recurrence meta.
	 *
	 * It seems that it was at one time possible for recurrence meta to be stored for
	 * an event without exclusions data attached. Now, the code in Events Calendar Pro
	 * that expects this exclusions data will generate a PHP Warning if it is not there.
	 *
	 * We've tried opening a support ticket with Modern Tribe, but the issue has not
	 * been resolved upstream. This should be a good enough workaround.
	 *
	 * @param array $recurrence_meta
	 *
	 * @return array
	 */
	public function fix_missing_exclusions( $recurrence_meta ) {
		if ( ! isset( $recurrence_meta['exclusions'] ) ) {
			$recurrence_meta['exclusions'] = array();
		}

		return $recurrence_meta;
	}

	/**
	 * Add custom fields to the Community tab on the Events Settings page.
	 *
	 * @param  array  $settings Existing array of The Events Calendar settings fields.
	 * @param  string $id       The tab ID.
	 *
	 * @return array
	 */
	public function add_custom_community_settings( $settings, $id ) {
		if ( class_exists( 'Tribe__Events__Community__Main' ) && 'community' === $id ) {
			$settings = Tribe__Main::array_insert_after_key(
				'single_geography_mode',
				$settings,
				array(
					'wsuwp-community-submission-open' => array(
						'type' => 'html',
						'html' => '<h3>Successful Submission Message</h3>',
					),
					'review-message' => array(
						'type' => 'textarea',
						'label' => 'Custom message',
						'tooltip' => 'Additional text to display when an event has been successfully submitted.',
						'default' => false,
						'validation_type' => 'html',
					),
					'review-details' => array(
						'type' => 'checkbox_bool',
						'label' => 'Include event details',
						'tooltip' => 'Display the details of a successfully submitted event.',
						'default' => false,
						'validation_type' => 'boolean',
					),
				)
			);
		}

		return $settings;
	}

	/**
	 * Force some events options to a specific value.
	 *
	 * @since 0.6.1
	 *
	 * @param string|bool $value
	 * @param string      $key
	 *
	 * @return bool
	 */
	public function filter_tribe_options( $value, $key ) {

		// If this is true, a MySQL error is generated at times.
		// See https://theeventscalendar.com/support/forums/topic/invalid-sql-query-with-rest-api-and-recurring-events/
		if ( 'hideSubsequentRecurrencesDefault' === $key ) {
			return false;
		}

		// Don't allow a great number of past recurring events.
		if ( 'recurrenceMaxMonthsBefore' === $key && 12 < $value ) {
			return 12;
		}

		// Only allow recurring events to be created 12 months ahead.
		if ( 'recurrenceMaxMonthsAfter' === $key && 12 < $value ) {
			return 12;
		}

		return $value;
	}

	/**
	 * Display details with the event submitted/updated message.
	 *
	 * @param array $errors Existing error messages.
	 *
	 * @return mixed
	 */
	public function community_events_submission_details( $errors ) {
		$options = get_option( 'tribe_events_calendar_options' );

		if ( ! is_array( $options ) ) {
			return $errors;
		}

		$custom_message = ( array_key_exists( 'review-message', $options ) && '' !== $options['review-message'] ) ? $options['review-message'] : false;
		$event_details = ( array_key_exists( 'review-details', $options ) && true === $options['review-details'] ) ? true : false;

		if ( ! $custom_message && ! $event_details && ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'ecp_event_submission' ) ) ) {
			return $errors;
		}

		if ( is_array( $errors ) && 'update' === $errors[0]['type'] ) {

			// Retrieve the ID of the submitted event.
			$event = get_page_by_title( sanitize_text_field( $_POST['post_title'] ), OBJECT, 'tribe_events' );
			$event_id = $event->ID;

			// Split the existing message - we'll put our message between default paragraphs.
			$existing_message = explode( '</p>', $errors[0]['message'] );

			// Remove the blank value at the end of the array.
			array_pop( $existing_message );

			// Store the paragraph with the "Submit another event" link for later.
			$submit_another = array_pop( $existing_message );

			// First portion of the original message.
			$details = implode( '</p>', $existing_message ) . '</p>';

			// Custom message.
			if ( $custom_message ) {
				$details .= wp_kses_post( $custom_message );
			}

			// Event details.
			if ( $event_details ) {
				$details .= '<div class="event-details">';
				$details .= '<div>Event Title:</div><p>' . get_the_title( $event_id ) . '</p>';
				$details .= '<div>Event Description:</div>' . wpautop( get_post( $event_id )->post_content );
				$details .= tribe_get_event_categories( $event_id );

				// Event image.
				if ( tribe_event_featured_image( $event_id ) ) {
					$details .= '<div>Event Image:</div>' . tribe_event_featured_image( $event_id );
				}

				// Event details - date/time information.
				if ( tribe_get_start_date( $event_id ) ) {
					$details .= '<div>Event Time &amp; Date:</div><p>' . tribe_get_start_date( $event_id ) . ' ';

					if ( tribe_event_is_all_day( $event_id ) ) {
						$details .= '- all day event';
					} else {
						$details .= 'to ';
						if ( tribe_get_start_date( $event_id, false ) !== tribe_get_end_date( $event_id, false ) ) {
							$details .= tribe_get_end_date( $event_id );
						} else {
							$details .= tribe_get_end_time( $event_id );
						}
					}

					$details .= '</p>';

					// Recurrence info.
					if ( class_exists( 'Tribe__Events__Pro__Main' ) ) {
						if ( tribe_is_recurring_event( $event_id ) ) {
							$details .= '<div>Event reccurence:</div><ul class="event-recurrence">';

							foreach ( tribe_get_recurrence_start_dates( $event_id ) as $recurrence ) {
								$details .= '<li>' . $recurrence . '</li>';
							}

							$details .= '</ul>';
						}
						if ( tribe_get_recurrence_text( $event_id ) ) {
							$details .= '<p>' . tribe_get_recurrence_text( $event_id ) . '</p>';
						}
					}
				}

				// Event details - venue information.
				if ( tribe_get_venue_id( $event_id ) ) {
					$details .= '<div>Venue Details:</div><p>' . implode( '<br />', tribe_get_venue_details( $event_id ) ) . '</p>';
				}

				// Event details - organizer information.
				if ( tribe_get_organizer_ids( $event_id ) ) {
					foreach ( tribe_get_organizer_ids( $event_id ) as $organizer_id ) {
						// `tribe_get_organizer_details()` doesn't seem to work as expected,
						// so build the individual pieces out manually.
						if ( tribe_get_organizer( $organizer_id ) ) {
							$details .= '<div>Organizer Details:</div><p>' . tribe_get_organizer( $organizer_id );

							if ( tribe_get_organizer_phone( $organizer_id ) ) {
								$details .= '<br />' . tribe_get_organizer_phone( $organizer_id );
							}

							if ( tribe_get_organizer_website_url( $organizer_id ) ) {
								$details .= '<br />' . esc_url( tribe_get_organizer_website_url( $organizer_id ) );
							}

							if ( tribe_get_organizer_email( $organizer_id ) ) {
								$details .= '<br />' . esc_html( tribe_get_organizer_website_link( $organizer_id ) );
							}

							$details .= '</p>';
						}
					}
				}

				// Event details - website.
				if ( tribe_get_event_website_url( $event_id ) ) {
					$details .= '<div>Event Website:</div><p>' . tribe_get_event_website_url( $event_id ) . '</p>';
				}

				// Event details - cost.
				if ( tribe_get_formatted_cost( $event_id ) ) {
					$details .= '<div>Event Cost:</div><p>' . tribe_get_formatted_cost( $event_id ) . '</p>';
				}

				$details .= '</div>';
			}

			// Append the rest of the existing message.
			$details .= $submit_another . '</p>';

			$errors[0] = array(
				'type' => $errors[0]['type'],
				'message' => $details,
			);
		}

		return $errors;
	}

	/**
	 * Ensure that Tribe App Shop assets are never enqueued by attaching `__return_false` as
	 * the conditional.
	 *
	 * @since 0.5.1
	 *
	 * @param object $asset The asset currently being registered.
	 *
	 * @return object The modified asset.
	 */
	public function filter_tribe_asset( $asset ) {
		if ( 'tribe-app-shop-css' === $asset->slug || 'tribe-app-shop-js' === $asset->slug ) {
			$asset->conditionals = array( '__return_false' );
		}

		return $asset;
	}
}
new WSU_Extended_Events_Calendar();
