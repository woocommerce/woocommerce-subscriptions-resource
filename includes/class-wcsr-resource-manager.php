<?php
/**
 * Manage the process of retrying a failed renewal payment that previously failed.
 *
 * @package		WooCommerce Subscriptions Resource
 * @subpackage	WCSR_Resource_Manager
 * @category	Class
 * @author		Prospress
 * @since		1.0.0
 */

class WCSR_Resource_Manager {

	/**
	 * Attach callbacks and set the retry rules
	 *
	 * @codeCoverageIgnore
	 * @since 2.1
	 */
	public static function init() {

		// Custom action that can be triggered by 3rd parties and/or middleware to create a resource on certain events
		add_action( 'wcsr_create_resource', __CLASS__ . '::create_resource', 10, 4 );

		// Custom action that can be triggered by 3rd parties and/or middleware to activate a resource
		add_action( 'wcsr_activate_resource', __CLASS__ . '::activate_resource', 10, 1 );

		// Custom action that can be triggered by 3rd parties and/or middleware to deactivate a resource
		add_action( 'wcsr_deactivate_resource', __CLASS__ . '::deactivate_resource', 10, 1 );

		// When a renewal payment is due, maybe prorate the line item amounts
		add_filter( 'wcs_renewal_order_created', __CLASS__ . '::maybe_prorate_renewal', 100, 2 );
	}

	/**
	 * Create a new resource with a given status, and linked to a given external object and subscription.
	 *
	 * @param string The status for the resource, either 'active' or 'inactive'.
	 * @param int The ID of the external object to link this resource to.
	 * @param int The ID of a subscription to link this resource to.
	 * @param array Set of optional additional data to customise the resource.
	 * @return null
	 */
	public static function create_resource( $status, $external_id, $subscription_id, $args = array() ) {

		$args = wp_parse_args( $args, array(
			'is_pre_paid'  => true,
			'is_prorated'  => false,
			'date_created' => gmdate( 'U' ),
			)
		);

		$resource_id    = 0;
		$resource_class = self::get_resource_class( $resource_id );
		$resource       = new $resource_class( $resource_id );

		$resource->set_external_id( $external_id );
		$resource->set_subscription_id( $subscription_id );

		$resource->set_is_pre_paid( $args['is_pre_paid'] );
		$resource->set_is_prorated( $args['is_prorated'] );
		$resource->set_date_created( $args['date_created'] );

		// If the resource is being created as an active resource, make sure its creation time is included in the activation timestamps
		if ( 'active' === $status ) {
			$resource->set_activation_timestamps( array( $args['date_created'] ) );
		}

		WCSR_Data_Store::store()->create( $resource );

		do_action( 'wcsr_created_resource', $resource, $status, $external_id, $subscription_id, $args );

		return $resource;
	}

	/**
	 * Activate a resource linked to an external object, specified by ID.
	 *
	 * @param int
	 * @return null
	 */
	public static function activate_resource( $external_id ) {
		if ( $resource_id = WCSR_Data_Store::store()->get_resource_id_by_external_id( $external_id ) ) {
			$resource = self::get_resource( $resource_id );
			$resource->activate();
			$resource->save();
		}
	}

	/**
	 * Deactivate a resource linked to an external object, specified by ID.
	 *
	 * @param int
	 * @return null
	 */
	public static function deactivate_resource( $external_id ) {
		if ( $resource_id = WCSR_Data_Store::store()->get_resource_id_by_external_id( $external_id ) ) {
			$resource = self::get_resource( $resource_id );
			$resource->deactivate();
			$resource->save();
		}
	}

	/**
	 * When a renewal order is created, make sure line items for all resources that are post-paid and prorated reflect
	 * the prorated amounts.
	 *
	 * @param WC_Order
	 * @param WC_Subscription
	 */
	public static function maybe_prorate_renewal( $renewal_order, $subscription ) {

		$is_prorated  = false;
		$resource_ids = WCSR_Data_Store::store()->get_resource_ids_for_subscription( $subscription->get_id(), 'wcsr-unended' );

		if ( ! empty( $resource_ids ) ) {

			// First, get the line items representing the resource so we can figure out things like cost for it
			$line_items = $renewal_order->get_items();

			foreach ( $resource_ids as $resource_id ) {

				$resource = self::get_resource( $resource_id );

				if ( ! empty( $resource ) && false === $resource->get_is_pre_paid() && $resource->get_is_prorated() && $resource->has_been_activated() ) {

					// Calculate prorated payments from paid date to match how Subscriptions determine next payment dates.
					if ( $subscription->get_time( 'last_order_date_paid' ) > 0 ) {
						$from_timestamp = $subscription->get_time( 'last_order_date_paid' );
					} elseif ( $subscription->get_time( 'last_order_date_completed' ) > 0 ) {
						$from_timestamp = $subscription->get_time( 'last_order_date_completed' );
					} else {
						// We can't use last order date created, because that will be the renewal order just created, so go straight to the subscrition start time
						$from_timestamp = $subscription->get_time( 'date_created' );
					}

					$from_timestamp = apply_filters( 'wcsr_renewal_proration_from_timetamp', $from_timestamp, $subscription, $renewal_order, $resource );

					// Now add a prorated line item for each resource based on the resource's usage for this period
					$days_in_period = wcs_estimate_periods_between( $from_timestamp, $renewal_order->get_date_created()->getTimestamp(), 'day', 'floor' );
					$days_active    = $resource->get_days_active( $from_timestamp, $renewal_order->get_date_created()->getTimestamp() );

					foreach ( $line_items as $line_item ) {

						$new_item = new WC_Order_Item_Product();

						wcs_copy_order_item( $line_item, $new_item );

						// Maybe prorate the totals
						if ( $days_active != $days_in_period ) {
							$taxes = $line_item->get_taxes();

							foreach( $taxes as $total_type => $tax_values ) {
								foreach( $tax_values as $tax_id => $tax_value ) {
									$taxes[ $total_type ][ $tax_id ] = $tax_value / $days_in_period * $days_active;
								}
							}

							$new_item->set_props( array(
								'subtotal'     => $line_item->get_subtotal() / $days_in_period * $days_active,
								'total'        => $line_item->get_total() / $days_in_period * $days_active,
								'subtotal_tax' => $line_item->get_subtotal_tax() / $days_in_period * $days_active,
								'total_tax'    => $line_item->get_total_tax() / $days_in_period * $days_active,
								'taxes'        => $taxes,
							) );

							$line_item_name = sprintf( '%s usage for %d of %d days.', $line_item->get_name(), $days_active, $days_in_period );

						} else {

							$line_item_name = $line_item->get_name();

						}

						$line_item_name = apply_filters( 'wcsr_renewal_line_item_name', $line_item_name, $resource, $line_item, $days_active, $days_in_period );

						$new_item->set_name( $line_item_name );

						// Add item to order
						$renewal_order->add_item( $new_item );

						$is_prorated = true;
					}
				}
			}

			if ( $is_prorated ) {

				// Delete the existing items as they've been replaced by the new, prorated items
				foreach ( $line_items as $line_item_id => $line_item ) {
					$renewal_order->remove_item( $line_item_id );
				}

				$renewal_order->calculate_totals(); // also saves the order
			}
		}

		// Allow 3rd party code to perform their own proration or other logic just after we have prorate
		if ( $is_prorated ) {
			$renewal_order = apply_filters( 'wcsr_after_renewal_order_prorated', $renewal_order, $resource_ids );
		}

		return $renewal_order;
	}

	/**
	 * Get an instance of a resource specified by ID
	 *
	 * @param int
	 * @return WCSR_Resource
	 */
	public static function get_resource( $resource_id ) {

		if ( ! $resource_id ) {
			return false;
		}

		$resource_class = self::get_resource_class( $resource_id );

		return new $resource_class( $resource_id );
	}

	/**
	 * Get the class used to instantiate resources or a given resource.
	 *
	 * @param int
	 * @return string
	 */
	protected static function get_resource_class( $resource_id ) {
		return apply_filters( 'wcsr_resource_class', 'WCSR_Resource', $resource_id );
	}
}
WCSR_Resource_Manager::init();
