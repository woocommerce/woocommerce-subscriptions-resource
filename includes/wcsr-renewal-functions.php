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

	return apply_filters( 'wcsr_renewal_line_item_name', $line_item_name, $resource, $line_item, $days_active, $days_in_period, $from_timestamp, $to_timestamp );
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

/**
 * Returns the new line item for the resource with updated the line item totals if prorating is required
 *
 * @since 1.1.0
 * @param WC_Order_Item_Product $line_item The existing line item on the renewal order
 * @param int $nb_of_impressions The number of impressions of the resource
 * @param int $resource_id The external resource ID
 * @return WC_Order_Item_Product
 */
function wcsr_get_impressions_line_item( $line_item, $nb_of_impressions, $resource_id ) {

	$impression_product = wcsr_get_product_for_impressions();
	$price_for_one_impression =  apply_filters( 'wcsr_price_for_one_impression', 1, $line_item, $nb_of_impressions, $resource_id );

	if ( $impression_product->get_price() !== $price_for_one_impression ) {
		$impression_product->set_price( $price_for_one_impression );
		$impression_product->set_regular_price( $price_for_one_impression );
		$impression_product->save();
	}

	$name = sprintf( '%s impressions for resource %d.', $nb_of_impressions, $resource_id) ;


	$new_item = new WC_Order_Item_Product();
	$new_item->set_name( $name );
	$new_item->set_product( $impression_product );
	$new_item->set_quantity( $nb_of_impressions );
	$new_item->set_total( $impression_product->get_price() * $nb_of_impressions );
	$new_item->set_subtotal( $impression_product->get_price() * $nb_of_impressions );
	$new_item->apply_changes();
	$new_item->save();
	return $new_item;
}

/**
 * This creates or retrieves the product to create impressions
 * @return mixed
 */
function wcsr_get_product_for_impressions() {
	$product_id = wc_get_product_id_by_sku( 'one_impression' );

	if ( $product_id ) {
		return  new \WC_Product( $product_id );
	}

	$product = new \WC_Product( null );
	$product->set_name( 'Impression' );
	$product->set_status( 'publish' );
	$product->set_downloadable( true );
	$product->set_catalog_visibility( 'hidden' ); // We don't want this to show in the catalog on the frontend.
	$product->set_description( 'Product equivalent to one impression' );
	$product->set_sku( 'one_impression' ); // this is how we ensure the
	$product->set_manage_stock( false );
	$product->set_virtual( true );
	$product->set_price( 1 );       // Price is 1 so we can use the ratio later
	$product->set_regular_price( 1 );
	$product->save();

	return $product;
}
