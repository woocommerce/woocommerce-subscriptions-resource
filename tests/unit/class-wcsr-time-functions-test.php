<?php
/**
 * Test the WCSR_Resource class's get active days ratio methods
 */
class WCSR_Time_Functions_Test extends WCSR_Unit_TestCase {

	public function provider_get_active_days_ratio() {
		return array(
			// standard 30 day month
			0 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 30,
				'days_active'      => 30,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 1
			),

			// days in period was 61 day, but the billing period is only 1 month (i.e. the last renewal failed, but the subscription was manually activated)
			1 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 61,
				'days_active'      => 61,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 2
			),

			// Resource was active for 45 days in 61 days (i.e. the last renewal failed, but the subscription was manually activated)
			2 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 61,
				'days_active'      => 45,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 1.48
			),

			// if the last payment failed for a monthly subscription and was manually reactivated - resource was active for a total of 45 out of the 61 days
			3 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 61,
				'days_active'      => 45,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 1.48
			),

			// if the last payment failed for a monthly subscription and was manually reactivated - resource remained active for the entire time
			4 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 61,
				'days_active'      => 61,
				'billing_period'   => 'month',
				'billing_interval' => 2,
				'expected_ratio'  => 1
			),

			// this tests the case where a subscription was cancelled mid way through the month or if the subscription was renewed manually early (for whatever reason)
			5 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 15,
				'days_active'      => 15,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0.5
			),

			// Months July/August both have 31 days.. so there's a possibility that the total days in period could be 62
			6 => array(
				'from_timestamp'   => '2017-07-14 14:21:40',
				'days_in_period'   => 62,
				'days_active'      => 62,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 2
			),

			// Same test as above but this time the billing cycle of the subscription is every 2 months
			7 => array(
				'from_timestamp'   => '2017-07-14 14:21:40',
				'days_in_period'   => 62,
				'days_active'      => 62,
				'billing_period'   => 'month',
				'billing_interval' => 2,
				'expected_ratio'  => 1
			),

			// test a monthly subscription renewing across Feb in a non-leap year (28 days)
			8 => array(
				'from_timestamp'   => '2017-02-01 14:21:40',
				'days_in_period'   => 28,
				'days_active'      => 28,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 1
			),

			9 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 61,
				'days_active'      => 1,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0.03
			),

			// simple test for 20 active days out of the full month in September
			10 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 30,
				'days_active'      => 20,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0.67
			),

			11 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 61,
				'days_active'      => 40,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 1.31
			),

			// if the subscription was active for all of Sept and then deactiveated it for October they should pay $9.00 for Sept, so the ratio should be 1
			12 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 61,
				'days_active'      => 30,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0.98 // i feel like this should be 1, not .98. i.e. the 30 active days could've been all of September so that should be $9.00 they get charged.. not $8.82
			),

			// If somehow the days active is 31 (this calculastion uses ceil) but only 30 days in the period (floor is used in this calculation), make sure we return 1
			13 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 30,
				'days_active'      => 31,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 1
			),

			// if the monthly subscription renews but only has 15 days active
			14 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 30,
				'days_active'      => 15,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0.5
			),

			// 31 days in the period from August (full month)
			15 => array(
				'from_timestamp'   => '2017-08-14 14:21:40',
				'days_in_period'   => 31,
				'days_active'      => 31,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 1
			),

			// 31 days in the period from September (full month + 1 day (i.e. maybe the renewal was a day late))
			16 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 31,
				'days_active'      => 31,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 1.03 // the renewal came late so they should be charged 1 month + 1 day.
			),

			// 0 case - no days in the period and 0 days active (only feasible scenario i can see this ever happening is if the renewal was manually triggered and days in period ends up being less than 1 day (i.e. floor is used so it would be 0), but because we use CEIL when calculating the active days that value could be 1)
			17 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 0,
				'days_active'      => 1,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0
			),

			// 0 case
			17 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 0,
				'days_active'      => 0,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0
			),

			// no active days in the period
			18 => array(
				'from_timestamp'   => '2017-09-14 14:21:40',
				'days_in_period'   => 30,
				'days_active'      => 0,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0 // the renewal came late so they should be charged 1 month + 1 day.
			),

			// monthly subscription, 17 day in period, 15 days active
			19 => array(
				'from_timestamp'   => '2017-11-11 10:24:40',
				'days_in_period'   => 17,
				'days_active'      => 15,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0.5 // For Jasons recent renewal and prior the latest changes, this value was returning 0.88 as the days active ratio and the result from the line item 0.88 * $9.00 = $7.92
			),

			// a simple test to confirm the logic for minusing off the extra days can be replaced by: ( $days_active / $days_in_period ) * $number_of_billing_periods
			20 => array(
				'from_timestamp'   => '2017-09-14 10:24:40',
				'days_in_period'   => 170, // just short of 6 months - extra days would've been ~140
				'days_active'      => 30,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'expected_ratio'  => 0.99
			),
		);
	}

	/**
	 * Make sure get_days_in_period() is calculating the number of days properly
	 *
	 * @group days_ratio
	 * @dataProvider provider_get_active_days_ratio
	 */
	public function test_get_active_days_ratio( $from_timestamp, $days_in_period, $days_active, $billing_period, $billing_interval, $expected_ratio ) {
		$this->assertEquals( $expected_ratio, wcsr_get_active_days_ratio( strtotime( $from_timestamp ), $days_in_period, $days_active, $billing_period, $billing_interval ) );
	}

	/**
	 * Procide data to test days in period
	 */
	public function provider_get_days_in_period() {
		return array(
			// end comes before start
			0 => array(
				'start_timestamp' => strtotime( '2017-09-14 14:21:40' ),
				'end_timestamp'   => strtotime( '2017-09-13 14:21:40' ),
				'expected_result' => 0,
			),

			// exactly 1 day
			1 => array(
				'start_timestamp' => strtotime( '2017-09-14 14:21:40' ),
				'end_timestamp'   => strtotime( '2017-09-15 14:21:40' ),
				'expected_result' => 1,
			),

			// just before 1 day
			2 => array(
				'start_timestamp' => strtotime( '2017-09-14 14:21:40' ),
				'end_timestamp'   => strtotime( '2017-09-15 14:20:40' ),
				'expected_result' => 0,
			),

			// just after 1 day
			3 => array(
				'start_timestamp' => strtotime( '2017-09-14 14:21:40' ),
				'end_timestamp'   => strtotime( '2017-09-15 14:22:40' ),
				'expected_result' => 1,
			),

			// standard 1 month renewal period
			4 => array(
				'start_timestamp' => strtotime( '2017-09-14 14:21:40' ),
				'end_timestamp'   => strtotime( '2017-10-14 14:26:30' ),
				'expected_result' => 30,
			),

			5 => array(
				'start_timestamp' => strtotime( '2017-09-14 14:21:40' ),
				'end_timestamp'   => strtotime( '2017-09-14 14:21:40' ),
				'expected_result' => 0,
			),
		);
	}

	/**
	 * Make sure get_days_in_period() is calculating the number of days properly
	 *
	 *
	 * @dataProvider provider_get_days_in_period
	 * @group days_in_period
	 */
	public function test_get_days_in_period( $start_timestamp, $end_timestamp, $expected_result ) {
		$this->assertEquals( $expected_result, wcsr_get_days_in_period( $start_timestamp, $end_timestamp ) );
	}

	/**
	 * Provide data to whether timestamp is on same day
	 */
	public function provider_is_on_same_day() {
		return array(

			// Exactly start of same day 00:00:00
			0 => array(
				'current_timestamp' => strtotime( '2017-09-14 09:13:14' ),
				'compare_timestamp' => strtotime( '2017-09-14 09:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => true,
			),

			// Just within the same day 23:59:59
			1 => array(
				'current_timestamp' => strtotime( '2017-09-15 09:13:13' ),
				'compare_timestamp' => strtotime( '2017-09-14 09:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => true,
			),

			// start of the next day 00:00:00
			2 => array(
				'current_timestamp' => strtotime( '2017-09-15 09:13:14' ),
				'compare_timestamp' => strtotime( '2017-09-14 09:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),

			// move comparison date and increase current second
			3 => array(
				'current_timestamp' => strtotime( '2017-09-20 09:13:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 09:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),

			// move comparison date and decrease current second
			4 => array(
				'current_timestamp' => strtotime( '2017-09-20 09:13:13' ),
				'compare_timestamp' => strtotime( '2017-09-19 09:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => true,
			),

			// move comparison date and increase current minute
			5 => array(
				'current_timestamp' => strtotime( '2017-09-20 09:15:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 09:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),

			// move comparison date and decrease current minute
			6 => array(
				'current_timestamp' => strtotime( '2017-09-20 09:12:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 09:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => true,
			),

			// move comparison date and increase current hour
			7 => array(
				'current_timestamp' => strtotime( '2017-09-20 10:13:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 09:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),

			// move comparison date and decrease current hour
			8 => array(
				'current_timestamp' => strtotime( '2017-09-20 08:13:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 09:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => true,
			),

			// move comparison date, decrease hour and increase current second
			9 => array(
				'current_timestamp' => strtotime( '2017-09-20 09:13:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 08:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),

			// move comparison date, decrease hour and and decrease current second
			10 => array(
				'current_timestamp' => strtotime( '2017-09-20 09:13:13' ),
				'compare_timestamp' => strtotime( '2017-09-19 08:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),

			// move comparison date, decrease hour and and increase current minute
			11 => array(
				'current_timestamp' => strtotime( '2017-09-20 09:15:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 08:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),

			// move comparison date, decrease hour and and decrease current minute
			12 => array(
				'current_timestamp' => strtotime( '2017-09-20 09:12:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 08:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),

			// move comparison date, decrease hour and and increase current hour
			13 => array(
				'current_timestamp' => strtotime( '2017-09-20 10:13:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 08:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),

			// move comparison date, decrease hour and set hour to just outside of 1 day (00:00:00)
			14 => array(
				'current_timestamp' => strtotime( '2017-09-20 08:13:14' ),
				'compare_timestamp' => strtotime( '2017-09-19 08:13:14' ),
				'start_timestamp'   => strtotime( '2017-09-14 09:13:14' ),
				'expected_result'   => false,
			),
		);
	}

	/**
	 * Make sure is_on_same_day() works
	 *
	 * @dataProvider provider_is_on_same_day
	 * @group is_on_same_day
	 */
	public function test_is_on_same_day( $current_timestamp, $compare_timestamp, $start_timestamp, $expected_result ) {
		$this->assertEquals( $expected_result, wcsr_is_on_same_day( $current_timestamp, $compare_timestamp, $start_timestamp ) );
	}
}
