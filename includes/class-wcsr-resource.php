<?php
/**
 * Resource Class
 *
 * Used to instantiate a resource.
 *
 * @package		WooCommerce Subscriptions Resource
 * @subpackage	WCSR_Resource
 * @category	Class
 * @author		Prospress
 * @since		1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCSR_Resource extends WC_Data {

	/**
	 * Resource Data array.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $data = array(
		'date_created'            => null,
		'external_id'             => 0,
		'subscription_id'         => 0,
		'is_pre_paid'             => true,
		'is_prorated'             => false,
		'activation_timestamps'   => array(),
		'deactivation_timestamps' => array(),
	);

	/**
	 * Get an instance for a given resource
	 *
	 * @return null
	 */
	public function __construct( $resource ) {
		parent::__construct( $resource );

		if ( is_numeric( $resource ) && $resource > 0 ) {
			$this->set_id( $resource );
		} elseif ( $resource instanceof self ) {
			$this->set_id( $resource->get_id() );
		} elseif ( ! empty( $resource->ID ) ) {
			$this->set_id( $resource->ID );
		} else {
			$this->set_object_read( true );
		}

		$this->data_store = WCSR_Data_Store::store();

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Check whether the resource is paid for before or after each billing period where the benefit of the resource has been consumed.
	 *
	 * By default, subscriptions with WooCommerce Subscriptions are always paid in advance; however, a resource can be paid after its benefit has
	 * been consumed, like a Slack account. Amoung other reasons why this might be used, it allows for proration of the resource's cost to account
	 * only for those days where it is actually used.
	 *
	 * By default, this flag applies to both the initial period in which the sign-up occurs, as well as successive billing periods.
	 *
	 * For example, consider a resource charged at $7 / week. If a customer is granted her initial access to the resource on the 4th day of the
	 * billing schedule, if $is_post_pay is:
	 * - true, the customer will be charged $3 at the time of sign-up to account for the remaining 3 days during the billing cycle.
	 * - false, the customer will be charged nothing at the time of sign-up, and will then be charged $3 at the time of the next scheduled payment
	 *   to account for the 3 days the resource was used during the billing cycle.
	 *
	 * @return bool
	 */
	public function get_is_pre_paid( $context = 'view' ) {
		return $this->get_prop( 'is_pre_paid', $context );
	}

	/**
	 * Check whether the resource's cost is prorated to the daily rate of its usage during each billing period.
	 *
	 * By default, subscriptions with WooCommerce Subscriptions are always paid in advance in full; however, a resource can be paid after its benefit
	 * has been consumed by setting the WCS_Resource::$is_pre_paid flag to false. Because this charges for the resource retrospectively, it allows
	 * for proration of the resource's cost to account only for those days where it is actually used (or at least, active).
	 *
	 * @return bool
	 */
	public function get_is_prorated( $context = 'view' ) {
		return $this->get_prop( 'is_prorated', $context );
	}

	/**
	 * Record the resource's activation
	 */
	public function activate() {

		$activation_timestamps   = $this->get_activation_timestamps();
		$activation_timestamps[] = gmdate( 'U' );

		$this->set_activation_timestamps( $activation_timestamps );
	}

	/**
	 * Record the resource's deactivation
	 */
	public function deactivate() {

		$deactivation_timestamps   = $this->get_deactivation_timestamps();
		$deactivation_timestamps[] = gmdate( 'U' );

		$this->set_deactivation_timestamps( $deactivation_timestamps );
	}

	/**
	 * Get date_created.
	 *
	 * @param string $context
	 * @return WC_DateTime|NULL object if the date is set or null if there is no date.
	 */
	public function get_date_created( $context = 'view' ) {
		return $this->get_prop( 'date_created', $context );
	}

	/**
	 * The ID for the subscription this resource is linked to.
	 *
	 * @param string $context
	 * @return int
	 */
	public function get_subscription_id( $context = 'view' ) {
		return $this->get_prop( 'subscription_id', $context );
	}

	/**
	 * Get ID for the object outside Subscriptions this resource is linked to.
	 *
	 * @param string $context
	 * @return int
	 */
	public function get_external_id( $context = 'view' ) {
		return $this->get_prop( 'external_id', $context );
	}

	/**
	 * Get an array of timestamps on which this resource was activated
	 *
	 * @param string $context
	 * @return array
	 */
	public function get_activation_timestamps( $context = 'view' ) {
		return $this->get_prop( 'activation_timestamps', $context );
	}

	/**
	 * Get an array of timestamps on which this resource was deactivated
	 *
	 * @param string $context
	 * @return array
	 */
	public function get_deactivation_timestamps( $context = 'view' ) {
		return $this->get_prop( 'deactivation_timestamps', $context );
	}

	/**
	 * Determine the number of days between two timestamps where this resource was active
	 *
	 * We don't use DateTime::diff() here to avoid gotchas like https://stackoverflow.com/questions/2040560/finding-the-number-of-days-between-two-dates#comment36236581_16177475
	 *
	 * @param int $from_timestamp
	 * @param int $to_timestamp
	 * @return int
	 */
	public function get_days_active( $from_timestamp, $to_timestamp = null ) {
		$days_active = 0;

		if ( false === $this->has_been_activated() ) {
			return $days_active;
		}

		if ( is_null( $to_timestamp ) ) {
			$to_timestamp = gmdate( 'U' );
		}

		// Find all the activation and deactivation timestamps between the given timestamps
		$activation_times   = self::get_timestamps_between( $this->get_activation_timestamps(), $from_timestamp, $to_timestamp );
		$deactivation_times = self::get_timestamps_between( $this->get_deactivation_timestamps(), $from_timestamp, $to_timestamp );

		// if the first activation date is after the first deactivation date, make sure we append the start timestamps to act as the first "activated" date for the resource
		if ( ! isset( $activation_times[0] ) || ( isset( $deactivation_times[0] ) && $activation_times[0] > $deactivation_times[0] ) ) {
			$start_timestamp = ( $this->get_date_created()->getTimestamp() > $from_timestamp ) ? $this->get_date_created()->getTimestamp() : $from_timestamp;
			array_unshift( $activation_times, $start_timestamp );
		}

		foreach ( $activation_times as $i => $activation_time ) {
			// If there is corresponding deactivation timestamp, the resouce has deactivated before the end of the period so that's the time we want, otherwise, use the end of the period as the resource was still active at end of the period
			$deactivation_time = isset( $deactivation_times[ $i ] ) ? $deactivation_times[ $i ] : $to_timestamp;

			// skip over any days that are activated/deactivated on the same day and have already been accounted for
			if ( $i !== 0 && self::is_on_same_day( $deactivation_time, $deactivation_times[ $i - 1 ], $from_timestamp ) ) {
				continue;
			}

			// Calculate days based on time between
			$days_by_time = intval( ceil( ( $deactivation_time - $activation_time ) / DAY_IN_SECONDS ) );

			// Increase our tally
			$days_active += $days_by_time;

			// If days based on time is only 1 but it was "across a day" we may need to adjust IF NOT accounted for already
			if ( $days_by_time == 1 && ! self::is_on_same_day( $activation_time, $deactivation_time, $from_timestamp ) ) {

				// handle situation if first activation crosses a day
				if ( $i == 0 && ! self::is_on_same_day( $activation_time, $deactivation_time, $from_timestamp ) ) {
					$days_active += 1;
				}

				// if this activation didn't start on the same day as previous activation it is safe to add an extra day
				if ( $i !== 0 && ! self::is_on_same_day( $activation_time, $deactivation_times[ $i - 1 ], $from_timestamp ) ) {
					$days_active += 1;
				}
			}
		}

		return $days_active;
	}

	/**
	 * Prototype/demo conditional check for whether a timestamp is on the same "day" as another timestamp
	 *
	 * The catch is the "day" is not typical calendar day - it based on a 24 hour period from initial activation
	 *
	 * Initial activation could be the actual time it was first activated of the start of the period.
	 *
	 * Takes the activation/starting timestamp and gets the time our days start from.
	 * Takes the timestamp we are checking if we are on the same day as and gets the date, previous days date and time
	 * Works out whether the current day started on the same date as the one we are comparing with or a day earlier and works makes a time stampe fo when the day starts
	 * Works out when the days ends
	 * Check if our timestamp is with the start and end dates we have determined
	 *
	 * @param  int  $current_timestamp    [description]
	 * @param  int  $compare_timestamp [description]
	 * @param  int  $start_timestamp  [description]
	 * @return boolean true on same day | false if not
	 */
	public static function is_on_same_day( $current_timestamp, $compare_timestamp, $start_timestamp ) {

		$start_time = gmdate( 'H:i:s', $start_timestamp );
		$start_hour = gmdate( 'H', $start_timestamp );
		$start_min  = gmdate( 'i', $start_timestamp );
		$start_sec  = gmdate( 's', $start_timestamp );

		$compare_day  = gmdate( 'Y-m-d', $compare_timestamp );
		$compare_day_previous  = gmdate( 'Y-m-d', strtotime( '-1 day', $compare_timestamp ) );
		$compare_hour = gmdate( 'H', $compare_timestamp );
		$compare_min  = gmdate( 'i', $compare_timestamp );
		$compare_sec  = gmdate( 's', $compare_timestamp );

		$start_of_the_day_date_time = $compare_day . ' ' . $start_time;
		$start_of_the_day = strtotime( $start_of_the_day_date_time );

		// Adjust for H:M:S being less
		if ( $compare_hour < $start_hour || ( ( $compare_hour == $start_hour ) && ( $compare_min < $start_min ) ) || ( ( $compare_hour == $start_hour ) && ( $compare_min == $start_min ) && ( $compare_sec < $start_sec ) ) ) {
			$start_of_the_day_date_time = $compare_day_previous . ' ' . $start_time;
			$start_of_the_day = strtotime( $start_of_the_day_date_time );
		}

		$end_of_the_day = strtotime( '+1 day', $start_of_the_day );

		// Compare timestamp to see if it is within our ranges
		if ( ( $current_timestamp >= $start_of_the_day ) && ( $current_timestamp < $end_of_the_day ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Find all the timestamps from a given array that fall within a from/to timestamp range.
	 *
	 * @param array $timestamps_to_check
	 * @param int $from_timestamp
	 * @param int $to_timestamp
	 * @return array
	 */
	protected static function get_timestamps_between( $timestamps_to_check, $from_timestamp, $to_timestamp ) {

		$times = array();

		foreach ( $timestamps_to_check as $i => $timestamp ) {
			if ( $timestamp >= $from_timestamp && $timestamp <= $to_timestamp ) {
				$times[ $i ] = $timestamp;
			}
		}

		return $times;
	}

	/**
	 * Determine if the resource has ever been activated by checking whether it has at least one activation timestamp
	 *
	 * @return bool
	 */
	public function has_been_activated() {

		$activation_timestamps = $this->get_activation_timestamps();

		return empty( $activation_timestamps ) ? false : true;
	}

	/**
	 * Setters
	 */

	/**
	 * The ID of the object in the external system (i.e. system outside Subscriptions) this resource is linked to.
	 *
	 * @param  string|integer|null $date UTC timestamp, or ISO 8601 DateTime. If the DateTime string has no timezone or offset, WordPress site timezone will be assumed. Null if there is no date.
	 * @throws WC_Data_Exception
	 */
	public function set_date_created( $date ) {
		$this->set_date_prop( 'date_created', $date );
	}

	/**
	 * The ID of the object in the external system (i.e. system outside Subscriptions) this resource is linked to.
	 *
	 * @param int|string
	 */
	public function set_external_id( $external_id ) {
		$this->set_prop( 'external_id', $external_id );
	}

	/**
	 * The ID of the subscription this resource is linked to.
	 *
	 * @param int
	 */
	public function set_subscription_id( $subscription_id ) {
		$this->set_prop( 'subscription_id', $subscription_id );
	}

	/**
	 * Set whether the resource is paid for before or after each billing period.
	 *
	 * @param bool
	 */
	public function set_is_pre_paid( $is_pre_paid ) {
		$this->set_prop( 'is_pre_paid', (bool) $is_pre_paid );
	}

	/**
	 * Set whether the resource's cost is prorated to the daily rate of its usage during each billing period.
	 *
	 * @param bool
	 */
	public function set_is_prorated( $is_prorated ) {
		$this->set_prop( 'is_prorated', (bool) $is_prorated );
	}

	/**
	 * Set the array of timestamps to record all occasions when this resource was activated
	 *
	 * @param array $timestamps
	 */
	public function set_activation_timestamps( $timestamps ) {
		$this->set_prop( 'activation_timestamps', $timestamps );
	}

	/**
	 * Set the array of timestamps to record all occasions when this resource was deactivated
	 *
	 * @param array $timestamps
	 */
	public function set_deactivation_timestamps( $timestamps ) {
		$this->set_prop( 'deactivation_timestamps', $timestamps );
	}
}
