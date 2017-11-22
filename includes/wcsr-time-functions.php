<?php

/**
 * Resource Teim functions
 *
 * @package		WooCommerce Subscriptions Resource
 * @author		Prospress
 * @since		1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Calculates the exact active days ratio by looking at the subscription billing cycle and the from_timestamp.
 *
 * Example case:
 * For a monthly subscription, the active_days_ratio for a resource with 61 days_active in a 61 day period previously would result in 1.
 * This meant that the cost for 2 month was only 1 * line_item_total. If the subscription's billing period is monthly, the ratio should be 2 so that the cost of the resource for 61 days is 2x the line item total.
 *
 * To help with the calculations, using the from_timestamp allows us to calculate accurate days in the billing periods that are months and years. Without the from_timestamp there's no way to figure out whether a month counted in the days_in_period was 28,29 (feb in leap years),30 or 31 days. Accounting for the off by one or 2 extra days in Feb was not possible
 *
 * Using the total days active and the amount days in the period, we can calculate a ratio between 0 and 1 for days active in the period. Then we need multiple that value by the number of billing periods.
 *
 * @since 1.1.0
 * @param int $from_timestamp
 * @param int $days_in_period
 * @param int $days_active
 * @param string $billing_period
 * @param int    $billing_interval
 * @return float
 */
function wcsr_get_active_days_ratio( $from_timestamp, $days_in_period, $days_active, $billing_period, $billing_interval ) {
	$days_in_billing_cycle = $counter = $number_of_billing_periods = 0;
	$days_left_in_period   = $days_in_period;

	// count the number of days in a billing cycle and the number of billing periods (can be float value i.e. 15 days is 0.5 number of periods of a monthly subscription)
	while ( $days_left_in_period > 0 && $counter < 50 ) {
		$next_timestamp         = wcs_add_time( $billing_interval, $billing_period, $from_timestamp ); // use the same function used to calculate subscription billing periods to work out the exact billing
		$days_in_billing_period = wcsr_get_days_in_period( $from_timestamp, $next_timestamp );
		$days_in_billing_cycle += $days_in_billing_period;

		if ( $days_left_in_period >= $days_in_billing_period ) {
			$number_of_billing_periods++;
		} elseif( $days_left_in_period < $days_in_billing_period ) { // days in period
			$number_of_billing_periods += round( $days_left_in_period / $days_in_billing_period, 2 );
			break;
		} elseif ( $days_left_in_period < $days_in_billing_period ) { // days in period don't even reach the full first billing period
			$number_of_billing_periods += round( $days_left_in_period / $days_in_billing_period, 2 );
			break;
		}

		$counter++;
		$days_left_in_period -= $days_in_billing_period;
		$from_timestamp       = $next_timestamp;
	}

	// if the number of days active is more than or equal to the days in the period - return the exact number of billing periods (remember this can be a float or whole number i.e. 15 day period of a monthly subscription is only 0.5)
	if ( $days_active >= $days_in_period ) {
		$active_days_ratio = $number_of_billing_periods;
	} else {
		// If there are more extra days in this cycle, then either the subscription has been suspended for one or more payments, or the more recent renewal order/s may have failed and the subscription has been manually reactivated, so we need to remove the extra days in the period to account for the extra days to make sure the ratio used to determine daily rates reflects the increased time period.
		$extra_days_in_cycle = $days_in_period - ( $days_in_billing_cycle / $number_of_billing_periods );

		// now that we have the exact amount of days in the billing cycle, we don't need to account for 2 days like the code example given in https://github.com/Prospress/woocommerce-subscriptions-resource/pull/16#issuecomment-344707347
		if ( $extra_days_in_cycle >= 0 ) {
			$days_in_period -= $extra_days_in_cycle;
		}

		$active_days_ratio = round( $days_active / $days_in_period, 2 );
	}

	return $active_days_ratio;
}

/**
 * Calculates the number of whole (i.e. using floor) days between two timestamps.
 * Uses the same logic in @see wcs_estimate_periods_between() but this function exclusively calculates the number of days
 *
 * @since 1.1.0
 * @param int $start_timestamp The starting timestamp to calculate the number of days from
 * @param int $end_timestamp   The end timestamp to calculate the number of days to
 * @return int
 */
function wcsr_get_days_in_period( $start_timestamp, $end_timestamp ) {
	// return 0 if the start timestamp is after the end timestamp
	$periods_until = 0;

	if ( $end_timestamp > $start_timestamp ) {
		$periods_until = floor( ( $end_timestamp - $start_timestamp ) / DAY_IN_SECONDS );
	}

	return $periods_until;
}

/**
 * Find all the timestamps from a given array that fall within a from/to timestamp range.
 *
 * @param array $timestamps_to_check
 * @param int $from_timestamp
 * @param int $to_timestamp
 * @return array
 */
function wcsr_get_timestamps_between( $timestamps_to_check, $from_timestamp, $to_timestamp ) {

	$times = array();

	foreach ( $timestamps_to_check as $i => $timestamp ) {
		if ( $timestamp >= $from_timestamp && $timestamp <= $to_timestamp ) {
			$times[ $i ] = $timestamp;
		}
	}

	return $times;
}


/**
 * Conditional check for whether a timestamp is on the same 24 hour block as another timestamp
 *
 * The catch is the "day" is not typical calendar day - it based on a 24 hour block from the $start_timestamp
 *
 * Uses the $start_timestamp to loop over and add DAY_IN_SECONDS to the time until it reaches the same 24 hour block as the $compare_timestamp
 * This function then checks whether the $current_timestamp and the $compare_timestamp are within the same 24 hour block
 *
 * @param  int  $current_timestamp The current timestamp being checked
 * @param  int  $compare_timestamp The timestamp used to check if the $current_timestamp is on the same 24 hour block
 * @param  int  $start_timestamp  The start timestamp of the period (to calculate when the 24 hour blocks start)
 * @return boolean true on same 24 hour block | false if not
 */
function wcsr_is_on_same_day( $current_timestamp, $compare_timestamp, $start_timestamp ) {
	for ( $end_of_the_day = $start_timestamp; $end_of_the_day <= $compare_timestamp; $end_of_the_day += DAY_IN_SECONDS ) {
		// The loop controls take care of incrementing the end day (3rd expression) until the day after the compare date (2nd expression), but we also want to set the start date so we do that here using the current value of end day (which will be the start day in the final iteration as the 3rd expression in the loop hasn't run yet)
		$start_of_the_day = $end_of_the_day;
	}

	// Compare timestamp to see if it is within our ranges
	if ( ( $current_timestamp >= $start_of_the_day ) && ( $current_timestamp < $end_of_the_day ) ) {
		return true;
	} else {
		return false;
	}
}
