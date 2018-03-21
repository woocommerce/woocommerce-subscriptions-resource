<?php

/**
 * Empty method for wcs_copy_order_item use for testing
 */
if ( ! function_exists( 'wcs_copy_order_item' ) ) {
	function wcs_copy_order_item() {
		// do nothing
	}
}

/**
 * Mock an empty version of add_action for testing purposes
 */
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		// do nothing
	}
}

/**
 * Mock an empty version of add_filter for testing purposes
 */
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {
		// do nothing
	}
}

/**
 * Mock a basic version of apply_filters for the purpose of testing
 */
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters() {
		$args = func_get_args();

		return $args[1];
	}
}
