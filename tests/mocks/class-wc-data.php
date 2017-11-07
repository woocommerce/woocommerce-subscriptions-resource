<?php
/**
 * An empty WC_Data class for use with unit tests
 *
 * We don't want our unit tests to depend on anything in the parent
 * WC_Data class, so we can simply define an empty class here for use
 * with testing.
 */
class WC_Data {

	/**
	 * Get an instance for a given resource
	 *
	 * @return null
	 */
	public function __construct( $resource ) {
	}
}