<?php

/**
 * Resource Renewal functions
 *
 * @package		WooCommerce Subscriptions Resource
 * @author		Prospress
 * @since		1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Get the resource line item name for the renewal order
 *
 * @since 1.1.0
 * @param WC_Order_Item_Product $line_item
 * @param int $days_active
 * @param int $days_in_period
 * @param WCSR_Resource $resource (optional)
 * @param int $from_timestamp (optional)
 * @param int $to_timestamp (optional)
 * @return string
 */
function wcsr_get_line_item_name( $line_item, $days_active, $days_in_period, $resource = null, $from_timestamp = 0, $to_timestamp = 0 )  {
	$line_item_name = ( $days_active != $days_in_period ) ? sprintf( '%s usage for %d of %d days.', $line_item->get_name(), $days_active, $days_in_period ) : $line_item->get_name();

	return apply_filters( 'wcsr_renewal_line_item_name', $line_item_name, $line_item, $days_active, $days_in_period, $resource, $from_timestamp, $to_timestamp );
}

