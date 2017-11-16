<?php
/**
 * WCSR Unit TestCase class
 * @since 1.1.0
 */
class WCSR_Unit_TestCase extends PHPUnit_Framework_TestCase {

	/**
	 * A utility function to make certain methods public. This is useful for testing protected methods.
	 */
	protected function get_accessible_protected_method( $object, $method_name ) {
		$reflected_object = new ReflectionClass( $object );
		$reflected_method = $reflected_object->getMethod( $method_name );
		$reflected_method->setAccessible( true );
		return $reflected_method;
	}
}
