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
		'status'                  => '',
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
	 * Update the resource's status
	 *
	 * @since 1.0.0
	 */
	public function get_status( $context = 'view' ) {
		$status = $this->get_prop( 'status', $context );

		if ( empty( $status ) && 'view' === $context ) {
			// In view context, return the default status if no status has been set.
			$status = apply_filters( 'wcsr_default_resource_status', 'wcsr-unended' );
		}

		return $status;
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

		if ( false === $this->has_been_activated() ) {
			return 0;
		}

		if ( is_null( $to_timestamp ) ) {
			$to_timestamp = gmdate( 'U' );
		}

		// Find all the activation and deactivation timestamps between the given timestamps
		$activation_times   = self::get_timestamps_between( $this->get_activation_timestamps(), $from_timestamp, $to_timestamp );
		$deactivation_times = self::get_timestamps_between( $this->get_deactivation_timestamps(), $from_timestamp, $to_timestamp );

		// Now find the number of days between the timestamps where the resource was active (the resource must be inactive for more than 24 hours to be considered inactive for a given day)
		$days_active = floor( ( $to_timestamp - $from_timestamp ) / DAY_IN_SECONDS );

		// First remove any gap between the $from_timestamp and the resources creation date
		if ( $this->get_date_created()->getTimestamp() > $from_timestamp ) {
			$days_active -= floor( ( $this->get_date_created()->getTimestamp() - $from_timestamp ) / DAY_IN_SECONDS );
		}

		foreach ( $activation_times as $i => $activation_time ) {
			if ( isset( $deactivation_times[ $i ] ) ) {
				$days_active -= floor( ( $activation_time - $deactivation_times[ $i ] ) / DAY_IN_SECONDS );
			}
		}

		return $days_active;
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

	/**
	 * Set resource status.
	 *
	 * @since 1.0.0
	 * @param string $new_status Status to change the resource to. Either 'wcsr-unended' or 'wcsr-ended'.
	 * @return array details of change
	 */
	public function set_status( $new_status ) {
		$old_status = $this->get_status();

		// If setting the status, ensure it's set to a valid status.
		if ( true === $this->object_read ) {
			// Only allow valid new status
			if ( ! in_array( $new_status, wcsr_get_valid_statuses() ) && 'trash' !== $new_status ) {
				$new_status = 'wcsr-unended';
			}

			// If the old status is set but unknown (e.g. draft) assume its pending for action usage.
			if ( $old_status && ! in_array( $old_status, wcsr_get_valid_statuses() ) && 'trash' !== $old_status ) {
				$old_status = 'wcsr-unended';
			}
		}

		$this->set_prop( 'status', $new_status );

		return array(
			'from' => $old_status,
			'to'   => $new_status,
		);
	}
}
