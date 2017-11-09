<?php

/**
 * Test the WCS_Retry class's public methods
 */
class WCSR_Resource_Test extends PHPUnit_Framework_TestCase {

	protected static $from_timestamp;

	protected static $to_timestamp;

	public static function setUpBeforeClass() {
		self::$from_timestamp = strtotime( '2017-09-14 09:13:14' );
		self::$to_timestamp   = strtotime( '2017-10-14 09:14:02' );
	}

	/**
	 * Provide data to test days active
	 */
	public function provider_get_days_active() {

		return array(

			/*
			 * Simulate a new resource that is active for only the first week during its first cycle.
			 *
			 * To test this requires a resource that is:
			 * 0. created at the same time as the start of the period being checked ($from_timestamp)
			 * 1. activate at the time it is created
			 * 2. active for at least 1 second more than 6 * 24 * 60, then deactivated
			 */
			0 => array(
				'date_created'         => '2017-09-14 09:13:14', // same as $from_timestamp
				'activation_times'     => array( '2017-09-14 09:13:14' ), // same as $from_timestamp
				'deactivation_times'   => array( '2017-09-20 11:01:40' ),
				'expected_days_active' => 7,
			),

			/*
			 * Simulate an existing active resource that is active for 10 days at the start of its 2nd cycle.
			 *
			 * To test this requires a resource that is:
			 * 0. created prior to the start of the period being checked ($creation_time < $from_timestamp)
			 * 1. activate at the start of the period being checked
			 * 2. active for at least 1 second more than 9 * 24 * 60 during the period then deactivated
			 */
			1 => array(
				'date_created'         => '2017-08-14 09:13:14', // 1 month prior to $from_timestamp
				'activation_times'     => array(),
				'deactivation_times'   => array( '2017-09-23 11:13:40' ),
				'expected_days_active' => 10,
			),

			/*
			 * Simulate an existing inactive resource that is active for 10 days in the middle of its 2nd cycle.
			 *
			 * To test this requires a resource that is:
			 * 0. created prior to the start of the period being checked ($creation_time < $from_timestamp)
			 * 1. first activation timestamp after the start time of the period being checked ($activation_times[0] > $from_timestamp)
			 * 2. active for at least 1 second more than 9 * 24 * 60 during the period then deactivated
			 */
			2 => array(
				'date_created'         => '2017-08-14 09:13:14', // 1 month prior to $from_timestamp
				'activation_times'     => array( '2017-09-24 09:13:14' ), // 10 days after $from_timestamp
				'deactivation_times'   => array( '2017-10-03 11:13:40' ), // 9 days, 2 hours, 26 seconds after activation timestamp
				'expected_days_active' => 10,
			),

			/*
			 * Simulate a new resource that is active for multiple different periods during its first cycle with a total of 10 days.
			 *
			 * To test this requires a resource that is:
			 * 0. created at the same time as the start of the period being checked ($from_timestamp)
			 * 1. activate at the time it is created
			 * 2. active for 2 days, then deactivated for 2 days
			 * 3. active for 2 days, then deactivated for 2 days
			 * 4. active for 2 days, then deactivated for 2 days
			 * 5. active for 2 days, then deactivated for 2 days
			 * 6. activated again for the rest of the cycle
			 */
			3 => array(
				'date_created'         => '2017-09-14 09:13:14',
				'activation_times'     => array( '2017-09-14 09:13:14', '2017-09-18 09:13:14', '2017-09-22 09:13:14', '2017-10-26 09:13:14', '2017-10-30 09:14:02' ),
				'deactivation_times'   => array( '2017-09-16 09:13:13', '2017-09-20 09:13:13', '2017-09-24 09:13:13', '2017-10-28 09:13:13' ),
				'expected_days_active' => 6,
			),

			/*
			 * Simulate a new resource that is active for multiple different periods during its first cycle with a total of 10 days.
			 *
			 * To test this requires a resource that is:
			 * 0. created at the same time as the start of the period being checked ($from_timestamp)
			 * 1. activate at the time it is created
			 * 2. active for at least 1 second more than 4 * 24 * 60, then deactivated
			 * 3. actived again for at least 1 second more than 4 * 24 * 60, then deactivated before the end of the cycle ($to_timestamp)
			 */
			4 => array(
				'date_created'         => '2017-09-14 09:13:14',
				'activation_times'     => array( '2017-09-14 09:13:14', '2017-09-20 09:13:14' ),
				'deactivation_times'   => array( '2017-09-18 09:13:15', '2017-09-24 09:13:15' ),
				'expected_days_active' => 10,
			),

			/*
			 * Simulate an existing active resource that is active for multiple different occasions during its 2nd cycle for a total of 10 days.
			 *
			 * To test this requires a resource that is:
			 * 0. created prior to the start of the period being checked ($creation_time < $from_timestamp)
			 * 1. active at the start of the period being checked
			 * 2. active for at least 1 second more than 4 * 24 * 60, then deactivated
			 * 3. activated again for at least 1 second more than 4 * 24 * 60, then deactivated before the end of the cycle ($to_timestamp)
			 */
			5 => array(
				'date_created'         => '2017-08-14 09:13:14', // 1 month prior to $from_timestamp
				'activation_times'     => array( '2017-09-30 09:13:14' ), // previously activated in the last cycle
				'deactivation_times'   => array( '2017-09-18 10:14:15', '2017-10-04 12:24:10' ),
				'expected_days_active' => 10,
			),

			/*
			 * Simulate an existing inactive resource that is actived for multiple different occasions during its 2nd cycle for a total of 10 days.
			 *
			 * To test this requires a resource that is:
			 * 0. created prior to the start of the period being checked ($creation_time < $from_timestamp)
			 * 1. inactive at the start of the period being checked
			 * 2. activated for at least 1 second more than 4 * 24 * 60, then deactivated more than 5 * 24 * 60 before the end of the cycle ($to_timestamp)
			 * 3. activated again for at least 1 second more than 4 * 24 * 60, then deactivated before the end of the cycle ($to_timestamp)
			 */
			6 => array(
				'date_created'         => '2017-08-14 09:13:14', // 1 month prior to $from_timestamp
				'activation_times'     => array( '2017-09-26 09:13:14', '2017-10-05 09:13:14' ), // previously activated in the last cycle
				'deactivation_times'   => array( '2017-09-30 15:35:43', '2017-10-09 12:24:10' ),
				'expected_days_active' => 10,
			),

			/*
			 * Simulate a new active resource that is activated and deactivated for multiple occasions on the same day.
			 *
			 * To test this requires a resource that is:
			 * 0. created at the same time as the start of the period being checked ($from_timestamp)
			 * 1. activate at the time it is created
			 * 2. activated for just over an hour, then deactivated for 2'ish hours
			 * 3. activated 4 hours then deactivated for the rest of the cycle
			 */
			7 => array(
				'date_created'         => '2017-09-14 09:13:14',
				'activation_times'     => array( '2017-09-14 09:13:14', '2017-09-14 13:13:14' ),
				'deactivation_times'   => array( '2017-09-14 10:35:43', '2017-09-14 17:24:10' ),
				'expected_days_active' => 1,
			),

			/*
			 * Simulate a new active resource that is active for the full cycle
			 *
			 * To test this requires a resource that is:
			 * 0. created at the same time as the start of the period being checked ($from_timestamp)
			 */
			8 => array(
				'date_created'         => '2017-09-14 09:13:14', // same as $from_timestamp
				'activation_times'     => array( '2017-09-14 09:13:14' ),
				'deactivation_times'   => array(),
				'expected_days_active' => 31,
			),

			/*
			 * Simulate an existing active resource that is active for full cycle
			 *
			 * To test this requires a resource that is:
			 * 0. created prior to the start of the period being checked ($creation_time < $from_timestamp)
			 * 1. active before the start of the period being checked
			 */
			9 => array(
				'date_created'         => '2017-08-14 09:13:14',
				'activation_times'     => array(),
				'deactivation_times'   => array(),
				'expected_days_active' => 31,
			),

			/*
			 * Simulate a new active resource that is activated and deactivated for multiple occasions on the same day and then left active for 2 days.
			 *
			 * To test this requires a resource that is:
			 * 0. created at the same time as the start of the period being checked ($from_timestamp)
			 * 1. activate at the time it is created
			 * 2. activated for just over an hour, then deactivated for 2'ish hours
			 * 3. activated 4 hours then deactivated for the rest of the cycle
			 */
			10 => array(
				'date_created'         => '2017-09-14 09:13:14',
				'activation_times'     => array( '2017-09-14 09:13:14', '2017-09-14 13:13:14', '2017-09-14 20:00:03' ),
				'deactivation_times'   => array( '2017-09-14 10:35:43', '2017-09-14 17:24:10', '2017-09-16 17:24:10' ),
				'expected_days_active' => 2,
			),
		);
	}

	/**
	 * Make sure get_days_active() handles all calculation scenarios
	 *
	 * @dataProvider provider_get_days_active
	 */
	public function test_get_days_active( $date_created_string, $activation_times, $deactivation_times, $expected_days_active ) {

		$date_created = new DateTime();
		$date_created->setTimestamp( strtotime( $date_created_string ) );

		// Convert activation/deactivate dates to timestamps
		$activation_times   = array_map( 'strtotime', $activation_times );
		$deactivation_times = array_map( 'strtotime', $deactivation_times );

		$resource_mock = $this->getMockBuilder( 'WCSR_Resource' )->setMethods( array( 'get_date_created', 'has_been_activated', 'get_activation_timestamps', 'get_deactivation_timestamps' ) )->disableOriginalConstructor()->getMock();
		$resource_mock->expects( $this->any() )->method( 'get_date_created' )->will( $this->returnValue( $date_created ) );
		$resource_mock->expects( $this->any() )->method( 'has_been_activated' )->will( $this->returnValue( true ) );
		$resource_mock->expects( $this->any() )->method( 'get_activation_timestamps' )->will( $this->returnValue( $activation_times ) );
		$resource_mock->expects( $this->any() )->method( 'get_deactivation_timestamps' )->will( $this->returnValue( $deactivation_times ) );

		$this->assertEquals( $expected_days_active, $resource_mock->get_days_active( self::$from_timestamp, self::$to_timestamp ) );
	}
}