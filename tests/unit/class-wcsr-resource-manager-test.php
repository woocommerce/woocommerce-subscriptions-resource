<?php

/**
 * Tests for WCSR_Resource_Manager_Test
 */
class WCSR_Resource_Manager_Test extends WCSR_Unit_TestCase {

	protected static $product_total     = 9.00;
	protected static $product_total_tax = 0.00;
	protected static $product_name      = 'Robot Ninja';

	/**
	 * Provide data to test get_prorated_resource_line_item
	 */
	public function provider_get_prorated_resource_line_item() {
		return array (
			// store is active for 24 days out of the 30 days in the period
			0 => array(
				'days_in_period' => 30,
				'days_active'    => 24,
				'days_active_ratio' => .8,
				'expected_data' => array(
					'line_item_name'  => self::$product_name . ' usage for 24 of 30 days.',
					'line_item_props' => array (
						'subtotal'     => 7.20,
						'total'        => 7.20,
						'subtotal_tax' => 0,
						'total_tax'    => 0,
						'taxes'        => array(),
					)
				),
			),

			1 => array(
				'days_in_period' => 30,
				'days_active'    => 30,
				'days_active_ratio' => 1,
				'expected_data' => array(
					'line_item_name'  => self::$product_name,
					'line_item_props' => array() // uses the existing line item totals from the produce (i.e. no proration required)
				),
			),

			2 => array(
				'days_in_period' => 31,
				'days_active'    => 31,
				'days_active_ratio' => 1,
				'expected_data' => array(
					'line_item_name'  => self::$product_name,
					'line_item_props' => array() // uses the existing line item totals from the produce (i.e. no proration required)
				),
			),

			3 => array(
				'days_in_period' => 31,
				'days_active'    => 1,
				'days_active_ratio' => 0.03,
				'expected_data' => array(
					'line_item_name'  => self::$product_name . ' usage for 1 of 31 days.',
					'line_item_props' => array (
						'subtotal'     => 0.27,
						'total'        => 0.27,
						'subtotal_tax' => 0,
						'total_tax'    => 0,
						'taxes'        => array(),
					)
				),
			)
		);
	}

	/**
	 * Make sure get_prorated_resource_line_item() correctly calculates the line item totals
	 *
	 * @dataProvider provider_get_prorated_resource_line_item
	 * @group prorated_line_items
	 */
	public function test_get_prorated_resource_line_item( $days_in_period, $days_active, $days_active_ratio, $expected_data ) {
		// create a copy of WC_Order_Item_Product and set args and line item name to be used as the expected result
		$expected_result        = new WC_Order_Item_Product();
		$expected_result->name  = $expected_data['line_item_name'];
		$expected_result->props = $expected_data['line_item_props'];

		// Mock the line item object and line item methods used within WCSR_Resource_Manager::get_prorated_resource_line_item() function
		$line_item_mock = $this->getMockBuilder( 'WC_Order_Item_Product' )->setMethods( array( 'set_name', 'set_props', 'get_name', 'get_taxes', 'get_subtotal', 'get_total', 'get_subtotal_tax', 'get_total_tax' ) )->disableOriginalConstructor()->getMock();
		$line_item_mock->expects( $this->any() )->method( 'get_taxes' )->will( $this->returnValue( array() ) );
		$line_item_mock->expects( $this->any() )->method( 'get_subtotal' )->will( $this->returnValue( self::$product_total ) );
		$line_item_mock->expects( $this->any() )->method( 'get_total' )->will( $this->returnValue( self::$product_total ) );
		$line_item_mock->expects( $this->any() )->method( 'get_subtotal_tax' )->will( $this->returnValue( self::$product_total_tax ) );
		$line_item_mock->expects( $this->any() )->method( 'get_total_tax' )->will( $this->returnValue( self::$product_total_tax ) );
		$line_item_mock->expects( $this->any() )->method( 'get_name' )->will( $this->returnValue( self::$product_name ) );

		// mock the resource manager class and a resource object for sending to get_prorated_resource_line_item
		$resource_manager_mock = $this->getMockBuilder( 'WCSR_Resource_Manager' )->setMethods( array( 'get_prorated_resource_line_item' ) )->disableOriginalConstructor()->getMock();
		$resource_mock         = $this->getMockBuilder( 'WCSR_Resource' )->setMethods( array( 'get_date_created' ) )->disableOriginalConstructor()->getMock();

		$this->assertEquals( $expected_result, $this->get_accessible_protected_method( 'WCSR_Resource_Manager', 'get_prorated_resource_line_item' )->invoke( $resource_manager_mock, $resource_mock, $line_item_mock, $days_in_period, $days_active, $days_active_ratio ) );
	}
}
