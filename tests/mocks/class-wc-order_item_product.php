<?php
/**
 * An part-empty WC_Order_Item_Product class for use with unit tests
 *
 * We don't want our unit tests to depend on anything in the parent
 * WC_Order_Item_Product class, so we can simply define a basic version of the class here for use
 * with testing.
 */
class WC_Order_Item_Product {

	/*
	 * @var Line Item name -
	 */
	public $name = '';

	/*
	 * @var Line Item name -
	 */
	public $props = array();

	/**
	 * Get an instance for a given resource
	 *
	 * @return null
	 */
	public function __construct() {
	}

	/**
	 * Stub method for set_name
	 */
	public function set_name( $name ) {
		$this->name = $name;
	}

	/**
	 * Stub method for set_props
	 */
	public function set_props( $props ) {
		$this->props = $props;
	}
}
