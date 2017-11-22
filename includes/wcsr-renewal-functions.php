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

/**
 * Returns the new line item for the resource with updated the line item totals if prorating is required
 *
 * @since 1.1.0
 * @param WC_Order_Item_Product $line_item The existing line item on the renewal order
 * @param float $days_active_ratio The ratio of days active to days in the billing period
 * @return WC_Order_Item_Product
 */
function wcsr_get_prorated_line_item( $line_item, $days_active_ratio ) {
	$new_item = new WC_Order_Item_Product();
	wcs_copy_order_item( $line_item, $new_item );

	// If the $days_in_period != $days_active or in other words if the ratio is not 1 is to 1, prorate the line item totals
	if ( $days_active_ratio !== 1 ) {
		$taxes = $line_item->get_taxes();

		foreach( $taxes as $total_type => $tax_values ) {
			foreach( $tax_values as $tax_id => $tax_value ) {
				$taxes[ $total_type ][ $tax_id ] = $tax_value * $days_active_ratio;
			}
		}

		$new_item->set_props( array(
			'subtotal'     => $line_item->get_subtotal() * $days_active_ratio,
			'total'        => $line_item->get_total() * $days_active_ratio,
			'subtotal_tax' => $line_item->get_subtotal_tax() * $days_active_ratio,
			'total_tax'    => $line_item->get_total_tax() * $days_active_ratio,
			'taxes'        => $taxes,
		) );
	}

	return $new_item;
}
